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

        @stack('security-sections')
    </div>
</div>
@endsection
