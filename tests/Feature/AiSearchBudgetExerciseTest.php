<?php

namespace Tests\Feature;

use App\Http\Controllers\AiSearchController;
use App\Models\Agency;
use App\Models\Tour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Chat\CreateResponse as ChatResponse;
use OpenAI\Responses\Embeddings\CreateResponse as EmbeddingResponse;
use Tests\TestCase;

/**
 * ============================ EGZERSİZ ============================
 * Amaç: "Bütçe içindeki tur, bütçeyi aşandan yukarıda sıralanır" isabet
 * kuralını KENDİN test ederek doğrula.
 *
 * SENARYO
 *   Kullanıcı: "kapadokya turu, bütçem 20 bin".
 *   Envanterde 2 Kapadokya turu var:
 *     - Ekonomik: 18.000 TL  (bütçe içinde)
 *     - Lüks:     35.000 TL  (bütçeyi aşıyor ama 1.8x tolerans içinde → listelenir)
 *
 * GÖREV (aşağıdaki TODO'ları doldur, sonra markTestIncomplete satırını sil)
 *   Yardımcılar hazır: $this->makeTour(başlık, fiyat) ve $this->search(sorgu, intent).
 *
 *   TODO 1 — 18.000 TL'lik "Kapadokya Ekonomik Tur" oluştur (makeTour ile).
 *   TODO 2 — 35.000 TL'lik "Kapadokya Lüks Tur" oluştur.
 *   TODO 3 — search() çağır:
 *              sorgu:  'kapadokya turu 20 bin bütçe'
 *              intent: ['max_budget' => 20000, 'preferred_destination' => 'Kapadokya']
 *   TODO 4 — Ekonomik turun sonuç listesinde olduğunu doğrula.
 *   TODO 5 — Ekonomik turun, Lüks turdan DAHA YUKARIDA sıralandığını doğrula.
 *            (İpucu: $ids = collect($result['results'])->pluck('id')->all();
 *                    array_search($id, $ids, true) sıra indeksini verir — küçük = yukarıda.)
 *   TODO 6 — Lüks turun sonuç öğesinde 'over_budget' bayrağının true olduğunu doğrula.
 *            (İpucu: collect($result['results'])->firstWhere('id', $luks->id))
 *
 * Doğrulama:  php artisan test tests/Feature/AiSearchBudgetExerciseTest.php
 * =================================================================
 */
class AiSearchBudgetExerciseTest extends TestCase
{
    use RefreshDatabase;

    private const VECTOR = [0.1, 0.2, 0.3];

    private Agency $agency;

    protected function setUp(): void
    {
        parent::setUp();
        $this->agency = Agency::create([
            'name' => 'Egzersiz Acenta', 'slug' => 'egzersiz-acenta', 'email' => 'egz@example.com',
            'is_active' => true, 'approval_status' => Agency::STATUS_APPROVED,
            'approved_at' => now(), 'legacy_category_access' => true,
        ]);
    }

    /** Hazır yardımcı: verilen başlık ve fiyatla aktif bir Kapadokya turu üretir. */
    private function makeTour(string $title, int $price): Tour
    {
        return Tour::create([
            'agency_id' => $this->agency->id,
            'title' => $title,
            'destination' => 'Kapadokya',
            'departure_city' => 'İstanbul',
            'duration_days' => 4,
            'currency' => 'TRY',
            'price' => $price,
            'is_active' => true,
            'is_international' => false,
            'departure_date' => today()->addDays(30),
            'embedding' => self::VECTOR,
            'search_text' => 'kapadokya turu balon peribacalari gezi',
        ]);
    }

    /** Hazır yardımcı: LLM'i mock'layıp aramayı çalıştırır, sonucu dizi olarak döner. */
    private function search(string $query, array $intent): array
    {
        OpenAI::fake([
            ChatResponse::fake(['choices' => [['index' => 0, 'message' => ['role' => 'assistant', 'content' => json_encode($intent)]]]]),
            EmbeddingResponse::fake(['data' => [['object' => 'embedding', 'index' => 0, 'embedding' => self::VECTOR]]]),
            ChatResponse::fake(['choices' => [['index' => 0, 'message' => ['role' => 'assistant', 'content' => 'İşte seçenekler.']]]]),
        ]);
        $request = Request::create('/test', 'GET');
        $request->setLaravelSession(app('session.store'));
        $result = app(AiSearchController::class)->performAiSearch($request, $query);
        $this->assertArrayNotHasKey('error', $result, $result['error'] ?? '');

        return $result;
    }

    public function test_butce_icindeki_tur_butce_asaninin_ustunde_siralanir(): void
    {
        // TODO 1: Ekonomik turu oluştur
        // $ekonomik = ...

        // TODO 2: Lüks turu oluştur
        // $luks = ...

        // TODO 3: Aramayı çalıştır
        // $result = $this->search(...);

        // TODO 4: Ekonomik tur listede mi?
        // TODO 5: Ekonomik, Lüks'ün üstünde mi?
        // TODO 6: Lüks turda over_budget = true mu?

        $this->markTestIncomplete('Egzersiz: TODO adımlarını doldur ve bu satırı sil.');
    }
}
