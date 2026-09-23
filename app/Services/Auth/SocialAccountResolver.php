<?php

namespace App\Services\Auth;

use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;

/**
 * Sağlayıcıdan dönen kimliği yerel kullanıcıya çevirir: bul / bağla / oluştur.
 *
 * Buradaki tek kritik karar e-posta çakışması: ali@gmail.com zaten şifreyle
 * kayıtlıyken Google ile geliniyor. Sağlayıcı e-postayı DOĞRULAMIŞSA (Google
 * email_verified / Apple email_verified) otomatik bağlanır; doğrulamamışsa
 * bağlanmaz, çünkü doğrulanmamış bir adresle başkasının hesabı devralınabilir.
 * O durumda kullanıcı şifresiyle girip profilinden bağlar.
 */
class SocialAccountResolver
{
    /** Sağlayıcı e-postayı doğrulamamış ve aynı e-posta yerelde kayıtlı. */
    public const CONFLICT_UNVERIFIED = 'unverified_conflict';

    /** Sağlayıcı e-posta vermedi (Apple'da kullanıcı paylaşmayı reddedebilir). */
    public const CONFLICT_NO_EMAIL = 'no_email';

    /** Sağlayıcı hesabı BAŞKA bir kullanıcıya bağlı (bağlama modunda). */
    public const CONFLICT_ALREADY_LINKED = 'already_linked';

    public function __construct(private SocialUserData $data) {}

    /**
     * Giriş / kayıt yolu.
     *
     * @return array{user: ?User, created: bool, conflict: ?string, email: ?string}
     */
    public function resolveForLogin(string $provider, SocialiteUser $socialite): array
    {
        $identity = $this->data->normalize($provider, $socialite);

        $existing = $this->findAccount($provider, $identity['provider_user_id']);

        if ($existing) {
            $this->touchAccount($existing, $identity);

            return $this->result($existing->user, false, null, $identity['email']);
        }

        if ($identity['email'] === null) {
            return $this->result(null, false, self::CONFLICT_NO_EMAIL, null);
        }

        $byEmail = User::where('email', $identity['email'])->first();

        if ($byEmail) {
            if (! $identity['email_verified']) {
                return $this->result(null, false, self::CONFLICT_UNVERIFIED, $identity['email']);
            }

            // D4: silme talebi verilmiş hesap açılır (LoginFlow talebi iptal eder);
            // anonimleştirilmiş hesaba ise bağlanılmaz — e-postası artık .invalid
            // olduğu için buraya zaten düşmez.
            $this->linkAccount($byEmail, $provider, $identity);

            return $this->result($byEmail, false, null, $identity['email']);
        }

        return $this->result($this->createUser($provider, $identity), true, null, $identity['email']);
    }

    /**
     * Giriş yapmış kullanıcının profilinden hesap bağlaması.
     *
     * @return array{user: ?User, created: bool, conflict: ?string, email: ?string}
     */
    public function resolveForLink(User $user, string $provider, SocialiteUser $socialite): array
    {
        $identity = $this->data->normalize($provider, $socialite);

        $existing = $this->findAccount($provider, $identity['provider_user_id']);

        if ($existing) {
            if ($existing->user_id !== $user->id) {
                return $this->result(null, false, self::CONFLICT_ALREADY_LINKED, $identity['email']);
            }

            $this->touchAccount($existing, $identity);

            return $this->result($user, false, null, $identity['email']);
        }

        $this->linkAccount($user, $provider, $identity);

        return $this->result($user, false, null, $identity['email']);
    }

    private function findAccount(string $provider, string $providerUserId): ?SocialAccount
    {
        return SocialAccount::with('user')
            ->where('provider', $provider)
            ->where('provider_user_id', $providerUserId)
            ->first();
    }

    /** @param array{provider_user_id: string, email: ?string, avatar: ?string} $identity */
    private function touchAccount(SocialAccount $account, array $identity): void
    {
        $account->forceFill([
            'email' => $identity['email'] ?? $account->email,
            'avatar' => $identity['avatar'] ?? $account->avatar,
            'last_login_at' => now(),
        ])->save();
    }

    /** @param array{provider_user_id: string, email: ?string, avatar: ?string} $identity */
    private function linkAccount(User $user, string $provider, array $identity): SocialAccount
    {
        return SocialAccount::create([
            'user_id' => $user->id,
            'provider' => $provider,
            'provider_user_id' => $identity['provider_user_id'],
            'email' => $identity['email'],
            'avatar' => $identity['avatar'],
            'last_login_at' => now(),
        ]);
    }

    /**
     * @param  array{provider_user_id: string, name: ?string, email: string, avatar: ?string}  $identity
     */
    private function createUser(string $provider, array $identity): User
    {
        return DB::transaction(function () use ($provider, $identity) {
            $user = User::create([
                'name' => $identity['name'] ?: Str::before($identity['email'], '@'),
                'email' => $identity['email'],
                // Rastgele şifre — kullanıcı bilmez, password_set_at null kalır.
                // null BIRAKILMAZ: AuthenticateSession her istekte bu hash'i
                // karşılaştırır (bkz. add_password_set_at migration'ı).
                'password' => Hash::make(Str::random(48)),
                // Sosyal giriş ile açılan hesapta rol DAİMA ziyaretçi: acenta
                // hesabı acenta adı + admin onayı isteyen ayrı bir akış.
                'role' => User::ROLE_VISITOR,
            ]);

            // Sağlayıcı e-postayı doğruladı; ayrıca doğrulama maili göndermeyiz.
            if ($identity['email_verified']) {
                $user->forceFill(['email_verified_at' => now()])->save();
            }

            $this->linkAccount($user, $provider, $identity);

            return $user;
        });
    }

    /** @return array{user: ?User, created: bool, conflict: ?string, email: ?string} */
    private function result(?User $user, bool $created, ?string $conflict, ?string $email): array
    {
        return ['user' => $user, 'created' => $created, 'conflict' => $conflict, 'email' => $email];
    }
}
