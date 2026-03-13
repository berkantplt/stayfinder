@extends('layouts.app')
@section('title', 'Profil Düzenle — ' . $agency->name)

@section('content')
<div class="container">
    <div>
        @include('partials.agency-sidebar')
        <div class="section" style="padding:0;">
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:28px;">
                <h1 style="font-size:24px;font-weight:800;letter-spacing:-0.5px;color:#0f172a;">Acenta Profili</h1>
            </div>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @if($errors->any())
            <div class="alert alert-error">@foreach($errors->all() as $e) {{ $e }}<br> @endforeach</div>
        @endif

        <div class="stat-card" style="padding:32px;">
            <form method="POST" action="{{ route('agency.profile.update') }}" enctype="multipart/form-data">
                @csrf @method('PUT')

                {{-- Logo --}}
                <div style="text-align:center;margin-bottom:28px;padding-bottom:28px;border-bottom:1px solid #f1f5f9;">
                    <div style="margin-bottom:12px;">
                        @if($agency->logo)
                            <img src="{{ $agency->logo }}" alt="Logo" id="logoPreview" style="width:96px;height:96px;border-radius:16px;object-fit:cover;box-shadow:0 4px 12px rgba(0,0,0,0.08);">
                        @else
                            <div id="logoPlaceholder" style="width:96px;height:96px;background:linear-gradient(135deg,#10b981,#059669);border-radius:16px;display:inline-flex;align-items:center;justify-content:center;font-size:40px;box-shadow:0 4px 12px rgba(16,185,129,0.3);">🏢</div>
                            <img id="logoPreview" style="display:none;width:96px;height:96px;border-radius:16px;object-fit:cover;">
                        @endif
                    </div>
                    <label class="btn btn-outline btn-sm" style="cursor:pointer;">
                        📷 Logo Yükle
                        <input type="file" name="logo" accept="image/*" style="display:none;" onchange="previewLogo(this)">
                    </label>
                    <div style="font-size:11px;color:#94a3b8;margin-top:6px;">Max 2MB · JPG, PNG</div>
                </div>

                {{-- Info --}}
                <div class="form-group">
                    <label>Acenta Adı *</label>
                    <input type="text" name="name" value="{{ old('name', $agency->name) }}" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Telefon</label>
                        <input type="text" name="phone" value="{{ old('phone', $agency->phone) }}" placeholder="+90 555 123 4567">
                    </div>
                    <div class="form-group">
                        <label>E-posta</label>
                        <input type="email" name="email" value="{{ old('email', $agency->email) }}">
                    </div>
                </div>

                <div class="form-group">
                    <label>Web Sitesi</label>
                    <input type="url" name="website_url" value="{{ old('website_url', $agency->website_url) }}" placeholder="https://acenta.com">
                </div>

                <div class="form-group">
                    <label>Adres</label>
                    <input type="text" name="address" value="{{ old('address', $agency->address) }}" placeholder="İstanbul, Türkiye">
                </div>

                <div class="form-group">
                    <label>Hakkımızda</label>
                    <textarea name="description" rows="4" placeholder="Acentanızı tanıtın...">{{ old('description', $agency->description) }}</textarea>
                </div>

                <button type="submit" class="btn btn-primary">Profili Güncelle</button>
            </form>
        </div>
    </div>
</div>

<script>
function previewLogo(i) {
    var r = new FileReader();
    r.onload = function(e) {
        var p = document.getElementById('logoPreview');
        p.src = e.target.result;
        p.style.display = 'inline-block';
        var ph = document.getElementById('logoPlaceholder');
        if (ph) ph.style.display = 'none';
    };
    if (i.files[0]) r.readAsDataURL(i.files[0]);
}
</script>
@endsection
