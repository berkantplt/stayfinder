<?php

namespace Tests\Feature;

use App\Services\TourImage\TourImageService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * downloadAndStore boyut kapısı: thumbnail/placeholder boyutundaki görseller
 * (kısa kenar < 300px) galeriye kaydedilmez; yeterince büyük görseller kaydedilir.
 */
class TourImageDownloadTest extends TestCase
{
    private function pngBytes(int $width, int $height): string
    {
        $im = imagecreatetruecolor($width, $height);
        imagefill($im, 0, 0, imagecolorallocate($im, 200, 200, 200));
        ob_start();
        imagepng($im);
        imagedestroy($im);

        return (string) ob_get_clean();
    }

    public function test_kucuk_gorsel_reddedilir_buyuk_gorsel_kaydedilir(): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD yok');
        }

        Storage::fake('public');
        // Host olarak public IP literal: isSafeRemoteUrl DNS'e gitmeden geçer,
        // Http::fake gerçek istek atılmasını zaten engeller.
        Http::fake([
            '203.0.113.10/small.png' => Http::response($this->pngBytes(150, 150), 200, ['Content-Type' => 'image/png']),
            '203.0.113.10/big.png' => Http::response($this->pngBytes(800, 500), 200, ['Content-Type' => 'image/png']),
            '203.0.113.10/garbage.png' => Http::response('bu bir gorsel degil', 200, ['Content-Type' => 'image/png']),
        ]);

        $service = new TourImageService;

        $this->assertNull($service->downloadAndStore('https://203.0.113.10/small.png'));
        $this->assertNull($service->downloadAndStore('https://203.0.113.10/garbage.png'));

        $stored = $service->downloadAndStore('https://203.0.113.10/big.png');
        $this->assertNotNull($stored);
        $this->assertStringStartsWith('/storage/tours/', $stored);
        Storage::disk('public')->assertExists(str_replace('/storage/', '', $stored));
    }
}
