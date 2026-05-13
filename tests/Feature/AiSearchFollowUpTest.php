<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\AiSearchLog;
use App\Models\Tour;
use App\Support\CategoryLicensing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Chat\CreateResponse as ChatResponse;
use Tests\TestCase;

class AiSearchFollowUpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (!Schema::hasColumn('ai_search_logs', 'conversation_id')) {
            Schema::table('ai_search_logs', function ($t) {
                $t->string('conversation_id', 64)->nullable()->index();
                $t->unsignedBigInteger('parent_log_id')->nullable()->index();
                $t->string('turn_type', 24)->nullable();
                $t->text('ai_comment')->nullable();
            });
        }
        CategoryLicensing::resetCache();
        Cache::flush();
    }

    public function test_followup_question_answers_using_previous_results_without_new_search(): void
    {
        $agency = Agency::create([
            'name' => 'Doğu Karadeniz Acentası',
            'email' => 'kd@example.com',
            'is_active' => true,
            'approval_status' => Agency::STATUS_APPROVED,
            'legacy_category_access' => true,
        ]);

        $rizeTour = null;
        Tour::withoutEvents(function () use ($agency, &$rizeTour) {
            $rizeTour = Tour::create([
                'agency_id' => $agency->id,
                'title' => 'Rize Yayla Kaçamağı',
                'slug' => 'rize-yayla',
                'destination' => 'Rize',
                'description' => 'Ayder ve Pokut yaylalarında 4 gece sakin doğa kaçamağı.',
                'price' => 9800,
                'currency' => 'TRY',
                'duration_days' => 4,
                'departure_date' => now()->addDays(20)->toDateString(),
                'is_active' => true,
                'is_international' => false,
                'requires_visa' => false,
                'embedding' => array_fill(0, 1536, 0.5),
            ]);
        });

        // Önceki turn — kullanıcı arama yaptı, AI Rize önerdi
        $conversationId = (string) \Illuminate\Support\Str::uuid();
        AiSearchLog::create([
            'session_id' => 'sess-1',
            'raw_query' => 'kalabalıktan uzak doğa tatili',
            'normalized_query' => 'kalabaliktan uzak doga tatili',
            'intent' => ['wants_nature' => true, 'avoid_crowded_city' => true],
            'applied_filters' => [],
            'candidate_count' => 1,
            'result_tour_ids' => [$rizeTour->id],
            'result_scores' => [],
            'latency_ms' => 100,
            'conversation_id' => $conversationId,
            'turn_type' => 'search',
            'ai_comment' => 'Sakin doğa için Rize Yayla Kaçamağı çok uygun: kalabalıktan uzak, 4 gece, makul bütçeli.',
        ]);

        // Şimdi follow-up: "neden rizeyi önerdin"
        $intentArgs = json_encode([
            'is_followup' => true,
            'followup_question' => 'Neden Rize önerildi?',
            'search_query' => 'neden rizeyi önerdin',
        ]);

        OpenAI::fake([
            // extractIntent: is_followup=true dönsün
            ChatResponse::fake([
                'choices' => [[
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => null,
                        'tool_calls' => [[
                            'id' => 'call_1',
                            'type' => 'function',
                            'function' => ['name' => 'capture_travel_intent', 'arguments' => $intentArgs],
                        ]],
                    ],
                    'finish_reason' => 'tool_calls',
                ]],
            ]),
            // handleFollowUp: gerekçeli cevap
            ChatResponse::fake([
                'choices' => [[
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'Rize\'yi sakin doğa, yayla ve kalabalıktan uzak atmosferi için önerdim — 4 gecelik süre ve bütçen bu profile çok uygundu.',
                        'tool_calls' => [],
                    ],
                    'finish_reason' => 'stop',
                ]],
            ]),
        ]);

        $response = $this->getJson('/yapay-zeka-arama-api?q=' . urlencode('neden rizeyi önerdin') .
            '&conversation_id=' . urlencode($conversationId));

        $response->assertOk();
        $body = $response->json();

        $this->assertSame('followup', $body['turn_type'] ?? null);
        $this->assertSame($conversationId, $body['conversation_id']);

        // Cevap önceki Rize önerisinin gerekçesini açıklamalı (clarifying soru DEĞİL)
        $comment = (string) ($body['aiComment'] ?? '');
        $this->assertStringContainsString('Rize', $comment);
        $this->assertStringNotContainsString('Sana uygun bir tatil bulalım', $comment);
        $this->assertStringNotContainsString('Kısaca anlatır mısın', $comment);

        // Önceki tur ID'si results'ta gelmeli (yeni arama yapılmadığının kanıtı)
        $this->assertNotEmpty($body['results']);
        $this->assertSame($rizeTour->id, $body['results'][0]['id']);

        // DB: yeni log follow-up tipinde, parent_log_id eski log'a işaret ediyor
        $followupLog = AiSearchLog::where('conversation_id', $conversationId)
            ->where('turn_type', 'followup')
            ->first();
        $this->assertNotNull($followupLog);
        $this->assertNotNull($followupLog->parent_log_id);
    }

    public function test_new_search_without_conversation_creates_new_conversation_id(): void
    {
        $agency = Agency::create([
            'name' => 'A',
            'email' => 'a@example.com',
            'is_active' => true,
            'approval_status' => Agency::STATUS_APPROVED,
            'legacy_category_access' => true,
        ]);

        Tour::withoutEvents(function () use ($agency) {
            Tour::create([
                'agency_id' => $agency->id,
                'title' => 'Tur',
                'slug' => 'tur-1',
                'destination' => 'Antalya',
                'price' => 8000,
                'currency' => 'TRY',
                'duration_days' => 4,
                'departure_date' => now()->addDays(15)->toDateString(),
                'is_active' => true,
                'is_international' => false,
                'requires_visa' => false,
                'embedding' => array_fill(0, 1536, 0.5),
            ]);
        });

        OpenAI::fake([
            // intent (yeni search)
            ChatResponse::fake([
                'choices' => [[
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => null,
                        'tool_calls' => [[
                            'id' => 'c1',
                            'type' => 'function',
                            'function' => ['name' => 'capture_travel_intent', 'arguments' => json_encode([
                                'is_followup' => false,
                                'search_query' => 'antalya tatili',
                            ])],
                        ]],
                    ],
                    'finish_reason' => 'tool_calls',
                ]],
            ]),
            // embedding
            \OpenAI\Responses\Embeddings\CreateResponse::fake([
                'data' => [['object' => 'embedding', 'index' => 0, 'embedding' => array_fill(0, 1536, 0.5)]],
            ]),
            // comment (re-rank atlanır çünkü tek aday var)
            ChatResponse::fake([
                'choices' => [[
                    'index' => 0,
                    'message' => ['role' => 'assistant', 'content' => 'Antalya turu uygun.', 'tool_calls' => []],
                    'finish_reason' => 'stop',
                ]],
            ]),
        ]);

        $response = $this->getJson('/yapay-zeka-arama-api?q=' . urlencode('antalya tatili'));
        $response->assertOk();

        $body = $response->json();
        $this->assertNotEmpty($body['conversation_id']);
        $this->assertSame('search', $body['turn_type'] ?? 'search');
    }
}
