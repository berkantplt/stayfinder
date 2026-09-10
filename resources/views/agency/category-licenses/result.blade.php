@extends('layouts.app')
@section('title', 'Ödeme Sonucu — Kategori Yetkileri')

@section('content')
@php
    $fiyat = fn ($tutar) => number_format((float) $tutar, 0, ',', '.');
    // Ekstra hak etiketi: otomatik yenileme açıkken aylık, kapalıyken tek seferlik
    $autoRenewEnabled = \App\Support\CategoryLicensing::autoRenewEnabled();
@endphp
<div class="container">
    <div>
        @include('partials.agency-sidebar')
        <div class="section" style="padding:24px 0 0 0;">
            <div class="stat-card" style="max-width:720px;margin:24px auto;padding:32px;text-align:center;">
                @if($order->isPaid())
                    <div style="font-size:48px;margin-bottom:8px;">✅</div>
                    <h1 style="font-size:24px;font-weight:800;color:var(--text);">Ödemeniz Başarıyla Alındı</h1>
                    <div style="font-size:14px;color:var(--text-meta);margin-top:8px;">
                        Sipariş No: <strong>{{ $order->order_number }}</strong> • Toplam: <strong>{{ $fiyat($order->subtotal) }} TL</strong>
                    </div>

                    @php
                        $licenseItems = $order->items->reject(fn ($item) => $item->isExtraSlot());
                        $slotItems = $order->items->filter(fn ($item) => $item->isExtraSlot());
                    @endphp

                    <div style="margin-top:24px;text-align:left;border:1px solid var(--border);border-radius:14px;overflow:hidden;">
                        <div style="background:var(--bg);padding:12px 16px;font-size:12px;color:var(--text-meta);font-weight:700;letter-spacing:.5px;text-transform:uppercase;">Aktif edilen kalemler</div>
                        @foreach($licenseItems as $item)
                            <div style="padding:12px 16px;display:flex;justify-content:space-between;gap:12px;border-top:1px solid var(--border);font-size:14px;color:var(--text);">
                                <span>{{ $item->category_name }}</span>
                                <strong style="white-space:nowrap;">{{ $fiyat($item->unit_price) }} TL / ay</strong>
                            </div>
                        @endforeach
                        @foreach($slotItems as $item)
                            <div style="padding:12px 16px;display:flex;justify-content:space-between;gap:12px;border-top:1px solid var(--border);font-size:14px;color:var(--text);">
                                <span>{{ $item->category_name }}</span>
                                {{-- Ekstra hak: otomatik yenileme açıkken aylık, kapalıyken tek seferlik ücretlendirilir --}}
                                <strong style="white-space:nowrap;">{{ $fiyat($item->unit_price) }} TL {{ $autoRenewEnabled ? '/ ay' : '(tek seferlik)' }}</strong>
                            </div>
                        @endforeach
                    </div>

                    <div style="display:flex;gap:12px;justify-content:center;margin-top:24px;flex-wrap:wrap;">
                        <a href="{{ route('agency.category-licenses.index') }}" class="btn btn-outline">Kategori Yetkilerine Dön</a>
                        <a href="{{ route('agency.tours.create') }}" class="btn btn-primary">Tur Oluştur</a>
                    </div>
                @elseif($order->isFailed())
                    <div style="font-size:48px;margin-bottom:8px;">❌</div>
                    <h1 style="font-size:24px;font-weight:800;color:#991b1b;">Ödeme Tamamlanamadı</h1>
                    <div style="font-size:14px;color:var(--text-meta);margin-top:8px;">
                        Sipariş No: <strong>{{ $order->order_number }}</strong>
                    </div>
                    @if($order->failure_reason)
                        <div style="margin-top:18px;padding:14px;background:#fef2f2;border:1px solid #fecaca;border-radius:12px;color:#991b1b;font-size:13px;text-align:left;">
                            {{ $order->failure_reason }}
                        </div>
                    @endif
                    <div style="margin-top:18px;font-size:13px;color:var(--text-sec);">
                        Sepetinizdeki kalemler korundu. Yeniden deneyebilirsiniz.
                    </div>
                    <div style="display:flex;gap:12px;justify-content:center;margin-top:24px;flex-wrap:wrap;">
                        <a href="{{ route('agency.category-licenses.cart.show') }}" class="btn btn-outline">Sepete Dön</a>
                        <a href="{{ route('agency.category-licenses.checkout-form') }}" class="btn btn-primary">Tekrar Dene</a>
                    </div>
                @else
                    <div style="font-size:48px;margin-bottom:8px;">⏳</div>
                    <h1 style="font-size:24px;font-weight:800;color:var(--text);">Ödeme Bekleniyor</h1>
                    <div style="font-size:14px;color:var(--text-meta);margin-top:8px;">
                        Sipariş No: <strong>{{ $order->order_number }}</strong>
                    </div>
                    <div style="margin-top:18px;font-size:13px;color:var(--text-sec);">
                        Bu siparişin ödeme onayı henüz iyzico tarafından bize iletilmedi. Birkaç saniye sonra sayfayı yenileyin.
                    </div>
                    <div style="display:flex;gap:12px;justify-content:center;margin-top:24px;flex-wrap:wrap;">
                        <a href="{{ url()->current() }}" class="btn btn-outline">Yenile</a>
                        <a href="{{ route('agency.category-licenses.index') }}" class="btn btn-primary">Kategori Yetkileri</a>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
