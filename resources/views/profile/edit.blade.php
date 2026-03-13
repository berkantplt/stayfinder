@extends('layouts.app')
@section('title', 'Profilimi Düzenle — StayFinder')

@section('content')
<div class="container" style="max-width:680px;">
    <div class="section">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;">
            <h1 style="font-size:22px;font-weight:800;">Profilimi Düzenle</h1>
            <a href="{{ route('profile.show') }}" class="btn btn-outline btn-sm">← Geri</a>
        </div>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="alert alert-error">{{ $errors->first() }}</div>
        @endif

        {{-- Profile Form --}}
        <div style="background:var(--white);border:1px solid var(--border);border-radius:var(--radius-lg);padding:24px;margin-bottom:20px;">
            <h2 style="font-size:16px;font-weight:700;margin-bottom:18px;">👤 Kişisel Bilgiler</h2>
            <form method="POST" action="{{ route('profile.update') }}">
                @csrf @method('PUT')
                <div class="form-row">
                    <div class="form-group">
                        <label>Ad Soyad *</label>
                        <input type="text" name="name" value="{{ old('name', $user->name) }}" required>
                    </div>
                    <div class="form-group">
                        <label>Telefon</label>
                        <input type="text" name="phone" value="{{ old('phone', $user->phone) }}" placeholder="05xx xxx xx xx">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Şehir</label>
                        <input type="text" name="city" value="{{ old('city', $user->city) }}" placeholder="İstanbul">
                    </div>
                    <div class="form-group">
                        <label>Doğum Tarihi</label>
                        <input type="date" name="birth_date" value="{{ old('birth_date', $user->birth_date?->format('Y-m-d')) }}">
                    </div>
                </div>
                <div class="form-group">
                    <label>Profil Fotoğrafı URL</label>
                    <input type="url" name="avatar" value="{{ old('avatar', $user->avatar) }}" placeholder="https://...">
                </div>
                <div class="form-group">
                    <label>Hakkımda</label>
                    <textarea name="bio" rows="3" placeholder="Kendinizden kısaca bahsedin...">{{ old('bio', $user->bio) }}</textarea>
                </div>
                <button type="submit" class="btn btn-primary">Bilgileri Kaydet</button>
            </form>
        </div>

        {{-- Password Form --}}
        <div style="background:var(--white);border:1px solid var(--border);border-radius:var(--radius-lg);padding:24px;">
            <h2 style="font-size:16px;font-weight:700;margin-bottom:18px;">🔒 Şifre Değiştir</h2>
            <form method="POST" action="{{ route('profile.password') }}">
                @csrf @method('PUT')
                <div class="form-group">
                    <label>Mevcut Şifre</label>
                    <input type="password" name="current_password" placeholder="••••••••">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Yeni Şifre</label>
                        <input type="password" name="password" placeholder="Min. 6 karakter">
                    </div>
                    <div class="form-group">
                        <label>Şifre Tekrar</label>
                        <input type="password" name="password_confirmation" placeholder="Tekrar girin">
                    </div>
                </div>
                <button type="submit" class="btn btn-outline">Şifreyi Güncelle</button>
            </form>
        </div>
    </div>
</div>
@endsection
