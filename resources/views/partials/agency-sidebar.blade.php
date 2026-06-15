<div class="panel-sidebar-module">
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:32px;padding:0 8px;">
        @if(auth()->user()->agency && auth()->user()->agency->logo)
            <img src="{{ auth()->user()->agency->logo }}" alt="{{ auth()->user()->agency->name }}" style="width:40px;height:40px;border-radius:10px;object-fit:cover;border:1px solid rgba(255,255,255,0.1);">
        @else
            <div style="width:40px;height:40px;background:linear-gradient(135deg, #1e293b, #0f172a);border:1px solid rgba(255,255,255,0.1);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:20px;box-shadow:inset 0 1px 1px rgba(255,255,255,0.1);">🏢</div>
        @endif
        <div>
            <div style="font-weight:700;font-size:16px;line-height:1.2;color:#f8fafc;letter-spacing:-0.3px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:160px;">
                {{ auth()->user()->agency ? auth()->user()->agency->name : 'Acenta Paneli' }}
            </div>
            <div style="font-size:12px;color:#94a3b8;margin-top:2px;">Acenta Paneli</div>
        </div>
    </div>

    <style>
        .sidebar-link { display:flex; align-items:center; gap:12px; padding:12px 16px; border-radius:12px; font-size:14px; font-weight:600; color:#94a3b8; transition:all .2s; margin-bottom:6px; letter-spacing:0.2px; }
        .sidebar-link:hover { background:rgba(255,255,255,0.05); color:#f8fafc; }
        .sidebar-link.active { background:rgba(255,255,255,0.1); color:#ffffff; box-shadow:inset 0 1px 1px rgba(255,255,255,0.1); }
        .sidebar-icon { font-size:18px; opacity:0.9; }
    </style>

    <div style="display:flex;flex-direction:column;">
        <a href="{{ route('agency.dashboard') }}" class="sidebar-link {{ request()->routeIs('agency.dashboard') ? 'active' : '' }}">
            <span class="sidebar-icon">📊</span> Dashboard
        </a>
        <a href="{{ route('agency.tours.index') }}" class="sidebar-link {{ request()->routeIs('agency.tours.index') ? 'active' : '' }}">
            <span class="sidebar-icon">📋</span> Turlarım
        </a>
        <a href="{{ route('agency.tours.create') }}" class="sidebar-link {{ request()->routeIs('agency.tours.create') ? 'active' : '' }}">
            <span class="sidebar-icon">➕</span> Tur Ekle
        </a>
        <a href="{{ route('agency.category-licenses.index') }}" class="sidebar-link {{ request()->routeIs('agency.category-licenses*') ? 'active' : '' }}">
            <span class="sidebar-icon">🧾</span> Kategori Yetkileri
        </a>
        <a href="{{ route('agency.campaigns.index') }}" class="sidebar-link {{ request()->routeIs('agency.campaigns.index') ? 'active' : '' }}">
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
        <hr style="border:none;border-top:1px solid rgba(255,255,255,0.08);margin:16px 0;">
        <a href="{{ route('home') }}" class="sidebar-link">
            <span class="sidebar-icon">🌍</span> Siteye Dön
        </a>
    </div>
</div>
