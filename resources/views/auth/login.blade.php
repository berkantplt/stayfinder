@extends('layouts.app')
@section('title', 'Giriş Yap — StayFinder')

@section('content')
@include('partials.auth-background')

<div class="container" style="max-width:460px;padding-top:100px;padding-bottom:100px;position:relative;z-index:10;">
    <div style="background:rgba(255,255,255,0.95);backdrop-filter:blur(16px);border-radius:24px;padding:48px 40px;box-shadow:0 25px 50px -12px rgba(0,0,0,0.25), 0 0 0 1px rgba(255,255,255,0.5);">
        
        <div style="text-align:center;margin-bottom:32px;">
            <h1 style="font-size:28px;font-weight:800;color:#0f172a;margin-bottom:8px;">Giriş Yap</h1>
            <p style="color:#475569;font-size:15px;">Hesabınıza giriş yapın</p>
        </div>

        @if($errors->any())
            <div class="alert alert-error" style="margin-bottom:24px;">
                @foreach($errors->all() as $error) {{ $error }}<br> @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('login.post') }}">
            @csrf
            <div class="form-group" style="margin-bottom:20px;">
                <label style="font-size:14px;font-weight:600;color:#334155;margin-bottom:8px;display:block;">E-posta</label>
                <input type="email" name="email" value="{{ old('email') }}" required autofocus style="width:100%;padding:14px 16px;border-radius:12px;border:1px solid #cbd5e1;background:#f8fafc;font-size:15px;outline:none;transition:all 0.2s;">
            </div>
            <div class="form-group" style="margin-bottom:28px;">
                <label style="font-size:14px;font-weight:600;color:#334155;margin-bottom:8px;display:block;">Şifre</label>
                <input type="password" name="password" required style="width:100%;padding:14px 16px;border-radius:12px;border:1px solid #cbd5e1;background:#f8fafc;font-size:15px;outline:none;transition:all 0.2s;">
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:14px;font-size:16px;font-weight:700;border-radius:12px;box-shadow:0 4px 12px rgba(13, 116, 144, 0.3);">Giriş Yap</button>
        </form>

        <div style="text-align:center;margin-top:16px;font-size:14px;">
            <a href="{{ route('password.request') }}" style="color:var(--accent);font-weight:600;">Şifremi unuttum</a>
        </div>

        <div style="text-align:center;margin-top:28px;font-size:14px;color:#475569;">
            Hesabınız yok mu? <a href="{{ route('register') }}" style="color:var(--accent);font-weight:700;">Kayıt Ol</a>
        </div>

        <div style="text-align:center;margin-top:10px;font-size:14px;color:#475569;">
            Acenta mısınız? <a href="{{ route('agency.register') }}" style="color:var(--accent);font-weight:700;">Acenta hesabı oluşturun</a>
        </div>

        <div style="text-align:center;margin-top:20px;font-size:13px;color:#64748b;padding-top:20px;border-top:1px solid #e2e8f0;">
            Demo: <strong>admin@stayfinder.com</strong> / password
        </div>
    </div>
</div>
@endsection
