@extends('layouts.app')
@section('title', 'Ödeme Sonucu')

@section('content')
<div class="container">
    <div>
        @include('partials.agency-sidebar')
        <div class="section" style="padding:24px 0 0 0;">
            <div class="stat-card" style="max-width:720px;margin:24px auto;padding:32px;text-align:center;">
                @if($order->isPaid())
                    <div style="font-size:48px;margin-bottom:8px;">✅</div>
                    <h1 style="font-size:24px;font-weight:800;color:#0f172a;">Ödemeniz Başarıyla Alındı</h1>
                    <div style="font-size:14px;color:#64748b;margin-top:8px;">
                        Sipariş No: <strong>{{ $order->order_number }}</strong> • Toplam: <strong>{{ number_format((float) $order->subtotal, 2, ',', '.') }} TL</strong>
                    </div>

                    <div style="margin-top:24px;text-align:left;border:1px solid #e2e8f0;border-radius:14px;overflow:hidden;">
                        <div style="background:#f8fafc;padding:12px 16px;font-size:12px;color:#64748b;font-weight:700;letter-spacing:.5px;text-transform:uppercase;">Aktif edilen kategoriler</div>
                        @foreach($order->items as $item)
                            <div style="padding:12px 16px;display:flex;justify-content:space-between;border-top:1px solid #e2e8f0;font-size:14px;color:#0f172a;">
                                <span>{{ $item->category_name }}</span>
                                <strong>{{ number_format((float) $item->unit_price, 0, ',', '.') }} TL / ay</strong>
                            </div>
                        @endforeach
                    </div>

                    <div style="display:flex;gap:12px;justify-content:center;margin-top:24px;flex-wrap:wrap;">
                        <a href="{{ route('agency.category-licenses.index') }}" class="btn btn-outline">Yetki Merkezine Dön</a>
                        <a href="{{ route('agency.tours.create') }}" class="btn btn-primary">Tur Oluştur</a>
                    </div>
                @elseif($order->isFailed())
                    <div style="font-size:48px;margin-bottom:8px;">❌</div>
                    <h1 style="font-size:24px;font-weight:800;color:#991b1b;">Ödeme Tamamlanamadı</h1>
                    <div style="font-size:14px;color:#64748b;margin-top:8px;">
                        Sipariş No: <strong>{{ $order->order_number }}</strong>
                    </div>
                    @if($order->failure_reason)
                        <div style="margin-top:18px;padding:14px;background:#fef2f2;border:1px solid #fecaca;border-radius:12px;color:#991b1b;font-size:13px;text-align:left;">
                            {{ $order->failure_reason }}
                        </div>
                    @endif
                    <div style="margin-top:18px;font-size:13px;color:#475569;">
                        Sepetinizdeki kategoriler korundu. Yeniden deneyebilirsiniz.
                    </div>
                    <div style="display:flex;gap:12px;justify-content:center;margin-top:24px;flex-wrap:wrap;">
                        <a href="{{ route('agency.category-licenses.index') }}" class="btn btn-outline">Sepete Dön</a>
                        <a href="{{ route('agency.category-licenses.checkout-form') }}" class="btn btn-primary">Tekrar Dene</a>
                    </div>
                @else
                    <div style="font-size:48px;margin-bottom:8px;">⏳</div>
                    <h1 style="font-size:24px;font-weight:800;color:#0f172a;">Ödeme Bekleniyor</h1>
                    <div style="font-size:14px;color:#64748b;margin-top:8px;">
                        Sipariş No: <strong>{{ $order->order_number }}</strong>
                    </div>
                    <div style="margin-top:18px;font-size:13px;color:#475569;">
                        Bu siparişin ödeme onayı henüz iyzico tarafından bize iletilmedi. Birkaç saniye sonra sayfayı yenileyin.
                    </div>
                    <div style="display:flex;gap:12px;justify-content:center;margin-top:24px;flex-wrap:wrap;">
                        <a href="{{ url()->current() }}" class="btn btn-outline">Yenile</a>
                        <a href="{{ route('agency.category-licenses.index') }}" class="btn btn-primary">Yetki Merkezi</a>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
