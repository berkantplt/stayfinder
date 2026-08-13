<?php

namespace App\Providers;

use App\Listeners\MigrateAnonymousAiConversations;
use App\Models\Destination;
use App\Models\Post;
use App\Models\TourDate;
use App\Observers\DestinationObserver;
use App\Observers\PostObserver;
use App\Observers\TourDateObserver;
use App\Services\Payment\IyzicoService;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
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
        $this->forceHttpsUrls();

        // RAG bilgi tabanını canlı tut: Post/Destination değişikliklerinde
        // KnowledgeChunk update + embedding job dispatch.
        // (TourObserver zaten #[ObservedBy] attribute ile Tour modeline bağlı.)
        Post::observe(PostObserver::class);
        Destination::observe(DestinationObserver::class);
        // Tur tarihleri değişince turun embedding'i (kalkış ayları) tazelensin
        TourDate::observe(TourDateObserver::class);

        // Girişte anonim AI konuşmalarını yeni kimliğe bağla (sahipsiz kalmasın)
        Event::listen(Login::class, MigrateAnonymousAiConversations::class);
    }

    /**
     * APP_URL https ise üretilen tüm adresler de https olsun.
     *
     * Plesk/Cloudflare gibi bir ters vekil arkasında PHP isteği çoğu kez düz
     * http olarak görür. TrustProxies bunu düzeltir ama vekil başlığı eksikse
     * canonical, og:url, sitemap ve e-posta bağlantıları http üretir. Google
     * için http ve https AYRI adreslerdir: canonical'ın http olması, https
     * sayfanın kendini başka bir sayfaya işaret etmesi demektir — indexlemeyi
     * doğrudan bozar. Bu yüzden şema APP_URL'den sabitlenir.
     */
    private function forceHttpsUrls(): void
    {
        // Yalnız canlıda: yerelde APP_URL https bir tünel olsa da siteye
        // http://127.0.0.1 üzerinden girilir, şema zorlanırsa yerel bağlantılar kırılır.
        if ($this->app->environment('production')
            && str_starts_with((string) config('app.url'), 'https://')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }
    }
}
