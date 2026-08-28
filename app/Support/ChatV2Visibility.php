<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Tur Danışmanı (chatbot v2) balonunun görünürlük kuralı — TEK yer.
 *
 * layouts/app.blade.php'deki yüzen balon ve home.blade.php'deki "Tur
 * Danışmanı AI" kartı aynı fonksiyonu çağırır. Kural iki dosyada elle kopya
 * dururken kayma riski vardı: listelerden biri güncellenince kart görünür
 * ama balon render edilmez olur, "Sohbete Başla" sessiz ölü butona dönerdi.
 */
final class ChatV2Visibility
{
    public static function visible(Request $request): bool
    {
        $user = $request->user();

        return config('ai.chat_v2_enabled')
            && ! $request->is('admin*')
            && ! $request->is('super-admin*')
            && ! $request->is('superadmin*')
            && ! $request->is('acenta', 'acenta/*')
            && ! $request->routeIs('agency.*')
            && ! ($user && in_array($user->role, ['admin', 'super_admin', 'superadmin'], true));
    }
}
