@extends('layouts.app')
@section('title', 'Güvenlik — turXtur')

@section('content')
<div class="container" style="max-width:680px;">
    <div class="section">
        @include('partials.account-nav')
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;">
            <h1 style="font-size:22px;font-weight:800;">🔒 Güvenlik</h1>
            <a href="{{ route('profile.edit') }}" class="btn btn-outline btn-sm">Kişisel bilgiler →</a>
        </div>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @include('partials.form-errors')

        {{-- Şifre. Sosyal giriş ile açılmış hesapta kullanıcının bileceği bir şifre
             yoktur: "mevcut şifre" sorulmaz, ilk şifre belirlenir. --}}
        @php($ilkSifre = $user->canSetPasswordWithoutCurrent())
        <div style="background:var(--white);border:1px solid var(--border);border-radius:var(--radius-lg);padding:24px;margin-bottom:20px;">
            <h2 style="font-size:16px;font-weight:700;margin-bottom:6px;">{{ $ilkSifre ? 'Şifre Belirle' : 'Şifre Değiştir' }}</h2>
            <p style="font-size:13px;color:var(--text-muted);margin-bottom:18px;">
                @if($ilkSifre)
                    Hesabınız {{ $user->socialAccounts->first()?->label() ?? 'sosyal hesap' }} ile açıldı ve şifresi yok.
                    Şifre belirlerseniz e-posta ve şifrenizle de giriş yapabilirsiniz.
                @else
                    Şifreniz değişince diğer cihazlardaki oturumlarınız kapatılır.
                @endif
            </p>
            <form method="POST" action="{{ route('profile.password') }}">
                @csrf @method('PUT')
                @unless($ilkSifre)
                <div class="form-group">
                    <label>Mevcut Şifre</label>
                    <input type="password" name="current_password" autocomplete="current-password" placeholder="••••••••">
@error('current_password')<p class="p-hata">{{ $message }}</p>@enderror
                </div>
                @endunless
                <div class="form-row">
                    <div class="form-group">
                        <label>Yeni Şifre</label>
                        <input type="password" name="password" autocomplete="new-password" placeholder="Min. 8 karakter">
@error('password')<p class="p-hata">{{ $message }}</p>@enderror
                    </div>
                    <div class="form-group">
                        <label>Şifre Tekrar</label>
                        <input type="password" name="password_confirmation" autocomplete="new-password" placeholder="Tekrar girin">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">{{ $ilkSifre ? 'Şifreyi Belirle' : 'Şifreyi Güncelle' }}</button>
            </form>
        </div>

        {{-- Bağlı hesaplar --}}
        @if(\App\Support\SocialAuth::anyEnabled() || $socialAccounts->isNotEmpty())
        <div style="background:var(--white);border:1px solid var(--border);border-radius:var(--radius-lg);padding:24px;margin-bottom:20px;">
            <h2 style="font-size:16px;font-weight:700;margin-bottom:6px;">Bağlı Hesaplar</h2>
            <p style="font-size:13px;color:var(--text-muted);margin-bottom:18px;">Google veya Apple hesabınızı bağlarsanız tek dokunuşla giriş yapabilirsiniz.</p>

            @foreach(\App\Models\SocialAccount::PROVIDERS as $provider)
                @continue(! \App\Support\SocialAuth::enabled($provider) && ! $socialAccounts->has($provider))
                @php($hesap = $socialAccounts->get($provider))
                <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding:14px 0;border-bottom:1px solid var(--border);">
                    <div style="min-width:0;">
                        <strong style="display:block;font-size:14px;">{{ \App\Support\SocialAuth::label($provider) }}</strong>
                        <span style="font-size:13px;color:var(--text-muted);word-break:break-all;">
                            {{ $hesap ? ($hesap->email ?: 'Bağlı') : 'Bağlı değil' }}
                        </span>
                    </div>
                    @if($hesap)
                        <form method="POST" action="{{ route('social.destroy', $provider) }}"
                              onsubmit="return confirm('{{ \App\Support\SocialAuth::label($provider) }} bağlantısı kaldırılsın mı?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-outline btn-sm">Kaldır</button>
                        </form>
                    @else
                        <a href="{{ route('social.redirect', $provider) }}" class="btn btn-outline btn-sm" rel="nofollow">Bağla</a>
                    @endif
                </div>
            @endforeach

            @if(! $user->hasPassword())
                <p style="font-size:12px;color:var(--text-muted);margin-top:14px;line-height:1.6;">
                    Şifreniz olmadığı için son bağlı hesabınızı kaldıramazsınız — önce yukarıdan bir şifre belirleyin.
                </p>
            @endif
        </div>
        @endif

        {{-- D4: KVKK — verilerimi indir --}}
        <div style="background:var(--white);border:1px solid var(--border);border-radius:var(--radius-lg);padding:24px;margin-bottom:20px;">
            <h2 style="font-size:16px;font-weight:700;margin-bottom:6px;">Verilerimi İndir</h2>
            <p style="font-size:13px;color:var(--text-muted);margin-bottom:14px;">Profil bilgileriniz, favorileriniz, yorumlarınız, kayıtlı aramalarınız, kupon kullanımlarınız, bildirimleriniz ve AI aramalarınız tek bir JSON dosyası olarak iner.</p>
            <a href="{{ route('profile.data-export') }}" class="btn btn-outline">⬇️ JSON olarak indir</a>
        </div>

        {{-- D4: KVKK — hesabımı sil --}}
        @if($user->isCustomer())
        <div style="background:#fff7f7;border:1px solid #fecaca;border-radius:var(--radius-lg);padding:24px;">
            <h2 style="font-size:16px;font-weight:700;margin-bottom:6px;color:#991b1b;">Hesabımı Sil</h2>
            <p style="font-size:13px;color:var(--text-sec);margin-bottom:14px;line-height:1.6;">
                Talebinizden sonra oturumunuz kapatılır ve hesabınız <strong>{{ \App\Services\Account\AccountDeletionService::WAITING_DAYS }} gün</strong> bekler;
                bu sürede tekrar giriş yaparsanız talep iptal edilir. Süre sonunda adınız, e-postanız ve iletişim bilgileriniz anonimleştirilir;
                yorumlarınız, favorileriniz, kayıtlı aramalarınız ve bildirimleriniz silinir. Bu işlem geri alınamaz.
            </p>
            <form method="POST" action="{{ route('profile.delete') }}" onsubmit="return confirm('Hesap silme talebi oluşturulsun mu? Oturumunuz kapatılacak.')">
                @csrf
                @if($user->hasPassword())
                <div class="form-group">
                    <label>Şifreniz</label>
                    <input type="password" name="password" autocomplete="current-password" placeholder="Onay için şifreniz" required>
@error('password')<p class="p-hata">{{ $message }}</p>@enderror
                </div>
                @else
                {{-- Sosyal giriş ile açılan hesabın şifresi yok; onay için kullanıcı
                     kendi e-posta adresini yazar (yanlışlıkla silmeye karşı). --}}
                <div class="form-group">
                    <label>Onay için e-posta adresinizi yazın</label>
                    <input type="text" name="eposta_onay" autocomplete="off" placeholder="{{ $user->email }}" required>
@error('eposta_onay')<p class="p-hata">{{ $message }}</p>@enderror
                </div>
                @endif
                <label style="display:flex;align-items:flex-start;gap:8px;font-size:13px;margin-bottom:14px;cursor:pointer;">
                    <input type="checkbox" name="onay" value="1"> Sonuçları okudum; hesabımın ve kişisel verilerimin {{ \App\Services\Account\AccountDeletionService::WAITING_DAYS }} gün sonra kalıcı olarak anonimleştirilmesini istiyorum.
                </label>
@error('onay')<p class="p-hata">{{ $message }}</p>@enderror
                <button type="submit" class="btn btn-danger">Hesabımı Sil</button>
            </form>
        </div>
        @endif
    </div>
</div>
@endsection
