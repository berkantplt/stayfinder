@extends('layouts.app')
@section('title', 'Banner Yönetimi — Admin')

@section('content')
<div class="container">
    <div>
        @include('partials.admin-sidebar')
        <div class="section" style="padding:0;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:30px;">
                <div style="display:flex;align-items:center;gap:12px;">
                    <div style="width:40px;height:40px;background:var(--accent-bg);color:var(--accent-dark);border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:18px;">🖼️</div>
                    <h1 style="font-size:26px;font-weight:800;letter-spacing:-0.5px;color:#0f172a;">Banner Yönetimi</h1>
                </div>
            </div>

            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if($errors->any())
                <div class="alert alert-error">@foreach($errors->all() as $e) {{ $e }}<br> @endforeach</div>
            @endif

            {{-- Add New Banner --}}
            <div class="stat-card" style="padding:30px;margin-bottom:32px;border-left:4px solid var(--accent) !important;">
                <h3 style="font-size:18px;font-weight:800;letter-spacing:-0.3px;color:#0f172a;margin-bottom:24px;">Yeni Banner Ekle</h3>
                <form method="POST" action="{{ route('admin.banners.store') }}" enctype="multipart/form-data">
                    @csrf
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;">
                        <div class="form-group" style="margin-bottom:0;">
                            <label style="font-size:13px;color:#475569;">Banner Başlığı <span style="color:#ef4444">*</span></label>
                            <input type="text" name="title" required placeholder="Ör: Kapadokya" value="{{ old('title') }}" style="padding:14px;background:#f8fafc;">
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label style="font-size:13px;color:#475569;">Görsel <span style="color:#ef4444">*</span></label>
                            <input type="file" name="image" accept="image/*" required style="padding:11px;background:#f8fafc;">
                            <div style="font-size:11px;color:#64748b;margin-top:6px;">Önerilen boyut: En az 1920x1080 piksel (Full HD)</div>
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label style="font-size:13px;color:#475569;">Bulanıklık (Blur): <span id="newBlurVal" style="font-weight:700;color:var(--accent);">0px</span></label>
                            <input type="range" name="blur" min="0" max="20" value="0" oninput="document.getElementById('newBlurVal').textContent=this.value+'px'" style="width:100%;accent-color:var(--accent);">
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label style="font-size:13px;color:#475569;">Karanlık (Overlay): <span id="newDarkVal" style="font-weight:700;color:var(--accent);">40%</span></label>
                            <input type="range" name="darkness" min="0" max="100" value="40" oninput="document.getElementById('newDarkVal').textContent=this.value+'%'" style="width:100%;accent-color:var(--accent);">
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label style="font-size:13px;color:#475569;">Sıralama</label>
                            <input type="number" name="sort_order" value="0" style="padding:14px;background:#f8fafc;">
                        </div>
                    </div>
                    <div style="margin-top:24px;display:flex;justify-content:flex-end;">
                        <button type="submit" class="btn btn-primary" style="padding:14px 32px;font-size:15px;">Banner Ekle</button>
                    </div>
                </form>
            </div>

            {{-- Existing Banners --}}
            <div class="stat-card" style="padding:30px;">
                <h3 style="font-size:18px;font-weight:800;margin-bottom:24px;color:#0f172a;letter-spacing:-0.3px;">Mevcut Bannerlar ({{ $banners->count() }})</h3>

                @forelse($banners as $banner)
                <div style="border:1px solid #e2e8f0;border-radius:16px;padding:0;margin-bottom:20px;overflow:hidden;{{ !$banner->is_active ? 'opacity:0.5;' : '' }} transition:all 0.2s;">
                    {{-- Preview --}}
                    <div id="preview-{{ $banner->id }}" style="position:relative;height:200px;overflow:hidden;">
                        <img src="{{ $banner->image_url }}" alt="{{ $banner->title }}" style="width:100%;height:100%;object-fit:cover;filter:blur({{ $banner->blur }}px);">
                        <div id="overlay-{{ $banner->id }}" style="position:absolute;inset:0;background:rgba(0,0,0,{{ $banner->darkness / 100 }});display:flex;align-items:center;padding:0 32px;">
                            <div>
                                <div style="font-size:20px;font-weight:800;color:white;text-shadow:0 2px 8px rgba(0,0,0,0.3);">{{ $banner->title }}</div>
                                <div style="font-size:12px;color:rgba(255,255,255,0.7);margin-top:4px;">
                                    {{ $banner->is_active ? '✅ Aktif' : '❌ Pasif' }} · Sıra: {{ $banner->sort_order }} · Blur: {{ $banner->blur }}px · Karanlık: {{ $banner->darkness }}%
                                </div>
                            </div>
                        </div>
                    </div>
                    {{-- Edit Form --}}
                    <form method="POST" action="{{ route('admin.banners.update', $banner) }}" enctype="multipart/form-data" style="padding:24px;">
                        @csrf @method('PUT')
                        <div style="display:grid;grid-template-columns:1.5fr 1fr 1fr;gap:20px;align-items:end;">
                            <div class="form-group" style="margin-bottom:0;">
                                <label style="font-size:12px;color:#475569;">Başlık</label>
                                <input type="text" name="title" value="{{ $banner->title }}" required style="padding:12px;background:#f8fafc;">
                            </div>
                            <div class="form-group" style="margin-bottom:0;">
                                <label style="font-size:12px;color:#475569;">Görsel Değiştir</label>
                                <input type="file" name="image" accept="image/*" style="padding:9px;background:#f8fafc;">
                            </div>
                            <div class="form-group" style="margin-bottom:0;">
                                <label style="font-size:12px;color:#475569;">Sıralama</label>
                                <input type="number" name="sort_order" value="{{ $banner->sort_order }}" style="padding:12px;background:#f8fafc;">
                            </div>
                        </div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-top:16px;">
                            <div class="form-group" style="margin-bottom:0;">
                                <label style="font-size:12px;color:#475569;">Bulanıklık: <span id="blurVal{{ $banner->id }}" style="font-weight:700;color:var(--accent);">{{ $banner->blur }}px</span></label>
                                <input type="range" name="blur" min="0" max="20" value="{{ $banner->blur }}"
                                    oninput="document.getElementById('blurVal{{ $banner->id }}').textContent=this.value+'px';document.querySelector('#preview-{{ $banner->id }} img').style.filter='blur('+this.value+'px)'"
                                    style="width:100%;accent-color:var(--accent);">
                            </div>
                            <div class="form-group" style="margin-bottom:0;">
                                <label style="font-size:12px;color:#475569;">Karanlık: <span id="darkVal{{ $banner->id }}" style="font-weight:700;color:var(--accent);">{{ $banner->darkness }}%</span></label>
                                <input type="range" name="darkness" min="0" max="100" value="{{ $banner->darkness }}"
                                    oninput="document.getElementById('darkVal{{ $banner->id }}').textContent=this.value+'%';document.getElementById('overlay-{{ $banner->id }}').style.background='rgba(0,0,0,'+(this.value/100)+')'"
                                    style="width:100%;accent-color:var(--accent);">
                            </div>
                        </div>
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-top:20px;gap:12px;flex-wrap:wrap;">
                            <div style="display:flex;gap:8px;">
                                <button type="submit" class="btn btn-primary btn-sm">Kaydet</button>
                            </div>
                            <div style="display:flex;gap:8px;">
                                <a href="{{ route('admin.banners.toggle', $banner) }}" class="btn btn-outline btn-sm" onclick="event.preventDefault();document.getElementById('toggleForm{{ $banner->id }}').submit();">
                                    {{ $banner->is_active ? '⏸️ Pasifleştir' : '▶️ Aktifleştir' }}
                                </a>
                                <button type="button" class="btn btn-danger btn-sm" onclick="if(confirm('Bu banner silinsin mi?'))document.getElementById('deleteForm{{ $banner->id }}').submit();">
                                    🗑️ Sil
                                </button>
                            </div>
                        </div>
                    </form>
                    {{-- Hidden forms --}}
                    <form id="toggleForm{{ $banner->id }}" method="POST" action="{{ route('admin.banners.toggle', $banner) }}" style="display:none;">@csrf @method('PATCH')</form>
                    <form id="deleteForm{{ $banner->id }}" method="POST" action="{{ route('admin.banners.destroy', $banner) }}" style="display:none;">@csrf @method('DELETE')</form>
                </div>
                @empty
                <div style="text-align:center;color:#64748b;font-size:14px;padding:48px;background:#f8fafc;border-radius:16px;border:1px dashed #cbd5e1;">
                    <div style="font-size:32px;margin-bottom:12px;">🖼️</div>
                    <div style="font-weight:600;color:#0f172a;margin-bottom:4px;">Henüz banner yok</div>
                    <div>Yukarıdaki formdan yeni bir banner ekleyebilirsiniz.</div>
                </div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
