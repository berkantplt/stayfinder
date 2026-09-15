<div class="panel-sidebar-module">
    <div class="p-sb-baslik">
        <div class="p-sb-logo">🛡️</div>
        <div>
            <div class="p-sb-ad">Admin Paneli</div>
            <div class="p-sb-alt">Yönetim Merkezi</div>
        </div>
    </div>

    {{-- A3: stiller layouts/app.blade.php "Panel bileşen katmanı" bloğunda --}}

    {{-- A7: iki COUNT her admin sayfasında koşuyordu; 60 sn önbellek. Onay/red
         işlemleri anahtarı siler (AdminController, Admin\CategoryRequestController). --}}
    @php
        $categoryLicensingActive = request()->routeIs('admin.category-licenses*');
        $bekleyen = cache()->remember('admin:bekleyen-sayaclar', 60, function () {
            return [
                'basvuru' => \App\Models\Agency::pendingApproval()->count(),
                'talep' => \App\Models\CategoryRequest::pending()->count(),
            ];
        });
        $pendingAgencyApplicationsCount = $bekleyen['basvuru'];
        $pendingCategoryRequestsCount = $bekleyen['talep'];
    @endphp

    <div class="p-sb-liste">
        <a href="{{ route('admin.dashboard') }}" class="sidebar-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
            <span class="sidebar-icon">📊</span> Dashboard
        </a>
        <a href="{{ route('admin.agencies') }}" class="sidebar-link {{ request()->routeIs('admin.agencies*') ? 'active' : '' }}">
            <span class="sidebar-icon">🏢</span> Acentalar
        </a>
        <a href="{{ route('admin.agency-applications') }}" class="sidebar-link {{ request()->routeIs('admin.agency-applications*') ? 'active' : '' }}">
            <span class="sidebar-icon">📝</span>
            <span class="p-sb-esnek">Acenta Başvuruları</span>
            @if($pendingAgencyApplicationsCount > 0)
                <span class="p-sb-rozet">{{ $pendingAgencyApplicationsCount }}</span>
            @endif
        </a>
        <a href="{{ route('admin.tours') }}" class="sidebar-link {{ request()->routeIs('admin.tours') ? 'active' : '' }}">
            <span class="sidebar-icon">📋</span> Tüm Turlar
        </a>
        <a href="{{ route('admin.departure-cities') }}" class="sidebar-link {{ request()->routeIs('admin.departure-cities*') ? 'active' : '' }}">
            <span class="sidebar-icon">🚌</span> Kalkış Şehirleri
        </a>
        <a href="{{ route('admin.tour-visa') }}" class="sidebar-link {{ request()->routeIs('admin.tour-visa*') ? 'active' : '' }}">
            <span class="sidebar-icon">🛂</span> Vize Durumu
        </a>
        <a href="{{ route('admin.categories.parents') }}" class="sidebar-link {{ request()->routeIs('admin.categories*') ? 'active' : '' }}">
            <span class="sidebar-icon">📁</span> Kategori Yönetimi
        </a>
        <div class="sidebar-submenu">
            <a href="{{ route('admin.categories.parents') }}" class="sidebar-sublink {{ request()->routeIs('admin.categories.parents') ? 'active' : '' }}">
                <span class="sidebar-bullet"></span> Üst Kategori Yönetimi
            </a>
            <a href="{{ route('admin.categories.index') }}" class="sidebar-sublink {{ request()->routeIs('admin.categories.index') ? 'active' : '' }}">
                <span class="sidebar-bullet"></span> Alt Kategori Yönetimi
            </a>
        </div>
        <a href="{{ route('admin.category-requests.index') }}" class="sidebar-link {{ request()->routeIs('admin.category-requests*') ? 'active' : '' }}">
            <span class="sidebar-icon">🗳️</span>
            <span class="p-sb-esnek">Kategori Talepleri</span>
            @if($pendingCategoryRequestsCount > 0)
                <span class="p-sb-rozet">{{ $pendingCategoryRequestsCount }}</span>
            @endif
        </a>
        <a href="{{ route('admin.category-licenses.index') }}" class="sidebar-link {{ $categoryLicensingActive ? 'active' : '' }}">
            <span class="sidebar-icon">🧾</span> Kategori Yetkilendirme
        </a>
        <div class="sidebar-submenu">
            <a href="{{ route('admin.category-licenses.index') }}" class="sidebar-sublink {{ request()->routeIs('admin.category-licenses.index') ? 'active' : '' }}">
                <span class="sidebar-bullet"></span> Genel Bakış
            </a>
            <a href="{{ route('admin.category-licenses.pricing') }}" class="sidebar-sublink {{ request()->routeIs('admin.category-licenses.pricing') ? 'active' : '' }}">
                <span class="sidebar-bullet"></span> Kategori Tarifesi
            </a>
            <a href="{{ route('admin.category-licenses.access') }}" class="sidebar-sublink {{ request()->routeIs('admin.category-licenses.access') ? 'active' : '' }}">
                <span class="sidebar-bullet"></span> Acenta Erişimleri
            </a>
            <a href="{{ route('admin.category-licenses.orders') }}" class="sidebar-sublink {{ request()->routeIs('admin.category-licenses.orders') ? 'active' : '' }}">
                <span class="sidebar-bullet"></span> Siparişler
            </a>
        </div>
        <a href="{{ route('admin.destinations') }}" class="sidebar-link {{ request()->routeIs('admin.destinations') ? 'active' : '' }}">
            <span class="sidebar-icon">📷</span> Destinasyonlar
        </a>
        <a href="{{ route('admin.blog.index') }}" class="sidebar-link {{ request()->routeIs('admin.blog*') ? 'active' : '' }}">
            <span class="sidebar-icon">✍️</span> Blog Yönetimi
        </a>
        <a href="{{ route('admin.banners.index') }}" class="sidebar-link {{ request()->routeIs('admin.banners*') ? 'active' : '' }}">
            <span class="sidebar-icon">🖼️</span> Banner Yönetimi
        </a>
        <a href="{{ route('admin.featured_cities.index') }}" class="sidebar-link {{ request()->routeIs('admin.featured_cities*') ? 'active' : '' }}">
            <span class="sidebar-icon">🏙️</span> Öne Çıkan Şehirler
        </a>
        <a href="{{ route('admin.coupons.index') }}" class="sidebar-link {{ request()->routeIs('admin.coupons*') ? 'active' : '' }}">
            <span class="sidebar-icon">🎟️</span> Kupon Yönetimi
        </a>
        <a href="{{ route('admin.traffic') }}" class="sidebar-link {{ request()->routeIs('admin.traffic*') ? 'active' : '' }}">
            <span class="sidebar-icon">🖱️</span> Trafik
        </a>
        <a href="{{ route('admin.reports.index') }}" class="sidebar-link {{ request()->routeIs('admin.reports*') ? 'active' : '' }}">
            <span class="sidebar-icon">📈</span> Raporlar
        </a>
        <a href="{{ route('admin.destination-profiles.index') }}" class="sidebar-link {{ request()->routeIs('admin.destination-profiles*') ? 'active' : '' }}">
            <span class="sidebar-icon">🌐</span> Destinasyon Profilleri
        </a>
        <hr class="p-sb-ayrac">
        <a href="{{ route('home') }}" class="sidebar-link">
            <span class="sidebar-icon">🌍</span> Siteye Dön
        </a>
    </div>
</div>
