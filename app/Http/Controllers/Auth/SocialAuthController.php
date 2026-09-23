<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Auth\SocialAccountResolver;
use App\Support\LoginFlow;
use App\Support\LoginReturn;
use App\Support\SocialAuth;
use App\Support\SocialIntent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Google / Apple ile giriş, kayıt ve profilden hesap bağlama.
 *
 * Dört şey bilinçli olarak böyle:
 *  - Girişten sonraki yönlendirme LoginFlow'a bırakılır; şifreli girişle birebir
 *    aynı davranır (admin/acenta paneli, onay bekleyen acenta, D4 silme talebinin
 *    iptali, kalpten gelen ziyaretçinin turuna dönmesi).
 *  - Geldiği sayfa / bekleyen favori / bağlama modu SocialIntent çerezinde taşınır,
 *    oturumda değil — Apple çapraz site POST ile döner (bkz. SocialIntent).
 *  - Apple sürücüsü stateless + cookieNonce çalışır: aynı sebeple oturumdaki
 *    state doğrulanamaz, CSRF koruması SameSite=none nonce çerezine bağlanır.
 *  - Sağlayıcı hatası kullanıcıya ham istisna olarak gösterilmez; log'a yazılır,
 *    kullanıcı anlaşılır bir mesajla giriş sayfasına döner.
 */
class SocialAuthController extends Controller
{
    public function __construct(private SocialAccountResolver $resolver) {}

    /** Sağlayıcıya yönlendir. Giriş yapılmışsa "bağlama" modu. */
    public function redirect(Request $request, string $provider): RedirectResponse
    {
        $this->guardProvider($provider);

        SocialIntent::queue(
            $provider,
            LoginReturn::safePath($request->input('next')),
            ctype_digit((string) $request->input('favori')) ? (int) $request->input('favori') : null,
            $request->user()?->id,
        );

        try {
            return $this->driver($provider)->redirect();
        } catch (Throwable $e) {
            Log::warning('Sosyal giriş yönlendirmesi başarısız', ['provider' => $provider, 'error' => $e->getMessage()]);

            return $this->fail($provider, 'Şu anda '.SocialAuth::label($provider).' ile giriş yapılamıyor. Lütfen e-posta ile deneyin.');
        }
    }

    /** Sağlayıcı dönüşü. Apple form_post ile POST atar; route ikisini de kabul eder. */
    public function callback(Request $request, string $provider): RedirectResponse
    {
        $this->guardProvider($provider);

        $intent = SocialIntent::pull($request, $provider);

        // Kullanıcı sağlayıcı ekranında "İptal"e bastı: hata değil, sessiz dönüş.
        if ($request->filled('error')) {
            return $this->cancelled($intent);
        }

        try {
            $socialite = $this->driver($provider)->user();
        } catch (Throwable $e) {
            Log::warning('Sosyal giriş callback başarısız', ['provider' => $provider, 'error' => $e->getMessage()]);

            return $this->fail($provider, SocialAuth::label($provider).' ile giriş tamamlanamadı. Lütfen tekrar deneyin.');
        }

        if ((string) $socialite->getId() === '') {
            return $this->fail($provider, SocialAuth::label($provider).' kimlik bilgisi alınamadı. Lütfen tekrar deneyin.');
        }

        $linkTarget = $this->linkTarget($request, $intent);

        return $linkTarget
            ? $this->handleLink($linkTarget, $provider, $socialite)
            : $this->handleLogin($request, $provider, $socialite, $intent);
    }

    /** Profilden bağlantıyı kaldır. */
    public function destroy(Request $request, string $provider): RedirectResponse
    {
        $this->guardProvider($provider, requireEnabled: false);

        $user = $request->user();
        $account = $user->socialAccounts()->where('provider', $provider)->first();

        if (! $account) {
            return back();
        }

        // Tek giriş yolunu koparmayalım: şifresi olmayan ve başka sağlayıcısı
        // bulunmayan kullanıcı bağlantıyı kaldırırsa hesabına bir daha giremez.
        if (! $user->hasPassword() && $user->socialAccounts()->count() === 1) {
            return back()->withErrors([
                'social' => 'Bu tek giriş yönteminiz. Kaldırmadan önce bir şifre belirleyin.',
            ]);
        }

        $account->delete();

        return back()->with('success', SocialAuth::label($provider).' bağlantısı kaldırıldı.');
    }

    /**
     * Bağlama modundaki kullanıcı.
     *
     * Önce oturum (Google dönüşünde vardır), sonra SocialIntent çerezindeki id
     * (Apple'ın çapraz site POST'unda oturum çerezi gelmez). Çerez şifreli ve
     * HttpOnly olduğu için istemci bu id'yi üretemez.
     *
     * @param  array{next: ?string, favori: ?int, link: ?int}  $intent
     */
    private function linkTarget(Request $request, array $intent): ?User
    {
        if ($intent['link'] === null) {
            return null;
        }

        if ($request->user()?->id === $intent['link']) {
            return $request->user();
        }

        // Oturum yoksa (Apple) çerezdeki kullanıcıya bak; oturum VARSA ve başka
        // birineyse bağlama yapılmaz — bağlamı başlatan kullanıcı o değil.
        return $request->user() === null ? User::find($intent['link']) : null;
    }

    /**
     * @param  \Laravel\Socialite\Contracts\User  $socialite
     * @param  array{next: ?string, favori: ?int, link: ?int}  $intent
     */
    private function handleLogin(Request $request, string $provider, $socialite, array $intent): RedirectResponse
    {
        $outcome = $this->resolver->resolveForLogin($provider, $socialite);

        if ($outcome['conflict'] === SocialAccountResolver::CONFLICT_NO_EMAIL) {
            return $this->fail($provider, SocialAuth::label($provider).' hesabınızdan e-posta adresi alınamadı. E-posta paylaşımına izin verip tekrar deneyin.');
        }

        if ($outcome['conflict'] === SocialAccountResolver::CONFLICT_UNVERIFIED) {
            return $this->fail($provider, $outcome['email'].' adresi zaten kayıtlı. Şifrenizle giriş yapıp profilinizden '.SocialAuth::label($provider).' hesabınızı bağlayabilirsiniz.');
        }

        $user = $outcome['user'];

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        // LoginFlow/LoginReturn bağlamı istek parametrelerinden okur; sağlayıcı
        // dönüşünde bunlar yok, çerezde sakladıklarımızı geri koyuyoruz.
        $request->merge(['next' => $intent['next'], 'favori' => $intent['favori']]);

        return LoginFlow::redirectAfterLogin(
            $user,
            $request,
            $outcome['created']
                ? 'Hoş geldiniz! Hesabınız '.SocialAuth::label($provider).' ile oluşturuldu.'
                : null
        );
    }

    /** @param \Laravel\Socialite\Contracts\User $socialite */
    private function handleLink(User $user, string $provider, $socialite): RedirectResponse
    {
        $outcome = $this->resolver->resolveForLink($user, $provider, $socialite);

        if ($outcome['conflict'] === SocialAccountResolver::CONFLICT_ALREADY_LINKED) {
            return redirect()->route('profile.security')->withErrors([
                'social' => 'Bu '.SocialAuth::label($provider).' hesabı başka bir turXtur hesabına bağlı.',
            ]);
        }

        return redirect()->route('profile.security')
            ->with('success', SocialAuth::label($provider).' hesabınız bağlandı.');
    }

    /** @param array{next: ?string, favori: ?int, link: ?int} $intent */
    private function cancelled(array $intent): RedirectResponse
    {
        if ($intent['link'] !== null) {
            return redirect()->route('profile.security');
        }

        return redirect()->route('login', array_filter([
            'next' => $intent['next'],
            'favori' => $intent['favori'],
        ]));
    }

    private function fail(string $provider, string $message): RedirectResponse
    {
        $route = Auth::check() ? 'profile.security' : 'login';

        return redirect()->route($route)->withErrors(['social' => $message]);
    }

    /**
     * Apple'ın sürücüsü stateless çalışmak ZORUNDA: kimlik doğrulama sonucu
     * çapraz site bir POST ile döner ve SameSite=lax oturum çerezi o isteğe
     * eklenmez, dolayısıyla oturumdaki state hiçbir zaman doğrulanamaz.
     * cookieNonce() CSRF korumasını SameSite=none şifreli bir nonce çerezine
     * bağlar (RFC 9700) — koruma kaybolmaz, yalnız taşıyıcı değişir.
     */
    private function driver(string $provider)
    {
        $driver = Socialite::driver($provider);

        if ($provider === SocialAccount::PROVIDER_APPLE) {
            return $driver->stateless()->cookieNonce();
        }

        return $driver;
    }

    private function guardProvider(string $provider, bool $requireEnabled = true): void
    {
        if (! in_array($provider, SocialAccount::PROVIDERS, true)) {
            throw new NotFoundHttpException;
        }

        if ($requireEnabled && ! SocialAuth::enabled($provider)) {
            throw new NotFoundHttpException;
        }
    }
}
