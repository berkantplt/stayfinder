<?php

namespace Tests\Feature;

use App\Models\AiSearchConversation;
use App\Services\AiSearch\ConversationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Konuşma hafızası davranışları (chatbot denetimi — Paket 1):
 * sıfırlama, netleştirme bağlamı, "beğenmedim başka öner".
 */
class AiConversationMemoryTest extends TestCase
{
    use RefreshDatabase;

    private function prepareTurn(AiSearchConversation $conversation, string $message): array
    {
        $service = app(ConversationService::class);
        $reflection = new \ReflectionMethod($service, 'prepareTurn');

        return $reflection->invoke($service, $conversation, $message);
    }

    private function makeConversation(array $intent = [], array $lastResults = []): AiSearchConversation
    {
        return AiSearchConversation::create([
            'session_id' => 'test-session',
            'current_intent' => $intent ?: null,
            'last_result_tour_ids' => $lastResults ?: null,
            'last_message_at' => now(),
        ]);
    }

    public function test_bastan_basla_tum_niyeti_sifirlar(): void
    {
        $conversation = $this->makeConversation([
            'max_budget' => 30000,
            'is_international' => true,
            'preferred_destination' => 'Avrupa',
            '_clarifications' => 2,
        ]);

        [$previousIntent] = $this->prepareTurn($conversation, 'baştan başlayalım, yeni arama yapalım');

        $this->assertSame([], $previousIntent);
    }

    public function test_netlestirme_baglami_sorguya_tasinir(): void
    {
        // Turn 1'de "Kapadokya" dendi, soru soruldu; turn 2'de "30 bin" cevabı geldi
        $conversation = $this->makeConversation([
            '_clarifications' => 1,
            '_pending_context' => 'Kapadokya balayı',
        ]);

        [, $searchQuery] = $this->prepareTurn($conversation, '30 bin bütçem var');

        $this->assertSame('Kapadokya balayı 30 bin bütçem var', $searchQuery);
    }

    public function test_begenmedim_onceki_sonuclari_dislar_ve_konuyu_korur(): void
    {
        $conversation = $this->makeConversation(
            ['search_query' => 'Kapadokya balayı turu'],
            [11, 22, 33]
        );

        [, $searchQuery, $excludeTourIds] = $this->prepareTurn($conversation, 'beğenmedim, başka öner');

        $this->assertSame([11, 22, 33], $excludeTourIds);
        // Semantik sorgu anlamsız "beğenmedim" yerine önceki konuyu taşır
        $this->assertStringContainsString('Kapadokya balayı turu', $searchQuery);
    }

    public function test_normal_mesajda_dislama_yok(): void
    {
        $conversation = $this->makeConversation(
            ['search_query' => 'Kapadokya turu'],
            [11, 22]
        );

        [, $searchQuery, $excludeTourIds] = $this->prepareTurn($conversation, 'eylül ayında olsun');

        $this->assertSame([], $excludeTourIds);
        $this->assertSame('eylül ayında olsun', $searchQuery);
    }

    public function test_netlestirme_sinyali_birlesik_baglamla_degerlendirilir(): void
    {
        $service = app(ConversationService::class);

        // "Kapadokya 30 bin" birleşik bağlamı 2 eksen (destinasyon + bütçe) doldurur
        // → artık soru SORULMAZ (eskiden Kapadokya unutulup tekrar soruluyordu)
        $question = $service->maybeAskClarification('Kapadokya balayı 30 bin bütçem var', []);

        $this->assertNull($question);
    }
}
