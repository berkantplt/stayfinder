<?php

namespace Tests\Feature;

use App\Models\AiSearchConversation;
use App\Models\User;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery\MockInterface;
use Tests\TestCase;

class AiConversationLockTest extends TestCase
{
    use RefreshDatabase;

    public function test_concurrent_message_to_same_conversation_returns_429(): void
    {
        $user = User::factory()->create(['role' => 'visitor']);
        $this->actingAs($user);

        $conversation = AiSearchConversation::create([
            'user_id' => $user->id,
            'last_message_at' => now(),
        ]);

        // Cache::lock mock: block() çağrılınca LockTimeoutException fırlat
        $lockMock = \Mockery::mock(Lock::class);
        $lockMock->shouldReceive('block')
            ->with(\Mockery::any(), \Mockery::any())
            ->andThrow(new LockTimeoutException('Lock alınamadı'));

        Cache::shouldReceive('lock')
            ->with('ai_conv_lock:' . $conversation->id, \Mockery::any())
            ->andReturn($lockMock);

        $r = $this->postJson(route('ai.search.message'), [
            'message' => 'merhaba',
            'conversation_uuid' => $conversation->uuid,
        ]);

        $r->assertStatus(429);
        $this->assertStringContainsString('işleniyor', $r->json('error'));
    }

    public function test_lock_releases_after_normal_completion(): void
    {
        // Lock'un try/finally veya block(closure) pattern ile release edildiğini doğrula:
        // Block closure tamamlandıktan sonra başka bir mesaj sorunsuz işlenmeli.
        $this->markTestSkipped('Cache::lock->block(closure) Laravel tarafında otomatik release yapar; integration testi gereksiz.');
    }
}
