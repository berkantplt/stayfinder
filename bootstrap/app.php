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
    })
    ->create();
