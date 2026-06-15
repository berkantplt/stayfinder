<div class="panel-sidebar-module">
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:32px;padding:0 8px;">
        <div style="width:40px;height:40px;background:linear-gradient(135deg, #1e293b, #0f172a);border:1px solid rgba(255,255,255,0.1);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:20px;box-shadow:inset 0 1px 1px rgba(255,255,255,0.1);">🛡️</div>
        <div>
            <div style="font-weight:700;font-size:16px;line-height:1.2;color:#f8fafc;letter-spacing:-0.3px;">Admin Paneli</div>
            <div style="font-size:12px;color:#94a3b8;margin-top:2px;">Yönetim Merkezi</div>
        </div>
    </div>

    <style>
        .sidebar-link { display:flex; align-items:center; gap:12px; padding:12px 16px; border-radius:12px; font-size:14px; font-weight:600; color:#94a3b8; transition:all .2s; margin-bottom:6px; letter-spacing:0.2px; }
        .sidebar-link:hover { background:rgba(255,255,255,0.05); color:#f8fafc; }
        .sidebar-link.active { background:rgba(255,255,255,0.1); color:#ffffff; box-shadow:inset 0 1px 1px rgba(255,255,255,0.1); }
        .sidebar-icon { font-size:18px; opacity:0.9; }
        .sidebar-submenu { margin:-2px 0 12px 22px; padding-left:16px; border-left:1px solid rgba(148,163,184,0.18); display:flex; flex-direction:column; gap:4px; }
        .sidebar-sublink { display:flex; align-items:center; gap:8px; padding:8px 12px; border-radius:10px; font-size:13px; font-weight:600; color:#94a3b8; transition:all .2s; }
        .sidebar-sublink:hover { background:rgba(255,255,255,0.05); color:#f8fafc; }
        .sidebar-sublink.active { background:rgba(255,255,255,0.08); color:#ffffff; }
        .sidebar-bullet { width:6px; height:6px; border-radius:999px; background:currentColor; opacity:0.75; }
    </style>

    @php($categoryLicensingActive = request()->routeIs('admin.category-licenses*'))
    @php($pendingAgencyApplicationsCount = \App\Models\Agency::pendingApproval()->count())

    <div style="display:flex;flex-direction:column;">
        <a href="{{ route('admin.dashboard') }}" class="sidebar-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
            <span class="sidebar-icon">📊</span> Dashboard
        </a>
        <a href="{{ route('admin.agencies') }}" class="sidebar-link {{ request()->routeIs('admin.agencies*') ? 'active' : '' }}">
            <span class="sidebar-icon">🏢</span> Acentalar
        </a>
        <a href="{{ route('admin.agency-applications') }}" class="sidebar-link {{ request()->routeIs('admin.agency-applications*') ? 'active' : '' }}">
            <span class="sidebar-icon">📝</span>
            <span style="flex:1;">Acenta Başvuruları</span>
            @if($pendingAgencyApplicationsCount > 0)
                <span style="min-width:22px;height:22px;border-radius:999px;background:#f97316;color:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:12px;font-weight:800;padding:0 7px;">{{ $pendingAgencyApplicationsCount }}</span>
            @endif
        </a>
        <a href="{{ route('admin.tours') }}" class="sidebar-link {{ request()->routeIs('admin.tours') ? 'active' : '' }}">
            <span class="sidebar-icon">📋</span> Tüm Turlar
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
        <a href="{{ route('admin.reports.index') }}" class="sidebar-link {{ request()->routeIs('admin.reports*') ? 'active' : '' }}">
            <span class="sidebar-icon">📈</span> Raporlar
        </a>
        <a href="{{ route('admin.destination-profiles.index') }}" class="sidebar-link {{ request()->routeIs('admin.destination-profiles*') ? 'active' : '' }}">
            <span class="sidebar-icon">🌐</span> Destinasyon Profilleri
        </a>
        <hr style="border:none;border-top:1px solid rgba(255,255,255,0.08);margin:16px 0;">
        <a href="{{ route('home') }}" class="sidebar-link">
            <span class="sidebar-icon">🌍</span> Siteye Dön
        </a>
    </div>
</div>
