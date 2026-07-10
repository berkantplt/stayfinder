<?php

namespace Tests\Feature;

use App\Listeners\MigrateAnonymousAiConversations;
use App\Models\AiSearchConversation;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Giriş yapınca o oturumda anonim başlatılmış AI konuşmaları kullanıcıya bağlanır
 * (sahipsiz kalıp startOrLoad'ın erişemeyip yeni bağlamsız konuşma açmasını önler).
 */
class AiConversationMigrateOnLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_listener_binds_anonymous_conversation_to_user(): void
    {
        $user = User::factory()->create(['role' => 'visitor']);

        // Oturumu başlat, kimliğini al (ConversationService ile aynı kısaltma)
        session()->start();
        $sessionId = Str::limit(session()->getId(), 64, '');

        $mine = AiSearchConversation::create([
            'user_id' => null,
            'session_id' => $sessionId,
            'last_message_at' => now(),
        ]);
        $other = AiSearchConversation::create([
            'user_id' => null,
            'session_id' => 'baska-oturum',
            'last_message_at' => now(),
        ]);

        (new MigrateAnonymousAiConversations)->handle(new Login('web', $user, false));

        $this->assertSame($user->id, $mine->fresh()->user_id, 'Kendi oturumumdaki konuşma bağlanmadı.');
        $this->assertNull($other->fresh()->user_id, 'Başka oturumun konuşması yanlışlıkla bağlandı.');
    }
}
