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
                    <div style="font-size:13px;color:#64748b;font-weight:600;">Ödenmiş Sipariş</div>
                    <div style="font-size:28px;font-weight:800;color:#0f172a;margin-top:6px;">{{ $orderStats['total_orders'] }}</div>
                    @if($orderStats['pending_orders'] > 0)
                        <a href="{{ route('admin.category-licenses.orders', ['status' => 'pending']) }}" class="p-etiket p-etiket-uyari" style="margin-top:6px;text-decoration:none;">{{ $orderStats['pending_orders'] }} ödeme bekliyor →</a>
                    @endif
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
                <h2 style="font-size:18px;font-weight:700;color:#0f172a;margin-bottom:12px;">Sipariş Geçmişi <span class="p-alt" style="font-weight:600;">({{ $orders->total() }})</span></h2>

                {{-- B9: filtreler (sayfalamada korunur) --}}
                @php($filtreAktif = array_filter($filtre) !== [])
                <form method="GET" action="{{ route('admin.category-licenses.orders') }}" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:16px;">
                    <input type="search" name="q" value="{{ $filtre['q'] }}" placeholder="Sipariş no" style="padding:9px 12px;border:1px solid var(--p-cizgi);border-radius:10px;min-width:150px;">
                    <select name="status" style="padding:9px 12px;border:1px solid var(--p-cizgi);border-radius:10px;background:#fff;">
                        <option value="">Tüm durumlar</option>
                        @foreach($durumlar as $d)<option value="{{ $d }}" @selected($filtre['status'] === $d)>{{ ['paid' => 'Ödendi', 'pending' => 'Ödeme bekliyor', 'failed' => 'Başarısız', 'cancelled' => 'İptal'][$d] ?? $d }}</option>@endforeach
                    </select>
                    <select name="agency_id" style="padding:9px 12px;border:1px solid var(--p-cizgi);border-radius:10px;background:#fff;max-width:220px;">
                        <option value="">Tüm acentalar</option>
                        @foreach($agencies as $a)<option value="{{ $a->id }}" @selected($filtre['agency_id'] === $a->id)>{{ $a->name }}</option>@endforeach
                    </select>
                    <input type="date" name="from" value="{{ $filtre['from'] }}" style="padding:9px 12px;border:1px solid var(--p-cizgi);border-radius:10px;">
                    <input type="date" name="to" value="{{ $filtre['to'] }}" style="padding:9px 12px;border:1px solid var(--p-cizgi);border-radius:10px;">
                    <button type="submit" class="p-btn p-btn-ikincil p-btn-kucuk">Filtrele</button>
                    @if($filtreAktif)<a href="{{ route('admin.category-licenses.orders') }}" class="p-alt">Temizle</a>@endif
                </form>

                @if($orders->isEmpty())
                    <div style="padding:18px;border:1px dashed #cbd5e1;border-radius:16px;background:#f8fafc;color:#475569;">
                        {{ $filtreAktif ? 'Filtreye uyan sipariş yok.' : 'Henüz kategori siparişi oluşmadı.' }}
                    </div>
                @else
                    <div style="overflow-x:auto;">
                        <div class="table-wrap"><table class="table" style="width:100%;text-align:left;">
                            <thead>
                                <tr>
                                    <th>Sipariş No</th>
                                    <th>Acenta</th>
                                    <th>Kategoriler</th>
                                    <th>Tutar</th>
                                    <th>Durum</th>
                                    <th>Tarih</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($orders as $order)
                                    @php($etiket = ['paid' => ['Ödendi', 'p-etiket-basari'], 'pending' => ['Ödeme bekliyor', 'p-etiket-uyari'], 'failed' => ['Başarısız', 'p-etiket-tehlike'], 'cancelled' => ['İptal', 'p-etiket-notr']][$order->status] ?? [$order->status, 'p-etiket-notr'])
                                    <tr>
                                        <td style="font-weight:700;color:#0f172a;"><a href="{{ route('admin.category-licenses.orders.show', $order) }}" style="color:inherit;">{{ $order->order_number }}</a></td>
                                        <td>
                                            @if($order->agency && ! $order->agency->trashed())
                                                <a href="{{ route('admin.agencies.show', $order->agency) }}" style="color:#0f172a;text-decoration:none;font-weight:600;">{{ $order->agency->name }}</a>
                                            @else
                                                <span style="font-weight:600;">{{ $order->agency?->name ?? '—' }}</span> <span class="p-etiket p-etiket-notr">silinmiş</span>
                                            @endif
                                        </td>
                                        <td>
                                            <div style="font-size:13px;color:#475569;line-height:1.5;">
                                                {{ $order->items->pluck('category_name')->implode(', ') }}
                                            </div>
                                            <div style="font-size:12px;color:#94a3b8;margin-top:4px;">{{ $order->items->count() }} kategori</div>
                                        </td>
                                        <td>{{ number_format((float) $order->subtotal, 0, ',', '.') }} TL</td>
                                        <td><span class="p-etiket {{ $etiket[1] }}">{{ $etiket[0] }}</span></td>
                                        <td>{{ $order->purchased_at?->format('d.m.Y H:i') }}@if($order->paid_at)<div class="p-alt">ödeme {{ $order->paid_at->format('d.m.Y') }}</div>@endif</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table></div>
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
