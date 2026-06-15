@extends('layouts.app')
@section('title', 'Şifre Sıfırla — StayFinder')

@section('content')
@include('partials.auth-background')

<div class="container" style="max-width:460px;padding-top:100px;padding-bottom:100px;position:relative;z-index:10;">
    <div style="background:rgba(255,255,255,0.95);backdrop-filter:blur(16px);border-radius:24px;padding:48px 40px;box-shadow:0 25px 50px -12px rgba(0,0,0,0.25), 0 0 0 1px rgba(255,255,255,0.5);">

        <div style="text-align:center;margin-bottom:32px;">
            <h1 style="font-size:28px;font-weight:800;color:#0f172a;margin-bottom:8px;">Yeni Şifre Belirle</h1>
            <p style="color:#475569;font-size:15px;">Hesabınız için yeni bir şifre oluşturun.</p>
        </div>

        @if($errors->any())
            <div class="alert alert-error" style="margin-bottom:24px;">
                @foreach($errors->all() as $error) {{ $error }}<br> @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('password.update') }}">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <div class="form-group" style="margin-bottom:20px;">
                <label style="font-size:14px;font-weight:600;color:#334155;margin-bottom:8px;display:block;">E-posta</label>
                <input type="email" name="email" value="{{ old('email', $email) }}" required style="width:100%;padding:14px 16px;border-radius:12px;border:1px solid #cbd5e1;background:#f8fafc;font-size:15px;outline:none;transition:all 0.2s;">
            </div>
            <div class="form-group" style="margin-bottom:20px;">
                <label style="font-size:14px;font-weight:600;color:#334155;margin-bottom:8px;display:block;">Yeni Şifre</label>
                <input type="password" name="password" required autocomplete="new-password" style="width:100%;padding:14px 16px;border-radius:12px;border:1px solid #cbd5e1;background:#f8fafc;font-size:15px;outline:none;transition:all 0.2s;">
            </div>
            <div class="form-group" style="margin-bottom:28px;">
                <label style="font-size:14px;font-weight:600;color:#334155;margin-bottom:8px;display:block;">Yeni Şifre (Tekrar)</label>
                <input type="password" name="password_confirmation" required autocomplete="new-password" style="width:100%;padding:14px 16px;border-radius:12px;border:1px solid #cbd5e1;background:#f8fafc;font-size:15px;outline:none;transition:all 0.2s;">
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:14px;font-size:16px;font-weight:700;border-radius:12px;box-shadow:0 4px 12px rgba(13, 116, 144, 0.3);">Şifreyi Güncelle</button>
        </form>

        <div style="text-align:center;margin-top:28px;font-size:14px;color:#475569;">
            <a href="{{ route('login') }}" style="color:var(--accent);font-weight:700;">Girişe Dön</a>
        </div>
    </div>
</div>
@endsection
