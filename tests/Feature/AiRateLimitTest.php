<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AiRateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Her test temiz limit ile başlasın
        RateLimiter::clear('ai:ip:127.0.0.1');
    }

    public function test_anonymous_user_is_limited_to_10_message_requests_per_minute(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            $r = $this->postJson(route('ai.search.message'), ['message' => 'ping ' . $i]);
            $this->assertNotSame(429, $r->status(), "Request #$i should not be rate limited");
        }

        // 11. istek bloklanmalı
        $this->postJson(route('ai.search.message'), ['message' => 'ping 11'])
            ->assertStatus(429);
    }

    public function test_authenticated_user_gets_higher_limit_30_per_minute(): void
    {
        $user = User::factory()->create(['role' => 'visitor']);
        RateLimiter::clear('ai:user:' . $user->id);
        $this->actingAs($user);

        for ($i = 1; $i <= 30; $i++) {
            $r = $this->postJson(route('ai.search.message'), ['message' => 'ping ' . $i]);
            $this->assertNotSame(429, $r->status(), "Auth user request #$i should not be limited");
        }

        $this->postJson(route('ai.search.message'), ['message' => 'ping 31'])
            ->assertStatus(429);
    }

    public function test_widget_searchapi_endpoint_is_also_rate_limited(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            $this->getJson(route('ai.search.api', ['q' => 'tatil ' . $i]))
                ->assertStatus(200);
        }

        $this->getJson(route('ai.search.api', ['q' => 'tatil 11']))
            ->assertStatus(429);
    }

    public function test_chat_page_get_is_not_rate_limited(): void
    {
        // Chat sayfasının kendisi (HTML) limit'e takılmamalı — sadece mesaj/api endpoint'leri
        for ($i = 1; $i <= 20; $i++) {
            $this->get(route('ai.search'))->assertOk();
        }
    }
}
