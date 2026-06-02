<?php

namespace App\Providers;

use App\Models\Destination;
use App\Models\Post;
use App\Observers\DestinationObserver;
use App\Observers\PostObserver;
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
        // RAG bilgi tabanını canlı tut: Post/Destination değişikliklerinde
        // KnowledgeChunk update + embedding job dispatch.
        // (TourObserver zaten #[ObservedBy] attribute ile Tour modeline bağlı.)
        Post::observe(PostObserver::class);
        Destination::observe(DestinationObserver::class);
    }
}
