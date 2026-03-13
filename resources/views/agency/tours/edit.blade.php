@extends('layouts.app')
@section('title', 'Tur Düzenle — Acenta Paneli')

@section('content')
<div class="container">
    <div>
        @include('partials.agency-sidebar')
        <div class="section" style="padding:0;">
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:24px;">
                <a href="{{ route('agency.tours.index') }}" class="btn btn-outline btn-sm">← Geri</a>
                <h1 style="font-size:24px;font-weight:800;letter-spacing:-0.5px;color:#0f172a;">Turu Düzenle</h1>
            </div>

        @if($errors->any())
            <div class="alert alert-error">
                @foreach($errors->all() as $e) {{ $e }}<br> @endforeach
            </div>
        @endif

        <div class="stat-card" style="padding:32px;">
        <form method="POST" action="{{ route('agency.tours.update', $tour) }}" enctype="multipart/form-data">
            @csrf @method('PUT')
            <div class="form-group"><label>Tur Adı *</label><input type="text" name="title" value="{{ old('title', $tour->title) }}" required></div>
            <div class="form-group">
                <label>Kategori</label>
                <select name="category_id">
                    <option value="">Kategori Seçin</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}" {{ old('category_id', $tour->category_id) == $cat->id ? 'selected' : '' }}>{{ $cat->icon }} {{ $cat->name }}</option>
                        @foreach($cat->children as $child)
                            <option value="{{ $child->id }}" {{ old('category_id', $tour->category_id) == $child->id ? 'selected' : '' }}>&nbsp;&nbsp;↳ {{ $child->icon }} {{ $child->name }}</option>
                        @endforeach
                    @endforeach
                </select>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Destinasyon *</label><input type="text" name="destination" value="{{ old('destination', $tour->destination) }}" required></div>
                <div class="form-group"><label>Süre (gün) *</label><input type="number" name="duration_days" value="{{ old('duration_days', $tour->duration_days) }}" min="1" required></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Kalkış Tarihi</label><input type="date" name="departure_date" value="{{ old('departure_date', $tour->departure_date?->format('Y-m-d')) }}"></div>
                <div class="form-group"><label>Dönüş Tarihi</label><input type="date" name="return_date" value="{{ old('return_date', $tour->return_date?->format('Y-m-d')) }}"></div>
            </div>
            <div class="form-group"><label>Fiyat (₺) *</label><input type="number" name="price" value="{{ old('price', $tour->price) }}" min="0" step="0.01" required></div>
            <div class="form-group"><label>Açıklama</label><textarea name="description">{{ old('description', $tour->description) }}</textarea></div>
            <div class="form-group"><label>Dahil Olanlar</label><textarea name="included">{{ old('included', $tour->included) }}</textarea></div>
            <div class="form-group"><label>Dahil Olmayanlar</label><textarea name="excluded">{{ old('excluded', $tour->excluded) }}</textarea></div>
            <div class="form-group">
                <label>Tur Resmi</label>
                @if($tour->image)
                    <div style="margin-bottom:8px;"><img src="{{ $tour->image }}" style="max-height:100px;border-radius:8px;" id="currentImg"><span style="font-size:12px;color:var(--text-muted);margin-left:8px;">Mevcut resim</span></div>
                @endif
                <input type="file" name="image" accept="image/*" onchange="previewImg(this)" style="padding:8px;">
                <img id="imgPreview" style="display:none;max-height:120px;border-radius:8px;margin-top:8px;">
            </div>
            <div class="form-group"><label>Tur Linki (Acentanın tur sayfası)</label><input type="url" name="tour_url" value="{{ old('tour_url', $tour->tour_url) }}" placeholder="https://acenta.com/bu-tur"></div>
            <div class="form-group">
                <label><input type="checkbox" name="is_active" value="1" {{ $tour->is_active ? 'checked' : '' }}> Aktif</label>
            </div>
            <div style="display:flex;gap:12px;">
                <button type="submit" class="btn btn-primary">Kaydet</button>
                <a href="{{ route('agency.tours.index') }}" class="btn btn-outline">İptal</a>
            </div>
        </form>
        </div>
<script>function previewImg(i){var r=new FileReader();r.onload=function(e){var p=document.getElementById('imgPreview');p.src=e.target.result;p.style.display='block';var c=document.getElementById('currentImg');if(c)c.style.display='none';};if(i.files[0])r.readAsDataURL(i.files[0]);}</script>
        </div>
    </div>
</div>
@endsection
