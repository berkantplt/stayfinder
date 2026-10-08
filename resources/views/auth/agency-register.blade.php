@extends('layouts.app')
@section('title', 'Acenta Başvurusu — turXtur')

@section('content')
{{-- Acenta kayıt sayfası (/acenta-kayit). Bireysel kayıttan (/kayit, auth/register)
     2026-10-08'de ayrıldı: footer "Acenta Ol", giriş sayfasındaki "Acenta hesabı
     oluşturun" ve yasal sayfalar buraya gelir. Aynı kart tasarımı, acenta metni;
     sosyal giriş BİLEREK yok (Google/Apple acenta adı, iletişim ve admin onayı
     bilgilerini getirmez). Ortak kart stili: auth/_kayit-stil. --}}

<div class="container kayit-sayfa">
    <div class="kayit-kart">
        <aside class="kayit-gorsel">
            <div class="kayit-gorsel__ic">
                <span class="kayit-gorsel__cizgi"></span>
                <h2>Turunuzu binlerce<br>gezginle buluşturun.</h2>
                <p>turXtur acentası olun, turlarınızı yayınlayın.</p>
                <ul>
                    <li>
                        <span class="kayit-tik" aria-hidden="true">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                        </span>
                        Daha fazla gezgine ulaş
                    </li>
                    <li>
                        <span class="kayit-tik" aria-hidden="true">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                        </span>
                        Satışlarını artır
                    </li>
                </ul>
            </div>

            {{-- İnce neon şerit: fotoğraf paneli ile formun birleştiği dikey ek
                 yerinde durur; 980px altında yatay hale gelir (bkz. _kayit-stil). --}}
            <span class="kayit-neon" aria-hidden="true"></span>
        </aside>

        <div class="kayit-form">
            <h1 id="register-title">Acenta başvurusu</h1>
            <p class="kayit-form__alt">Başvurunu gönder, admin onayı sonrası panelin açılsın.</p>

            @include('partials.form-errors', ['style' => 'margin-bottom:22px;'])

            <form method="POST" action="{{ route('agency.register.post') }}" id="agency-register-form">
                @csrf

                <div class="kayit-bilgi">
                    Başvuru gönderildiğinde sizin için bir acenta profili ve yetkili kullanıcı hesabı oluşturulur. Admin onayı sonrası kategori satın alıp tur paylaşmaya başlayabilirsiniz.
                </div>

                <div class="kayit-alan">
                    <label for="agency_name">Acenta Adı</label>
                    <input type="text" id="agency_name" name="agency_name" value="{{ old('agency_name') }}" required autofocus autocomplete="organization" placeholder="Acentanızın ticari adı">
                </div>

                <div class="kayit-alan">
                    <label for="name">Yetkili Ad Soyad</label>
                    <input type="text" id="name" name="name" value="{{ old('name') }}" required autocomplete="name" placeholder="Adınız ve soyadınız">
                </div>

                <div class="kayit-alan">
                    <label for="email">Yetkili E-posta</label>
                    <input type="email" id="email" name="email" value="{{ old('email') }}" required autocomplete="email" placeholder="ornek@acenta.com">
                </div>

                <div class="kayit-ikili">
                    <div class="kayit-alan">
                        <label for="phone">Telefon</label>
                        <input type="text" id="phone" name="phone" value="{{ old('phone') }}" autocomplete="tel" placeholder="0212 000 00 00">
                    </div>
                    <div class="kayit-alan">
                        <label for="website_url">Web Sitesi</label>
                        <input type="url" id="website_url" name="website_url" value="{{ old('website_url') }}" autocomplete="url" placeholder="https://...">
                    </div>
                </div>

                <div class="kayit-alan">
                    <label for="description">Kısa Açıklama</label>
                    <textarea id="description" name="description" rows="3" placeholder="Acentanızı birkaç cümleyle tanıtın">{{ old('description') }}</textarea>
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
                        <button type="button" class="kayit-goz" data-sifre-hedef="password_confirmation" aria-label="Şifre tekrarını göster" aria-pressed="false">
                            @include('partials.icon-eye')
                        </button>
                    </div>
                </div>

                <button type="submit" class="kayit-gonder">
                    <span>Acenta başvurusu gönder</span>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h13M13 6l6 6-6 6"/></svg>
                </button>
            </form>

            <div class="kayit-giris">
                Zaten acenta hesabın var mı? <a href="{{ route('login') }}">Giriş yap</a>
            </div>
            <div class="kayit-giris kayit-giris--ikincil">
                Gezgin misiniz? <a href="{{ route('register') }}">Bireysel hesap oluşturun</a>
            </div>
        </div>
    </div>
</div>
@endsection

@section('styles')
    @include('auth._kayit-stil')
@endsection

{{-- Şifre göz düğmeleri layouts/app.blade.php'deki ortak [data-sifre-hedef] dinleyicisinde. --}}
