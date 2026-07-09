<?php

namespace Tests\Feature;

use App\Services\TourImport\TourUrlImporter;
use Illuminate\Support\Facades\Http;
use ReflectionClass;
use Tests\TestCase;

/**
 * İçerik-farkındalıklı görsel eleme (turperisi/acenta360 vakası): hash isimli
 * CDN'lerde aynı fotoğrafın jpg+webp kopyaları URL'den ayırt edilemez —
 * algısal parmak izi (dHash) ile tekilleşir; isimden tanınamayan logolar
 * boyut/oran ile elenir.
 */
class TourImageContentFilterTest extends TestCase
{
    private function gradientImage(int $w, int $h, bool $inverted = false): \GdImage
    {
        $im = imagecreatetruecolor($w, $h);
        for ($x = 0; $x < $w; $x += 4) {
            $shade = (int) round(255 * ($x / $w));
            if ($inverted) {
                $shade = 255 - $shade;
            }
            $color = imagecolorallocate($im, $shade, (int) ($shade / 2), 255 - $shade);
            imagefilledrectangle($im, $x, 0, $x + 3, $h, $color);
        }
        // Yatay bant: dHash'in satır sinyali de dolsun
        imagefilledrectangle($im, 0, (int) ($h / 3), $w, (int) ($h / 2), imagecolorallocate($im, 250, 250, 250));

        return $im;
    }

    private function encode(\GdImage $im, string $format): string
    {
        ob_start();
        $format === 'jpeg' ? imagejpeg($im, null, 88) : imagepng($im);

        return (string) ob_get_clean();
    }

    public function test_perceptual_duplicates_merge_and_logo_is_dropped(): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD yok');
        }

        // Aynı fotoğraf: küçük jpg + büyük "webp" kopya (farklı hash isimleri)
        $photo = $this->gradientImage(800, 600);
        $small = $this->encode($photo, 'jpeg');
        $big = imagescale($photo, 1024, 768);
        $bigBody = $this->encode($big, 'png');

        // Farklı fotoğraf: ters gradyan — tekilleşmemeli
        $other = $this->encode($this->gradientImage(800, 600, true), 'jpeg');

        // Logo: 400x100 şerit (isminde 'logo' YOK — isim filtresi yakalayamaz)
        $logoBody = $this->encode($this->gradientImage(400, 100), 'png');

        Http::fake([
            'cdn.test/6a1f0a.jpg' => Http::response($small, 200, ['Content-Type' => 'image/jpeg']),
            'cdn.test/9b2e4c.webp' => Http::response($bigBody, 200, ['Content-Type' => 'image/webp']),
            'cdn.test/77ac1d.jpg' => Http::response($other, 200, ['Content-Type' => 'image/jpeg']),
            'cdn.test/31fe9b.png' => Http::response($logoBody, 200, ['Content-Type' => 'image/png']),
        ]);

        $importer = new TourUrlImporter;
        $ref = new ReflectionClass($importer);
        $prop = $ref->getProperty('lastHtml');
        $prop->setValue($importer, implode('', [
            '<img src="https://cdn.test/6a1f0a.jpg">',
            '<img src="https://cdn.test/9b2e4c.webp">',
            '<img src="https://cdn.test/77ac1d.jpg">',
            '<img src="https://cdn.test/31fe9b.png">',
        ]));

        $urls = $ref->getMethod('harvestImages')->invoke($importer, 'https://cdn.test/tur/x');

        // jpg+webp çifti tekilleşti (büyük kopya, İLK sırada kaldı), logo elendi
        $this->assertSame([
            'https://cdn.test/9b2e4c.webp',
            'https://cdn.test/77ac1d.jpg',
        ], $urls);
    }

    public function test_unreachable_candidates_are_kept(): void
    {
        Http::fake(['cdn.test/*' => Http::response('', 500)]);

        $importer = new TourUrlImporter;
        $ref = new ReflectionClass($importer);
        $prop = $ref->getProperty('lastHtml');
        $prop->setValue($importer, '<img src="https://cdn.test/a.jpg"><img src="https://cdn.test/b.jpg">');

        $urls = $ref->getMethod('harvestImages')->invoke($importer, 'https://cdn.test/tur/x');

        // İndirilemeyen adaylar temkinli şekilde tutulur (ağ hatası eleme sebebi değil)
        $this->assertCount(2, $urls);
    }
}
