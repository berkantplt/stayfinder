<?php

namespace Tests\Feature;

use App\Http\Controllers\AiSearchController;
use App\Models\Agency;
use App\Models\Tour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AiTopKByCosineTest extends TestCase
{
    use RefreshDatabase;

    public function test_topkbycosine_returns_top_k_in_descending_similarity_order(): void
    {
        Queue::fake();
        Notification::fake();

        $agency = Agency::create([
            'name' => 'Topk Test',
            'slug' => 'topk-test',
            'email' => 'topk@example.com',
            'is_active' => true,
            'legacy_category_access' => true,
        ]);

        // Query vector: [1, 0, 0, ..., 0]
        $queryVector = array_pad([1.0], 1536, 0.0);

        // 5 tur — her birinde farklı şekilde aligned embedding
        $tours = [];
        $embeddings = [
            'highest' => array_pad([1.0], 1536, 0.0),       // similarity = 1.0
            'high'    => array_pad([0.9, 0.1], 1536, 0.0),  // similarity ≈ 0.99
            'medium'  => array_pad([0.5, 0.5], 1536, 0.0),  // similarity ≈ 0.71
            'low'     => array_pad([0.1, 0.9], 1536, 0.0),  // similarity ≈ 0.11
            'orth'    => array_pad([0.0, 1.0], 1536, 0.0),  // similarity = 0
        ];

        foreach ($embeddings as $label => $embedding) {
            $tours[$label] = Tour::create([
                'agency_id' => $agency->id,
                'title' => 'Tur ' . $label,
                'destination' => 'Test',
                'description' => 'Test description',
                'price' => 5000,
                'currency' => 'TRY',
                'duration_days' => 3,
                'departure_date' => today()->addDays(5),
                'return_date' => today()->addDays(7),
                'is_active' => true,
                'embedding' => $embedding,
            ]);
        }

        $controller = app(AiSearchController::class);
        $reflection = new \ReflectionMethod($controller, 'topKByCosine');
        $reflection->setAccessible(true);

        $query = Tour::whereNotNull('embedding')
            ->where('agency_id', $agency->id);

        $top3 = $reflection->invoke($controller, $query, $queryVector, 3);

        $this->assertCount(3, $top3);

        // Sıralama: highest > high > medium
        $this->assertSame($tours['highest']->id, $top3[0]->id);
        $this->assertSame($tours['high']->id, $top3[1]->id);
        $this->assertSame($tours['medium']->id, $top3[2]->id);

        // Similarity attach edildi mi
        $this->assertEqualsWithDelta(1.0, $top3[0]->similarity, 0.001);
        $this->assertGreaterThan($top3[1]->similarity, $top3[0]->similarity);
        $this->assertGreaterThan($top3[2]->similarity, $top3[1]->similarity);

        // Agency relation eager loaded mı
        $this->assertTrue($top3[0]->relationLoaded('agency'));
    }

    public function test_topkbycosine_handles_empty_candidate_set(): void
    {
        $controller = app(AiSearchController::class);
        $reflection = new \ReflectionMethod($controller, 'topKByCosine');
        $reflection->setAccessible(true);

        $emptyQuery = Tour::whereNotNull('embedding')->where('id', -1);
        $queryVector = array_pad([1.0], 1536, 0.0);

        $result = $reflection->invoke($controller, $emptyQuery, $queryVector, 50);

        $this->assertCount(0, $result);
    }

    public function test_topkbycosine_skips_tours_with_null_embedding(): void
    {
        Queue::fake();
        Notification::fake();

        $agency = Agency::create([
            'name' => 'Null Embed Test',
            'slug' => 'null-embed',
            'email' => 'n@example.com',
            'is_active' => true,
            'legacy_category_access' => true,
        ]);

        // Embedding'i olan tek tur
        $valid = Tour::create([
            'agency_id' => $agency->id,
            'title' => 'Valid',
            'destination' => 'X',
            'description' => 'd',
            'price' => 100, 'currency' => 'TRY', 'duration_days' => 1,
            'departure_date' => today()->addDay(),
            'return_date' => today()->addDays(2),
            'is_active' => true,
            'embedding' => array_pad([1.0], 1536, 0.0),
        ]);

        // Embedding null — bu tur cursor'da skip edilmeli
        Tour::create([
            'agency_id' => $agency->id,
            'title' => 'No embedding',
            'destination' => 'Y',
            'description' => 'd',
            'price' => 100, 'currency' => 'TRY', 'duration_days' => 1,
            'departure_date' => today()->addDay(),
            'return_date' => today()->addDays(2),
            'is_active' => true,
            'embedding' => null,
        ]);

        $controller = app(AiSearchController::class);
        $reflection = new \ReflectionMethod($controller, 'topKByCosine');
        $reflection->setAccessible(true);

        // Filter sadece embedding'i NULL OLMAYAN turları çekiyor (mevcut akıştaki gibi)
        $query = Tour::whereNotNull('embedding')->where('agency_id', $agency->id);
        $queryVector = array_pad([1.0], 1536, 0.0);

        $result = $reflection->invoke($controller, $query, $queryVector, 10);

        $this->assertCount(1, $result);
        $this->assertSame($valid->id, $result[0]->id);
    }
}
