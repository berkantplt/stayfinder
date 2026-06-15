@extends('layouts.app')
@section('title', 'Şifremi Unuttum — StayFinder')

@section('content')
@include('partials.auth-background')

<div class="container" style="max-width:460px;padding-top:100px;padding-bottom:100px;position:relative;z-index:10;">
    <div style="background:rgba(255,255,255,0.95);backdrop-filter:blur(16px);border-radius:24px;padding:48px 40px;box-shadow:0 25px 50px -12px rgba(0,0,0,0.25), 0 0 0 1px rgba(255,255,255,0.5);">

        <div style="text-align:center;margin-bottom:32px;">
            <h1 style="font-size:28px;font-weight:800;color:#0f172a;margin-bottom:8px;">Şifremi Unuttum</h1>
            <p style="color:#475569;font-size:15px;">E-posta adresinizi girin, size şifre sıfırlama bağlantısı gönderelim.</p>
        </div>

        @if(session('success'))
            <div class="alert alert-success" style="margin-bottom:24px;">{{ session('success') }}</div>
        @endif

        @if($errors->any())
            <div class="alert alert-error" style="margin-bottom:24px;">
                @foreach($errors->all() as $error) {{ $error }}<br> @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('password.email') }}">
            @csrf
            <div class="form-group" style="margin-bottom:28px;">
                <label style="font-size:14px;font-weight:600;color:#334155;margin-bottom:8px;display:block;">E-posta</label>
                <input type="email" name="email" value="{{ old('email') }}" required autofocus style="width:100%;padding:14px 16px;border-radius:12px;border:1px solid #cbd5e1;background:#f8fafc;font-size:15px;outline:none;transition:all 0.2s;">
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:14px;font-size:16px;font-weight:700;border-radius:12px;box-shadow:0 4px 12px rgba(13, 116, 144, 0.3);">Sıfırlama Bağlantısı Gönder</button>
        </form>

        <div style="text-align:center;margin-top:28px;font-size:14px;color:#475569;">
            Şifrenizi hatırladınız mı? <a href="{{ route('login') }}" style="color:var(--accent);font-weight:700;">Giriş Yap</a>
        </div>
    </div>
</div>
@endsection
