@extends('layouts.app')
@section('title', 'Üst Kategori Yönetimi — Admin')

@section('content')
<div class="container">
    <div>
        @include('partials.admin-sidebar')

        <div class="section" style="padding:0;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;max-width:94%;margin-left:auto;margin-right:auto;">
                <h1 style="font-size:24px;font-weight:700;">🗂️ Üst Kategori Yönetimi</h1>
                <a href="{{ route('admin.categories.index') }}" class="btn btn-outline btn-sm">← Tüm Kategoriler</a>
            </div>
            <p style="font-size:13px;color:var(--text-muted);margin-bottom:24px;max-width:94%;margin-left:auto;margin-right:auto;">
                Üst (ana) kategoriler sadece alt kategorileri gruplamak için kullanılır — <strong>fiyatlandırılmaz.</strong> Fiyat ve satın alma alt kategorilerde olur. Bir kategoriye üst kategori atamak için "Tüm Kategoriler" sayfasındaki formu kullanın.
            </p>

            @if(session('success'))
                <div class="alert alert-success" style="max-width:94%;margin-left:auto;margin-right:auto;">{{ session('success') }}</div>
            @endif

            @if($errors->any())
                <div class="alert alert-error" style="max-width:94%;margin-left:auto;margin-right:auto;">@foreach($errors->all() as $e) {{ $e }}<br> @endforeach</div>
            @endif

            {{-- Üst kategori ekleme formu (fiyatsız) --}}
            <div style="background:var(--white);border:1px solid var(--border-light);border-radius:var(--radius);padding:20px;margin-bottom:24px;max-width:94%;margin-left:auto;margin-right:auto;">
                <h3 style="font-size:15px;font-weight:700;margin-bottom:16px;">+ Yeni Üst Kategori Ekle</h3>
                <form method="POST" action="{{ route('admin.categories.parents.store') }}">
                    @csrf
                    <div style="display:grid;grid-template-columns:repeat(4, 1fr);gap:12px;align-items:end;">
                        <div class="form-group" style="margin:0;">
                            <label>Üst Kategori Adı *</label>
                            <input type="text" name="name" required placeholder="Yurt Dışı Turlar">
                        </div>
                        <div class="form-group" style="margin:0;">
                            <label>İkon (Emoji)</label>
                            <input type="text" name="icon" placeholder="🌍">
                        </div>
                        <div class="form-group" style="margin:0;">
                            <label>Sıralama</label>
                            <input type="number" name="sort_order" value="0">
                        </div>
                        <button type="submit" class="btn btn-primary" style="height:44px;">+ Üst Kategori Ekle</button>
                    </div>
                </form>
            </div>

            {{-- Üst kategoriler tablosu --}}
            <div style="background:var(--white);border:1px solid var(--border-light);border-radius:var(--radius);overflow:hidden;max-width:94%;margin-left:auto;margin-right:auto;">
                <table class="table" style="margin:0;border:none;">
                    <thead><tr><th>İkon & Ad</th><th>Alt Kategori</th><th>Sıralama</th><th>Durum</th><th>İşlemler</th></tr></thead>
                    <tbody>
                        @forelse($parentCategories as $category)
                        <tr style="border-bottom:1px solid var(--border-light);">
                            <td>
                                <div style="font-weight:600;display:flex;align-items:center;gap:8px;">
                                    <span style="font-size:18px;">{{ $category->icon }}</span> {{ $category->name }}
                                </div>
                            </td>
                            <td>
                                <span class="badge" style="background:var(--bg);color:var(--text-sec);">{{ $category->children_count }} alt kategori</span>
                            </td>
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
                                        data-sort-order="{{ $category->sort_order }}"
                                        data-update-url="{{ route('admin.categories.update', $category) }}"
                                        onclick="editParent(this)"
                                    >✏️ Düzenle</button>
                                    <form method="POST" action="{{ route('admin.categories.destroy', $category) }}" onsubmit="return confirm('Üst kategori silinsin mi? (Alt kategorisi veya turu varsa silinemez)');">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-danger btn-sm">🗑️ Sil</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="5" style="text-align:center;color:var(--text-muted);padding:24px;">Henüz üst kategori yok.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

        </div>
    </div>
</div>

{{-- Edit Modal (fiyatsız) --}}
<div id="editModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:999;align-items:center;justify-content:center;">
    <div style="background:white;padding:24px;border-radius:16px;width:100%;max-width:500px;box-shadow:var(--shadow-lg);">
        <h3 style="font-size:18px;font-weight:700;margin-bottom:16px;">Üst Kategori Düzenle</h3>
        <form method="POST" id="editForm">
            @csrf @method('PUT')
            <div class="form-group"><label>Ad *</label><input type="text" name="name" id="editName" required></div>
            <div class="form-row">
                <div class="form-group"><label>İkon (Emoji)</label><input type="text" name="icon" id="editIcon"></div>
                <div class="form-group"><label>Sıralama</label><input type="number" name="sort_order" id="editSort"></div>
            </div>
            <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:24px;">
                <button type="button" class="btn btn-outline" onclick="closeEditModal()">İptal</button>
                <button type="submit" class="btn btn-primary">Kaydet</button>
            </div>
        </form>
    </div>
</div>

<script>
function editParent(button) {
    document.getElementById('editForm').action = button.dataset.updateUrl;
    document.getElementById('editName').value = button.dataset.name || '';
    document.getElementById('editIcon').value = button.dataset.icon || '';
    document.getElementById('editSort').value = button.dataset.sortOrder || 0;
    document.getElementById('editModal').style.display = 'flex';
}
function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}
</script>
@endsection
