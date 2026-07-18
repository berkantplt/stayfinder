<?php

namespace Tests\Feature;

use App\Services\TourImport\TourUrlImporter;
use Illuminate\Support\Facades\Http;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Chat\CreateResponse;
use Tests\TestCase;

/**
 * Prontotour vakası: sayfada HİÇBİR bağımsız tarih kanıtı yokken (metin
 * taraması + gömülü JSON + fiyat blokları boş) LLM'in döndürdüğü tarihler
 * uydurmadır — prompt'ta bugünün tarihi bulunduğundan model "bugün + rastgele
 * gün" üretebiliyor (canlıda 18 Temmuz [=o günün tarihi] + 29 Ağustos hayalet
 * kalkış olarak forma dolmuştu). Guard: doğrulanamayan tarih sessizce yazılmaz.
 */
class TourImportPhantomDatesTest extends TestCase
{
    private function fakeLlmWithDates(array $dates): void
    {
        OpenAI::fake([
            CreateResponse::fake([
                'choices' => [[
                    'index' => 0,
                    'message' => ['role' => 'assistant', 'content' => json_encode([
                        'title' => 'Doğu Anadolu Turu',
                        'price' => 42999,
                        'currency' => 'TRY',
                        'departure_dates' => $dates,
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
    }

    public function test_llm_only_dates_are_dropped_when_page_has_no_date_evidence(): void
    {
        // Sayfada tek bir tarih bile yok — LLM yine de tarih uydursun
        Http::fake(['*' => Http::response(
            '<html><body><h1>Tur</h1><p>Doğu Anadolu turu detayları, tren ile seyahat.</p></body></html>',
            200,
            ['Content-Type' => 'text/html']
        )]);
        $this->fakeLlmWithDates(['2027-07-18', '2027-08-29']);

        $result = (new TourUrlImporter)->import('https://1.1.1.1/dogu-anadolu-turu');

        $this->assertSame([], $result['departure_dates'], 'sayfada kanıtı olmayan LLM tarihleri elenmiş olmalı');
        $this->assertStringContainsString(
            'doğrulanabilir kalkış tarihi bulunamadı',
            implode(' ', $result['warnings']),
            'kullanıcıya açıkça bildirilmeli'
        );
    }

    public function test_llm_dates_kept_when_page_text_corroborates(): void
    {
        // Sayfa metninde GERÇEK bir tarih var → tarih hasadı kanıt sağlar, elenmez
        $pageDate = today()->addDays(45);
        $html = '<html><body><h1>Tur</h1><p>Kalkış: '.$pageDate->format('d.m.Y').'</p></body></html>';
        Http::fake(['*' => Http::response($html, 200, ['Content-Type' => 'text/html'])]);
        $this->fakeLlmWithDates([$pageDate->toDateString()]);

        $result = (new TourUrlImporter)->import('https://1.1.1.1/kanitli-tur');

        $this->assertContains(
            $pageDate->toDateString(),
            $result['departure_dates'],
            'sayfayla doğrulanan tarih korunmalı'
        );
        $this->assertStringNotContainsString(
            'doğrulanabilir kalkış tarihi bulunamadı',
            implode(' ', $result['warnings'])
        );
    }
}
