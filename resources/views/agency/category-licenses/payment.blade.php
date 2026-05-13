@extends('layouts.app')
@section('title', 'Güvenli Ödeme — iyzico')

@section('content')
<div class="container">
    <div>
        @include('partials.agency-sidebar')
        <div class="section" style="padding:24px 0 0 0;">
            <div style="max-width:94%;margin:0 auto 24px;">
                <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;">
                    <div>
                        <h1 style="font-size:26px;font-weight:800;letter-spacing:-0.5px;color:#0f172a;">Güvenli Ödeme</h1>
                        <div style="font-size:14px;color:#64748b;margin-top:4px;">
                            Sipariş No: <strong>{{ $order->order_number }}</strong> • Tutar: <strong>{{ number_format((float) $order->subtotal, 2, ',', '.') }} TL</strong>
                        </div>
                    </div>
                    <a href="{{ route('agency.category-licenses.checkout-form') }}" class="btn btn-outline">Geri</a>
                </div>
            </div>

            <div class="stat-card" style="max-width:94%;margin:0 auto 32px;padding:24px;">
                <div style="display:flex;align-items:center;gap:10px;font-size:13px;color:#475569;background:#eff6ff;border:1px solid #bfdbfe;padding:12px 16px;border-radius:14px;margin-bottom:18px;">
                    <span style="font-size:18px;">🔒</span>
                    <span>Kart bilgileriniz iyzico'nun PCI-DSS sertifikalı altyapısında işlenir; bizim sunucumuza ulaşmaz.</span>
                </div>

                <div id="iyzipay-checkout-form" class="responsive"></div>

                @if(empty($checkoutFormContent) && !empty($paymentPageUrl))
                    <div style="text-align:center;margin-top:18px;">
                        <a href="{{ $paymentPageUrl }}" class="btn btn-primary" target="_blank" rel="noopener">iyzico Ödeme Sayfasında Aç</a>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

@if(!empty($checkoutFormContent))
    {!! $checkoutFormContent !!}
@endif
@endsection
