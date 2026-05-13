<?php

namespace Tests\Feature;

use App\Http\Controllers\AiSearchController;
use App\Models\AiSearchConversation;
use App\Models\AiSearchMessage;
use App\Models\Agency;
use App\Models\Tour;
use App\Models\User;
use App\Services\AiSearch\ConversationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Mockery\MockInterface;
use Tests\TestCase;

class AiConversationFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_turn_conversation_persists_messages_and_merges_intent(): void
    {
        $user = User::factory()->create(['role' => 'visitor']);
        $this->actingAs($user);

        [$tour] = $this->seedVisibleTour();

        $turn1Result = $this->fakeSearchResult(
            $tour,
            aiComment: 'Avrupa kültür turlarına bütçene uygun bir öneri.',
            intent: ['max_budget' => 30000, 'preferred_destination' => 'Avrupa', 'is_international' => true],
            appliedFilters: ['max_budget' => 30000, 'preferred_destination' => 'Avrupa']
        );

        $turn2Result = $this->fakeSearchResult(
            $tour,
            aiComment: 'Eylül için seçenekler güncellendi.',
            intent: ['max_budget' => 30000, 'preferred_destination' => 'Avrupa', 'is_international' => true, 'preferred_month' => 9],
            appliedFilters: ['max_budget' => 30000, 'preferred_destination' => 'Avrupa', 'preferred_month' => 9]
        );

        $this->mock(AiSearchController::class, function (MockInterface $mock) use ($turn1Result, $turn2Result) {
            $mock->makePartial();
            $mock->shouldReceive('performAiSearch')
                ->twice()
                ->andReturn($turn1Result, $turn2Result);
        });
        $this->stubConversationServiceWithoutClarification();

        // Turn 1
        $r1 = $this->postJson(route('ai.search.message'), [
            'message' => '30K bütçe, Avrupa kültür turu',
        ])->assertOk();

        $uuid = $r1->json('conversation_uuid');
        $this->assertNotEmpty($uuid);
        $this->assertSame('Avrupa kültür turlarına bütçene uygun bir öneri.', $r1->json('assistant_message.content'));
        $this->assertCount(1, $r1->json('tours'));
        $this->assertSame($tour->id, $r1->json('tours.0.id'));

        $conversation = AiSearchConversation::where('uuid', $uuid)->firstOrFail();
        $this->assertSame(30000, $conversation->current_intent['max_budget']);
        $this->assertSame('Avrupa', $conversation->current_intent['preferred_destination']);

        // Turn 2: bütçeyi tekrar söylemeden ay ekle
        $r2 = $this->postJson(route('ai.search.message'), [
            'message' => 'Eylül olsun',
            'conversation_uuid' => $uuid,
        ])->assertOk();

        $this->assertSame($uuid, $r2->json('conversation_uuid'));
        $this->assertSame('Eylül için seçenekler güncellendi.', $r2->json('assistant_message.content'));

        $conversation->refresh();
        // Intent merge: önceki bütçe + Avrupa korundu, ay eklendi
        $this->assertSame(30000, $conversation->current_intent['max_budget']);
        $this->assertSame('Avrupa', $conversation->current_intent['preferred_destination']);
        $this->assertSame(9, $conversation->current_intent['preferred_month']);

        // 2 user + 2 assistant = 4 mesaj
        $this->assertSame(4, AiSearchMessage::where('conversation_id', $conversation->id)->count());

        // Title ilk mesajdan oluşturuldu
        $this->assertStringStartsWith('30K bütçe', $conversation->title);
    }

    public function test_send_message_passes_previous_intent_to_search_on_second_turn(): void
    {
        $user = User::factory()->create(['role' => 'visitor']);
        $this->actingAs($user);

        [$tour] = $this->seedVisibleTour();

        $firstIntent = ['max_budget' => 25000, 'is_international' => false];

        $captured = ['second_call_previous_intent' => null];

        $this->mock(AiSearchController::class, function (MockInterface $mock) use ($tour, $firstIntent, &$captured) {
            $mock->makePartial();

            $turn1 = $this->fakeSearchResult($tour, aiComment: '1. cevap', intent: $firstIntent, appliedFilters: $firstIntent);
            $turn2 = $this->fakeSearchResult(
                $tour,
                aiComment: '2. cevap',
                intent: array_merge($firstIntent, ['preferred_month' => 7]),
                appliedFilters: array_merge($firstIntent, ['preferred_month' => 7])
            );

            $mock->shouldReceive('performAiSearch')
                ->twice()
                ->andReturnUsing(function ($req, $msg, $prev = null) use ($turn1, $turn2, &$captured) {
                    if (!isset($captured['first_done'])) {
                        $captured['first_done'] = true;
                        return $turn1;
                    }
                    $captured['second_call_previous_intent'] = $prev;
                    return $turn2;
                });
        });
        $this->stubConversationServiceWithoutClarification();

        $r1 = $this->postJson(route('ai.search.message'), ['message' => 'Yurt içi 25K'])->assertOk();
        $uuid = $r1->json('conversation_uuid');

        $this->postJson(route('ai.search.message'), [
            'message' => 'Temmuz olsun',
            'conversation_uuid' => $uuid,
        ])->assertOk();

        $this->assertNotNull($captured['second_call_previous_intent']);
        $this->assertSame(25000, $captured['second_call_previous_intent']['max_budget']);
        $this->assertFalse($captured['second_call_previous_intent']['is_international']);
    }

    public function test_assistant_asks_clarifying_question_when_intent_is_too_vague(): void
    {
        $user = User::factory()->create(['role' => 'visitor']);
        $this->actingAs($user);

        [$tour] = $this->seedVisibleTour();

        // ConversationService partial: clarification döndür
        $controller = $this->mock(AiSearchController::class, function (MockInterface $mock) {
            $mock->makePartial();
            $mock->shouldNotReceive('performAiSearch');
        });

        $svc = \Mockery::mock(ConversationService::class, [$controller])->makePartial();
        $svc->shouldReceive('maybeAskClarification')
            ->once()
            ->andReturn('Tatil için bir bütçe aralığın var mı?');
        $this->app->instance(ConversationService::class, $svc);

        $r = $this->postJson(route('ai.search.message'), [
            'message' => 'tatil yapmak istiyorum',
        ])->assertOk();

        $this->assertTrue($r->json('is_clarification'));
        $this->assertSame('Tatil için bir bütçe aralığın var mı?', $r->json('assistant_message.content'));
        $this->assertSame([], $r->json('tours'));

        $conversation = AiSearchConversation::where('uuid', $r->json('conversation_uuid'))->firstOrFail();
        $this->assertSame(1, $conversation->current_intent['_clarifications']);
    }

    public function test_vague_initial_message_triggers_deterministic_clarification_question(): void
    {
        $service = app(ConversationService::class);

        // Hiç bilgi içermeyen mesaj + boş previous intent → LLM'e gitmeden zorla soru
        $question = $service->maybeAskClarification('tatil yapmak istiyorum', []);

        $this->assertNotNull($question);
        $this->assertStringContainsString('bütçen', mb_strtolower($question));
    }

    public function test_clarification_logic_covers_signal_combinations(): void
    {
        $service = app(ConversationService::class);

        // 0 sinyal → soru
        $this->assertNotNull($service->maybeAskClarification('tatil yapmak istiyorum', []));
        $this->assertNotNull($service->maybeAskClarification('merhaba', []));

        // 1 sinyal (sadece bütçe) → hâlâ soru
        $this->assertNotNull($service->maybeAskClarification('30K bütçem var', []));

        // 1 sinyal (sadece destinasyon) → hâlâ soru
        $this->assertNotNull($service->maybeAskClarification('Bali görmek istiyorum', []));

        // 2 sinyal (bütçe + destinasyon) → arama yapılır, soru null
        $this->assertNull($service->maybeAskClarification('30K bütçeyle Avrupa düşünüyorum', []));

        // 2 sinyal (destinasyon + zaman) → null
        $this->assertNull($service->maybeAskClarification('Eylül ayında Yunanistan', []));

        // 1 sinyal mesajda + 1 önceki niyetten (bütçe) = 2 → null
        $this->assertNull($service->maybeAskClarification('Bali görmek istiyorum', ['max_budget' => 30000]));

        // Önceki niyetten 1 sinyal + mesajda 0 sinyal = hâlâ 1, soru
        $this->assertNotNull($service->maybeAskClarification('peki', ['max_budget' => 30000]));
    }

    public function test_budget_signal_recognizes_natural_phrasings(): void
    {
        $service = app(ConversationService::class);

        // "bütçe" kelimesi + 4+ haneli sayı + zaman → arama
        $this->assertNull($service->maybeAskClarification('20000 bütçe 3 yada 4 gün lük bir tatil', []));

        // "bütçe" tek başına → sadece bütçe sinyali, zaman/destinasyon yok → soru
        $this->assertNotNull($service->maybeAskClarification('20000 bütçe', []));

        // "butce" (ASCII varyant)
        $this->assertNotNull($service->maybeAskClarification('butce 15000', []));

        // 4+ haneli çıplak sayı + zaman → arama (bütçe varsayılır)
        $this->assertNull($service->maybeAskClarification('15000 lira 5 gün', []));

        // "30 bin" gibi metinsel bütçe + destinasyon
        $this->assertNull($service->maybeAskClarification('30 bin Avrupa', []));

        // Yıl gibi görünen sayı (2025, 2026) bütçe sayılmamalı
        $this->assertNotNull($service->maybeAskClarification('2026 yılında tatil', []));
        $this->assertNotNull($service->maybeAskClarification('Eylül 2025', []));
    }

    public function test_widget_searchapi_returns_clarification_for_vague_query(): void
    {
        // Floating widget'ın çağırdığı /yapay-zeka-arama-api endpoint'i de
        // belirsiz girdilerde performAiSearch'a gitmeden soru dönmeli.
        $this->mock(AiSearchController::class, function (MockInterface $mock) {
            $mock->makePartial();
            $mock->shouldNotReceive('performAiSearch');
        });

        $r = $this->getJson(route('ai.search.api', ['q' => 'tatil yapmak istiyorum']))
            ->assertOk();

        $this->assertTrue($r->json('is_clarification'));
        $this->assertSame([], $r->json('results'));
        $this->assertNotEmpty($r->json('aiComment'));
    }

    public function test_clarification_count_is_capped_so_search_eventually_runs(): void
    {
        $user = User::factory()->create(['role' => 'visitor']);
        $this->actingAs($user);

        [$tour] = $this->seedVisibleTour();

        // Conversation'ı zaten max clarification asked durumda yarat
        $conversation = AiSearchConversation::create([
            'user_id' => $user->id,
            'session_id' => null,
            'current_intent' => ['_clarifications' => ConversationService::MAX_CLARIFICATIONS],
            'last_message_at' => now(),
        ]);

        // Cap'e ulaşıldığı için maybeAskClarification çağrılmamalı; gerçek ConversationService bu kontrolü yapar
        $turn = $this->fakeSearchResult($tour, '3. turn cevap', ['max_budget' => 5000], ['max_budget' => 5000]);
        $controller = $this->mock(AiSearchController::class, function (MockInterface $mock) use ($turn) {
            $mock->makePartial();
            $mock->shouldReceive('performAiSearch')->once()->andReturn($turn);
        });

        $svc = \Mockery::mock(ConversationService::class, [$controller])->makePartial();
        $svc->shouldNotReceive('maybeAskClarification');
        $this->app->instance(ConversationService::class, $svc);

        $r = $this->postJson(route('ai.search.message'), [
            'message' => 'son denemem',
            'conversation_uuid' => $conversation->uuid,
        ])->assertOk();

        $this->assertFalse((bool) $r->json('is_clarification'));
        $this->assertCount(1, $r->json('tours'));
    }

    private function stubConversationServiceWithoutClarification(): void
    {
        $controller = app(AiSearchController::class);
        $svc = \Mockery::mock(ConversationService::class, [$controller])->makePartial();
        $svc->shouldReceive('maybeAskClarification')->andReturn(null);
        $this->app->instance(ConversationService::class, $svc);
    }

    private function seedVisibleTour(): array
    {
        $agency = Agency::create([
            'name' => 'Test Acenta',
            'slug' => 'test-acenta',
            'email' => 'test@example.com',
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

    private function fakeSearchResult(Tour $tour, string $aiComment, array $intent, array $appliedFilters): array
    {
        $results = new Collection([
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
            ],
        ]);

        return [
            'results' => $results,
            'aiComment' => $aiComment,
            'log_id' => null,
            'intent' => $intent,
            'applied_filters' => $appliedFilters,
            'latency_ms' => 120,
        ];
    }
}
