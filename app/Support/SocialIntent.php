<?php

namespace App\Support;

use App\Models\SocialAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

/**
 * Sosyal girişe gitmeden önceki bağlamı (geldiği sayfa, bekleyen favori, bağlama
 * modu) sağlayıcı dönüşüne kadar taşır.
 *
 * Neden oturum değil de çerez: Apple kimlik doğrulamayı form_post ile, yani
 * ÇAPRAZ SİTE bir POST isteğiyle geri gönderir. Oturum çerezimiz SameSite=lax
 * (bkz. config/session.php) olduğundan tarayıcı onu bu isteğe eklemez —
 * oturuma yazılan her şey Apple dönüşünde yok sayılırdı: kalpten gelen ziyaretçi
 * turunu kaybeder, profilden bağlama ise kimin bağladığını bilemeyip kullanıcıya
 * İKİNCİ bir hesap açardı.
 *
 * Bu yüzden bağlam kendi çerezimizde durur ve SameSite sağlayıcıya göre seçilir:
 * Apple'da none+secure (çapraz site POST), diğerlerinde lax (üst düzey GET
 * yönlendirmesinde zaten gönderilir). Çerez EncryptCookies ile şifrelenir ve
 * HttpOnly'dir; istemci ne okuyabilir ne de üretebilir.
 */
final class SocialIntent
{
    public const COOKIE = 'social_auth_intent';

    /** Giriş akışı birkaç dakika sürer; kısa ömür artık çerezleri bırakmaz. */
    private const TTL_MINUTES = 15;

    public static function queue(string $provider, ?string $next, ?int $favori, ?int $linkUserId): void
    {
        $payload = json_encode([
            'p' => $provider,
            'next' => $next,
            'favori' => $favori,
            'link' => $linkUserId,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $apple = $provider === SocialAccount::PROVIDER_APPLE;

        Cookie::queue(Cookie::make(
            name: self::COOKIE,
            value: (string) $payload,
            minutes: self::TTL_MINUTES,
            path: '/',
            domain: null,
            // SameSite=none çerezleri tarayıcılar yalnız Secure ile kabul eder.
            secure: $apple ? true : (config('session.secure') ?? request()->isSecure()),
            httpOnly: true,
            raw: false,
            sameSite: $apple ? 'none' : 'lax',
        ));
    }

    /**
     * Bağlamı oku ve çerezi düşür (tek kullanımlık).
     *
     * @return array{next: ?string, favori: ?int, link: ?int}
     */
    public static function pull(Request $request, string $provider): array
    {
        Cookie::queue(Cookie::forget(self::COOKIE, '/'));

        $raw = $request->cookie(self::COOKIE);
        $data = is_string($raw) ? json_decode($raw, true) : null;

        // Sağlayıcı eşleşmiyorsa bağlam başka bir akışa ait; kullanılmaz.
        if (! is_array($data) || ($data['p'] ?? null) !== $provider) {
            return ['next' => null, 'favori' => null, 'link' => null];
        }

        return [
            'next' => LoginReturn::safePath($data['next'] ?? null),
            'favori' => is_int($data['favori'] ?? null) ? $data['favori'] : null,
            'link' => is_int($data['link'] ?? null) ? $data['link'] : null,
        ];
    }
}
