<?php

namespace Tests\Feature;

use App\Services\AiSearch\TourSearchService;
use App\Models\Agency;
use App\Models\AiSearchLog;
use App\Models\Tour;
use App\Models\TourDate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Chat\CreateResponse as ChatResponse;
use OpenAI\Responses\Embeddings\CreateResponse as EmbeddingResponse;
use Tests\TestCase;

/**
 * Faz 2: hibrit arama, ay sertliği, bütçe kurtarıcı, bulanık destinasyon, re-ranker.
 */
class AiSearchPhase2Test extends TestCase
{
    use RefreshDatabase;

    private const VECTOR = [0.1, 0.2, 0.3];

    private Agency $agency;

    protected function setUp(): void
    {
        parent::setUp();
        $this->agency = Agency::create([
            'name' => 'Faz2 Acenta', 'slug' => 'faz2-acenta', 'email' => 'faz2@example.com',
            'is_active' => true, 'approval_status' => Agency::STATUS_APPROVED,
            'approved_at' => now(), 'legacy_category_access' => true,
        ]);
    }

    private function makeTour(array $attrs = []): Tour
    {
        return Tour::create(array_merge([
            'agency_id' => $this->agency->id,
            'title' => 'Standart Ege Turu',
            'destination' => 'Ege',
            'departure_city' => 'İstanbul',
            'duration_days' => 5,
            'currency' => 'TRY',
            'price' => 9000,
            'is_active' => true,
            'departure_date' => today()->addDays(20),
            'embedding' => self::VECTOR,
        ], $attrs));
    }

    private function search(string $query, array $intentJson = []): array
    {
        OpenAI::fake([
            ChatResponse::fake(['choices' => [['index' => 0, 'message' => ['role' => 'assistant', 'content' => json_encode($intentJson)]]]]),
            EmbeddingResponse::fake(['data' => [['object' => 'embedding', 'index' => 0, 'embedding' => self::VECTOR]]]),
            ChatResponse::fake(['choices' => [['index' => 0, 'message' => ['role' => 'assistant', 'content' => 'İşte seçenekler.']]]]),
        ]);

        $request = Request::create('/test', 'GET');
        $request->setLaravelSession(app('session.store'));

        $result = app(TourSearchService::class)->performAiSearch($request, $query);
        $this->assertIsArray($result);
        $this->assertArrayNotHasKey('error', $result, $result['error'] ?? '');

        return $result;
    }

    public function test_anahtar_kelime_kanali_birebir_ifadeyi_yuzeye_cikarir(): void
    {
        // Vektörleri özdeş 5 tur — cosine ayrıştıramaz; kelime kanalı ayrıştırır
        for ($i = 1; $i <= 4; $i++) {
            $this->makeTour(['title' => "Genel Tur {$i}", 'search_text' => 'genel ege turu gezi konaklama']);
        }
        $hedef = $this->makeTour([
            'title' => 'Yüzme Molalı Tekne Turu',
            'search_text' => 'yuzme molali tekne turu koylar deniz',
        ]);

        $result = $this->search('yüzme molalı tur istiyorum');

        $ids = collect($result['results'])->pluck('id')->all();
        $this->assertContains($hedef->id, $ids, 'Kelime kanalı birebir eşleşen turu getirmeliydi');
        // Kelime kanalından gelen tur ilk sırada olmalı (RRF + semantik taban)
        $this->assertSame($hedef->id, $ids[0]);
    }

    public function test_ay_tercihi_sert_filtre_kalkisi_olmayan_gosterilmez(): void
    {
        $eylul = $this->makeTour(['title' => 'Eylül Turu', 'departure_date' => null]);
        TourDate::create(['tour_id' => $eylul->id, 'departure_date' => now()->setDay(14)->setMonth(9)->addYear()->toDateString(), 'return_date' => now()->setDay(18)->setMonth(9)->addYear()->toDateString(), 'price' => 9000]);

        $agustos = $this->makeTour(['title' => 'Ağustos Turu', 'departure_date' => null]);
        TourDate::create(['tour_id' => $agustos->id, 'departure_date' => now()->setDay(10)->setMonth(8)->addYear()->toDateString(), 'return_date' => now()->setDay(14)->setMonth(8)->addYear()->toDateString(), 'price' => 9000]);

        $result = $this->search('eylülde tur', ['preferred_month' => 9]);

        $ids = collect($result['results'])->pluck('id')->all();
        $this->assertContains($eylul->id, $ids);
        $this->assertNotContains($agustos->id, $ids, 'Eylül isteyene Ağustos turu gösterilmemeli');
    }

    public function test_ay_bulunamazsa_gevseterek_nedenini_soyler(): void
    {
        $this->makeTour(['title' => 'Sadece Ağustos', 'departure_date' => now()->setDay(10)->setMonth(8)->addYear()]);

        $result = $this->search('aralıkta tur', ['preferred_month' => 12]);

        $this->assertNotEmpty($result['results']);
        $this->assertNotNull($result['relaxation_note']);
        $this->assertStringContainsString('Aralık', $result['relaxation_note']);
    }

    public function test_butce_kurtarici_ucuz_tarihi_cipler(): void
    {
        $tour = $this->makeTour(['title' => 'Pahalı Ana Fiyat', 'price' => 45000]);
        TourDate::create(['tour_id' => $tour->id, 'departure_date' => today()->addDays(40)->toDateString(), 'return_date' => today()->addDays(44)->toDateString(), 'price' => 30000]);

        $result = $this->search('tur istiyorum 35 bin bütçe', ['max_budget' => 35000]);

        $item = collect($result['results'])->firstWhere('id', $tour->id);
        $this->assertNotNull($item, 'Bütçe gevşetmesiyle tur listelenmeli');
        $this->assertTrue($item['over_budget']);
        $this->assertNotNull($item['flex_date'], 'Bütçeye giren ucuz tarih çipi olmalı');
        $this->assertStringContainsString('30.000', $item['flex_date']['price']);
    }

    public function test_bulanik_destinasyon_yazim_hatasini_tolere_eder(): void
    {
        $this->makeTour(['title' => 'Kapadokya Turu', 'destination' => 'Kapadokya']);
        cache()->forget('ai_search_known_destinations_v1');

        $found = app(\App\Services\AiSearch\IntentHeuristics::class)
            ->findKnownDestinationFromText('kapadokia turu bakıyorum');

        $this->assertSame('Kapadokya', $found);
    }

    public function test_reranker_acikken_puanlari_harmanlar(): void
    {
        config(['ai.rerank_enabled' => true]);

        $a = $this->makeTour(['title' => 'Tur A']);
        $b = $this->makeTour(['title' => 'Tur B']);
        $c = $this->makeTour(['title' => 'Tur C']);

        OpenAI::fake([
            // intent
            ChatResponse::fake(['choices' => [['index' => 0, 'message' => ['role' => 'assistant', 'content' => '{}']]]]),
            // embedding
            EmbeddingResponse::fake(['data' => [['object' => 'embedding', 'index' => 0, 'embedding' => self::VECTOR]]]),
            // re-ranker: C'ye 10, diğerlerine düşük puan
            ChatResponse::fake(['choices' => [['index' => 0, 'message' => ['role' => 'assistant', 'content' => json_encode([
                'scores' => [
                    ['id' => $a->id, 'score' => 2, 'reason' => 'Az uygun'],
                    ['id' => $b->id, 'score' => 3, 'reason' => 'Orta'],
                    ['id' => $c->id, 'score' => 10, 'reason' => 'Sakin tempolu program tam istediğin gibi'],
                ],
            ])]]]]),
            // yorum
            ChatResponse::fake(['choices' => [['index' => 0, 'message' => ['role' => 'assistant', 'content' => 'İşte.']]]]),
        ]);

        $request = Request::create('/test', 'GET');
        $request->setLaravelSession(app('session.store'));

        $result = app(TourSearchService::class)->performAiSearch($request, 'sakin tempolu tur');
        $this->assertArrayNotHasKey('error', $result, $result['error'] ?? '');

        $first = $result['results'][0];
        $this->assertSame($c->id, $first['id'], 'Re-ranker C\'yi öne almalıydı');
        $this->assertStringContainsString('Sakin tempolu', $first['reason']);
    }

    /** Sohbet v1 ile silinen AiChatRouterTest'ten kurtarıldı. */
    public function test_gerekce_deterministik_uretilir(): void
    {
        $service = app(TourSearchService::class);

        $tour = new Tour(['price' => 9000, 'currency' => 'TRY']);
        $tour->price_try = 9000;
        $tour->month_score = 1.0;
        $tour->nature_score = 0.9;
        $tour->similarity = 0.8;

        $reason = $service->buildTourReason($tour, 20000, 9, null, true, null, null);

        $this->assertIsString($reason);
        $this->assertStringContainsString('Eylül kalkışı var', $reason);
        $this->assertStringContainsString('bütçene uyuyor', $reason);
    }

    /**
     * Tur sayfası AI danışman barı: aramadan gelen kullanıcı bağlamını görür ve
     * tıklama loglanır. (Sohbet v1 ile silinen AiPhase4Test'ten kurtarıldı.)
     */
    public function test_tur_sayfasi_danisman_bari_ai_baglaminda_gosterilir(): void
    {
        $tour = $this->makeTour(['title' => 'Kaş Balayı Turu', 'destination' => 'Kaş', 'price' => 20000]);
        $user = User::factory()->create(['role' => 'visitor']);
        $this->actingAs($user);

        $log = AiSearchLog::create([
            'user_id' => $user->id,
            'session_id' => '', // sahiplik kullanıcı üzerinden (test istekleri arasında session id değişir)
            'raw_query' => 'balayı için sakin bir yer',
            'normalized_query' => 'balayı',
            'intent' => ['max_budget' => 25000],
            'applied_filters' => [],
            'candidate_count' => 1,
            'result_tour_ids' => [$tour->id],
            'result_scores' => [['tour_id' => $tour->id, 'rank' => 1, 'compatibility_score' => 0.87]],
            'latency_ms' => 100,
        ]);

        $response = $this->get(route('tours.show', $tour).'?ai_log_id='.$log->id.'&ai_rank=1');

        $response->assertOk();
        $response->assertSee('Aradığın', false);
        $response->assertSee('%87 uyumlu');
        $response->assertSee('bütçe', false);

        // Tıklama loglandı
        $log->refresh();
        $this->assertSame($tour->id, (int) $log->selected_tour_id);
    }
}
