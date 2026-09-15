<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A5 — Tarayıcı güvenlik başlıkları.
 *
 * Panel tek tıkla çalışan POST formlarıyla dolu (onayla/iptal/kart sil); bu
 * sayfaların yabancı bir siteye iframe olarak gömülmesi clickjacking demek.
 * Laravel bu başlıkların hiçbirini kendiliğinden göndermez.
 *
 * - X-Frame-Options: SAMEORIGIN (DENY değil): iyzico Checkout Form kendi
 *   iframe'inin içinden /iyzico-callback'e döner; o sayfa bizim origin'de,
 *   üst pencere de bizim — SAMEORIGIN buna izin verir, DENY kırardı.
 * - CSP yalnız Report-Only: panel satır içi stil/script ile yazılmış (bkz. A3);
 *   sıkı bir CSP bugün ekranı boşaltır. Önce konsolda neyin engelleneceği
 *   görülür, A3 ilerledikçe liste daraltılıp enforce moduna geçilir.
 * - HSTS yalnız canlı + HTTPS: yerelde (http://127.0.0.1) asla gönderilmez;
 *   bir kez gönderilince tarayıcı süresi boyunca HTTP'yi reddeder.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = $response->headers;

        $headers->set('X-Frame-Options', 'SAMEORIGIN');
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(self)');

        if (app()->isProduction() && $request->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // frame-ancestors, X-Frame-Options'ın modern karşılığı; enforce edilir.
        // Geri kalan kaynak politikası report-only: satır içi stil/script serbest,
        // dış kaynaklar bilinen CDN'lerle sınırlı. Engelleme yok, yalnız konsol raporu.
        $headers->set('Content-Security-Policy', "frame-ancestors 'self'");
        $headers->set('Content-Security-Policy-Report-Only', implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://unpkg.com",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://unpkg.com",
            "font-src 'self' data: https://fonts.gstatic.com",
            "img-src 'self' data: blob: https:",
            "connect-src 'self'",
            "frame-src 'self' https://*.iyzipay.com https://*.iyzico.com",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self' https://*.iyzipay.com https://*.iyzico.com",
        ]));

        return $response;
    }
}
