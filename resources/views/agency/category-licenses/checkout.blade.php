@extends('layouts.app')
@section('title', 'Ödeme — Kategori Yetkilendirme')

@section('content')
<div class="container">
    <div>
        @include('partials.agency-sidebar')
        <div class="section" style="padding:24px 0 0 0;">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;max-width:94%;margin:0 auto 24px;">
                <div>
                    <h1 style="font-size:26px;font-weight:800;letter-spacing:-0.5px;color:#0f172a;">Ödeme Bilgileri</h1>
                    <div style="font-size:14px;color:#64748b;margin-top:4px;">Faturalama bilgilerinizi doldurun, sonraki adımda iyzico üzerinden güvenli ödeme yapacaksınız.</div>
                </div>
                <a href="{{ route('agency.category-licenses.index') }}" class="btn btn-outline">Sepete Dön</a>
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

            <div style="display:grid;grid-template-columns:1.4fr 1fr;gap:24px;max-width:94%;margin:0 auto 32px;">
                <div class="stat-card" style="padding:28px;">
                    <form method="POST" action="{{ route('agency.category-licenses.initiate-payment') }}" id="iyzico-checkout-form">
                        @csrf

                        <div style="display:flex;gap:0;background:#f1f5f9;padding:6px;border-radius:14px;margin-bottom:24px;">
                            <label style="flex:1;cursor:pointer;">
                                <input type="radio" name="buyer_type" value="individual" {{ old('buyer_type', 'individual') === 'individual' ? 'checked' : '' }} style="display:none;" data-buyer-toggle>
                                <div data-buyer-tab="individual" style="padding:12px;text-align:center;border-radius:10px;font-weight:700;font-size:14px;color:#475569;transition:all .2s;">
                                    Bireysel (kendi adıma)
                                </div>
                            </label>
                            <label style="flex:1;cursor:pointer;">
                                <input type="radio" name="buyer_type" value="corporate" {{ old('buyer_type') === 'corporate' ? 'checked' : '' }} style="display:none;" data-buyer-toggle>
                                <div data-buyer-tab="corporate" style="padding:12px;text-align:center;border-radius:10px;font-weight:700;font-size:14px;color:#475569;transition:all .2s;">
                                    Tüzel (acenta adına)
                                </div>
                            </label>
                        </div>

                        <div data-buyer-section="corporate" style="display:none;border:1px solid #e2e8f0;border-radius:14px;padding:18px;margin-bottom:18px;background:#f8fafc;">
                            <div style="font-size:12px;color:#475569;font-weight:700;letter-spacing:.5px;text-transform:uppercase;margin-bottom:10px;">Şirket Bilgileri</div>
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                                <div style="grid-column:1 / -1;">
                                    <label style="font-size:13px;color:#475569;font-weight:600;">Ünvan</label>
                                    <input type="text" name="company_title" value="{{ old('company_title', $agency->name) }}" class="form-input" data-corporate-required>
                                </div>
                                <div>
                                    <label style="font-size:13px;color:#475569;font-weight:600;">Vergi Numarası</label>
                                    <input type="text" name="tax_number" value="{{ old('tax_number') }}" class="form-input" data-corporate-required>
                                </div>
                                <div>
                                    <label style="font-size:13px;color:#475569;font-weight:600;">Vergi Dairesi</label>
                                    <input type="text" name="tax_office" value="{{ old('tax_office') }}" class="form-input" data-corporate-required>
                                </div>
                            </div>
                        </div>

                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                            <div>
                                <label style="font-size:13px;color:#475569;font-weight:600;">Ad <span data-corporate-only style="color:#94a3b8;font-weight:400;">(yetkili kişi)</span></label>
                                <input type="text" name="name" value="{{ old('name') }}" class="form-input" required>
                            </div>
                            <div>
                                <label style="font-size:13px;color:#475569;font-weight:600;">Soyad <span data-corporate-only style="color:#94a3b8;font-weight:400;">(yetkili kişi)</span></label>
                                <input type="text" name="surname" value="{{ old('surname') }}" class="form-input" required>
                            </div>
                            <div>
                                <label style="font-size:13px;color:#475569;font-weight:600;">TC Kimlik No</label>
                                <input type="text" name="identity_number" value="{{ old('identity_number') }}" maxlength="11" minlength="11" class="form-input" required>
                            </div>
                            <div>
                                <label style="font-size:13px;color:#475569;font-weight:600;">GSM</label>
                                <input type="text" name="gsm" value="{{ old('gsm', $agency->phone) }}" placeholder="+905..." class="form-input" required>
                            </div>
                            <div style="grid-column:1 / -1;">
                                <label style="font-size:13px;color:#475569;font-weight:600;">E-posta</label>
                                <input type="email" name="email" value="{{ old('email', $agency->email) }}" class="form-input" required>
                            </div>
                            <div style="grid-column:1 / -1;">
                                <label style="font-size:13px;color:#475569;font-weight:600;">Adres</label>
                                <textarea name="address" rows="2" class="form-input" required>{{ old('address', $agency->address) }}</textarea>
                            </div>
                            <div>
                                <label style="font-size:13px;color:#475569;font-weight:600;">Şehir</label>
                                <input type="text" name="city" value="{{ old('city', 'İstanbul') }}" class="form-input" required>
                            </div>
                            <div>
                                <label style="font-size:13px;color:#475569;font-weight:600;">Posta Kodu</label>
                                <input type="text" name="zip_code" value="{{ old('zip_code') }}" class="form-input">
                            </div>
                            <input type="hidden" name="country" value="Turkey">
                        </div>

                        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;margin-top:24px;" {{ $iyzicoConfigured ? '' : 'disabled' }}>
                            iyzico ile Güvenli Ödemeye Geç →
                        </button>
                        <div style="font-size:12px;color:#94a3b8;margin-top:10px;text-align:center;">
                            Sonraki adımda kart bilgilerinizi iyzico'nun güvenli formuna girersiniz. Kart verileriniz bizim sunucumuza ulaşmaz.
                        </div>
                    </form>
                </div>

                <div class="stat-card" style="padding:24px;align-self:start;">
                    <h2 style="font-size:18px;font-weight:700;color:#0f172a;margin-bottom:16px;">Sepet Özeti</h2>
                    <div style="display:flex;flex-direction:column;gap:10px;">
                        @foreach($cartCategories as $category)
                            <div style="display:flex;justify-content:space-between;font-size:14px;color:#334155;">
                                <span>{{ $category->name }}</span>
                                <strong>{{ number_format((float) $category->monthly_price, 0, ',', '.') }} TL</strong>
                            </div>
                        @endforeach
                    </div>
                    <div style="border-top:1px solid #e2e8f0;margin-top:16px;padding-top:16px;display:flex;justify-content:space-between;align-items:center;">
                        <span style="font-size:14px;color:#475569;">Aylık toplam</span>
                        <strong style="font-size:22px;color:#0f172a;">{{ number_format((float) $cartTotal, 0, ',', '.') }} TL</strong>
                    </div>
                    <div style="font-size:12px;color:#94a3b8;margin-top:10px;">Ödeme onaylandıktan sonra kategoriler 1 ay aktive edilir.</div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    #iyzico-checkout-form .form-input {
        width:100%;
        padding:11px 14px;
        border:1px solid #e2e8f0;
        border-radius:12px;
        background:#fff;
        font-size:14px;
        outline:none;
        transition:border-color .2s, box-shadow .2s;
        margin-top:6px;
    }
    #iyzico-checkout-form .form-input:focus {
        border-color:#6366f1;
        box-shadow:0 0 0 3px rgba(99,102,241,0.12);
    }
    [data-buyer-tab].is-active {
        background:#fff;
        color:#0f172a;
        box-shadow:0 2px 6px rgba(15,23,42,.08);
    }
</style>

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
