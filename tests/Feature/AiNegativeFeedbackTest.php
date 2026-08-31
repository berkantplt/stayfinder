<?php

namespace Tests\Feature;

use App\Services\AiSearch\TourSearchService;
use App\Models\Agency;
use App\Models\AiSearchLog;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AiNegativeFeedbackTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Tour::create observer'ı sync queue'da gerçek OpenAI'a gidiyor — testlerde block et
        Queue::fake();
        Notification::fake();
    }

    public function test_owner_can_reject_a_tour_with_reason(): void
    {
        [$user, $log, $tours] = $this->seedLogWithResults();
        $this->actingAs($user);

        $response = $this->postJson(route('ai.search.reject', $log), [
            'tour_id' => $tours[0]->id,
            'reason' => 'too_expensive',
        ]);

        $response->assertOk();
        $response->assertJsonPath('ok', true);
        $response->assertJsonPath('rejected_tour_ids', [$tours[0]->id]);

        $log->refresh();
        $this->assertSame([$tours[0]->id], $log->rejectedTourIds());
        $this->assertNotNull($log->rejected_at);

        $entries = $log->rejected_tour_ids;
        $this->assertSame('too_expensive', $entries[0]['reason']);
    }

    public function test_repeat_rejection_updates_reason_idempotently(): void
    {
        [$user, $log, $tours] = $this->seedLogWithResults();
        $this->actingAs($user);

        $this->postJson(route('ai.search.reject', $log), [
            'tour_id' => $tours[0]->id,
            'reason' => 'wrong_destination',
        ])->assertOk();

        $this->postJson(route('ai.search.reject', $log), [
            'tour_id' => $tours[0]->id,
            'reason' => 'too_expensive',
        ])->assertOk();

        $log->refresh();
        $this->assertCount(1, $log->rejected_tour_ids);
        $this->assertSame('too_expensive', $log->rejected_tour_ids[0]['reason']);
    }

    public function test_other_users_cannot_reject_someone_elses_log(): void
    {
        [$owner, $log, $tours] = $this->seedLogWithResults();
        $intruder = User::factory()->create(['role' => 'visitor']);

        $this->actingAs($intruder);

        $this->postJson(route('ai.search.reject', $log), [
            'tour_id' => $tours[0]->id,
        ])->assertForbidden();
    }

    public function test_cannot_reject_tour_not_in_log_results(): void
    {
        [$user, $log] = $this->seedLogWithResults();
        $this->actingAs($user);

        // Log'da olmayan başka bir tour oluştur
        $otherAgency = $this->makeAgency('other');
        $otherTour = $this->makeTour($otherAgency, 'Başka Tur');

        $this->postJson(route('ai.search.reject', $log), [
            'tour_id' => $otherTour->id,
        ])->assertStatus(422);
    }

    public function test_invalid_reason_is_rejected(): void
    {
        [$user, $log, $tours] = $this->seedLogWithResults();
        $this->actingAs($user);

        $this->postJson(route('ai.search.reject', $log), [
            'tour_id' => $tours[0]->id,
            'reason' => 'made_up_reason',
        ])->assertStatus(422);
    }

    public function test_rejected_tour_ids_collected_from_recent_logs(): void
    {
        [$user, $log, $tours] = $this->seedLogWithResults();
        $this->actingAs($user);

        $log->recordRejection($tours[0]->id, 'too_expensive');
        $log->recordRejection($tours[1]->id, 'wrong_vibe');

        $controller = app(TourSearchService::class);
        $reflection = new \ReflectionMethod($controller, 'collectRejectedTourIds');

        $rejected = $reflection->invoke($controller, $this->buildRequestWithSessionAndUser($user));

        sort($rejected);
        $expected = [$tours[0]->id, $tours[1]->id];
        sort($expected);
        $this->assertSame($expected, $rejected);
    }

    public function test_old_rejections_beyond_24h_are_ignored(): void
    {
        [$user, $log, $tours] = $this->seedLogWithResults();
        $this->actingAs($user);

        // Manuel olarak 48 saat öncesi rejection ekle
        $log->update([
            'rejected_tour_ids' => [['tour_id' => $tours[0]->id, 'reason' => 'too_expensive']],
            'rejected_at' => now()->subDays(2),
        ]);

        $controller = app(TourSearchService::class);
        $reflection = new \ReflectionMethod($controller, 'collectRejectedTourIds');

        $rejected = $reflection->invoke($controller, $this->buildRequestWithSessionAndUser($user));
        $this->assertEmpty($rejected);
    }

    private function buildRequestWithSessionAndUser(?User $user = null): \Illuminate\Http\Request
    {
        $request = \Illuminate\Http\Request::create('/test', 'GET');
        $request->setLaravelSession(app('session')->driver());
        if ($user) {
            $request->setUserResolver(fn() => $user);
        }

        return $request;
    }

    public function test_rejection_avg_embedding_is_centroid_of_rejected_tours(): void
    {
        $agency = $this->makeAgency('emb');
        $t1 = $this->makeTour($agency, 'T1', [1.0, 0.0, 0.0]);
        $t2 = $this->makeTour($agency, 'T2', [0.0, 1.0, 0.0]);

        // Debug: gerçek embedding'leri DB'den oku
        $fresh1 = $t1->fresh();
        $fresh2 = $t2->fresh();
        $this->assertCount(3, $fresh1->embedding, 'T1 embedding DB\'de 3-dim olmalı');
        $this->assertCount(3, $fresh2->embedding, 'T2 embedding DB\'de 3-dim olmalı');

        $avg = app(\App\Services\AiSearch\HybridRanker::class)->computeRejectionAvgEmbedding([$t1->id, $t2->id]);

        $this->assertNotNull($avg);
        $this->assertCount(3, $avg);
        $this->assertEqualsWithDelta(0.5, $avg[0], 0.0001);
        $this->assertEqualsWithDelta(0.5, $avg[1], 0.0001);
        $this->assertEqualsWithDelta(0.0, $avg[2], 0.0001);
    }

    public function test_compute_avg_returns_null_for_empty_or_missing_embeddings(): void
    {
        $ranker = app(\App\Services\AiSearch\HybridRanker::class);

        $this->assertNull($ranker->computeRejectionAvgEmbedding([]));
        $this->assertNull($ranker->computeRejectionAvgEmbedding([99999])); // var olmayan id
    }

    /**
     * @return array{0: User, 1: AiSearchLog, 2: array<int, Tour>}
     */
    private function seedLogWithResults(): array
    {
        $user = User::factory()->create(['role' => 'visitor']);
        $agency = $this->makeAgency('seed');

        $t1 = $this->makeTour($agency, 'Tur 1');
        $t2 = $this->makeTour($agency, 'Tur 2');

        $log = AiSearchLog::create([
            'user_id' => $user->id,
            'session_id' => 'sess-' . uniqid(),
            'raw_query' => 'test',
            'normalized_query' => 'test',
            'intent' => [],
            'applied_filters' => [],
            'candidate_count' => 2,
            'result_tour_ids' => [$t1->id, $t2->id],
            'result_scores' => [],
            'latency_ms' => 100,
        ]);

        return [$user, $log, [$t1, $t2]];
    }

    private function makeAgency(string $tag): Agency
    {
        return Agency::create([
            'name' => 'Agency ' . $tag,
            'slug' => 'agency-' . $tag . '-' . uniqid(),
            'email' => $tag . '-' . uniqid() . '@example.com',
            'is_active' => true,
            'legacy_category_access' => true,
        ]);
    }

    private function makeTour(Agency $agency, string $title, ?array $embedding = null): Tour
    {
        return Tour::create([
            'agency_id' => $agency->id,
            'category_id' => null,
            'title' => $title,
            'destination' => 'Test',
            'description' => 'd',
            'price' => 10000,
            'currency' => 'TRY',
            'duration_days' => 5,
            'departure_date' => today()->addDays(15),
            'return_date' => today()->addDays(20),
            'is_active' => true,
            'embedding' => $embedding,
        ]);
    }
}
