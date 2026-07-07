@extends('layouts.app')
@section('title', 'Profilimi Düzenle — turXtur')

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
            <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data">
                @csrf @method('PUT')

                {{-- Avatar --}}
                <div class="form-group">
                    <label>Profil Fotoğrafı</label>
                    <div style="display:flex;align-items:center;gap:16px;margin-bottom:8px;">
                        @php
                            $avatarUrl = $user->avatar
                                ? (\Illuminate\Support\Str::startsWith($user->avatar, ['http://','https://']) ? $user->avatar : asset('storage/'.$user->avatar))
                                : null;
                        @endphp
                        @if($avatarUrl)
                            <img src="{{ $avatarUrl }}" alt="avatar" style="width:64px;height:64px;border-radius:50%;object-fit:cover;border:2px solid var(--border);">
                        @else
                            <div style="width:64px;height:64px;border-radius:50%;background:var(--accent-bg);display:flex;align-items:center;justify-content:center;font-size:24px;font-weight:800;color:var(--accent);border:2px solid var(--border);">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</div>
                        @endif
                        <div style="flex:1;">
                            <input type="file" name="avatar_file" accept="image/jpeg,image/png,image/webp">
                            <div style="font-size:12px;color:var(--text-muted);margin-top:4px;">JPG, PNG veya WEBP — max 2 MB</div>
                            @if($avatarUrl)
                                <label style="display:inline-flex;align-items:center;gap:6px;margin-top:8px;font-size:13px;color:#dc2626;cursor:pointer;">
                                    <input type="checkbox" name="remove_avatar" value="1"> Mevcut fotoğrafı kaldır
                                </label>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Ad Soyad *</label>
                        <input type="text" name="name" value="{{ old('name', $user->name) }}" required>
                    </div>
                    <div class="form-group">
                        <label>E-posta *</label>
                        <input type="email" name="email" value="{{ old('email', $user->email) }}" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Telefon</label>
                        <input type="text" name="phone" value="{{ old('phone', $user->phone) }}" placeholder="05xx xxx xx xx">
                    </div>
                    <div class="form-group">
                        <label>Şehir</label>
                        <input type="text" name="city" value="{{ old('city', $user->city) }}" placeholder="İstanbul">
                    </div>
                </div>
                <div class="form-group">
                    <label>Doğum Tarihi</label>
                    <input type="date" name="birth_date" value="{{ old('birth_date', $user->birth_date?->format('Y-m-d')) }}">
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
                        <input type="password" name="password" placeholder="Min. 8 karakter">
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
