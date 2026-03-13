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

            {{-- Create Category Form --}}
            <div style="background:var(--white);border:1px solid var(--border-light);border-radius:var(--radius);padding:20px;margin-bottom:24px;max-width:94%;margin-left:auto;margin-right:auto;">
                <h3 style="font-size:15px;font-weight:700;margin-bottom:16px;">+ Yeni Kategori Ekle</h3>
                <form method="POST" action="{{ route('admin.categories.store') }}">
                    @csrf
                    <div style="display:grid;grid-template-columns:repeat(5, 1fr);gap:12px;align-items:end;">
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
                    <thead><tr><th>İkon & Ad</th><th>Üst Kategori</th><th>Sıralama</th><th>Durum</th><th>İşlemler</th></tr></thead>
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
                                    <button type="button" class="btn btn-outline btn-sm" onclick="editCategory({{ json_encode($category) }})">✏️ Düzenle</button>
                                    <form method="POST" action="{{ route('admin.categories.destroy', $category) }}" onsubmit="return confirm('Kategori silinsin mi?');">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-danger btn-sm">🗑️ Sil</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="5" style="text-align:center;color:var(--text-muted);padding:24px;">Kategori bulunamadı.</td></tr>
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
function editCategory(cat) {
    document.getElementById('editForm').action = '/admin/categories/' + cat.id;
    document.getElementById('editName').value = cat.name;
    document.getElementById('editIcon').value = cat.icon || '';
    document.getElementById('editSort').value = cat.sort_order || 0;
    document.getElementById('editParent').value = cat.parent_id || '';
    document.getElementById('editModal').style.display = 'flex';
}
function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}
</script>
@endsection
