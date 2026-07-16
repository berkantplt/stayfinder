<?php

namespace Tests\Feature;

use App\Services\TourImport\TourUrlImporter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Chat\CreateResponse;
use Tests\TestCase;

/**
 * Tatilciniz vakası: LLM'in geçici hatasında doImport uyarılı/boş kısmi sonuç
 * döner — bu kısmi sonuç cache'lenirse URL 30 dk "boş geliyor" durumuna
 * kilitlenir. Sağlık kapısı: sadece başlıklı + fiyat sinyalli sonuç cache'lenir.
 */
class TourImportCacheHealthTest extends TestCase
{
    private function importer(): TourUrlImporter
    {
        return new TourUrlImporter;
    }

    public function test_failed_llm_result_is_returned_but_not_cached(): void
    {
        Http::fake(['*' => Http::response('<html><body><h1>Tur</h1><p>Fethiye turu detayları.</p></body></html>', 200, ['Content-Type' => 'text/html'])]);
        OpenAI::fake([]); // her LLM çağrısı patlar ("No fake responses left")

        $result = $this->importer()->import('https://1.1.1.1/fethiye-turu');

        $this->assertSame('', (string) $result['title']);
        $this->assertNotEmpty($result['warnings']);

        // Kısmi sonuç CACHE'E GİRMEMELİ — tekrar deneme taze koşulmalı
        $this->assertNull(Cache::get('tour_import:v12:'.md5('https://1.1.1.1/fethiye-turu|0')));
    }

    public function test_healthy_result_is_cached_and_reused(): void
    {
        Http::fake(['*' => Http::response('<html><body><h1>Tur</h1><p>Fethiye turu detayları.</p></body></html>', 200, ['Content-Type' => 'text/html'])]);
        OpenAI::fake([
            CreateResponse::fake([
                'choices' => [[
                    'index' => 0,
                    'message' => ['role' => 'assistant', 'content' => json_encode([
                        'title' => 'Fethiye Turu',
                        'price' => 13499,
                        'currency' => 'TRY',
                    ])],
                ]],
            ]),
            CreateResponse::fake([
                'choices' => [[
                    'index' => 0,
                    'message' => ['role' => 'assistant', 'content' => '{"pricing_blocks":[]}'],
                ]],
            ]),
        ]);

        $first = $this->importer()->import('https://1.1.1.1/fethiye-turu');
        $this->assertSame('Fethiye Turu', $first['title']);
        $this->assertNotNull(Cache::get('tour_import:v12:'.md5('https://1.1.1.1/fethiye-turu|0')));

        // İkinci çağrı cache'ten dönmeli: fake havuzu bitti, LLM'e gitse patlardı
        $second = $this->importer()->import('https://1.1.1.1/fethiye-turu');
        $this->assertSame('Fethiye Turu', $second['title']);
    }
}
