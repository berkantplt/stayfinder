@extends('layouts.app')
@section('title', 'Acenta Ekle — Admin')

@section('content')
<div class="container">
        @include('partials.breadcrumb', ['schema' => false, 'root' => ['name' => 'Admin', 'url' => route('admin.dashboard')], 'items' => [['name' => 'Acentalar', 'url' => route('admin.agencies')], ['name' => 'Yeni Acenta']]])
    <div class="section">
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:24px;">
            <a href="{{ route('admin.agencies') }}" class="btn btn-outline btn-sm">← Geri</a>
            <h1 style="font-size:24px;font-weight:700;">Yeni Acenta Ekle</h1>
        </div>

        @include('partials.form-errors')

        <form method="POST" action="{{ route('admin.agencies.store') }}">
            @csrf
            <div class="form-group"><label>Acenta Adı *</label><input type="text" name="name" value="{{ old('name') }}" required>
@error('name')<p class="p-hata">{{ $message }}</p>@enderror</div>
            <div class="form-row">
                <div class="form-group"><label>Telefon</label><input type="text" name="phone" value="{{ old('phone') }}">
@error('phone')<p class="p-hata">{{ $message }}</p>@enderror</div>
                <div class="form-group">
                    <label>E-posta *</label>
                    <input type="email" name="email" value="{{ old('email') }}" required>
@error('email')<p class="p-hata">{{ $message }}</p>@enderror
                    <small style="color:#64748b;font-size:12px;">Panel giriş adresi olur. Şifre otomatik üretilip kaydettikten sonra bir kez gösterilir.</small>
                </div>
            </div>
            <div class="form-group"><label>Web Sitesi</label><input type="url" name="website_url" value="{{ old('website_url') }}">
@error('website_url')<p class="p-hata">{{ $message }}</p>@enderror</div>
            <div class="form-group"><label>Açıklama</label><textarea name="description">{{ old('description') }}</textarea>
@error('description')<p class="p-hata">{{ $message }}</p>@enderror</div>
            <button type="submit" class="btn btn-primary">Acenta Oluştur</button>
        </form>
    </div>
</div>
@endsection
