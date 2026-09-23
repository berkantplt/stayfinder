<?php

namespace App\Support;

use App\Models\User;
use App\Services\Account\AccountDeletionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Giriş başarılı olduktan SONRAKİ yönlendirme — tek yerden.
 *
 * Bu mantık (admin/acenta panel ayrımı, onay bekleyen acenta, D4 silme talebinin
 * girişle iptali, ziyaretçinin geldiği tura dönmesi) eskiden yalnız /giris
 * closure'ının içindeydi. Sosyal giriş ikinci bir kapı açıyor; kopyalansaydı iki
 * akış zamanla ayrışır, ör. silme talebi Google ile girende iptal olmazdı.
 *
 * Çağıranın tek sorumluluğu Auth::login() ve session regenerate.
 */
final class LoginFlow
{
    public static function redirectAfterLogin(User $user, Request $request, ?string $fallbackMessage = null): RedirectResponse
    {
        if ($user->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        if ($user->isAgency()) {
            if (! $user->agencyApproved()) {
                return redirect()->route('agency.application.status');
            }

            return redirect()->route('agency.dashboard');
        }

        // D4: 30 günlük bekleme içinde giriş, silme talebini iptal eder
        if ($user->deletion_requested_at !== null && $user->anonymized_at === null) {
            app(AccountDeletionService::class)->cancel($user);

            return LoginReturn::redirectAfter($user, $request)
                ->with('success', 'Hoş geldiniz — hesap silme talebiniz iptal edildi, hesabınız açık kalıyor.');
        }

        // Ziyaretçi: bekleyen favori tamamlanır, geldiği tura dönülür (bkz. LoginReturn)
        return LoginReturn::redirectAfter($user, $request, $fallbackMessage);
    }
}
