<?php

namespace Tests\Feature;

use App\Models\AiSearchConversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiConversationAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_chat_page_renders_for_anonymous_user(): void
    {
        $this->get(route('ai.search'))
            ->assertOk()
            ->assertSee('AI Tatil Danışmanı', false);
    }

    public function test_user_can_open_their_own_conversation_by_uuid(): void
    {
        $user = User::factory()->create(['role' => 'visitor']);

        $conversation = AiSearchConversation::create([
            'user_id' => $user->id,
            'title' => 'Önceki konuşma',
            'last_message_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('ai.search.show', $conversation->uuid))
            ->assertOk();
    }

    public function test_user_cannot_open_another_users_conversation(): void
    {
        $owner = User::factory()->create(['role' => 'visitor']);
        $other = User::factory()->create(['role' => 'visitor']);

        $conversation = AiSearchConversation::create([
            'user_id' => $owner->id,
            'title' => 'Gizli konuşma',
            'last_message_at' => now(),
        ]);

        $this->actingAs($other)
            ->get(route('ai.search.show', $conversation->uuid))
            ->assertForbidden();
    }

    public function test_send_message_validates_payload(): void
    {
        $this->post(route('ai.search.message'), [])
            ->assertSessionHasErrors('message');
    }
}
