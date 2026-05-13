<?php

namespace App\Providers;

use App\Services\Payment\IyzicoService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(IyzicoService::class, function ($app) {
            return new IyzicoService((array) $app['config']->get('iyzico', []));
        });
    }

    public function boot(): void
    {
        //
    }
}
