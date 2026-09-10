@extends('layouts.app')
@section('title', $tour->title . ' — turXtur')
@section('description', \Illuminate\Support\Str::limit(preg_replace('/\s+/u', ' ', $tour->description_text), 150))
@if($tour->image)
    @section('og_image', url($tour->image))
@endif

@push('head')
{{-- TouristTrip + Product + FAQPage tek @graph içinde (bkz. App\Support\TourSchema) --}}
@include('partials.json-ld', ['data' => \App\Support\TourSchema::graph($tour)])
<style>
    /* Sekme barı: çerçeveli kutular (mobilde 2 sütun, tek kalan buton tam genişlik) */
    .tour-tabs { display:grid; grid-template-columns:repeat(2,1fr); gap:10px; margin:8px 0 20px; }
    .tour-tab { background:var(--white); border:1.5px solid var(--border); border-radius:10px; cursor:pointer; font-family:var(--font); font-size:13px; font-weight:800; letter-spacing:0.4px; text-transform:uppercase; padding:14px 8px; color:var(--text); text-align:center; line-height:1.25; transition:color .2s, border-color .2s, box-shadow .2s; }
    .tour-tab:last-child:nth-child(odd) { grid-column:1 / -1; }
    .tour-tab:hover { border-color:var(--accent); color:var(--accent); }
    .tour-tab.active { color:var(--accent); border-color:var(--accent); box-shadow:inset 0 0 0 1px var(--accent); }
    .tour-tab-count { display:inline-block; background:var(--accent); color:#fff; font-size:11px; font-weight:700; padding:1px 7px; border-radius:999px; margin-left:4px; vertical-align:1px; }
    @media(min-width:769px) {
        .tour-tabs { display:flex; flex-wrap:wrap; }
        .tour-tab { flex:1 1 auto; padding:13px 18px; font-size:13.5px; white-space:nowrap; }
    }
    .tour-tab-panel[hidden] { display:none; }

    .m-cta { display:none; }

    /* ── Galeri: kaydırmalı şerit + sayaç + tam ekran ── */
    .m-gallery { position:relative; }
    .g-strip { display:flex; height:340px; overflow-x:auto; overflow-y:hidden; scroll-snap-type:x mandatory; scrollbar-width:none; border-radius:var(--radius-lg); -webkit-overflow-scrolling:touch; }
    .g-strip::-webkit-scrollbar { display:none; }
    .g-slide { flex:0 0 100%; width:100%; height:100%; object-fit:cover; scroll-snap-align:start; scroll-snap-stop:always; cursor:zoom-in; display:block; }
    .g-count { position:absolute; left:12px; top:300px; background:rgba(15,23,42,.72); color:#fff; font-size:12px; font-weight:700; padding:4px 10px; border-radius:100px; pointer-events:none; font-variant-numeric:tabular-nums; }
    .g-thumb.on { border-color:var(--accent) !important; }
    .g-lb { border:0; padding:0; width:100vw; max-width:100vw; height:100vh; max-height:100vh; background:#0b1220; color:#fff; }
    .g-lb::backdrop { background:rgba(0,0,0,.92); }
    .g-lb-strip { display:flex; width:100%; height:100%; overflow-x:auto; scroll-snap-type:x mandatory; scrollbar-width:none; }
    .g-lb-strip::-webkit-scrollbar { display:none; }
    .g-lb-slide { flex:0 0 100%; width:100%; height:100%; display:flex; align-items:center; justify-content:center; scroll-snap-align:start; scroll-snap-stop:always; overflow:auto; touch-action:pinch-zoom; }
    .g-lb-slide img { max-width:100%; max-height:100%; object-fit:contain; cursor:zoom-in; }
    .g-lb-slide img.buyuk { max-width:none; max-height:none; width:180vw; height:auto; cursor:zoom-out; }
    .g-lb-kapat, .g-lb-nav { position:absolute; z-index:2; border:0; background:rgba(255,255,255,.16); color:#fff; width:44px; height:44px; border-radius:50%; cursor:pointer; display:flex; align-items:center; justify-content:center; }
    .g-lb-kapat:hover, .g-lb-nav:hover { background:rgba(255,255,255,.28); }
    .g-lb-kapat { top:calc(12px + env(safe-area-inset-top)); right:12px; }
    .g-lb-nav { top:50%; transform:translateY(-50%); }
    .g-lb-prev { left:12px; }
    .g-lb-next { right:12px; }
    .g-lb-count { position:absolute; top:calc(24px + env(safe-area-inset-top)); left:16px; font-size:13px; font-weight:700; z-index:2; font-variant-numeric:tabular-nums; }

    /* ── Bilgi şeridi (karar bilgileri) ── */
    .p-strip { display:grid; grid-template-columns:repeat(var(--p-strip-n, 5), minmax(0, 1fr)); gap:0; background:var(--white); border:1px solid var(--border); border-radius:var(--radius); padding:14px 6px; margin:0 0 16px; }
    .p-cell { display:grid; grid-template-columns:20px minmax(0, 1fr); grid-template-rows:auto auto; column-gap:10px; align-items:center; min-width:0; padding:0 12px; border-left:1px solid var(--border-light); }
    .p-cell-ikon { grid-row:1 / span 2; }
    .p-cell:first-child { border-left:0; }
    .p-cell-ikon { display:flex; flex:none; width:20px; height:20px; color:var(--accent-ink); }
    .p-cell-ikon svg { width:20px; height:20px; }
    .p-cell-etiket { display:block; font-size:11px; font-weight:600; letter-spacing:.2px; text-transform:uppercase; color:var(--text-meta); }
    .p-cell-deger { display:block; font-size:13.5px; font-weight:700; color:var(--text); line-height:1.2; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .p-cell-ikon + .p-cell-etiket { flex:none; }
    .p-cell > .p-cell-etiket, .p-cell > .p-cell-deger { min-width:0; }
    @media(max-width:768px) {
        /* Mobil: en fazla 4 hücre, dikey ikon/etiket/değer; tempo masaüstüne özel */
        .p-strip { grid-template-columns:repeat(4, minmax(0, 1fr)); padding:12px 0; border-radius:16px; }
        .p-cell { display:flex; flex-direction:column; gap:4px; text-align:center; padding:0 2px; border-left:0; }
        .p-cell-masaustu { display:none; }
        .p-cell-etiket { font-size:10px; }
        .p-cell-deger { font-size:12px; white-space:nowrap; max-width:100%; }
    }

    /* ── Paylaş / favori düğmeleri ── */
    .detail-main { position:relative; min-width:0; }
    .p-actions { display:flex; justify-content:flex-end; gap:8px; margin:0 0 12px; }
    .p-act { display:inline-flex; align-items:center; gap:6px; height:38px; padding:0 14px; border:1.5px solid var(--border); border-radius:10px; background:var(--white); color:var(--text); font-family:var(--font); font-size:13px; font-weight:600; cursor:pointer; transition:border-color .2s, color .2s; }
    .p-act:hover { border-color:var(--accent); color:var(--accent-ink); }
    .p-act.on { color:#e0563a; border-color:#fecaca; background:#fef2f2; }
    .p-act.on svg { fill:currentColor; }
    @media(max-width:768px) {
        .detail-main:not(.no-gallery) .p-actions { position:absolute; right:-4px; top:198px; z-index:3; margin:0; }
        .detail-main:not(.no-gallery) .p-act { width:40px; height:40px; padding:0; border:none; border-radius:50%; justify-content:center; background:rgba(255,255,255,.94); box-shadow:0 2px 8px rgba(4,24,21,.18); }
        .detail-main:not(.no-gallery) .p-act span { display:none; }
    }
    /* Kısa bilgi baloncuğu (paylaş/favori geri bildirimi) */
    #p-toast { position:fixed; left:16px; right:16px; bottom:calc(var(--tabbar-h) + var(--cta-h) + 12px); z-index:var(--z-banner); max-width:440px; margin:0 auto; background:#0f172a; color:#fff; border-radius:12px; padding:12px 14px; font-size:13px; line-height:1.45; box-shadow:0 10px 25px -3px rgba(0,0,0,.25); opacity:0; transform:translateY(8px); transition:opacity .2s, transform .2s; pointer-events:none; }
    #p-toast.acik { opacity:1; transform:none; }
    @media(prefers-reduced-motion:reduce) { #p-toast { transition:none; } }

    /* Mobil kompakt fiyat kartı: tek satır fiyat + acenta, ince buton üçlüsü */
    .p-tel-short { display:none; }
    @media(max-width:768px) {
        #priceCard { display:flex; flex-wrap:wrap; align-items:baseline; column-gap:8px; row-gap:2px; padding:10px 12px !important; border-width:1px !important; border-radius:14px !important; }
        #priceCard .p-badge { margin:0 !important; }
        #priceCard .p-badge .badge { font-size:10px; padding:3px 8px; }
        #priceCard .p-price { margin:0 !important; }
        #priceCard .p-price .price-tag { font-size:22px !important; }
        #priceCard .p-agency { margin:0 0 0 auto !important; }
        #priceCard .p-agency a { font-size:13px !important; }
        #priceCard .p-ctas { flex-basis:100%; flex-direction:row !important; gap:6px !important; margin-top:8px; }
        #priceCard .p-ctas .btn { width:auto !important; padding:9px 8px; font-size:13px; }
        #priceCard .p-rez { flex-basis:100%; font-size:11.5px !important; margin-top:8px !important; }
        #priceCard .p-agency { flex-basis:100%; margin:4px 0 0 !important; }
        #priceCard .p-go { flex:1.6; }
        #priceCard .p-call { flex:1; }
        #priceCard .p-mail { flex:0.7; min-width:96px; padding:9px 0 !important; font-size:18px !important; }
        #priceCard .p-tel-full, #priceCard .p-mail-text { display:none; }
        #priceCard .p-tel-short { display:inline; }
        #priceCard #campaign-countdown { flex-basis:100%; margin:6px 0 0 !important; padding:6px 10px !important; }
        #priceCard #campaign-countdown div:last-child { font-size:14px !important; }
        #mPriceSlot:not(:empty) { margin-bottom:4px; }

        /* ===== Yapışkan CTA şeridi (yalnız mobil, yalnız fiyat kartı ekrandan çıkınca) ===== */
        body.cta-acik .m-cta { display:flex; }
        .m-cta { position:fixed; left:0; right:0; bottom:var(--tabbar-h); z-index:var(--z-tepsi); align-items:center; gap:10px; padding:10px 16px; background:var(--white); border-top:1px solid var(--border); box-shadow:0 -6px 18px rgba(15,23,42,.08); }
        .m-cta-fiyat { flex:1; min-width:0; display:flex; flex-direction:column; gap:1px; }
        .m-cta-satir { display:flex; align-items:baseline; gap:5px; }
        .m-cta-tutar { font-family:'Space Grotesk',var(--font); font-size:20px; font-weight:700; color:var(--accent); letter-spacing:-.5px; white-space:nowrap; }
        .m-cta-kisi { font-size:11px; color:var(--text-meta); }
        .m-cta-acenta { display:flex; align-items:center; gap:4px; font-size:11px; font-weight:600; color:var(--accent-ink); white-space:nowrap; overflow:hidden; }
        .m-cta-git { height:44px; padding:0 16px !important; font-size:14px !important; flex:none; }
        .m-cta-tel { width:44px; height:44px; padding:0 !important; border-radius:12px; flex:none; }
        @media (prefers-reduced-motion:no-preference) {
            .m-cta { animation:m-cta-in .2s ease; }
            @keyframes m-cta-in { from { transform:translateY(100%); } to { transform:none; } }
        }

        /* ===== Mobil tasarım dili (turXtur Mobil 3) ===== */
        /* Galeri kenardan kenara, altı yuvarlatılmış — uygulama kalıbı */
        .m-gallery { margin:0 -16px 18px !important; }
        .g-strip { height:250px !important; border-radius:0 0 20px 20px !important; }
        .g-count { top:212px; }
        .g-lb-nav { display:none; }
        .m-gallery-thumbs { padding:0 16px 4px !important; }
        .container > .section:first-child { padding-top:12px !important; }
        /* Başlıklar tasarım tipografisi */
        .detail-grid h1, .detail-grid h2, .detail-grid h3, .detail-sidebar h3 { font-family:'Manrope',var(--font); letter-spacing:-.5px; }
        .detail-grid h1 { font-size:21px !important; line-height:1.28 !important; }
        /* Fiyat vurgusu: kart dilindeki teal */
        #priceCard .p-price .price-tag { color:var(--accent) !important; font-family:'Space Grotesk',var(--font); }
        /* Bölüm kartları: yumuşak köşe + ince kenar */
        .detail-grid .card, .detail-grid > div > div[style*="border-radius"] { border-radius:16px !important; }
    }

    /* Mobil: tarih/paket fiyat tablosu dikey karta dönüşür — yatay kaydırma kalkar.
       Tablo hücrelerinin inline style'ları var, bu yüzden ezmeler !important. */
    @media(max-width:640px) {
        .pricing-scroll { overflow-x:visible !important; padding:12px; }
        .pricing-table { display:block !important; min-width:0 !important; }
        .pricing-table thead { display:none; }
        .pricing-table tbody { display:block; }
        .pricing-table tr { display:block; border:1px solid var(--border) !important; border-radius:10px; overflow:hidden; margin-bottom:10px; }
        .pricing-table tr:last-child { margin-bottom:0; }
        .pricing-table td { display:block; text-align:left !important; white-space:normal !important; }
        .pricing-table td.pkg-name { background:var(--accent-bg); padding:10px 12px !important; }
        .pricing-table td.pkg-price { display:flex; align-items:baseline; justify-content:space-between; gap:12px; padding:9px 12px !important; border-top:1px solid var(--border); }
        .pricing-table td.pkg-price::before { content:attr(data-label); color:var(--text-muted); font-size:12.5px; font-weight:600; }
        .pricing-table td.pkg-price .pkg-price-val { white-space:nowrap; text-align:right; }
    }

    /* ── Dahil olan/olmayan kutuları ──
       Kapalı: gövde ~3 satırda kırpılır, alt kısım ::after degradesiyle beyaza
       kaybolur. .acik sınıfı gelince max-height büyür, degrade söner, ok döner.
       .kisa: liste önizlemeye zaten sığıyor — soldurma ve ok gereksiz. */
    .inc-box { background:var(--white); border:1px solid var(--border); border-radius:var(--radius); padding:20px; margin-bottom:16px; }
    .inc-head { width:100%; display:flex; align-items:center; justify-content:space-between; gap:8px; background:none; border:0; padding:0; margin:0; cursor:pointer; font-family:var(--font); text-align:left; color:inherit; }
    .inc-head h3 { font-size:15px; font-weight:700; margin:0; }
    .inc-body { position:relative; overflow:hidden; max-height:104px; transition:max-height .35s ease; }
    .inc-body::after { content:''; position:absolute; left:0; right:0; bottom:0; height:60px; background:linear-gradient(rgba(255,255,255,0), var(--white)); pointer-events:none; transition:opacity .3s; }
    /* Yuvarlak aç/kapa butonu: gövdenin altında ortada, ok açıkken yukarı döner */
    .inc-more { display:flex; align-items:center; justify-content:center; width:34px; height:34px; margin:6px auto 0; border-radius:50%; border:1px solid var(--border); background:var(--white); color:var(--text-muted); cursor:pointer; box-shadow:0 1px 4px rgba(15,23,42,.06); transition:color .2s, border-color .2s; }
    .inc-more:hover { color:var(--accent); border-color:var(--accent); }
    .inc-caret { font-size:16px; line-height:1; transition:transform .25s; }
    {{-- Tavan, en uzun içerik (çok günlük tur programı) taşmayacak kadar yüksek --}}
    .inc-box.acik .inc-body { max-height:6000px; }
    .inc-box.acik .inc-body::after { opacity:0; }
    .inc-box.acik .inc-caret { transform:rotate(180deg); }
    .inc-box.kisa .inc-body::after { display:none; }
    .inc-box.kisa .inc-more { display:none; }
    .inc-box.kisa .inc-head { cursor:default; }

    /* ── Tur programı: gün gün akordeon ──
       Her günün başlığı her zaman görünür; içeriğin ilk ~2-3 satırı görünüp
       ::after degradesiyle beyaza kaybolur, satıra tıklanınca tamamı açılır.
       .kisa: içerik önizlemeye zaten sığıyor (ölçüm scripts'te, inc-box ile ortak). */
    .prog-day { border-bottom:1px dashed var(--border); }
    .prog-day:last-child { border-bottom:0; }
    .prog-day-head { width:100%; display:flex; align-items:center; justify-content:space-between; gap:8px; background:none; border:0; padding:11px 0; margin:0; cursor:pointer; font-family:var(--font); text-align:left; font-weight:700; color:#0f172a; font-size:14px; }
    .prog-day-body { position:relative; overflow:hidden; max-height:66px; transition:max-height .35s ease; margin-bottom:12px; color:var(--text-sec); line-height:1.8; font-size:14px; white-space:pre-line; }
    .prog-day-body::after { content:''; position:absolute; left:0; right:0; bottom:0; height:40px; background:linear-gradient(rgba(255,255,255,0), var(--white)); pointer-events:none; transition:opacity .3s; }
    .prog-day.acik .prog-day-body { max-height:6000px; }
    .prog-day.acik .prog-day-body::after { opacity:0; }
    .prog-day.acik .inc-caret { transform:rotate(180deg); }
    .prog-day.kisa .prog-day-body::after { display:none; }
    .prog-day.kisa .inc-caret { display:none; }
    .prog-day.kisa .prog-day-head { cursor:default; }
    .prog-day-plain { padding:11px 0; font-weight:700; color:#0f172a; font-size:14px; }
</style>
@endpush

@section('content')
<div class="container">
    <div class="section">
        @include('partials.breadcrumb', ['items' => array_filter([
            ['name' => 'Turlar', 'url' => route('tours.index')],
            $tour->category
                ? ['name' => $tour->category->name, 'url' => \App\Support\LandingSlug::urlForCategory($tour->category)]
                : null,
            ['name' => $tour->title],
        ])])

        <div class="detail-grid">
            {{-- Left: Tour Info --}}
            @php
                $gallery = is_array($tour->images) && count($tour->images) ? $tour->images : ($tour->image ? [$tour->image] : []);
                // Paylaşılan adrese ref etiketi: kanonik adres bu parametreyi zaten
                // temizliyor (App\Support\Seo::TRACKING_PARAMS), SEO'ya dokunmaz.
                $shareUrl = route('tours.show', $tour).'?ref=paylas';
            @endphp
            <div class="detail-main {{ count($gallery) ? '' : 'no-gallery' }}">
                @if(count($gallery))
                    <div class="m-gallery" style="margin-bottom:20px;">
                        {{-- Kaydırmalı şerit (scroll-snap): mobilde parmakla, masaüstünde küçük
                             resimle gezilir; sayaç "3 / 12"; tıklayınca tam ekran. Yalnız ilk
                             görsel view-transition-name taşır (aynı ad iki kez → geçiş iptal). --}}
                        <div class="g-strip" id="gStrip" role="region" aria-label="Tur fotoğrafları">
                            @foreach($gallery as $i => $img)
                                <img src="{{ $img }}" alt="{{ $tour->title }}{{ count($gallery) > 1 ? ' — fotoğraf '.($i + 1) : '' }}" class="g-slide" data-index="{{ $i }}"
                                    @if($i === 0) id="galleryMain" style="view-transition-name: tour-{{ $tour->id }};" @else loading="lazy" @endif>
                            @endforeach
                        </div>
                        @if(count($gallery) > 1)
                            <div class="g-count" id="gCount" aria-live="polite">1 / {{ count($gallery) }}</div>
                            <div class="m-gallery-thumbs" style="display:flex;gap:8px;overflow-x:auto;margin-top:10px;padding-bottom:4px;">
                                @foreach($gallery as $i => $img)
                                    <img src="{{ $img }}" alt="" loading="lazy" class="g-thumb {{ $i === 0 ? 'on' : '' }}" data-index="{{ $i }}"
                                        style="width:90px;height:64px;object-fit:cover;border-radius:8px;cursor:pointer;flex:0 0 auto;border:2px solid transparent;">
                                @endforeach
                            </div>
                        @endif
                    </div>
                    {{-- Tam ekran görüntüleyici: <dialog>, kaydırmalı, ok tuşları, dokununca büyüt.
                         Görseller acenta sitelerinden dış bağlantı; yükleme kırık görsel yedeğine düşer. --}}
                    <dialog id="gLightbox" class="g-lb" aria-label="Fotoğraf galerisi">
                        <button type="button" class="g-lb-kapat" id="gLbKapat" aria-label="Kapat"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg></button>
                        <div class="g-lb-count" id="gLbCount"></div>
                        <div class="g-lb-strip" id="gLbStrip">
                            @foreach($gallery as $i => $img)
                                <div class="g-lb-slide"><img src="{{ $img }}" alt="{{ $tour->title }}" loading="lazy" data-index="{{ $i }}"></div>
                            @endforeach
                        </div>
                        @if(count($gallery) > 1)
                            <button type="button" class="g-lb-nav g-lb-prev" id="gLbPrev" aria-label="Önceki fotoğraf"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg></button>
                            <button type="button" class="g-lb-nav g-lb-next" id="gLbNext" aria-label="Sonraki fotoğraf"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg></button>
                        @endif
                    </dialog>
                @endif

                {{-- Paylaş / favori satırı: masaüstünde başlığın üstünde sağa yaslı,
                     mobilde galeri fotoğrafının üstünde yuvarlak düğmeler (CSS). --}}
                <div class="p-actions" id="pActions">
                    <button type="button" class="p-act" id="pShare" data-url="{{ $shareUrl }}" data-title="{{ $tour->title }}" aria-label="Paylaş"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="M8.6 13.5l6.8 4M15.4 6.5l-6.8 4"/></svg><span>Paylaş</span></button>
                    @php $isFav = auth()->check() && auth()->user()->hasFavorited($tour); @endphp
                    {{-- Ziyaretçi de görür: tıklayınca girişe gider (data-login), üye için AJAX --}}
                    <button type="button" class="p-act p-fav {{ $isFav ? 'on' : '' }}" id="pFav" data-tour="{{ $tour->id }}"
                        data-login="{{ auth()->check() ? '' : route('login', ['next' => request()->getRequestUri(), 'favori' => $tour->id]) }}" aria-pressed="{{ $isFav ? 'true' : 'false' }}" aria-label="Favorilere ekle"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.8 5.6a5.2 5.2 0 0 0-7.4 0L12 7l-1.4-1.4a5.2 5.2 0 1 0-7.4 7.4L12 21.5l8.8-8.5a5.2 5.2 0 0 0 0-7.4z"/></svg><span class="p-fav-text">{{ $isFav ? 'Favoride' : 'Favorilere ekle' }}</span></button>
                </div>

                {{-- AI danışman barı: aramadan gelen kullanıcı bağlamını kaybetmesin --}}
                @if(!empty($aiContext))
                    <div style="display:flex;flex-wrap:wrap;align-items:center;gap:8px;background:#eef2ff;border:1px solid #c7d2fe;border-radius:12px;padding:10px 14px;margin-bottom:14px;font-size:13px;color:#3730a3;">
                        <span>🤖 Aradığın: “{{ \Illuminate\Support\Str::limit($aiContext['query'], 60) }}”</span>
                        @if($aiContext['compatibility'] !== null)
                            <span style="font-weight:700;">· %{{ round($aiContext['compatibility'] * 100) }} uyumlu</span>
                        @endif
                        @foreach($aiContext['checks'] as $check)
                            <span>· {{ $check }}</span>
                        @endforeach
                    </div>
                @endif

                <h1 style="font-size:24px;font-weight:800;letter-spacing:-0.5px;margin-bottom:10px;line-height:1.3;">{{ $tour->title }}</h1>

                <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:16px;">
                    @if($tour->category)
                        <a href="{{ \App\Support\LandingSlug::urlForCategory($tour->category) }}" class="badge badge-accent" style="text-decoration:none;">{{ $tour->category->icon }} {{ $tour->category->name }}</a>
                    @endif
                    <span class="badge badge-accent">📍 {{ $tour->destination }}</span>
                    <span class="badge badge-accent">⏱ {{ $tour->duration_label }}</span>
                </div>

                @php
                    // Kalkış + duraklar tek liste: yolcu hepsinden binebilir → hepsi "kalkış şehri"
                    $boardingCities = collect([$tour->departure_city])
                        ->merge(is_array($tour->stop_cities) ? $tour->stop_cities : [])
                        ->filter()
                        ->unique()
                        ->values();
                @endphp
                @if($boardingCities->count())
                    <div style="margin-bottom:16px;font-size:14px;color:var(--text-sec);">
                        <strong>🚌 Kalkış Şehirleri:</strong>
                        {{-- Bilgi amaçlı: tıklanabilir değil. Şehre tıklayınca o
                             şehrin tüm turlarına gitmek kullanıcıyı incelediği
                             turdan koparıyordu. --}}
                        {{ $boardingCities->implode(', ') }}
                    </div>
                @endif

                {{-- Bilgi şeridi: karar bilgileri tek satırda (ulaşım, kalkış, vize, süre;
                     masaüstünde tempo). Boş veri hücre basmaz; vize null ise (acenta hiç
                     işaretlememiş) "Vizesiz" denmez, hücre çıkmaz. --}}
                @php
                    $seritHucreleri = array_values(array_filter([
                        ['ikon' => $tour->transport_type === 'ucak' ? 'plane' : 'bus', 'etiket' => 'Ulaşım', 'deger' => $tour->transport_short_label],
                        ['ikon' => 'pin', 'etiket' => 'Kalkış', 'deger' => $boardingCities->count()
                            ? $boardingCities->first().($boardingCities->count() > 1 ? ' +'.($boardingCities->count() - 1) : '')
                            : null],
                        ['ikon' => 'visa', 'etiket' => 'Vize', 'deger' => $tour->visa_label],
                        ['ikon' => 'clock', 'etiket' => 'Süre', 'deger' => $tour->duration_label ?: null],
                        ['ikon' => 'gauge', 'etiket' => 'Tempo', 'deger' => $tour->tempo_label, 'masaustu' => true],
                    ], fn ($h) => $h['deger'] !== null && $h['deger'] !== ''));
                    $seritIkonlar = [
                        'bus' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="3" width="16" height="15" rx="3"/><path d="M4 10h16M8 18v2M16 18v2"/><circle cx="8" cy="14" r="1"/><circle cx="16" cy="14" r="1"/></svg>', 'plane' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 14l8-2 4-8 2 1-2 8 6 3-1 2-7-1-3 4-2-1 1-5z"/></svg>', 'pin' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>', 'visa' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="5" y="3" width="14" height="18" rx="2"/><circle cx="12" cy="10" r="2.5"/><path d="M8.5 16.5h7"/></svg>', 'clock' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>', 'gauge' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 15a8 8 0 0 1 16 0"/><path d="M12 15l3-4"/><circle cx="12" cy="15" r="1"/></svg>',
                    ];
                @endphp
                @if(count($seritHucreleri))
                    <div class="p-strip" style="--p-strip-n:{{ count($seritHucreleri) }};">
                        @foreach($seritHucreleri as $h)
                            <div class="p-cell {{ !empty($h['masaustu']) ? 'p-cell-masaustu' : '' }}">
                                <span class="p-cell-ikon">{!! $seritIkonlar[$h['ikon']] !!}</span>
                                <span class="p-cell-etiket">{{ $h['etiket'] }}</span>
                                <span class="p-cell-deger">{{ $h['deger'] }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif

                {{-- Mobilde kompakt fiyat kartı buraya taşınır (JS ile — tek DOM, iki yuva) --}}
                <div id="mPriceSlot"></div>

                {{-- #yorumlar derin linki için görünür çapa: tarayıcı buraya kaydırır,
                     sekme JS'i paneli açar (gizli panele id konursa scroll çalışmaz) --}}
                <div id="yorumlar" style="scroll-margin-top:90px;"></div>

                @if(session('success'))
                    <div class="alert alert-success">{{ session('success') }}</div>
                @endif

                @php
                    $upcomingDates = $tour->dates
                        ->filter(fn($date) => $date->departure_date && $date->departure_date->greaterThanOrEqualTo(now()->startOfDay()))
                        ->sortBy('departure_date')
                        ->values();

                    // Kampanya aktifken tarih kartlarında da indirimli fiyat göster
                    $activeCampaignForDates = $tour->activeCampaign;
                    $datePriceRenderer = function ($date) use ($tour, $activeCampaignForDates) {
                        $original = (float) ($date->price ?? $tour->price);
                        $currency = $tour->currency_symbol;

                        if (!$activeCampaignForDates) {
                            return '<span style="display:inline-flex;align-items:center;padding:2px 8px;border-radius:999px;background:#dcfce7;color:#166534;font-size:11px;font-weight:700;margin-left:6px;">'
                                . number_format($original, 0, ',', '.') . ' ' . e($currency)
                                . '</span>';
                        }

                        // Pro-rata: tur.price'tan campaign indirimi oranı, date.price'a uygulanır
                        $tourBase = max((float) $tour->price, 0.01);
                        $discountRate = max(0.0, min(1.0, ($tourBase - (float) $activeCampaignForDates->discount_price) / $tourBase));
                        $discounted = round($original * (1 - $discountRate), 2);
                        $pctOff = (int) round($discountRate * 100);

                        return '<span style="margin-left:6px;display:inline-flex;align-items:center;gap:6px;">'
                            . '<s style="color:#94a3b8;font-size:11px;">' . number_format($original, 0, ',', '.') . ' ' . e($currency) . '</s>'
                            . '<span style="padding:2px 8px;border-radius:999px;background:#dcfce7;color:#166534;font-size:11px;font-weight:700;">'
                            . number_format($discounted, 0, ',', '.') . ' ' . e($currency)
                            . '</span>'
                            . ($pctOff > 0 ? '<span style="font-size:10px;color:#16a34a;font-weight:700;">-%' . $pctOff . '</span>' : '')
                            . '</span>';
                    };

                    // Geçmiş tarihler artık soldurulmuyor, hiç basılmıyor: paket fiyat
                    // bloklarından kalkışı geçmiş tarihler ayıklanır; tarihi (ya da paketi)
                    // kalmayan blok gizlenir, hepsi geçmişse bölüm hiç açılmaz.
                    $pricingBlocks = collect(is_array($tour->pricing_blocks) ? $tour->pricing_blocks : [])
                        ->map(function ($block) {
                            $block['dates'] = collect($block['dates'] ?? [])
                                ->map(fn ($d) => \Illuminate\Support\Carbon::parse($d))
                                ->filter(fn ($d) => $d->greaterThanOrEqualTo(now()->startOfDay()))
                                ->sort()
                                ->values();

                            return $block;
                        })
                        ->filter(fn ($block) => $block['dates']->count()
                            && count(array_filter((array) ($block['packages'] ?? []), 'is_array')))
                        ->values();
                    $hasPricingBlocks = $pricingBlocks->count() > 0;

                    $detailSections = [
                        ['🚌', 'Kalkış / Biniş Noktaları', $tour->departure_points],
                        ['🏨', 'Konaklama', $tour->hotel_info],
                        ['➕', 'Ekstra Tur ve Aktiviteler', $tour->extras],
                        ['👤', 'Rehber', $tour->guide_info],
                        ['↩️', 'İptal / İade Koşulları', $tour->cancellation_policy],
                    ];

                    // Sekmeler: içeriği boş olan sekme hiç gösterilmez
                    $hasProgram = is_array($tour->itinerary) && count($tour->itinerary);
                    $hasPrices  = $hasPricingBlocks || $upcomingDates->count()
                        || ($tour->departure_date && $tour->departure_date->greaterThanOrEqualTo(now()->startOfDay()))
                        || $priceData->count() >= 2;
                    $hasGeneral = $tour->description_text !== ''
                        || collect($detailSections)->contains(fn ($s) => trim((string) $s[2]) !== '')
                        || trim((string) $tour->frequency) !== '';

                    $tourTabList = array_values(array_filter([
                        $hasProgram ? 'program' : null,
                        $hasPrices ? 'tarihler' : null,
                        $hasGeneral ? 'genel' : null,
                        'yorumlar',
                    ]));
                    $defaultTab = $tourTabList[0];
                @endphp

                {{-- Sekme barı --}}
                <div class="tour-tabs" id="tourTabs" role="tablist">
                    @if($hasProgram)
                        <button type="button" class="tour-tab{{ $defaultTab === 'program' ? ' active' : '' }}" data-tab="program">Tur Programı</button>
                    @endif
                    @if($hasPrices)
                        <button type="button" class="tour-tab{{ $defaultTab === 'tarihler' ? ' active' : '' }}" data-tab="tarihler">Tarihler ve Fiyatlar</button>
                    @endif
                    @if($hasGeneral)
                        <button type="button" class="tour-tab{{ $defaultTab === 'genel' ? ' active' : '' }}" data-tab="genel">Genel Bilgiler</button>
                    @endif
                    <button type="button" class="tour-tab{{ $defaultTab === 'yorumlar' ? ' active' : '' }}" data-tab="yorumlar">Yorumlar
                        @if($reviews->count())<span class="tour-tab-count">{{ $reviews->count() }}</span>@endif
                    </button>
                </div>

                {{-- Sekme: Tur Programı --}}
                @if($hasProgram)
                {{-- Gün gün akordeon: TÜM gün başlıkları listede görünür; her günün
                     içeriğinin ilk ~2-3 satırı görünüp alta doğru beyaza kaybolur,
                     satıra tıklanınca o gün tamamen açılır (dahil kutularıyla aynı
                     kalıp, incToggle ortak). İçeriksiz gün tıklanamaz düz satırdır. --}}
                <div class="tour-tab-panel" data-tab-panel="program" @if($defaultTab !== 'program') hidden @endif>
                    <div style="background:var(--white);border:1px solid var(--border);border-radius:var(--radius);padding:20px;margin-bottom:16px;">
                        <h3 style="font-size:15px;font-weight:700;margin-bottom:6px;">📋 Tur Programı</h3>
                        @foreach($tour->itinerary as $i => $day)
                            @php
                                // Başlıkta zaten "N. Gün" / "Gün N" ön eki varsa temizle (sayfa kendi ekliyor)
                                $dayTitle = trim($day['title'] ?? '');
                                $dayTitle = preg_replace('/^\s*\d+\s*\.?\s*g[üu]n\s*[:\-–—]?\s*/iu', '', $dayTitle);
                                $dayTitle = preg_replace('/^\s*g[üu]n\s*\d+\s*[:\-–—]?\s*/iu', '', $dayTitle);
                                $dayTitle = trim($dayTitle);
                                $dayLabel = ($i + 1) . '. Gün' . ($dayTitle !== '' ? ': ' . $dayTitle : '');
                            @endphp
                            @if(! empty($day['content']))
                                <div class="prog-day">
                                    <button type="button" class="prog-day-head" aria-expanded="false" onclick="window.incToggle(this)">
                                        <span>{{ $dayLabel }}</span>
                                        <span class="inc-caret" aria-hidden="true">▾</span>
                                    </button>
                                    <div class="prog-day-body">{{ $day['content'] }}</div>
                                </div>
                            @else
                                <div class="prog-day prog-day-plain">{{ $dayLabel }}</div>
                            @endif
                        @endforeach
                    </div>
                </div>
                @endif

                {{-- Sekme: Tarihler ve Fiyatlar --}}
                @if($hasPrices)
                <div class="tour-tab-panel" data-tab-panel="tarihler" @if($defaultTab !== 'tarihler') hidden @endif>
                    {{-- Tarih ızgarası yalnızca paket fiyat tablosu YOKKEN gösterilir;
                         fiyat blokları varsa tarihler zaten aşağıdaki tabloda var (tekrar olmasın) --}}
                    @if(! $hasPricingBlocks)
                    @if($upcomingDates->count())
                    <div style="margin-bottom:20px;">
                        <div style="font-size:13px;font-weight:600;color:var(--text-muted);margin-bottom:8px;">📅 Kalkış Tarihleri</div>
                        <div style="display:flex;flex-wrap:wrap;gap:8px;">
                            @foreach($upcomingDates as $date)
                            <div style="background:var(--accent-bg);border-radius:var(--radius);padding:8px 14px;font-size:13px;">
                                <span style="font-weight:600;">{{ $date->departure_date->format('d-m-Y') }}</span>
                                <span style="color:var(--text-muted);margin:0 3px;">→</span>
                                <span style="font-weight:600;">{{ $date->return_date->format('d-m-Y') }}</span>
                                {!! $datePriceRenderer($date) !!}
                                @if($date->label)
                                    <span class="badge badge-accent" style="font-size:10px;margin-left:4px;">{{ $date->label }}</span>
                                @endif
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @elseif($tour->departure_date && $tour->departure_date->greaterThanOrEqualTo(now()->startOfDay()))
                    <div style="margin-bottom:16px;">
                        <span class="badge badge-accent">📅 {{ $tour->departure_date->format('d-m-Y') }} — {{ $tour->return_date?->format('d-m-Y') }}</span>
                    </div>
                    @endif
                    @endif

                    {{-- Fiyat tablosu: tarihe tıklanınca paket/oda-tipi fiyat matrisi açılır --}}
                    @if($hasPricingBlocks)
                        @php $roomTypeLabels = \App\Models\Tour::ROOM_TYPES; $priceCurrency = $tour->currency_symbol; @endphp
                        <div style="margin-bottom:24px;">
                            <div style="font-size:13px;font-weight:600;color:var(--text-muted);margin-bottom:8px;">💰 Tarih ve Paket Fiyatları</div>
                            @foreach($pricingBlocks as $block)
                                @php
                                    $blockDates = $block['dates']; // yukarıda geçmişi ayıklandı + sıralandı
                                    $packages = array_values(array_filter((array) ($block['packages'] ?? []), 'is_array'));
                                    // Bu blokta veri girilmiş oda/yaş tiplerini ROOM_TYPES sırasında topla
                                    $activeTypes = [];
                                    foreach (array_keys($roomTypeLabels) as $type) {
                                        foreach ($packages as $pkg) {
                                            $cell = $pkg['prices'][$type] ?? null;
                                            if (is_array($cell) && (($cell['old'] ?? null) !== null || ($cell['new'] ?? null) !== null || trim((string) ($cell['note'] ?? '')) !== '')) {
                                                $activeTypes[] = $type;
                                                break;
                                            }
                                        }
                                    }
                                @endphp
                                @if($blockDates->count() && count($packages))
                                {{-- name: aynı gruptaki details'lerden yalnız biri açık kalır (modern tarayıcı native) --}}
                                <details class="pricing-acc" name="pricing-dates" style="background:var(--white);border:1px solid var(--border);border-radius:var(--radius);margin-bottom:10px;overflow:hidden;">
                                    <summary style="cursor:pointer;padding:12px 16px;font-weight:600;font-size:14px;list-style:none;display:flex;flex-wrap:wrap;gap:6px;align-items:center;">
                                        <span style="color:var(--accent);">📅</span>
                                        @foreach($blockDates as $bd)
                                            @php $bdReturn = $bd->copy()->addDays(max(1, (int) $tour->duration_days) - 1); @endphp
                                            <span style="background:var(--accent-bg);border-radius:999px;padding:2px 10px;font-size:12px;">{{ $bd->format('d-m-Y') }} → {{ $bdReturn->format('d-m-Y') }}</span>
                                        @endforeach
                                        <span style="color:var(--text-muted);font-size:12px;font-weight:500;margin-left:auto;">fiyatları gör ▾</span>
                                    </summary>
                                    <div class="pricing-scroll" style="overflow-x:auto;border-top:1px solid var(--border);">
                                        <table class="pricing-table" style="width:100%;border-collapse:collapse;font-size:13px;min-width:520px;">
                                            <thead>
                                                <tr style="background:var(--accent-bg);">
                                                    <th style="text-align:left;padding:10px 12px;font-weight:700;">Paket / Otel</th>
                                                    @foreach($activeTypes as $type)
                                                        <th style="text-align:right;padding:10px 12px;font-weight:700;white-space:nowrap;">{{ $roomTypeLabels[$type] }}</th>
                                                    @endforeach
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($packages as $pkg)
                                                    <tr style="border-top:1px solid var(--border);">
                                                        <td class="pkg-name" style="padding:10px 12px;font-weight:600;">{{ ($pkg['hotel'] ?? '') !== '' ? $pkg['hotel'] : 'Standart Paket' }}</td>
                                                        @foreach($activeTypes as $type)
                                                            @php
                                                                $cell = $pkg['prices'][$type] ?? [];
                                                                $old = is_array($cell) ? ($cell['old'] ?? null) : null;
                                                                $new = is_array($cell) ? ($cell['new'] ?? null) : null;
                                                                $note = is_array($cell) ? trim((string) ($cell['note'] ?? '')) : '';
                                                            @endphp
                                                            <td class="pkg-price" data-label="{{ $roomTypeLabels[$type] }}" style="padding:10px 12px;text-align:right;white-space:nowrap;">
                                                                <span class="pkg-price-val">
                                                                @if($old !== null && $new !== null && (float) $old > (float) $new)
                                                                    <s style="color:#94a3b8;font-size:12px;">{{ number_format((float) $old, 0, ',', '.') }}</s>
                                                                    <span style="font-weight:700;color:#166534;margin-left:4px;">{{ number_format((float) $new, 0, ',', '.') }} {{ $priceCurrency }}</span>
                                                                @elseif($new !== null)
                                                                    <span style="font-weight:700;">{{ number_format((float) $new, 0, ',', '.') }} {{ $priceCurrency }}</span>
                                                                @elseif($old !== null)
                                                                    <span style="font-weight:700;">{{ number_format((float) $old, 0, ',', '.') }} {{ $priceCurrency }}</span>
                                                                @elseif($note !== '')
                                                                    <span style="color:var(--text-muted);font-size:12px;font-weight:600;">{{ $note }}</span>
                                                                @else
                                                                    <span style="color:var(--text-muted);">—</span>
                                                                @endif
                                                                </span>
                                                            </td>
                                                        @endforeach
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </details>
                                @endif
                            @endforeach
                        </div>
                        <script>
                        // Tekli akordeon güvencesi: bir tarih paketi açılınca açık olan diğeri
                        // kapanır. Modern tarayıcılar bunu <details name="..."> ile native yapar;
                        // bu dinleyici desteklemeyen eski tarayıcılar için aynı davranışı sağlar.
                        document.querySelectorAll('details.pricing-acc').forEach(function (d) {
                            d.addEventListener('toggle', function () {
                                if (!d.open) return;
                                document.querySelectorAll('details.pricing-acc[open]').forEach(function (o) {
                                    if (o !== d) o.open = false;
                                });
                            });
                        });
                        </script>
                    @endif

                    {{-- Price History Chart --}}
                    @if($priceData->count() >= 2)
                    <div style="background:var(--white);border:1px solid var(--border);border-radius:var(--radius-lg);padding:24px;margin-bottom:16px;">
                        <div style="display:flex;align-items:center;gap:10px;margin-bottom:20px;">
                            <span style="font-size:18px;">📊</span>
                            <h3 style="font-size:16px;font-weight:700;color:#0f172a;">Fiyat Geçmişi (Son 30 Gün)</h3>
                            @php
                                $firstPrice = $priceData->first();
                                $lastPrice  = $priceData->last();
                                $diff = $lastPrice - $firstPrice;
                                $pct  = $firstPrice > 0 ? round(($diff / $firstPrice) * 100) : 0;
                            @endphp
                            @if($diff != 0)
                            <span style="font-size:13px;font-weight:700;color:{{ $diff < 0 ? '#059669' : '#dc2626' }};background:{{ $diff < 0 ? '#d1fae5' : '#fef2f2' }};padding:4px 12px;border-radius:20px;">
                                {{ $diff < 0 ? '↓' : '↑' }} %{{ abs($pct) }} {{ $diff < 0 ? 'düşüş' : 'artış' }}
                            </span>
                            @endif
                        </div>
                        <div style="position:relative;height:200px;">
                            <canvas id="priceHistoryChart"></canvas>
                        </div>
                    </div>
                    @endif
                </div>
                @endif

                {{-- Sekme: Genel Bilgiler --}}
                @if($hasGeneral)
                <div class="tour-tab-panel" data-tab-panel="genel" @if($defaultTab !== 'genel') hidden @endif>
                    {{-- description_html: etiketler atılmış + e() ile kaçırılmış metin,
                         satır sonları <br>. Bunu {{ }} ile basmaya geri dönme —
                         ekranda "<p>" yazısı görünür (bkz. bulgu B1). --}}
                    @if($tour->description_html)
                        <p style="color:var(--text-sec);line-height:1.8;margin-bottom:24px;font-size:15px;">{!! $tour->description_html !!}</p>
                    @endif

                    @foreach($detailSections as [$icon, $heading, $body])
                        @if(trim((string) $body) !== '')
                            <div style="background:var(--white);border:1px solid var(--border);border-radius:var(--radius);padding:20px;margin-bottom:16px;">
                                <h3 style="font-size:15px;font-weight:700;margin-bottom:10px;">{{ $icon }} {{ $heading }}</h3>
                                <div style="color:var(--text-sec);line-height:1.8;font-size:14px;white-space:pre-line;">{{ trim($body) }}</div>
                            </div>
                        @endif
                    @endforeach

                    @if($tour->frequency)
                        <div style="color:var(--text-muted);font-size:13px;margin-bottom:16px;">🔁 {{ $tour->frequency }}</div>
                    @endif
                </div>
                @endif

                {{-- Sekme: Yorumlar --}}
                <div class="tour-tab-panel" data-tab-panel="yorumlar" @if($defaultTab !== 'yorumlar') hidden @endif>
                    <div style="display:flex;align-items:center;gap:16px;margin-bottom:24px;">
                        <h2 style="font-size:20px;font-weight:700;">Yorumlar</h2>
                        @if($avgRating)
                            <div style="display:flex;align-items:center;gap:6px;background:var(--accent-bg);border-radius:20px;padding:4px 14px;">
                                <span style="color:#f59e0b;font-size:16px;">★</span>
                                <span style="font-weight:700;color:var(--accent);">{{ $avgRating }}</span>
                                <span style="font-size:13px;color:var(--text-muted);">({{ $reviews->count() }} yorum)</span>
                            </div>
                        @else
                            <span style="font-size:13px;color:var(--text-muted);">Henüz yorum yok</span>
                        @endif
                    </div>

                    {{-- Write Review Form --}}
                    @auth
                        @if(!$userReview)
                        <div style="background:var(--white);border:1px solid var(--border);border-radius:var(--radius);padding:20px;margin-bottom:24px;">
                            <h3 style="font-size:15px;font-weight:700;margin-bottom:14px;">Yorum Yaz</h3>
                            <form method="POST" action="{{ route('reviews.store', $tour) }}">
                                @csrf
                                @if($errors->any())
                                    <div class="alert alert-error">{{ $errors->first() }}</div>
                                @endif
                                <div class="form-group">
                                    <label>Puanınız</label>
                                    <div style="display:flex;gap:8px;">
                                        @for($i = 1; $i <= 5; $i++)
                                        <label style="cursor:pointer;font-size:24px;" title="{{ $i }} yıldız">
                                            <input type="radio" name="rating" value="{{ $i }}" style="display:none;" {{ old('rating') == $i ? 'checked' : ($i == 5 ? 'checked' : '') }}>
                                            <span style="color:#f59e0b;">★</span>
                                        </label>
                                        @endfor
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>Yorumunuz</label>
                                    <textarea name="comment" placeholder="Bu tur hakkında deneyiminizi paylaşın..." rows="3">{{ old('comment') }}</textarea>
                                </div>
                                <button type="submit" class="btn btn-primary btn-sm">Yorum Yap</button>
                            </form>
                        </div>
                        @endif
                    @else
                        <div style="background:var(--accent-bg);border-radius:var(--radius);padding:16px;margin-bottom:24px;text-align:center;">
                            <a href="{{ route('login') }}" style="color:var(--accent);font-weight:600;">Giriş yapın</a> ve bu tur hakkında yorum yapın.
                        </div>
                    @endauth

                    {{-- Reviews List --}}
                    @forelse($reviews as $review)
                    <div style="background:var(--white);border:1px solid var(--border);border-radius:var(--radius);padding:18px;margin-bottom:10px;">
                        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:8px;">
                            <div style="display:flex;align-items:center;gap:10px;">
                                <div style="width:38px;height:38px;background:var(--accent-bg);border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;color:var(--accent);">{{ mb_substr($review->user->name, 0, 1) }}</div>
                                <div>
                                    <div style="font-weight:600;font-size:14px;">{{ $review->user->name }}</div>
                                    <div style="font-size:12px;color:var(--text-muted);">{{ $review->created_at->diffForHumans() }}</div>
                                </div>
                            </div>
                            <div style="display:flex;align-items:center;gap:8px;">
                                <div style="color:#f59e0b;font-size:14px;">{{ str_repeat('★', $review->rating) }}{{ str_repeat('☆', 5 - $review->rating) }}</div>
                                @if(auth()->check() && auth()->id() === $review->user_id)
                                    <form method="POST" action="{{ route('reviews.destroy', $review) }}" style="display:inline;">
                                        @csrf @method('DELETE')
                                        <button type="submit" style="font-size:12px;color:#dc2626;background:none;border:none;cursor:pointer;font-family:var(--font);">Sil</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                        <p style="font-size:14px;color:var(--text-sec);line-height:1.7;">{{ $review->comment }}</p>
                    </div>
                    @empty
                        <div style="text-align:center;padding:32px;color:var(--text-muted);">
                            <div style="font-size:36px;margin-bottom:8px;">⭐</div>
                            Henüz yorum yapılmamış. İlk yorumu sen yap!
                        </div>
                    @endforelse
                </div>

                {{-- Sıkça Sorulan Sorular.
                     ŞART: FAQPage şeması sayfada GÖRÜNÜR karşılığı olmadan
                     basılamaz — Google bunu ihlal sayar. Bu blok ile şema aynı
                     kaynaktan (TourSchema::faq) üretilir, ikisi hep eşittir. --}}
                @php
                    $tourFaq = \App\Support\TourSchema::faq($tour);
                @endphp
                @if($tourFaq)
                    <section style="margin-top:32px;" aria-labelledby="sss-baslik">
                        <h2 id="sss-baslik" style="font-size:20px;font-weight:700;margin-bottom:16px;">Sıkça Sorulan Sorular</h2>
                        @foreach($tourFaq['mainEntity'] as $soru)
                            <details style="background:var(--white);border:1px solid var(--border);border-radius:var(--radius);margin-bottom:10px;">
                                <summary style="cursor:pointer;padding:14px 16px;font-weight:600;font-size:15px;list-style:none;">
                                    {{ $soru['name'] }}
                                </summary>
                                <div style="padding:0 16px 14px;color:var(--text-sec);font-size:14px;line-height:1.7;">
                                    {{ $soru['acceptedAnswer']['text'] }}
                                </div>
                            </details>
                        @endforeach
                    </section>
                @endif

                {{-- Dahil olan/olmayanlar sol sütunda: kenar çubuğu yalnız yapışkan fiyat
                     bloğunu taşır (viewport'tan uzun kenar çubuğu yapışamıyordu) --}}
                {{-- Dahil olan/olmayan kutuları: kapalıyken ilk ~3 satır görünür,
                     devamı beyaza kaybolur (.inc-body::after degradesi); başlığa
                     basınca tamamı açılır. Liste önizlemeye zaten sığıyorsa
                     soldurma + ok gizlenir (bkz. scripts'teki inc-box ölçümü). --}}
                @if($tour->included)
                    <div class="inc-box">
                        <button type="button" class="inc-head" aria-expanded="false" onclick="window.incToggle(this)">
                            <h3>✅ Dahil Olanlar</h3>
                        </button>
                        <div class="inc-body">
                            <ul style="list-style:none;margin-top:10px;">
                                @foreach(explode("\n", $tour->included) as $item)
                                    @php $line = ltrim(trim($item), "•-*–— \t"); @endphp
                                    @if($line !== '')
                                        <li style="padding:4px 0;color:var(--text-sec);font-size:14px;">• {{ $line }}</li>
                                    @endif
                                @endforeach
                            </ul>
                        </div>
                        <button type="button" class="inc-more" aria-expanded="false" aria-label="Devamını göster" onclick="window.incToggle(this)">
                            <span class="inc-caret" aria-hidden="true">▾</span>
                        </button>
                    </div>
                @endif

                @if($tour->excluded)
                    <div class="inc-box">
                        <button type="button" class="inc-head" aria-expanded="false" onclick="window.incToggle(this)">
                            <h3>❌ Dahil Olmayanlar</h3>
                        </button>
                        <div class="inc-body">
                            <ul style="list-style:none;margin-top:10px;">
                                @foreach(explode("\n", $tour->excluded) as $item)
                                    @php $line = ltrim(trim($item), "•-*–— \t"); @endphp
                                    @if($line !== '')
                                        <li style="padding:4px 0;color:var(--text-sec);font-size:14px;">• {{ $line }}</li>
                                    @endif
                                @endforeach
                            </ul>
                        </div>
                        <button type="button" class="inc-more" aria-expanded="false" aria-label="Devamını göster" onclick="window.incToggle(this)">
                            <span class="inc-caret" aria-hidden="true">▾</span>
                        </button>
                    </div>
                @endif
            </div>

            {{-- Right: Pricing & Agency --}}
            <div class="detail-sidebar">
                {{-- Yapışkan blok: fiyat kartı + karşılaştır + diğer acentalar. Kenar çubuğu
                     satır yüksekliğine uzar (align-self:stretch), blok içinde yapışır. --}}
                <div class="detail-sticky">


                {{-- Main Price Card --}}
                @php $campaign = $tour->activeCampaign; @endphp
                <div id="dPriceSlot"></div>
                <div id="priceCard" style="background:var(--white);border:2px solid {{ $campaign ? 'var(--green)' : 'var(--accent)' }};border-radius:var(--radius-lg);padding:24px;margin-bottom:16px;">
                    @if($campaign)
                        {{-- Campaign active --}}
                        <div class="p-badge" style="margin-bottom:6px;">
                            <span class="badge badge-green">🏷️ {{ $campaign->label }}</span>
                        </div>
                        <div class="p-price" style="margin:10px 0 4px;">
                            <span style="text-decoration:line-through;color:var(--text-muted);font-size:16px;">{{ $tour->formatted_price }}</span>
                            <span class="price-tag cheapest" style="font-size:32px;margin-left:8px;color:var(--green);">{{ $campaign->formatted_discount_price }}</span>
                            <span class="price-sm"> / kişi başı</span>
                        </div>
                        {{-- Countdown --}}
                        <div id="campaign-countdown" style="background:linear-gradient(135deg,#fef3c7,#fde68a);border-radius:var(--radius);padding:10px 14px;margin:12px 0 8px;text-align:center;">
                            <div style="font-size:11px;font-weight:600;color:#92400e;margin-bottom:4px;">⏰ Kampanya Bitiyor</div>
                            <div id="countdown-timer" style="font-size:18px;font-weight:800;color:#d97706;font-variant-numeric:tabular-nums;"></div>
                        </div>
                        <script>
                        (function(){
                            var end = new Date("{{ $campaign->ends_at->toIso8601String() }}").getTime();
                            var el = document.getElementById('countdown-timer');
                            function tick(){
                                var now = Date.now(), diff = end - now;
                                if(diff <= 0){ el.textContent = 'Süre doldu!'; return; }
                                var d = Math.floor(diff/86400000), h = Math.floor((diff%86400000)/3600000),
                                    m = Math.floor((diff%3600000)/60000), s = Math.floor((diff%60000)/1000);
                                el.textContent = (d > 0 ? d + 'g ' : '') + h + 's ' + m + 'dk ' + s + 'sn';
                                setTimeout(tick, 1000);
                            }
                            tick();
                        })();
                        </script>
                    @else
                        {{-- Rozet koşullu: daha ucuz teklif varsa dürüst uyarı, gerçekten en
                             ucuzsa rozet, tek teklifse hiçbiri (kıyas yoksa "en ucuz" da yok). --}}
                        @if($cheaperOffer)
                            @php $ucuzYuzde = (int) round((1 - (float) $cheaperOffer->price_try / max((float) $tour->price_try, 0.01)) * 100); @endphp
                            <div class="p-badge p-ucuz-uyari" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:8px 12px;color:#92400e;font-size:12.5px;font-weight:600;margin-bottom:4px;">
                                <span style="flex:1;min-width:0;">{{ $cheaperOffer->agency->name }} bu turu{{ $ucuzYuzde > 0 ? ' %'.$ucuzYuzde : '' }} daha ucuza veriyor</span>
                                <a href="{{ route('tour.redirect', $cheaperOffer) }}" target="_blank" rel="noopener" style="color:#b45309;font-weight:700;white-space:nowrap;">Teklife git →</a>
                            </div>
                        @elseif($otherOffers->count())
                            <div class="p-badge" style="margin-bottom:4px;display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                                <span class="badge badge-green">✓ En ucuz teklif</span>
                                <span style="font-size:12px;color:var(--text-meta);">{{ $otherOffers->count() + 1 }} acenta içinde</span>
                            </div>
                        @endif
                        <div class="p-price" style="margin:12px 0 4px;">
                            <span class="price-tag cheapest" style="font-size:32px;">{{ $tour->formatted_price }}</span>
                            <span class="price-sm"> / kişi başı</span>
                        </div>
                    @endif
                    <div class="p-agency" style="margin-bottom:16px;display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                        <a href="{{ route('agencies.show', $tour->agency) }}" style="color:var(--accent-ink);font-weight:600;font-size:15px;">{{ $tour->agency->name }}</a>
                        {{-- Onay verisi Agency.approval_status: eski (null) ve onaylı acentalar rozet alır --}}
                        @if($tour->agency->isApproved())
                            <span class="badge badge-accent p-onay" style="font-size:11px;padding:3px 8px;gap:4px;"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3l7.5 3v5.5c0 4.6-3.2 8.3-7.5 9.5-4.3-1.2-7.5-4.9-7.5-9.5V6z"/><path d="M9 12.2l2.1 2.1L15.4 10"/></svg> Onaylı acenta</span>
                        @endif
                    </div>
                    <div class="p-ctas" style="display:flex;flex-direction:column;gap:10px;">
                        @php $mainUrl = $tour->tour_url ?: $tour->agency->website_url; @endphp
                        @if($mainUrl)
                            <a href="{{ route('tour.redirect', $tour) }}" target="_blank" rel="noopener" class="btn btn-primary p-go" style="width:100%;">Acentada İncele <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 4h6v6M20 4l-9 9"/><path d="M19 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1h5"/></svg></a>
                        @endif
                        @if($tour->agency->phone)
                            <a href="tel:{{ preg_replace('/\s+/', '', $tour->agency->phone) }}" class="btn btn-outline p-call" style="width:100%;">📞 <span class="p-tel-full">{{ $tour->agency->phone }}</span><span class="p-tel-short">Ara</span></a>
                        @endif
                        @if($tour->agency->email)
                            <a href="mailto:{{ $tour->agency->email }}" class="btn btn-outline p-mail" style="width:100%;">✉️<span class="p-mail-text"> E-posta Gönder</span></a>
                        @endif
                    </div>
                    {{-- Çıkış beklentisi: düğme müşteriyi acentanın sitesine götürür, sürpriz olmasın --}}
                    <div class="p-rez" style="display:flex;align-items:center;gap:6px;margin-top:12px;font-size:12.5px;color:var(--text-meta);line-height:1.4;"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="10" width="16" height="11" rx="3"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg> Rezervasyon acentanın sitesinde tamamlanır. turXtur ödeme almaz.</div>
                </div>

                {{-- Compare button --}}
                <div style="margin-bottom:16px;">
                    <button type="button" class="compare-toggle" data-tour-id="{{ $tour->id }}" onclick="window.toggleCompare({{ $tour->id }})" style="width:100%;padding:11px;border:1.5px solid var(--border);border-radius:10px;background:var(--white);color:var(--text-sec);font-family:var(--font);font-size:14px;font-weight:600;cursor:pointer;transition:all .2s;">
                        + Karşılaştır
                    </button>
                </div>

                {{-- Other Agencies --}}
                @if($otherOffers->count())
                <div style="background:var(--white);border:1px solid var(--border);border-radius:var(--radius-lg);padding:20px;">
                    <h3 style="font-size:15px;font-weight:700;margin-bottom:14px;">Diğer Acentalar</h3>
                    <div style="display:flex;flex-direction:column;gap:10px;">
                        @foreach($otherOffers as $offer)
                        <div style="display:flex;align-items:center;justify-content:space-between;padding:12px;background:var(--bg);border-radius:10px;">
                            <div>
                                <div style="font-weight:600;font-size:14px;">{{ $offer->agency->name }}</div>
                                <div style="font-size:12px;color:var(--text-muted);">
                                    {{-- Kur-normalize kıyas: farklı para birimindeki teklifler de doğru okunsun --}}
                                    @php $diff = (float) $tour->price_try > 0 ? round((((float) $offer->price_try - (float) $tour->price_try) / (float) $tour->price_try) * 100) : 0; @endphp
                                    @if($diff > 0) +%{{ $diff }} daha pahalı @elseif($diff < 0) <span style="color:#b45309;font-weight:600;">%{{ abs($diff) }} daha ucuz</span> @endif
                                </div>
                            </div>
                            <div style="text-align:right;">
                                <div class="price-tag" style="font-size:16px;">{{ $offer->formatted_price }}</div>
                                @php $offerUrl = $offer->tour_url ?: $offer->agency->website_url; @endphp
                                @if($offerUrl)
                                    <a href="{{ route('tour.redirect', $offer) }}" target="_blank" rel="noopener" style="font-size:12px;color:var(--accent-ink);font-weight:600;">Acentada İncele →</a>
                                @endif
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif
                </div>{{-- /.detail-sticky --}}
            </div>
        </div>
    </div>
</div>

{{-- Mobil yapışkan CTA şeridi: fiyat kartı ekranın üstünden çıkınca belirir
     (body.cta-acik, gözlemci aşağıdaki betikte). Dip katman token'ı --cta-h ile
     sohbet balonu ve karşılaştırma tepsisi şeridin üstüne çıkar; sekme barı altta
     kalır. $campaign ve $mainUrl yukarıdaki fiyat kartı bloğunda tanımlı. --}}
<div class="m-cta" id="mCta" aria-hidden="true">
    <div class="m-cta-fiyat">
        <div class="m-cta-satir">
            <span class="m-cta-tutar">{{ $campaign ? $campaign->formatted_discount_price : $tour->formatted_price }}</span>
            <span class="m-cta-kisi">/ kişi</span>
        </div>
        <div class="m-cta-acenta">{{ $tour->agency->name }}@if($tour->agency->isApproved()) <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3l7.5 3v5.5c0 4.6-3.2 8.3-7.5 9.5-4.3-1.2-7.5-4.9-7.5-9.5V6z"/><path d="M9 12.2l2.1 2.1L15.4 10"/></svg> Onaylı@endif</div>
    </div>
    @if($mainUrl)
        <a href="{{ route('tour.redirect', $tour) }}" target="_blank" rel="noopener" class="btn btn-primary m-cta-git">Acentada İncele <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 4h6v6M20 4l-9 9"/><path d="M19 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1h5"/></svg></a>
    @endif
    @if($tour->agency->phone)
        <a href="tel:{{ preg_replace('/\s+/', '', $tour->agency->phone) }}" class="btn btn-outline m-cta-tel" aria-label="Acentayı ara"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.4 1.8.7 2.6a2 2 0 0 1-.5 2.1L8 9.7a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.5c.8.3 1.7.6 2.6.7a2 2 0 0 1 1.7 2z"/></svg></a>
    @endif
</div>
@endsection

@push('scripts')
<script>
// Önizlemeli akordeonlar (dahil kutuları + program günleri): tıklama aç/kapa.
// İçerik kapalı önizlemeye (max-height) zaten sığıyorsa öğe .kisa olur —
// soldurma ve ok gizlenir, tıklama işlevsizleşir.
window.incToggle = function (btn) {
    var box = btn.closest('.inc-box, .prog-day');
    if (box.classList.contains('kisa')) return;
    var acik = box.classList.toggle('acik');
    box.querySelectorAll('[aria-expanded]').forEach(function (b) { b.setAttribute('aria-expanded', acik); });
};
document.querySelectorAll('.inc-box .inc-body, .prog-day .prog-day-body').forEach(function (body) {
    if (body.scrollHeight <= body.clientHeight) body.closest('.inc-box, .prog-day').classList.add('kisa');
});
</script>
<script>
// Sekme geçişi: içerik DOM'da kalır (SEO), yalnız görünürlük değişir.
// Derin link: #program / #tarihler / #genel / #yorumlar
(function () {
    var tabs = document.querySelectorAll('.tour-tab');
    if (!tabs.length) return;

    function activate(id, updateHash) {
        var target = document.querySelector('.tour-tab[data-tab="' + id + '"]');
        if (!target) return;
        tabs.forEach(function (b) { b.classList.toggle('active', b === target); });
        document.querySelectorAll('.tour-tab-panel').forEach(function (p) {
            p.hidden = p.getAttribute('data-tab-panel') !== id;
        });
        // Grafik gizli panelde sıfır boyut render eder; sekme ilk açıldığında çiz
        if (id === 'tarihler' && window.__initPriceChart) window.__initPriceChart();
        if (updateHash && history.replaceState) history.replaceState(null, '', '#' + id);
    }

    tabs.forEach(function (b) {
        b.addEventListener('click', function () { activate(b.dataset.tab, true); });
    });

    var hash = (location.hash || '').replace('#', '');
    if (hash && document.querySelector('.tour-tab[data-tab="' + hash + '"]')) {
        activate(hash, false);
    } else if (@json($errors->any())) {
        // Yorum formu hatayla dönünce kullanıcı formu kaybetmesin
        activate('yorumlar', false);
    }

    window.addEventListener('hashchange', function () {
        var h = (location.hash || '').replace('#', '');
        if (h) activate(h, false);
    });
})();

// Galeri: şerit sayacı, küçük resimle gezinme, tam ekran görüntüleyici.
(function () {
    var strip = document.getElementById('gStrip');
    if (!strip) return;
    var slides = strip.querySelectorAll('.g-slide'), n = slides.length;
    var count = document.getElementById('gCount'), thumbs = document.querySelectorAll('.g-thumb');
    var lb = document.getElementById('gLightbox'), lbStrip = document.getElementById('gLbStrip'), lbCount = document.getElementById('gLbCount');
    function idxOf(el) { return Math.min(n - 1, Math.max(0, Math.round(el.scrollLeft / Math.max(1, el.clientWidth)))); }
    function goTo(el, i, yumusak) { el.scrollTo({ left: i * el.clientWidth, behavior: yumusak ? 'smooth' : 'auto' }); }
    function guncelle() {
        var i = idxOf(strip);
        if (count) count.textContent = (i + 1) + ' / ' + n;
        thumbs.forEach(function (t, k) { t.classList.toggle('on', k === i); });
    }
    strip.addEventListener('scroll', function () { window.requestAnimationFrame(guncelle); }, { passive: true });
    thumbs.forEach(function (t) { t.addEventListener('click', function () { goTo(strip, +t.dataset.index, true); }); });

    if (!lb || typeof lb.showModal !== 'function') return;
    function lbGuncelle() { if (lbCount) lbCount.textContent = (idxOf(lbStrip) + 1) + ' / ' + n; }
    function lbAc(i) {
        lb.showModal();
        document.body.style.overflow = 'hidden';
        window.requestAnimationFrame(function () { goTo(lbStrip, i, false); lbGuncelle(); });
    }
    slides.forEach(function (s) { s.addEventListener('click', function () { lbAc(+s.dataset.index); }); });
    lb.addEventListener('close', function () {
        document.body.style.overflow = '';
        lbStrip.querySelectorAll('img.buyuk').forEach(function (im) { im.classList.remove('buyuk'); });
        goTo(strip, idxOf(lbStrip), false); // şeritte de aynı fotoğrafta kal
    });
    lb.addEventListener('click', function (e) { if (e.target === lb) lb.close(); });
    document.getElementById('gLbKapat').addEventListener('click', function () { lb.close(); });
    var prev = document.getElementById('gLbPrev'), next = document.getElementById('gLbNext');
    if (prev) prev.addEventListener('click', function () { goTo(lbStrip, Math.max(0, idxOf(lbStrip) - 1), true); });
    if (next) next.addEventListener('click', function () { goTo(lbStrip, Math.min(n - 1, idxOf(lbStrip) + 1), true); });
    lbStrip.addEventListener('scroll', function () { window.requestAnimationFrame(lbGuncelle); }, { passive: true });
    lb.addEventListener('keydown', function (e) {
        if (e.key === 'ArrowRight' && next) next.click();
        if (e.key === 'ArrowLeft' && prev) prev.click();
    });
    // Tıklayınca/dokununca büyüt; iki parmakla sıkıştırma tarayıcıya bırakılır (touch-action)
    lbStrip.querySelectorAll('img').forEach(function (im) { im.addEventListener('click', function () { im.classList.toggle('buyuk'); }); });
})();

// Kısa bilgi baloncuğu: ekranın altında 3 sn görünür; şerit ve sekme barının üstünde durur.
window.pToast = function (mesaj) {
    var el = document.getElementById('p-toast');
    if (!el) { el = document.createElement('div'); el.id = 'p-toast'; el.setAttribute('role', 'status'); document.body.appendChild(el); }
    el.textContent = mesaj;
    clearTimeout(el._t);
    requestAnimationFrame(function () { el.classList.add('acik'); });
    el._t = setTimeout(function () { el.classList.remove('acik'); }, 3200);
};

// Paylaş: cihazın paylaşım menüsü → (dokunmatik) WhatsApp → bağlantıyı kopyala.
(function () {
    var btn = document.getElementById('pShare');
    if (!btn) return;
    btn.addEventListener('click', async function () {
        var url = btn.dataset.url, title = btn.dataset.title;
        if (navigator.share) {
            try { await navigator.share({ title: title, text: title, url: url }); return; }
            catch (e) { if (e && e.name === 'AbortError') return; }
        }
        if (window.matchMedia('(pointer:coarse)').matches) {
            window.open('https://wa.me/?text=' + encodeURIComponent(title + ' ' + url), '_blank', 'noopener');
            return;
        }
        try { await navigator.clipboard.writeText(url); window.pToast('Bağlantı kopyalandı.'); }
        catch (e) { window.prompt('Bağlantıyı kopyala', url); }
    });
})();

// Favori: sayfa yenilenmeden (AJAX) ekle/çıkar; baloncuk tek kanala göre dürüst
// yazıldı — bugün yalnız site içi bildirim var, e-posta yok. Ziyaretçi girişe gider.
(function () {
    var btn = document.getElementById('pFav');
    if (!btn) return;
    btn.addEventListener('click', function () {
        if (btn.dataset.login) { window.location.href = btn.dataset.login; return; }
        if (btn.dataset.busy) return;
        btn.dataset.busy = '1';
        fetch(@json(url('/favoriler')) + '/' + btn.dataset.tour, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        }).then(function (r) { if (!r.ok) throw new Error('favori'); return r.json(); })
        .then(function (d) {
            btn.classList.toggle('on', d.favorited);
            btn.setAttribute('aria-pressed', d.favorited ? 'true' : 'false');
            btn.querySelector('.p-fav-text').textContent = d.favorited ? 'Favoride' : 'Favorilere ekle';
            window.pToast(d.favorited
                ? 'Favorilere eklendi. Fiyat değişikliklerini bildirimlerinden takip edebilirsin.'
                : 'Favorilerden çıkarıldı.');
        })
        .catch(function () { window.pToast('Olmadı, tekrar dener misin?'); })
        .finally(function () { delete btn.dataset.busy; });
    });
})();

// Yapışkan CTA şeridi: fiyat kartı (mobilde başlığın altında) ekranın ÜSTÜNDEN
// çıkınca body.cta-acik; kart görünürken ya da henüz aşağıdayken şerit yok.
// Kaydırma dinleyicisi yerine IntersectionObserver: ucuz ve titremesiz. Kart
// placePriceCard ile yuvalar arasında taşınsa da gözlem elemana bağlı, sürer.
(function () {
    var card = document.getElementById('priceCard');
    var bar = document.getElementById('mCta');
    if (!card || !bar || !('IntersectionObserver' in window)) return;
    var mobil = window.matchMedia('(max-width:768px)');
    var gecti = false;
    function uygula() {
        var acik = mobil.matches && gecti;
        document.body.classList.toggle('cta-acik', acik);
        bar.setAttribute('aria-hidden', acik ? 'false' : 'true');
    }
    new IntersectionObserver(function (entries) {
        var e = entries[0];
        gecti = !e.isIntersecting && e.boundingClientRect.top < 0;
        uygula();
    }, { threshold: 0 }).observe(card);
    if (mobil.addEventListener) mobil.addEventListener('change', uygula);
})();

// Fiyat kartı: mobilde başlığın altına, masaüstünde sidebar'a (tek DOM, iki yuva)
(function () {
    const card = document.getElementById('priceCard');
    const mSlot = document.getElementById('mPriceSlot');
    const dSlot = document.getElementById('dPriceSlot');
    if (!card || !mSlot || !dSlot) return;
    function placePriceCard() {
        const target = window.innerWidth <= 768 ? mSlot : dSlot;
        if (card.parentElement !== target) target.appendChild(card);
    }
    placePriceCard();
    window.addEventListener('resize', placePriceCard);
})();
</script>
@if(isset($priceData) && $priceData->count() >= 2)
<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script>
// Grafik yalnız görünürken çizilir (gizli sekmede canvas 0 boyut olur)
window.__initPriceChart = function () {
    if (window.__priceChartDone) return;
    var canvas = document.getElementById('priceHistoryChart');
    if (!canvas || typeof Chart === 'undefined') return;
    window.__priceChartDone = true;

    const ctx = canvas.getContext('2d');
    const currencySymbol = @json($tour->currency_symbol);

    // Create gradient
    const gradient = ctx.createLinearGradient(0, 0, 0, 200);
    gradient.addColorStop(0, 'rgba(13, 148, 136, 0.4)'); // Teal transparent
    gradient.addColorStop(1, 'rgba(13, 148, 136, 0.0)');

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: @json($priceLabels),
            datasets: [{
                label: 'Fiyat (' + currencySymbol + ')',
                data: @json($priceData),
                borderColor: '#0d9488', // Accent teal
                backgroundColor: gradient,
                borderWidth: 3,
                fill: true,
                tension: 0.4, // Smooth curves
                pointBackgroundColor: '#ffffff',
                pointBorderColor: '#0d9488',
                pointBorderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: 'rgba(15, 23, 42, 0.9)',
                    titleFont: { size: 13, family: 'Inter' },
                    bodyFont: { size: 14, family: 'Inter', weight: 'bold' },
                    padding: 12,
                    cornerRadius: 8,
                    displayColors: false,
                    callbacks: {
                        label: function(context) {
                            return new Intl.NumberFormat('tr-TR').format(context.raw) + ' ' + currencySymbol;
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: { display: false, drawBorder: false },
                    ticks: { font: { family: 'Inter', size: 12 }, color: '#64748b' }
                },
                y: {
                    grid: { color: '#f1f5f9', drawBorder: false },
                    ticks: {
                        font: { family: 'Inter', size: 12 },
                        color: '#64748b',
                        callback: function(value) { return new Intl.NumberFormat('tr-TR').format(value); }
                    }
                }
            }
        }
    });
};
document.addEventListener('DOMContentLoaded', function () {
    var panel = document.querySelector('[data-tab-panel="tarihler"]');
    if (panel && !panel.hidden) window.__initPriceChart();
});
</script>
@endif
@endpush
