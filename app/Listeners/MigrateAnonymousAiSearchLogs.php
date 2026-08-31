<?php

namespace App\Listeners;

use App\Models\AiSearchLog;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Str;

/**
 * Kullanıcı giriş yapınca (veya kayıt sonrası Auth::login), o oturumda anonim
 * yapılmış AI arama loglarını yeni kimliğe bağlar. Aksi halde login route'undaki
 * session regenerate ile anonim log sahipsiz kalır ve kullanıcı kendi aramasının
 * "tüm sonuçlar" sayfasına (AiSearchController::showResults sahiplik kontrolü)
 * erişemezdi.
 *
 * Login event'i Auth::attempt sırasında (session regenerate'ten ÖNCE) tetiklenir,
 * yani bu noktada oturum kimliği hâlâ logun kaydedildiği kimliktir.
 */
class MigrateAnonymousAiSearchLogs
{
    public function handle(Login $event): void
    {
        $userId = $event->user->getAuthIdentifier();
        if (! $userId || ! session()->isStarted()) {
            return;
        }

        // AiSearchLog::session_id ile aynı kısaltma (64 karakter)
        $sessionId = Str::limit(session()->getId(), 64, '');
        if ($sessionId === '') {
            return;
        }

        AiSearchLog::whereNull('user_id')
            ->where('session_id', $sessionId)
            ->update(['user_id' => $userId]);
    }
}
