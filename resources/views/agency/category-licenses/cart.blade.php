@extends('layouts.app')
@section('title', 'Sepetim — Kategori Yetkileri')

@section('content')
@php $fiyat = fn ($tutar) => number_format((float) $tutar, 0, ',', '.'); @endphp
<div class="container">
    <div>
        @include('partials.agency-sidebar')
        <div class="section" style="padding:24px 0 0 0;">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;max-width:94%;margin:0 auto 24px;">
                <div>
                    <h1 style="font-size:26px;font-weight:800;letter-spacing:-0.5px;color:var(--text);">Sepetim</h1>
                    <div style="font-size:14px;color:var(--text-meta);margin-top:4px;">Kategori yetkileri, yenilemeler ve ekstra tur haklarınızı gözden geçirip ödemeye geçin.</div>
                </div>
                <a href="{{ route('agency.category-licenses.index') }}" class="btn btn-outline">← Kategori Yetkileri</a>
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
                        <div style="font-size:16px;font-weight:700;color:var(--text);margin-top:10px;">Sepetiniz boş</div>
                        <div style="font-size:13px;color:var(--text-meta);margin-top:6px;">Satın alınabilir kategorilerden seçim yaparak başlayın.</div>
                        <a href="{{ route('agency.category-licenses.index') }}" class="btn btn-primary" style="margin-top:16px;">Kategorilere Göz At</a>
                    </div>
                @else
                    <div class="stat-card" style="padding:24px;">
                        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:16px;">
                            <h2 style="font-size:17px;font-weight:700;color:var(--text);">Sepet Kalemleri</h2>
                            <span class="badge" style="background:var(--border-light);color:var(--text-meta);">{{ $cartItems->count() + $slotCartItems->count() }} kalem</span>
                        </div>

                        <div style="display:flex;flex-direction:column;gap:12px;">
                            @foreach($cartItems as $category)
                                <div style="border:1px solid var(--border);border-radius:14px;padding:14px 16px;background:var(--white);">
                                    <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                                        <div style="min-width:0;">
                                            <div style="font-weight:700;color:var(--text);">
                                                {{ $category->icon }} {{ $category->name }}
                                                @if($category->cart_renewal)
                                                    <span class="badge badge-green" style="margin-left:6px;">Yenileme</span>
                                                @endif
                                            </div>
                                            <div style="font-size:12px;color:var(--text-meta);margin-top:4px;line-height:1.5;">
                                                @if($category->cart_renewal)
                                                    Mevcut bitiş {{ $category->renewal_from?->format('d.m.Y') }} → yeni bitiş <strong style="color:var(--text);">{{ $category->renewal_to?->format('d.m.Y') }}</strong>
                                                    · {{ $fiyat($category->monthly_price) }} TL / ay · kalan günler yanmaz
                                                @else
                                                    Kategori yetkisi · {{ $fiyat($category->monthly_price) }} TL / ay
                                                    @if($slotSchemaReady)
                                                        · {{ \App\Support\CategoryLicensing::BASE_TOUR_ALLOWANCE }} tur hakkı dahil
                                                    @endif
                                                @endif
                                            </div>
                                        </div>
                                        <form method="POST" action="{{ route('agency.category-licenses.cart.remove', $category) }}" style="margin:0;">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline btn-sm">Kaldır</button>
                                        </form>
                                    </div>
                                    @if($slotSchemaReady)
                                        <form method="POST" action="{{ route('agency.category-licenses.cart.add-slot') }}" style="margin-top:10px;">
                                            @csrf
                                            <input type="hidden" name="category_id" value="{{ $category->id }}">
                                            <button type="submit" class="btn btn-outline btn-sm" style="font-size:11.5px;padding:4px 10px;">+ Ekstra tur hakkı ekle ({{ $fiyat($category->extra_tour_price) }} TL{{ $autoRenewEnabled ? ' / ay' : '' }})</button>
                                        </form>
                                    @endif
                                </div>
                            @endforeach

                            @foreach($slotCartItems as $slotItem)
                                <div style="border:1px solid var(--border);border-radius:14px;padding:14px 16px;background:var(--white);">
                                    <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                                        <div style="min-width:0;">
                                            <div style="font-weight:700;color:var(--text);">{{ $slotItem->category->name }} — Ekstra Tur Hakkı{{ $slotItem->quantity > 1 ? ' ×'.$slotItem->quantity : '' }}</div>
                                            <div style="font-size:12px;color:var(--text-meta);margin-top:4px;">
                                                {{ $fiyat($slotItem->unit_price) }} TL / hak
                                                @if($slotItem->quantity > 1)
                                                    · toplam {{ $fiyat($slotItem->line_total) }} TL
                                                @endif
                                                {{ $autoRenewEnabled ? '· aylık' : '· tek seferlik' }}
                                            </div>
                                        </div>
                                        <div style="display:flex;gap:6px;align-items:center;">
                                            <form method="POST" action="{{ route('agency.category-licenses.cart.add-slot') }}" style="margin:0;">
                                                @csrf
                                                <input type="hidden" name="category_id" value="{{ $slotItem->category->id }}">
                                                <button type="submit" class="btn btn-outline btn-sm" title="Bir hak daha ekle" aria-label="Bir hak daha ekle">+1</button>
                                            </form>
                                            <form method="POST" action="{{ route('agency.category-licenses.cart.remove-slot', $slotItem->category) }}" style="margin:0;">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-outline btn-sm">Kaldır</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div style="margin-top:18px;padding-top:16px;border-top:1px solid var(--border);">
                            <div style="display:flex;align-items:center;justify-content:space-between;font-size:14px;color:var(--text-sec);">
                                <span>İlk dönem toplamı</span>
                                <strong style="font-size:22px;color:var(--text);">{{ $fiyat($cartTotal) }} TL</strong>
                            </div>
                            <div style="font-size:12px;color:var(--text-meta);margin-top:8px;line-height:1.5;">
                                @if($autoRenewEnabled)
                                    Yeni kategoriler 1 ay aktif edilir, yenilemeler mevcut bitişe 1 ay ekler; ekstra tur hakları aboneliğinize anında tanımlanır ve abonelikle birlikte her ay yenilenip ücretlendirilir.
                                @else
                                    Yeni kategoriler 1 ay aktif edilir, yenilemeler mevcut bitişe 1 ay ekler; ekstra tur hakları aboneliğinize anında tanımlanır ve abonelik sürdükçe geçerlidir.
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
