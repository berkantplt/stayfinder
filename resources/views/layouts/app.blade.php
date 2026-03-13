<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'StayFinder — Tur Karşılaştırma')</title>
    <meta name="description" content="@yield('description', 'Türkiye\'nin en iyi tur acentalarından fiyatları karşılaştırın.')">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
        :root {
            --white:#fff; --bg:#f8fafc; --text:#0f172a; --text-sec:#475569; --text-muted:#94a3b8;
            --accent:#0d9488; --accent-dark:#0f766e; --accent-light:#ccfbf1; --accent-bg:rgba(13,148,136,0.06);
            --green:#059669; --green-bg:#d1fae5; --green-text:#065f46;
            --border:#e2e8f0; --border-light:#f1f5f9;
            --shadow:0 1px 3px rgba(0,0,0,0.08), 0 1px 2px rgba(0,0,0,0.04);
            --shadow-md:0 4px 6px -1px rgba(0,0,0,0.08), 0 2px 4px -2px rgba(0,0,0,0.05);
            --shadow-lg:0 10px 25px -3px rgba(0,0,0,0.08), 0 4px 6px -4px rgba(0,0,0,0.04);
            --radius:12px; --radius-lg:16px;
            --font:'Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;
        }
        body { font-family:var(--font); background:var(--bg); color:var(--text); line-height:1.6; -webkit-font-smoothing:antialiased; }
        a { color:inherit; text-decoration:none; }
        img { max-width:100%; }
        .container { max-width:1120px; margin:0 auto; padding:0 20px; }

        /* ── Navbar ── */
        .nav { background:var(--white); border-bottom:1px solid var(--border-light); padding:0 32px; position:sticky; top:0; z-index:100; height:70px; display:flex; align-items:center; }
        .nav-inner { display:flex; align-items:center; width:100%; max-width:none !important; margin:0; }
        .nav-logo { font-size:20px; font-weight:800; color:#0f172a; display:flex; align-items:center; gap:8px; letter-spacing:-0.5px; flex-shrink:0; }
        .nav-logo span { color:var(--accent); }
        .nav-links { display:flex; align-items:center; gap:20px; margin-left:auto; } 
        .nav-links a { color:#475569; font-weight:600; font-size:14.5px; transition:all 0.2s; display:flex; align-items:center; gap:6px; letter-spacing:0.1px; }
        .nav-links a:hover { color:var(--accent); }
        .nav-profile { display:flex; align-items:center; gap:8px; color:#64748b; font-size:14.5px; font-weight:500; padding:0 16px; border-left:1px solid #e2e8f0; height:24px; margin-left:4px; }
        .nav-btn { padding:8px 16px; border-radius:8px; font-size:14px; font-weight:600; border:1px solid #e2e8f0; background:var(--white); color:#0f172a; cursor:pointer; transition:all 0.2s; }
        .nav-btn:hover { background:#f8fafc; border-color:#cbd5e1; }
        .nav-btn-primary { background:var(--accent); color:var(--white); border:none; }
        .nav-btn-primary:hover { background:var(--accent-hover); color:var(--white); transform:translateY(-1px); }
        .nav-logout-btn { padding:6px 16px; border-radius:8px; font-size:14px; font-weight:600; border:1px solid #e2e8f0; background:var(--white); color:#0f172a; cursor:pointer; transition:all 0.2s; margin-left:8px; }
        .nav-logout-btn:hover { background:#f8fafc; border-color:#cbd5e1; }
        /* Mobile menu */
        .mobile-menu-btn {
            display:none; width:40px; height:40px; align-items:center; justify-content:center;
            border:1.5px solid var(--border); border-radius:10px; background:var(--white); cursor:pointer; font-size:18px;
        }
        .mobile-nav {
            display:none; flex-direction:column; gap:4px; padding:12px 20px 16px;
            border-bottom:1px solid var(--border); background:var(--white);
        }
        .mobile-nav a, .mobile-nav button {
            display:block; padding:12px 0; font-size:15px; font-weight:500; color:var(--text-sec);
            border:none; background:none; font-family:var(--font); cursor:pointer; text-align:left;
            border-bottom:1px solid var(--border-light);
        }
        .mobile-nav a:last-child { border-bottom:none; }
        .mobile-nav a:hover { color:var(--accent); }
        .mobile-nav .mobile-auth { display:flex; gap:10px; margin-top:8px; padding-top:8px; }

        /* ── Grid ── */
        .grid-2 { display:grid; grid-template-columns:repeat(2,1fr); gap:16px; }
        .grid-3 { display:grid; grid-template-columns:repeat(3,1fr); gap:16px; }
        .grid-4 { display:grid; grid-template-columns:repeat(4,1fr); gap:16px; }

        /* ── Cards ── */
        .card { background:var(--white); border:1px solid var(--border); border-radius:var(--radius); overflow:hidden; transition:all .25s ease; }
        .card:hover { box-shadow:var(--shadow-lg); transform:translateY(-3px); }
        .card-img { width:100%; height:180px; object-fit:cover; display:block; }
        .card-body { padding:16px; }
        .card-title { font-size:15px; font-weight:700; margin-bottom:4px; line-height:1.3; }
        .card-meta { font-size:13px; color:var(--text-muted); }

        /* ── Badges ── */
        .badge { display:inline-flex; align-items:center; padding:4px 10px; border-radius:20px; font-size:12px; font-weight:600; }
        .badge-green { background:var(--green-bg); color:var(--green-text); }
        .badge-accent { background:var(--accent-bg); color:var(--accent-dark); }

        /* ── Buttons ── */
        .btn { display:inline-flex; align-items:center; justify-content:center; gap:8px; padding:12px 24px; border-radius:12px; font-family:var(--font); font-size:14px; font-weight:600; cursor:pointer; border:none; transition:all .25s cubic-bezier(0.4, 0, 0.2, 1); text-align:center; white-space:nowrap; }
        .btn-primary { background:var(--accent); color:white; box-shadow:0 2px 4px rgba(13,148,136,0.1); }
        .btn-primary:hover { background:var(--accent-dark); transform:translateY(-1px); box-shadow:0 6px 16px rgba(13,148,136,.35); }
        .btn-outline { background:var(--white); border:1.5px solid var(--border); color:var(--text); box-shadow:0 1px 2px rgba(0,0,0,0.02); }
        .btn-outline:hover { border-color:var(--accent); color:var(--accent); background:#f8fafc; }
        .btn-danger { background:#ef4444; color:white; box-shadow:0 2px 4px rgba(239,68,68,0.1); }
        .btn-danger:hover { background:#dc2626; transform:translateY(-1px); box-shadow:0 6px 16px rgba(220,38,38,0.3); }
        .btn-sm { padding:8px 16px; font-size:13px; border-radius:10px; }

        /* ── Forms ── */
        .form-group { margin-bottom:16px; }
        .form-group label { display:block; font-size:13px; font-weight:600; color:var(--text-sec); margin-bottom:6px; }
        .form-group input, .form-group textarea, .form-group select {
            width:100%; padding:11px 14px; border:1.5px solid var(--border); border-radius:10px;
            font-family:var(--font); font-size:14px; color:var(--text); outline:none; transition:all .2s;
            background:var(--white);
        }
        .form-group input:focus, .form-group textarea:focus, .form-group select:focus {
            border-color:var(--accent); box-shadow:0 0 0 3px rgba(13,148,136,.1);
        }
        .form-group textarea { resize:vertical; min-height:80px; }
        .form-row { display:grid; grid-template-columns:1fr 1fr; gap:16px; }

        /* ── Alerts ── */
        .alert { padding:12px 16px; border-radius:10px; font-size:14px; margin-bottom:16px; }
        .alert-success { background:var(--green-bg); color:var(--green-text); border:1px solid #a7f3d0; }
        .alert-error { background:#fef2f2; color:#991b1b; border:1px solid #fecaca; }

        /* ── Price ── */
        .price-tag { font-size:22px; font-weight:800; color:var(--text); letter-spacing:-0.5px; }
        .price-tag.cheapest { color:var(--green); }
        .price-sm { font-size:13px; color:var(--text-muted); }

        /* ── Table ── */
        .table { width:100%; border-collapse:collapse; }
        .table th, .table td { padding:12px 16px; text-align:left; border-bottom:1px solid var(--border-light); }
        .table th { font-size:12px; font-weight:600; color:var(--text-muted); text-transform:uppercase; letter-spacing:.5px; }
        .table tr:hover { background:var(--accent-bg); }

        /* ── Section ── */
        .section { padding:32px 0; }
        .section-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:20px; }
        .section-header h2 { font-size:20px; font-weight:700; }
        .section-header a { font-size:14px; font-weight:600; color:var(--accent); }

        /* ── Page details ── */
        .detail-grid { display:grid; grid-template-columns:1.3fr 1fr; gap:32px; }
        .detail-sidebar { position:sticky; top:80px; align-self:start; }

        /* ── Footer ── */
        .footer { background:#0f172a; color:#94a3b8; padding:48px 0 24px; margin-top:48px; }
        .footer-grid { display:grid; grid-template-columns:2fr 1fr 1fr 1fr; gap:32px; margin-bottom:32px; }
        .footer h4 { color:white; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:1px; margin-bottom:14px; }
        .footer ul { list-style:none; }
        .footer ul li { margin-bottom:8px; }
        .footer ul a { font-size:14px; color:#94a3b8; transition:color .2s; }
        .footer ul a:hover { color:white; }
        .footer-bottom { border-top:1px solid rgba(255,255,255,.06); padding-top:20px; text-align:center; font-size:13px; }

        /* ── Pagination ── */
        .pagination-wrapper { display:flex; justify-content:center; gap:4px; margin-top:24px; }
        .pagination-wrapper .page-link {
            padding:8px 14px; border:1px solid var(--border); border-radius:8px;
            font-size:14px; color:var(--text-sec); transition:all .2s; display:inline-block; background:var(--white);
        }
        .pagination-wrapper .page-link:hover { border-color:var(--accent); color:var(--accent); }
        .pagination-wrapper .page-link.active { background:var(--accent); border-color:var(--accent); color:white; }

        /* Laravel default pagination (nav > span/a) */
        nav[role="navigation"] { display:flex; justify-content:center; align-items:center; flex-wrap:wrap; gap:4px; margin-top:24px; }
        nav[role="navigation"] > div { display:flex; align-items:center; gap:4px; }
        nav[role="navigation"] span[aria-current="page"] > span,
        nav[role="navigation"] span > span,
        nav[role="navigation"] a {
            display:inline-flex; align-items:center; justify-content:center;
            min-width:36px; height:36px; padding:0 10px;
            border:1px solid var(--border); border-radius:8px;
            font-size:14px; color:var(--text-sec); background:var(--white);
            transition:all .2s; line-height:1;
        }
        nav[role="navigation"] a:hover { border-color:var(--accent); color:var(--accent); }
        nav[role="navigation"] span[aria-current="page"] > span {
            background:var(--accent); border-color:var(--accent); color:white; font-weight:600;
        }
        nav[role="navigation"] span.cursor-default > span {
            color:var(--text-muted); border-color:var(--border-light); background:var(--bg);
        }
        /* Fix giant SVG arrows */
        nav[role="navigation"] svg {
            width:14px; height:14px; display:inline-block; vertical-align:middle;
        }

        /* ── Stat Card ── */
        .stat-card { background:var(--white); border:1px solid var(--border); border-radius:var(--radius); padding:20px; }
        .stat-card .stat-value { font-size:28px; font-weight:800; letter-spacing:-0.5px; }
        .stat-card .stat-label { font-size:13px; color:var(--text-muted); margin-top:2px; }

        /* ── Panel Layout Overrides (Premium) ── */
        body.panel-layout-active { padding-top:70px; 
            background: linear-gradient(135deg, #fdfbfb 0%, #ebedee 100%) !important; 
        }
        body.panel-layout-active .nav { position:fixed; top:0; left:0; right:0; z-index:1001; height:70px; display:flex; align-items:center; border-bottom:1px solid rgba(255,255,255,0.1); box-shadow:0 1px 3px rgba(0,0,0,0.02); padding:0 32px; background:var(--white);}
        body.panel-layout-active .nav .nav-inner { max-width:100% !important; padding:0 !important; width:100%; display:flex !important; align-items:center; }
        body.panel-layout-active .nav-logo { font-size: 20px; color:#0f172a; font-weight:800; }
        
        /* Remove default container constraints entirely for panel */
        body.panel-layout-active .container { max-width:none !important; padding:0 !important; width:100% !important; display:block !important; margin:0 !important; }
        
        .panel-sidebar-module {
            position:fixed !important; top:70px !important; left:0 !important; bottom:0 !important; 
            width:260px !important; 
            background: linear-gradient(180deg, #1e293b 0%, #0f172a 100%) !important; 
            color: #f8fafc !important;
            border: none !important;
            border-radius:0 !important; padding:24px 16px !important; overflow-y:auto; z-index:1000 !important;
            box-shadow: 4px 0 15px rgba(0,0,0,0.05) !important;
        }
        
        /* Premium dashboard main area padding */
        .panel-sidebar-module + div, .panel-sidebar-module + .section, .panel-sidebar-module ~ div, .panel-sidebar-module ~ .section {
            margin-left:260px !important; padding:32px 48px; min-height:calc(100vh - 70px);
            width:calc(100% - 260px) !important; box-sizing:border-box; background:transparent; max-width:none !important;
        }
        
        /* Panel global UI refinements */
        body.panel-layout-active .stat-card, body.panel-layout-active div[style*="border-radius"] {
            box-shadow: 0 10px 40px -10px rgba(0,0,0,0.06), 0 8px 16px -8px rgba(0,0,0,0.03) !important;
            border: 1px solid rgba(255,255,255,0.8) !important;
            border-radius: 20px !important;
            background: rgba(255, 255, 255, 0.9) !important;
            backdrop-filter: blur(12px);
        }
        body.panel-layout-active .table th { background:transparent; border-bottom:1px solid #f1f5f9; font-size:12px; text-transform:uppercase; letter-spacing:0.8px; font-weight:700; color:#94a3b8; padding:20px 24px; }
        body.panel-layout-active .table td { padding:20px 24px; border-bottom:1px solid #f8fafc; vertical-align:middle; transition:background 0.2s;}
        body.panel-layout-active .table tr:hover td { background: #f8fafc; }
        body.panel-layout-active h1 { color:#0f172a; letter-spacing:-0.5px; }

        /* Panel content blocks: 94% centered */
        body.panel-layout-active .panel-sidebar-module ~ div > .stat-card,
        body.panel-layout-active .panel-sidebar-module ~ .section > .stat-card,
        body.panel-layout-active .panel-sidebar-module ~ div > div[style*="grid"],
        body.panel-layout-active .panel-sidebar-module ~ .section > div[style*="grid"],
        body.panel-layout-active .panel-sidebar-module ~ div > .section > .stat-card,
        body.panel-layout-active .panel-sidebar-module ~ div > .section > div[style*="grid"],
        body.panel-layout-active .panel-sidebar-module ~ div > .card,
        body.panel-layout-active .panel-sidebar-module ~ div .section > .card {
            max-width: 94%; margin-left: auto; margin-right: auto;
        }

        body.panel-layout-active footer { display:none !important; }

        /* ── Responsive ── */
        @media(max-width:768px) {
            .container { padding:0 16px; }
            .grid-3,.grid-4 { grid-template-columns:repeat(2,1fr); gap:12px; }
            .form-row { grid-template-columns:1fr; }
            .footer-grid { grid-template-columns:1fr 1fr; }
            .nav-links { display:none; }
            .mobile-menu-btn { display:flex!important; }
            .mobile-nav.open { display:flex!important; }
            .detail-grid { grid-template-columns:1fr; }
            .detail-sidebar { position:static; }
            .section { padding:24px 0; }
            .card-img { height:140px; }
        }
        @media(max-width:480px) {
            .grid-2,.grid-3,.grid-4 { grid-template-columns:1fr; }
            .footer-grid { grid-template-columns:1fr; }
        }

        @yield('styles')
    </style>
</head>
<body class="{{ request()->is('admin*') || request()->is('agency*') || request()->is('acenta*') ? 'panel-layout-active' : '' }}">
    <nav class="nav">
        <div class="container nav-inner">
            <a href="{{ route('home') }}" class="nav-logo">🏖️ Stay<span>Finder</span></a>
            <div class="nav-links">
                <a href="{{ route('tours.index') }}">Turlar</a>
                <a href="{{ route('blog.index') }}">Blog</a>
                @auth
                    @if(auth()->user()->isAdmin())
                        <a href="{{ route('admin.dashboard') }}">Admin Panel</a>
                    @elseif(auth()->user()->isAgency())
                        <a href="{{ route('agency.dashboard') }}">Panelim</a>
                    @else
                        <a href="{{ route('favorites.index') }}">Favorilerim</a>
                    @endif
                    
                    <div class="nav-profile">
                        👤 {{ auth()->user()->name }}
                    </div>

                    <form method="POST" action="{{ route('logout') }}" style="display:inline;margin:0;">
                        @csrf
                        <button type="submit" class="nav-logout-btn">Çıkış</button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="nav-btn">Giriş Yap</a>
                    <a href="{{ route('register') }}" class="nav-btn nav-btn-primary">Kayıt Ol</a>
                @endauth
            </div>
            <button class="mobile-menu-btn" onclick="document.getElementById('mobileNav').classList.toggle('open')">☰</button>
        </div>
        <div id="mobileNav" class="mobile-nav">
            <a href="{{ route('tours.index') }}">🧭 Turlar</a>
            <a href="{{ route('blog.index') }}">✍️ Blog</a>
            @auth
                @if(auth()->user()->isAdmin())
                    <a href="{{ route('admin.dashboard') }}">⚙️ Admin Panel</a>
                @elseif(auth()->user()->isAgency())
                    <a href="{{ route('agency.dashboard') }}">📊 Panelim</a>
                @else
                    <a href="{{ route('favorites.index') }}">❤️ Favorilerim</a>
                    <a href="{{ route('profile.show') }}">👤 Profilim</a>
                @endif
                <div style="font-size:14px;color:var(--text-muted);padding:10px 0;border-bottom:none;">👤 {{ auth()->user()->name }}</div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" style="color:#dc2626;font-weight:600;padding:12px 0;border-bottom:none;">Çıkış Yap</button>
                </form>
            @else
                <div class="mobile-auth">
                    <a href="{{ route('login') }}" class="btn btn-outline" style="flex:1;">Giriş Yap</a>
                    <a href="{{ route('register') }}" class="btn btn-primary" style="flex:1;">Kayıt Ol</a>
                </div>
            @endauth
        </div>
    </nav>

    <main>@yield('content')</main>

    <footer class="footer">
        <div class="container">
            <div class="footer-grid">
                <div>
                    <div style="font-size:20px;font-weight:800;color:white;margin-bottom:10px;">🏖️ Stay<span style="color:#0d9488;">Finder</span></div>
                    <p style="font-size:14px;line-height:1.8;">Türkiye'nin en iyi tur acentalarından fiyatları karşılaştırın. En uygun turu bulun.</p>
                </div>
                <div><h4>Platform</h4><ul><li><a href="{{ route('tours.index') }}">Turlar</a></li><li><a href="#">Nasıl Çalışır?</a></li></ul></div>
                <div><h4>Acentalar</h4><ul><li><a href="{{ route('login') }}">Acenta Girişi</a></li><li><a href="#">Acenta Ol</a></li></ul></div>
                <div><h4>Yasal</h4><ul><li><a href="#">Gizlilik</a></li><li><a href="#">Kullanım Koşulları</a></li></ul></div>
            </div>
            <div class="footer-bottom">StayFinder © 2026 · Tüm hakları saklıdır.</div>
        </div>
    </footer>
    @stack('scripts')
</body>
</html>
