@extends('layouts.app')
@section('title', 'Öne Çıkan Şehirler (Story) — Admin')

@section('content')
<div class="container">
    <div>
        @include('partials.admin-sidebar')
        <div class="section" style="padding:0;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:30px;">
                <div style="display:flex;align-items:center;gap:12px;">
                    <div style="width:40px;height:40px;background:var(--accent-bg);color:var(--accent-dark);border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:18px;">🏙️</div>
                    <h1 style="font-size:26px;font-weight:800;letter-spacing:-0.5px;color:#0f172a;">Öne Çıkan Şehirler (Story)</h1>
                </div>
            </div>

            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if($errors->any())
                <div class="alert alert-error">@foreach($errors->all() as $e) {{ $e }}<br> @endforeach</div>
            @endif

            {{-- Add New City --}}
            <div class="stat-card" style="padding:30px;margin-bottom:32px;border-left:4px solid var(--accent) !important;">
                <h3 style="font-size:18px;font-weight:800;letter-spacing:-0.3px;color:#0f172a;margin-bottom:24px;">Yeni Şehir Ekle</h3>
                <form method="POST" action="{{ route('admin.featured_cities.store') }}">
                    @csrf
                    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:24px;">
                        <div class="form-group" style="margin-bottom:0;">
                            <label style="font-size:13px;color:#475569;">Şehir Adı <span style="color:#ef4444">*</span></label>
                            <input type="text" name="name" required placeholder="Ör: Paris" value="{{ old('name') }}" style="padding:14px;background:#f8fafc;">
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label style="font-size:13px;color:#475569;">Ülke <span style="color:#ef4444">*</span></label>
                            <input type="text" name="country" required placeholder="Ör: Fransa" value="{{ old('country') }}" style="padding:14px;background:#f8fafc;">
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label style="font-size:13px;color:#475569;">Sıralama</label>
                            <input type="number" name="sort_order" value="0" style="padding:14px;background:#f8fafc;">
                        </div>
                    </div>
                    <div class="form-group" style="margin-top:20px;margin-bottom:0;">
                        <label style="font-size:13px;color:#475569;">Turları Gör Bağlantısı <span style="color:#94a3b8;font-weight:400;">(boşsa şehir adıyla arama yapılır)</span></label>
                        <input type="text" name="link" placeholder="Ör: /turlar?destination=Paris veya /turlar?q=Paris" value="{{ old('link') }}" style="padding:14px;background:#f8fafc;">
                    </div>
                    <div style="margin-top:24px;display:flex;justify-content:flex-end;">
                        <button type="submit" class="btn btn-primary" style="padding:14px 32px;font-size:15px;">Şehir Ekle</button>
                    </div>
                </form>
            </div>

            {{-- Existing Cities --}}
            <div class="stat-card" style="padding:30px;">
                <h3 style="font-size:18px;font-weight:800;margin-bottom:24px;color:#0f172a;letter-spacing:-0.3px;">Mevcut Şehirler ({{ $cities->count() }})</h3>

                @forelse($cities as $city)
                <div style="border:1px solid #e2e8f0;border-radius:16px;padding:24px;margin-bottom:30px;transition:all 0.2s;{{ !$city->is_active ? 'opacity:0.6;' : '' }}">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:20px;">
                        <div>
                            <h4 style="font-size:18px;font-weight:800;color:#0f172a;">{{ $city->name }} <span style="font-size:14px;font-weight:500;color:#64748b;margin-left:8px;">{{ $city->country }}</span></h4>
                            <p style="font-size:12px;color:#64748b;margin-top:4px;">Sıra: {{ $city->sort_order }} · {{ $city->is_active ? '✅ Aktif' : '❌ Pasif' }}</p>
                        </div>
                        <div style="display:flex;gap:8px;">
                            <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('edit-city-{{ $city->id }}').style.display='block'">Düzenle</button>
                            <form action="{{ route('admin.featured_cities.destroy', $city) }}" method="POST" onsubmit="return confirm('Bu şehri ve TÜM görsellerini silmek istediğinize emin misiniz?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-sm">Sil</button>
                            </form>
                        </div>
                    </div>

                    {{-- Edit City Overlay (Hidden by default) --}}
                    <div id="edit-city-{{ $city->id }}" style="display:none;background:#f1f5f9;padding:20px;border-radius:12px;margin-bottom:20px;">
                        <form method="POST" action="{{ route('admin.featured_cities.update', $city) }}">
                            @csrf @method('PUT')
                            <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:15px;align-items:end;">
                                <div class="form-group" style="margin-bottom:0;">
                                    <label style="font-size:12px;">Şehir</label>
                                    <input type="text" name="name" value="{{ $city->name }}" required style="padding:10px;">
                                </div>
                                <div class="form-group" style="margin-bottom:0;">
                                    <label style="font-size:12px;">Ülke</label>
                                    <input type="text" name="country" value="{{ $city->country }}" required style="padding:10px;">
                                </div>
                                <div class="form-group" style="margin-bottom:0;">
                                    <label style="font-size:12px;">Sıra</label>
                                    <input type="number" name="sort_order" value="{{ $city->sort_order }}" style="padding:10px;">
                                </div>
                                <div class="form-group" style="margin-bottom:0;">
                                    <label style="font-size:12px;">Durum</label>
                                    <select name="is_active" style="padding:10px;">
                                        <option value="1" {{ $city->is_active ? 'selected' : '' }}>Aktif</option>
                                        <option value="0" {{ !$city->is_active ? 'selected' : '' }}>Pasif</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group" style="margin-top:12px;margin-bottom:0;">
                                <label style="font-size:12px;">Turları Gör Bağlantısı <span style="color:#94a3b8;">(boşsa şehir adıyla arama)</span></label>
                                <input type="text" name="link" value="{{ $city->link }}" placeholder="/turlar?destination={{ $city->name }}" style="padding:10px;">
                            </div>
                            <div style="margin-top:15px;display:flex;justify-content:flex-end;gap:10px;">
                                <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('edit-city-{{ $city->id }}').style.display='none'">İptal</button>
                                <button type="submit" class="btn btn-primary btn-sm">Güncelle</button>
                            </div>
                        </form>
                    </div>

                    {{-- Image Management for this City --}}
                    <div style="background:#f8fafc;padding:20px;border-radius:12px;border:1px solid #e2e8f0;">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:15px;">
                            <h5 style="font-size:14px;font-weight:700;color:#475569;">Hikaye Görselleri ({{ $city->images->count() }})</h5>
                            <form action="{{ route('admin.featured_cities.add_image', $city) }}" method="POST" enctype="multipart/form-data" style="display:flex;align-items:center;gap:10px;">
                                @csrf
                                <input type="file" name="image" accept="image/*" required style="font-size:12px;">
                                <button type="submit" class="btn btn-primary btn-sm">+ Görsel Ekle</button>
                            </form>
                        </div>

                        <div style="display:flex;gap:15px;overflow-x:auto;padding-bottom:10px;">
                            @forelse($city->images as $image)
                            <div style="flex-shrink:0;width:100px;position:relative;">
                                <img src="{{ asset('storage/' . $image->image_path) }}" style="width:100px;height:160px;object-fit:cover;border-radius:8px;border:1px solid #e2e8f0;">
                                <form action="{{ route('admin.featured_cities.destroy_image', $image) }}" method="POST" style="position:absolute;top:5px;right:5px;">
                                    @csrf @method('DELETE')
                                    <button type="submit" style="background:#ef4444;color:white;border:none;border-radius:50%;width:24px;height:24px;cursor:pointer;font-size:12px;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 4px rgba(0,0,0,0.2);" onclick="return confirm('Görsel silinsin mi?')">✕</button>
                                </form>
                            </div>
                            @empty
                            <div style="color:#94a3b8;font-size:12px;padding:20px;text-align:center;width:100%;">Henüz görsel eklenmemiş. Hikaye modunda görsellerin görünmesi için en az bir tane eklemelisiniz.</div>
                            @endforelse
                        </div>
                    </div>
                </div>
                @empty
                <div style="text-align:center;color:#64748b;font-size:14px;padding:48px;background:#f8fafc;border-radius:16px;border:1px dashed #cbd5e1;">
                    <div style="font-size:32px;margin-bottom:12px;">🏙️</div>
                    <div style="font-weight:600;color:#0f172a;margin-bottom:4px;">Henüz şehir eklenmemiş</div>
                    <div>Yukarıdaki formdan öne çıkarılacak ilk şehri ekleyebilirsiniz.</div>
                </div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
