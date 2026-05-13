<?php

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
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            'agency.approved' => \App\Http\Middleware\EnsureAgencyApproved::class,
        ]);

        // Trust all proxies (required for Cloudflare tunnel / ngrok)
        $middleware->trustProxies(at: '*');

        // iyzico kendi session'umuzu bilmiyor; callback POST'unu CSRF'den muaf tut.
        $middleware->validateCsrfTokens(except: [
            'iyzico-callback/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
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
                ? Limit::perMinute(30)->by('ai:user:' . $request->user()->id)
                : Limit::perMinute(10)->by('ai:ip:' . $request->ip());
        });
    })
    ->create();
