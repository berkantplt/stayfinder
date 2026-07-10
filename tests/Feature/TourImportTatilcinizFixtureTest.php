<?php

namespace Tests\Feature;

use App\Services\TourImport\TourUrlImporter;
use Illuminate\Support\Facades\Http;
use ReflectionClass;
use Tests\TestCase;

/**
 * tatilciniz.com.tr/sunshine regresyon bekçisi: bu sayfa yapısında görsel
 * hasadı her zaman /images/tour/ galerisini bulmalı, site rozetlerini
 * (tursab, banka, logo) içerik filtresi elemelidir. Gerçek sayfa fixture'ı:
 * tests/Fixtures/page_tatilciniz_sunshine.html
 */
class TourImportTatilcinizFixtureTest extends TestCase
{
    /** URL'e göre deterministik, birbirinden farklı 800x450 jpeg üretir. */
    private function bigUniqueJpeg(string $url): string
    {
        $im = imagecreatetruecolor(800, 450);
        mt_srand(crc32($url));
        for ($i = 0; $i < 40; $i++) {
            imagefilledrectangle(
                $im,
                mt_rand(0, 700), mt_rand(0, 380),
                mt_rand(60, 799), mt_rand(40, 449),
                imagecolorallocate($im, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255))
            );
        }
        ob_start();
        imagejpeg($im, null, 85);

        return (string) ob_get_clean();
    }

    private function tinyBadgePng(): string
    {
        $im = imagecreatetruecolor(120, 60);
        imagefilledrectangle($im, 0, 0, 120, 60, imagecolorallocate($im, 200, 30, 30));
        ob_start();
        imagepng($im);

        return (string) ob_get_clean();
    }

    public function test_gallery_harvested_and_site_badges_filtered(): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD yok');
        }

        Http::fake(function ($request) {
            $url = $request->url();
            if (str_contains($url, '/images/tour/')) {
                return Http::response($this->bigUniqueJpeg($url), 200, ['Content-Type' => 'image/jpeg']);
            }

            // Rozet/logo/banner: küçük görsel — içerik filtresi elemeli
            return Http::response($this->tinyBadgePng(), 200, ['Content-Type' => 'image/png']);
        });

        $importer = new TourUrlImporter;
        $ref = new ReflectionClass($importer);
        $ref->getProperty('lastHtml')->setValue(
            $importer,
            (string) file_get_contents(base_path('tests/Fixtures/page_tatilciniz_sunshine.html'))
        );

        $urls = $ref->getMethod('harvestImages')->invoke($importer, 'https://www.tatilciniz.com.tr/sunshine');

        $this->assertGreaterThanOrEqual(5, count($urls), 'Galeri görselleri kaybolmuş: '.implode(', ', $urls));
        $this->assertStringContainsString('823_vitrin', $urls[0], 'Kapak (og:image) ilk sırada değil');

        foreach ($urls as $u) {
            $this->assertStringContainsString('/images/tour/', $u, "Galeri dışı görsel sızdı: $u");
        }
    }
}
