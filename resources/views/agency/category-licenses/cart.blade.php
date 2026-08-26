@extends('layouts.app')
@section('title', 'Sepetim — Kategori Yetkilendirme')

@section('content')
<div class="container">
    <div>
        @include('partials.agency-sidebar')
        <div class="section" style="padding:24px 0 0 0;">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;max-width:94%;margin:0 auto 24px;">
                <div>
                    <h1 style="font-size:26px;font-weight:800;letter-spacing:-0.5px;color:#0f172a;">Sepetim</h1>
                    <div style="font-size:14px;color:#64748b;margin-top:4px;">Kategori yetkileri ve ekstra tur haklarınızı gözden geçirip ödemeye geçin.</div>
                </div>
                <a href="{{ route('agency.category-licenses.index') }}" class="btn btn-outline">← Yetkilendirme Merkezi</a>
            </div>

            @if(session('success'))
                <div class="alert alert-success" style="max-width:94%;margin:0 auto 24px;">{{ session('success') }}</div>
            @endif

            @if($errors->any())
                <div class="alert alert-error" style="max-width:94%;margin:0 auto 24px;">
                    @foreach($errors->all() as $error) {{ $error }}<br> @endforeach
                </div>
            @endif

            <div style="max-width:720px;margin:0 auto;">
                @if($cartItems->isEmpty() && $slotCartItems->isEmpty())
                    <div class="stat-card" style="padding:32px;text-align:center;">
                        <div style="font-size:34px;">🛒</div>
                        <div style="font-size:16px;font-weight:700;color:#0f172a;margin-top:10px;">Sepetiniz boş</div>
                        <div style="font-size:13px;color:#64748b;margin-top:6px;">Satın alınabilir kategorilerden seçim yaparak başlayın.</div>
                        <a href="{{ route('agency.category-licenses.index') }}" class="btn btn-primary" style="margin-top:16px;">Kategorilere Göz At</a>
                    </div>
                @else
                    <div class="stat-card" style="padding:24px;">
                        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:16px;">
                            <h2 style="font-size:17px;font-weight:700;color:#0f172a;">Sepet Kalemleri</h2>
                            <span class="badge">{{ $cartItems->count() + $slotCartItems->count() }} kalem</span>
                        </div>

                        <div style="display:flex;flex-direction:column;gap:12px;">
                            @foreach($cartItems as $category)
                                <div style="border:1px solid #e2e8f0;border-radius:14px;padding:14px 16px;background:#fff;">
                                    <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
                                        <div>
                                            <div style="font-weight:700;color:#0f172a;">{{ $category->icon }} {{ $category->name }}</div>
                                            <div style="font-size:12px;color:#94a3b8;margin-top:4px;">
                                                Kategori yetkisi · {{ number_format((float) $category->monthly_price, 0, ',', '.') }} TL / ay
                                                @if($slotSchemaReady)
                                                    · {{ \App\Support\CategoryLicensing::BASE_TOUR_ALLOWANCE }} tur hakkı dahil
                                                @endif
                                            </div>
                                        </div>
                                        <form method="POST" action="{{ route('agency.category-licenses.cart.remove', $category) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline btn-sm">Kaldır</button>
                                        </form>
                                    </div>
                                    @if($slotSchemaReady)
                                        <form method="POST" action="{{ route('agency.category-licenses.cart.add-slot') }}" style="margin-top:10px;">
                                            @csrf
                                            <input type="hidden" name="category_id" value="{{ $category->id }}">
                                            <button type="submit" class="btn btn-outline btn-sm" style="font-size:11px;padding:3px 8px;">+ Ekstra tur hakkı ekle ({{ number_format((float) $category->extra_tour_price, 0, ',', '.') }} TL{{ $autoRenewEnabled ? ' / ay' : '' }})</button>
                                        </form>
                                    @endif
                                </div>
                            @endforeach

                            @foreach($slotCartItems as $slotItem)
                                <div style="border:1px solid #e2e8f0;border-radius:14px;padding:14px 16px;background:#fff;">
                                    <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
                                        <div>
                                            <div style="font-weight:700;color:#0f172a;">{{ $slotItem->category->name }} — Ekstra Tur Hakkı{{ $slotItem->quantity > 1 ? ' ×'.$slotItem->quantity : '' }}</div>
                                            <div style="font-size:12px;color:#94a3b8;margin-top:4px;">
                                                {{ number_format((float) $slotItem->unit_price, 0, ',', '.') }} TL / hak
                                                @if($slotItem->quantity > 1)
                                                    · toplam {{ number_format((float) $slotItem->line_total, 0, ',', '.') }} TL
                                                @endif
                                                {{ $autoRenewEnabled ? '· aylık' : '· tek seferlik' }}
                                            </div>
                                        </div>
                                        <div style="display:flex;gap:6px;align-items:center;">
                                            <form method="POST" action="{{ route('agency.category-licenses.cart.add-slot') }}">
                                                @csrf
                                                <input type="hidden" name="category_id" value="{{ $slotItem->category->id }}">
                                                <button type="submit" class="btn btn-outline btn-sm" title="Bir hak daha ekle">+1</button>
                                            </form>
                                            <form method="POST" action="{{ route('agency.category-licenses.cart.remove-slot', $slotItem->category) }}">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-outline btn-sm">Kaldır</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div style="margin-top:18px;padding-top:16px;border-top:1px solid #e2e8f0;">
                            <div style="display:flex;align-items:center;justify-content:space-between;font-size:14px;color:#475569;">
                                <span>İlk dönem toplamı</span>
                                <strong style="font-size:22px;color:#0f172a;">{{ number_format($cartTotal, 0, ',', '.') }} TL</strong>
                            </div>
                            <div style="font-size:12px;color:#94a3b8;margin-top:8px;">
                                @if($autoRenewEnabled)
                                    Satın alım sonrası kategoriler 1 aylık aktif edilir; ekstra tur hakları aboneliğinize anında tanımlanır ve abonelikle birlikte her ay yenilenip ücretlendirilir.
                                @else
                                    Satın alım sonrası kategoriler 1 aylık aktif edilir; ekstra tur hakları aboneliğinize anında tanımlanır ve abonelik sürdükçe geçerlidir.
                                @endif
                            </div>
                            <a href="{{ route('agency.category-licenses.checkout-form') }}" class="btn btn-primary" style="width:100%;justify-content:center;margin-top:14px;">
                                Ödemeye Geç
                            </a>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
