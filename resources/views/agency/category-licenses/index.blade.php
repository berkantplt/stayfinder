@extends('layouts.app')
@section('title', 'Kategori Yetkileri — Acenta Paneli')

@section('styles')
<style>
    /* KYM sayfa stilleri: renkler layout token'larından; sarı/kırmızı/mor
       durum renkleri için token yok, yalnız burada sınıf olarak tanımlı. */
    .kym-baslik { display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap; margin-bottom:24px; }
    .kym-baslik h1 { font-size:26px; font-weight:800; letter-spacing:-0.5px; color:var(--text); }
    .kym-baslik .alt { font-size:14px; color:var(--text-meta); margin-top:4px; }
    .kym-bolum { display:flex; flex-direction:column; gap:24px; }
    .kym-bolum-baslik { display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; margin-bottom:16px; }
    .kym-bolum-baslik h2 { font-size:18px; font-weight:700; color:var(--text); }
    .kym-bolum-baslik .not { font-size:13px; color:var(--text-meta); }
    .kym-izgara { display:grid; grid-template-columns:repeat(auto-fill, minmax(270px, 1fr)); gap:14px; }
    .kym-kart { border:1px solid var(--border); border-radius:16px; padding:16px; background:var(--white); min-width:0; }
    .kym-kart .ad { font-size:15px; font-weight:700; color:var(--text); overflow-wrap:anywhere; }
    .kym-kart .ust { font-size:12px; color:var(--text-meta); margin-top:3px; }
    .kym-kart .aciklama { font-size:13px; color:var(--text-sec); margin-top:8px; line-height:1.55; }
    .kym-kart .fiyat { font-size:18px; font-weight:800; color:var(--text); white-space:nowrap; }
    .kym-kart .meta { font-size:12px; color:var(--text-meta); margin-top:8px; line-height:1.5; }
    .kym-kpi .etiket { font-size:13px; color:var(--text-meta); font-weight:600; }
    .kym-kpi-izgara { grid-template-columns:repeat(auto-fit, minmax(140px, 1fr)); } /* 375'te 2 sütun: 4 kart üst üste yığılmasın */
    .kym-kpi .deger { font-size:24px; font-weight:800; color:var(--text); margin-top:6px; letter-spacing:-0.5px; overflow-wrap:anywhere; }
    .kym-kpi .deger.tarih { font-size:20px; white-space:nowrap; overflow-wrap:normal; }
    .kym-kpi .alt { font-size:12.5px; color:var(--text-meta); margin-top:6px; line-height:1.45; }
    .kym-kpi.sari .deger { color:#b45309; }
    .kym-kpi.kirmizi .deger { color:#b91c1c; }
    .kym-rozet-sari { background:#fef3c7; color:#92400e; }
    .kym-rozet-kirmizi { background:#fee2e2; color:#991b1b; }
    .kym-rozet-mor { background:#f3e8ff; color:#6b21a8; }
    .kym-rozet-gri { background:var(--border-light); color:var(--text-meta); }
    .kym-abonelik { display:flex; flex-direction:column; gap:10px; scroll-margin-top:90px; }
    .kym-abonelik[data-yakin="soon"] { border-color:#fcd34d; }
    .kym-abonelik[data-yakin="critical"] { border-color:#fca5a5; }
    .kym-abonelik[data-iptal="1"] { border-style:dashed; }
    .kym-abonelik .satir { display:flex; align-items:flex-start; justify-content:space-between; gap:10px; }
    .kym-abonelik .bilgi { font-size:12.5px; color:var(--text-sec); line-height:1.5; }
    .kym-abonelik .bilgi strong { color:var(--text); }
    .kym-cubuk { height:6px; background:var(--border-light); border-radius:99px; overflow:hidden; margin-top:6px; }
    .kym-cubuk > span { display:block; height:100%; background:var(--accent-ink); border-radius:99px; }
    .kym-cubuk.dolu > span { background:#dc2626; }
    .kym-eylemler { display:flex; flex-wrap:wrap; gap:8px; align-items:center; margin-top:2px; }
    .kym-eylemler form { margin:0; }
    .kym-eylemler .btn-sm { padding:6px 12px; font-size:12.5px; }
    .kym-eylemler .ipucu { font-size:11.5px; color:var(--text-meta); }
    .kym-azalt { display:flex; align-items:center; gap:6px; flex-wrap:wrap; font-size:12px; color:var(--text-meta); }
    .kym-azalt input[type="number"] { width:60px; margin:0; padding:5px 8px; font-size:12.5px; border:1px solid var(--border); border-radius:8px; }
    .kym-yenileme { font-size:12px; color:var(--text-meta); display:flex; align-items:center; gap:6px; flex-wrap:wrap; }
    .kym-filtre { display:flex; flex-wrap:wrap; gap:8px; align-items:center; margin-bottom:16px; }
    .kym-arama { flex:1 1 200px; min-width:0; padding:9px 12px; border:1px solid var(--border); border-radius:10px; font-size:13px; background:var(--white); }
    .kym-hap { border:1px solid var(--border); background:var(--white); border-radius:99px; padding:6px 12px; font-size:12.5px; font-weight:600; color:var(--text-sec); cursor:pointer; font-family:inherit; }
    .kym-hap.aktif { background:var(--accent-ink); border-color:var(--accent-ink); color:#fff; }
    .kym-grup-baslik { font-size:12px; text-transform:uppercase; letter-spacing:.6px; font-weight:700; color:var(--text-meta); margin:20px 0 10px; }
    .kym-grup:first-of-type .kym-grup-baslik { margin-top:0; }
    .kym-bos { padding:20px; border:1px dashed #cbd5e1; border-radius:16px; background:var(--bg); color:var(--text-sec); font-size:13.5px; line-height:1.55; }
    .kym-sepet-kalem { display:flex; align-items:center; justify-content:space-between; gap:8px; font-size:13px; padding:8px 0; border-bottom:1px solid var(--border-light); }
    .kym-sepet-kalem .ad { min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; color:var(--text); font-weight:600; }
    .kym-sepet-kalem .fiyat { color:var(--text-meta); white-space:nowrap; font-size:12px; }
    .kym-sil { background:none; border:0; color:var(--text-muted); cursor:pointer; font-size:18px; line-height:1; padding:0 4px; font-family:inherit; }
    .kym-sil:hover { color:#b91c1c; }
    .kym-yan-kart h2 { font-size:15px; font-weight:700; color:var(--text); }
    .kym-siparis { border:1px solid var(--border); border-radius:12px; padding:10px 12px; background:var(--white); }
    .kym-siparis .no { font-weight:700; font-size:12.5px; color:var(--text); overflow-wrap:anywhere; }
    .kym-siparis .tarih { font-size:11.5px; color:var(--text-meta); margin-top:3px; }
    .kym-siparis .tutar { font-size:14px; font-weight:800; color:var(--text); white-space:nowrap; }
    .kym-eslesme-yok { display:none; }
</style>
@endsection

@section('content')
@php
    $cartEmpty = $cartItems->isEmpty() && $slotCartItems->isEmpty();
    $isLegacy = (bool) $agency->legacy_category_access;
    $fiyat = fn ($tutar) => number_format((float) $tutar, 0, ',', '.');
@endphp
<div class="container">
    <div>
        @include('partials.agency-sidebar')
        <div class="section" style="padding:24px 0 0 0;">
            <div class="kym-baslik" style="max-width:94%;margin-left:auto;margin-right:auto;">
                <div>
                    <h1>Kategori Yetkileri</h1>
                    <div class="alt">Kategori bazlı aylık yetki satın alın, sadece açık kategorilerde tur yayınlayın.</div>
                </div>
                {{-- Ana eylem duruma bağlı: sepet doluysa ödeme, hiç yetki yoksa kategori seçimi, aksi halde tur oluşturma --}}
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <a href="{{ route('agency.category-licenses.checkout-form') }}" class="btn btn-primary" data-cta-odeme style="{{ $cartEmpty ? 'display:none;' : '' }}">Ödemeye Geç</a>
                    @if(! $isLegacy && $licensedCategories->isEmpty())
                        <a href="#satin-alinabilir" class="btn btn-primary" data-cta-varsayilan style="{{ $cartEmpty ? '' : 'display:none;' }}">Kategori Seç</a>
                    @else
                        <a href="{{ route('agency.tours.create') }}" class="btn btn-outline" data-cta-varsayilan style="{{ $cartEmpty ? '' : 'display:none;' }}">Tur Oluştur</a>
                    @endif
                </div>
            </div>

            @if(session('success'))
                <div class="alert alert-success" style="max-width:94%;margin:0 auto 24px;">{{ session('success') }}</div>
            @endif

            @if($errors->any())
                <div class="alert alert-error" style="max-width:94%;margin:0 auto 24px;">
                    @foreach($errors->all() as $error) {{ $error }}<br> @endforeach
                </div>
            @endif

            <div id="cart-flash" role="status" aria-live="polite" style="max-width:94%;margin:0 auto 24px;display:none;"></div>

            {{-- KPI şeridi: acentanın karar vermesi için gereken sayılar --}}
            <div class="panel-grid-4 kym-kpi-izgara" style="margin-bottom:24px;">
                <div class="stat-card kym-kpi">
                    <div class="etiket">Aktif Yetki</div>
                    <div class="deger">{{ $summary->active_count }}</div>
                    <div class="alt">{{ $isLegacy ? 'Tüm alt kategoriler açık · geçiş erişimi' : 'Şu anda tur açabileceğiniz kategori sayısı' }}</div>
                </div>
                @if($isLegacy)
                    <div class="stat-card kym-kpi">
                        <div class="etiket">Erişim Süresi</div>
                        <div class="deger">Süresiz</div>
                        <div class="alt">Geçiş erişiminde bitiş tarihi yok</div>
                    </div>
                    <div class="stat-card kym-kpi">
                        <div class="etiket">Aylık Maliyet</div>
                        <div class="deger">Ücretsiz</div>
                        <div class="alt">Kayıtlı turlarınız için ücret alınmaz</div>
                    </div>
                    <div class="stat-card kym-kpi">
                        <div class="etiket">Tur Hakkı</div>
                        <div class="deger">Limitsiz</div>
                        <div class="alt">Kategori başına tur sınırı uygulanmaz</div>
                    </div>
                @else
                    @php $nearest = $summary->nearest; @endphp
                    <div class="stat-card kym-kpi {{ $nearest ? ($nearest->urgency === 'critical' ? 'kirmizi' : ($nearest->urgency === 'soon' ? 'sari' : '')) : '' }}">
                        <div class="etiket">En Yakın Bitiş</div>
                        <div class="deger tarih">{{ $nearest ? $nearest->expires_at?->format('d.m.Y') : '—' }}</div>
                        <div class="alt">
                            @if($nearest)
                                {{ $nearest->category->name }} · {{ $nearest->days_left }} gün kaldı
                                @if($summary->expiring_count > 1)
                                    · {{ $summary->expiring_count }} kategori bitmek üzere
                                @endif
                            @else
                                Henüz aktif abonelik yok
                            @endif
                        </div>
                    </div>
                    <div class="stat-card kym-kpi">
                        <div class="etiket">Aylık Toplam</div>
                        <div class="deger">{{ $fiyat($summary->monthly_total) }} TL</div>
                        <div class="alt">{{ $autoRenewEnabled ? 'Kategori ücretleri + ekstra haklar, her ay' : 'Aktif kategori ücretlerinin toplamı' }}</div>
                    </div>
                    <div class="stat-card kym-kpi {{ $summary->full_slot_count > 0 ? 'kirmizi' : '' }}">
                        <div class="etiket">Hakkı Dolu Kategori</div>
                        <div class="deger">{{ $summary->full_slot_count }}</div>
                        <div class="alt">{{ $summary->full_slot_count > 0 ? 'Bu kategorilere yeni tur için ekstra hak alın' : 'Tüm kategorilerde boş tur hakkı var' }}</div>
                    </div>
                @endif
            </div>

            @if($isLegacy)
                <div class="alert alert-success" style="max-width:94%;margin:0 auto 24px;">
                    Bu acenta geçiş kapsamına alındı. Kayıtlı turların etkilenmemesi için tüm aktif alt kategoriler satın alınmış gibi tanımlandı.
                </div>
            @endif

            <div class="panel-grid-yan">
                <div class="kym-bolum">
                    <div class="stat-card" id="satin-alinabilir" style="padding:24px;">
                        <div class="kym-bolum-baslik">
                            <h2>Satın Alınabilir Kategoriler</h2>
                            <div class="not">Aylık ücret kategori bazlıdır.</div>
                        </div>

                        @if($isLegacy)
                            <div class="kym-bos">
                                Geçiş erişimi nedeniyle yeni kategori satın alımı gerekmiyor. Tüm aktif alt kategoriler hesabınızda açık görünüyor.
                            </div>
                        @elseif($availableCategories->isEmpty())
                            <div class="kym-bos">
                                Satın alınabilir açık kategori kalmadı. Aktif yetkileriniz tüm kullanılabilir kategorileri kapsıyor.
                            </div>
                        @else
                            <div class="kym-bos" data-all-in-cart-note style="{{ $availableCategories->count() === $cartCategoryIds->count() ? '' : 'display:none;' }}">
                                Satın alınabilir tüm kategoriler sepetinizde. Ödemeye geçebilirsiniz.
                            </div>
                            <div class="kym-izgara" data-purchasable-grid>
                                @foreach($availableCategories as $category)
                                    <div class="kym-kart" data-category-card="{{ $category->id }}" style="{{ $cartCategoryIds->contains($category->id) ? 'display:none;' : '' }}">
                                        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;">
                                            <div style="min-width:0;">
                                                <div class="ad">{{ $category->icon }} {{ $category->name }}</div>
                                                <div class="ust">{{ $category->parent?->name ? 'Üst kategori: '.$category->parent->name : 'Ana kategori' }}</div>
                                                @if($category->description)
                                                    <div class="aciklama">{{ \Illuminate\Support\Str::limit($category->description, 110) }}</div>
                                                @endif
                                            </div>
                                            <div class="fiyat">{{ $fiyat($category->monthly_price) }} TL</div>
                                        </div>
                                        <div class="meta">
                                            Aylık yetki bedeli
                                            @if($slotSchemaReady)
                                                · {{ \App\Support\CategoryLicensing::BASE_TOUR_ALLOWANCE }} tur hakkı dahil
                                                · ekstra hak {{ $fiyat($category->extra_tour_price) }} TL{{ $autoRenewEnabled ? ' / ay' : '' }}
                                            @endif
                                        </div>
                                        <form method="POST" action="{{ route('agency.category-licenses.cart.add') }}" data-cart-form style="margin-top:14px;">
                                            @csrf
                                            <input type="hidden" name="category_id" value="{{ $category->id }}">
                                            <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;">Sepete Ekle</button>
                                        </form>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div class="stat-card" id="aktif-yetkiler" style="padding:24px;">
                        <div class="kym-bolum-baslik">
                            <h2>Aktif Kategori Yetkileri <span class="badge kym-rozet-gri" style="margin-left:6px;">{{ $licensedCategories->count() }}</span></h2>
                            @if(! $isLegacy && $licensedCategories->isNotEmpty())
                                <div class="not">Bitişi en yakın olan önce listelenir.</div>
                            @endif
                        </div>

                        @if($licensedCategories->isEmpty())
                            <div class="kym-bos">
                                Henüz aktif kategori yetkiniz yok. Yukarıdaki listeden kategori seçip sepete ekleyin; ödeme sonrası o kategoride tur yayınlayabilirsiniz.
                            </div>
                        @else
                            @if($licensedCategories->count() > 3)
                                <div class="kym-filtre" data-abonelik-filtre>
                                    <input type="search" class="kym-arama" data-abonelik-arama placeholder="Kategori ara…" aria-label="Aktif yetkilerde ara">
                                    @if(! $isLegacy)
                                        <button type="button" class="kym-hap aktif" data-filtre="tumu">Tümü</button>
                                        <button type="button" class="kym-hap" data-filtre="yakin">Bitmek üzere ({{ $summary->expiring_count }})</button>
                                        @if($slotSchemaReady)
                                            <button type="button" class="kym-hap" data-filtre="dolu">Hakkı dolu ({{ $summary->full_slot_count }})</button>
                                        @endif
                                        @if($autoRenewEnabled)
                                            <button type="button" class="kym-hap" data-filtre="iptal">İptal edildi ({{ $summary->cancelled_count }})</button>
                                        @endif
                                    @endif
                                </div>
                            @endif

                            <div class="kym-bos kym-eslesme-yok" data-eslesme-yok>Aramanızla eşleşen yetki bulunamadı.</div>

                            @foreach($licensedGroups as $group)
                                <div class="kym-grup" data-grup>
                                    <div class="kym-grup-baslik">{{ $group->parent?->icon }} {{ $group->parent?->name ?? 'Diğer' }}</div>
                                    <div class="kym-izgara">
                                        @foreach($group->items as $license)
                                            @php
                                                $sub = $license->subscription;
                                                $aramaMetni = mb_strtolower($license->category->name.' '.($group->parent?->name ?? ''), 'UTF-8');
                                            @endphp
                                            <div class="kym-kart kym-abonelik"
                                                 @if($sub) id="abonelik-{{ $sub->id }}" @endif
                                                 data-subscription-card="{{ $license->category->id }}"
                                                 data-ara="{{ $aramaMetni }}"
                                                 data-yakin="{{ $license->urgency }}"
                                                 data-dolu="{{ $license->slots_full ? 1 : 0 }}"
                                                 data-iptal="{{ $license->cancelled ? 1 : 0 }}">
                                                <div class="satir">
                                                    <div style="min-width:0;">
                                                        <div class="ad">{{ $license->category->icon }} {{ $license->category->name }}</div>
                                                    </div>
                                                    @if($license->source === 'legacy')
                                                        <span class="badge badge-green" style="white-space:nowrap;">Geçiş erişimi</span>
                                                    @elseif($license->cancelled)
                                                        <span class="badge kym-rozet-kirmizi" style="white-space:nowrap;">İptal edildi</span>
                                                    @elseif($license->urgency === 'critical')
                                                        <span class="badge kym-rozet-kirmizi" style="white-space:nowrap;">Son {{ max(0, $license->days_left) }} gün</span>
                                                    @elseif($license->urgency === 'soon')
                                                        <span class="badge kym-rozet-sari" style="white-space:nowrap;">{{ $license->days_left }} gün kaldı</span>
                                                    @else
                                                        <span class="badge badge-green" style="white-space:nowrap;">Aktif</span>
                                                    @endif
                                                </div>

                                                <div class="bilgi">
                                                    @if($license->source === 'legacy')
                                                        Süresiz · ücretsiz geçiş erişimi
                                                    @else
                                                        <strong>{{ $license->expires_at?->format('d.m.Y') }}</strong> tarihinde {{ $license->cancelled ? 'sona erecek' : 'bitiyor' }}
                                                        · {{ $license->days_left }} gün
                                                        · {{ $fiyat($license->monthly_price) }} TL / ay
                                                    @endif
                                                </div>

                                                @if($slotSchemaReady)
                                                    <div class="bilgi">
                                                        @if($license->tour_limit === null)
                                                            Tur hakkı: <strong>Limitsiz</strong> · {{ $license->used_slots }} tur kayıtlı
                                                        @else
                                                            Tur hakkı: <strong style="{{ $license->slots_full ? 'color:#b91c1c;' : '' }}">{{ $license->used_slots }}/{{ $license->tour_limit }}</strong>
                                                            @if($license->extra_slots > 0)
                                                                · {{ $license->extra_slots }} ekstra hak dahil
                                                            @endif
                                                            @if($license->slots_full)
                                                                · <span style="color:#b91c1c;font-weight:600;">Hak doldu</span>
                                                            @endif
                                                            <div class="kym-cubuk {{ $license->slots_full ? 'dolu' : '' }}" aria-hidden="true">
                                                                <span style="width:{{ min(100, (int) round($license->used_slots / max(1, $license->tour_limit) * 100)) }}%;"></span>
                                                            </div>
                                                        @endif
                                                    </div>
                                                @endif

                                                @if($license->source !== 'legacy')
                                                    <div class="kym-eylemler">
                                                        <form method="POST" action="{{ route('agency.category-licenses.cart.add') }}" data-cart-form data-renew-form style="{{ $license->in_cart_renewal ? 'display:none;' : '' }}">
                                                            @csrf
                                                            <input type="hidden" name="category_id" value="{{ $license->category->id }}">
                                                            <button type="submit" class="btn btn-primary btn-sm" title="Bitiş tarihine 1 ay eklenir, kalan günler yanmaz">Şimdi Yenile</button>
                                                        </form>
                                                        <span class="badge badge-green" data-renew-in-cart style="{{ $license->in_cart_renewal ? '' : 'display:none;' }}">Yenileme sepette ✓</span>

                                                        @if($slotSchemaReady && ! $license->cancelled)
                                                            <form method="POST" action="{{ route('agency.category-licenses.cart.add-slot') }}" data-cart-form>
                                                                @csrf
                                                                <input type="hidden" name="category_id" value="{{ $license->category->id }}">
                                                                <button type="submit" class="btn btn-outline btn-sm">+ Ekstra Hak</button>
                                                            </form>
                                                            <span class="ipucu">{{ $fiyat($license->category->extra_tour_price) }} TL / hak{{ $autoRenewEnabled ? ' / ay' : '' }}</span>
                                                        @endif

                                                        @if($autoRenewEnabled && $sub)
                                                            @if($license->cancelled)
                                                                <form method="POST" action="{{ route('agency.category-licenses.subscription.resume', $sub) }}">
                                                                    @csrf
                                                                    <button type="submit" class="btn btn-outline btn-sm">Yenilemeyi Aç</button>
                                                                </form>
                                                            @else
                                                                <form method="POST" action="{{ route('agency.category-licenses.subscription.cancel', $sub) }}" onsubmit="return confirm('Abonelik iptal edilsin mi? Dönem sonuna kadar kullanmaya devam edersiniz; dönem sonunda otomatik çekim yapılmaz ve turlarınız yayından kalkar.');">
                                                                    @csrf
                                                                    <button type="submit" class="btn btn-outline btn-sm" style="color:#b91c1c;border-color:#fecaca;">İptal Et</button>
                                                                </form>
                                                            @endif
                                                        @endif
                                                    </div>

                                                    @if($autoRenewEnabled && $sub && $license->extra_slots > 0 && ! $license->cancelled)
                                                        @if($license->next_extra_slots !== null)
                                                            <div class="kym-azalt">
                                                                <span style="color:#b45309;font-weight:600;">Yeni dönemde {{ $license->next_extra_slots }} ekstra hak kalacak.</span>
                                                                <form method="POST" action="{{ route('agency.category-licenses.subscription.slot-plan', $sub) }}" style="margin:0;">
                                                                    @csrf
                                                                    <input type="hidden" name="keep" value="{{ $license->extra_slots }}">
                                                                    <button type="submit" class="btn btn-outline btn-sm" style="padding:4px 10px;font-size:11.5px;">Azaltmayı geri al</button>
                                                                </form>
                                                            </div>
                                                        @else
                                                            <form method="POST" action="{{ route('agency.category-licenses.subscription.slot-plan', $sub) }}" class="kym-azalt">
                                                                @csrf
                                                                <label for="hak-plani-{{ $sub->id }}">Yeni dönemde kalacak ekstra hak:</label>
                                                                <input type="number" id="hak-plani-{{ $sub->id }}" name="keep" min="0" max="{{ $license->extra_slots }}" value="{{ $license->extra_slots }}">
                                                                <button type="submit" class="btn btn-outline btn-sm" style="padding:4px 10px;font-size:11.5px;">Azalt</button>
                                                            </form>
                                                        @endif
                                                    @endif

                                                    @if($autoRenewEnabled && $sub)
                                                        <div class="kym-yenileme">
                                                            @if($license->cancelled)
                                                                Dönem sonunda kapanır, kartınızdan çekim yapılmaz.
                                                            @elseif($storedCard)
                                                                <span class="badge badge-green" style="padding:2px 8px;">Otomatik yenileme</span> {{ $storedCard->displayLabel() }} ile
                                                            @else
                                                                <span class="badge kym-rozet-sari" style="padding:2px 8px;">Kart bekleniyor</span>
                                                                <a href="#yenileme-karti" style="color:var(--accent-ink);font-weight:600;">Otomatik yenileme için kart saklayın</a>
                                                            @endif
                                                        </div>
                                                    @endif
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        @endif
                    </div>
                </div>

                <div class="kym-bolum" style="gap:16px;">
                    {{-- Sepet özeti: kalem adları burada, kaldırma XHR ile; tam ekran ayrı sayfada (cart.show) --}}
                    <div class="stat-card kym-yan-kart" style="padding:16px;">
                        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:12px;">
                            <h2>Sepet</h2>
                            <span class="badge kym-rozet-gri" data-cart-count>{{ $cartItems->count() + $slotCartItems->count() }} kalem</span>
                        </div>

                        <div class="kym-bos" data-cart-empty style="padding:14px;{{ $cartEmpty ? '' : 'display:none;' }}">
                            Sepetiniz boş. Satın alınabilir kategorilerden seçim yapın.
                        </div>

                        <div data-cart-body style="{{ $cartEmpty ? 'display:none;' : '' }}">
                            <div data-cart-items>
                                @foreach($cartItems as $category)
                                    <div class="kym-sepet-kalem">
                                        <span class="ad" title="{{ $category->name }}">{{ $category->icon }} {{ $category->name }}{{ $category->cart_renewal ? ' — Yenileme' : '' }}</span>
                                        <span class="fiyat">{{ $fiyat($category->monthly_price) }} TL / ay</span>
                                        <form method="POST" action="{{ route('agency.category-licenses.cart.remove', $category) }}" data-cart-form style="margin:0;">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="kym-sil" aria-label="{{ $category->name }} sepetten kaldır">×</button>
                                        </form>
                                    </div>
                                @endforeach
                                @foreach($slotCartItems as $slotItem)
                                    <div class="kym-sepet-kalem">
                                        <span class="ad" title="{{ $slotItem->category->name }}">{{ $slotItem->category->name }} — Ekstra Tur Hakkı{{ $slotItem->quantity > 1 ? ' ×'.$slotItem->quantity : '' }}</span>
                                        <span class="fiyat">{{ $fiyat($slotItem->line_total) }} TL{{ $autoRenewEnabled ? ' / ay' : '' }}</span>
                                        <form method="POST" action="{{ route('agency.category-licenses.cart.remove-slot', $slotItem->category) }}" data-cart-form style="margin:0;">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="kym-sil" aria-label="{{ $slotItem->category->name }} ekstra hakkı sepetten kaldır">×</button>
                                        </form>
                                    </div>
                                @endforeach
                            </div>
                            <div style="display:flex;align-items:center;justify-content:space-between;font-size:13px;color:var(--text-sec);margin-top:12px;">
                                <span>İlk dönem toplamı</span>
                                <strong data-cart-total style="font-size:18px;color:var(--text);">{{ $fiyat($cartTotal) }} TL</strong>
                            </div>
                            <a href="{{ route('agency.category-licenses.cart.show') }}" class="btn btn-outline" style="width:100%;justify-content:center;margin-top:12px;">
                                Sepeti Görüntüle
                            </a>
                            <a href="{{ route('agency.category-licenses.checkout-form') }}" class="btn btn-primary" style="width:100%;justify-content:center;margin-top:8px;">
                                Ödemeye Geç
                            </a>
                        </div>
                    </div>

                    @if($autoRenewEnabled && ! $isLegacy)
                        <div class="stat-card kym-yan-kart" id="yenileme-karti" style="padding:16px;scroll-margin-top:90px;">
                            <h2 style="margin-bottom:12px;">Otomatik Yenileme Kartı</h2>
                            @if($storedCard)
                                <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;border:1px solid var(--border);border-radius:12px;padding:10px 12px;background:var(--white);">
                                    <div style="min-width:0;">
                                        <div style="font-weight:700;font-size:13px;color:var(--text);">💳 {{ $storedCard->displayLabel() }}</div>
                                        <div style="font-size:12px;color:var(--text-meta);margin-top:3px;">Abonelik yenilemelerinde bu kart kullanılır</div>
                                    </div>
                                    <form method="POST" action="{{ route('agency.category-licenses.stored-card.delete') }}" onsubmit="return confirm('Kayıtlı kart silinsin mi? Kart olmadan abonelikler otomatik yenilenemez.');" style="margin:0;">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-outline btn-sm">Kartı Sil</button>
                                    </form>
                                </div>
                            @else
                                <div class="kym-bos" style="padding:14px;border-color:#fcd34d;background:#fffbeb;color:#92400e;">
                                    <strong>Kayıtlı kartınız yok;</strong> abonelikleriniz dönem sonunda otomatik yenilenemez. Bir sonraki ödemede iyzico formundaki <strong>"Kartımı sakla"</strong> seçeneğini işaretlerseniz her ay otomatik yenilenir; dilediğinizde iptal edebilirsiniz.
                                </div>
                            @endif
                        </div>
                    @endif

                    {{-- Satın alımlar burada yalnız ÖZET (yalnız ödenmiş siparişler): tam liste ayrı ekranda --}}
                    <div class="stat-card kym-yan-kart" style="padding:16px;">
                        <h2 style="margin-bottom:12px;">Son Satın Alım</h2>

                        @if($lastOrder === null)
                            <div class="kym-bos" style="padding:14px;">
                                Henüz ödenmiş kategori satın alımı yok.
                            </div>
                        @else
                            @php $manuel = $lastOrder->payment_provider === \App\Models\AgencyCategoryOrder::PROVIDER_MANUAL; @endphp
                            <div class="kym-siparis">
                                <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:10px;">
                                    <div style="min-width:0;">
                                        <div class="no">{{ $lastOrder->order_number }}</div>
                                        <div class="tarih">{{ $lastOrder->purchased_at?->format('d.m.Y H:i') ?? '—' }}</div>
                                        <div style="margin-top:6px;">
                                            @if($manuel)
                                                <span class="badge kym-rozet-mor">Yönetici tanımladı</span>
                                            @else
                                                <span class="badge badge-green">Ödendi</span>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="tutar">{{ $manuel ? 'Ücretsiz' : $fiyat($lastOrder->subtotal).' TL' }}</div>
                                </div>
                            </div>
                            <div style="font-size:11.5px;color:var(--text-meta);margin-top:8px;">Toplam {{ $paidOrdersCount }} ödenmiş sipariş</div>
                            <a href="{{ route('agency.category-licenses.orders') }}" class="btn btn-outline" style="width:100%;justify-content:center;margin-top:10px;">
                                Satın Alımları Görüntüle
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const flashBox = document.getElementById('cart-flash');
    const cartCount = document.querySelector('[data-cart-count]');
    const cartEmpty = document.querySelector('[data-cart-empty]');
    const cartBody = document.querySelector('[data-cart-body]');
    const cartItems = document.querySelector('[data-cart-items]');
    const cartTotal = document.querySelector('[data-cart-total]');
    const allInCartNote = document.querySelector('[data-all-in-cart-note]');
    const ctaOdeme = document.querySelector('[data-cta-odeme]');
    const ctaVarsayilan = document.querySelector('[data-cta-varsayilan]');

    let flashTimer = null;

    function esc(value) {
        return String(value ?? '').replace(/[&<>"']/g, (ch) => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
        })[ch]);
    }

    function flash(message, type) {
        if (!flashBox || !message) return;
        flashBox.className = type === 'error' ? 'alert alert-error' : 'alert alert-success';
        flashBox.textContent = message;
        flashBox.style.display = '';
        clearTimeout(flashTimer);
        flashTimer = setTimeout(() => { flashBox.style.display = 'none'; }, 4000);
    }

    function renderCartItems(items) {
        if (!cartItems) return;
        cartItems.innerHTML = items.map((item) => `
            <div class="kym-sepet-kalem">
                <span class="ad" title="${esc(item.name)}">${esc(item.name)}</span>
                <span class="fiyat">${esc(item.price_label)}</span>
                <form method="POST" action="${esc(item.remove_url)}" data-cart-form style="margin:0;">
                    <input type="hidden" name="_token" value="${esc(csrfToken)}">
                    <input type="hidden" name="_method" value="DELETE">
                    <button type="submit" class="kym-sil" aria-label="${esc(item.name)} sepetten kaldır">×</button>
                </form>
            </div>`).join('');
    }

    function renderCart(data) {
        const items = Array.isArray(data.items) ? data.items : [];
        // Satın alınabilir kartları yalnız LİSANS kalemleri gizler; yenileme
        // kalemi abonelik kartını "sepette" durumuna alır.
        const inCart = new Set(items.filter((item) => item.type === 'license').map((item) => String(item.id)));
        const renewing = new Set(items.filter((item) => item.type === 'renewal').map((item) => String(item.id)));

        if (cartCount) cartCount.textContent = `${data.count} kalem`;
        if (cartTotal) cartTotal.textContent = `${data.total_label} TL`;
        renderCartItems(items);

        const empty = items.length === 0;
        if (cartBody) cartBody.style.display = empty ? 'none' : '';
        if (cartEmpty) cartEmpty.style.display = empty ? '' : 'none';
        if (ctaOdeme) ctaOdeme.style.display = empty ? 'none' : '';
        if (ctaVarsayilan) ctaVarsayilan.style.display = empty ? '' : 'none';

        let visibleCards = 0;
        document.querySelectorAll('[data-category-card]').forEach((card) => {
            const hidden = inCart.has(card.getAttribute('data-category-card'));
            card.style.display = hidden ? 'none' : '';
            if (!hidden) visibleCards += 1;
        });
        if (allInCartNote) allInCartNote.style.display = visibleCards === 0 ? '' : 'none';

        document.querySelectorAll('[data-subscription-card]').forEach((card) => {
            const inRenewal = renewing.has(card.getAttribute('data-subscription-card'));
            const form = card.querySelector('[data-renew-form]');
            const badge = card.querySelector('[data-renew-in-cart]');
            if (form) form.style.display = inRenewal ? 'none' : '';
            if (badge) badge.style.display = inRenewal ? '' : 'none';
        });
    }

    async function submitCartForm(form) {
        if (form.dataset.busy === '1') return;

        const button = form.querySelector('button[type="submit"]');
        const originalLabel = button ? button.textContent : '';
        form.dataset.busy = '1';
        if (button) {
            button.disabled = true;
            if (!button.classList.contains('kym-sil')) button.textContent = 'İşleniyor…';
        }

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: new FormData(form),
            });

            const data = await response.json().catch(() => null);

            if (!response.ok || !data || data.ok !== true) {
                flash(data?.message || 'İşlem tamamlanamadı. Lütfen sayfayı yenileyip tekrar deneyin.', 'error');
                return;
            }

            renderCart(data);
            flash(data.message, 'success');
        } catch (error) {
            flash('Bağlantı hatası. Lütfen tekrar deneyin.', 'error');
        } finally {
            form.dataset.busy = '0';
            if (button) {
                button.disabled = false;
                if (!button.classList.contains('kym-sil')) button.textContent = originalLabel;
            }
        }
    }

    // Sepet satırları JS ile yeniden basıldığı için olay dinleyici document seviyesinde.
    document.addEventListener('submit', function (event) {
        const form = event.target.closest('form[data-cart-form]');
        if (!form) return;
        event.preventDefault();
        submitCartForm(form);
    });

    // ── Aktif yetkilerde arama + filtre (istemci tarafı) ──
    const filtreKutusu = document.querySelector('[data-abonelik-filtre]');
    if (filtreKutusu) {
        const arama = filtreKutusu.querySelector('[data-abonelik-arama]');
        const haplar = filtreKutusu.querySelectorAll('[data-filtre]');
        const eslesmeYok = document.querySelector('[data-eslesme-yok]');
        let aktifFiltre = 'tumu';

        function uygula() {
            const q = (arama?.value || '').trim().toLocaleLowerCase('tr');
            let toplamGorunen = 0;
            document.querySelectorAll('[data-grup]').forEach((grup) => {
                let gorunen = 0;
                grup.querySelectorAll('[data-subscription-card]').forEach((card) => {
                    const metinUyar = q === '' || (card.getAttribute('data-ara') || '').includes(q);
                    let filtreUyar = true;
                    if (aktifFiltre === 'yakin') filtreUyar = card.getAttribute('data-yakin') !== 'ok';
                    if (aktifFiltre === 'dolu') filtreUyar = card.getAttribute('data-dolu') === '1';
                    if (aktifFiltre === 'iptal') filtreUyar = card.getAttribute('data-iptal') === '1';
                    const goster = metinUyar && filtreUyar;
                    card.style.display = goster ? '' : 'none';
                    if (goster) gorunen += 1;
                });
                grup.style.display = gorunen > 0 ? '' : 'none';
                toplamGorunen += gorunen;
            });
            if (eslesmeYok) eslesmeYok.style.display = toplamGorunen === 0 ? '' : 'none';
        }

        arama?.addEventListener('input', uygula);
        haplar.forEach((hap) => hap.addEventListener('click', () => {
            aktifFiltre = hap.getAttribute('data-filtre') || 'tumu';
            haplar.forEach((h) => h.classList.toggle('aktif', h === hap));
            uygula();
        }));
    }
})();
</script>
@endpush
