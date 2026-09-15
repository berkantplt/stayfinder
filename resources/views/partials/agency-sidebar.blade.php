<div class="panel-sidebar-module">
    <div class="p-sb-baslik">
        @if(auth()->user()->agency && auth()->user()->agency->logo)
            <div class="p-sb-logo"><img src="{{ auth()->user()->agency->logo }}" alt="{{ auth()->user()->agency->name }}"></div>
        @else
            <div class="p-sb-logo">🏢</div>
        @endif
        <div>
            <div class="p-sb-ad">
                {{ auth()->user()->agency ? auth()->user()->agency->name : 'Acenta Paneli' }}
            </div>
            <div class="p-sb-alt">Acenta Paneli</div>
        </div>
    </div>

    {{-- A3: stiller layouts/app.blade.php "Panel bileşen katmanı" bloğunda --}}

    <div class="p-sb-liste">
        <a href="{{ route('agency.dashboard') }}" class="sidebar-link {{ request()->routeIs('agency.dashboard') ? 'active' : '' }}">
            <span class="sidebar-icon">📊</span> Dashboard
        </a>
        <a href="{{ route('agency.tours.index') }}" class="sidebar-link {{ request()->routeIs('agency.tours.index', 'agency.tours.show', 'agency.tours.edit') ? 'active' : '' }}">
            <span class="sidebar-icon">📋</span> Turlarım
        </a>
        <a href="{{ route('agency.tours.create') }}" class="sidebar-link {{ request()->routeIs('agency.tours.create') ? 'active' : '' }}">
            <span class="sidebar-icon">➕</span> Tur Ekle
        </a>
        @php
            // C15: alt menü + sepet rozeti (oturumdan, DB'ye gitmez); geçiş erişimli acentada sepet yok (C17)
            $kymLegacy = (bool) auth()->user()->agency?->legacy_category_access;
            $kymCartCount = $kymLegacy ? 0 : \App\Http\Controllers\Agency\CategoryLicenseController::sessionCartItemCount();
        @endphp
        <a href="{{ route('agency.category-licenses.index') }}" class="sidebar-link {{ request()->routeIs('agency.category-licenses.index') ? 'active' : '' }}">
            <span class="sidebar-icon">🧾</span> Kategori Yetkileri
            @if($kymCartCount > 0)<span class="p-sb-rozet" title="Sepette {{ $kymCartCount }} kalem">{{ $kymCartCount }}</span>@endif
        </a>
        <a href="{{ route('agency.category-licenses.index') }}" class="sidebar-link p-sb-alt-link {{ request()->routeIs('agency.category-licenses.index') ? 'active' : '' }}">Genel Bakış</a>
        @unless($kymLegacy)
            <a href="{{ route('agency.category-licenses.cart.show') }}" class="sidebar-link p-sb-alt-link {{ request()->routeIs('agency.category-licenses.cart.show', 'agency.category-licenses.checkout-form') ? 'active' : '' }}">
                Sepet
                @if($kymCartCount > 0)<span class="p-sb-rozet">{{ $kymCartCount }}</span>@endif
            </a>
        @endunless
        <a href="{{ route('agency.category-licenses.orders') }}" class="sidebar-link p-sb-alt-link {{ request()->routeIs('agency.category-licenses.orders*') ? 'active' : '' }}">Satın Alımlar</a>
        <a href="{{ route('agency.campaigns.index') }}" class="sidebar-link {{ request()->routeIs('agency.campaigns.*') ? 'active' : '' }}">
            <span class="sidebar-icon">🏷️</span> Kampanyalar
        </a>
        <a href="{{ route('agency.coupons.index') }}" class="sidebar-link {{ request()->routeIs('agency.coupons*') ? 'active' : '' }}">
            <span class="sidebar-icon">🎟️</span> Kuponlar
        </a>
        <a href="{{ route('agency.category-requests.index') }}" class="sidebar-link {{ request()->routeIs('agency.category-requests*') ? 'active' : '' }}">
            <span class="sidebar-icon">🗳️</span> Kategori Talebi
        </a>
        <a href="{{ route('agency.stats') }}" class="sidebar-link {{ request()->routeIs('agency.stats') ? 'active' : '' }}">
            <span class="sidebar-icon">📈</span> İstatistikler
        </a>
        <a href="{{ route('agency.profile') }}" class="sidebar-link {{ request()->routeIs('agency.profile') ? 'active' : '' }}">
            <span class="sidebar-icon">⚙️</span> Acenta Profili
        </a>
        <hr class="p-sb-ayrac">
        <a href="{{ route('home') }}" class="sidebar-link">
            <span class="sidebar-icon">🌍</span> Siteye Dön
        </a>
    </div>
</div>
