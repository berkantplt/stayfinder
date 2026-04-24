@extends('layouts.app')
@section('title', 'Kategori Yetkilendirme — Siparişler')

@section('content')
<div class="container">
    <div>
        @include('partials.admin-sidebar')
        <div class="section" style="padding:0;">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;max-width:94%;margin:0 auto 24px;">
                <div>
                    <div style="font-size:13px;font-weight:700;color:#0ea5e9;letter-spacing:0.3px;text-transform:uppercase;">Kategori Yetkilendirme</div>
                    <h1 style="font-size:28px;font-weight:800;letter-spacing:-0.5px;color:#0f172a;margin-top:6px;">Siparişler</h1>
                    <div style="font-size:14px;color:#64748b;margin-top:4px;">Kategori bazlı satın alma hareketlerini daha rahat okunur bir sipariş ekranında takip edin.</div>
                </div>
                <div style="display:flex;gap:10px;flex-wrap:wrap;">
                    <a href="{{ route('admin.category-licenses.index') }}" class="btn btn-outline">Genel Bakış</a>
                    <a href="{{ route('admin.category-licenses.access') }}" class="btn btn-primary">Acenta Erişimleri</a>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;max-width:94%;margin:0 auto 24px;">
                <div class="stat-card" style="padding:20px;">
                    <div style="font-size:13px;color:#64748b;font-weight:600;">Toplam Sipariş</div>
                    <div style="font-size:28px;font-weight:800;color:#0f172a;margin-top:6px;">{{ $orderStats['total_orders'] }}</div>
                </div>
                <div class="stat-card" style="padding:20px;">
                    <div style="font-size:13px;color:#64748b;font-weight:600;">Toplam Sipariş Tutarı</div>
                    <div style="font-size:28px;font-weight:800;color:#0f172a;margin-top:6px;">{{ number_format($orderStats['total_revenue'], 0, ',', '.') }} TL</div>
                </div>
                <div class="stat-card" style="padding:20px;">
                    <div style="font-size:13px;color:#64748b;font-weight:600;">Son 30 Gün Sipariş</div>
                    <div style="font-size:28px;font-weight:800;color:#0f172a;margin-top:6px;">{{ $orderStats['last_30_days_orders'] }}</div>
                </div>
                <div class="stat-card" style="padding:20px;">
                    <div style="font-size:13px;color:#64748b;font-weight:600;">Son 30 Gün Gelir</div>
                    <div style="font-size:28px;font-weight:800;color:#0f172a;margin-top:6px;">{{ number_format($orderStats['last_30_days_revenue'], 0, ',', '.') }} TL</div>
                </div>
            </div>

            <div class="stat-card" style="padding:24px;max-width:94%;margin:0 auto;">
                <h2 style="font-size:18px;font-weight:700;color:#0f172a;margin-bottom:18px;">Sipariş Geçmişi</h2>

                @if($orders->isEmpty())
                    <div style="padding:18px;border:1px dashed #cbd5e1;border-radius:16px;background:#f8fafc;color:#475569;">
                        Henüz kategori siparişi oluşmadı.
                    </div>
                @else
                    <div style="overflow-x:auto;">
                        <table class="table" style="width:100%;text-align:left;">
                            <thead>
                                <tr>
                                    <th>Sipariş No</th>
                                    <th>Acenta</th>
                                    <th>Kategoriler</th>
                                    <th>Tutar</th>
                                    <th>Tarih</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($orders as $order)
                                    <tr>
                                        <td style="font-weight:700;color:#0f172a;">{{ $order->order_number }}</td>
                                        <td>
                                            <a href="{{ route('admin.agencies.show', $order->agency) }}" style="color:#0f172a;text-decoration:none;font-weight:600;">
                                                {{ $order->agency->name }}
                                            </a>
                                        </td>
                                        <td>
                                            <div style="font-size:13px;color:#475569;line-height:1.5;">
                                                {{ $order->items->pluck('category_name')->implode(', ') }}
                                            </div>
                                            <div style="font-size:12px;color:#94a3b8;margin-top:4px;">{{ $order->items->count() }} kategori</div>
                                        </td>
                                        <td>{{ number_format((float) $order->subtotal, 0, ',', '.') }} TL</td>
                                        <td>{{ $order->purchased_at?->format('d.m.Y H:i') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div style="margin-top:24px;">
                        {{ $orders->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
