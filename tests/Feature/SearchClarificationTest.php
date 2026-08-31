<?php

namespace Tests\Feature;

use App\Services\AiSearch\ClarificationAdvisor;
use App\Services\AiSearch\TourSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Netleştirme danışmanının (ClarificationAdvisor) sinyal mantığı + widget'ın
 * çağırdığı /yapay-zeka-arama-api ucunun belirsiz sorguda soru dönmesi.
 *
 * Sohbet v1 kaldırıldığında silinen AiConversationFlowTest /
 * AiConversationMemoryTest dosyalarından kurtarıldı.
 */
class SearchClarificationTest extends TestCase
{
    use RefreshDatabase;

    private function advisor(): ClarificationAdvisor
    {
        return app(ClarificationAdvisor::class);
    }

    public function test_vague_initial_message_triggers_deterministic_clarification_question(): void
    {
        // Hiç bilgi içermeyen mesaj + boş previous intent → LLM'e gitmeden zorla soru
        $question = $this->advisor()->maybeAskClarification('tatil yapmak istiyorum', []);

        $this->assertNotNull($question);
        $this->assertStringContainsString('bütçen', mb_strtolower($question));
    }

    public function test_clarification_logic_covers_signal_combinations(): void
    {
        $advisor = $this->advisor();

        // 0 sinyal → soru
        $this->assertNotNull($advisor->maybeAskClarification('tatil yapmak istiyorum', []));
        $this->assertNotNull($advisor->maybeAskClarification('merhaba', []));

        // 1 sinyal (sadece bütçe) → hâlâ soru
        $this->assertNotNull($advisor->maybeAskClarification('30K bütçem var', []));

        // 1 sinyal (sadece destinasyon) → hâlâ soru
        $this->assertNotNull($advisor->maybeAskClarification('Bali görmek istiyorum', []));

        // 2 sinyal (bütçe + destinasyon) → arama yapılır, soru null
        $this->assertNull($advisor->maybeAskClarification('30K bütçeyle Avrupa düşünüyorum', []));

        // 2 sinyal (destinasyon + zaman) → null
        $this->assertNull($advisor->maybeAskClarification('Eylül ayında Yunanistan', []));

        // 1 sinyal mesajda + 1 önceki niyetten (bütçe) = 2 → null
        $this->assertNull($advisor->maybeAskClarification('Bali görmek istiyorum', ['max_budget' => 30000]));

        // Önceki niyetten 1 sinyal + mesajda 0 sinyal = hâlâ 1, soru
        $this->assertNotNull($advisor->maybeAskClarification('peki', ['max_budget' => 30000]));
    }

    public function test_budget_signal_recognizes_natural_phrasings(): void
    {
        $advisor = $this->advisor();

        // "bütçe" kelimesi + 4+ haneli sayı + zaman → arama
        $this->assertNull($advisor->maybeAskClarification('20000 bütçe 3 yada 4 gün lük bir tatil', []));

        // "bütçe" tek başına → sadece bütçe sinyali, zaman/destinasyon yok → soru
        $this->assertNotNull($advisor->maybeAskClarification('20000 bütçe', []));

        // "butce" (ASCII varyant)
        $this->assertNotNull($advisor->maybeAskClarification('butce 15000', []));

        // 4+ haneli çıplak sayı + zaman → arama (bütçe varsayılır)
        $this->assertNull($advisor->maybeAskClarification('15000 lira 5 gün', []));

        // "30 bin" gibi metinsel bütçe + destinasyon
        $this->assertNull($advisor->maybeAskClarification('30 bin Avrupa', []));

        // Yıl gibi görünen sayı (2025, 2026) bütçe sayılmamalı
        $this->assertNotNull($advisor->maybeAskClarification('2026 yılında tatil', []));
        $this->assertNotNull($advisor->maybeAskClarification('Eylül 2025', []));
    }

    public function test_netlestirme_sinyali_birlesik_baglamla_degerlendirilir(): void
    {
        // "Kapadokya 30 bin" birleşik bağlamı 2 eksen (destinasyon + bütçe) doldurur
        // → artık soru SORULMAZ (eskiden Kapadokya unutulup tekrar soruluyordu)
        $question = $this->advisor()->maybeAskClarification('Kapadokya balayı 30 bin bütçem var', []);

        $this->assertNull($question);
    }

    public function test_widget_searchapi_returns_clarification_for_vague_query(): void
    {
        // Floating widget'ın çağırdığı /yapay-zeka-arama-api endpoint'i de
        // belirsiz girdilerde performAiSearch'a gitmeden soru dönmeli.
        $this->mock(TourSearchService::class, function (MockInterface $mock) {
            $mock->makePartial();
            $mock->shouldNotReceive('performAiSearch');
        });

        $r = $this->getJson(route('ai.search.api', ['q' => 'tatil yapmak istiyorum']))
            ->assertOk();

        $this->assertTrue($r->json('is_clarification'));
        $this->assertSame([], $r->json('results'));
        $this->assertNotEmpty($r->json('aiComment'));
    }
}
