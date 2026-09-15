<?php

namespace App\Services\Account;

use App\Models\AiSearchLog;
use App\Models\DiscoveryGuide;
use App\Models\TourView;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * D4 — Hesap silme akışı (KVKK unutulma hakkı). Üç adım:
 *  1) request(): talep zamanı yazılır, oturum kapatılır (30 gün bekleme).
 *  2) cancel(): süre içinde giriş yapan kullanıcının talebi iptal edilir.
 *  3) anonymize(): 30 gün sonra komutla — kişisel veri silinir/maskelenir, satır
 *     kalır (kupon kullanımı gibi mali izler kullanıcı id'sine bağlı kalır).
 * Yalnız müşteri (visitor) hesapları: acenta/admin hesapları bu yoldan silinmez.
 */
class AccountDeletionService
{
    public const WAITING_DAYS = 30;

    public function request(User $user): void
    {
        $user->forceFill(['deletion_requested_at' => now()])->save();
    }

    public function cancel(User $user): void
    {
        $user->forceFill(['deletion_requested_at' => null])->save();
    }

    /** Bekleme süresi dolmuş, henüz anonimleştirilmemiş kullanıcılar. */
    public function dueUsers()
    {
        return User::query()
            ->whereNotNull('deletion_requested_at')
            ->whereNull('anonymized_at')
            ->where('deletion_requested_at', '<=', now()->subDays(self::WAITING_DAYS));
    }

    public function anonymize(User $user): void
    {
        DB::transaction(function () use ($user) {
            // Kişisel içerik: yorum metni, favoriler, kayıtlı aramalar, bildirimler
            $user->reviews()->withTrashed()->forceDelete();
            $user->favoriteTours()->detach();
            $user->savedSearches()->delete();
            $user->notifications()->delete();

            // Analitik izler kalır ama kişiden koparılır
            AiSearchLog::where('user_id', $user->id)->update(['user_id' => null]);
            DiscoveryGuide::where('user_id', $user->id)->update(['user_id' => null]);
            TourView::where('user_id', $user->id)->update(['user_id' => null]);

            if ($user->avatar && ! Str::startsWith($user->avatar, ['http://', 'https://'])) {
                Storage::disk('public')->delete($user->avatar);
            }

            $user->forceFill([
                'name' => 'Silinmiş Kullanıcı',
                'email' => 'silinmis-'.$user->id.'@anonim.invalid',
                'password' => Hash::make(Str::random(40)),
                'phone' => null,
                'avatar' => null,
                'city' => null,
                'bio' => null,
                'birth_date' => null,
                'ai_preference' => null,
                'pending_email' => null,
                'pending_email_requested_at' => null,
                'remember_token' => null,
                'email_verified_at' => null,
                'anonymized_at' => now(),
            ])->save();
        });
    }
}
