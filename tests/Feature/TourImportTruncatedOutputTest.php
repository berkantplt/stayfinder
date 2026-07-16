<?php

namespace Tests\Feature;

use App\Services\TourImport\TourUrlImporter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Chat\CreateResponse;
use Tests\TestCase;

/**
 * Token tavanına çarpan (finish_reason=length) LLM çıktısı alanları SESSİZCE
 * kaybetmemeli: uyarı üretilmeli ve sonuç cache'lenmemeli (tekrar denemede taze).
 */
class TourImportTruncatedOutputTest extends TestCase
{
    public function test_truncated_general_extraction_warns_and_is_not_cached(): void
    {
        Http::fake(['*' => Http::response('<html><body><h1>Tur</h1><p>Uzun bir tur açıklaması burada.</p></body></html>', 200, ['Content-Type' => 'text/html'])]);

        // Genel çıkarım çağrısı kesik döner (finish_reason=length + yarım JSON)
        OpenAI::fake([
            CreateResponse::fake([
                'choices' => [[
                    'index' => 0,
                    'finish_reason' => 'length',
                    'message' => ['role' => 'assistant', 'content' => '{"title":"Fethiye tur'],
                ]],
            ]),
            // Fiyat matrisi çağrısı (deterministik boşsa) — boş dönsün
            CreateResponse::fake([
                'choices' => [[
                    'index' => 0,
                    'message' => ['role' => 'assistant', 'content' => '{"pricing_blocks":[]}'],
                ]],
            ]),
        ]);

        $result = (new TourUrlImporter)->import('https://1.1.1.1/fethiye-turu');

        // Alanlar sessizce kaybolmadı: kullanıcıya uyarı gösteriliyor
        $this->assertNotEmpty($result['warnings']);
        $warningText = implode(' ', $result['warnings']);
        $this->assertStringContainsString('çıkarım', mb_strtolower($warningText));

        // Sağlıksız (başlıksız) sonuç cache'lenmedi → tekrar denemede taze koşar
        $this->assertNull(Cache::get('tour_import:v9:'.md5('https://1.1.1.1/fethiye-turu|0')));
    }
}
