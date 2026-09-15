@extends('layouts.app')
@section('title', 'Sipariş '.$order->order_number.' — Admin')

@php
    // B9 — Sipariş detayı. Para/durum alanları salt okunur; yalnız PENDING siparişte
    // "manuel tamamla" (finalizer ile lisans açılır) ve "iptal et" işlemleri var.
    $durumEtiket = [
        \App\Models\AgencyCategoryOrder::STATUS_PAID => ['Ödendi', 'p-etiket-basari'],
        \App\Models\AgencyCategoryOrder::STATUS_PENDING => ['Ödeme bekliyor', 'p-etiket-uyari'],
        \App\Models\AgencyCategoryOrder::STATUS_FAILED => ['Başarısız', 'p-etiket-tehlike'],
        \App\Models\AgencyCategoryOrder::STATUS_CANCELLED => ['İptal', 'p-etiket-notr'],
    ][$order->status] ?? [$order->status, 'p-etiket-notr'];
    $tl = fn ($v) => number_format((float) $v, 2, ',', '.').' '.($order->currency ?: 'TRY');
    $buyerEtiket = ['name' => 'Ad Soyad', 'first_name' => 'Ad', 'last_name' => 'Soyad', 'email' => 'E-posta', 'phone' => 'Telefon',
        'identity_number' => 'TC Kimlik', 'tckn' => 'TC Kimlik', 'company_title' => 'Ünvan', 'tax_number' => 'Vergi No', 'tax_office' => 'Vergi Dairesi',
        'address' => 'Adres', 'city' => 'Şehir', 'country' => 'Ülke', 'buyer_type' => 'Alıcı türü'];
@endphp

@section('content')
<div class="container">
    <div>
        @include('partials.admin-sidebar')
        <div class="section" style="padding:0;">
        @include('partials.breadcrumb', ['schema' => false, 'root' => ['name' => 'Admin', 'url' => route('admin.dashboard')], 'items' => [['name' => 'Kategori Yetkilendirme', 'url' => route('admin.category-licenses.index')], ['name' => 'Siparişler', 'url' => route('admin.category-licenses.orders')], ['name' => $order->order_number]]])

            <div style="max-width:94%;margin:0 auto 20px;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;">
                <div>
                    <h1 class="p-sayfa-baslik">Sipariş {{ $order->order_number }} <span class="p-etiket {{ $durumEtiket[1] }}" style="vertical-align:middle;margin-left:8px;">{{ $durumEtiket[0] }}</span></h1>
                    <p class="p-alt" style="margin-top:4px;">
                        <a href="{{ route('admin.agencies.show', $order->agency) }}" style="font-weight:700;color:inherit;">{{ $order->agency?->name ?? '—' }}</a>
                        · oluşturma {{ $order->purchased_at?->format('d.m.Y H:i') ?? '—' }}
                        · ödeme {{ $order->paid_at?->format('d.m.Y H:i') ?? '—' }}
                    </p>
                </div>
                <div style="font-size:28px;font-weight:800;">{{ $tl($order->subtotal) }}</div>
            </div>

            <div style="max-width:94%;margin:0 auto;display:grid;grid-template-columns:minmax(0,1.4fr) minmax(300px,1fr);gap:20px;">
                <div style="display:flex;flex-direction:column;gap:20px;">
                    <div class="p-kart">
                        <h2 class="p-kart-baslik">Kalemler</h2>
                        <div class="table-wrap"><table class="table" style="width:100%;">
                            <thead><tr><th>Kalem</th><th>Tür</th><th>Dönem</th><th style="text-align:right;">Tutar</th><th>Abonelik (şu an)</th></tr></thead>
                            <tbody>
                            @foreach($order->items as $item)
                                @php($sub = $subscriptions->get($item->category_id))
                                <tr>
                                    <td style="font-weight:600;">{{ $item->category_name }}@if($item->category && $item->category->name !== $item->category_name) <span class="p-alt">(şimdi: {{ $item->category->name }})</span>@endif</td>
                                    <td>{{ $item->isExtraSlot() ? 'Ekstra tur hakkı' : 'Kategori lisansı' }}</td>
                                    <td>{{ $item->billing_cycle === 'monthly' ? 'Aylık' : $item->billing_cycle }}</td>
                                    <td style="text-align:right;">{{ $tl($item->unit_price) }}</td>
                                    <td>
                                        @if($sub)
                                            <span class="p-etiket {{ $sub->is_active ? 'p-etiket-basari' : 'p-etiket-notr' }}">{{ $sub->status }}</span>
                                            <span class="p-alt">bitiş {{ $sub->expires_at?->format('d.m.Y') ?? '—' }}{{ $sub->extra_tour_slots ? ' · +'.$sub->extra_tour_slots.' hak' : '' }}</span>
                                        @else
                                            <span class="p-alt">abonelik kaydı yok</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table></div>
                    </div>

                    @if($order->isPending())
                        <div class="p-kart" style="border-color:var(--p-uyari);">
                            <h2 class="p-kart-baslik">İşlemler (ödeme bekleyen sipariş)</h2>
                            <p class="p-alt" style="margin-bottom:12px;">
                                <strong>Manuel tamamla:</strong> paranın başka yoldan (havale, iyzico panelinde görünen ama callback'i düşmüş ödeme) alındığından eminseniz. Sipariş ÖDENDİ olur ve abonelikler satın alma akışıyla birebir aynı kuralla açılır/uzatılır. Geri alınamaz.<br>
                                <strong>İptal et:</strong> ödeme gelmeyecekse. Acentanın fatura bilgileri (KVKK) siparişten silinir.
                            </p>
                            <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-start;">
                                <form method="POST" action="{{ route('admin.category-licenses.orders.complete', $order) }}" onsubmit="return confirm('Sipariş ÖDENDİ olarak işaretlenecek ve lisanslar açılacak. Para gerçekten alındı mı? Bu işlem geri alınamaz.');">
                                    @csrf
                                    <input type="text" name="reference" maxlength="120" placeholder="Referans (dekont no, iyzico ödeme no)" required style="padding:9px 12px;border:1px solid var(--p-cizgi);border-radius:10px;min-width:260px;">
                                    @error('reference')<p class="p-hata">{{ $message }}</p>@enderror
                                    <button type="submit" class="p-btn p-btn-birincil" style="margin-top:8px;">✓ Manuel tamamla (lisansı aç)</button>
                                </form>
                                <form method="POST" action="{{ route('admin.category-licenses.orders.cancel', $order) }}" onsubmit="return confirm('Sipariş iptal edilecek. Emin misiniz?');">
                                    @csrf
                                    <input type="text" name="reason" maxlength="200" placeholder="İptal gerekçesi" required style="padding:9px 12px;border:1px solid var(--p-cizgi);border-radius:10px;min-width:260px;">
                                    @error('reason')<p class="p-hata">{{ $message }}</p>@enderror
                                    <button type="submit" class="p-btn p-btn-tehlike" style="margin-top:8px;">✕ Siparişi iptal et</button>
                                </form>
                            </div>
                        </div>
                    @elseif($order->isPaid())
                        <div class="p-alt">Ödenmiş siparişte iade panelden yapılmaz: iade iyzico satıcı panelinden yapılır, ardından ilgili abonelik acenta sayfasından iptal edilir.</div>
                    @endif
                </div>

                <div style="display:flex;flex-direction:column;gap:20px;">
                    <div class="p-kart">
                        <h2 class="p-kart-baslik">Ödeme</h2>
                        <dl style="display:grid;grid-template-columns:auto 1fr;gap:6px 14px;font-size:13px;margin:0;">
                            <dt class="p-alt">Yöntem</dt><dd style="margin:0;">{{ $order->payment_provider ?: '—' }}</dd>
                            <dt class="p-alt">Otomatik yenileme</dt><dd style="margin:0;">{{ $order->auto_renewal ? 'evet' : 'hayır' }}</dd>
                            <dt class="p-alt">Sağlayıcı ödeme no</dt><dd style="margin:0;font-family:monospace;">{{ $order->provider_payment_id ?: '—' }}</dd>
                            <dt class="p-alt">Sağlayıcı token</dt><dd style="margin:0;font-family:monospace;word-break:break-all;">{{ $order->provider_token ? \Illuminate\Support\Str::limit($order->provider_token, 18) : '—' }}</dd>
                            <dt class="p-alt">Alıcı türü</dt><dd style="margin:0;">{{ $order->buyer_type === \App\Models\AgencyCategoryOrder::BUYER_CORPORATE ? 'Tüzel (şirket)' : 'Bireysel' }}</dd>
                            @if($order->failure_reason)<dt class="p-alt">Hata / not</dt><dd style="margin:0;color:var(--p-tehlike-metin);">{{ $order->failure_reason }}</dd>@endif
                        </dl>
                    </div>
                    <div class="p-kart">
                        <h2 class="p-kart-baslik">Fatura bilgileri</h2>
                        @if(is_array($buyer) && $buyer !== [])
                            <dl style="display:grid;grid-template-columns:auto 1fr;gap:6px 14px;font-size:13px;margin:0;">
                                @foreach($buyer as $k => $v)
                                    @if(is_scalar($v) && (string) $v !== '')
                                        <dt class="p-alt">{{ $buyerEtiket[$k] ?? \Illuminate\Support\Str::headline((string) $k) }}</dt><dd style="margin:0;">{{ $v }}</dd>
                                    @endif
                                @endforeach
                            </dl>
                        @else
                            <div class="p-alt">Fatura bilgisi yok (iptal/zaman aşımı sonrası KVKK gereği silinir ya da manuel siparişlerde toplanmaz).</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
