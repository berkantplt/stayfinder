<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Tour;
use App\Support\CategoryLicensing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Chat\CreateResponse as ChatResponse;
use OpenAI\Responses\Embeddings\CreateResponse as EmbeddingResponse;
use Tests\TestCase;

class AiSearchPipelineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (!\Illuminate\Support\Facades\Schema::hasColumn('ai_search_logs', 'conversation_id')) {
            \Illuminate\Support\Facades\Schema::table('ai_search_logs', function ($t) {
                $t->string('conversation_id', 64)->nullable()->index();
                $t->unsignedBigInteger('parent_log_id')->nullable()->index();
                $t->string('turn_type', 24)->nullable();
                $t->text('ai_comment')->nullable();
            });
        }
        CategoryLicensing::resetCache();
        Cache::flush();
    }

    public function test_pipeline_excludes_unapproved_agency_tours_and_past_tours_and_attaches_llm_reason(): void
    {
        $approved = Agency::create([
            'name' => 'Onaylı Acenta',
            'email' => 'a@example.com',
            'is_active' => true,
            'approval_status' => Agency::STATUS_APPROVED,
            'legacy_category_access' => true,
        ]);

        $pending = Agency::create([
            'name' => 'Onaysız Acenta',
            'email' => 'b@example.com',
            'is_active' => true,
            'approval_status' => Agency::STATUS_PENDING,
            'legacy_category_access' => true,
        ]);

        $embedding = array_fill(0, 1536, 0.5);

        $approvedTours = [];
        Tour::withoutEvents(function () use ($approved, $embedding, &$approvedTours) {
            for ($i = 1; $i <= 6; $i++) {
                $approvedTours[] = Tour::create([
                    'agency_id' => $approved->id,
                    'title' => "Onaylı Tur {$i}",
                    'slug' => "onayli-tur-{$i}",
                    'destination' => 'Kapadokya',
                    'description' => 'Doğa ağırlıklı huzurlu bir kaçamak.',
                    'included' => 'Konaklama, kahvaltı',
                    'price' => 10000 + ($i * 500),
                    'currency' => 'TRY',
                    'duration_days' => 4,
                    'departure_date' => now()->addDays(30 + $i)->toDateString(),
                    'is_active' => true,
                    'is_international' => false,
                    'requires_visa' => false,
                    'embedding' => $embedding,
                ]);
            }

            Tour::create([
                'agency_id' => $approved->id,
                'title' => 'Geçmiş Tur',
                'slug' => 'gecmis-tur',
                'destination' => 'Kapadokya',
                'description' => 'Tarihi geçmiş tur.',
                'price' => 9000,
                'currency' => 'TRY',
                'duration_days' => 3,
                'departure_date' => now()->subDays(10)->toDateString(),
                'is_active' => true,
                'is_international' => false,
                'requires_visa' => false,
                'embedding' => $embedding,
            ]);
        });

        Tour::withoutEvents(function () use ($pending, $embedding) {
            Tour::create([
                'agency_id' => $pending->id,
                'title' => 'Onaysız Acenta Turu',
                'slug' => 'onaysiz-acenta-turu',
                'destination' => 'Kapadokya',
                'description' => 'Onaysız acentadan tur — görünmemeli.',
                'price' => 12000,
                'currency' => 'TRY',
                'duration_days' => 4,
                'departure_date' => now()->addDays(40)->toDateString(),
                'is_active' => true,
                'is_international' => false,
                'requires_visa' => false,
                'embedding' => $embedding,
            ]);
        });

        $intentArgs = json_encode([
            'max_budget' => 15000,
            'preferred_destination' => 'Kapadokya',
            'wants_nature' => true,
            'interests' => ['doğa', 'huzur'],
            'traveler_type' => 'couple',
            'pace' => 'relaxed',
            'search_query' => 'kapadokya doğa kaçamağı',
        ]);

        $rerankArgs = json_encode([
            'rankings' => [
                ['index' => 0, 'fit_score' => 0.95, 'reason' => 'Kapadokya doğa kaçamağı için ideal süre ve fiyat'],
                ['index' => 2, 'fit_score' => 0.88, 'reason' => 'Huzurlu konaklama, çiftler için uygun'],
                ['index' => 4, 'fit_score' => 0.81, 'reason' => 'Yüksek puanlı doğa rotası'],
            ],
        ]);

        OpenAI::fake([
            // 1) Intent extraction (tool call)
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
            // 2) Query embedding
            EmbeddingResponse::fake([
                'data' => [['object' => 'embedding', 'index' => 0, 'embedding' => $embedding]],
            ]),
            // 3) Re-rank (tool call)
            ChatResponse::fake([
                'choices' => [[
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => null,
                        'tool_calls' => [[
                            'id' => 'call_2',
                            'type' => 'function',
                            'function' => ['name' => 'rank_best_tours', 'arguments' => $rerankArgs],
                        ]],
                    ],
                    'finish_reason' => 'tool_calls',
                ]],
            ]),
            // 4) AI comment (plain content)
            ChatResponse::fake([
                'choices' => [[
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'Senin için Kapadokya kaçamakları en uygun seçenekler.',
                        'tool_calls' => [],
                    ],
                    'finish_reason' => 'stop',
                ]],
            ]),
        ]);

        $response = $this->getJson('/yapay-zeka-arama-api?q=' . urlencode('Kapadokya doğa ağırlıklı huzurlu çift tatili'));

        $response->assertOk();
        $body = $response->json();

        $this->assertArrayHasKey('results', $body);
        $this->assertArrayHasKey('aiComment', $body);
        $this->assertNotEmpty($body['results'], 'Aday sonuç havuzu boş olmamalı');

        $titles = collect($body['results'])->pluck('title')->all();

        // Onaysız acenta turu sonuçlarda olmamalı
        $this->assertNotContains('Onaysız Acenta Turu', $titles, 'Onaysız acenta turu görünmemeli');
        // Geçmiş tur sonuçlarda olmamalı
        $this->assertNotContains('Geçmiş Tur', $titles, 'Tarihi geçmiş tur görünmemeli');

        // En azından ilk sonuç LLM gerekçesine sahip olmalı (re-rank pipeline çalıştı)
        $first = $body['results'][0];
        $this->assertArrayHasKey('llm_reason', $first);
        $this->assertNotEmpty($first['llm_reason'], 'LLM re-rank gerekçesi sonuçta görünmeli');
        $this->assertArrayHasKey('llm_fit_score', $first);

        // İlk sonuç LLM'in seçtiği index 0'a karşılık gelmeli
        $this->assertSame($approvedTours[0]->id, $first['id'], 'Re-rank LLM\'in 1. sırayı uyguladığı id olmalı');

        // AI yorumu da gelmeli
        $this->assertNotEmpty($body['aiComment']);
    }
}
