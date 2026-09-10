<?php

namespace App\Support;

use App\Models\Tour;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Giriş / kayıt sonrası bağlamı koru: ziyaretçi bir turda kalbe basınca girişe
 * gidiyor, giriş yapınca ana sayfaya düşüyor ve favori eklenmiyordu. Form
 * gizli alanlarla ?next (yalnız yerel yol) ve ?favori (tur id) taşır; burada
 * doğrulanır, favori tamamlanır ve aynı sayfaya dönülür.
 *
 * Admin ve acenta yönlendirmeleri çağıranda ÖNCE gelir; bu sınıf yalnız
 * ziyaretçi dalında kullanılır.
 */
final class LoginReturn
{
    /** Yalnız yerel yol kabul edilir: mutlak adres, protokol-göreli (//) ve kontrol karakterleri reddedilir. */
    public static function safePath(?string $next): ?string
    {
        $next = trim((string) $next);

        if ($next === '' || ! str_starts_with($next, '/') || str_starts_with($next, '//')) {
            return null;
        }

        if (str_starts_with($next, '/\\') || preg_match('/[\r\n\0]/', $next)) {
            return null;
        }

        return $next;
    }

    /** Bekleyen favoriyi ekler; tur görünür değilse sessizce atlar. Eklendiyse true. */
    public static function completePendingFavorite(User $user, mixed $favoriId): bool
    {
        $id = (int) $favoriId;
        if ($id < 1) {
            return false;
        }

        $tour = Tour::find($id);
        if (! $tour || ! $tour->isPubliclyVisible() || ! $tour->agency?->is_active) {
            return false;
        }

        if ($user->favoriteTours()->where('tour_id', $tour->id)->exists()) {
            return true;
        }

        $user->favoriteTours()->attach($tour->id);

        return true;
    }

    /** Ziyaretçi dalı: favoriyi tamamla, güvenli next varsa oraya, yoksa ana sayfaya dön. */
    public static function redirectAfter(User $user, Request $request, ?string $fallbackMessage = null): RedirectResponse
    {
        $favoriEklendi = self::completePendingFavorite($user, $request->input('favori'));
        $next = self::safePath($request->input('next'));

        $response = $next ? redirect($next) : redirect()->route('home');

        if ($favoriEklendi) {
            return $response->with('success', 'Favorilere eklendi. Fiyat değişikliklerini bildirimlerinden takip edebilirsin.');
        }

        return $fallbackMessage ? $response->with('success', $fallbackMessage) : $response;
    }
}
