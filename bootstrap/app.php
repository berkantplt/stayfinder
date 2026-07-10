<?php

use App\Http\Middleware\EnsureAgencyApproved;
use App\Http\Middleware\RoleMiddleware;
use App\Providers\PartnerServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'api',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'agency.approved' => EnsureAgencyApproved::class,
        ]);

        // Trust all proxies (required for Cloudflare tunnel / ngrok)
        $middleware->trustProxies(at: '*');

        // iyzico kendi session'umuzu bilmiyor; callback POST'unu CSRF'den muaf tut.
        $middleware->validateCsrfTokens(except: [
            'iyzico-callback/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // 419 (oturum/CSRF zaman aşımı): soğuk hata sayfası yerine giriş
        // sayfasına anlaşılır bir mesajla yönlendir. AJAX istekleri JSON
        // beklediği için onlara 419 aynen döner (istemci kendi ele alır).
        // Not: framework TokenMismatchException'ı render callback'lerinden
        // ÖNCE HttpException(419)'a çevirir — o yüzden statü koduyla yakalanır.
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e, Request $request) {
            if ($e->getStatusCode() !== 419) {
                return null; // diğer HTTP hataları normal akışında kalsın
            }

            // AJAX/fetch/SSE istekleri login HTML redirect'i değil, JSON 419
            // beklemeli. Aksi halde fetch redirect'i sessizce izler, chat/arama
            // widget'ı yanlış "bağlantı kesildi" gösterir ve tekrar deneme sonsuza
            // dek başarısız olur. Accept başlığında json VEYA event-stream olan
            // (ya da X-Requested-With ile AJAX işaretli) istekler JSON alır.
            $accept = (string) $request->header('Accept', '');
            $wantsJson = $request->expectsJson()
                || $request->ajax()
                || str_contains($accept, 'application/json')
                || str_contains($accept, 'text/event-stream');

            if ($wantsJson) {
                return response()->json(['message' => 'Oturum süresi doldu. Sayfayı yenileyip tekrar deneyin.'], 419);
            }

            return redirect()->route('login')
                ->withErrors(['session' => 'Oturumunuzun süresi doldu. Lütfen tekrar giriş yapın.']);
        });
    })
    ->withProviders([
        PartnerServiceProvider::class,
    ])
    ->booting(function (Application $app): void {
        /*
        |----------------------------------------------------------------------
        | Rate Limiter: search
        |----------------------------------------------------------------------
        |
        | 60 requests per minute per IP address.
        | Applied to the /api/v1/search endpoint to prevent abuse
        | while supporting legitimate traffic at scale.
        |
        */
        RateLimiter::for('search', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip());
        });

        /*
        |----------------------------------------------------------------------
        | Rate Limiter: ai_search
        |----------------------------------------------------------------------
        |
        | AI tarafı her istek başına OpenAI API çağrısı yapar; kötü niyetli
        | trafik fatura riski. Anonim kullanıcı IP başına dakikada 10, auth'lu
        | kullanıcı user_id başına dakikada 30 istek.
        |
        */
        RateLimiter::for('ai_search', function (Request $request) {
            return $request->user()
                ? Limit::perMinute(30)->by('ai:user:'.$request->user()->id)
                : Limit::perMinute(10)->by('ai:ip:'.$request->ip());
        });

        /*
        |----------------------------------------------------------------------
        | Rate Limiter: login
        |----------------------------------------------------------------------
        |
        | Brute-force koruması. Hesap başına: aynı e-posta+IP ikilisi dakikada
        | 5 deneme (tek hesabı hedefleyen saldırı). IP başına: dakikada 20
        | (e-posta rotasyonuyla password spraying'i de sınırlar).
        |
        */
        RateLimiter::for('login', function (Request $request) {
            $email = mb_strtolower(trim((string) $request->input('email')));

            return [
                Limit::perMinute(5)->by('login:email:'.sha1($email.'|'.$request->ip())),
                Limit::perMinute(20)->by('login:ip:'.$request->ip()),
            ];
        });

        /*
        |----------------------------------------------------------------------
        | Rate Limiter: register
        |----------------------------------------------------------------------
        |
        | Toplu sahte hesap kaydını sınırlar: IP başına dakikada 6 deneme.
        |
        */
        RateLimiter::for('register', function (Request $request) {
            return Limit::perMinute(6)->by('register:ip:'.$request->ip());
        });

        /*
        |----------------------------------------------------------------------
        | Rate Limiter: tour_import
        |----------------------------------------------------------------------
        |
        | URL'den tur içe aktarma her istekte dış fetch + 1 gpt-4o çağrısı yapar
        | (maliyet + suistimal riski). Acenta başına dakikada 10 istek.
        |
        */
        RateLimiter::for('tour_import', function (Request $request) {
            return Limit::perMinute(10)->by('tour_import:'.($request->user()?->id ?: $request->ip()));
        });
    })
    ->create();
