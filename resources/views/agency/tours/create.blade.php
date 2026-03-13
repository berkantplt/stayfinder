@extends('layouts.app')
@section('title', 'Tur Ekle — Acenta Paneli')

@section('content')
<div class="container">
    <div>
        @include('partials.agency-sidebar')
        <div class="section" style="padding:24px 0 0 0;">
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:24px;max-width:94%;margin-left:auto;margin-right:auto;">
                <a href="{{ route('agency.tours.index') }}" class="btn btn-outline btn-sm">← Geri</a>
                <h1 style="font-size:24px;font-weight:800;letter-spacing:-0.5px;color:#0f172a;">Yeni Tur Ekle</h1>
            </div>

        @if($errors->any())
            <div class="alert alert-error" style="max-width:94%;margin-left:auto;margin-right:auto;">
                @foreach($errors->all() as $e) {{ $e }}<br> @endforeach
            </div>
        @endif

        <div class="stat-card" style="padding:32px;max-width:94%;margin-left:auto;margin-right:auto;">
        <form method="POST" action="{{ route('agency.tours.store') }}" enctype="multipart/form-data">
            @csrf
            <div class="form-group"><label>Tur Adı *</label><input type="text" name="title" value="{{ old('title') }}" required></div>
            <div class="form-group">
                <label>Kategori</label>
                <select name="category_id">
                    <option value="">Kategori Seçin</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}" {{ old('category_id') == $cat->id ? 'selected' : '' }}>{{ $cat->icon }} {{ $cat->name }}</option>
                        @foreach($cat->children as $child)
                            <option value="{{ $child->id }}" {{ old('category_id') == $child->id ? 'selected' : '' }}>&nbsp;&nbsp;↳ {{ $child->icon }} {{ $child->name }}</option>
                        @endforeach
                    @endforeach
                </select>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Destinasyon *</label><input type="text" name="destination" value="{{ old('destination') }}" required placeholder="Ör: Antalya"></div>
                <div class="form-group"><label>Süre (gün) *</label><input type="number" name="duration_days" value="{{ old('duration_days', 1) }}" min="1" required></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Kalkış Tarihi</label><input type="date" name="departure_date" value="{{ old('departure_date') }}"></div>
                <div class="form-group"><label>Dönüş Tarihi</label><input type="date" name="return_date" value="{{ old('return_date') }}"></div>
            </div>
            <div class="form-group"><label>Fiyat (₺) *</label><input type="number" name="price" value="{{ old('price') }}" min="0" step="0.01" required></div>
            <div class="form-group"><label>Açıklama</label><textarea name="description">{{ old('description') }}</textarea></div>
            <div class="form-group"><label>Dahil Olanlar (her satıra bir madde)</label><textarea name="included">{{ old('included') }}</textarea></div>
            <div class="form-group"><label>Dahil Olmayanlar</label><textarea name="excluded">{{ old('excluded') }}</textarea></div>
            <div class="form-group">
                <label>Tur Resmi</label>
                <input type="file" name="image" accept="image/*" onchange="previewImg(this)" style="padding:8px;">
                <img id="imgPreview" style="display:none;max-height:120px;border-radius:8px;margin-top:8px;">
            </div>
            <div class="form-group"><label>Tur Linki (Acentanın tur sayfası)</label><input type="url" name="tour_url" value="{{ old('tour_url') }}" placeholder="https://acenta.com/bu-tur"></div>
            <div style="display:flex;gap:12px;">
                <button type="submit" class="btn btn-primary">Turu Kaydet</button>
                <a href="{{ route('agency.tours.index') }}" class="btn btn-outline">İptal</a>
            </div>
        </form>
        </div>
<script>function previewImg(i){var r=new FileReader();r.onload=function(e){var p=document.getElementById('imgPreview');p.src=e.target.result;p.style.display='block';};if(i.files[0])r.readAsDataURL(i.files[0]);}</script>
        </div>
    </div>
</div>
@endsection
