<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\AiSearchLog;
use App\Models\Tour;
use App\Support\AiWeightEvaluator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Faz 3: öğrenen sistem — replay değerlendirme, kalibrasyon korkulukları,
 * CTR öncelikleri (sınırlı), kalite raporu.
 */
class AiLearningTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int, int> makeLog'un yarattığı üç turun id'leri (indeks 0-2) */
    private array $tourIds = [];

    private function makeTours(): void
    {
        if (! empty($this->tourIds)) {
            return;
        }
        $agency = Agency::firstOrCreate(['slug' => 'log-acenta'], [
            'name' => 'Log Acenta', 'email' => 'log@example.com', 'is_active' => true,
            'approval_status' => Agency::STATUS_APPROVED, 'approved_at' => now(), 'legacy_category_access' => true,
        ]);
        foreach ([1, 2, 3] as $i) {
            $this->tourIds[] = Tour::create([
                'agency_id' => $agency->id, 'title' => "Log Tur {$i}", 'destination' => 'Ege',
                'duration_days' => 3, 'currency' => 'TRY', 'price' => 1000, 'is_active' => true,
            ])->id;
        }
    }

    private function makeLog(array $attrs = []): AiSearchLog
    {
        $this->makeTours();

        $items = [];
        foreach ($this->tourIds as $i => $id) {
            $items[] = [
                'tour_id' => $id, 'rank' => $i + 1,
                'compatibility_score' => 0.8 - $i * 0.1,
                'semantic_score' => 0.8 - $i * 0.1,
                'budget_score' => 1.0, 'international_score' => 1.0, 'visa_score' => 1.0,
                'duration_score' => 1.0, 'nature_score' => $i === 2 ? 1.0 : 0.2,
                'city_escape_score' => 1.0, 'lively_score' => 1.0,
                'destination_score' => 1.0, 'month_score' => 1.0,
                'vibe_score' => 0.0, 'seasonal_bonus' => 0.0, 'rejection_penalty' => 0.0,
            ];
        }

        return AiSearchLog::create(array_merge([
            'session_id' => 't',
            'raw_query' => 'doğa turu',
            'normalized_query' => 'doğa turu',
            'intent' => [],
            'applied_filters' => ['wants_nature' => true],
            'candidate_count' => 3,
            'result_tour_ids' => $this->tourIds,
            'result_scores' => $items,
            'latency_ms' => 100,
        ], $attrs));
    }

    public function test_replay_degerlendirme_tiklanan_turun_sirasini_olcer(): void
    {
        // Kullanıcı 2. sıradaki turu tıklamış
        $this->makeTours();
        $this->makeLog(['selected_tour_id' => $this->tourIds[1], 'selected_rank' => 2, 'selected_at' => now()]);

        $metrics = AiWeightEvaluator::evaluate(AiSearchLog::all());

        $this->assertSame(1, $metrics['n']);
        // Yeniden hesaplanan sırada tıklanan tur 3. (nature ekseni idx2'yi öne itiyor) → MRR 1/3
        $this->assertEqualsWithDelta(1 / 3, $metrics['mrr'], 0.01);
        $this->assertSame(1.0, $metrics['hit_at_7']);
    }

    public function test_agirlik_degisikligi_replayde_sirayi_degistirir(): void
    {
        // Tıklanan 103: nature_score'u yüksek tek tur — nature ağırlığı artınca öne geçmeli
        $this->makeTours();
        $this->makeLog(['selected_tour_id' => $this->tourIds[2], 'selected_at' => now()]);

        $baseline = AiWeightEvaluator::evaluate(AiSearchLog::all());
        $boosted = AiWeightEvaluator::evaluate(AiSearchLog::all(), array_merge(
            AiWeightEvaluator::defaultWeights(),
            ['nature' => 0.60] // abartılı artış — sıra değişimini garanti eder
        ));

        $this->assertGreaterThan($baseline['mrr'], $boosted['mrr']);
    }

    public function test_kalibrasyon_kucuk_orneklemde_uygulamaz(): void
    {
        $this->makeTours();
        $this->makeLog(['selected_tour_id' => $this->tourIds[1], 'selected_at' => now()]);

        $this->artisan('ai:calibrate-weights')
            ->expectsOutputToContain('Yetersiz veri')
            ->assertSuccessful();

        $this->assertNull(Cache::get(AiWeightEvaluator::CACHE_KEY));
    }

    public function test_ctr_onceligi_sinirli_ve_tiklanani_odullendirir(): void
    {
        $agency = Agency::create([
            'name' => 'Ctr Acenta', 'slug' => 'ctr-acenta', 'email' => 'ctr@example.com',
            'is_active' => true, 'approval_status' => Agency::STATUS_APPROVED,
            'approved_at' => now(), 'legacy_category_access' => true,
        ]);
        $hot = Tour::create(['agency_id' => $agency->id, 'title' => 'Tıklanan', 'destination' => 'Ege', 'duration_days' => 3, 'currency' => 'TRY', 'price' => 1, 'is_active' => true]);
        $cold = Tour::create(['agency_id' => $agency->id, 'title' => 'Atlanan', 'destination' => 'Ege', 'duration_days' => 3, 'currency' => 'TRY', 'price' => 1, 'is_active' => true]);

        // 10 gösterim: hot hep tıklanmış, cold hiç
        for ($i = 0; $i < 10; $i++) {
            AiSearchLog::create([
                'session_id' => 't', 'raw_query' => 'q', 'normalized_query' => 'q',
                'intent' => [], 'applied_filters' => [], 'candidate_count' => 2,
                'result_tour_ids' => [$hot->id, $cold->id], 'result_scores' => [],
                'latency_ms' => 1, 'selected_tour_id' => $hot->id, 'selected_at' => now(),
            ]);
        }

        $this->artisan('ai:update-ctr-priors')->assertSuccessful();

        $hot->refresh();
        $cold->refresh();
        $this->assertGreaterThan(0, (float) $hot->ai_ctr_bonus);
        $this->assertLessThan(0, (float) $cold->ai_ctr_bonus);
        // Sınır: ±0.03 aşılamaz
        $this->assertLessThanOrEqual(0.03, abs((float) $hot->ai_ctr_bonus));
        $this->assertLessThanOrEqual(0.03, abs((float) $cold->ai_ctr_bonus));
    }

    public function test_kalite_raporu_calisir_ve_cache_yazar(): void
    {
        $this->makeLog(); // tıklamasız arama
        AiSearchLog::create([
            'session_id' => 't', 'raw_query' => 'balayı maldivler', 'normalized_query' => 'balayı',
            'intent' => [], 'applied_filters' => ['relaxation' => 'not'], 'candidate_count' => 0,
            'result_tour_ids' => [], 'result_scores' => [], 'latency_ms' => 1,
        ]);

        $this->artisan('ai:quality-report')->assertSuccessful();

        $report = Cache::get('ai:last_quality_report');
        $this->assertNotNull($report);
        $this->assertSame(2, $report['toplam_arama']);
        $this->assertSame(1, $report['sifir_sonuc']);
        $this->assertContains('balayı maldivler', $report['sifir_sonuc_sorgular']);
    }
}
