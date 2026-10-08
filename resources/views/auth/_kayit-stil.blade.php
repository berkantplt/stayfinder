{{--
    Kayıt kartı stilleri — bireysel (/kayit) ve acenta (/acenta-kayit) kayıt
    sayfalarının ORTAK CSS'i. İki sayfa aynı kartı (sol fotoğraf paneli + sağ form)
    kullanır; tasarım burada tek yerde durur.

    Kullanım: @section('styles') @include('auth._kayit-stil') @endsection
    Layout bu bölümü <style> içine ÇIPLAK basar — burada <style> sarmalı OLMAZ
    (iç içe <style> ilk kuralı sessizce düşürür, bkz. layouts/app.blade.php).
--}}
    .container.kayit-sayfa { max-width:1180px; padding-top:44px; padding-bottom:72px; }

    .kayit-kart {
        position:relative;
        display:grid;
        grid-template-columns:0.9fr 1.1fr;
        background:#fff;
        border-radius:26px;
        overflow:hidden;
        box-shadow:0 34px 64px -30px rgba(15,23,42,.42), 0 0 0 1px rgba(226,232,240,.9);
    }

    /* ── İnce neon şerit ──
       Fotoğraf paneli ile formun birleştiği dikey ek yerinde duran 3px'lik
       turkuaz çizgi. Panelin sağ kenarına yapışıyor (grid oranından bağımsız),
       parlama bir yanda fotoğrafa bir yanda beyaz forma düşüyor.
       ::after içindeki açık geçiş yavaş bir süpürme yapar — hareketi azaltılmış
       modda duruyor (WCAG 2.3.3). */
    .kayit-neon {
        position:absolute; top:0; bottom:0; right:0; width:3px; z-index:4;
        overflow:hidden;
        background:linear-gradient(180deg,
            rgba(45,212,191,0) 0%, #2dd4bf 15%, #5eead4 38%,
            #22d3ee 62%, #2dd4bf 85%, rgba(45,212,191,0) 100%);
        box-shadow:0 0 10px rgba(45,212,191,.95), 0 0 26px rgba(34,211,238,.5), -3px 0 20px rgba(45,212,191,.3);
    }
    .kayit-neon::after {
        content:""; position:absolute; left:0; right:0; top:0; height:26%;
        background:linear-gradient(180deg, transparent, rgba(255,255,255,.95), transparent);
        animation:kayit-neon-kay 7s linear infinite;
    }
    @keyframes kayit-neon-kay {
        from { transform:translateY(-120%); }
        to   { transform:translateY(480%); }
    }
    @keyframes kayit-neon-kay-yatay {
        from { transform:translateX(-120%); }
        to   { transform:translateX(450%); }
    }
    @media (prefers-reduced-motion: reduce) {
        .kayit-neon::after { animation:none; opacity:.3; }
    }

    /* ── Sol görsel panel ── */
    .kayit-gorsel {
        position:relative;
        min-height:580px;
        display:flex;
        align-items:flex-end;
        background-image:
            linear-gradient(to top, rgba(6,44,40,.94) 0%, rgba(6,44,40,.62) 32%, rgba(6,44,40,.10) 60%, rgba(6,44,40,.16) 100%),
            url('{{ asset('images/auth-bg.jpg') }}');
        background-size:cover;
        background-position:center;
    }
    .kayit-gorsel__ic { padding:42px 38px; color:#fff; }
    .kayit-gorsel__cizgi {
        display:block; width:34px; height:3px; border-radius:2px;
        background:#5eead4; margin-bottom:18px;
        box-shadow:0 0 10px rgba(94,234,212,.85);
    }
    .kayit-gorsel h2 { font-size:40px; line-height:1.12; font-weight:800; letter-spacing:-.02em; margin-bottom:12px; }
    .kayit-gorsel p { font-size:16px; color:rgba(255,255,255,.88); margin-bottom:22px; }
    .kayit-gorsel ul { list-style:none; display:flex; flex-wrap:wrap; gap:12px 24px; }
    .kayit-gorsel li { display:flex; align-items:center; gap:9px; font-size:14px; font-weight:600; }
    .kayit-tik {
        width:22px; height:22px; flex:0 0 22px; border-radius:50%;
        background:#14b8a6; display:inline-flex; align-items:center; justify-content:center;
        box-shadow:0 0 12px rgba(20,184,166,.55);
    }

    /* ── Sağ form panel ── */
    .kayit-form { padding:48px 46px 44px; }
    .kayit-form h1 { font-size:32px; font-weight:800; letter-spacing:-.02em; color:#0f172a; margin-bottom:8px; }
    .kayit-form__alt { font-size:15px; color:#64748b; margin-bottom:26px; }

    /* ── Alanlar ── */
    .kayit-alan { margin-bottom:18px; }
    .kayit-alan label { display:block; font-size:13.5px; font-weight:700; color:#334155; margin-bottom:8px; }
    .kayit-alan input, .kayit-alan textarea {
        width:100%; padding:14px 16px; border-radius:12px;
        border:1px solid #cbd5e1; background:#fff; color:#0f172a;
        font-size:15px; font-family:inherit; outline:none;
        transition:border-color .18s ease, box-shadow .18s ease;
    }
    .kayit-alan textarea { resize:vertical; }
    .kayit-alan input::placeholder, .kayit-alan textarea::placeholder { color:#94a3b8; }
    .kayit-alan input:focus, .kayit-alan textarea:focus {
        border-color:#14b8a6; box-shadow:0 0 0 3px rgba(20,184,166,.16);
    }
    .kayit-ikili { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:14px; }

    .kayit-sifre { position:relative; }
    .kayit-sifre input { padding-right:48px; }
    .kayit-goz {
        position:absolute; right:7px; bottom:5px; width:38px; height:38px;
        border:none; background:none; color:#64748b; cursor:pointer; border-radius:10px;
        display:flex; align-items:center; justify-content:center;
        transition:color .15s ease, background .15s ease;
    }
    .kayit-goz:hover { color:#0f766e; background:#f1f5f9; }

    .kayit-bilgi {
        padding:14px 16px; border-radius:14px; background:#f0fdfa;
        border:1px solid #99f6e4; color:#0f766e;
        font-size:13px; line-height:1.6; margin-bottom:20px;
    }

    .kayit-gonder {
        width:100%; margin-top:6px;
        display:inline-flex; align-items:center; justify-content:center; gap:10px;
        padding:16px 20px; border:none; border-radius:13px;
        background:var(--accent-ink); color:#fff;
        font-size:16px; font-weight:700; font-family:inherit; cursor:pointer;
        box-shadow:0 10px 22px -10px rgba(13,148,136,.8);
        transition:background .2s ease, transform .2s ease, box-shadow .2s ease;
    }
    .kayit-gonder:hover {
        background:var(--accent-deep); transform:translateY(-1px);
        box-shadow:0 14px 26px -10px rgba(13,148,136,.9);
    }

    /* Form altı bağlantılar: "Giriş yap" + karşı hesap türüne geçiş */
    .kayit-giris { text-align:center; margin-top:22px; font-size:14.5px; color:#64748b; }
    .kayit-giris a { color:var(--accent-ink); font-weight:700; }
    .kayit-giris a:hover { color:var(--accent-deep); }
    .kayit-giris--ikincil { margin-top:10px; font-size:13.5px; }

    @media (max-width: 980px) {
        .kayit-kart { grid-template-columns:1fr; }
        /* Tek sütunda ek yeri artık banner ile formun arasında — şerit de yatay */
        .kayit-neon {
            top:auto; left:0; right:0; bottom:0; width:auto; height:3px;
            background:linear-gradient(90deg,
                rgba(45,212,191,0) 0%, #2dd4bf 15%, #5eead4 38%,
                #22d3ee 62%, #2dd4bf 85%, rgba(45,212,191,0) 100%);
            box-shadow:0 0 10px rgba(45,212,191,.95), 0 0 26px rgba(34,211,238,.5), 0 -3px 20px rgba(45,212,191,.3);
        }
        .kayit-neon::after {
            top:0; bottom:0; left:0; right:auto; height:auto; width:30%;
            background:linear-gradient(90deg, transparent, rgba(255,255,255,.95), transparent);
            animation-name:kayit-neon-kay-yatay;
        }
        .kayit-gorsel { min-height:215px; background-position:center 40%; }
        .kayit-gorsel__ic { padding:26px 24px; }
        .kayit-gorsel__cizgi { margin-bottom:14px; }
        .kayit-gorsel h2 { font-size:28px; }
        .kayit-gorsel p { font-size:15px; margin-bottom:0; }
        .kayit-gorsel ul { display:none; }
        .kayit-form { padding:32px 28px 36px; }
    }

    @media (max-width: 560px) {
        .container.kayit-sayfa { padding-top:22px; padding-bottom:48px; }
        .kayit-kart { border-radius:20px; }
        .kayit-gorsel { min-height:170px; }
        .kayit-gorsel h2 { font-size:24px; }
        .kayit-form { padding:26px 20px 30px; }
        .kayit-form h1 { font-size:25px; }
        .kayit-ikili { grid-template-columns:1fr; gap:0; }
    }
