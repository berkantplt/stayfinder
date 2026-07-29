<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, interactive-widget=resizes-content">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'turXtur — Tur Karşılaştırma')</title>
    <meta name="description" content="@yield('description', 'Türkiye\'nin en iyi tur acentalarından fiyatları karşılaştırın.')">
    <link rel="canonical" href="{{ url()->current() }}">

    {{-- Open Graph --}}
    <meta property="og:type" content="website">
    <meta property="og:title" content="@yield('title', 'turXtur — Tur Karşılaştırma')">
    <meta property="og:description" content="@yield('description', 'Türkiye\'nin en iyi tur acentalarından fiyatları karşılaştırın.')">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:image" content="@yield('og_image', asset('images/og-default.png'))">
    <meta property="og:locale" content="tr_TR">
    <meta property="og:site_name" content="turXtur">

    {{-- Twitter Card --}}
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="@yield('title', 'turXtur — Tur Karşılaştırma')">
    <meta name="twitter:description" content="@yield('description', 'Türkiye\'nin en iyi tur acentalarından fiyatları karşılaştırın.')">
    <meta name="twitter:image" content="@yield('og_image', asset('images/og-default.png'))">

    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">

    {{-- PWA: ana ekrana eklenince uygulama gibi açılır --}}
    <link rel="manifest" href="{{ asset('manifest.json') }}">
    <meta name="theme-color" content="#0c332e">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="turXtur">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    {{-- Mobil tasarım fontları (yalnız mobil seçicilerde kullanılır; kullanılmayan font indirilmez) --}}
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@500;600;700;800&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">
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
        .container { max-width:1280px; margin:0 auto; padding:0 20px; }

        /* ── Navbar ── */
        .nav { background:var(--white); border-bottom:1px solid var(--border-light); padding:0 32px; position:sticky; top:0; z-index:100; height:70px; display:flex; align-items:center; }
        /* Logo tam ortada: 1fr auto 1fr — yan içerikler farklı genişlikte olsa da merkez sabit */
        .nav-inner { display:grid; grid-template-columns:1fr auto 1fr; align-items:center; width:100%; max-width:none !important; margin:0; }
        .nav-logo { font-size:20px; font-weight:800; color:#0f172a; display:flex; align-items:center; gap:8px; letter-spacing:-0.5px; flex-shrink:0; grid-column:2; grid-row:1; justify-self:center; }
        .nav-logo span { color:var(--accent); }
        .nav-links { display:flex; align-items:center; gap:20px; }
        /* Gruplar logonun iki yanında ortada kümeli — kenarlar boş */
        .nav-links-left { grid-column:1; grid-row:1; justify-self:end; margin-right:36px; }
        .nav-links-right { grid-column:3; grid-row:1; justify-self:start; margin-left:36px; }
        .nav-bell-mobile { display:none; grid-column:3; grid-row:1; justify-self:end; position:relative; font-size:20px; text-decoration:none; align-items:center; }
        .nav-links a:not(.nav-btn) { color:#475569; font-weight:600; font-size:14.5px; transition:all 0.2s; display:flex; align-items:center; gap:6px; letter-spacing:0.1px; padding:6px 0; border-bottom:2px solid transparent; }
        .nav-links a:not(.nav-btn):hover { color:var(--accent); }
        .nav-links a.nav-active:not(.nav-btn) { color:var(--accent); border-bottom-color:var(--accent); }
        
        .nav-profile { display:flex; align-items:center; gap:8px; color:#64748b; font-size:14.5px; font-weight:500; padding:0 16px; border-left:1px solid #e2e8f0; height:24px; margin-left:4px; }
        
        .nav-btn { display:inline-flex; align-items:center; justify-content:center; padding:0 20px; height:40px; border-radius:10px; font-size:14.5px; font-weight:600; border:1px solid #cbd5e1; background:var(--white); color:#0f172a; cursor:pointer; transition:all 0.2s; flex-shrink:0; white-space:nowrap; text-decoration:none; }
        .nav-btn:hover { background:#f8fafc; border-color:#94a3b8; }
        .nav-btn-primary { background:var(--accent); color:#fff; border-color:var(--accent); }
        .nav-btn-primary:hover { background:var(--accent-dark); color:#fff; border-color:var(--accent-dark); transform:translateY(-1px); box-shadow:0 6px 14px -4px rgba(13,148,136,0.4); }
        
        .nav-profile { display:flex; align-items:center; gap:8px; color:#64748b; font-size:14px; font-weight:600; padding:0 16px; border-left:1px solid #e2e8f0; height:24px; margin-left:4px; }
        .nav-logout-btn { background:none; border:none; color:#ef4444; font-family:var(--font); font-size:14px; font-weight:700; cursor:pointer; padding:6px 12px; border-radius:8px; transition:all 0.2s; }
        .nav-logout-btn:hover { background:#fef2f2; color:#dc2626; }
        
        /* Mobile menu */
        .mobile-menu-btn {
            display:none; width:40px; height:40px; align-items:center; justify-content:center;
            border:1.5px solid var(--border); border-radius:10px; background:var(--white); cursor:pointer; font-size:18px;
            grid-column:1; grid-row:1; justify-self:start;
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

        @media(max-width: 992px) {
            .grid-4 { grid-template-columns:repeat(2,1fr); }
            .grid-3 { grid-template-columns:repeat(2,1fr); }
        }
        @media(max-width: 640px) {
            .grid-2, .grid-3, .grid-4 { grid-template-columns:1fr; }
        }

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
        /* Grid kolonu, içindeki en geniş kırılmaz öğe kadar genişlemesin
           (mobilde tarih kutuları sayfayı yana taşırıyordu) */
        .detail-grid > div { min-width:0; }
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
        body.panel-layout-active .nav .nav-inner { max-width:100% !important; padding:0 !important; width:100%; display:grid !important; grid-template-columns:1fr auto 1fr; align-items:center; }
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
            .mobile-nav.open { display:flex!important; }
            .detail-grid { grid-template-columns:1fr; }
            .detail-sidebar { position:static; }
            .section { padding:24px 0; }
            .card-img { height:140px; }

            /* ===== Mobil iskelet (turXtur Mobil 2a tasarımı) ===== */
            .nav { height:auto; padding:0; flex-direction:column; align-items:stretch; }
            .nav-inner { display:none !important; }
            .m-trust { display:flex; justify-content:center; gap:16px; background:#0c332e; color:rgba(255,255,255,.85); font-size:10.5px; font-weight:600; padding:7px 12px; font-family:'Manrope',var(--font); }
            .m-trust span { display:flex; align-items:center; gap:5px; }
            .m-trust i { width:5px; height:5px; border-radius:50%; background:#5eead4; }
            .m-head { display:flex; align-items:center; justify-content:space-between; padding:10px 16px; background:var(--white); }
            .m-head-right { display:flex; align-items:center; gap:12px; }
            .m-bell { position:relative; width:34px; height:34px; border-radius:50%; background:#f0f6f4; display:flex; align-items:center; justify-content:center; font-size:15px; text-decoration:none; }
            .m-bell-dot { position:absolute; top:5px; right:6px; width:7px; height:7px; border-radius:50%; background:#e0563a; border:1.5px solid #fff; }
            .m-avatar { width:34px; height:34px; border-radius:50%; background:var(--accent); color:#fff; border:none; font-family:'Manrope',var(--font); font-size:12px; font-weight:800; cursor:pointer; overflow:hidden; display:flex; align-items:center; justify-content:center; }
            .m-avatar img { width:100%; height:100%; object-fit:cover; }
            .m-login { background:var(--accent); color:#fff; font-size:12.5px; font-weight:700; padding:8px 16px; border-radius:100px; text-decoration:none; font-family:'Manrope',var(--font); }
            .m-tabbar { display:grid; position:fixed; bottom:0; left:0; right:0; z-index:1500; background:rgba(255,255,255,.93); backdrop-filter:blur(12px); -webkit-backdrop-filter:blur(12px); border-top:1px solid rgba(15,36,33,.08); grid-template-columns:repeat(4,1fr); padding:10px 8px calc(12px + env(safe-area-inset-bottom)); }
            .m-tabbar a { display:flex; flex-direction:column; align-items:center; gap:4px; font-size:11px; font-weight:600; color:#8a9a95; text-decoration:none; font-family:'Manrope',var(--font); }
            .m-tabbar a i { width:5px; height:5px; border-radius:50%; background:transparent; }
            .m-tabbar a.active { color:var(--accent); font-weight:800; }
            .m-tabbar a.active i { background:var(--accent); }
            body { padding-bottom:80px; }
            body.panel-layout-active { padding-bottom:0; }
            body.panel-layout-active .m-trust { display:none; }
            #ai-chat-container { bottom:92px !important; }
            #compare-bar { bottom:92px !important; }
            /* AI chat mobil: balon kompakt yuvarlak FAB olur (yazı gizli),
               pencere tam ekran açılır (inline stilleri !important ezer) */
            #ai-chat-trigger { padding:10px !important; gap:0 !important; border-radius:50% !important; }
            #ai-chat-trigger > div:last-child { display:none; }
            #ai-chat-window { position:fixed !important; inset:0 !important; width:100% !important; height:100% !important; max-height:none !important; border-radius:0 !important; }
            #ai-chat-window > div:first-child { padding-top:calc(16px + env(safe-area-inset-top)) !important; }
            #ai-chat-window > div:last-child { padding-bottom:calc(12px + env(safe-area-inset-bottom)) !important; }

            /* ===== Yatay tur kartı listesi (anasayfa kart düzeni — m-hcard-list
               sınıfı verilen her tur ızgarası mobilde bu düzene döner) ===== */
            .m-hcard-list { display:flex !important; flex-direction:column; gap:12px !important; }
            .m-hcard-list .card { position:relative; border-radius:16px; border:1px solid rgba(15,36,33,.09); overflow:hidden; }
            .m-hcard-list a.card { display:flex !important; flex-direction:row !important; }
            .m-hcard-list div.card > a { display:flex; flex-direction:row; }
            .m-hcard-list .card-img { width:118px !important; min-width:118px; height:auto !important; min-height:110px; object-fit:cover; }
            .m-hcard-list .card-body { padding:12px 14px !important; flex:1; min-width:0; display:flex; flex-direction:column; gap:5px; }
            .m-hcard-list .card-title { font-size:13px !important; font-weight:700; line-height:1.35; font-family:'Manrope',var(--font); margin-bottom:0 !important; }
            .m-hcard-list .card-meta { font-family:ui-monospace,monospace; font-size:10.5px !important; color:#7d938d; margin-bottom:0 !important; }
            .m-hcard-list .price-tag { font-family:'Space Grotesk',var(--font); font-size:16px !important; color:#08211d; }
            /* Anasayfa kartlarındaki gibi yalın: liste içi buton/rozetler gizli */
            .m-hcard-list .card .btn { display:none !important; }
            .m-hcard-list .card .badge { display:none !important; }
            .m-hcard-list div.card > div:last-child { display:none; }

            /* ===== App hissi: dokunma geri bildirimi ===== */
            * { -webkit-tap-highlight-color: transparent; }
            a, button, select, .card { touch-action: manipulation; }
            .btn, .m-chip, .category-tab, .tour-tab, .m-tabbar a, .m-avatar, .m-login, .m-search-btn { transition: transform .12s ease; }
            .btn:active, .m-chip:active, .category-tab:active, .tour-tab:active, .m-tabbar a:active, .m-avatar:active, .m-login:active, .m-search-btn:active { transform: scale(.95); }
            .card { transition: transform .12s ease; }
            .card:active { transform: scale(.98); }
            .m-trust, .m-head, .m-tabbar, .tour-tabs, .stories-scroll { -webkit-user-select:none; user-select:none; }
        }
        @media(min-width:769px) {
            .m-trust, .m-head, .m-tabbar { display:none; }
        }

        /* ===== App hissi: sayfalar arası yumuşak geçiş (View Transitions) ===== */
        @view-transition { navigation: auto; }
        ::view-transition-old(root), ::view-transition-new(root) { animation-duration: .18s; }
        @media (prefers-reduced-motion: reduce) {
            ::view-transition-old(*), ::view-transition-new(*) { animation: none !important; }
        }
        @media(max-width:480px) {
            .grid-2,.grid-3,.grid-4 { grid-template-columns:1fr; }
            .footer-grid { grid-template-columns:1fr; }
        }

        @yield('styles')
    </style>
    @stack('head')
</head>
<body class="{{ request()->is('admin*') || request()->is('agency*') || request()->is('acenta*') ? 'panel-layout-active' : '' }}">
    <nav class="nav">
        @auth
            @php
                // SQL COUNT (satırları belleğe yüklemeden) + görülmemiş duyuru sayısı
                $unreadCount = auth()->user()->unreadNotifications()->count()
                    + \App\Models\Announcement::unseenBy(auth()->user())->count();
            @endphp
        @endauth

        {{-- ===== Mobil üst şerit + başlık (≤768px) ===== --}}
        <div class="m-trust">
            <span><i></i>Onaylı acentalar</span>
            <span><i></i>Komisyonsuz karşılaştırma</span>
            <span><i></i>7/24 destek</span>
        </div>
        <div class="m-head">
            <a href="{{ route('home') }}" style="display:flex;align-items:center;">@include('partials.logo', ['height' => 24])</a>
            <div class="m-head-right">
                @auth
                    <a href="{{ route('notifications.index') }}" class="m-bell" aria-label="Bildirimler">🔔
                        @if($unreadCount > 0)<span class="m-bell-dot"></span>@endif
                    </a>
                    <button type="button" class="m-avatar" aria-label="Menü"
                        onclick="document.getElementById('mobileNav').classList.toggle('open')">
                        @if(auth()->user()->avatar)
                            <img src="{{ Str::startsWith(auth()->user()->avatar, ['http://','https://']) ? auth()->user()->avatar : asset('storage/'.auth()->user()->avatar) }}" alt="{{ auth()->user()->name }}">
                        @else
                            {{ mb_strtoupper(collect(explode(' ', trim(auth()->user()->name)))->filter()->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->join('')) }}
                        @endif
                    </button>
                @else
                    <a href="{{ route('login') }}" class="m-login">Giriş Yap</a>
                @endauth
            </div>
        </div>

        <div class="container nav-inner">
            <button class="mobile-menu-btn" onclick="document.getElementById('mobileNav').classList.toggle('open')">☰</button>

            {{-- Sol grup: gezinme --}}
            <div class="nav-links nav-links-left">
                <a href="{{ route('tours.index') }}" class="{{ request()->is('turlar') ? 'nav-active' : '' }}">Turlar</a>
                <a href="{{ route('blog.index') }}" class="{{ request()->is('blog*') ? 'nav-active' : '' }}">Blog</a>
                @auth
                    @if(! auth()->user()->isAdmin() && ! auth()->user()->isAgency())
                        <a href="{{ route('favorites.index') }}">Favorilerim</a>
                        <a href="{{ route('customer.coupons.index') }}">Kuponlarım</a>
                    @endif
                @endauth
            </div>

            <a href="{{ route('home') }}" class="nav-logo">@include('partials.logo', ['height' => 30])</a>

            {{-- Sağ grup: hesap --}}
            <div class="nav-links nav-links-right">
                @auth
                    <a href="{{ route('notifications.index') }}" class="nav-notification-icon {{ request()->routeIs('notifications.index') ? 'active' : '' }}" style="position:relative; font-size:20px; text-decoration:none;">
                        🔔
                        @if($unreadCount > 0)
                        <span style="position:absolute; top:-5px; right:-8px; background:#ef4444; color:white; font-size:10px; font-weight:800; padding:2px 5px; border-radius:100px; line-height:1; min-width:14px; text-align:center; border:2px solid white;">{{ $unreadCount }}</span>
                        @endif
                    </a>

                    @if(auth()->user()->isAdmin())
                        <a href="{{ route('admin.dashboard') }}">Admin Panel</a>
                    @elseif(auth()->user()->isAgency())
                        <a href="{{ auth()->user()->agencyApproved() ? route('agency.dashboard') : route('agency.application.status') }}">
                            {{ auth()->user()->agencyApproved() ? 'Panelim' : 'Başvuru Durumu' }}
                        </a>
                    @else
                        <a href="{{ route('profile.show') }}">Profilim</a>
                    @endif

                    <a href="{{ auth()->user()->isAdmin() || auth()->user()->isAgency() ? '#' : route('profile.show') }}" class="nav-profile" style="text-decoration:none;color:inherit;">
                        @if(auth()->user()->avatar)
                            <img src="{{ Str::startsWith(auth()->user()->avatar, ['http://','https://']) ? auth()->user()->avatar : asset('storage/'.auth()->user()->avatar) }}" alt="{{ auth()->user()->name }}" style="width:24px;height:24px;border-radius:50%;object-fit:cover;">
                        @else
                            <span style="font-size:16px;">👤</span>
                        @endif
                        {{ auth()->user()->name }}
                    </a>

                    <form method="POST" action="{{ route('logout') }}" style="display:inline;margin:0;">
                        @csrf
                        <button type="submit" class="nav-logout-btn">Çıkış</button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="nav-btn">Giriş Yap</a>
                    <a href="{{ route('register') }}" class="nav-btn nav-btn-primary">Kayıt Ol</a>
                @endauth
            </div>

            {{-- Mobil: sağ köşede zil (menü linkleri hamburgerde) --}}
            @auth
                <a href="{{ route('notifications.index') }}" class="nav-bell-mobile">
                    🔔
                    @if($unreadCount > 0)
                    <span style="position:absolute; top:-5px; right:-8px; background:#ef4444; color:white; font-size:10px; font-weight:800; padding:2px 5px; border-radius:100px; line-height:1; min-width:14px; text-align:center; border:2px solid white;">{{ $unreadCount }}</span>
                    @endif
                </a>
            @endauth
        </div>
        <div id="mobileNav" class="mobile-nav">
            <a href="{{ route('tours.index') }}">🧭 Turlar</a>
            <a href="{{ route('blog.index') }}">✍️ Blog</a>
            @auth
                @php
                    // Desktop nav yukarıda hesapladıysa yeniden sorgulama
                    $unreadCount ??= auth()->user()->unreadNotifications()->count()
                        + \App\Models\Announcement::unseenBy(auth()->user())->count();
                @endphp
                <a href="{{ route('notifications.index') }}">🔔 Bildirimler @if($unreadCount > 0) <span style="background:#ef4444; color:white; padding:2px 8px; border-radius:10px; font-size:12px; margin-left:5px;">{{ $unreadCount }}</span> @endif</a>
                @if(auth()->user()->isAdmin())
                    <a href="{{ route('admin.dashboard') }}">⚙️ Admin Panel</a>
                @elseif(auth()->user()->isAgency())
                    <a href="{{ auth()->user()->agencyApproved() ? route('agency.dashboard') : route('agency.application.status') }}">
                        {{ auth()->user()->agencyApproved() ? '📊 Panelim' : '📝 Başvuru Durumu' }}
                    </a>
                @else
                    <a href="{{ route('favorites.index') }}">❤️ Favorilerim</a>
                    <a href="{{ route('customer.coupons.index') }}">🎟️ Kuponlarım</a>
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

    {{-- ===== Mobil sabit alt gezinme (≤768px, panel sayfaları hariç) ===== --}}
    @if(! request()->is('admin*') && ! request()->is('agency*') && ! request()->is('acenta*'))
        @php
            $mProfileUrl = auth()->guest()
                ? route('login')
                : (auth()->user()->isAdmin()
                    ? route('admin.dashboard')
                    : (auth()->user()->isAgency()
                        ? (auth()->user()->agencyApproved() ? route('agency.dashboard') : route('agency.application.status'))
                        : route('profile.show')));
        @endphp
        <div class="m-tabbar">
            <a href="{{ route('home') }}" class="{{ request()->routeIs('home') ? 'active' : '' }}"><i></i>Keşfet</a>
            <a href="{{ route('tours.index') }}" class="{{ request()->is('turlar*') ? 'active' : '' }}"><i></i>Turlar</a>
            <a href="{{ auth()->check() ? route('favorites.index') : route('login') }}" class="{{ request()->is('favorilerim*') ? 'active' : '' }}"><i></i>Favoriler</a>
            <a href="{{ $mProfileUrl }}" class="{{ request()->is('profil*') ? 'active' : '' }}"><i></i>Profil</a>
        </div>
    @endif

    <footer class="footer">
        <div class="container">
            <div class="footer-grid">
                <div>
                    <div style="font-size:20px;font-weight:800;color:white;margin-bottom:10px;letter-spacing:-0.4px;">tur<span style="color:#2dd4bf;">X</span>tur</div>
                    <p style="font-size:14px;line-height:1.8;">Türkiye'nin en iyi tur acentalarından fiyatları karşılaştırın. En uygun turu bulun.</p>
                </div>
                <div><h4>Platform</h4><ul><li><a href="{{ route('tours.index') }}">Turlar</a></li><li><a href="#">Nasıl Çalışır?</a></li></ul></div>
                <div><h4>Acentalar</h4><ul><li><a href="{{ route('login') }}">Acenta Girişi</a></li><li><a href="{{ route('agency.register') }}">Acenta Ol</a></li></ul></div>
                <div><h4>Yasal</h4><ul><li><a href="#">Gizlilik</a></li><li><a href="#">Kullanım Koşulları</a></li></ul></div>
            </div>
            <div class="footer-bottom">turXtur © 2026 · Tüm hakları saklıdır.</div>
        </div>
    </footer>

    <div id="compare-bar" style="display:none;position:fixed;left:50%;bottom:24px;transform:translateX(-50%);z-index:1600;background:#0f172a;color:#f8fafc;border:1px solid rgba(255,255,255,0.15);border-radius:16px;box-shadow:0 16px 32px rgba(2,6,23,0.35);padding:12px 16px;min-width:320px;max-width:calc(100vw - 24px);">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;">
            <div style="font-size:14px;font-weight:700;display:flex;align-items:center;gap:8px;">
                <span style="width:24px;height:24px;border-radius:999px;background:#0d9488;display:inline-flex;align-items:center;justify-content:center;font-size:12px;">✓</span>
                <span><span id="compare-count">0</span> tur seçildi</span>
            </div>
            <div style="display:flex;align-items:center;gap:8px;">
                <button type="button" onclick="window.clearCompare()" style="border:1px solid rgba(255,255,255,0.25);background:transparent;color:#cbd5e1;border-radius:10px;padding:8px 12px;font-size:12px;font-weight:700;cursor:pointer;">Temizle</button>
                <button type="button" onclick="window.goToCompare()" style="border:none;background:#0d9488;color:white;border-radius:10px;padding:8px 14px;font-size:12px;font-weight:800;cursor:pointer;">Karşılaştır</button>
            </div>
        </div>
    </div>

    {{-- ===== AI tur kartı: TEK ortak üretici (yüzen widget + tam sayfa sohbet) =====
         $hideAiChat guard'ının DIŞINDA durur: admin tam sayfa sohbeti açtığında
         widget gizliyken de tam sayfanın kart üreticisi çalışmalı. --}}
    <script>
    window.turxturAiCard = (function () {
        var CURRENCY = { TRY: '₺', USD: '$', EUR: '€', GBP: '£', AED: 'AED', SAR: 'SAR' };

        // Tema paletleri: dark = yüzen widget (cam panel), light = tam sayfa sohbet
        var THEMES = {
            dark: {
                card: 'position:relative; display:flex; flex-direction:column; flex:0 0 220px; min-width:220px; min-height:252px; background:rgba(255,255,255,0.08); border-radius:16px; border:1px solid rgba(255,255,255,0.15); scroll-snap-align:start; transition:transform .3s, box-shadow .3s, opacity .35s; overflow:hidden;',
                cardClass: 'ai-tour-card',
                hoverLift: 'translateY(-4px)',
                hoverShadow: '0 16px 32px rgba(0,0,0,0.3)',
                link: 'text-decoration:none; color:white; display:flex; flex-direction:column; flex:1; min-height:252px;',
                imgBox: 'position:relative; width:100%; height:140px; background:#1e293b; flex-shrink:0;',
                imgFallback: 'linear-gradient(135deg,#0f172a,#134e4a)',
                gradient: 'linear-gradient(to top, rgba(15,23,42,0.8), transparent)',
                badge: 'position:absolute; top:8px; right:8px; background:rgba(0,0,0,0.6); backdrop-filter:blur(8px); padding:4px 10px; border-radius:12px; font-size:10px; font-weight:800; color:#34d399; z-index:2;',
                content: 'padding:14px; display:flex; flex-direction:column; flex:1;',
                dest: 'font-size:10px; color:#94a3b8; font-weight:800; text-transform:uppercase; letter-spacing:0.5px;',
                title: 'font-size:14px; font-weight:800; margin-top:4px; color:#f8fafc; line-height:1.4;',
                agency: 'font-size:11px; color:#94a3b8; margin-top:2px;',
                reason: 'font-size:11px; color:#5eead4; font-style:italic; margin-top:4px; line-height:1.4;',
                nextDep: 'font-size:11px; color:#94a3b8; margin-top:4px;',
                chipRow: 'display:flex; flex-wrap:wrap; gap:4px; margin-top:6px;',
                chipOver: 'font-size:10px; color:#fde68a; font-weight:700; background:rgba(251,191,36,0.15); border:1px solid rgba(251,191,36,0.4); padding:2px 8px; border-radius:8px;',
                chipFlex: 'font-size:10px; color:#6ee7b7; font-weight:700; background:rgba(52,211,153,0.15); border:1px solid rgba(52,211,153,0.4); padding:2px 8px; border-radius:8px;',
                bottom: 'display:flex; justify-content:space-between; align-items:center; margin-top:auto; padding-top:10px; border-top:1px solid rgba(255,255,255,0.1); gap:6px;',
                price: 'font-size:16px; font-weight:900; color:#34d399;',
                cta: 'font-size:11px; font-weight:700; color:#0f172a; background:#34d399; padding:6px 12px; border-radius:100px; white-space:nowrap;',
                rejectBtn: 'position:absolute; top:8px; left:8px; width:24px; height:24px; border-radius:50%; border:none; background:rgba(255,255,255,0.18); color:#fff; cursor:pointer; font-size:13px; line-height:1; display:flex; align-items:center; justify-content:center; backdrop-filter:blur(8px); z-index:3;',
                popup: 'display:none; position:absolute; top:36px; left:8px; background:#0f172a; border:1px solid rgba(255,255,255,0.15); border-radius:12px; padding:6px; z-index:4; flex-direction:column; gap:2px; min-width:160px; box-shadow:0 12px 24px rgba(0,0,0,.3);',
                popupOpt: 'background:transparent; border:none; text-align:left; padding:6px 10px; border-radius:8px; cursor:pointer; font-size:12px; color:#e2e8f0;',
                popupOptHover: 'rgba(255,255,255,0.08)'
            },
            light: {
                card: 'position:relative; display:flex; flex-direction:column; flex:0 0 220px; min-width:220px; background:#fff; border:1px solid #e2e8f0; border-radius:14px; overflow:hidden; transition:transform .15s, box-shadow .15s, opacity .35s;',
                cardClass: '',
                hoverLift: 'translateY(-2px)',
                hoverShadow: '0 4px 12px rgba(0,0,0,.08)',
                link: 'text-decoration:none; color:inherit; display:flex; flex-direction:column; flex:1;',
                imgBox: 'position:relative; width:100%; height:120px; background:#f1f5f9; flex-shrink:0;',
                imgFallback: 'linear-gradient(135deg,#eef2ff,#ecfdf5)',
                gradient: null,
                badge: 'position:absolute; top:8px; right:8px; background:rgba(255,255,255,0.92); padding:3px 9px; border-radius:10px; font-size:11px; font-weight:700; color:#0f766e; z-index:2;',
                content: 'padding:12px; display:flex; flex-direction:column; flex:1;',
                dest: 'font-size:11px; color:#6366f1; font-weight:700; text-transform:uppercase;',
                title: 'font-size:14px; font-weight:700; margin-top:4px; color:#0f172a; line-height:1.3;',
                agency: 'font-size:12px; color:#64748b; margin-top:2px;',
                reason: 'font-size:11.5px; color:#0f766e; font-style:italic; margin-top:4px; line-height:1.4;',
                nextDep: 'font-size:11px; color:#64748b; margin-top:4px;',
                chipRow: 'display:flex; flex-wrap:wrap; gap:4px; margin-top:6px;',
                chipOver: 'font-size:11px; color:#92400e; font-weight:700; background:#fffbeb; border:1px solid #fde68a; padding:2px 8px; border-radius:8px;',
                chipFlex: 'font-size:11px; color:#166534; font-weight:700; background:#ecfdf5; border:1px solid #bbf7d0; padding:2px 8px; border-radius:8px;',
                bottom: 'display:flex; justify-content:space-between; align-items:center; margin-top:auto; padding-top:10px; border-top:1px solid #f1f5f9; gap:6px;',
                price: 'font-size:15px; font-weight:800; color:#0f172a;',
                cta: 'font-size:11px; font-weight:700; color:#fff; background:#6366f1; padding:6px 12px; border-radius:100px; white-space:nowrap;',
                rejectBtn: 'position:absolute; top:8px; left:8px; width:24px; height:24px; border-radius:50%; border:none; background:rgba(15,23,42,0.55); color:#fff; cursor:pointer; font-size:13px; line-height:1; display:flex; align-items:center; justify-content:center; backdrop-filter:blur(4px); z-index:3;',
                popup: 'display:none; position:absolute; top:36px; left:8px; background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:6px; z-index:4; flex-direction:column; gap:2px; min-width:160px; box-shadow:0 8px 24px rgba(0,0,0,.12);',
                popupOpt: 'background:transparent; border:none; text-align:left; padding:6px 10px; border-radius:8px; cursor:pointer; font-size:12px; color:#334155;',
                popupOptHover: '#f1f5f9'
            }
        };

        var REJECT_REASONS = [
            ['too_expensive', '💸 Çok pahalı'],
            ['wrong_destination', '🗺️ Yanlış destinasyon'],
            ['wrong_vibe', '🎭 Tarz uymuyor'],
            ['other', '🤷 Diğer']
        ];

        async function submitRejection(cardWrap, tourId, reason, logId) {
            try {
                var csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                var res = await fetch('/yapay-zeka-arama/' + logId + '/reddet', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ tour_id: tourId, reason: reason })
                });
                if (!res.ok) {
                    console.warn('Reject failed', res.status);
                    return;
                }
                cardWrap.style.opacity = '0';
                cardWrap.style.transform = 'scale(0.9)';
                setTimeout(function () { cardWrap.remove(); }, 350);
            } catch (err) {
                console.error('Reject error', err);
            }
        }

        function buildRejectControl(cardWrap, tourId, logId, T) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.title = 'Bu öneri uymaz';
            btn.textContent = '✕';
            btn.style.cssText = T.rejectBtn;

            var popup = document.createElement('div');
            popup.style.cssText = T.popup;

            REJECT_REASONS.forEach(function (pair) {
                var opt = document.createElement('button');
                opt.type = 'button';
                opt.textContent = pair[1];
                opt.style.cssText = T.popupOpt;
                opt.onmouseenter = function () { this.style.background = T.popupOptHover; };
                opt.onmouseleave = function () { this.style.background = 'transparent'; };
                opt.onclick = function (e) {
                    e.stopPropagation();
                    e.preventDefault();
                    submitRejection(cardWrap, tourId, pair[0], logId);
                };
                popup.appendChild(opt);
            });

            btn.onclick = function (e) {
                e.stopPropagation();
                e.preventDefault();
                popup.style.display = popup.style.display === 'flex' ? 'none' : 'flex';
            };

            var holder = document.createElement('div');
            holder.appendChild(btn);
            holder.appendChild(popup);
            return holder;
        }

        // Tek kart: her alan textContent ile yazılır (XSS güvenli); tema dışında
        // iki yüzeyde birebir aynı yapı ve tek CTA formatı ("İncele →").
        function build(tour, opts) {
            opts = opts || {};
            var T = THEMES[opts.theme] || THEMES.dark;
            var index = opts.index || 0;
            var logId = opts.logId || null;

            var title = tour.title || 'Tur';
            var destination = tour.destination || 'Dünya';
            var price = tour.price ? new Intl.NumberFormat('tr-TR').format(tour.price) : '0';
            var cur = String(tour.currency || 'TRY').toUpperCase();
            var duration = tour.duration_days ? tour.duration_days + ' Gün' : '';
            var rank = (typeof tour.rank === 'number') ? tour.rank : (index + 1);
            var detailUrl = tour.url
                || (tour.id ? ('/turlar/' + tour.id) : '')
                || (tour.slug ? ('/turlar/' + tour.slug) : '/turlar');

            var wrapper = document.createElement('div');
            if (T.cardClass) wrapper.className = T.cardClass;
            wrapper.style.cssText = T.card;
            if (tour.id != null) wrapper.dataset.tourId = String(tour.id);
            wrapper.onmouseenter = function () { this.style.transform = T.hoverLift; this.style.boxShadow = T.hoverShadow; };
            wrapper.onmouseleave = function () { this.style.transform = ''; this.style.boxShadow = ''; };

            var link = document.createElement('a');
            try {
                var u = new URL(detailUrl, window.location.origin);
                if (logId) u.searchParams.set('ai_log_id', String(logId));
                u.searchParams.set('ai_rank', String(rank));
                link.href = u.pathname + u.search;
            } catch (e) { link.href = detailUrl; }
            link.style.cssText = T.link;

            // Görsel: yoksa/kırıksa nötr yer tutucu — rastgele stok foto tur verisi
            // değildir, yanlış izlenim vermesin
            var imgBox = document.createElement('div');
            imgBox.style.cssText = T.imgBox;
            var ph = document.createElement('div');
            ph.style.cssText = 'position:absolute; inset:0; display:flex; align-items:center; justify-content:center; font-size:26px; background:' + T.imgFallback + ';';
            ph.textContent = '🌍';
            imgBox.appendChild(ph);
            if (tour.image) {
                var img = document.createElement('img');
                img.src = tour.image;
                img.alt = title;
                img.loading = 'lazy';
                img.style.cssText = 'position:absolute; inset:0; width:100%; height:100%; object-fit:cover; display:block;';
                img.onerror = function () { this.remove(); };
                imgBox.appendChild(img);
            }

            // Uyum rozeti: skor gerçekten varsa göster — yoksa uydurma yüzde yazılmaz
            var rawScore = (typeof tour.compatibility_score === 'number') ? tour.compatibility_score
                : (typeof tour.similarity === 'number' ? tour.similarity : null);
            if (rawScore != null) {
                var badge = document.createElement('div');
                badge.style.cssText = T.badge;
                badge.textContent = '%' + Math.round(Math.max(0, Math.min(1, rawScore)) * 100) + ' Uyumlu';
                imgBox.appendChild(badge);
            }

            if (T.gradient) {
                var gradient = document.createElement('div');
                gradient.style.cssText = 'position:absolute; bottom:0; left:0; width:100%; height:50%; background:' + T.gradient + '; pointer-events:none;';
                imgBox.appendChild(gradient);
            }
            link.appendChild(imgBox);

            var content = document.createElement('div');
            if (T.cardClass) content.className = 'ai-tour-card-content';
            content.style.cssText = T.content;

            var dest = document.createElement('div');
            dest.style.cssText = T.dest;
            dest.textContent = destination + (duration ? ' • ' + duration : '');
            content.appendChild(dest);

            var titleEl = document.createElement('div');
            titleEl.style.cssText = T.title;
            titleEl.textContent = title;
            content.appendChild(titleEl);

            if (tour.agency_name) {
                var agencyEl = document.createElement('div');
                agencyEl.style.cssText = T.agency;
                agencyEl.textContent = tour.agency_name;
                content.appendChild(agencyEl);
            }

            // "Neden bu tur?" — kişiye özel tek cümle gerekçe (sunucudan deterministik)
            if (tour.reason) {
                var reasonEl = document.createElement('div');
                reasonEl.style.cssText = T.reason;
                reasonEl.textContent = '✨ ' + tour.reason;
                content.appendChild(reasonEl);
            }

            if (tour.next_departure) {
                var nextEl = document.createElement('div');
                nextEl.style.cssText = T.nextDep;
                nextEl.textContent = '📅 En yakın kalkış: ' + tour.next_departure;
                content.appendChild(nextEl);
            }

            // Bütçe çipleri: bütçe üstü uyarısı + başka tarihte bütçeye giren fiyat
            if (tour.over_budget || (tour.flex_date && tour.flex_date.date)) {
                var chips = document.createElement('div');
                chips.style.cssText = T.chipRow;
                if (tour.over_budget) {
                    var over = document.createElement('div');
                    over.style.cssText = T.chipOver;
                    over.textContent = 'bütçe üstü';
                    chips.appendChild(over);
                }
                if (tour.flex_date && tour.flex_date.date) {
                    var flex = document.createElement('div');
                    flex.style.cssText = T.chipFlex;
                    flex.textContent = '🟢 ' + tour.flex_date.date + ' — ' + tour.flex_date.price + ' bütçende';
                    chips.appendChild(flex);
                }
                content.appendChild(chips);
            }

            var bottom = document.createElement('div');
            if (T.cardClass) bottom.className = 'ai-tour-card-bottom';
            bottom.style.cssText = T.bottom;

            var priceEl = document.createElement('div');
            priceEl.style.cssText = T.price;
            priceEl.textContent = price + ' ' + (CURRENCY[cur] || cur);
            bottom.appendChild(priceEl);

            var btn = document.createElement('div');
            btn.style.cssText = T.cta;
            btn.textContent = 'İncele →';
            bottom.appendChild(btn);

            content.appendChild(bottom);
            link.appendChild(content);
            wrapper.appendChild(link);

            // Reddet kontrolü yalnız search akışı sonucu (log_id) olan kartlarda
            if (logId && tour.id) {
                wrapper.appendChild(buildRejectControl(wrapper, tour.id, logId, T));
            }

            return wrapper;
        }

        return { build: build };
    })();

    /**
     * turxturQuiz — Tur eşleştirme testi (brif v1, tamamen LLM'siz).
     * 7 tercih sorusu (+zorunlu "fark etmez") + önem sorusu (≤2) + bağlam
     * adımı (sert filtreler) → sunucu eşleştirmesi → ilk 3 tur + gerekçe.
     * Arketip/persona yok; sonuç oturum bazlıdır, kalıcı profile yazılmaz.
     * Tüm içerik textContent ile yazılır (XSS güvenli).
     */
    window.turxturQuiz = (function () {
        let defCache = null;
        let activeCard = null;
        const AYLAR = ['Oca', 'Şub', 'Mar', 'Nis', 'May', 'Haz', 'Tem', 'Ağu', 'Eyl', 'Eki', 'Kas', 'Ara'];

        async function definition() {
            if (defCache) return defCache;
            const res = await fetch('/tatil-karakteri', { headers: { 'Accept': 'application/json' } });
            if (!res.ok) throw new Error('quiz definition ' + res.status);
            defCache = await res.json();
            return defCache;
        }

        function palette(theme) {
            return theme === 'dark' ? {
                card: 'rgba(255,255,255,0.06)', border: 'rgba(255,255,255,0.15)', text: '#fff',
                sub: 'rgba(255,255,255,0.6)', chipBg: 'rgba(255,255,255,0.08)',
                chipSel: 'rgba(45,212,191,0.25)', accent: '#2dd4bf', track: 'rgba(255,255,255,0.15)'
            } : {
                card: '#fff', border: '#e2e8f0', text: '#0f172a', sub: '#64748b',
                chipBg: '#f1f5f9', chipSel: '#ccfbf1', accent: '#0d9488', track: '#e2e8f0'
            };
        }

        function el(tag, styles, text) {
            const node = document.createElement(tag);
            if (styles) node.style.cssText = styles;
            if (text !== undefined) node.textContent = text;
            return node;
        }

        // opts: { container, theme }
        async function open(opts) {
            if (activeCard && activeCard.isConnected) {
                if (opts.container.contains(activeCard)) {
                    activeCard.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    return;
                }
                activeCard.remove();
                activeCard = null;
            }

            const P = palette(opts.theme || 'light');
            const card = el('div', 'border:1px solid ' + P.border + '; border-radius:16px; padding:18px; background:' + P.card + '; color:' + P.text + '; font-size:14px; max-width:540px;');
            card.setAttribute('role', 'group');
            card.setAttribute('aria-label', 'Tur eşleştirme testi');
            activeCard = card;
            opts.container.appendChild(card);
            card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

            let def;
            try {
                def = await definition();
            } catch (e) {
                activeCard = null;
                card.appendChild(el('div', 'color:' + P.sub + ';', 'Test şu an yüklenemedi — birazdan tekrar dener misin?'));
                const retry = el('button', 'margin-top:8px; border:1px solid ' + P.border + '; background:' + P.chipBg + '; color:' + P.text + '; border-radius:999px; padding:6px 14px; cursor:pointer; font-size:12px;', 'Tekrar dene');
                retry.type = 'button';
                retry.onclick = () => { card.remove(); open(opts); };
                card.appendChild(retry);
                return;
            }

            const prefs = def.tercih_sorulari;
            const onem = def.onem_sorusu;
            const answers = { [onem.key]: [] };
            const baglam = { aylar: [], gun: null, butce: null, kalkis: '', eris: null };
            const totalSteps = prefs.length + 2; // + önem + bağlam
            let stepIdx = 0;

            function chipStyle(on) {
                return 'border-radius:999px; padding:8px 14px; font-size:13px; cursor:pointer; border:1px solid ' + (on ? P.accent : P.border) + '; background:' + (on ? P.chipSel : P.chipBg) + '; color:' + P.text + ';';
            }

            function header(label) {
                const wrap = el('div');
                const row = el('div', 'display:flex; justify-content:space-between; font-size:11px; color:' + P.sub + '; margin-bottom:4px;');
                row.appendChild(el('span', '', label));
                row.appendChild(el('span', '', '🧭 Tur Eşleştirme'));
                wrap.appendChild(row);
                const bar = el('div', 'height:4px; border-radius:2px; background:' + P.track + '; margin-bottom:14px; overflow:hidden;');
                bar.appendChild(el('div', 'height:100%; width:' + Math.round((Math.min(stepIdx, totalSteps) / totalSteps) * 100) + '%; background:' + P.accent + '; border-radius:2px;'));
                wrap.appendChild(bar);
                return wrap;
            }

            function navRow(nextEnabled, onNext, nextLabel) {
                const row = el('div', 'display:flex; justify-content:space-between; align-items:center; margin-top:14px;');
                const back = el('button', 'background:none; border:none; color:' + P.sub + '; cursor:pointer; font-size:12px; padding:4px;', '← Geri');
                back.type = 'button';
                back.style.visibility = stepIdx === 0 ? 'hidden' : 'visible';
                back.onclick = () => { stepIdx = Math.max(0, stepIdx - 1); render(); };
                const next = el('button', 'border:1px solid ' + P.border + '; background:' + (nextEnabled ? P.accent : P.chipBg) + '; color:' + (nextEnabled ? '#0f172a' : P.sub) + '; border-radius:999px; padding:8px 18px; font-size:13px; font-weight:600; cursor:pointer;', nextLabel || 'Devam →');
                next.type = 'button';
                next.disabled = !nextEnabled;
                next.onclick = onNext;
                row.appendChild(back);
                row.appendChild(next);
                return row;
            }

            function render() {
                card.replaceChildren();
                if (stepIdx < prefs.length) return renderPref(prefs[stepIdx]);
                if (stepIdx === prefs.length) return renderImportance();
                return renderContext();
            }

            function renderPref(soru) {
                card.appendChild(header('Soru ' + (stepIdx + 1) + '/' + totalSteps));
                card.appendChild(el('div', 'font-weight:600; margin-bottom:10px;', soru.soru));
                const wrap = el('div', 'display:flex; flex-wrap:wrap; gap:8px;');
                soru.secenekler.forEach((opt, i) => {
                    const chip = el('button', chipStyle(answers[soru.key] === i), opt.metin);
                    chip.type = 'button';
                    chip.setAttribute('aria-pressed', answers[soru.key] === i ? 'true' : 'false');
                    chip.onclick = () => { answers[soru.key] = i; stepIdx++; render(); };
                    wrap.appendChild(chip);
                });
                card.appendChild(wrap);
                card.appendChild(navRow(Number.isInteger(answers[soru.key]), () => { stepIdx++; render(); }));
            }

            function renderImportance() {
                card.appendChild(header('Soru ' + (stepIdx + 1) + '/' + totalSteps));
                card.appendChild(el('div', 'font-weight:600; margin-bottom:2px;', onem.soru));
                card.appendChild(el('div', 'font-size:12px; color:' + P.sub + '; margin-bottom:10px;', 'En fazla ' + onem.max_secim + ' seçim — bunların ağırlığı artar.'));
                const secili = answers[onem.key];
                const wrap = el('div', 'display:flex; flex-wrap:wrap; gap:8px;');
                const uyari = el('div', 'font-size:12px; color:' + P.sub + '; margin-top:8px; min-height:16px;', '');
                onem.secenekler.forEach((opt, i) => {
                    const secildi = secili.includes(i);
                    const chip = el('button', chipStyle(secildi), opt.metin);
                    chip.type = 'button';
                    chip.setAttribute('aria-pressed', secildi ? 'true' : 'false');
                    chip.onclick = () => {
                        const ix = secili.indexOf(i);
                        if (ix >= 0) {
                            secili.splice(ix, 1);
                        } else if (secili.length < onem.max_secim) {
                            secili.push(i);
                        } else {
                            // Sessizce yutma: sınıra gelindiğini kullanıcıya söyle
                            uyari.textContent = 'En fazla ' + onem.max_secim + ' seçebilirsin — birini kaldırıp tekrar dene.';
                            return;
                        }
                        render();
                    };
                    wrap.appendChild(chip);
                });
                card.appendChild(wrap);
                card.appendChild(uyari);
                card.appendChild(navRow(true, () => { stepIdx++; render(); }));
            }

            function renderContext() {
                card.appendChild(header('Son adım'));
                card.appendChild(el('div', 'font-weight:600; margin-bottom:2px;', 'Pratik detaylar'));
                card.appendChild(el('div', 'font-size:12px; color:' + P.sub + '; margin-bottom:10px;', 'Bunlar filtre olarak kullanılır — boş bırakabilirsin.'));

                const section = (label) => card.appendChild(el('div', 'font-size:12px; font-weight:600; color:' + P.sub + '; margin:10px 0 6px;', label));

                section('Hangi ay(lar)?');
                const ayWrap = el('div', 'display:flex; flex-wrap:wrap; gap:6px;');
                AYLAR.forEach((ay, i) => {
                    const on = baglam.aylar.includes(i + 1);
                    const chip = el('button', chipStyle(on) + ' padding:6px 10px; font-size:12px;', ay);
                    chip.type = 'button';
                    chip.onclick = () => {
                        const ix = baglam.aylar.indexOf(i + 1);
                        if (ix >= 0) baglam.aylar.splice(ix, 1); else baglam.aylar.push(i + 1);
                        render();
                    };
                    ayWrap.appendChild(chip);
                });
                card.appendChild(ayWrap);

                const alanlar = def.baglam_sorulari.alanlar;
                const gunAlan = alanlar.find(a => a.key === 'gun_araligi');
                if (gunAlan) {
                    section(gunAlan.soru);
                    const wrap = el('div', 'display:flex; flex-wrap:wrap; gap:6px;');
                    gunAlan.secenekler.forEach((opt, i) => {
                        const chip = el('button', chipStyle(baglam.gun === i) + ' font-size:12px;', opt.metin);
                        chip.type = 'button';
                        chip.onclick = () => { baglam.gun = baglam.gun === i ? null : i; render(); };
                        wrap.appendChild(chip);
                    });
                    card.appendChild(wrap);
                }

                const butceAlan = alanlar.find(a => a.key === 'butce_bandi');
                if (butceAlan) {
                    section(butceAlan.soru);
                    const wrap = el('div', 'display:flex; flex-wrap:wrap; gap:6px;');
                    butceAlan.secenekler.forEach((opt, i) => {
                        const chip = el('button', chipStyle(baglam.butce === i) + ' font-size:12px;', opt.metin);
                        chip.type = 'button';
                        chip.onclick = () => { baglam.butce = baglam.butce === i ? null : i; render(); };
                        wrap.appendChild(chip);
                    });
                    card.appendChild(wrap);
                }

                section('Nereden kalkacaksın? (opsiyonel)');
                const kalkis = document.createElement('input');
                kalkis.type = 'text';
                kalkis.value = baglam.kalkis;
                kalkis.placeholder = 'İstanbul, Ankara…';
                kalkis.style.cssText = 'width:100%; border:1px solid ' + P.border + '; background:' + P.chipBg + '; color:' + P.text + '; border-radius:10px; padding:8px 12px; font-size:13px; font-family:inherit; outline:none;';
                kalkis.setAttribute('aria-label', 'Kalkış şehri');
                kalkis.oninput = () => { baglam.kalkis = kalkis.value; };
                card.appendChild(kalkis);

                card.appendChild(navRow(true, submit, 'Turları eşleştir ✨'));
            }

            async function submit() {
                card.replaceChildren();
                card.appendChild(header('Eşleştiriliyor'));
                card.appendChild(el('div', 'color:' + P.sub + ';', 'Cevapların turlarla karşılaştırılıyor…'));

                const alanlar = def.baglam_sorulari.alanlar;
                const gunSec = baglam.gun !== null ? alanlar.find(a => a.key === 'gun_araligi')?.secenekler[baglam.gun] : null;
                const butceSec = baglam.butce !== null ? alanlar.find(a => a.key === 'butce_bandi')?.secenekler[baglam.butce] : null;

                const payload = {
                    answers: answers,
                    baglam: {
                        aylar: baglam.aylar,
                        gun_min: gunSec?.min ?? null,
                        gun_max: gunSec?.max ?? null,
                        butce_max_try: butceSec?.max_try ?? null,
                        kalkis_sehri: baglam.kalkis.trim() || null,
                    },
                };

                try {
                    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                    const res = await fetch('/tatil-karakteri', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                        body: JSON.stringify(payload),
                    });
                    if (!res.ok) throw new Error('quiz submit ' + res.status);
                    renderResults(await res.json());
                } catch (e) {
                    card.replaceChildren();
                    card.appendChild(header('Hata'));
                    const cokIstek = String(e && e.message || '').includes('429');
                    card.appendChild(el('div', 'color:' + P.sub + ';', cokIstek
                        ? 'Çok fazla istek gönderildi — bir dakika sonra tekrar dener misin?'
                        : 'Eşleştirme yapılamadı — tekrar dener misin?'));
                    const satir = el('div', 'display:flex; gap:8px; align-items:center; margin-top:10px;');
                    const geri = el('button', 'background:none; border:none; color:' + P.sub + '; cursor:pointer; font-size:12px; padding:4px;', '← Cevaplara dön');
                    geri.type = 'button';
                    // Cevaplar korunur: son adıma dönülür, hiçbir şey kaybolmaz
                    geri.onclick = () => { stepIdx = totalSteps - 1; render(); };
                    const retry = el('button', 'border:1px solid ' + P.border + '; background:' + P.chipBg + '; color:' + P.text + '; border-radius:999px; padding:6px 14px; cursor:pointer; font-size:12px;', 'Tekrar dene');
                    retry.type = 'button';
                    retry.onclick = submit;
                    satir.appendChild(geri);
                    satir.appendChild(retry);
                    card.appendChild(satir);
                }
            }

            function renderResults(res) {
                activeCard = null;
                card.remove();

                const tourList = res.tours || [];
                const bubble = el('div', 'border:1px solid ' + P.border + '; border-radius:16px; padding:14px 16px; background:' + P.card + '; color:' + P.text + '; font-size:14px; max-width:540px;');
                // Boş sonuçta sunucunun olumlu mesajı çelişkili olurdu
                bubble.appendChild(el('div', 'font-weight:600;', tourList.length
                    ? (res.message || 'Eşleşmeler hazır 🧭')
                    : 'Filtrelerine uyan puanlanmış tur bulamadım.'));
                (res.relaxation_notes || []).forEach(n => {
                    bubble.appendChild(el('div', 'font-size:12px; color:' + P.sub + '; margin-top:4px;', 'ℹ️ ' + n));
                });
                opts.container.appendChild(bubble);

                const tours = tourList;
                if (tours.length && window.turxturAiCard) {
                    const row = el('div', 'display:flex; gap:10px; overflow-x:auto; padding:10px 2px; max-width:100%;');
                    tours.forEach((t, i) => {
                        try { row.appendChild(window.turxturAiCard.build(t, { theme: opts.theme || 'light', index: i })); } catch (e) {}
                    });
                    opts.container.appendChild(row);
                } else if (!tours.length) {
                    bubble.appendChild(el('div', 'font-size:12px; color:' + P.sub + '; margin-top:6px;', 'Şu an filtrelerine uyan puanlanmış tur yok — sohbete ne aradığını yazarsan birlikte bakalım.'));
                }
                bubble.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }

            render();
        }

        return { open: open };
    })();
    </script>

    @php
        // ❄️ Sohbet asistanı dondurulmuş durumda (config/ai.php: chat_enabled)
        $hideAiChat = ! config('ai.chat_enabled')
            || request()->is('admin*')
            || request()->is('super-admin*')
            || request()->is('superadmin*')
            || request()->is('acenta*')
            || request()->routeIs('agency.*')
            || (auth()->check() && in_array(auth()->user()->role, ['admin', 'super_admin', 'superadmin'], true));
    @endphp

    @if(!$hideAiChat)
    {{-- AI Chatbot Trigger & Window (PREMIUM V2 - DARK GLASS) --}}
    <div id="ai-chat-container" style="position:fixed; bottom:24px; right:24px; z-index:2000; font-family:var(--font); width:max-content; max-width:calc(100vw - 48px);">
        {{-- Floating Glass Bar --}}
        <div id="ai-chat-trigger" onclick="toggleAIChat()" role="button" tabindex="0" aria-label="AI tatil asistanını aç" onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();toggleAIChat();}" style="background:rgba(15,23,42,0.85); backdrop-filter:blur(20px); -webkit-backdrop-filter:blur(20px); color:white; padding:10px 20px; border-radius:100px; cursor:pointer; display:flex; align-items:center; gap:12px; box-shadow:0 10px 40px rgba(0,0,0,0.25); border:1px solid rgba(255,255,255,0.15); transition:all 0.4s cubic-bezier(0.165, 0.84, 0.44, 1); white-space:nowrap; flex-wrap:nowrap; user-select:none;">
            <div style="width:32px; height:32px; background:linear-gradient(135deg, #0d9488, #2dd4bf); border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:18px; box-shadow:0 4px 12px rgba(13,148,136,0.3);">🤖</div>
            <div style="font-size:13px; font-weight:700; letter-spacing:-0.2px; color:#f1f5f9;">Hayalindeki tatili anlat...</div>
        </div>

        {{-- Premium Chat Window (Dark Glass) --}}
        <div id="ai-chat-window" role="dialog" aria-modal="true" aria-label="turXtur AI tatil asistanı" style="display:none; position:absolute; bottom:70px; right:0; width:min(400px, 90vw); height:600px; background:rgba(15,23,42,0.75); backdrop-filter:blur(25px); -webkit-backdrop-filter:blur(25px); border-radius:24px; box-shadow:0 30px 60px -12px rgba(0,0,0,0.4); border:1px solid rgba(255,255,255,0.15); overflow:hidden; flex-direction:column; animation:premiumSlideIn 0.5s cubic-bezier(0.19, 1, 0.22, 1);">
            {{-- Header --}}
            <div style="border-bottom:1px solid rgba(255,255,255,0.1); color:white; padding:20px 24px; display:flex; justify-content:space-between; align-items:center; position:relative;">
                <div style="display:flex; align-items:center; gap:12px;">
                    <div style="width:40px; height:40px; background:rgba(255,255,255,0.1); border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:22px; border:1px solid rgba(255,255,255,0.05);">🤖</div>
                    <div>
                        <div style="font-size:16px; font-weight:800; letter-spacing:-0.4px;">turXtur AI</div>
                        <div style="display:flex; align-items:center; gap:5px; font-size:11px; font-weight:600; color:#34d399; margin-top:2px;">
                            <span style="width:6px; height:6px; background:#34d399; border-radius:50%; display:inline-block; animation:pulse 2s infinite;"></span>
                            Sana özel tatil asistanı
                        </div>
                    </div>
                </div>
                <div style="display:flex; gap:6px;">
                    <button onclick="resetAIChat()" title="Yeni konuşma" style="background:rgba(255,255,255,0.1); border:none; color:white; width:32px; height:32px; border-radius:50%; cursor:pointer; display:flex; align-items:center; justify-content:center; font-size:18px; transition:background 0.2s;">↻</button>
                    <button onclick="toggleAIChat()" style="background:rgba(255,255,255,0.1); border:none; color:white; width:32px; height:32px; border-radius:50%; cursor:pointer; display:flex; align-items:center; justify-content:center; font-size:18px; transition:background 0.2s;">&times;</button>
                </div>
            </div>
            
            {{-- Messages Area --}}
            <div id="ai-chat-messages" aria-live="polite" aria-atomic="false" style="flex:1; min-height:0; overflow-y:auto; padding:24px; display:flex; flex-direction:column; gap:16px; align-items:stretch;">
                <div class="ai-msg-ai">
                    Selam! 👋 Ben senin kişisel tatil uzmanıyım. <span style="font-weight:700; color:#2dd4bf;">Kültür, vize, bütçe</span> veya sadece <span style="font-weight:700; color:#2dd4bf;">hayallerindeki doğayı</span> anlat, senin için en iyi turu saniyeler içinde bulayım!
                </div>
                
                {{-- Suggestion Chips --}}
                <div style="display:flex; flex-wrap:wrap; gap:8px; margin-top:4px;">
                    <button onclick="openTurxturQuizWidget()" class="suggestion-chip" style="border-color:rgba(45,212,191,0.5);">🧭 Tur Eşleştirme Testi</button>
                    <button onclick="setSuggestion('Vizesiz en iyi yurt dışı turları hangileri?')" class="suggestion-chip">🌍 Vizesiz Turlar</button>
                    <button onclick="setSuggestion('20.000 TL bütçe ile tatil önerisi.')" class="suggestion-chip">💰 Bütçe Dostu</button>
                    <button onclick="setSuggestion('Huzurlu bir doğa ve deniz tatili istiyorum.')" class="suggestion-chip">🌿 Doğa & Deniz</button>
                    <button onclick="setSuggestion('Bana en lüks gemi turlarını göster.')" class="suggestion-chip">🚢 Gemi Turları</button>
                </div>
            </div>

            {{-- Input Area --}}
            <div style="padding:16px; border-top:1px solid rgba(255,255,255,0.1); background:rgba(0,0,0,0.2);">
                <div style="display:flex; gap:10px; background:rgba(255,255,255,0.05); padding:6px; border-radius:18px; border:1px solid rgba(255,255,255,0.1);">
                    <input type="text" id="ai-chat-input" placeholder="Hayalindeki rotayı tarif et..." style="flex:1; border:none; padding:10px 15px; font-size:14px; outline:none; font-family:var(--font); background:transparent; color:white;">
                    <button onclick="sendAIChatMessage()" style="background:linear-gradient(135deg, #0d9488, #2dd4bf); color:white; border:none; width:42px; height:42px; border-radius:14px; cursor:pointer; display:flex; align-items:center; justify-content:center; box-shadow:0 8px 16px rgba(13,148,136,0.25); transition:all 0.3s; flex-shrink:0;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
                    </button>
                </div>
                <div style="font-size:10px; text-align:center; color:rgba(255,255,255,0.4); margin-top:10px; font-weight:600; letter-spacing:0.3px;">Powered by turXtur AI Engine</div>
            </div>
        </div>
    </div>

    <style>
        @keyframes premiumSlideIn { from { transform: translateY(40px) scale(0.95); opacity: 0; } to { transform: translateY(0) scale(1); opacity: 1; } }
        @keyframes pulse { 0% { opacity: 0.6; } 50% { opacity: 1; } 100% { opacity: 0.6; } }
        @keyframes messageSlide { from { transform: translateY(10px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        
        #ai-chat-input::placeholder { color: rgba(255,255,255,0.4); }
        
        .suggestion-chip { background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.15); padding:8px 14px; border-radius:100px; font-size:12px; font-weight:600; color:#e2e8f0; cursor:pointer; transition:all 0.2s; white-space:nowrap; }
        .suggestion-chip:hover { background:rgba(45,212,191,0.2); border-color:#2dd4bf; color:white; transform:translateY(-1px); }
        
        #ai-chat-trigger:hover { transform: scale(1.02) translateY(-2px); box-shadow:0 15px 50px rgba(0,0,0,0.3); }
        
        #ai-chat-messages > * { flex-shrink:0; }
        .ai-msg-ai { background:rgba(255,255,255,0.1); padding:16px 20px; border-radius:20px 20px 20px 6px; font-size:14px; color:#f8fafc; max-width:85%; border:1px solid rgba(255,255,255,0.05); animation:messageSlide 0.3s ease; line-height:1.5; }
        .ai-msg-user { background:linear-gradient(135deg, #0d9488, #2dd4bf) !important; color:white !important; border-radius:20px 20px 6px 20px !important; align-self:flex-end !important; padding:14px 18px !important; font-size:14px !important; max-width:85% !important; box-shadow:0 8px 20px rgba(13,148,136,0.2) !important; animation:messageSlide 0.3s ease; line-height:1.5; }

        .ai-tour-carousel { flex:0 0 auto; min-height:252px; }
        .ai-tour-card { background:rgba(0,0,0,0.25); border-radius:16px; border:1px solid rgba(255,255,255,0.12); transition:transform 0.3s, box-shadow 0.3s; flex:0 0 220px; min-width:220px; min-height:252px; overflow:hidden; }
        .ai-tour-card:hover { transform:translateY(-4px); box-shadow:0 20px 40px rgba(0,0,0,0.3); border-color:rgba(255,255,255,0.2); }
        .ai-tour-card > a { min-height:252px; display:flex !important; flex-direction:column; text-decoration:none; color:white; }
        .ai-tour-card-content { padding:14px; display:flex; flex-direction:column; flex:1; }
        .ai-tour-card-bottom { margin-top:auto; display:flex; justify-content:space-between; align-items:center; padding-top:10px; border-top:1px solid rgba(255,255,255,0.1); }
        
        /* Custom scrollbar for chat */
        #ai-chat-messages::-webkit-scrollbar { width: 6px; }
        #ai-chat-messages::-webkit-scrollbar-track { background: transparent; }
        #ai-chat-messages::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.2); border-radius: 10px; }
        
        .ai-tour-carousel::-webkit-scrollbar { height: 4px; }
        .ai-tour-carousel::-webkit-scrollbar-track { background: transparent; }
        .ai-tour-carousel::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.15); border-radius: 10px; }
        #ai-chat-messages::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.3); }
    </style>

    <script>
        // --- AI Chatbot Core ---
        let isDragging = false;
        let dragStartX, dragStartY;

        function positionAIChatWindow() {
            const container = document.getElementById('ai-chat-container');
            const windowObj = document.getElementById('ai-chat-window');
            if (!container || !windowObj) return;

            const margin = 12;
            const gap = 10;

            // Keep panel fully visible on short screens
            windowObj.style.maxHeight = Math.max(320, window.innerHeight - (margin * 2)) + 'px';

            const wasHidden = (windowObj.style.display === 'none' || getComputedStyle(windowObj).display === 'none');
            if (wasHidden) {
                windowObj.style.display = 'flex';
                windowObj.style.visibility = 'hidden';
            }

            // Reset anchors before measuring / placing
            windowObj.style.top = 'auto';
            windowObj.style.bottom = 'auto';
            windowObj.style.left = 'auto';
            windowObj.style.right = 'auto';

            const rect = container.getBoundingClientRect();
            const panelWidth = windowObj.offsetWidth;
            const panelHeight = windowObj.offsetHeight;

            // Horizontal: keep panel inside viewport (prefer right-aligned to trigger)
            const desiredLeft = rect.right - panelWidth;
            const clampedLeft = Math.min(
                Math.max(desiredLeft, margin),
                window.innerWidth - panelWidth - margin
            );
            windowObj.style.left = (clampedLeft - rect.left) + 'px';

            // Vertical: prefer above trigger; if not possible, place below; always clamp in viewport
            const topIfAbove = rect.top - panelHeight - gap;
            const topIfBelow = rect.bottom + gap;
            let panelTop = topIfAbove;

            if (topIfAbove < margin && topIfBelow + panelHeight <= window.innerHeight - margin) {
                panelTop = topIfBelow;
            }

            panelTop = Math.min(
                Math.max(panelTop, margin),
                window.innerHeight - panelHeight - margin
            );
            windowObj.style.top = (panelTop - rect.top) + 'px';

            if (wasHidden) {
                windowObj.style.display = 'none';
                windowObj.style.visibility = '';
            }
        }

        // Mobil tam ekran chat: klavye açılınca pencereyi görsel viewport'a bağla,
        // input klavyenin arkasında kalmasın (visualViewport, iOS Safari + Android).
        function _aiSyncViewport() {
            const w = document.getElementById('ai-chat-window');
            if (!w || w.style.display === 'none' || window.innerWidth > 768 || !window.visualViewport) return;
            w.style.height = window.visualViewport.height + 'px';
        }

        // Escape ile kapat + odağı tetikleyiciye iade (dialog erişilebilirliği)
        function _aiEscHandler(e) {
            if (e.key === 'Escape') {
                const w = document.getElementById('ai-chat-window');
                if (w && w.style.display !== 'none') { toggleAIChat(); }
            }
        }

        function toggleAIChat() {
            if (isDragging) return;
            const windowObj = document.getElementById('ai-chat-window');
            const trigger = document.getElementById('ai-chat-trigger');
            if (windowObj.style.display === 'none') {
                windowObj.style.display = 'flex';
                windowObj.style.visibility = 'hidden';
                positionAIChatWindow();
                windowObj.style.visibility = 'visible';
                trigger.style.opacity = '0';
                trigger.style.pointerEvents = 'none';
                // Mobilde pencere tam ekran: arka plan kaymasın
                if (window.innerWidth <= 768) document.body.style.overflow = 'hidden';
                document.getElementById('ai-chat-input').focus();
                document.addEventListener('keydown', _aiEscHandler);
                if (window.visualViewport) {
                    window.visualViewport.addEventListener('resize', _aiSyncViewport);
                    _aiSyncViewport();
                }
            } else {
                windowObj.style.display = 'none';
                windowObj.style.height = ''; // viewport override'ını temizle
                trigger.style.opacity = '1';
                trigger.style.pointerEvents = 'auto';
                document.body.style.overflow = '';
                document.removeEventListener('keydown', _aiEscHandler);
                if (window.visualViewport) window.visualViewport.removeEventListener('resize', _aiSyncViewport);
                trigger.focus();
            }
        }

        function setSuggestion(text) {
            document.getElementById('ai-chat-input').value = text;
            sendAIChatMessage();
        }

        function resetAIChat() {
            // Devam eden akışı iptal et — yoksa eski stream'in yanıt/tur kartları
            // temizlenmiş konuşmaya geri akmaya devam eder.
            if (window._aiWidgetController) { try { window._aiWidgetController.abort(); } catch (e) {} }
            if (window._aiWidgetWatchdog) { clearTimeout(window._aiWidgetWatchdog); window._aiWidgetWatchdog = null; }
            window._aiWidgetSending = false;
            try { localStorage.removeItem('turxtur_ai_conv'); } catch (e) {}
            const messages = document.getElementById('ai-chat-messages');
            if (messages) {
                // İlk welcome mesajı ve suggestion chips'i bırak, geri kalanı temizle
                while (messages.children.length > 2) {
                    messages.removeChild(messages.lastChild);
                }
            }
        }

        // Tur eşleştirme testi (brif v1): sonuçlar bileşenin içinde LLM'siz
        // render edilir — konuşma/takip sorgusu bağlantısı yok, oturum bazlı.
        function openTurxturQuizWidget() {
            // Akış sürerken kart mesaj listesinin ortasına girmesin
            if (window._aiWidgetSending) return;
            const messages = document.getElementById('ai-chat-messages');
            window.turxturQuiz.open({ container: messages, theme: 'dark' });
            messages.scrollTop = messages.scrollHeight;
        }

        async function sendAIChatMessage() {
            const input = document.getElementById('ai-chat-input');
            const messages = document.getElementById('ai-chat-messages');
            const text = input.value.trim();
            if (!text) return;
            if (window._aiWidgetSending) return; // çift gönderim koruması
            window._aiWidgetSending = true;

            // Watchdog: akış askıda kalırsa (sunucu/ağ) kilit sonsuza dek açık
            // kalmasın. Her SSE olayında sıfırlanır; 75 sn sessizlikte iptal eder.
            const controller = new AbortController();
            window._aiWidgetController = controller;
            const WATCHDOG_MS = 75000;
            const armWatchdog = () => {
                if (window._aiWidgetWatchdog) clearTimeout(window._aiWidgetWatchdog);
                window._aiWidgetWatchdog = setTimeout(() => { try { controller.abort(); } catch (e) {} }, WATCHDOG_MS);
            };
            armWatchdog();

            // 1. User Message
            const userMsg = document.createElement('div');
            userMsg.className = "ai-msg-user";
            userMsg.innerText = text;
            messages.appendChild(userMsg);
            input.value = '';
            messages.scrollTop = messages.scrollHeight;

            // 2. Premium Loading
            const loadingMsg = document.createElement('div');
            loadingMsg.className = "ai-msg-ai";
            loadingMsg.innerHTML = `<div style="display:flex; gap:5px; align-items:center;"><span style="font-size:13px; font-weight:700;">turXtur AI</span> <div class="dots-load">...</div></div> <div style="font-size:12px; color:var(--text-muted); margin-top:4px;">Senin için seçenekleri analiz ediyorum...</div>`;
            messages.appendChild(loadingMsg);
            messages.scrollTop = messages.scrollHeight;

            try {
                // Multi-turn: conversation_uuid localStorage'da tutulur.
                const STORAGE_KEY = 'turxtur_ai_conv';
                const IDLE_MS = 30 * 60 * 1000;
                let convData = null;
                try {
                    const raw = localStorage.getItem(STORAGE_KEY);
                    if (raw) {
                        const parsed = JSON.parse(raw);
                        if (parsed && parsed.uuid && parsed.lastTs && (Date.now() - parsed.lastTs) < IDLE_MS) {
                            convData = parsed;
                        }
                    }
                } catch (e) {}

                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

                // Streaming endpoint — SSE chunks geldiğinde anında render
                const response = await fetch('/yapay-zeka-arama/mesaj/akis', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'text/event-stream',
                    },
                    body: JSON.stringify({
                        message: text,
                        conversation_uuid: convData?.uuid || null,
                    }),
                    signal: controller.signal,
                });

                if (!response.ok) {
                    throw new Error('Sunucu hatası: HTTP ' + response.status);
                }

                // SSE chunk parser
                const reader = response.body.getReader();
                const decoder = new TextDecoder();
                let buffer = '';

                // State değişkenleri
                let aiMsg = null;
                let contentEl = null;
                let fullContent = '';
                let isClarification = false;
                let loadingRemoved = false;
                let lastLogId = null;

                const scrollBottom = () => { messages.scrollTop = messages.scrollHeight; };

                const removeLoading = () => {
                    if (loadingRemoved) return;
                    if (loadingMsg && loadingMsg.parentNode) loadingMsg.parentNode.removeChild(loadingMsg);
                    loadingRemoved = true;
                };

                const ensureAiMsg = () => {
                    if (aiMsg) return;
                    removeLoading();
                    aiMsg = document.createElement('div');
                    aiMsg.className = "ai-msg-ai";
                    const labelColor = isClarification ? '#fbbf24' : '#2dd4bf';
                    aiMsg.innerHTML = `<div style="font-size:12px; font-weight:800; color:${labelColor}; margin-bottom:4px; text-transform:uppercase; letter-spacing:0.5px;">Zeka Yanıtı</div><div data-ai-content style="white-space:pre-wrap;"></div>`;
                    contentEl = aiMsg.querySelector('[data-ai-content]');
                    messages.appendChild(aiMsg);
                    scrollBottom();
                };

                const handleEvent = (eventName, data) => {
                    if (eventName === 'search') {
                        // Conversation UUID'i sakla
                        if (data.conversation_uuid) {
                            try {
                                localStorage.setItem(STORAGE_KEY, JSON.stringify({
                                    uuid: data.conversation_uuid, lastTs: Date.now(),
                                }));
                            } catch (e) {}
                        }
                    } else if (eventName === 'tours') {
                        // Yeni format: {log_id, items}. Eski: array
                        const tourList = Array.isArray(data) ? data : (data.items || []);
                        if (data && typeof data === 'object' && !Array.isArray(data) && data.log_id) {
                            lastLogId = data.log_id;
                        }
                        // Gevşetme notu: sonuçlar neden birebir eşleşme değil, kullanıcı görsün
                        const relaxNote = (data && !Array.isArray(data)) ? data.relaxation_note : null;
                        if (relaxNote) {
                            removeLoading();
                            const note = document.createElement('div');
                            note.style.cssText = 'font-size:12.5px; color:#fde68a; background:rgba(251,191,36,0.12); border:1px solid rgba(251,191,36,0.35); border-radius:10px; padding:8px 12px;';
                            note.textContent = 'ℹ️ ' + relaxNote;
                            messages.appendChild(note);
                        }
                        if (tourList.length > 0) {
                            removeLoading();
                            const carousel = document.createElement('div');
                            carousel.style.cssText = 'display:flex; flex:0 0 auto; overflow-x:auto; overflow-y:visible; align-items:stretch; gap:10px; padding:4px 0 12px 0; margin-bottom:10px; min-height:252px; scroll-snap-type:x mandatory; -webkit-overflow-scrolling:touch;';
                            carousel.className = 'ai-tour-carousel';
                            tourList.forEach((tour, idx) => {
                                carousel.appendChild(window.turxturAiCard.build(tour, { theme: 'dark', index: idx, logId: lastLogId }));
                            });
                            messages.appendChild(carousel);
                            // 7'den fazla eşleşme varsa tam listeye bağlantı ver
                            if (data && !Array.isArray(data) && data.all_results_url) {
                                const more = document.createElement('a');
                                more.href = data.all_results_url;
                                more.style.cssText = 'display:block;margin:2px 0 10px 0;font-size:13px;font-weight:700;color:#2dd4bf;text-decoration:none;';
                                more.textContent = '→ Eşleşen ' + (data.total_matches || 'tüm') + ' turun tamamını gör';
                                messages.appendChild(more);
                            }
                            requestAnimationFrame(scrollBottom);
                        }
                    } else if (eventName === 'compare') {
                        // Deterministik kıyas tablosu (sayılar sunucudan, LLM üretmedi)
                        removeLoading();
                        const box = document.createElement('div');
                        box.style.cssText = 'overflow-x:auto; background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.12); border-radius:12px; -webkit-overflow-scrolling:touch;';
                        const table = document.createElement('table');
                        table.style.cssText = 'border-collapse:collapse; font-size:12px; min-width:340px; width:100%;';
                        const head = document.createElement('tr');
                        const corner = document.createElement('th');
                        corner.style.cssText = 'padding:9px 10px;';
                        head.appendChild(corner);
                        (data.columns || []).forEach(c => {
                            const th = document.createElement('th');
                            th.style.cssText = 'padding:9px 10px; text-align:left;';
                            const a = document.createElement('a');
                            a.href = c.url;
                            a.textContent = c.title;
                            a.style.cssText = 'color:#2dd4bf; text-decoration:none; font-weight:700;';
                            th.appendChild(a);
                            head.appendChild(th);
                        });
                        table.appendChild(head);
                        (data.rows || []).forEach(row => {
                            const tr = document.createElement('tr');
                            tr.style.cssText = 'border-top:1px solid rgba(255,255,255,0.08);';
                            const td0 = document.createElement('td');
                            td0.style.cssText = 'padding:7px 10px; color:#94a3b8; font-weight:600; white-space:nowrap;';
                            td0.textContent = row.label;
                            tr.appendChild(td0);
                            (row.values || []).forEach(v => {
                                const td = document.createElement('td');
                                td.style.cssText = 'padding:7px 10px; color:#f8fafc;';
                                td.textContent = v;
                                tr.appendChild(td);
                            });
                            table.appendChild(tr);
                        });
                        box.appendChild(table);
                        messages.appendChild(box);
                        scrollBottom();
                    } else if (eventName === 'handoff') {
                        // Acentaya sıcak devir kartı: özet dolu WhatsApp + telefon
                        removeLoading();
                        const card = document.createElement('div');
                        card.style.cssText = 'background:rgba(52,211,153,0.12); border:1px solid rgba(52,211,153,0.35); border-radius:12px; padding:14px; display:flex; flex-wrap:wrap; gap:10px; align-items:center;';
                        const info = document.createElement('div');
                        info.style.cssText = 'font-size:13px; color:#a7f3d0; font-weight:600; flex:1; min-width:160px;';
                        info.textContent = '📞 ' + (data.agency_name || 'Acenta') + (data.tour_title ? ' — "' + data.tour_title + '"' : '');
                        card.appendChild(info);
                        if (data.whatsapp_link) {
                            const wa = document.createElement('a');
                            wa.href = data.whatsapp_link;
                            wa.target = '_blank';
                            wa.rel = 'noopener';
                            wa.style.cssText = 'font-size:12px; font-weight:800; color:#0f172a; background:#34d399; padding:8px 14px; border-radius:100px; text-decoration:none; white-space:nowrap;';
                            wa.textContent = '📱 WhatsApp\'tan yaz';
                            card.appendChild(wa);
                        }
                        if (data.phone_link) {
                            const tel = document.createElement('a');
                            tel.href = data.phone_link;
                            tel.style.cssText = 'font-size:12px; font-weight:800; color:#e2e8f0; border:1px solid rgba(255,255,255,0.25); padding:8px 14px; border-radius:100px; text-decoration:none; white-space:nowrap;';
                            tel.textContent = '📞 Ara';
                            card.appendChild(tel);
                        }
                        messages.appendChild(card);
                        scrollBottom();
                    } else if (eventName === 'suggestions') {
                        // Devam önerisi çipleri — tıklayınca mesaj olarak gönderilir
                        removeLoading();
                        const wrap = document.createElement('div');
                        wrap.style.cssText = 'display:flex; flex-wrap:wrap; gap:8px;';
                        (data.items || []).forEach(t => {
                            const chip = document.createElement('button');
                            chip.type = 'button';
                            chip.className = 'suggestion-chip';
                            chip.textContent = t;
                            chip.onclick = () => {
                                // Akış sürerken kullanıcının yazmakta olduğu taslağı ezme
                                if (window._aiWidgetSending) return;
                                const inputEl = document.getElementById('ai-chat-input');
                                inputEl.value = t;
                                sendAIChatMessage();
                                // Gönderim gerçekten başladıysa input tüketilmiştir;
                                // başlamadıysa çipler yerinde kalsın
                                if (!inputEl.value) wrap.remove();
                            };
                            wrap.appendChild(chip);
                        });
                        if (wrap.children.length) {
                            messages.appendChild(wrap);
                            scrollBottom();
                        }
                    } else if (eventName === 'comment') {
                        ensureAiMsg();
                        if (data.delta) {
                            fullContent += data.delta;
                            contentEl.textContent = (isClarification ? '🤔 ' : '') + fullContent;
                            scrollBottom();
                        }
                    } else if (eventName === 'done') {
                        if (data.is_clarification) isClarification = true;
                        // Netleştirme işareti yalnız 'done'da gelir; comment o an zaten
                        // normal stille render edilmiştir → geriye dönük yeniden stille.
                        if (data.is_clarification && contentEl) {
                            contentEl.textContent = '🤔 ' + fullContent;
                            const label = aiMsg && aiMsg.querySelector('div');
                            if (label) label.style.color = '#fbbf24';
                        }
                        if (data.log_id) lastLogId = data.log_id;
                        if (!aiMsg && !loadingRemoved) {
                            // Sonuç boş + comment yok edge case
                            if (loadingMsg && loadingMsg.parentNode) loadingMsg.parentNode.removeChild(loadingMsg);
                            loadingRemoved = true;
                            const noResult = document.createElement('div');
                            noResult.className = 'ai-msg-ai';
                            noResult.textContent = 'Maalesef kriterlerine net uyan bir tur bulamadım. 😔';
                            messages.appendChild(noResult);
                        }
                        scrollBottom();
                    } else if (eventName === 'error') {
                        if (!loadingRemoved) {
                            if (loadingMsg && loadingMsg.parentNode) loadingMsg.parentNode.removeChild(loadingMsg);
                            loadingRemoved = true;
                        }
                        const errorMsg = document.createElement('div');
                        errorMsg.className = 'ai-msg-ai';
                        errorMsg.textContent = '⚠️ ' + (data.message || 'Bir hata oluştu');
                        messages.appendChild(errorMsg);
                        scrollBottom();
                    }
                };

                // SSE parsing loop
                while (true) {
                    const { done, value } = await reader.read();
                    if (done) break;
                    armWatchdog(); // her veri parçasında watchdog sıfırlanır
                    buffer += decoder.decode(value, { stream: true });

                    let sepIdx;
                    while ((sepIdx = buffer.indexOf('\n\n')) !== -1) {
                        const chunk = buffer.slice(0, sepIdx);
                        buffer = buffer.slice(sepIdx + 2);

                        let eventName = 'message';
                        let dataStr = '';
                        for (const line of chunk.split('\n')) {
                            if (line.startsWith('event:')) eventName = line.slice(6).trim();
                            else if (line.startsWith('data:')) dataStr += line.slice(5).trim();
                        }
                        if (!dataStr) continue;
                        try {
                            handleEvent(eventName, JSON.parse(dataStr));
                        } catch (e) {
                            console.warn('SSE parse error', e, dataStr);
                        }
                    }
                }

                // SESSİZ ÖLÜM koruması: akış hiçbir olay üretmeden bittiyse
                // "analiz ediyorum" balonu sonsuza dek kalmasın
                if (!loadingRemoved) {
                    if (loadingMsg && loadingMsg.parentNode) loadingMsg.parentNode.removeChild(loadingMsg);
                    const silentMsg = document.createElement('div');
                    silentMsg.className = 'ai-msg-ai';
                    silentMsg.textContent = '⚠️ Bağlantı beklenmedik şekilde kesildi — mesajını tekrar gönderebilirsin.';
                    messages.appendChild(silentMsg);
                    messages.scrollTop = messages.scrollHeight;
                    if (!input.value) input.value = text; // mesajı geri koy, elle yazmasın
                }
            } catch (err) {
                console.error('AI Error:', err);
                if (loadingMsg && loadingMsg.parentNode) loadingMsg.parentNode.removeChild(loadingMsg);
                var errorMsg = document.createElement('div');
                errorMsg.className = 'ai-msg-ai';
                if (err && err.name === 'AbortError') {
                    errorMsg.textContent = '⚠️ Yanıt çok uzun sürdü — mesajını tekrar gönderebilirsin.';
                } else if (err && String(err.message || '').includes('429')) {
                    errorMsg.textContent = '⚠️ Biraz hızlı gidiyoruz 🙂 Birkaç saniye bekleyip tekrar dener misin?';
                } else {
                    errorMsg.textContent = '⚠️ Bir bağlantı sorunu oluştu — lütfen tekrar dene.';
                }
                messages.appendChild(errorMsg);
                messages.scrollTop = messages.scrollHeight;
                if (!input.value) input.value = text; // mesajı geri koy
            } finally {
                if (window._aiWidgetWatchdog) { clearTimeout(window._aiWidgetWatchdog); window._aiWidgetWatchdog = null; }
                window._aiWidgetController = null;
                window._aiWidgetSending = false;
            }
        }

        // --- Draggable Setup ---
        function initDraggableChat() {
            const container = document.getElementById('ai-chat-container');
            const trigger = document.getElementById('ai-chat-trigger');
            let offsetX, offsetY;

            function startDrag(clientX, clientY) {
                isDragging = false;
                dragStartX = clientX;
                dragStartY = clientY;
                const rect = container.getBoundingClientRect();
                offsetX = clientX - rect.left;
                offsetY = clientY - rect.top;
                // Prevent stretching while dragging: lock to current fixed box
                container.style.position = 'fixed';
                container.style.left = rect.left + 'px';
                container.style.top = rect.top + 'px';
                container.style.right = 'auto';
                container.style.bottom = 'auto';
                container.style.transition = 'none';
            }

            function moveDrag(clientX, clientY) {
                if (Math.abs(clientX - dragStartX) > 5 || Math.abs(clientY - dragStartY) > 5) {
                    isDragging = true;
                    container.style.position = 'fixed';
                    container.style.left = (clientX - offsetX) + 'px';
                    container.style.top = (clientY - offsetY) + 'px';
                }
                return isDragging;
            }

            function endDrag() {
                if (!isDragging) return;
                const margin = 24;
                const clamp = (value, min, max) => Math.min(Math.max(value, min), max);
                const w = window.innerWidth;
                const h = window.innerHeight;
                const rect = container.getBoundingClientRect();
                const dists = { left: rect.left, right: w - rect.right, top: rect.top, bottom: h - rect.bottom };
                const min = Math.min(dists.left, dists.right, dists.top, dists.bottom);
                container.style.transition = 'all 0.6s cubic-bezier(0.165, 0.84, 0.44, 1)';
                const maxLeft = Math.max(margin, w - container.offsetWidth - margin);
                const maxTop = Math.max(margin, h - container.offsetHeight - margin);

                if (min === dists.left) {
                    container.style.left = margin + 'px';
                    container.style.top = clamp(rect.top, margin, maxTop) + 'px';
                } else if (min === dists.right) {
                    container.style.left = maxLeft + 'px';
                    container.style.top = clamp(rect.top, margin, maxTop) + 'px';
                } else if (min === dists.top) {
                    container.style.top = margin + 'px';
                    container.style.left = clamp(rect.left, margin, maxLeft) + 'px';
                } else {
                    container.style.top = maxTop + 'px';
                    container.style.left = clamp(rect.left, margin, maxLeft) + 'px';
                }

                const chatWindow = document.getElementById('ai-chat-window');
                if (chatWindow && chatWindow.style.display === 'flex') {
                    positionAIChatWindow();
                }
            }

            trigger.addEventListener('mousedown', (e) => {
                startDrag(e.clientX, e.clientY);
                document.addEventListener('mousemove', onMouseMove);
                document.addEventListener('mouseup', onMouseUp);
            });

            function onMouseMove(e) { moveDrag(e.clientX, e.clientY); }

            function onMouseUp() {
                document.removeEventListener('mousemove', onMouseMove);
                document.removeEventListener('mouseup', onMouseUp);
                endDrag();
                setTimeout(() => { isDragging = false; }, 50);
            }

            // Dokunmatik sürükleme (mobil): parmakla taşı, bırakınca kenara yapış
            trigger.addEventListener('touchstart', (e) => {
                const t = e.touches[0];
                startDrag(t.clientX, t.clientY);
                document.addEventListener('touchmove', onTouchMove, { passive: false });
                document.addEventListener('touchend', onTouchEnd);
            }, { passive: true });

            function onTouchMove(e) {
                // Sürükleme başladıysa sayfanın kaymasını engelle
                const t = e.touches[0];
                if (moveDrag(t.clientX, t.clientY)) e.preventDefault();
            }

            function onTouchEnd() {
                document.removeEventListener('touchmove', onTouchMove);
                document.removeEventListener('touchend', onTouchEnd);
                endDrag();
                // touchend'in ardından gelen sentetik click chat'i yanlışlıkla açmasın
                setTimeout(() => { isDragging = false; }, 400);
            }
        }

        document.getElementById('ai-chat-input')?.addEventListener('keypress', (e) => { if (e.key === 'Enter') sendAIChatMessage(); });
        document.addEventListener('DOMContentLoaded', initDraggableChat);
        // Widget transkripti sayfa yenilenince kaybolur (kalıcı DOM yok). Saklı
        // conversation_uuid'i de temizle: aksi halde boş ekrana sorulan yeni soru,
        // sunucudaki GÖRÜNMEYEN eski niyet/filtrelerle yanıtlanır (kafa karıştırır).
        // Böylece her sayfa yüklemesi widget'ta temiz bir konuşma başlatır.
        try { localStorage.removeItem('turxtur_ai_conv'); } catch (e) {}
        window.addEventListener('resize', () => {
            const chatWindow = document.getElementById('ai-chat-window');
            if (chatWindow && chatWindow.style.display === 'flex') {
                positionAIChatWindow();
            }
        });

        // --- Comparison Code Fix ---
        let comparedTours = JSON.parse(localStorage.getItem('compared_tours') || '[]');
        const comparePageUrl = @json(route('tours.compare'));
        function updateCompareUI() {
            const bar = document.getElementById('compare-bar');
            if (bar) {
                const countEl = document.getElementById('compare-count');
                if (countEl) countEl.innerText = comparedTours.length;
                bar.style.display = comparedTours.length > 0 ? 'block' : 'none';
            }
            document.querySelectorAll('.compare-toggle').forEach(btn => {
                const id = parseInt(btn.dataset.tourId);
                if (comparedTours.includes(id)) { btn.innerHTML = '✓ Eklendi'; btn.style.background = '#059669'; btn.style.color = '#fff'; }
                else { btn.innerHTML = '+ Karşılaştır'; btn.style.background = '#fff'; btn.style.color = '#475569'; }
            });
        }
        window.toggleCompare = function(id) { 
            id = parseInt(id); const idx = comparedTours.indexOf(id);
            if (idx > -1) comparedTours.splice(idx, 1);
            else if (comparedTours.length < 3) comparedTours.push(id);
            else alert('En fazla 3 tur.');
            localStorage.setItem('compared_tours', JSON.stringify(comparedTours));
            updateCompareUI();
        };
        window.clearCompare = function() {
            comparedTours = [];
            localStorage.setItem('compared_tours', JSON.stringify(comparedTours));
            updateCompareUI();
        };
        window.goToCompare = function() {
            if (comparedTours.length < 2) {
                alert('Karşılaştırma için en az 2 tur seçmelisin.');
                return;
            }
            const query = comparedTours.map(function(id) {
                return 'ids[]=' + encodeURIComponent(id);
            }).join('&');
            window.location.href = comparePageUrl + '?' + query;
        };
        document.addEventListener('DOMContentLoaded', updateCompareUI);
    </script>
    @endif

    @php
        // v2 sohbet: v1'den BAĞIMSIZ bayrak (v1 dondurulmuşken kademeli açılabilsin)
        $showChatV2 = config('ai.chat_v2_enabled')
            && ! request()->is('admin*')
            && ! request()->is('super-admin*')
            && ! request()->is('superadmin*')
            && ! request()->is('acenta*')
            && ! request()->routeIs('agency.*')
            && ! (auth()->check() && in_array(auth()->user()->role, ['admin', 'super_admin', 'superadmin'], true));
    @endphp

    @if($showChatV2)
    {{-- Chatbot v2 — araç çağırma mimarisi (CHATBOT_V2.md) --}}
    <div id="cv2" style="position:fixed; bottom:24px; right:24px; z-index:2000; font-family:var(--font); max-width:calc(100vw - 32px);">
        <button type="button" id="cv2-trigger" aria-label="Tur danışmanını aç" aria-expanded="false"
            style="display:flex; align-items:center; gap:10px; background:rgba(15,23,42,0.9); backdrop-filter:blur(20px); color:#fff; border:1px solid rgba(255,255,255,0.15); padding:10px 18px; border-radius:100px; cursor:pointer; box-shadow:0 10px 40px rgba(0,0,0,0.25); font-family:inherit;">
            <span style="width:30px; height:30px; border-radius:50%; background:linear-gradient(135deg,#0d9488,#2dd4bf); display:flex; align-items:center; justify-content:center; font-size:16px;">🧭</span>
            <span style="font-size:13px; font-weight:600;">Tur danışmanı</span>
        </button>

        {{-- display:none satır içinde: satır içi display:flex, [hidden] özniteliğini
             ezip paneli kapanmaz hale getiriyordu. Açılış togglePanel'de yapılır. --}}
        <div id="cv2-panel" role="dialog" aria-modal="true" aria-label="Tur danışmanı" hidden
            style="position:absolute; bottom:56px; right:0; width:min(420px, calc(100vw - 32px)); height:min(600px, calc(100vh - 120px)); background:rgba(15,23,42,0.97); backdrop-filter:blur(24px); border:1px solid rgba(255,255,255,0.15); border-radius:20px; box-shadow:0 24px 64px rgba(0,0,0,0.4); display:none; flex-direction:column; overflow:hidden;">
            <div style="display:flex; align-items:center; justify-content:space-between; padding:14px 16px; border-bottom:1px solid rgba(255,255,255,0.1);">
                <span style="color:#fff; font-size:14px; font-weight:700;">🧭 Tur danışmanı</span>
                <span>
                    <button type="button" id="cv2-reset" title="Konuşmayı sıfırla" aria-label="Konuşmayı sıfırla"
                        style="background:none; border:none; color:rgba(255,255,255,0.5); cursor:pointer; font-size:12px; padding:4px 8px;">sıfırla</button>
                    <button type="button" id="cv2-close" aria-label="Kapat"
                        style="background:none; border:none; color:rgba(255,255,255,0.6); cursor:pointer; font-size:18px; padding:4px 8px;">×</button>
                </span>
            </div>

            <div id="cv2-msgs" aria-live="polite" style="flex:1; min-height:0; overflow-y:auto; padding:16px; display:flex; flex-direction:column; gap:14px;">
                <div class="cv2-ai">Merhaba! Nasıl bir tatil hayal ediyorsun? Anlat bana — sessizlik mi, hareket mi, yoksa lezzet peşinde misin?</div>
            </div>

            <div style="padding:12px 14px; border-top:1px solid rgba(255,255,255,0.1);">
                <form id="cv2-form" style="display:flex; gap:8px; align-items:flex-end;">
                    <textarea id="cv2-input" rows="1" required aria-label="Mesajınız" placeholder="Hayalindeki tatili anlat..."
                        style="flex:1; background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.12); border-radius:14px; padding:10px 12px; color:#fff; font-size:14px; font-family:inherit; resize:none; max-height:110px; outline:none;"></textarea>
                    <button type="submit" id="cv2-send" aria-label="Gönder"
                        style="background:linear-gradient(135deg,#0d9488,#2dd4bf); border:none; width:40px; height:40px; border-radius:12px; color:#fff; cursor:pointer; flex-shrink:0; font-size:16px;">↑</button>
                </form>
            </div>
        </div>
    </div>

    <style>
        #cv2-msgs .cv2-user { align-self:flex-end; max-width:85%; background:linear-gradient(135deg,#0d9488,#2dd4bf); color:#fff; padding:10px 14px; border-radius:16px 16px 4px 16px; font-size:14px; line-height:1.5; white-space:pre-wrap; overflow-wrap:anywhere; }
        #cv2-msgs .cv2-ai { align-self:flex-start; max-width:90%; background:rgba(255,255,255,0.07); color:#e2e8f0; padding:11px 14px; border-radius:16px 16px 16px 4px; font-size:14px; line-height:1.6; white-space:pre-wrap; overflow-wrap:anywhere; }
        #cv2-msgs .cv2-err { align-self:flex-start; max-width:90%; background:rgba(239,68,68,0.15); color:#fca5a5; padding:10px 14px; border-radius:14px; font-size:13px; }
        #cv2-msgs .cv2-cards { display:flex; gap:10px; overflow-x:auto; padding:4px 2px 8px; max-width:100%; scroll-snap-type:x mandatory; }
        #cv2-trigger:hover { transform:translateY(-2px); }
        #cv2-trigger { transition:transform .25s; }
    </style>

    <script>
    (function () {
        const trigger = document.getElementById('cv2-trigger');
        const panel = document.getElementById('cv2-panel');
        const msgs = document.getElementById('cv2-msgs');
        const form = document.getElementById('cv2-form');
        const input = document.getElementById('cv2-input');
        const sendBtn = document.getElementById('cv2-send');
        let sending = false;

        function el(cls, text) {
            const d = document.createElement('div');
            d.className = cls;
            if (text !== undefined) d.textContent = text;   // XSS: her zaman textContent
            msgs.appendChild(d);
            msgs.scrollTop = msgs.scrollHeight;
            return d;
        }

        function acikMi() {
            return panel.style.display !== 'none';
        }
        function togglePanel(open) {
            // hidden + display birlikte: hidden erişilebilirlik için, display
            // görsel için (satır içi stil [hidden] kuralını ezdiğinden ikisi şart)
            panel.hidden = !open;
            panel.style.display = open ? 'flex' : 'none';
            trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (open) input.focus();
        }
        trigger.onclick = () => togglePanel(! acikMi());
        document.getElementById('cv2-close').onclick = () => { togglePanel(false); trigger.focus(); };
        document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && acikMi()) { togglePanel(false); trigger.focus(); } });

        document.getElementById('cv2-reset').onclick = async () => {
            if (sending) return;
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            try { await fetch('/sohbet/sifirla', { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' } }); } catch (e) {}
            msgs.replaceChildren();
            el('cv2-ai', 'Baştan başlayalım — nasıl bir tatil istiyorsun?');
        };

        input.addEventListener('input', () => { input.style.height = 'auto'; input.style.height = Math.min(input.scrollHeight, 110) + 'px'; });
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); form.requestSubmit(); }
        });

        function setSending(on) {
            sending = on;
            input.disabled = on;
            sendBtn.disabled = on;
            sendBtn.textContent = on ? '…' : '↑';
            if (!on) input.focus();
        }

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            if (sending) return;
            const text = input.value.trim();
            if (!text) return;

            el('cv2-user', text);
            input.value = '';
            input.style.height = 'auto';
            setSending(true);

            const bubble = el('cv2-ai', 'Düşünüyorum…');
            let ilkParca = true;

            // Bekçi: 90 sn boyunca hiç veri gelmezse iptal et (araç turları uzun sürebilir)
            const controller = new AbortController();
            let watchdog = setTimeout(() => controller.abort(), 90000);
            const resetWatchdog = () => { clearTimeout(watchdog); watchdog = setTimeout(() => controller.abort(), 90000); };

            try {
                const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                const res = await fetch('/sohbet/akis', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'text/event-stream' },
                    body: JSON.stringify({ message: text }),
                    signal: controller.signal,
                });
                if (!res.ok) throw new Error('http ' + res.status);

                const reader = res.body.getReader();
                const decoder = new TextDecoder();
                let buffer = '';

                while (true) {
                    const { done, value } = await reader.read();
                    if (done) break;
                    resetWatchdog();
                    buffer += decoder.decode(value, { stream: true });

                    const bloklar = buffer.split('\n\n');
                    buffer = bloklar.pop() || '';

                    for (const blok of bloklar) {
                        let event = null, dataRaw = '';
                        for (const satir of blok.split('\n')) {
                            if (satir.startsWith('event:')) event = satir.slice(6).trim();
                            else if (satir.startsWith('data:')) dataRaw += satir.slice(5).trim();
                        }
                        if (!event || !dataRaw) continue;
                        let data; try { data = JSON.parse(dataRaw); } catch (e) { continue; }

                        if (event === 'faz') {
                            // Araç koşarken ne yapıldığını göster; ilk gerçek
                            // metin gelince bu yazı yerini cevaba bırakır
                            if (ilkParca) bubble.textContent = data.text || 'Bakıyorum…';
                        } else if (event === 'delta') {
                            if (ilkParca) { bubble.textContent = ''; ilkParca = false; }
                            bubble.textContent += data.text || '';
                            msgs.scrollTop = msgs.scrollHeight;
                        } else if (event === 'tours') {
                            const row = document.createElement('div');
                            row.className = 'cv2-cards';
                            (data.items || []).forEach((t, i) => {
                                try { row.appendChild(window.turxturAiCard.build(t, { theme: 'dark', index: i })); } catch (err) {}
                            });
                            if (row.children.length) { msgs.appendChild(row); msgs.scrollTop = msgs.scrollHeight; }
                        } else if (event === 'error') {
                            el('cv2-err', data.message || 'Bir sorun oluştu.');
                        }
                    }
                }

                // Sessiz ölüm: hiç veri gelmeden akış bittiyse kullanıcı takılmasın
                if (ilkParca) {
                    bubble.remove();
                    el('cv2-err', 'Bağlantı kesildi — mesajını tekrar gönderir misin?');
                    input.value = text;
                }
            } catch (err) {
                bubble.remove();
                el('cv2-err', err.name === 'AbortError'
                    ? 'Yanıt çok uzun sürdü, tekrar dener misin?'
                    : 'Bağlanamadım — mesajını tekrar gönderir misin?');
                input.value = text;
            } finally {
                clearTimeout(watchdog);
                setSending(false);
            }
        });
    })();
    </script>
    @endif

    @stack('scripts')

    <script>
        // PWA service worker (kurulabilirlik için; önbellekleme yapmaz)
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}));
        }
    </script>

    {{-- App hissi: dokunma başlarken sayfayı önceden hazırla — geçişler anında olur.
         (Tarayıcı isteği Sec-Purpose başlığıyla işaretler; dokunma %99 gezinmeye
         dönüştüğü için görüntülenme sayacına etkisi ihmal edilebilir.) --}}
    <script type="speculationrules">
    {
        "prerender": [{
            "where": { "and": [
                { "href_matches": "/turlar/*" },
                { "not": { "href_matches": "/turlar/karsilastir*" } }
            ]},
            "eagerness": "moderate"
        }],
        "prefetch": [{
            "where": { "href_matches": ["/", "/turlar", "/blog", "/favorilerim", "/profil"] },
            "eagerness": "moderate"
        }]
    }
    </script>
</body>
</html>
