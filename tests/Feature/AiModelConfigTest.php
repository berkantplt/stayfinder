<?php

namespace Tests\Feature;

use App\Jobs\GenerateDestinationProfileJob;
use App\Jobs\GenerateKnowledgeEmbeddingJob;
use App\Jobs\GenerateTourEmbeddingJob;
use App\Models\KnowledgeChunk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Chat\CreateResponse;
use OpenAI\Responses\Embeddings\CreateResponse as EmbeddingResponse;
use Tests\TestCase;

class AiModelConfigTest extends TestCase
{
    use RefreshDatabase;

    public function test_config_defaults_match_spec(): void
    {
        // 2026-07 geçişi: doğruluk-kritik/tek-seferlik görevler gpt-5.4-mini,
        // hacimli canlı görevler (yorum/router) gpt-4o-mini'de kalır.
        $this->assertSame('gpt-5.4-mini', config('ai.intent_model'));
        $this->assertSame('gpt-4o-mini', config('ai.comment_model'));
        $this->assertSame('gpt-4o-mini', config('ai.router_model'));
        $this->assertSame('text-embedding-3-small', config('ai.embedding_model'));
        $this->assertSame('gpt-5.4-mini', config('ai.destination_enrichment_model'));
        $this->assertSame('gpt-5.4-mini', config('ai.tour_character_model'));
    }

    public function test_destination_enrichment_job_uses_configured_model(): void
    {
        config(['ai.destination_enrichment_model' => 'gpt-test-dest']);

        OpenAI::fake([
            CreateResponse::fake([
                'choices' => [[
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => json_encode([
                            'crowd' => 0.5, 'lively' => 0.5,
                        ]),
                    ],
                ]],
            ]),
        ]);

        (new GenerateDestinationProfileJob('TestCity'))->handle();

        OpenAI::assertSent(\OpenAI\Resources\Chat::class, function (string $method, array $params) {
            return $method === 'create' && ($params['model'] ?? null) === 'gpt-test-dest';
        });
    }

    public function test_tour_embedding_job_uses_configured_embedding_model(): void
    {
        config(['ai.embedding_model' => 'embed-test-v1']);

        // Mock tour
        $agency = \App\Models\Agency::create([
            'name' => 'E',
            'slug' => 'e-' . uniqid(),
            'email' => uniqid() . '@x.com',
            'is_active' => true,
            'legacy_category_access' => true,
        ]);

        \Illuminate\Support\Facades\Queue::fake();
        \Illuminate\Support\Facades\Notification::fake();

        $tour = \App\Models\Tour::create([
            'agency_id' => $agency->id,
            'title' => 'Embedding Test',
            'destination' => 'X',
            'description' => 'd',
            'price' => 1000, 'currency' => 'TRY', 'duration_days' => 1,
            'departure_date' => today()->addDay(),
            'return_date' => today()->addDays(2),
            'is_active' => true,
        ]);

        OpenAI::fake([
            EmbeddingResponse::fake([
                'data' => [
                    ['embedding' => array_fill(0, 1536, 0.0), 'index' => 0, 'object' => 'embedding'],
                ],
            ]),
        ]);

        (new GenerateTourEmbeddingJob($tour->id))->handle();

        OpenAI::assertSent(\OpenAI\Resources\Embeddings::class, function (string $method, array $params) {
            return $method === 'create' && ($params['model'] ?? null) === 'embed-test-v1';
        });
    }

    public function test_knowledge_embedding_job_uses_configured_embedding_model(): void
    {
        config(['ai.embedding_model' => 'embed-knowledge-v2']);

        $chunk = KnowledgeChunk::create([
            'source_type' => 'test',
            'source_id' => 1,
            'title' => 'T',
            'content' => 'içerik',
            'content_hash' => md5('içerik'),
        ]);

        OpenAI::fake([
            EmbeddingResponse::fake([
                'data' => [
                    ['embedding' => array_fill(0, 1536, 0.0), 'index' => 0, 'object' => 'embedding'],
                ],
            ]),
        ]);

        (new GenerateKnowledgeEmbeddingJob($chunk->id))->handle();

        OpenAI::assertSent(\OpenAI\Resources\Embeddings::class, function (string $method, array $params) {
            return $method === 'create' && ($params['model'] ?? null) === 'embed-knowledge-v2';
        });
    }
}
