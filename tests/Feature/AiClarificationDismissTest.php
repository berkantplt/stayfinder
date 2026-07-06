<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Tour;
use App\Services\AiSearch\ConversationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Chat\CreateResponse as ChatResponse;
use OpenAI\Responses\Embeddings\CreateResponse as EmbeddingResponse;
use Tests\TestCase;

/**
 * Netleştirme kilitlenmesi düzeltmesi: "farketmez" cevabına ve cevabı yeni
 * eksen eklemeyen kullanıcıya AYNI soru asla tekrarlanmaz — arama yapılır.
 * (Ekran görüntüsü hatası: "yurt dışı turu öner" → soru → "farketmez yurt
 * dışı olsun yeter" → AYNI soru tekrar.)
 */
class AiClarificationDismissTest extends TestCase
{
    use RefreshDatabase;

    private const VECTOR = [0.1, 0.2, 0.3];

    private function makeTour(): Tour
    {
        $agency = Agency::create([
            'name' => 'Dismiss Acenta', 'slug' => 'dismiss-acenta', 'email' => 'dismiss@example.com',
            'is_active' => true, 'approval_status' => Agency::STATUS_APPROVED,
            'approved_at' => now(), 'legacy_category_access' => true,
        ]);

        return Tour::create([
            'agency_id' => $agency->id, 'title' => 'İspanya Turu', 'destination' => 'İspanya',
            'duration_days' => 7, 'currency' => 'TRY', 'price' => 45000, 'is_active' => true,
            'is_international' => true, 'departure_date' => today()->addDays(30),
            'embedding' => self::VECTOR,
        ]);
    }

    public function test_farketmez_cevabi_soruyu_gecistirir_arama_yapilir(): void
    {
        $service = app(ConversationService::class);

        // Ekrandaki birleşik sorgu: hâlâ tek eksen (destinasyon) dolu ama
        // kullanıcı "farketmez" dediği için soru SORULMAMALI
        $question = $service->maybeAskClarification(
            'yurt dışı turu öner farketmez yurt dışı olsun yeter',
            []
        );

        $this->assertNull($question);
    }

    public function test_gecistirme_kaliplari_taninir(): void
    {
        $service = app(ConversationService::class);

        foreach ([
            'önemli değil sen seç',
            'hepsi olur',
            'sana bırakıyorum',
            'ne olursa olsun',
            'FARKETMEZ',
        ] as $message) {
            $this->assertNull(
                $service->maybeAskClarification($message, []),
                "'{$message}' geçiştirme olarak tanınmalıydı"
            );
        }

        // Kontrol: geçiştirme İÇERMEYEN tek eksenli sorgu hâlâ soru üretmeli
        $this->assertNotNull($service->maybeAskClarification('tur önerir misin', []));
    }

    public function test_widget_farketmez_sonrasi_ayni_soruyu_tekrarlamaz(): void
    {
        $this->makeTour();

        // 1. mesaj: tek eksen → netleştirme sorusu beklenir (LLM'e gitmez)
        $first = $this->getJson(route('ai.search.api', ['q' => 'yurt dışı turu öner']));
        $first->assertOk()->assertJsonPath('is_clarification', true);
        $firstQuestion = $first->json('aiComment');

        // 2. mesaj: "farketmez ..." → SORU DEĞİL, arama sonucu dönmeli
        OpenAI::fake([
            ChatResponse::fake(['choices' => [['index' => 0, 'message' => ['role' => 'assistant', 'content' => json_encode(['is_international' => true])]]]]),
            EmbeddingResponse::fake(['data' => [['object' => 'embedding', 'index' => 0, 'embedding' => self::VECTOR]]]),
            ChatResponse::fake(['choices' => [['index' => 0, 'message' => ['role' => 'assistant', 'content' => 'Yurt dışı seçenekleri hazır.']]]]),
        ]);

        $second = $this->getJson(route('ai.search.api', ['q' => 'farketmez yurt dışı olsun yeter']));

        $second->assertOk();
        $this->assertNotTrue($second->json('is_clarification'), 'Aynı soru tekrarlandı — geçiştirme tanınmadı');
        $this->assertNotSame($firstQuestion, $second->json('aiComment'));
        $this->assertNotEmpty($second->json('results'));
    }

    public function test_widget_bos_cevapta_ayni_soru_yerine_arama_yapar(): void
    {
        $this->makeTour();

        $first = $this->getJson(route('ai.search.api', ['q' => 'güzel bir tur öner']));
        $first->assertOk()->assertJsonPath('is_clarification', true);

        // Cevap yeni eksen eklemiyor ve geçiştirme kalıbı da yok ("hmm bilemedim ya")
        // → aynı soru oluşur ama TEKRARLANMAMALI; arama çalışmalı
        OpenAI::fake([
            ChatResponse::fake(['choices' => [['index' => 0, 'message' => ['role' => 'assistant', 'content' => '{}']]]]),
            EmbeddingResponse::fake(['data' => [['object' => 'embedding', 'index' => 0, 'embedding' => self::VECTOR]]]),
            ChatResponse::fake(['choices' => [['index' => 0, 'message' => ['role' => 'assistant', 'content' => 'Seçenekler burada.']]]]),
        ]);

        $second = $this->getJson(route('ai.search.api', ['q' => 'hmm bilemedim ya']));

        $second->assertOk();
        $this->assertNotTrue($second->json('is_clarification'), 'Aynı soru kullanıcıya ikinci kez soruldu');
        $this->assertNotEmpty($second->json('results'));
    }
}
