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

        {{-- Şifre --}}
        <div style="background:var(--white);border:1px solid var(--border);border-radius:var(--radius-lg);padding:24px;margin-bottom:20px;">
            <h2 style="font-size:16px;font-weight:700;margin-bottom:6px;">Şifre Değiştir</h2>
            <p style="font-size:13px;color:var(--text-muted);margin-bottom:18px;">Şifreniz değişince diğer cihazlardaki oturumlarınız kapatılır.</p>
            <form method="POST" action="{{ route('profile.password') }}">
                @csrf @method('PUT')
                <div class="form-group">
                    <label>Mevcut Şifre</label>
                    <input type="password" name="current_password" autocomplete="current-password" placeholder="••••••••">
@error('current_password')<p class="p-hata">{{ $message }}</p>@enderror
                </div>
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
                <button type="submit" class="btn btn-primary">Şifreyi Güncelle</button>
            </form>
        </div>

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
                <div class="form-group">
                    <label>Şifreniz</label>
                    <input type="password" name="password" autocomplete="current-password" placeholder="Onay için şifreniz" required>
@error('password')<p class="p-hata">{{ $message }}</p>@enderror
                </div>
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
