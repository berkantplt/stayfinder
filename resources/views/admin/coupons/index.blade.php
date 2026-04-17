@extends('layouts.app')
@section('title', 'Kupon Yönetimi')

@section('content')
<div class="container">
    <div>
        @include('partials.admin-sidebar')
        <div class="section" style="padding:0;">
            <div class="admin-header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px; max-width:94%; margin-left:auto; margin-right:auto;">
                <div>
                    <h1 style="font-size:24px; font-weight:700; color:var(--text);">Kupon Yönetimi</h1>
                    <p style="color:var(--text-sec); font-size:14px; margin-top:4px;">İndirim kodlarını oluşturun ve yönetin.</p>
                </div>
                <button onclick="document.getElementById('createModal').style.display='flex'" class="btn btn-primary">
                    + Yeni Kupon Ekle
                </button>
            </div>

            <div style="max-width:94%; margin-left:auto; margin-right:auto;">
                @if(session('success'))
                <div class="alert alert-success" style="margin-bottom:16px;">{{ session('success') }}</div>
                @endif

                <div class="card">
    <div style="overflow-x:auto;">
        <table class="table">
            <thead>
                <tr>
                    <th>KOD</th>
                    <th>TİP / DEĞER</th>
                    <th>MİN SEPET</th>
                    <th>KULLANIM</th>
                    <th>TARİHLER</th>
                    <th>DURUM</th>
                    <th style="text-align:right;">İŞLEMLER</th>
                </tr>
            </thead>
            <tbody>
                @forelse($coupons as $coupon)
                <tr>
                    <td style="font-weight:700; color:var(--accent);">{{ $coupon->code }}</td>
                    <td>
                        {{ $coupon->discount_type === 'percent' ? '%' . number_format($coupon->discount_value, 0) : number_format($coupon->discount_value, 0) . ' ₺' }}
                        indirim
                    </td>
                    <td>{{ $coupon->min_purchase_amount > 0 ? number_format($coupon->min_purchase_amount, 0) . ' ₺' : 'Yok' }}</td>
                    <td>{{ $coupon->usages_count }} / {{ $coupon->max_uses ?: 'Sınırsız' }}</td>
                    <td style="font-size:12px;">
                        Başlangıç: {{ $coupon->starts_at ? $coupon->starts_at->format('d.m.Y H:i') : '-' }}<br>
                        Bitiş: {{ $coupon->expires_at ? $coupon->expires_at->format('d.m.Y H:i') : '-' }}
                    </td>
                    <td>
                        <span class="badge {{ $coupon->is_active ? 'badge-green' : 'badge-danger' }}" style="background: {{ $coupon->is_active ? 'var(--green-bg)' : '#fef2f2' }}; color: {{ $coupon->is_active ? 'var(--green-text)' : '#991b1b' }}">
                            {{ $coupon->is_active ? 'Aktif' : 'Pasif' }}
                        </span>
                    </td>
                    <td style="text-align:right;">
                        <div style="display:flex; gap:8px; justify-content:flex-end;">
                            <form action="{{ route('admin.coupons.toggle', $coupon) }}" method="POST">
                                @csrf
                                <button class="btn btn-outline btn-sm">
                                    {{ $coupon->is_active ? 'Pasife Al' : 'Aktifleştir' }}
                                </button>
                            </form>
                            <form action="{{ route('admin.coupons.destroy', $coupon) }}" method="POST" onsubmit="return confirm('Emin misiniz?');">
                                @csrf @method('DELETE')
                                <button class="btn btn-danger btn-sm" style="background:#ef4444; color:white; border:none; border-radius:10px; padding:8px 16px;">Sil</button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" style="text-align:center; padding:32px; color:var(--text-muted);">
                        Henüz hiç kupon tanımlanmamış.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div id="createModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); align-items:center; justify-content:center; padding:20px; z-index:1000;">
    <div style="background:white; border-radius:16px; padding:32px; width:100%; max-width:600px; max-height:90vh; overflow-y:auto;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px;">
            <h2 style="font-size:20px; font-weight:700;">Yeni Kupon Ekle</h2>
            <button onclick="document.getElementById('createModal').style.display='none'" style="background:none; border:none; font-size:24px; cursor:pointer;">&times;</button>
        </div>

        <form action="{{ route('admin.coupons.store') }}" method="POST">
            @csrf
            
            <div class="form-group">
                <label>Kupon Kodu (Örn: YAZ2026)</label>
                <input type="text" name="code" required style="width:100%; padding:10px; border:1px solid #e2e8f0; border-radius:8px;">
            </div>

            <div style="display:flex; gap:16px; margin-bottom:16px;">
                <div style="flex:1;">
                    <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px;">İndirim Tipi</label>
                    <select name="discount_type" required style="width:100%; padding:10px; border:1px solid #e2e8f0; border-radius:8px;">
                        <option value="fixed">Sabit Tutar (₺)</option>
                        <option value="percent">Yüzde (%)</option>
                    </select>
                </div>
                <div style="flex:1;">
                    <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px;">İndirim Değeri</label>
                    <input type="number" name="discount_value" step="0.01" required style="width:100%; padding:10px; border:1px solid #e2e8f0; border-radius:8px;">
                </div>
            </div>

            <div style="display:flex; gap:16px; margin-bottom:16px;">
                <div style="flex:1;">
                    <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px;">Minimum Sepet Tutarı (₺)</label>
                    <input type="number" name="min_purchase_amount" value="0" step="0.01" style="width:100%; padding:10px; border:1px solid #e2e8f0; border-radius:8px;">
                </div>
                <div style="flex:1;">
                    <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px;">Toplam Kullanım Limiti</label>
                    <input type="number" name="max_uses" placeholder="Örn: 100 (Boşsa sınırsız)" style="width:100%; padding:10px; border:1px solid #e2e8f0; border-radius:8px;">
                </div>
            </div>

            <div style="display:flex; gap:16px; margin-bottom:16px;">
                <div style="flex:1;">
                    <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px;">Başlangıç Tarihi</label>
                    <input type="date" name="starts_at" style="width:100%; padding:10px; border:1px solid #e2e8f0; border-radius:8px;">
                </div>
                <div style="flex:1;">
                    <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px;">Bitiş Tarihi</label>
                    <input type="date" name="expires_at" style="width:100%; padding:10px; border:1px solid #e2e8f0; border-radius:8px;">
                </div>
            </div>

            <div style="margin-bottom:24px;">
                <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                    <input type="checkbox" name="is_active" value="1" checked> Aktif
                </label>
            </div>

            <button type="submit" class="btn btn-primary" style="width:100%;">Kaydet</button>
        </form>
    </div>
</div>
            </div>
        </div>
    </div>
</div>
@endsection
