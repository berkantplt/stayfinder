<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * ai_search limiter'ının kapsamı: anonim 10/dk, girişli 30/dk. Sohbet v1
 * kaldırıldıktan sonra limiter'ın altındaki AI ucu /yapay-zeka-arama-api.
 */
class AiRateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Her test temiz limit ile başlasın
        RateLimiter::clear('ai:ip:127.0.0.1');
    }

    /**
     * Aramanın OpenAI hattı: her istek sırayla intent(chat) + embedding +
     * yorum(chat) tüketir (RAG embedding'i aynı metni sorduğundan
     * QueryEmbeddingCache'ten gelir, API'ye gitmez).
     */
    private function fakeOpenAiForSearches(int $count): void
    {
        $responses = [];
        for ($i = 0; $i < $count; $i++) {
            $responses[] = \OpenAI\Responses\Chat\CreateResponse::fake();
            $responses[] = \OpenAI\Responses\Embeddings\CreateResponse::fake();
            $responses[] = \OpenAI\Responses\Chat\CreateResponse::fake();
        }
        \OpenAI\Laravel\Facades\OpenAI::fake($responses);
    }

    public function test_anonymous_user_is_limited_to_10_requests_per_minute(): void
    {
        $this->fakeOpenAiForSearches(10);

        for ($i = 1; $i <= 10; $i++) {
            $r = $this->getJson(route('ai.search.api', ['q' => 'tatil '.$i]));
            $this->assertNotSame(429, $r->status(), "Request #$i should not be rate limited");
        }

        // 11. istek bloklanmalı
        $this->getJson(route('ai.search.api', ['q' => 'tatil 11']))
            ->assertStatus(429);
    }

    public function test_authenticated_user_gets_higher_limit_30_per_minute(): void
    {
        $user = User::factory()->create(['role' => 'visitor']);
        RateLimiter::clear('ai:user:'.$user->id);
        $this->actingAs($user);

        $this->fakeOpenAiForSearches(30);

        for ($i = 1; $i <= 30; $i++) {
            $r = $this->getJson(route('ai.search.api', ['q' => 'tatil '.$i]));
            $this->assertNotSame(429, $r->status(), "Auth user request #$i should not be limited");
        }

        $this->getJson(route('ai.search.api', ['q' => 'tatil 31']))
            ->assertStatus(429);
    }

    public function test_public_pages_are_not_rate_limited(): void
    {
        // Limiter yalnız AI uçlarında; normal HTML sayfaları (anonim ai_search
        // sınırı olan 10'un üstünde bile) takılmamalı
        for ($i = 1; $i <= 12; $i++) {
            $this->get(route('home'))->assertOk();
        }
    }
}
