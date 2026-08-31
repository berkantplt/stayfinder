<?php

namespace Tests\Feature;

use App\Listeners\MigrateAnonymousAiSearchLogs;
use App\Models\AiSearchLog;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Giriş yapınca o oturumda anonim yapılmış AI arama logları kullanıcıya bağlanır
 * (login route'undaki session regenerate ile log sahipsiz kalırsa kullanıcı kendi
 * aramasının "tüm sonuçlar" sayfasına — showResults sahiplik kontrolü — erişemezdi).
 */
class MigrateAnonymousSearchLogsOnLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_listener_binds_anonymous_search_log_to_user(): void
    {
        $user = User::factory()->create(['role' => 'visitor']);

        // Oturumu başlat, kimliğini al (AiSearchLog::session_id ile aynı kısaltma)
        session()->start();
        $sessionId = Str::limit(session()->getId(), 64, '');

        $mine = $this->makeLog($sessionId);
        $other = $this->makeLog('baska-oturum');

        (new MigrateAnonymousAiSearchLogs)->handle(new Login('web', $user, false));

        $this->assertSame($user->id, $mine->fresh()->user_id, 'Kendi oturumumdaki arama logu bağlanmadı.');
        $this->assertNull($other->fresh()->user_id, 'Başka oturumun arama logu yanlışlıkla bağlandı.');
    }

    private function makeLog(string $sessionId): AiSearchLog
    {
        return AiSearchLog::create([
            'user_id' => null,
            'session_id' => $sessionId,
            'raw_query' => 'kapadokya turu',
            'normalized_query' => 'kapadokya turu',
            'intent' => [],
            'applied_filters' => [],
            'candidate_count' => 0,
            'result_tour_ids' => [],
            'result_scores' => [],
            'latency_ms' => 10,
        ]);
    }
}
