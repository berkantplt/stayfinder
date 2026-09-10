@extends('layouts.app')
@section('title', 'Ödeme — Kategori Yetkileri')

@section('styles')
<style>
    #iyzico-checkout-form .form-input {
        width:100%; padding:11px 14px; border:1px solid var(--border); border-radius:12px;
        background:var(--white); font-size:14px; outline:none; margin-top:6px;
        transition:border-color .2s, box-shadow .2s; font-family:inherit;
    }
    #iyzico-checkout-form .form-input:focus {
        border-color:var(--accent-ink);
        box-shadow:0 0 0 3px rgba(15,118,110,0.15);
    }
    #iyzico-checkout-form label { font-size:13px; color:var(--text-sec); font-weight:600; display:block; }
    .kym-alanlar { display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:14px; }
    .kym-alanlar .tam { grid-column:1 / -1; }
    [data-buyer-tab].is-active { background:var(--white); color:var(--text); box-shadow:0 2px 6px rgba(15,23,42,.08); }
    /* Dar ekranda sepet özeti formun üstüne çıkar: ne ödendiği önce görülsün */
    @media(max-width:900px){ .kym-odeme-ozet { order:-1; } }
</style>
@endsection

@section('content')
@php $fiyat = fn ($tutar) => number_format((float) $tutar, 0, ',', '.'); @endphp
<div class="container">
    <div>
        @include('partials.agency-sidebar')
        <div class="section" style="padding:24px 0 0 0;">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;max-width:94%;margin:0 auto 24px;">
                <div>
                    <h1 style="font-size:26px;font-weight:800;letter-spacing:-0.5px;color:var(--text);">Ödeme Bilgileri</h1>
                    <div style="font-size:14px;color:var(--text-meta);margin-top:4px;">Faturalama bilgilerinizi doldurun, sonraki adımda iyzico üzerinden güvenli ödeme yapacaksınız.</div>
                </div>
                <a href="{{ route('agency.category-licenses.cart.show') }}" class="btn btn-outline">← Sepete Dön</a>
            </div>

            @if($errors->any())
                <div class="alert alert-error" style="max-width:94%;margin:0 auto 24px;">
                    @foreach($errors->all() as $error) {{ $error }}<br> @endforeach
                </div>
            @endif

            @unless($iyzicoConfigured)
                <div class="alert alert-error" style="max-width:94%;margin:0 auto 24px;">
                    Ödeme altyapısı henüz yapılandırılmamış. Lütfen yönetici ile iletişime geçin (IYZICO_API_KEY / IYZICO_SECRET_KEY tanımlanmalı).
                </div>
            @endunless

            <div class="panel-grid-yan" style="margin-bottom:32px;">
                <div class="stat-card" style="padding:28px;">
                    <form method="POST" action="{{ route('agency.category-licenses.initiate-payment') }}" id="iyzico-checkout-form">
                        @csrf

                        <div style="display:flex;gap:0;background:var(--border-light);padding:6px;border-radius:14px;margin-bottom:24px;" role="radiogroup" aria-label="Fatura tipi">
                            <label style="flex:1;cursor:pointer;">
                                <input type="radio" name="buyer_type" value="individual" {{ old('buyer_type', 'individual') === 'individual' ? 'checked' : '' }} style="position:absolute;opacity:0;width:1px;height:1px;" data-buyer-toggle>
                                <div data-buyer-tab="individual" style="padding:12px;text-align:center;border-radius:10px;font-weight:700;font-size:14px;color:var(--text-sec);transition:all .2s;">
                                    Bireysel (kendi adıma)
                                </div>
                            </label>
                            <label style="flex:1;cursor:pointer;">
                                <input type="radio" name="buyer_type" value="corporate" {{ old('buyer_type') === 'corporate' ? 'checked' : '' }} style="position:absolute;opacity:0;width:1px;height:1px;" data-buyer-toggle>
                                <div data-buyer-tab="corporate" style="padding:12px;text-align:center;border-radius:10px;font-weight:700;font-size:14px;color:var(--text-sec);transition:all .2s;">
                                    Tüzel (acenta adına)
                                </div>
                            </label>
                        </div>

                        <div data-buyer-section="corporate" style="display:none;border:1px solid var(--border);border-radius:14px;padding:18px;margin-bottom:18px;background:var(--bg);">
                            <div style="font-size:12px;color:var(--text-sec);font-weight:700;letter-spacing:.5px;text-transform:uppercase;margin-bottom:10px;">Şirket Bilgileri</div>
                            <div class="kym-alanlar">
                                <div class="tam">
                                    <label for="odeme-unvan">Ünvan</label>
                                    <input type="text" id="odeme-unvan" name="company_title" value="{{ old('company_title', $agency->name) }}" class="form-input" data-corporate-required autocomplete="organization">
                                </div>
                                <div>
                                    <label for="odeme-vergi-no">Vergi Numarası</label>
                                    <input type="text" id="odeme-vergi-no" name="tax_number" value="{{ old('tax_number') }}" class="form-input" data-corporate-required inputmode="numeric">
                                </div>
                                <div>
                                    <label for="odeme-vergi-dairesi">Vergi Dairesi</label>
                                    <input type="text" id="odeme-vergi-dairesi" name="tax_office" value="{{ old('tax_office') }}" class="form-input" data-corporate-required>
                                </div>
                            </div>
                        </div>

                        <div class="kym-alanlar">
                            <div>
                                <label for="odeme-ad">Ad <span data-corporate-only style="color:var(--text-muted);font-weight:400;">(yetkili kişi)</span></label>
                                <input type="text" id="odeme-ad" name="name" value="{{ old('name') }}" class="form-input" required autocomplete="given-name">
                            </div>
                            <div>
                                <label for="odeme-soyad">Soyad <span data-corporate-only style="color:var(--text-muted);font-weight:400;">(yetkili kişi)</span></label>
                                <input type="text" id="odeme-soyad" name="surname" value="{{ old('surname') }}" class="form-input" required autocomplete="family-name">
                            </div>
                            <div>
                                <label for="odeme-tc">TC Kimlik No</label>
                                <input type="text" id="odeme-tc" name="identity_number" value="{{ old('identity_number') }}" maxlength="11" minlength="11" class="form-input" required inputmode="numeric">
                            </div>
                            <div>
                                <label for="odeme-gsm">GSM</label>
                                <input type="tel" id="odeme-gsm" name="gsm" value="{{ old('gsm', $agency->phone) }}" placeholder="+905..." class="form-input" required autocomplete="tel">
                            </div>
                            <div class="tam">
                                <label for="odeme-eposta">E-posta</label>
                                <input type="email" id="odeme-eposta" name="email" value="{{ old('email', $agency->email) }}" class="form-input" required autocomplete="email">
                            </div>
                            <div class="tam">
                                <label for="odeme-adres">Adres</label>
                                <textarea id="odeme-adres" name="address" rows="2" class="form-input" required autocomplete="street-address">{{ old('address', $agency->address) }}</textarea>
                            </div>
                            <div>
                                <label for="odeme-sehir">Şehir</label>
                                <input type="text" id="odeme-sehir" name="city" value="{{ old('city') }}" class="form-input" required autocomplete="address-level2">
                            </div>
                            <div>
                                <label for="odeme-posta">Posta Kodu</label>
                                <input type="text" id="odeme-posta" name="zip_code" value="{{ old('zip_code') }}" class="form-input" autocomplete="postal-code">
                            </div>
                            <input type="hidden" name="country" value="Turkey">
                        </div>

                        @if($autoRenewEnabled)
                            <div style="margin-top:20px;padding:14px 16px;border:1px solid #bae6fd;border-radius:12px;background:#f0f9ff;color:#0c4a6e;font-size:13px;line-height:1.6;">
                                💳 iyzico formunda <strong>"Kartımı sakla"</strong> seçeneğini işaretlerseniz aboneliğiniz her ay güncel tarife üzerinden otomatik yenilenir. Dilediğinizde Kategori Yetkileri sayfasından iptal edebilirsiniz; iptalde dönem sonuna kadar kullanım sürer, çekim yapılmaz.
                            </div>
                        @endif

                        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;margin-top:24px;" {{ $iyzicoConfigured ? '' : 'disabled' }}>
                            iyzico ile Güvenli Ödemeye Geç →
                        </button>
                        <div style="font-size:12px;color:var(--text-meta);margin-top:10px;text-align:center;">
                            Sonraki adımda kart bilgilerinizi iyzico'nun güvenli formuna girersiniz. Kart verileriniz bizim sunucumuza ulaşmaz.
                        </div>
                    </form>
                </div>

                <div class="stat-card kym-odeme-ozet" style="padding:24px;align-self:start;">
                    <h2 style="font-size:18px;font-weight:700;color:var(--text);margin-bottom:16px;">Sepet Özeti</h2>
                    <div style="display:flex;flex-direction:column;gap:10px;">
                        @foreach($cartCategories as $category)
                            <div style="display:flex;justify-content:space-between;gap:10px;font-size:14px;color:var(--text-sec);">
                                <span style="min-width:0;">
                                    {{ $category->name }}
                                    @if($category->cart_renewal)
                                        <span style="color:var(--text-muted);font-size:12px;">(yenileme · yeni bitiş {{ $category->renewal_to?->format('d.m.Y') }})</span>
                                    @else
                                        <span style="color:var(--text-muted);font-size:12px;">(aylık)</span>
                                    @endif
                                </span>
                                <strong style="white-space:nowrap;">{{ $fiyat($category->monthly_price) }} TL</strong>
                            </div>
                        @endforeach
                        @foreach($slotCartItems as $slotItem)
                            <div style="display:flex;justify-content:space-between;gap:10px;font-size:14px;color:var(--text-sec);">
                                <span style="min-width:0;">{{ $slotItem->category->name }} — Ekstra Tur Hakkı{{ $slotItem->quantity > 1 ? ' ×'.$slotItem->quantity : '' }} <span style="color:var(--text-muted);font-size:12px;">({{ $autoRenewEnabled ? 'aylık' : 'tek seferlik' }})</span></span>
                                <strong style="white-space:nowrap;">{{ $fiyat($slotItem->line_total) }} TL</strong>
                            </div>
                        @endforeach
                    </div>
                    <div style="border-top:1px solid var(--border);margin-top:16px;padding-top:16px;display:flex;justify-content:space-between;align-items:center;">
                        <span style="font-size:14px;color:var(--text-sec);">Toplam</span>
                        <strong style="font-size:22px;color:var(--text);">{{ $fiyat($cartTotal) }} TL</strong>
                    </div>
                    <div style="font-size:12px;color:var(--text-meta);margin-top:10px;line-height:1.5;">Ödeme onaylandıktan sonra yeni kategoriler 1 ay aktive edilir, yenilemeler mevcut bitişe 1 ay ekler; ekstra tur hakları aboneliğinize anında tanımlanır.</div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    const form = document.getElementById('iyzico-checkout-form');
    if (!form) return;
    const tabs = form.querySelectorAll('[data-buyer-tab]');
    const radios = form.querySelectorAll('[data-buyer-toggle]');
    const corporateSection = form.querySelector('[data-buyer-section="corporate"]');
    const corporateOnlyHints = form.querySelectorAll('[data-corporate-only]');
    const corporateRequired = form.querySelectorAll('[data-corporate-required]');

    function applyType(type) {
        tabs.forEach(tab => tab.classList.toggle('is-active', tab.dataset.buyerTab === type));
        const isCorporate = type === 'corporate';
        corporateSection.style.display = isCorporate ? 'block' : 'none';
        corporateOnlyHints.forEach(el => el.style.display = isCorporate ? 'inline' : 'none');
        corporateRequired.forEach(el => {
            if (isCorporate) {
                el.setAttribute('required', 'required');
            } else {
                el.removeAttribute('required');
            }
        });
    }

    radios.forEach(radio => {
        radio.addEventListener('change', () => applyType(radio.value));
    });

    const initial = form.querySelector('[data-buyer-toggle]:checked')?.value || 'individual';
    applyType(initial);
})();
</script>
@endsection
