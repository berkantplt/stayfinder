@extends('layouts.app')
@section('title', 'Destinasyonlar — Admin')

@section('content')
<div class="container">
    <div>
        @include('partials.admin-sidebar')
        <div class="section" style="padding:0;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;max-width:94%;margin-left:auto;margin-right:auto;">
                <h1 style="font-size:24px;font-weight:700;">Destinasyonlar</h1>
            </div>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:20px;max-width:94%;margin-left:auto;margin-right:auto;">
            @foreach($destinations as $dest)
            <div style="background:var(--white);border:1px solid var(--border-light);border-radius:var(--radius);overflow:hidden;">
                {{-- Preview --}}
                <div style="position:relative;height:160px;background:#f0f0f0;">
                    @if($dest->image)
                        <img src="{{ $dest->image }}" alt="{{ $dest->name }}" style="width:100%;height:100%;object-fit:cover;">
                    @else
                        <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:48px;background:linear-gradient(135deg,var(--accent-bg),#f0fdf4);">🌍</div>
                    @endif
                    <div style="position:absolute;top:8px;right:8px;">
                        <span class="badge {{ $dest->is_active ? 'badge-green' : '' }}" style="{{ !$dest->is_active ? 'background:#fef2f2;color:#991b1b;' : '' }}">{{ $dest->is_active ? 'Aktif' : 'Pasif' }}</span>
                    </div>
                </div>

                {{-- Form --}}
                <form method="POST" action="{{ route('admin.destinations.update', $dest) }}" enctype="multipart/form-data" style="padding:16px;">
                    @csrf @method('PUT')
                    <input type="hidden" name="page" value="{{ $destinations->currentPage() }}">
                    {{-- old()/@error yalnız hatalı gönderimin geldiği karta uygulanır (sayfada 12 form var) --}}
                    @php($buKart = (int) old('dest_id') === $dest->id)
                    @php($eski = fn (string $alan, $varsayilan) => $buKart ? old($alan, $varsayilan) : $varsayilan)
                    <input type="hidden" name="dest_id" value="{{ $dest->id }}">
                    {{-- B17: ad/ülke/açıklama düzenlenebilir, görsel sunucuya yüklenebilir --}}
                    <div class="form-group">
                        <label>Ad</label>
                        <input type="text" name="name" value="{{ $eski('name', $dest->name) }}" required maxlength="100">
@if($buKart)@error('name')<p class="p-hata">{{ $message }}</p>@enderror @endif
                    </div>

                    <div class="form-group">
                        <label>Ülke</label>
                        <input type="text" name="country" value="{{ $eski('country', $dest->country) }}" maxlength="100" placeholder="Türkiye">
@if($buKart)@error('country')<p class="p-hata">{{ $message }}</p>@enderror @endif
                    </div>

                    <div class="form-group">
                        <label>Açıklama</label>
                        <textarea name="description" rows="3" maxlength="2000" placeholder="Kısa tanıtım (ana sayfa kartı ve destinasyon sayfası)">{{ $eski('description', $dest->description) }}</textarea>
@if($buKart)@error('description')<p class="p-hata">{{ $message }}</p>@enderror @endif
                    </div>

                    <div class="form-group">
                        <label>Görsel yükle <span style="font-weight:400;color:var(--text-muted);">(JPG/PNG/WEBP/AVIF, en fazla 8 MB — URL alanına göre önceliklidir)</span></label>
                        <input type="file" name="image_file" accept="image/jpeg,image/png,image/webp,image/avif">
@if($buKart)@error('image_file')<p class="p-hata">{{ $message }}</p>@enderror @endif
                    </div>

                    <div class="form-group">
                        <label>ya da Fotoğraf URL'si</label>
                        <input type="url" name="image" value="{{ $eski('image', \Illuminate\Support\Str::startsWith((string) $dest->image, ['http://', 'https://']) ? $dest->image : '') }}" placeholder="https://images.unsplash.com/...">
                        @if(\Illuminate\Support\Str::startsWith((string) $dest->image, '/storage/'))
                            <div class="p-alt" style="margin-top:4px;">Sunucuya yüklü görsel kullanılıyor ({{ basename($dest->image) }}). Değiştirmek için dosya yükleyin ya da URL girin; boş bırakmak silmez.</div>
                        @endif
@if($buKart)@error('image')<p class="p-hata">{{ $message }}</p>@enderror @endif
                    </div>

                    @if($dest->image)
                        <div class="form-group">
                            <label style="display:flex;align-items:center;gap:8px;font-weight:400;">
                                <input type="checkbox" name="remove_image" value="1"> Mevcut görseli kaldır
                            </label>
                        </div>
                    @endif

                    <div class="form-group">
                        <label>Sıralama</label>
                        <input type="number" name="sort_order" value="{{ $eski('sort_order', $dest->sort_order) }}" min="0">
@if($buKart)@error('sort_order')<p class="p-hata">{{ $message }}</p>@enderror @endif
                    </div>

                    <div style="display:flex;gap:8px;">
                        <button type="submit" class="btn btn-primary btn-sm">Kaydet</button>
                    </div>
                </form>
                <form method="POST" action="{{ route('admin.destinations.toggle', $dest) }}" style="padding:0 16px 16px;">
                    @csrf
                    <input type="hidden" name="page" value="{{ $destinations->currentPage() }}">
                    <button type="submit" class="btn btn-outline btn-sm">{{ $dest->is_active ? 'Pasif Yap' : 'Aktif Yap' }}</button>
                </form>
            </div>
            @endforeach
        </div>

        @if($destinations->total() === 0)
            <div style="max-width:94%;margin:0 auto;padding:40px;text-align:center;color:var(--text-muted);border:1px dashed #cbd5e1;border-radius:16px;">
                Henüz destinasyon kaydı yok.
            </div>
        @endif

        <div style="max-width:94%;margin:24px auto 0;">{{ $destinations->links() }}</div>
        </div>
    </div>
</div>
@endsection
