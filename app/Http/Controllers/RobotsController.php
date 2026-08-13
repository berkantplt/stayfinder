<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

/**
 * robots.txt — statik dosya yerine rota.
 *
 * İki sebeple:
 *  1) Sitemap satırı MUTLAK URL olmak zorundadır (sitemaps.org spec). Eski
 *     statik dosyada "Sitemap: /sitemap.xml" yazıyordu; göreli olduğu için
 *     Google bu satırı sessizce yok sayıyordu — yani sitemap hiç bildirilmemişti.
 *  2) Canlı/prova ortam ayrımı: prova ortamında tüm site taramaya kapatılır,
 *     aksi halde aynı içerik iki adresten indexlenip kopya içerik üretir.
 */
class RobotsController extends Controller
{
    /**
     * Taranmasının bir anlamı olmayan yollar. Hepsi ya girişe bağlı, ya
     * araç sayfası, ya da sonsuz kombinasyon üreten uçlar.
     *
     * @var array<int, string>
     */
    private const DISALLOW = [
        // Yönetim ve panel.
        //
        // DİKKAT: robots.txt eşleşmesi ön-ek tabanlıdır. Eski dosyadaki slash'sız
        // "Disallow: /acenta" satırı, herkese açık "/acentalar/jolly-tur"
        // sayfalarını da kapsıyordu — 11 acenta sayfası baştan beri taramaya
        // kapalıydı. Panel kökü "$" ile tam eşleşmeye, alt yollar "/" ile
        // sınırlanıyor; böylece "/acentalar/..." ve public "/acenta-kayit"
        // sayfası kapsam dışında kalır.
        '/admin$',
        '/admin/',
        '/acenta$',
        '/acenta/',
        // Girişe bağlı kullanıcı alanları — indexlenecek içerik yok
        '/giris',
        '/kayit',
        '/sifremi-unuttum',
        '/sifre-sifirla',
        '/profilim',
        '/favorilerim',
        '/bildirimler',
        '/kuponlarim',
        // Araç sayfaları: karşılaştırma seçime göre değişir, kalıcı içerik değil
        '/turlar/karsilastir',
        // Sohbet/arama uçları: her istek yeni URL üretir, tarama bütçesini yakar
        '/yapay-zeka-arama',
        '/sohbet',
        // Dış bağlantı sayacı — hedefe yönlendirir, kendi içeriği yok
        '/git/',
        // Ödeme geri dönüşü
        '/iyzico-callback',
    ];

    /**
     * Tarama bütçesini koruyan sorgu-parametresi kuralları.
     *
     * Filtre kombinasyonları zaten "noindex,follow" alıyor (App\Support\Seo),
     * ama noindex sayfanın TARANMASINI engellemez — Google yine indirir. Sıralama
     * ve fiyat/tarih aralıkları aynı 92 turu yeniden dizmekten başka bir şey
     * üretmediği için bunlar taramadan da çıkarılır.
     *
     * @var array<int, string>
     */
    private const DISALLOW_QUERY = [
        '/*?*sort=',
        '/*?*min_price=',
        '/*?*max_price=',
        '/*?*min_days=',
        '/*?*max_days=',
        '/*?*date_start=',
        '/*?*date_end=',
        '/*?*agency_id=',
        '/*?*utm_',
        '/*?*fbclid=',
        '/*?*gclid=',
    ];

    public function index(): Response
    {
        $lines = [];

        // Prova/yerel ortam: hiçbir şey indexlenmesin. Canlı olmayan bir kopyanın
        // indexlenmesi, canlı siteyle birebir kopya içerik demektir.
        if (! app()->environment('production')) {
            $lines[] = '# '.app()->environment().' ortamı — tarama tamamen kapalı';
            $lines[] = 'User-agent: *';
            $lines[] = 'Disallow: /';

            return $this->text($lines);
        }

        $lines[] = 'User-agent: *';

        foreach (self::DISALLOW as $path) {
            $lines[] = 'Disallow: '.$path;
        }

        $lines[] = '';
        $lines[] = '# Sıralama/aralık filtreleri: aynı içeriğin yeniden dizilmesi';
        foreach (self::DISALLOW_QUERY as $pattern) {
            $lines[] = 'Disallow: '.$pattern;
        }

        $lines[] = '';
        $lines[] = 'Allow: /';
        $lines[] = '';
        // Mutlak URL — spec gereği. Eski göreli satır Google tarafından yok sayılıyordu.
        $lines[] = 'Sitemap: '.route('sitemap');

        return $this->text($lines);
    }

    /**
     * @param  array<int, string>  $lines
     */
    private function text(array $lines): Response
    {
        return response(implode("\n", $lines)."\n", 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }
}
