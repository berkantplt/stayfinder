@extends('layouts.app')
@section('title', 'Kategori Yetkilendirme — Kategori Tarifesi')

@section('content')
<div class="container">
    <div>
        @include('partials.admin-sidebar')
        <div class="section" style="padding:0;">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;max-width:94%;margin:0 auto 24px;">
                <div>
                    <div style="font-size:13px;font-weight:700;color:#0ea5e9;letter-spacing:0.3px;text-transform:uppercase;">Kategori Yetkilendirme</div>
                    <h1 style="font-size:28px;font-weight:800;letter-spacing:-0.5px;color:#0f172a;margin-top:6px;">Kategori Fiyat Tarifesi</h1>
                    <div style="font-size:14px;color:#64748b;margin-top:4px;">Kategori başı aylık bedeli, talebi ve portföy etkisini tek tabloda yönetin.</div>
                </div>
                <div style="display:flex;gap:10px;flex-wrap:wrap;">
                    <a href="{{ route('admin.category-licenses.index') }}" class="btn btn-outline">Genel Bakış</a>
                    <a href="{{ route('admin.categories.index') }}" class="btn btn-primary">Kategori Yönetimi</a>
                </div>
            </div>

            @if(session('success'))
                <div class="alert alert-success" style="max-width:94%;margin:0 auto 24px;">{{ session('success') }}</div>
            @endif

            @if($errors->any())
                <div class="alert alert-error" style="max-width:94%;margin:0 auto 24px;">
                    @foreach($errors->all() as $error) {{ $error }}<br> @endforeach
                </div>
            @endif

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;max-width:94%;margin:0 auto 24px;">
                <div class="stat-card" style="padding:20px;">
                    <div style="font-size:13px;color:#64748b;font-weight:600;">Kategori Sayısı</div>
                    <div style="font-size:28px;font-weight:800;color:#0f172a;margin-top:6px;">{{ $stats['category_count'] }}</div>
                </div>
                <div class="stat-card" style="padding:20px;">
                    <div style="font-size:13px;color:#64748b;font-weight:600;">Ortalama Aylık Ücret</div>
                    <div style="font-size:28px;font-weight:800;color:#0f172a;margin-top:6px;">{{ number_format($stats['average_monthly_price'], 0, ',', '.') }} TL</div>
                </div>
                <div class="stat-card" style="padding:20px;">
                    <div style="font-size:13px;color:#64748b;font-weight:600;">Toplam Yetki Talebi</div>
                    <div style="font-size:28px;font-weight:800;color:#0f172a;margin-top:6px;">{{ $stats['combined_demand'] }}</div>
                </div>
                <div class="stat-card" style="padding:20px;">
                    <div style="font-size:13px;color:#64748b;font-weight:600;">Aylık Portföy Değeri</div>
                    <div style="font-size:28px;font-weight:800;color:#0f172a;margin-top:6px;">{{ number_format($stats['portfolio_monthly_value'], 0, ',', '.') }} TL</div>
                </div>
            </div>

            <div class="stat-card" style="padding:24px;max-width:94%;margin:0 auto;">
                <h2 style="font-size:18px;font-weight:700;color:#0f172a;margin-bottom:18px;">Fiyat ve Talep Tablosu</h2>
                <div style="overflow-x:auto;">
                    <table class="table" style="width:100%;text-align:left;">
                        <thead>
                            <tr>
                                <th>Kategori</th>
                                <th>Gerçek Lisans</th>
                                <th>Legacy Talep</th>
                                <th>Aktif Tur</th>
                                <th>Aylık Ücret</th>
                                <th>Portföy</th>
                                <th>Durum</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($categories as $category)
                                <tr>
                                    <td>
                                        <div style="font-weight:700;color:#0f172a;">{{ $category->icon }} {{ $category->name }}</div>
                                        @if($category->parent)
                                            <div style="font-size:12px;color:#94a3b8;margin-top:2px;">{{ $category->parent->name }}</div>
                                        @endif
                                    </td>
                                    <td>{{ $category->active_subscriptions_count }}</td>
                                    <td>{{ $category->legacy_demand_count }}</td>
                                    <td>{{ $category->active_tours_count }}</td>
                                    <td style="min-width:220px;">
                                        <form method="POST" action="{{ route('admin.category-licenses.pricing.update', $category) }}" style="display:flex;gap:10px;align-items:center;">
                                            @csrf
                                            @method('PUT')
                                            <input type="number" name="monthly_price" min="0" step="0.01" value="{{ number_format((float) $category->monthly_price, 2, '.', '') }}" style="margin:0;">
                                            <button type="submit" class="btn btn-outline btn-sm">Kaydet</button>
                                        </form>
                                    </td>
                                    <td>
                                        <div style="font-weight:700;color:#0f172a;">{{ number_format((float) $category->portfolio_monthly_value, 0, ',', '.') }} TL</div>
                                        <div style="font-size:12px;color:#94a3b8;margin-top:2px;">{{ $category->order_items_count }} satış kalemi</div>
                                    </td>
                                    <td style="color:#94a3b8;">{{ $category->is_active ? 'Aktif' : 'Pasif' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
