<?php

namespace App\Listeners;

use App\Models\AiSearchConversation;
use App\Models\AiSearchLog;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Str;

/**
 * Kullanıcı giriş yapınca (veya kayıt sonrası Auth::login), o oturumda anonim
 * yapılmış AI konuşmalarını ve arama loglarını yeni kimliğe bağlar. Aksi halde
 * login route'undaki session regenerate ile anonim konuşma sahipsiz kalır ve
 * startOrLoad erişemeyip sessizce yeni (bağlamsız) konuşma açardı.
 *
 * Login event'i Auth::attempt sırasında (session regenerate'ten ÖNCE) tetiklenir,
 * yani bu noktada oturum kimliği hâlâ anonim konuşmanın kaydedildiği kimliktir.
 */
class MigrateAnonymousAiConversations
{
    public function handle(Login $event): void
    {
        $userId = $event->user->getAuthIdentifier();
        if (! $userId || ! session()->isStarted()) {
            return;
        }

        // ConversationService::startOrLoad ile aynı kısaltma (64 karakter)
        $sessionId = Str::limit(session()->getId(), 64, '');
        if ($sessionId === '') {
            return;
        }

        AiSearchConversation::whereNull('user_id')
            ->where('session_id', $sessionId)
            ->update(['user_id' => $userId]);

        AiSearchLog::whereNull('user_id')
            ->where('session_id', $sessionId)
            ->update(['user_id' => $userId]);
    }
}
