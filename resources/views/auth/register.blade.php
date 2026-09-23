@extends('layouts.app')
@section('title', 'Kayıt Ol — turXtur')

@section('content')
{{-- Tam ekran fotoğraf zemini (partials.auth-background) bu sayfada BİLEREK yok:
     fotoğraf artık kartın sol paneline taşındı, sayfa zemini sade kalıyor.
     Giriş / şifre sayfaları eski zeminde — onlar ayrı karar. --}}

@php($selectedType = old('account_type', request('type') === 'agency' ? 'agency' : 'visitor'))

<div class="container kayit-sayfa">
    <div class="kayit-kart">
        <aside class="kayit-gorsel">
            <div class="kayit-gorsel__ic">
                <span class="kayit-gorsel__cizgi"></span>
                <h2>Yeni rotalar,<br>yeni hikâyeler.</h2>
                <p>Bir sonraki seyahatin burada başlasın.</p>
                <ul>
                    <li>
                        <span class="kayit-tik" aria-hidden="true">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                        </span>
                        Turları karşılaştır
                    </li>
                    <li>
                        <span class="kayit-tik" aria-hidden="true">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                        </span>
                        Favorilerini sakla
                    </li>
                </ul>
            </div>

            {{-- İnce neon şerit: fotoğraf paneli ile formun birleştiği dikey ek
                 yerinde durur; panelin sağ kenarına yapışık olduğu için sütun
                 oranı değişse de yerini korur. Kart overflow:hidden olduğundan
                 parlama dışarı taşmaz. 980px altında sütunlar alt alta düşer,
                 aynı şerit orada yatay hale gelir (aşağıdaki media sorgusu). --}}
            <span class="kayit-neon" aria-hidden="true"></span>
        </aside>

        <div class="kayit-form">
            <h1 id="register-title">{{ $selectedType === 'agency' ? 'Acenta başvurusu' : 'Hesabını oluştur' }}</h1>
            <p class="kayit-form__alt" id="register-subtitle">
                {{ $selectedType === 'agency' ? 'Başvurunu gönder, admin onayı sonrası panelin açılsın.' : 'Ücretsiz katıl, sana uyan turları keşfet.' }}
            </p>

            <div class="kayit-tipler" role="radiogroup" aria-label="Hesap türü">
                <button type="button" role="radio" aria-checked="{{ $selectedType === 'visitor' ? 'true' : 'false' }}"
                        class="kayit-tip {{ $selectedType === 'visitor' ? 'secili' : '' }}" data-account-type="visitor">
                    <span class="kayit-tip__rozet" aria-hidden="true">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                    </span>
                    <svg class="kayit-tip__ikon" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-6 8-6s8 2 8 6"/>
                    </svg>
                    <span class="kayit-tip__ad">Bireysel</span>
                    <span class="kayit-tip__not">Seyahatlerim için</span>
                </button>

                <button type="button" role="radio" aria-checked="{{ $selectedType === 'agency' ? 'true' : 'false' }}"
                        class="kayit-tip {{ $selectedType === 'agency' ? 'secili' : '' }}" data-account-type="agency">
                    <span class="kayit-tip__rozet" aria-hidden="true">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                    </span>
                    <svg class="kayit-tip__ikon" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M4 21V5a1 1 0 0 1 1-1h9a1 1 0 0 1 1 1v16"/><path d="M15 9h4a1 1 0 0 1 1 1v11"/><path d="M2 21h20"/>
                        <path d="M7.5 8h3M7.5 12h3M7.5 16h3M17.5 13h0M17.5 17h0"/>
                    </svg>
                    <span class="kayit-tip__ad">Acenta</span>
                    <span class="kayit-tip__not">Turlarımı yayınlamak için</span>
                </button>
            </div>

            <div class="kayit-ayrac"></div>

            @include('partials.form-errors', ['style' => 'margin-bottom:22px;'])

            <form method="POST" action="{{ route('register.post') }}" id="register-form">
                @csrf
                <input type="hidden" name="account_type" id="account_type" value="{{ $selectedType }}">
                {{-- Bağlam (giriş sayfasıyla aynı): kayıt sonrası aynı tura dönüş + bekleyen favori --}}
                <input type="hidden" name="next" value="{{ \App\Support\LoginReturn::safePath(request('next')) }}">
                <input type="hidden" name="favori" value="{{ ctype_digit((string) request('favori')) ? request('favori') : '' }}">

                <div id="agency-fields" style="display:{{ $selectedType === 'agency' ? 'block' : 'none' }};">
                    <div class="kayit-bilgi">
                        Başvuru gönderildiğinde sizin için bir acenta profili ve yetkili kullanıcı hesabı oluşturulur. Admin onayı sonrası kategori satın alıp tur paylaşmaya başlayabilirsiniz.
                    </div>

                    <div class="kayit-alan">
                        <label for="agency_name">Acenta Adı</label>
                        <input type="text" id="agency_name" name="agency_name" value="{{ old('agency_name') }}" data-agency-required="true" placeholder="Acentanızın ticari adı">
                    </div>
                </div>

                <div class="kayit-alan">
                    <label for="name" id="name-label">{{ $selectedType === 'agency' ? 'Yetkili Ad Soyad' : 'Ad Soyad' }}</label>
                    <input type="text" id="name" name="name" value="{{ old('name') }}" required autofocus autocomplete="name" placeholder="Adınız ve soyadınız">
                </div>

                <div class="kayit-alan">
                    <label for="email" id="email-label">{{ $selectedType === 'agency' ? 'Yetkili E-posta' : 'E-posta' }}</label>
                    <input type="email" id="email" name="email" value="{{ old('email') }}" required autocomplete="email" placeholder="ornek@eposta.com">
                </div>

                <div id="agency-meta-fields" style="display:{{ $selectedType === 'agency' ? 'block' : 'none' }};">
                    <div class="kayit-ikili">
                        <div class="kayit-alan">
                            <label for="phone">Telefon</label>
                            <input type="text" id="phone" name="phone" value="{{ old('phone') }}" placeholder="0212 000 00 00">
                        </div>
                        <div class="kayit-alan">
                            <label for="website_url">Web Sitesi</label>
                            <input type="url" id="website_url" name="website_url" value="{{ old('website_url') }}" placeholder="https://...">
                        </div>
                    </div>

                    <div class="kayit-alan">
                        <label for="description">Kısa Açıklama</label>
                        <textarea id="description" name="description" rows="3" placeholder="Acentanızı birkaç cümleyle tanıtın">{{ old('description') }}</textarea>
                    </div>
                </div>

                <div class="kayit-ikili">
                    <div class="kayit-alan kayit-sifre">
                        <label for="password">Şifre</label>
                        <input type="password" id="password" name="password" required autocomplete="new-password" placeholder="En az 8 karakter">
                        <button type="button" class="kayit-goz" data-sifre-hedef="password" aria-label="Şifreyi göster" aria-pressed="false">
                            @include('partials.icon-eye')
                        </button>
                    </div>

                    <div class="kayit-alan kayit-sifre">
                        <label for="password_confirmation">Şifre tekrar</label>
                        <input type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password" placeholder="Şifreyi tekrar yazın">
                        <button type="button" class="kayit-goz" data-sifre-hedef="password_confirmation" aria-label="Şifreyi göster" aria-pressed="false">
                            @include('partials.icon-eye')
                        </button>
                    </div>
                </div>

                <button type="submit" class="kayit-gonder" id="submit-label">
                    <span>{{ $selectedType === 'agency' ? 'Acenta hesabı oluştur' : 'Ücretsiz hesap oluştur' }}</span>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h13M13 6l6 6-6 6"/></svg>
                </button>
            </form>

            {{-- Sosyal kayıt yalnız bireysel hesap içindir: acenta başvurusu acenta adı,
                 iletişim bilgisi ve admin onayı ister; Google/Apple bunları getirmez. --}}
            <div id="social-auth-wrap" style="display:{{ $selectedType === 'agency' ? 'none' : 'block' }};">
                @include('partials.social-auth-buttons', ['mode' => 'register'])
            </div>

            <div class="kayit-giris">
                Zaten hesabın var mı? <a href="{{ route('login', array_filter(['next' => \App\Support\LoginReturn::safePath(request('next')), 'favori' => ctype_digit((string) request('favori')) ? request('favori') : null])) }}">Giriş yap</a>
            </div>
        </div>
    </div>
</div>
@endsection

@section('styles')
<style>
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

    /* ── Hesap türü kutuları ── */
    .kayit-tipler { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:12px; }
    .kayit-tip {
        position:relative; border:1.5px solid #e2e8f0; background:#fff;
        border-radius:16px; padding:18px 12px 16px; text-align:center;
        color:#0f172a; font-family:inherit; cursor:pointer;
        transition:border-color .18s ease, background .18s ease, box-shadow .18s ease;
    }
    .kayit-tip:hover { border-color:#94a3b8; }
    .kayit-tip__ikon { color:#64748b; transition:color .18s ease; }
    .kayit-tip__ad { display:block; font-size:15px; font-weight:700; margin-top:9px; }
    .kayit-tip__not { display:block; font-size:12px; color:#64748b; margin-top:3px; }
    .kayit-tip.secili { border-color:#14b8a6; background:#f0fdfa; box-shadow:0 0 0 3px rgba(20,184,166,.13); }
    .kayit-tip.secili .kayit-tip__ikon { color:#0f766e; }
    .kayit-tip__rozet {
        position:absolute; top:9px; right:9px; width:23px; height:23px; border-radius:50%;
        background:#14b8a6; display:none; align-items:center; justify-content:center;
    }
    .kayit-tip.secili .kayit-tip__rozet { display:flex; }

    .kayit-ayrac { height:1px; background:#e2e8f0; margin:26px 0 24px; }

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
        position:absolute; right:7px; bottom:7px; width:38px; height:38px;
        border:none; background:none; color:#94a3b8; cursor:pointer; border-radius:10px;
        display:flex; align-items:center; justify-content:center;
        transition:color .15s ease, background .15s ease;
    }
    .kayit-goz:hover { color:#0f766e; background:#f1f5f9; }
    .kayit-goz .goz-kapa { display:none; }
    .kayit-goz--acik .goz-ac { display:none; }
    .kayit-goz--acik .goz-kapa { display:block; }

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

    .kayit-giris { text-align:center; margin-top:22px; font-size:14.5px; color:#64748b; }
    .kayit-giris a { color:var(--accent-ink); font-weight:700; }
    .kayit-giris a:hover { color:var(--accent-deep); }

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
        .kayit-tip__not { display:none; }
    }
</style>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const switches = document.querySelectorAll('.kayit-tip');
    const accountTypeInput = document.getElementById('account_type');
    const agencyFields = document.getElementById('agency-fields');
    const agencyMetaFields = document.getElementById('agency-meta-fields');
    const submitLabel = document.querySelector('#submit-label span');
    const title = document.getElementById('register-title');
    const nameLabel = document.getElementById('name-label');
    const emailLabel = document.getElementById('email-label');
    const subtitle = document.getElementById('register-subtitle');
    const socialAuthWrap = document.getElementById('social-auth-wrap');
    const agencySpecificInputs = [
        document.querySelector('input[name="agency_name"]'),
        document.querySelector('input[name="phone"]'),
        document.querySelector('input[name="website_url"]'),
        document.querySelector('textarea[name="description"]')
    ];

    function syncForm(type) {
        const isAgency = type === 'agency';

        accountTypeInput.value = type;
        agencyFields.style.display = isAgency ? 'block' : 'none';
        agencyMetaFields.style.display = isAgency ? 'block' : 'none';
        submitLabel.textContent = isAgency ? 'Acenta hesabı oluştur' : 'Ücretsiz hesap oluştur';
        title.textContent = isAgency ? 'Acenta başvurusu' : 'Hesabını oluştur';
        nameLabel.textContent = isAgency ? 'Yetkili Ad Soyad' : 'Ad Soyad';
        emailLabel.textContent = isAgency ? 'Yetkili E-posta' : 'E-posta';
        subtitle.textContent = isAgency
            ? 'Başvurunu gönder, admin onayı sonrası panelin açılsın.'
            : 'Ücretsiz katıl, sana uyan turları keşfet.';

        if (socialAuthWrap) {
            socialAuthWrap.style.display = isAgency ? 'none' : 'block';
        }

        switches.forEach((button) => {
            const secili = button.dataset.accountType === type;
            button.classList.toggle('secili', secili);
            button.setAttribute('aria-checked', secili ? 'true' : 'false');
        });

        // Gizli alanlar disabled kalır: yoksa boş "website_url" url kuralına takılır
        agencySpecificInputs.forEach((field) => {
            if (!field) {
                return;
            }

            const requiredForAgency = field.dataset.agencyRequired === 'true';
            field.disabled = !isAgency;
            field.required = isAgency && requiredForAgency;
        });
    }

    switches.forEach((button) => {
        button.addEventListener('click', function () {
            syncForm(this.dataset.accountType);
        });
    });

    // Şifre göz düğmeleri: type değişince tarayıcı odağı kaybetmesin diye
    // odak alana geri veriliyor (imleç sona konumlanır).
    document.querySelectorAll('.kayit-goz').forEach((button) => {
        button.addEventListener('click', function () {
            const field = document.getElementById(this.dataset.sifreHedef);
            if (!field) {
                return;
            }

            const acik = field.type === 'text';
            field.type = acik ? 'password' : 'text';
            this.setAttribute('aria-pressed', acik ? 'false' : 'true');
            this.setAttribute('aria-label', acik ? 'Şifreyi göster' : 'Şifreyi gizle');
            this.classList.toggle('kayit-goz--acik', !acik);
            field.focus();
            const uzunluk = field.value.length;
            try { field.setSelectionRange(uzunluk, uzunluk); } catch (e) { /* type=email değil, sorun yok */ }
        });
    });

    syncForm(accountTypeInput.value || 'visitor');
});
</script>
@endpush
