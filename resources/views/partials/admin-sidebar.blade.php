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
    </style>

    <div style="display:flex;flex-direction:column;">
        <a href="{{ route('admin.dashboard') }}" class="sidebar-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
            <span class="sidebar-icon">📊</span> Dashboard
        </a>
        <a href="{{ route('admin.agencies') }}" class="sidebar-link {{ request()->routeIs('admin.agencies*') ? 'active' : '' }}">
            <span class="sidebar-icon">🏢</span> Acentalar
        </a>
        <a href="{{ route('admin.tours') }}" class="sidebar-link {{ request()->routeIs('admin.tours') ? 'active' : '' }}">
            <span class="sidebar-icon">📋</span> Tüm Turlar
        </a>
        <a href="{{ route('admin.categories.index') }}" class="sidebar-link {{ request()->routeIs('admin.categories*') ? 'active' : '' }}">
            <span class="sidebar-icon">📁</span> Kategori Yönetimi
        </a>
        <a href="{{ route('admin.destinations') }}" class="sidebar-link {{ request()->routeIs('admin.destinations') ? 'active' : '' }}">
            <span class="sidebar-icon">📷</span> Destinasyonlar
        </a>
        <a href="{{ route('admin.blog.index') }}" class="sidebar-link {{ request()->routeIs('admin.blog*') ? 'active' : '' }}">
            <span class="sidebar-icon">✍️</span> Blog Yönetimi
        </a>
        <a href="{{ route('admin.banners.index') }}" class="sidebar-link {{ request()->routeIs('admin.banners*') ? 'active' : '' }}">
            <span class="sidebar-icon">🖼️</span> Banner Yönetimi
        </a>
        <hr style="border:none;border-top:1px solid rgba(255,255,255,0.08);margin:16px 0;">
        <a href="{{ route('home') }}" class="sidebar-link">
            <span class="sidebar-icon">🌍</span> Siteye Dön
        </a>
    </div>
</div>
