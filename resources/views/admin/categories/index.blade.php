@extends('layouts.app')
@section('title', 'Kategori Yönetimi — Admin')

@section('content')
<div class="container">
    <div>
        @include('partials.admin-sidebar')
        
        <div class="section" style="padding:0;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;max-width:94%;margin-left:auto;margin-right:auto;">
                <h1 style="font-size:24px;font-weight:700;">Kategori Yönetimi</h1>
            </div>

            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            @if($errors->any())
                <div class="alert alert-error">@foreach($errors->all() as $e) {{ $e }}<br> @endforeach</div>
            @endif

            @unless($categoryLicensingReady)
                <div class="alert alert-error" style="max-width:94%;margin-left:auto;margin-right:auto;">
                    Kategori yetkilendirme veritabanı migration'ı henüz uygulanmamış. Fiyat alanları aktif değil; eski kategori yönetimi çalışmaya devam eder.
                </div>
            @endunless

            {{-- Üst Kategori Yönetimi (ayrı alan) --}}
            <div style="background:var(--white);border:1px solid var(--border-light);border-radius:var(--radius);padding:20px;margin-bottom:24px;max-width:94%;margin-left:auto;margin-right:auto;border-left:4px solid var(--accent);">
                <h3 style="font-size:15px;font-weight:700;margin-bottom:4px;">🗂️ Üst Kategori Yönetimi</h3>
                <p style="font-size:13px;color:var(--text-muted);margin-bottom:16px;">
                    Üst (ana) kategoriler, alt kategorileri gruplamak için kullanılır. Buradan eklenen kategoriler her zaman ana kategori olur; alt kategori atamak için aşağıdaki genel formu kullanın.
                </p>

                <form method="POST" action="{{ route('admin.categories.parents.store') }}">
                    @csrf
                    <div style="display:grid;grid-template-columns:repeat({{ $categoryLicensingReady ? 5 : 4 }}, 1fr);gap:12px;align-items:end;">
                        <div class="form-group" style="margin:0;">
                            <label>Üst Kategori Adı *</label>
                            <input type="text" name="name" required placeholder="Yurt Dışı Turlar">
                        </div>
                        <div class="form-group" style="margin:0;">
                            <label>İkon (Emoji)</label>
                            <input type="text" name="icon" placeholder="🌍">
                        </div>
                        @if($categoryLicensingReady)
                            <div class="form-group" style="margin:0;">
                                <label>Aylık Ücret (TL)</label>
                                <input type="number" name="monthly_price" value="2000" min="0" step="0.01" required>
                            </div>
                        @endif
                        <div class="form-group" style="margin:0;">
                            <label>Sıralama</label>
                            <input type="number" name="sort_order" value="0">
                        </div>
                        <button type="submit" class="btn btn-primary" style="height:44px;">+ Üst Kategori Ekle</button>
                    </div>
                </form>

                {{-- Mevcut üst kategoriler --}}
                <div style="margin-top:20px;border-top:1px solid var(--border-light);padding-top:16px;">
                    <div style="font-size:13px;font-weight:700;color:var(--text-sec);margin-bottom:10px;">Mevcut Üst Kategoriler ({{ $parentCategories->count() }})</div>
                    @if($parentCategories->isEmpty())
                        <div style="color:var(--text-muted);font-style:italic;font-size:13px;">Henüz üst kategori yok.</div>
                    @else
                        <div style="display:flex;flex-wrap:wrap;gap:10px;">
                            @foreach($parentCategories as $parent)
                                <div style="display:flex;align-items:center;gap:10px;border:1px solid var(--border-light);border-radius:10px;padding:8px 12px;background:var(--bg);">
                                    <span style="font-size:18px;">{{ $parent->icon }}</span>
                                    <div>
                                        <div style="font-weight:600;font-size:14px;{{ $parent->is_active ? '' : 'color:var(--text-muted);text-decoration:line-through;' }}">{{ $parent->name }}</div>
                                        <div style="font-size:11px;color:var(--text-muted);">{{ $parent->children_count }} alt kategori · sıra {{ $parent->sort_order }}</div>
                                    </div>
                                    <div style="display:flex;gap:4px;margin-left:6px;">
                                        <button
                                            type="button"
                                            class="btn btn-outline btn-sm"
                                            data-name="{{ $parent->name }}"
                                            data-icon="{{ $parent->icon }}"
                                            @if($categoryLicensingReady)
                                            data-monthly-price="{{ number_format((float) $parent->monthly_price, 2, '.', '') }}"
                                            @endif
                                            data-sort-order="{{ $parent->sort_order }}"
                                            data-parent-id="{{ $parent->parent_id }}"
                                            data-update-url="{{ route('admin.categories.update', $parent) }}"
                                            onclick="editCategory(this)"
                                            title="Düzenle"
                                        >✏️</button>
                                        <form method="POST" action="{{ route('admin.categories.toggle', $parent) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-outline btn-sm" title="{{ $parent->is_active ? 'Pasifleştir' : 'Aktifleştir' }}">{{ $parent->is_active ? '🟢' : '⚪️' }}</button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.categories.destroy', $parent) }}" onsubmit="return confirm('Üst kategori silinsin mi? (Alt kategorisi veya turu varsa silinemez)');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-danger btn-sm" title="Sil">🗑️</button>
                                        </form>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            {{-- Create Category Form --}}
            <div style="background:var(--white);border:1px solid var(--border-light);border-radius:var(--radius);padding:20px;margin-bottom:24px;max-width:94%;margin-left:auto;margin-right:auto;">
                <h3 style="font-size:15px;font-weight:700;margin-bottom:16px;">+ Yeni Kategori Ekle</h3>
                <form method="POST" action="{{ route('admin.categories.store') }}">
                    @csrf
                    <div style="display:grid;grid-template-columns:repeat({{ $categoryLicensingReady ? 6 : 5 }}, 1fr);gap:12px;align-items:end;">
                        <div class="form-group" style="margin:0;">
                            <label>Ad *</label>
                            <input type="text" name="name" required placeholder="Kültür Turları">
                        </div>
                        <div class="form-group" style="margin:0;">
                            <label>İkon (Emoji)</label>
                            <input type="text" name="icon" placeholder="🏛️">
                        </div>
                        <div class="form-group" style="margin:0;">
                            <label>Üst Kategori</label>
                            <select name="parent_id">
                                <option value="">Yok (Ana Kategori)</option>
                                @foreach($parentCategories as $parent)
                                    <option value="{{ $parent->id }}">{{ $parent->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        @if($categoryLicensingReady)
                            <div class="form-group" style="margin:0;">
                                <label>Aylık Ücret (TL)</label>
                                <input type="number" name="monthly_price" value="2000" min="0" step="0.01" required>
                            </div>
                        @endif
                        <div class="form-group" style="margin:0;">
                            <label>Sıralama</label>
                            <input type="number" name="sort_order" value="0">
                        </div>
                        <button type="submit" class="btn btn-primary" style="height:44px;">Ekle</button>
                    </div>
                </form>
            </div>

            {{-- Categories Table --}}
            <div style="background:var(--white);border:1px solid var(--border-light);border-radius:var(--radius);overflow:hidden;max-width:94%;margin-left:auto;margin-right:auto;">
                <table class="table" style="margin:0;border:none;">
                    <thead><tr><th>İkon & Ad</th><th>Üst Kategori</th>@if($categoryLicensingReady)<th>Aylık Ücret</th>@endif<th>Sıralama</th><th>Durum</th><th>İşlemler</th></tr></thead>
                    <tbody>
                        @forelse($categories as $category)
                        <tr style="border-bottom:1px solid var(--border-light);">
                            <td>
                                <div style="font-weight:600;display:flex;align-items:center;gap:8px;">
                                    @if($category->parent_id) <span style="color:var(--text-muted);font-weight:400;">↳</span> @endif
                                    <span style="font-size:18px;">{{ $category->icon }}</span> {{ $category->name }}
                                </div>
                            </td>
                            <td>
                                @if($category->parent)
                                    <span class="badge" style="background:var(--bg);color:var(--text-sec);">{{ $category->parent->name }}</span>
                                @else
                                    <span style="color:var(--text-muted);font-style:italic;">Ana Kategori</span>
                                @endif
                            </td>
                            @if($categoryLicensingReady)
                                <td>{{ number_format((float) $category->monthly_price, 0, ',', '.') }} TL</td>
                            @endif
                            <td>{{ $category->sort_order }}</td>
                            <td>
                                <form method="POST" action="{{ route('admin.categories.toggle', $category) }}">
                                    @csrf
                                    <button type="submit" class="badge {{ $category->is_active ? 'badge-green' : '' }}" style="border:none;cursor:pointer;font-family:inherit;{{ !$category->is_active ? 'background:#fef2f2;color:#991b1b;' : '' }}">
                                        {{ $category->is_active ? 'Aktif' : 'Pasif' }}
                                    </button>
                                </form>
                            </td>
                            <td>
                                <div style="display:flex;gap:4px;">
                                    <button
                                        type="button"
                                        class="btn btn-outline btn-sm"
                                        data-name="{{ $category->name }}"
                                        data-icon="{{ $category->icon }}"
                                        @if($categoryLicensingReady)
                                        data-monthly-price="{{ number_format((float) $category->monthly_price, 2, '.', '') }}"
                                        @endif
                                        data-sort-order="{{ $category->sort_order }}"
                                        data-parent-id="{{ $category->parent_id }}"
                                        data-update-url="{{ route('admin.categories.update', $category) }}"
                                        onclick="editCategory(this)"
                                    >✏️ Düzenle</button>
                                    <form method="POST" action="{{ route('admin.categories.destroy', $category) }}" onsubmit="return confirm('Kategori silinsin mi?');">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-danger btn-sm">🗑️ Sil</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="{{ $categoryLicensingReady ? 6 : 5 }}" style="text-align:center;color:var(--text-muted);padding:24px;">Kategori bulunamadı.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

        </div>
    </div>
</div>

{{-- Edit Modal Overlay (simple implementation without real Modal logic for brevity) --}}
<div id="editModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:999;align-items:center;justify-content:center;">
    <div style="background:white;padding:24px;border-radius:16px;width:100%;max-width:500px;box-shadow:var(--shadow-lg);">
        <h3 style="font-size:18px;font-weight:700;margin-bottom:16px;">Kategori Düzenle</h3>
        <form method="POST" id="editForm">
            @csrf @method('PUT')
            <div class="form-group"><label>Ad *</label><input type="text" name="name" id="editName" required></div>
            <div class="form-row">
                <div class="form-group"><label>İkon (Emoji)</label><input type="text" name="icon" id="editIcon"></div>
                @if($categoryLicensingReady)
                    <div class="form-group"><label>Aylık Ücret (TL)</label><input type="number" name="monthly_price" id="editMonthlyPrice" min="0" step="0.01" required></div>
                @endif
                <div class="form-group"><label>Sıralama</label><input type="number" name="sort_order" id="editSort"></div>
            </div>
            <div class="form-group">
                <label>Üst Kategori</label>
                <select name="parent_id" id="editParent">
                    <option value="">Yok (Ana Kategori)</option>
                    @foreach($parentCategories as $parent)
                        <option value="{{ $parent->id }}">{{ $parent->name }}</option>
                    @endforeach
                </select>
            </div>
            <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:24px;">
                <button type="button" class="btn btn-outline" onclick="closeEditModal()">İptal</button>
                <button type="submit" class="btn btn-primary">Kaydet</button>
            </div>
        </form>
    </div>
</div>

<script>
function editCategory(button) {
    document.getElementById('editForm').action = button.dataset.updateUrl;
    document.getElementById('editName').value = button.dataset.name || '';
    document.getElementById('editIcon').value = button.dataset.icon || '';
    @if($categoryLicensingReady)
    document.getElementById('editMonthlyPrice').value = button.dataset.monthlyPrice || 0;
    @endif
    document.getElementById('editSort').value = button.dataset.sortOrder || 0;
    document.getElementById('editParent').value = button.dataset.parentId || '';
    document.getElementById('editModal').style.display = 'flex';
}
function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}
</script>
@endsection
