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
 * 0-sonuç gevşetme merdiveninin SON BASAMAĞI: hiçbir filtre kombinasyonu sonuç
 * vermese bile aktif tur varsa kullanıcı asla çıplak "bulunamadı" görmez.
 */
class AiSearchRelaxationTest extends TestCase
{
    use RefreshDatabase;

    private const VECTOR = [0.1, 0.2, 0.3];

    private function makeActiveTour(): Tour
    {
        $agency = Agency::create([
            'name' => 'Relax Acenta',
            'slug' => 'relax-acenta',
            'email' => 'relax@example.com',
            'is_active' => true,
            'approval_status' => Agency::STATUS_APPROVED,
            'approved_at' => now(),
            'legacy_category_access' => true,
        ]);

        return Tour::create([
            'agency_id' => $agency->id,
            'title' => 'Yüzme Molalı Ege Turu',
            'destination' => 'Ege',
            'departure_city' => 'İstanbul',
            'duration_days' => 5,
            'currency' => 'TRY',
            'price' => 9000,
            'is_active' => true,
            'is_international' => false,
            'departure_date' => today()->addDays(20),
            'embedding' => self::VECTOR,
        ]);
    }

    public function test_miras_filtreler_sifir_sonuc_uretse_bile_en_yakin_turlar_gosterilir(): void
    {
        $this->makeActiveTour();

        OpenAI::fake([
            // intent: boş JSON (heuristikler de sinyal bulamaz)
            ChatResponse::fake(['choices' => [['index' => 0, 'message' => ['role' => 'assistant', 'content' => '{}']]]]),
            EmbeddingResponse::fake(['data' => [['object' => 'embedding', 'index' => 0, 'embedding' => self::VECTOR]]]),
            ChatResponse::fake(['choices' => [['index' => 0, 'message' => ['role' => 'assistant', 'content' => 'En yakın seçenekler bunlar.']]]]),
        ]);

        // Önceki turdan miras kalan minicik bütçe DB'deki tek turu sıfırlıyor —
        // gevşetme merdiveni bütçeyi bırakıp turu göstermeli
        $request = Request::create('/test', 'GET');
        $request->setLaravelSession(app('session.store'));

        $result = app(AiSearchController::class)->performAiSearch(
            $request,
            'yazlık bir tur istiyorum denize gireyim',
            ['max_budget' => 100]
        );

        $this->assertIsArray($result);
        if (isset($result['error'])) {
            $this->fail('performAiSearch hata döndü: '.$result['error']);
        }
        $this->assertGreaterThan(0, count($result['results']), 'Gevşetme merdiveni sonuç üretmeliydi');
        $this->assertNotNull($result['relaxation_note'], 'Kullanıcıya gevşetme nedeni söylenmeli');
        $this->assertSame('Yüzme Molalı Ege Turu', $result['results'][0]['title']);
    }

    public function test_yon_asla_gevsetilmez_yurt_disi_isteyene_yurt_ici_onerilmez(): void
    {
        $this->makeActiveTour(); // yurt içi Ege turu

        OpenAI::fake([
            ChatResponse::fake(['choices' => [['index' => 0, 'message' => ['role' => 'assistant', 'content' => '{}']]]]),
            EmbeddingResponse::fake(['data' => [['object' => 'embedding', 'index' => 0, 'embedding' => self::VECTOR]]]),
            ChatResponse::fake(['choices' => [['index' => 0, 'message' => ['role' => 'assistant', 'content' => 'Maalesef yurt dışı tur yok.']]]]),
        ]);

        $request = Request::create('/test', 'GET');
        $request->setLaravelSession(app('session.store'));

        $result = app(AiSearchController::class)->performAiSearch($request, 'yurt dışı istiyorum');

        $this->assertIsArray($result);
        // Envanterde yurt dışı tur yok → yurt içi Ege turu ASLA önerilmez, dürüst 0
        $this->assertCount(0, $result['results']);
    }

    public function test_destinasyon_siniflandirici(): void
    {
        $cases = [
            ['İtalya', true],
            ['İspanya', true],
            ['Barselona, Valencia, Granada, Madrid', true],
            ['Kuzey İtalya', true],
            ['Balkanlar', true],
            ['Dubai', true],
            ['Ege', false],
            ['Kapadokya', false],
            ['Antalya', false],
            ['Salda Gölü, Pamukkale, Çeşme', false],
            ['İstanbul', false],
        ];

        foreach ($cases as [$destination, $expected]) {
            $this->assertSame(
                $expected,
                \App\Support\DestinationClassifier::isInternational($destination),
                "Sınıflandırma hatalı: {$destination}"
            );
        }

        $this->assertNull(\App\Support\DestinationClassifier::isInternational('Bilinmeyen Yer'));
    }

    public function test_kayitta_yurt_disi_bayragi_destinasyondan_turetilir(): void
    {
        $agency = Agency::create([
            'name' => 'Sınıf Acenta',
            'slug' => 'sinif-acenta',
            'email' => 'sinif@example.com',
            'is_active' => true,
            'approval_status' => Agency::STATUS_APPROVED,
            'approved_at' => now(),
            'legacy_category_access' => true,
        ]);
        $category = \App\Models\Category::create(['name' => 'Yurt Dışı', 'slug' => 'yurt-disi-k', 'is_active' => true]);
        $user = \App\Models\User::factory()->create(['role' => 'agency', 'agency_id' => $agency->id]);

        $this->actingAs($user)->post(route('agency.tours.store'), [
            'category_id' => $category->id,
            'title' => 'Klasik İspanya Turu',
            'destination' => 'Barselona, Valencia, Granada',
            'departure_city' => 'İstanbul',
            'duration_days' => 7,
            'currency' => 'EUR',
            'pricing_options' => [['price' => '899', 'departure_dates' => [today()->addDays(30)->toDateString()]]],
        ])->assertRedirect(route('agency.tours.index'));

        $tour = Tour::firstWhere('title', 'Klasik İspanya Turu');
        $this->assertNotNull($tour);
        $this->assertTrue($tour->is_international, 'İspanya turu yurt dışı olarak işaretlenmeliydi');
    }

    public function test_yazlik_kelimesi_temmuza_eslenir(): void
    {
        $controller = app(AiSearchController::class);
        $reflection = new \ReflectionMethod($controller, 'extractPreferredMonth');

        $this->assertSame(7, $reflection->invoke($controller, [], 'yazlık bir tur istiyorum'));
    }

    public function test_sohbette_en_iyi_7_gosterilir_tamami_loglanir_ve_sayfada_acilir(): void
    {
        $agency = Agency::create([
            'name' => 'Top7 Acenta',
            'slug' => 'top7-acenta',
            'email' => 'top7@example.com',
            'is_active' => true,
            'approval_status' => Agency::STATUS_APPROVED,
            'approved_at' => now(),
            'legacy_category_access' => true,
        ]);

        for ($i = 1; $i <= 10; $i++) {
            Tour::create([
                'agency_id' => $agency->id,
                'title' => "Ege Turu {$i}",
                'destination' => 'Ege',
                'departure_city' => 'İstanbul',
                'duration_days' => 5,
                'currency' => 'TRY',
                'price' => 9000 + $i,
                'is_active' => true,
                'is_international' => false,
                'departure_date' => today()->addDays(20),
                'embedding' => self::VECTOR,
            ]);
        }

        OpenAI::fake([
            ChatResponse::fake(['choices' => [['index' => 0, 'message' => ['role' => 'assistant', 'content' => '{}']]]]),
            EmbeddingResponse::fake(['data' => [['object' => 'embedding', 'index' => 0, 'embedding' => self::VECTOR]]]),
            ChatResponse::fake(['choices' => [['index' => 0, 'message' => ['role' => 'assistant', 'content' => 'İşte seçenekler.']]]]),
        ]);

        $user = \App\Models\User::factory()->create(['role' => 'visitor']);
        $this->actingAs($user);

        $request = Request::create('/test', 'GET');
        $request->setLaravelSession(app('session.store'));
        $request->setUserResolver(fn () => $user);

        $result = app(AiSearchController::class)->performAiSearch($request, 'ege turu istiyorum');

        $this->assertIsArray($result);
        // Sohbette en isabetli 7 tur
        $this->assertCount(7, $result['results']);
        $this->assertSame(10, $result['total_matches']);
        $this->assertNotNull($result['all_results_url']);

        // Log TAM listeyi tutar
        $log = \App\Models\AiSearchLog::findOrFail($result['log_id']);
        $this->assertCount(10, $log->result_tour_ids);

        // "Tümünü gör" sayfası sahibine açılır ve 10 turu listeler
        $response = $this->get($result['all_results_url']);
        $response->assertOk();
        $response->assertSee('Ege Turu 1');
        $response->assertSee('Ege Turu 10');

        // Başkası erişemez
        $other = \App\Models\User::factory()->create(['role' => 'visitor']);
        $this->actingAs($other);
        $this->get($result['all_results_url'])->assertForbidden();
    }
}
