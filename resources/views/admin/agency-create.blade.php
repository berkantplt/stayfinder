@extends('layouts.app')
@section('title', 'Acenta Ekle — Admin')

@section('content')
<div class="container">
    <div class="section">
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:24px;">
            <a href="{{ route('admin.agencies') }}" class="btn btn-outline btn-sm">← Geri</a>
            <h1 style="font-size:24px;font-weight:700;">Yeni Acenta Ekle</h1>
        </div>

        @if($errors->any())
            <div class="alert alert-error">@foreach($errors->all() as $e) {{ $e }}<br> @endforeach</div>
        @endif

        <form method="POST" action="{{ route('admin.agencies.store') }}">
            @csrf
            <div class="form-group"><label>Acenta Adı *</label><input type="text" name="name" value="{{ old('name') }}" required></div>
            <div class="form-row">
                <div class="form-group"><label>Telefon</label><input type="text" name="phone" value="{{ old('phone') }}"></div>
                <div class="form-group">
                    <label>E-posta *</label>
                    <input type="email" name="email" value="{{ old('email') }}" required>
                    <small style="color:#64748b;font-size:12px;">Panel giriş adresi olur. Şifre otomatik üretilip kaydettikten sonra bir kez gösterilir.</small>
                </div>
            </div>
            <div class="form-group"><label>Web Sitesi</label><input type="url" name="website_url" value="{{ old('website_url') }}"></div>
            <div class="form-group"><label>Açıklama</label><textarea name="description">{{ old('description') }}</textarea></div>
            <button type="submit" class="btn btn-primary">Acenta Oluştur</button>
        </form>
    </div>
</div>
@endsection
