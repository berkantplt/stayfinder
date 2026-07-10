<?php

namespace Tests\Feature;

use App\Services\TourImport\TourUrlImporter;
use Illuminate\Support\Facades\Http;
use ReflectionClass;
use RuntimeException;
use Tests\TestCase;

/**
 * SSRF regresyon bekçisi: güvenli görünen ilk URL, 302 ile iç ağa/metadata'ya
 * yönlendirse bile importer hedefi ÇEKMEMELİ (her redirect hedefi yeniden
 * assertSafeUrl'den geçer).
 */
class ImportSsrfRedirectTest extends TestCase
{
    public function test_redirect_to_internal_metadata_ip_is_blocked(): void
    {
        // İlk URL public (TEST-NET-3), ama 302 ile bulut metadata IP'sine yönlendiriyor.
        Http::fake([
            '203.0.113.10/*' => Http::response('', 302, ['Location' => 'http://169.254.169.254/latest/meta-data/']),
            '169.254.169.254/*' => Http::response('GİZLİ METADATA', 200, ['Content-Type' => 'text/html']),
        ]);

        $importer = new TourUrlImporter;
        $ref = new ReflectionClass($importer);
        $fetchDirect = $ref->getMethod('fetchDirect');

        try {
            $fetchDirect->invoke($importer, 'https://203.0.113.10/tur');
            $this->fail('Redirect iç ağa gitti — SSRF engeli çalışmadı.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('güvenlik', mb_strtolower($e->getMessage()));
        }

        // 169.254.169.254'e HİÇ istek gitmemeli
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '169.254.169.254'));
    }

    public function test_normal_page_still_fetched(): void
    {
        Http::fake([
            '203.0.113.10/*' => Http::response('<html><body>Tur içeriği</body></html>', 200, ['Content-Type' => 'text/html']),
        ]);

        $importer = new TourUrlImporter;
        $ref = new ReflectionClass($importer);
        $out = $ref->getMethod('fetchDirect')->invoke($importer, 'https://203.0.113.10/tur');

        $this->assertStringContainsString('Tur içeriği', $out);
    }
}
