{{--
    D1 — Müşteri hesabı ortak gezinmesi. /profilim, /favorilerim, /kuponlarim,
    /bildirimler, /kayitli-aramalarim ve Güvenlik sayfaları bu sekmeleri
    paylaşır; yalnız müşteri (visitor) rolünde basılır — acenta/admin kendi
    kenar çubuğunu kullanır. Kullanım: @include('partials.account-nav')
--}}
@if(auth()->check() && auth()->user()->isCustomer())
@once
<style>
    .hesap-nav { display:flex; gap:6px; overflow-x:auto; scrollbar-width:none; padding:4px 0 12px; margin-bottom:20px; border-bottom:1px solid var(--border, #e2e8f0); -webkit-overflow-scrolling:touch; }
    .hesap-nav::-webkit-scrollbar { display:none; }
    .hesap-nav a { flex:0 0 auto; display:inline-flex; align-items:center; gap:6px; padding:8px 14px; border-radius:999px; font-size:13.5px; font-weight:600; color:var(--text-sec, #475569); text-decoration:none; background:var(--bg, #f8fafc); border:1px solid transparent; white-space:nowrap; }
    .hesap-nav a:hover { color:var(--text, #0f172a); border-color:var(--border, #e2e8f0); }
    .hesap-nav a.active { background:var(--accent, #0d9488); color:#fff; }
    .hesap-nav .hesap-rozet { min-width:18px; height:18px; padding:0 5px; border-radius:999px; background:#ef4444; color:#fff; font-size:11px; font-weight:700; display:inline-flex; align-items:center; justify-content:center; }
    .hesap-nav a.active .hesap-rozet { background:#fff; color:var(--accent, #0d9488); }
</style>
@endonce
@php
    $hesapUnread = isset($unreadCount) ? (int) $unreadCount : (int) cache()->remember(auth()->user()->badgeCacheKey(), 60, fn () => auth()->user()->unreadNotifications()->count()
        + \App\Models\Announcement::unseenBy(auth()->user())->count());
    $hesapSekmeler = [
        ['route' => 'profile.show', 'is' => ['profilim'], 'ikon' => '👤', 'ad' => 'Profil'],
        ['route' => 'favorites.index', 'is' => ['favorilerim*'], 'ikon' => '❤️', 'ad' => 'Favoriler'],
        ['route' => 'customer.coupons.index', 'is' => ['kuponlarim*'], 'ikon' => '🎟️', 'ad' => 'Kuponlarım'],
        ['route' => 'notifications.index', 'is' => ['bildirimler*'], 'ikon' => '🔔', 'ad' => 'Bildirimler', 'rozet' => $hesapUnread],
        ['route' => 'customer.saved-searches.index', 'is' => ['kayitli-aramalarim*'], 'ikon' => '🔎', 'ad' => 'Kayıtlı Aramalar'],
        ['route' => 'account.activity', 'is' => ['hesabim/aramalarim*'], 'ikon' => '🤖', 'ad' => 'Aramalarım'],
        ['route' => 'profile.security', 'is' => ['profilim/guvenlik*', 'profilim/duzenle*'], 'ikon' => '🔒', 'ad' => 'Güvenlik'],
    ];
@endphp
<nav class="hesap-nav" aria-label="Hesap menüsü">
    @foreach($hesapSekmeler as $sekme)
        @if(\Illuminate\Support\Facades\Route::has($sekme['route']))
            <a href="{{ route($sekme['route']) }}" class="{{ request()->is(...$sekme['is']) ? 'active' : '' }}">
                <span aria-hidden="true">{{ $sekme['ikon'] }}</span> {{ $sekme['ad'] }}
                @if(($sekme['rozet'] ?? 0) > 0)<span class="hesap-rozet">{{ $sekme['rozet'] }}</span>@endif
            </a>
        @endif
    @endforeach
</nav>
@endif
