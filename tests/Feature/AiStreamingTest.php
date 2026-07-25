<?php

namespace Tests\Feature;

use App\Http\Controllers\AiSearchController;
use App\Models\Agency;
use App\Models\AiSearchConversation;
use App\Models\AiSearchMessage;
use App\Models\Tour;
use App\Models\User;
use App\Services\AiSearch\ConversationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Mockery\MockInterface;
use Tests\TestCase;

class AiStreamingTest extends TestCase
{
    use RefreshDatabase;

    public function test_stream_endpoint_returns_text_event_stream_content_type(): void
    {
        $user = User::factory()->create(['role' => 'visitor']);
        $this->actingAs($user);

        // Test runner streaming response body'sini consume etmediği için service'i
        // mock'lamaya gerek yok — endpoint'in sadece header'larını doğruluyoruz.
        $response = $this->postJson(route('ai.search.message.stream'), [
            'message' => 'Avrupa kültür turu 30K',
        ]);

        $response->assertStatus(200);
        $this->assertStringStartsWith('text/event-stream', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('no-cache', $response->headers->get('Cache-Control'));
        $this->assertSame('no', $response->headers->get('X-Accel-Buffering'));
    }

    public function test_respondStreamed_emits_search_tours_comment_done_in_order(): void
    {
        $user = User::factory()->create(['role' => 'visitor']);
        $this->actingAs($user);
        [$tour] = $this->seedVisibleTour();

        $this->mockSearchAndStreamComment($tour, tokens: ['Tabii ', 'ki ', 'yardımcı olayım.']);
        $this->stubConversationServiceWithoutClarification();

        $events = [];
        $emit = function (string $event, $data) use (&$events) {
            $events[] = ['event' => $event, 'data' => $data];
        };

        $service = app(ConversationService::class);
        $conversation = AiSearchConversation::create([
            'user_id' => $user->id,
            'last_message_at' => now(),
        ]);

        $request = request();

        $service->respondStreamed($request, $conversation, 'Avrupa kültür turu 30K', $emit);

        $eventNames = array_column($events, 'event');

        // Beklenen sıra: search → tours → comment (3 chunk) → suggestions (devam çipleri) → done
        $this->assertSame(['search', 'tours', 'comment', 'comment', 'comment', 'suggestions', 'done'], $eventNames);

        // Search event'i conversation uuid ve user_message içermeli
        $this->assertSame($conversation->uuid, $events[0]['data']['conversation_uuid']);
        $this->assertSame('Avrupa kültür turu 30K', $events[0]['data']['user_message']['content']);

        // Tours event'i: {log_id, items} formatında
        $this->assertIsArray($events[1]['data']);
        $this->assertArrayHasKey('items', $events[1]['data']);
        $this->assertArrayHasKey('log_id', $events[1]['data']);
        $this->assertCount(1, $events[1]['data']['items']);
        $this->assertSame($tour->id, $events[1]['data']['items'][0]['id']);
        $this->assertSame(42, $events[1]['data']['log_id']);

        // Comment chunk'ları sırasıyla
        $this->assertSame('Tabii ', $events[2]['data']['delta']);
        $this->assertSame('ki ', $events[3]['data']['delta']);
        $this->assertSame('yardımcı olayım.', $events[4]['data']['delta']);

        // Done event'i meta bilgi içermeli (events[5] = suggestions çipleri)
        $this->assertArrayHasKey('assistant_message_id', $events[6]['data']);
        $this->assertFalse($events[6]['data']['is_clarification']);

        // Asistan mesajının final content'i DB'ye yazılmış olmalı
        $assistantMsg = AiSearchMessage::find($events[6]['data']['assistant_message_id']);
        $this->assertNotNull($assistantMsg);
        $this->assertSame('Tabii ki yardımcı olayım.', $assistantMsg->content);
    }

    public function test_clarification_emits_search_comment_done_without_tours(): void
    {
        $user = User::factory()->create(['role' => 'visitor']);
        $this->actingAs($user);

        // ConversationService partial: hep clarification dönsün
        $controller = $this->mock(AiSearchController::class, function (MockInterface $mock) {
            $mock->makePartial();
            $mock->shouldNotReceive('performAiSearch');
            $mock->shouldNotReceive('streamComment');
        });

        $svc = \Mockery::mock(ConversationService::class, [$controller, app(\App\Services\AiSearch\DestinationKnowledgeService::class)])->makePartial();
        $svc->shouldReceive('maybeAskClarification')->once()->andReturn('Bütçen ne kadar?');
        $this->app->instance(ConversationService::class, $svc);

        $events = [];
        $emit = function (string $e, $d) use (&$events) { $events[] = ['event' => $e, 'data' => $d]; };

        $conversation = AiSearchConversation::create([
            'user_id' => $user->id,
            'last_message_at' => now(),
        ]);

        app(ConversationService::class)->respondStreamed(request(), $conversation, 'tatil', $emit);

        $names = array_column($events, 'event');
        $this->assertSame(['search', 'comment', 'done'], $names);
        $this->assertSame('Bütçen ne kadar?', $events[1]['data']['delta']);
        $this->assertTrue($events[2]['data']['is_clarification']);
    }

    private function stubConversationServiceWithoutClarification(): void
    {
        $controller = app(AiSearchController::class);
        $svc = \Mockery::mock(ConversationService::class, [$controller, app(\App\Services\AiSearch\DestinationKnowledgeService::class)])->makePartial();
        $svc->shouldReceive('maybeAskClarification')->andReturn(null);
        $this->app->instance(ConversationService::class, $svc);
    }

    private function mockSearchAndStreamComment(Tour $tour, array $tokens = ['cevap']): void
    {
        $resultCollection = new Collection([$tour]);
        $tour->similarity = 0.91;
        $tour->compatibility_score = 0.87;
        $tour->seasonal_bonus = 0.0;
        $tour->vibe_score = 0.0;
        $tour->destination_summary = null;

        $cleanResults = new Collection([
            [
                'id' => $tour->id,
                'title' => $tour->title,
                'slug' => $tour->slug,
                'url' => route('tours.show', $tour->id),
                'destination' => $tour->destination,
                'price' => (float) $tour->price,
                'currency' => $tour->currency,
                'duration_days' => $tour->duration_days,
                'departure_date' => $tour->departure_date,
                'image' => $tour->image,
                'rank' => 1,
                'similarity' => 0.91,
                'compatibility_score' => 0.87,
                'nature_score' => 1.0,
                'city_escape_score' => 1.0,
                'lively_score' => 1.0,
                'destination_score' => 1.0,
                'month_score' => 1.0,
                'vibe_score' => 0.0,
                'seasonal_bonus' => 0.0,
            ],
        ]);

        $this->mock(AiSearchController::class, function (MockInterface $mock) use ($cleanResults, $resultCollection, $tokens) {
            $mock->makePartial();
            $mock->shouldReceive('performAiSearch')
                ->once()
                ->andReturn([
                    'results' => $cleanResults,
                    'aiComment' => null,
                    'log_id' => 42,
                    'intent' => ['max_budget' => 30000],
                    'applied_filters' => [],
                    'latency_ms' => 100,
                    '_comment_context' => [
                        'query' => 'Avrupa kültür turu 30K',
                        'results' => $resultCollection,
                        'knowledge_context' => '',
                        'preferred_destination' => null,
                    ],
                ]);
            $mock->shouldReceive('streamComment')
                ->once()
                ->andReturnUsing(function ($q, $results, $ctx, $pref, $cb) use ($tokens) {
                    $full = '';
                    foreach ($tokens as $t) {
                        $full .= $t;
                        $cb($t);
                    }
                    return $full;
                });
        });
    }

    private function seedVisibleTour(): array
    {
        Queue::fake();
        Notification::fake();

        $agency = Agency::create([
            'name' => 'Stream Test',
            'slug' => 'stream-test',
            'email' => 'stream@example.com',
            'is_active' => true,
            'legacy_category_access' => true,
        ]);

        $tour = Tour::create([
            'agency_id' => $agency->id,
            'category_id' => null,
            'title' => 'Test Turu',
            'destination' => 'Roma',
            'description' => 'Test',
            'price' => 18500,
            'currency' => 'TRY',
            'duration_days' => 5,
            'departure_date' => today()->addDays(30),
            'return_date' => today()->addDays(34),
            'is_active' => true,
        ]);

        return [$tour, $agency];
    }
}
