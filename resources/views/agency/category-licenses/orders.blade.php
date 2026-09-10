@extends('layouts.app')
@section('title', 'Satın Alım Geçmişi — Kategori Yetkileri')

@section('content')
@php $fiyat = fn ($tutar) => number_format((float) $tutar, 0, ',', '.'); @endphp
<div class="container">
    <div>
        @include('partials.agency-sidebar')
        <div class="section" style="padding:24px 0 0 0;">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;max-width:94%;margin:0 auto 24px;">
                <div>
                    <h1 style="font-size:26px;font-weight:800;letter-spacing:-0.5px;color:var(--text);">Satın Alım Geçmişi</h1>
                    <div style="font-size:14px;color:var(--text-meta);margin-top:4px;">Kategori yetkisi ve ekstra tur hakkı siparişlerinizin tamamı; yönetici tanımları ayrıca işaretlenir.</div>
                </div>
                <a href="{{ route('agency.category-licenses.index') }}" class="btn btn-outline">← Kategori Yetkileri</a>
            </div>

            <div style="max-width:820px;margin:0 auto;">
                @if($orders->isEmpty())
                    <div class="stat-card" style="padding:32px;text-align:center;">
                        <div style="font-size:34px;">🧾</div>
                        <div style="font-size:16px;font-weight:700;color:var(--text);margin-top:10px;">Henüz satın alım yok</div>
                        <div style="font-size:13px;color:var(--text-meta);margin-top:6px;">Kategori yetkisi aldığınızda siparişleriniz burada listelenir.</div>
                    </div>
                @else
                    <div style="display:flex;flex-direction:column;gap:12px;">
                        @foreach($orders as $order)
                            @php $manuel = $order->payment_provider === \App\Models\AgencyCategoryOrder::PROVIDER_MANUAL; @endphp
                            <div class="stat-card" style="padding:16px 18px;">
                                <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                                    <div style="min-width:0;">
                                        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                                            <span style="font-weight:700;color:var(--text);overflow-wrap:anywhere;">{{ $order->order_number }}</span>
                                            @if($manuel)
                                                <span class="badge" style="background:#f3e8ff;color:#6b21a8;">Yönetici tanımladı</span>
                                            @elseif($order->status === \App\Models\AgencyCategoryOrder::STATUS_PAID)
                                                <span class="badge badge-green">Ödendi</span>
                                            @elseif($order->status === \App\Models\AgencyCategoryOrder::STATUS_PENDING)
                                                <span class="badge" style="background:#fef9c3;color:#854d0e;">Beklemede</span>
                                            @elseif($order->status === \App\Models\AgencyCategoryOrder::STATUS_CANCELLED)
                                                <span class="badge" style="background:var(--border-light);color:var(--text-meta);">İptal</span>
                                            @else
                                                <span class="badge" style="background:#fef2f2;color:#991b1b;">Başarısız</span>
                                            @endif
                                            @if($order->auto_renewal ?? false)
                                                <span class="badge" style="background:#e0f2fe;color:#075985;">Otomatik yenileme</span>
                                            @endif
                                        </div>
                                        <div style="font-size:12px;color:var(--text-meta);margin-top:4px;">{{ ($order->paid_at ?? $order->purchased_at)?->format('d.m.Y H:i') ?? '—' }}</div>
                                    </div>
                                    <div style="font-size:17px;font-weight:800;color:var(--text);white-space:nowrap;">{{ $manuel ? 'Ücretsiz' : $fiyat($order->subtotal).' TL' }}</div>
                                </div>
                                <div style="font-size:12px;color:var(--text-meta);margin-top:10px;line-height:1.6;">
                                    {{-- Aynı kalem tekrarları duvar örmesin: ×N ile grupla --}}
                                    {{ $order->items->pluck('category_name')->countBy()->map(fn ($count, $name) => $name.($count > 1 ? ' ×'.$count : ''))->implode(', ') }}
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div style="margin-top:20px;">{{ $orders->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
