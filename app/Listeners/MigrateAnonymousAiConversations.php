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

        // Anonimken çözülen Tatil Karakteri Testi kullanıcı hesabına taşınır —
        // konuşma meta'sındaki profil kalıcı users.recreation_profile olur.
        // Kullanıcının zaten profili varsa (daha taze/onaylı) üzerine yazılmaz.
        $user = $event->user;
        if ($user instanceof \App\Models\User && $user->recreation_profile === null) {
            $withProfile = AiSearchConversation::where('user_id', $userId)
                ->where('session_id', $sessionId)
                ->latest('updated_at')
                ->get()
                ->first(fn ($c) => ! empty(($c->current_intent ?? [])[\App\Services\AiSearch\RecreationQuizService::META_KEY]['vector']));

            if ($withProfile) {
                $user->forceFill([
                    'recreation_profile' => $withProfile->current_intent[\App\Services\AiSearch\RecreationQuizService::META_KEY],
                ])->save();
            }
        }
    }
}
