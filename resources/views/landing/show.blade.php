@extends('layouts.app')

@php
    use App\Support\LandingFilter;
    use App\Support\LandingSlug;
    use App\Support\Seo;

    $baslik = LandingSlug::heading($model);
    $stem = Seo::stem($model->name);
    $sayi = $tours->total();                       // filtrelenmiş liste
    $toplam = $toplamTur;                          // sayfanın tüm envanteri
    $acentaSayisi = count($stats['acentalar'] ?? []);
    $enDusuk = $stats['fiyat']['min'] ?? null;
    $tl = fn ($n) => number_format((float) $n, 0, ',', '.').' ₺';

    // Kalp dolgusu: giriş yapmış kullanıcının favori tur id'leri (istek başına tek sorgu)
    $favIds = auth()->check()
        ? once(fn () => auth()->user()->favoriteTours()->pluck('tours.id')->all())
        : [];

    // Çip ve "Temizle" adresleri: mevcut filtreyi koruyup tek parametreyi değiştirir
    $temelUrl = url()->current();
    $paramlar = array_filter([
        'kalkis' => $filtre['kalkis'],
        'ay' => $filtre['ay'],
        'sure' => $filtre['sure'],
        'gece' => $filtre['gece'],
        'acenta' => $filtre['acenta'],
        'min_fiyat' => $filtre['min_fiyat'] !== null ? (int) $filtre['min_fiyat'] : null,
        'max_fiyat' => $filtre['max_fiyat'] !== null ? (int) $filtre['max_fiyat'] : null,
        'sirala' => $filtre['sirala'] !== 'onerilen' ? $filtre['sirala'] : null,
    ], fn ($v) => $v !== null && $v !== []);
    $adres = function (array $degisiklik) use ($temelUrl, $paramlar) {
        $p = array_filter(array_merge($paramlar, $degisiklik), fn ($v) => $v !== null && $v !== []);

        return $p === [] ? $temelUrl : $temelUrl.'?'.http_build_query($p);
    };
    $cip = function (string $anahtar, int $deger) use ($filtre, $adres) {
        $secili = in_array($deger, $filtre[$anahtar], true);
        $yeni = $secili
            ? array_values(array_diff($filtre[$anahtar], [$deger]))
            : array_merge($filtre[$anahtar], [$deger]);

        return [$secili, $adres([$anahtar => $yeni])];
    };

    $fiyatAraligi = $facets['fiyat'] ?? null;
    $fiyatKaydirici = $fiyatAraligi && $fiyatAraligi['max'] > $fiyatAraligi['min'];
    // Fiyat kaydırıcısı sınırları (masaüstü kenar çubuğu + mobil panel aynı değerleri okur).
    // step=1: adım >1 olunca tarayıcı değeri adıma yuvarlar, uç değer max'a eşit olmaz ve
    // "uçta → gönderme" kuralı kaçar (9768 ≠ 9800).
    $fMin = $fiyatKaydirici ? (int) $fiyatAraligi['min'] : 0;
    $fMax = $fiyatKaydirici ? (int) $fiyatAraligi['max'] : 0;
    $fAdim = 1;
    $fSecMin = $filtre['min_fiyat'] !== null ? max($fMin, min($fMax, (int) $filtre['min_fiyat'])) : $fMin;
    $fSecMax = $filtre['max_fiyat'] !== null ? max($fMin, min($fMax, (int) $filtre['max_fiyat'])) : $fMax;
    $landingFaq = \App\Support\LandingStats::faq($stats, $baslik);
@endphp

@section('title', Seo::listingTitle($model->name))
@section('description', trim(($model->description ?: '')) ?:
    $baslik.' — '.$toplam.' tur, '.$acentaSayisi.' acenta karşılaştırmalı.'
    .($enDusuk ? ' '.number_format($enDusuk, 0, ',', '.').' ₺\'den başlayan fiyatlar.' : ''))

@if(!empty($hero['image']))
    @section('og_image', $hero['image'])
@endif

@push('head')
    @include('partials.json-ld', [
        'data' => \App\Support\TourSchema::itemList($tours, Seo::canonical()) ?? [],
    ])
    @include('partials.pagination-seo', ['paginator' => $tours])
    @if($landingFaq)
        @include('partials.json-ld', ['data' => ['@context' => 'https://schema.org'] + $landingFaq])
    @endif
    <style>
        /* ── Landing (kategori / destinasyon) sayfası ──
           Sayfaya özgü sınıflar lp- önekli; tema renkleri layout'taki :root'tan.

           İKİ GÖVDE: .lp-desk (masaüstü tasarımı, >768px) ve .lp-mobil (mobil
           tasarımı, ≤768px — 2026-10-03: hero YOK, degrade çerçeveli başlık
           bloğu, yapışkan filtre şeridi + alttan filtre paneli, tam genişlik
           kartlar; acenta filtresi bilerek yok). Kırılma noktası sitenin
           geneliyle aynı (layouts/app 768px). */
        .lp { padding-bottom: 8px; }
        .lp-crumb { padding: 18px 0 4px; }
        .lp-mobil { display: none; }
        .lp-mobil-sss { display: none; }
        @media (max-width: 768px) {
            .lp-desk { display: none; }
            .lp-mobil { display: block; }
            .lp-mobil-sss { display: block; }
            .lp-crumb { padding-top: 24px; }
        }

        /* Hero */
        .lp-hero { position: relative; min-height: 300px; border-radius: 22px; overflow: hidden; background: #0f2421; color: #fff; isolation: isolate; }
        .lp-hero-img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; z-index: 0; }
        .lp-hero-overlay { position: absolute; inset: 0; z-index: 1; }
        .lp-hero-body { position: relative; z-index: 2; padding: 40px 44px 44px; max-width: 560px; }
        .lp-hero-eyebrow { font-size: 12.5px; font-weight: 800; letter-spacing: .14em; text-transform: uppercase; color: #5eead4; margin-bottom: 10px; }
        .lp-hero-title { font-family: 'Manrope', var(--font); font-size: 46px; font-weight: 800; line-height: 1.04; letter-spacing: -1.4px; margin: 0 0 12px; text-wrap: balance; }
        .lp-hero-sub { font-size: 17px; line-height: 1.5; color: rgba(255,255,255,.88); margin: 0 0 22px; }
        .lp-hero-stats { display: flex; flex-wrap: wrap; gap: 10px; }
        .lp-stat { display: inline-flex; align-items: center; gap: 9px; padding: 11px 16px; border-radius: 13px; background: rgba(255,255,255,.1); border: 1px solid rgba(255,255,255,.18); backdrop-filter: blur(6px); font-size: 14px; font-weight: 600; }
        .lp-stat svg { width: 18px; height: 18px; color: #5eead4; flex: none; }
        .lp-hero-caption { position: absolute; right: 22px; bottom: 18px; z-index: 2; display: inline-flex; align-items: center; gap: 6px; font-size: 12.5px; font-weight: 600; color: rgba(255,255,255,.92); text-shadow: 0 1px 3px rgba(0,0,0,.5); }
        .lp-hero-caption svg { width: 14px; height: 14px; }

        /* Arama kartı (hero'nun altına biner) */
        .lp-search { position: relative; z-index: 3; margin: -34px 18px 0; background: var(--white); border: 1px solid var(--border); border-radius: 18px; padding: 16px 18px 14px; box-shadow: 0 18px 40px -18px rgba(15,23,42,.22); }
        .lp-search-row { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)) auto; gap: 0; align-items: stretch; }
        .lp-field { display: flex; align-items: center; gap: 12px; padding: 6px 16px; border-right: 1px solid var(--border-light); min-width: 0; cursor: pointer; }
        .lp-field:first-child { padding-left: 6px; }
        .lp-field-ic { width: 38px; height: 38px; flex: none; border-radius: 11px; display: grid; place-items: center; color: var(--accent-ink); background: var(--accent-bg); }
        .lp-field-ic svg { width: 19px; height: 19px; }
        .lp-field-txt { display: flex; flex-direction: column; min-width: 0; flex: 1; }
        .lp-field-txt small { font-size: 11.5px; color: var(--text-meta); font-weight: 600; margin-bottom: 2px; }
        .lp-field select { appearance: none; -webkit-appearance: none; border: none; background: transparent url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2.2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E") no-repeat right center; padding: 0 22px 0 0; font: inherit; font-size: 15px; font-weight: 700; color: var(--text); width: 100%; cursor: pointer; }
        .lp-search-btn { margin-left: 16px; align-self: center; padding: 14px 24px; border-radius: 13px; gap: 10px; }
        .lp-search-btn svg { width: 16px; height: 16px; }
        .lp-chips { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px; padding-top: 12px; border-top: 1px solid var(--border-light); }
        .lp-chip { display: inline-flex; align-items: center; padding: 7px 14px; border-radius: 999px; border: 1px solid var(--border); background: var(--white); font-size: 13px; font-weight: 600; color: var(--text-sec); transition: border-color .15s, background .15s, color .15s; }
        .lp-chip:hover { border-color: var(--accent); color: var(--accent-ink); }
        .lp-chip.on { background: var(--accent-light); border-color: #5eead4; color: var(--accent-deep); }

        /* Ana düzen */
        .lp-main { display: grid; grid-template-columns: 220px minmax(0, 1fr); gap: 24px; margin-top: 28px; align-items: start; }
        .lp-side { position: sticky; top: 90px; display: flex; flex-direction: column; gap: 16px; }
        .lp-filt { background: var(--white); border: 1px solid var(--border); border-radius: 16px; padding: 18px 18px 6px; }
        .lp-filt-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px; }
        .lp-filt-head h2 { font-size: 18px; font-weight: 800; letter-spacing: -.3px; margin: 0; }
        .lp-clear { font-size: 12.5px; font-weight: 700; color: var(--accent-ink); }
        .lp-fg { border-top: 1px solid var(--border-light); padding: 12px 0; }
        .lp-fg summary { list-style: none; display: flex; align-items: center; justify-content: space-between; cursor: pointer; font-size: 14px; font-weight: 800; color: var(--text); }
        .lp-fg summary::-webkit-details-marker { display: none; }
        .lp-fg summary svg { width: 16px; height: 16px; color: var(--text-meta); transition: transform .2s; }
        .lp-fg[open] summary svg { transform: rotate(180deg); }
        .lp-fg-body { padding-top: 10px; display: flex; flex-direction: column; gap: 9px; }
        .lp-check { display: flex; align-items: center; gap: 9px; font-size: 13.5px; color: var(--text-sec); cursor: pointer; }
        .lp-check input { width: 16px; height: 16px; accent-color: var(--accent-ink); margin: 0; flex: none; }
        .lp-check small { margin-left: auto; color: var(--text-muted); font-size: 12px; }
        .lp-range { position: relative; padding: 10px 2px 2px; }
        .lp-range-track { position: relative; height: 4px; border-radius: 4px; background: var(--border); }
        .lp-range-fill { position: absolute; top: 0; bottom: 0; background: var(--accent); border-radius: 4px; }
        .lp-range input[type=range] { position: absolute; left: 0; right: 0; top: 10px; width: 100%; height: 4px; margin: 0; background: none; pointer-events: none; -webkit-appearance: none; appearance: none; }
        .lp-range input[type=range]::-webkit-slider-thumb { -webkit-appearance: none; pointer-events: auto; width: 18px; height: 18px; border-radius: 50%; background: var(--white); border: 3px solid var(--accent); box-shadow: 0 1px 4px rgba(0,0,0,.18); cursor: pointer; }
        .lp-range input[type=range]::-moz-range-thumb { pointer-events: auto; width: 12px; height: 12px; border-radius: 50%; background: var(--white); border: 3px solid var(--accent); cursor: pointer; }
        .lp-range-vals { display: flex; justify-content: center; gap: 6px; margin-top: 14px; font-size: 13px; font-weight: 700; color: var(--text); }
        .lp-apply { display: none; }

        .lp-advisor { background: linear-gradient(160deg, #0f3b36, #0a1f1c); color: #fff; border-radius: 16px; padding: 22px 20px; }
        .lp-advisor-ic { width: 46px; height: 46px; border-radius: 50%; display: grid; place-items: center; border: 1.5px solid rgba(94,234,212,.5); color: #5eead4; margin-bottom: 16px; }
        .lp-advisor-ic svg { width: 22px; height: 22px; }
        .lp-advisor strong { display: block; font-size: 16.5px; font-weight: 800; letter-spacing: -.3px; margin-bottom: 4px; }
        .lp-advisor p { font-size: 13.5px; line-height: 1.5; color: rgba(255,255,255,.72); margin: 0 0 12px; }
        .lp-advisor a { font-size: 13.5px; font-weight: 700; color: #5eead4; display: inline-flex; align-items: center; gap: 6px; }

        /* Liste başlığı + sıralama */
        .lp-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; margin-bottom: 18px; }
        .lp-head h1 { font-size: 30px; font-weight: 800; letter-spacing: -.8px; margin: 0 0 4px; line-height: 1.1; }
        .lp-head h1 span { font-weight: 500; color: var(--text-muted); font-size: 24px; letter-spacing: -.3px; }
        .lp-head p { color: var(--text-meta); font-size: 14px; margin: 0; }
        .lp-sort { display: inline-flex; align-items: center; gap: 6px; padding: 10px 14px; border: 1px solid var(--border); border-radius: 12px; background: var(--white); font-size: 13px; color: var(--text-sec); white-space: nowrap; flex: none; }
        .lp-sort select { appearance: none; -webkit-appearance: none; border: none; background: transparent url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%230f172a' stroke-width='2.4' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E") no-repeat right center; padding: 0 20px 0 0; font: inherit; font-size: 13px; font-weight: 800; color: var(--text); cursor: pointer; }

        /* Kartlar */
        /* 3'lü satır (2026-10-01 kullanıcı isteği); dar pencerede 2'ye düşer */
        .lp-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; }
        .lp-card { position: relative; display: flex; flex-direction: column; background: var(--white); border: 1px solid var(--border); border-radius: 16px; overflow: hidden; transition: transform .2s, box-shadow .2s, border-color .2s; }
        .lp-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-lg); border-color: #cbd5e1; }
        .lp-card-link { display: flex; flex-direction: column; color: inherit; flex: 1; }
        .lp-card-media { position: relative; aspect-ratio: 16 / 9; background: linear-gradient(135deg, #e0f2fe, #f0fdf4); display: grid; place-items: center; font-size: 40px; }
        .lp-card-media img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
        .lp-card-body { padding: 13px 14px 10px; display: flex; flex-direction: column; gap: 4px; flex: 1; }
        .lp-card-title { font-size: 16px; font-weight: 800; letter-spacing: -.3px; line-height: 1.3; margin: 0; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .lp-card-agency { font-size: 13px; color: var(--text-meta); }
        .lp-card-meta { display: flex; flex-wrap: wrap; gap: 4px 14px; margin-top: 4px; font-size: 13px; color: var(--text-sec); }
        .lp-card-meta span { display: inline-flex; align-items: center; gap: 5px; }
        .lp-card-meta svg { width: 14px; height: 14px; color: var(--text-meta); }
        .lp-card-foot { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px 10px; margin-top: auto; padding-top: 12px; }
        .lp-price { display: flex; align-items: baseline; gap: 7px; flex-wrap: wrap; }
        .lp-price strong { font-family: 'Manrope', var(--font); font-size: 21px; font-weight: 800; letter-spacing: -.6px; color: var(--text); }
        .lp-price strong.kampanya { color: var(--green); }
        .lp-price s { font-size: 12.5px; color: var(--text-muted); }
        .lp-price small { font-size: 12.5px; color: var(--text-meta); }
        .lp-cta { display: inline-flex; align-items: center; gap: 6px; padding: 9px 13px; border-radius: 11px; background: var(--accent-light); color: var(--accent-deep); font-size: 13.5px; font-weight: 800; white-space: nowrap; transition: background .15s, color .15s; }
        .lp-cta svg { width: 14px; height: 14px; }
        .lp-card:hover .lp-cta { background: var(--accent-ink); color: #fff; }
        .lp-cmp { display: flex; align-items: center; gap: 7px; padding: 0 14px 12px; font-size: 12.5px; color: var(--text-meta); cursor: pointer; }
        .lp-cmp input { width: 15px; height: 15px; margin: 0; accent-color: var(--accent-ink); }
        .lp-cmp input:checked + span { color: var(--accent-deep); font-weight: 700; }
        .lp-card .m-fav { top: 12px; right: 12px; width: 36px; height: 36px; }
        .lp-card .m-drop-badge { top: 12px; left: 12px; }
        .lp-empty { grid-column: 1 / -1; text-align: center; padding: 56px 20px; background: var(--white); border: 1px dashed #cbd5e1; border-radius: 16px; }
        .lp-empty h3 { font-size: 19px; font-weight: 800; margin: 10px 0 6px; }
        .lp-empty p { color: var(--text-meta); font-size: 14.5px; margin: 0 0 16px; }
        .lp-pager { margin: 28px 0 8px; }

        /* Yola çıkmadan önce (SSS bandı) */
        .lp-before { margin: 40px 0 36px; background: #f1f5f7; border-radius: 22px; padding: 36px 40px; display: grid; grid-template-columns: minmax(0, 5fr) minmax(0, 7fr); gap: 40px; align-items: start; }
        .lp-before-eyebrow { font-size: 12px; font-weight: 800; letter-spacing: .14em; text-transform: uppercase; color: var(--accent-ink); margin-bottom: 10px; }
        .lp-before h2 { font-family: 'Manrope', var(--font); font-size: 32px; font-weight: 800; letter-spacing: -1px; line-height: 1.1; margin: 0 0 10px; text-wrap: balance; }
        .lp-before > div > p { color: var(--text-meta); font-size: 15px; line-height: 1.6; margin: 0 0 18px; }
        .lp-tiles { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
        .lp-tile { display: flex; align-items: center; gap: 12px; padding: 14px 16px; border-radius: 14px; background: var(--accent-light); color: var(--accent-deep); }
        .lp-tile svg { width: 22px; height: 22px; flex: none; }
        .lp-tile small { display: block; font-size: 12px; font-weight: 600; color: var(--accent-ink); margin-bottom: 2px; }
        .lp-tile strong { font-family: 'Manrope', var(--font); font-size: 22px; font-weight: 800; letter-spacing: -.5px; color: var(--text); }
        .lp-faq { background: var(--white); border: 1px solid var(--border); border-radius: 18px; padding: 6px 22px; }
        .lp-faq-label { font-size: 12px; font-weight: 800; letter-spacing: .1em; text-transform: uppercase; color: var(--text-muted); margin: 14px 0 4px; }
        .lp-faq details { border-top: 1px solid var(--border-light); }
        .lp-faq details:first-of-type { border-top: none; }
        .lp-faq summary { list-style: none; display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 17px 0; cursor: pointer; font-size: 15.5px; font-weight: 700; color: var(--text); }
        .lp-faq summary::-webkit-details-marker { display: none; }
        .lp-faq summary i { flex: none; width: 24px; height: 24px; border-radius: 50%; border: 1.5px solid var(--accent); color: var(--accent-ink); display: grid; place-items: center; font-style: normal; font-weight: 800; line-height: 1; transition: transform .2s, background .2s, color .2s; }
        .lp-faq details[open] summary i { transform: rotate(45deg); background: var(--accent-ink); color: #fff; }
        .lp-faq-a { padding: 0 0 18px; color: var(--text-sec); font-size: 14.5px; line-height: 1.7; }

        /* Editoryal / veri-tabanlı SEO blokları (her iki gövdede ortak) */
        .lp-editorial { max-width: 900px; margin: 0 0 40px; }
        .lp-editorial section { margin-bottom: 34px; }
        .lp-editorial h2 { font-size: 21px; font-weight: 800; letter-spacing: -.4px; margin-bottom: 10px; }
        .lp-editorial p { color: var(--text-sec); font-size: 15px; line-height: 1.8; }
        .lp-pills { display: flex; flex-wrap: wrap; gap: 8px; }
        .lp-pill { border: 1px solid var(--border); background: var(--white); border-radius: 999px; padding: 7px 14px; font-size: 13.5px; color: var(--text-sec); }
        .lp-pill strong { color: var(--text); }
        .lp-table-wrap { overflow-x: auto; border: 1px solid var(--border); border-radius: 14px; background: var(--white); }
        .lp-table { width: 100%; border-collapse: collapse; font-size: 14.5px; min-width: 460px; }
        .lp-table th { background: var(--border-light); text-align: left; padding: 11px 14px; font-weight: 700; font-size: 13px; color: var(--text-sec); }
        .lp-table td { padding: 11px 14px; border-top: 1px solid var(--border-light); }
        .lp-table .en { background: var(--green-bg); color: var(--green-text); font-size: 11px; font-weight: 800; padding: 2px 7px; border-radius: 999px; margin-left: 6px; }
        .lp-subcats { display: flex; flex-wrap: wrap; gap: 8px; margin: 18px 0 0; }
        .lp-subcats a { border: 1px solid var(--border); background: var(--white); border-radius: 999px; padding: 8px 16px; font-size: 13.5px; font-weight: 600; color: var(--text-sec); }
        .lp-subcats a:hover { border-color: var(--accent); color: var(--accent-ink); }

        @media (max-width: 1100px) {
            .lp-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media (max-width: 1024px) {
            .lp-main { grid-template-columns: 200px minmax(0, 1fr); }
            .lp-hero-title { font-size: 38px; }
        }

        /* ── Mobil gövde (lpm-): başlık bloğu, yapışkan şerit, kartlar, alttan filtre paneli ── */
        @media (max-width: 768px) {
            .lpm-baslik { background: linear-gradient(135deg, #5eead4 0%, #0d9488 60%, #0f766e 100%); border-radius: 20px; padding: 3px; margin-bottom: 10px; }
            .lpm-baslik-ic { background: var(--white); border-radius: 17px; padding: 16px 16px 14px; display: flex; flex-direction: column; gap: 8px; }
            .lpm-baslik-ust { display: flex; align-items: center; justify-content: space-between; gap: 10px; min-height: 34px; }
            .lpm-etiket { display: inline-flex; align-items: center; min-height: 34px; padding: 0 8px; margin-left: -8px; font-size: 11px; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; color: var(--accent-ink); text-decoration: none; }
            .lpm-ikon { width: 34px; height: 34px; border-radius: 10px; background: var(--accent-light); display: flex; align-items: center; justify-content: center; font-size: 18px; line-height: 1; flex: none; }
            .lpm-ikon svg { width: 18px; height: 18px; color: var(--accent-ink); }
            .lpm-h1 { font-family: 'Manrope', var(--font); font-size: 28px; font-weight: 800; letter-spacing: -.8px; line-height: 1.1; margin: -4px 0 0; }
            .lpm-satir { font-size: 13.5px; color: var(--text-sec); line-height: 1.45; margin: 0; }
            .lpm-rozetler { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 2px; }
            .lpm-rozet { display: inline-flex; align-items: center; gap: 5px; height: 30px; padding: 0 10px; border-radius: 999px; background: #f0f6f4; font-size: 12px; font-weight: 700; color: #0f2421; }
            .lpm-rozet svg { width: 13px; height: 13px; color: var(--accent-ink); flex: none; }
            .lpm-rozet-vurgu { background: var(--accent-light); color: var(--accent-deep); font-weight: 800; }
            .lpm-altkat { display: flex; gap: 8px; overflow-x: auto; scrollbar-width: none; margin: 0 -16px 2px; padding: 2px 16px 6px; }
            .lpm-altkat::-webkit-scrollbar { display: none; }
            .lpm-altkat a { flex: none; display: inline-flex; align-items: center; height: 36px; padding: 0 14px; border-radius: 999px; background: var(--white); border: 1px solid var(--border); font-size: 13px; font-weight: 600; color: var(--text-sec); text-decoration: none; white-space: nowrap; }

            /* Yapışkan şerit */
            .lpm-serit { position: sticky; top: var(--lpm-ust, 61px); z-index: var(--z-sticky-nav); background: rgba(248,250,252,.96); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); margin: 0 -16px; padding: 8px 16px 10px; display: flex; gap: 8px; align-items: center; overflow-x: auto; scrollbar-width: none; -webkit-overflow-scrolling: touch; }
            .lpm-serit::-webkit-scrollbar { display: none; }
            .lpm-filtrele { display: inline-flex; align-items: center; gap: 7px; height: 40px; padding: 0 14px; border-radius: 999px; border: none; background: var(--accent-ink); color: #fff; font-family: var(--font); font-size: 13px; font-weight: 700; flex: none; cursor: pointer; }
            .lpm-filtrele svg { width: 15px; height: 15px; }
            .lpm-sayac { background: #5eead4; color: #0f2421; border-radius: 999px; padding: 1px 7px; font-size: 11px; }
            .lpm-cip { display: inline-flex; align-items: center; gap: 6px; height: 40px; padding: 0 14px; border-radius: 999px; background: var(--white); border: 1px solid var(--border); font-family: var(--font); font-size: 13px; font-weight: 600; color: var(--text-sec); white-space: nowrap; flex: none; text-decoration: none; cursor: pointer; }
            .lpm-cip.on { background: var(--accent-light); border-color: #5eead4; color: var(--accent-deep); font-weight: 700; }
            .lpm-cip svg { width: 12px; height: 12px; flex: none; }
            .lpm-cip-sirala { color: var(--text); }
            .lpm-cip-sirala span { font-weight: 500; }

            /* Kartlar */
            .lpm-liste { display: flex; flex-direction: column; gap: 12px; margin-top: 12px; }
            .lpm-kart { position: relative; background: var(--white); border: 1px solid var(--border); border-radius: 18px; overflow: hidden; }
            .lpm-kart-link { display: flex; flex-direction: column; color: inherit; text-decoration: none; }
            .lpm-kart-gorsel { position: relative; height: 176px; background: linear-gradient(135deg, #e0f2fe, #f0fdf4); display: flex; align-items: center; justify-content: center; font-size: 40px; }
            .lpm-kart-gorsel img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
            .lpm-kart .m-fav { top: 12px; right: 12px; width: 36px; height: 36px; }
            .lpm-kart .m-drop-badge { top: 12px; left: 12px; }
            .lpm-kart-govde { padding: 12px 14px; display: flex; flex-direction: column; gap: 6px; }
            .lpm-kart-baslik { font-size: 16px; font-weight: 800; line-height: 1.3; letter-spacing: -.2px; margin: 0; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
            .lpm-kart-acenta { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
            .lpm-kart-acenta .card-agency { margin: 0; }
            .lpm-puan { display: inline-flex; align-items: center; gap: 3px; font-size: 13px; font-weight: 700; color: var(--text); }
            .lpm-puan svg { width: 12px; height: 12px; }
            .lpm-puan i { font-style: normal; color: var(--text-meta); font-weight: 500; }
            .lpm-kart-meta { display: flex; flex-wrap: wrap; gap: 4px 12px; font-size: 12.5px; color: var(--text-sec); }
            .lpm-kart-meta span { display: inline-flex; align-items: center; gap: 4px; }
            .lpm-kart-meta svg { width: 13px; height: 13px; flex: none; }
            .lpm-kart-alt { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-top: 4px; padding-top: 10px; border-top: 1px solid var(--border-light); }
            .lpm-fiyat { display: flex; align-items: baseline; gap: 6px; flex-wrap: wrap; }
            .lpm-fiyat strong { font-family: 'Manrope', var(--font); font-size: 20px; font-weight: 800; letter-spacing: -.5px; color: var(--text); }
            .lpm-fiyat strong.kampanya { color: var(--green); }
            .lpm-fiyat s { font-size: 12px; color: var(--text-meta); }
            .lpm-fiyat small { font-size: 12px; color: var(--text-meta); }
            .lpm-cta { display: inline-flex; align-items: center; gap: 6px; height: 40px; padding: 0 14px; border-radius: 11px; background: var(--accent-light); color: var(--accent-deep); font-size: 13px; font-weight: 800; white-space: nowrap; flex: none; }
            .lpm-cta svg { width: 13px; height: 13px; }
            .lpm-sayfalama { margin: 20px 0 8px; }
            .lpm-sayfalama nav[role="navigation"] > div { flex-wrap: wrap; justify-content: center; }
            .lpm-gizli { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); clip-path: inset(50%); white-space: nowrap; }
            .lpm-bos { background: var(--white); border: 1px dashed #cbd5e1; border-radius: 16px; padding: 32px 16px; text-align: center; margin-top: 12px; }

            /* Alttan açılan filtre paneli (m-pick kalıbı: 2500/2600) */
            .lpm-ortu { position: fixed; inset: 0; z-index: 2500; background: rgba(4,24,21,.45); opacity: 0; pointer-events: none; transition: opacity .25s ease; }
            .lpm-ortu.open { opacity: 1; pointer-events: auto; }
            .lpm-sheet { position: fixed; left: 0; right: 0; bottom: 0; z-index: 2600; max-height: 86vh; background: var(--white); border-radius: 22px 22px 0 0; box-shadow: 0 -12px 40px rgba(4,24,21,.3); transform: translateY(105%); transition: transform .3s ease; }
            .lpm-sheet.open { transform: translateY(0); }
            .lpm-form { display: flex; flex-direction: column; max-height: 86vh; margin: 0; }
            .lpm-tutamac { width: 36px; height: 4px; border-radius: 3px; background: #cbd5e1; margin: 10px auto 0; flex: none; }
            .lpm-sheet-head { display: flex; align-items: center; justify-content: space-between; padding: 12px 18px 12px 20px; border-bottom: 1px solid #eef2f1; flex: none; }
            .lpm-sheet-head h2 { font-family: 'Manrope', var(--font); font-size: 18px; font-weight: 800; letter-spacing: -.3px; margin: 0; }
            .lpm-temizle { display: inline-flex; align-items: center; min-height: 36px; padding: 0 10px; margin-right: 2px; font-size: 13px; font-weight: 700; color: var(--accent-ink); text-decoration: none; }
            .lpm-kapat { width: 36px; height: 36px; border-radius: 50%; border: 1px solid var(--border); background: var(--white); color: var(--text); font-size: 20px; line-height: 1; cursor: pointer; }
            .lpm-sheet-govde { flex: 1; min-height: 0; overflow-y: auto; padding: 6px 20px 0; -webkit-overflow-scrolling: touch; overscroll-behavior: contain; }
            .lpm-grup { border: none; margin: 0; padding: 12px 0; border-bottom: 1px solid var(--border-light); min-width: 0; }
            .lpm-grup:last-child { border-bottom: none; }
            .lpm-grup legend { font-size: 14px; font-weight: 800; padding: 0; margin-bottom: 10px; color: var(--text); }
            .lpm-cipler { display: flex; flex-wrap: wrap; gap: 8px; }
            .lpm-grup-not { display: block; font-size: 12px; font-weight: 600; color: var(--text-meta); margin-top: 2px; }
            .lpm-fiyat-kutular { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
            .lpm-sayi { display: flex; align-items: center; gap: 8px; height: 46px; padding: 0 12px; border: 1px solid #cbd5e1; border-radius: 12px; background: #f8fafc; }
            .lpm-sayi:focus-within { border-color: var(--accent); background: var(--white); }
            .lpm-sayi-etiket { font-size: 10px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; color: var(--text-meta); flex: none; }
            .lpm-sayi input { flex: 1; min-width: 0; width: 100%; border: none; background: transparent; font-family: var(--font); font-size: 16px; font-weight: 700; color: var(--text); outline: none; -moz-appearance: textfield; appearance: textfield; }
            .lpm-sayi input::-webkit-outer-spin-button, .lpm-sayi input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
            .lpm-sayi-birim { font-size: 13px; font-weight: 700; color: var(--text-meta); flex: none; }
            .lpm-secim { position: relative; }
            .lpm-secim input { position: absolute; opacity: 0; width: 1px; height: 1px; margin: 0; }
            .lpm-secim span { display: inline-flex; align-items: center; gap: 6px; height: 36px; padding: 0 13px; border-radius: 999px; background: var(--white); border: 1px solid var(--border); font-size: 12.5px; font-weight: 600; color: var(--text-sec); cursor: pointer; }
            .lpm-secim span small { color: var(--text-meta); font-size: 11.5px; }
            .lpm-secim input:checked + span { background: var(--accent-light); border-color: #5eead4; color: var(--accent-deep); font-weight: 700; }
            .lpm-secim input:checked + span small { color: var(--accent-ink); }
            .lpm-secim input:focus-visible + span { outline: 2px solid var(--odak-halka); outline-offset: 2px; }
            .lpm-sheet-alt { padding: 12px 16px calc(16px + env(safe-area-inset-bottom)); border-top: 1px solid #eef2f1; flex: none; }
            .lpm-uygula { width: 100%; height: 50px; border: none; border-radius: 14px; background: linear-gradient(135deg, #0d9488, #0c6e63); color: #fff; font-family: 'Manrope', var(--font); font-size: 15px; font-weight: 800; cursor: pointer; }
            body.lpm-kilit { overflow: hidden; }

            /* Liste altı bölümler (fiyat özeti, kaç gün, kalkış ayları, SSS, hakkında):
               her başlık kendi kutucuğunda, çevresi ince neon şerit (kullanıcı isteği, 2026-10-03) */
            .lp-editorial { margin: 16px 0 28px; }
            .lp-editorial section { border: 2px solid transparent; border-radius: 18px; background: linear-gradient(var(--white), var(--white)) padding-box, linear-gradient(135deg, #5eead4 0%, #0d9488 60%, #0f766e 100%) border-box; padding: 16px 16px 14px; margin-bottom: 12px; }
            .lp-editorial section:last-child { margin-bottom: 0; }
            .lp-editorial h2 { font-size: 18px; letter-spacing: -.3px; margin-bottom: 8px; }
            .lp-editorial p { font-size: 14px; line-height: 1.7; }
            .lp-editorial .lp-table-wrap { border-radius: 12px; }
            .lp-mobil-sss details { margin-bottom: 8px; }
        }
    </style>
@endpush

@section('content')
<div class="container lp">
    <div class="lp-crumb">
        @include('partials.breadcrumb', ['items' => $breadcrumb])
    </div>

    {{-- ════════════════════════════════════════════════════════════════════
         MASAÜSTÜ GÖVDE (>768px) — yeni tasarım. Mobilde CSS ile gizli.
         ════════════════════════════════════════════════════════════════════ --}}
    <div class="lp-desk">
    {{-- ── HERO ── Görsel + metinler App\Support\CategoryHero'dan: admin'in
         kategori banner'ı → üst kategori → genel varsayılan → ilk tur görseli.
         H1 BURADA DEĞİL: başlık listede, anahtar kelimeyi taşıyor; hero metni
         editoryal bir slogan. --}}
    <section class="lp-hero" aria-label="{{ $baslik }}">
        @if(!empty($hero['image']))
            {{-- loading=lazy: mobilde gövde gizli, görsel boşuna inmesin; masaüstünde
                 görünür alanda olduğundan yine hemen yüklenir --}}
            <img src="{{ $hero['image'] }}" alt="" class="lp-hero-img" loading="lazy" fetchpriority="high" decoding="async">
        @endif
        <div class="lp-hero-overlay" style="background:{{ $hero['overlay'] }};" aria-hidden="true"></div>
        <div class="lp-hero-body">
            <div class="lp-hero-eyebrow">{{ $hero['eyebrow'] }}</div>
            <p class="lp-hero-title">{{ $hero['title'] }}</p>
            <p class="lp-hero-sub">{{ $hero['subtitle'] }}</p>
            @if($toplam)
                <div class="lp-hero-stats">
                    <span class="lp-stat">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 4 3 6v14l6-2 6 2 6-2V4l-6 2-6-2z"/><path d="M9 4v14M15 6v14"/></svg>
                        {{ $toplam }} tur
                    </span>
                    <span class="lp-stat">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><path d="M16 4.5a3.5 3.5 0 0 1 0 7M21.5 20a6.5 6.5 0 0 0-4.5-6.2"/></svg>
                        {{ $acentaSayisi }} acenta
                    </span>
                    @if($facets['baslangic'])
                        <span class="lp-stat">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.6 13.4 13.4 20.6a2 2 0 0 1-2.8 0L3 13V3h10l7.6 7.6a2 2 0 0 1 0 2.8z"/><circle cx="7.5" cy="7.5" r="1.5"/></svg>
                            En düşük {{ $facets['baslangic'] }}
                        </span>
                    @endif
                </div>
            @endif
        </div>
        @if(!empty($hero['caption']))
            <div class="lp-hero-caption">
                <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a7 7 0 0 0-7 7c0 5.2 7 13 7 13s7-7.8 7-13a7 7 0 0 0-7-7zm0 9.5a2.5 2.5 0 1 1 0-5 2.5 2.5 0 0 1 0 5z"/></svg>
                {{ $hero['caption'] }}
            </div>
        @endif
    </section>

    @if($toplam)
        {{-- ── ARAMA KARTI ── Tek form: kenar çubuğundaki kutular da form="lp-form" ile
             buraya bağlı; üstteki seçimler ile soldaki kutular aynı parametreyi
             (ay[], sure[]) besler, LandingFilter::parse tekilleştirir. --}}
        <form id="lp-form" method="GET" action="{{ $temelUrl }}" class="lp-search" data-lp-form>
            <div class="lp-search-row">
                <label class="lp-field">
                    <span class="lp-field-ic" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg></span>
                    <span class="lp-field-txt">
                        <small>Kalkış noktası</small>
                        <select name="kalkis" data-lp-auto>
                            <option value="">Tümü</option>
                            @foreach($facets['kalkislar'] as $k)
                                <option value="{{ $k['ad'] }}" @selected($filtre['kalkis'] === $k['ad'])>{{ $k['ad'] }} ({{ $k['adet'] }})</option>
                            @endforeach
                        </select>
                    </span>
                </label>
                <label class="lp-field">
                    <span class="lp-field-ic" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="3"/><path d="M16 2v4M8 2v4M3 10h18"/></svg></span>
                    <span class="lp-field-txt">
                        <small>Seyahat dönemi</small>
                        <select name="ay[]" data-lp-auto data-lp-mirror="ay">
                            <option value="">{{ count($filtre['ay']) > 1 ? 'Birden fazla ay' : 'Tarih seç' }}</option>
                            @foreach($facets['aylar'] as $a)
                                <option value="{{ $a['ay'] }}" @selected(count($filtre['ay']) === 1 && $filtre['ay'][0] === $a['ay'])>{{ $a['ad'] }} ({{ $a['adet'] }})</option>
                            @endforeach
                        </select>
                    </span>
                </label>
                <label class="lp-field">
                    <span class="lp-field-ic" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></span>
                    <span class="lp-field-txt">
                        <small>Tur süresi</small>
                        <select name="sure[]" data-lp-auto data-lp-mirror="sure">
                            <option value="">{{ count($filtre['sure']) > 1 ? 'Birden fazla süre' : 'Tümü' }}</option>
                            @foreach($facets['sureler'] as $s)
                                <option value="{{ $s['gun'] }}" @selected(count($filtre['sure']) === 1 && $filtre['sure'][0] === $s['gun'])>{{ $s['etiket'] }} ({{ $s['adet'] }})</option>
                            @endforeach
                        </select>
                    </span>
                </label>
                <button type="submit" class="btn btn-primary lp-search-btn">
                    Turları keşfet
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                </button>
            </div>

            {{-- Hızlı çipler: en yaygın süreler + aylar; tıklama parametreyi aç/kapat --}}
            @if(count($facets['sureler']) || count($facets['aylar']))
                <div class="lp-chips" aria-label="Hızlı filtreler">
                    <a href="{{ $temelUrl }}" class="lp-chip {{ $filtreAktif ? '' : 'on' }}">Tüm turlar</a>
                    @foreach(array_slice($facets['sureler'], 0, 4) as $s)
                        @php [$secili, $url] = $cip('sure', $s['gun']); @endphp
                        <a href="{{ $url }}" class="lp-chip {{ $secili ? 'on' : '' }}">{{ $s['etiket'] }}</a>
                    @endforeach
                    @foreach(array_slice($facets['aylar'], 0, 4) as $a)
                        @php [$secili, $url] = $cip('ay', $a['ay']); @endphp
                        <a href="{{ $url }}" class="lp-chip {{ $secili ? 'on' : '' }}">{{ $a['ad'] }}</a>
                    @endforeach
                </div>
            @endif
        </form>

        {{-- ── ANA DÜZEN: filtre kenar çubuğu + liste ── --}}
        <div class="lp-main">
            <aside class="lp-side" aria-label="Filtreler">
                <div class="lp-filt">
                    <div class="lp-filt-head">
                        <h2>Filtrele</h2>
                        <a href="{{ $temelUrl }}" class="lp-clear">Temizle</a>
                    </div>

                    @if($fiyatKaydirici)
                        <details class="lp-fg" open>
                            <summary>Fiyat aralığı <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg></summary>
                            <div class="lp-range" data-lp-range data-min="{{ $fMin }}" data-max="{{ $fMax }}">
                                <div class="lp-range-track"><div class="lp-range-fill" data-lp-fill></div></div>
                                <input type="range" name="min_fiyat" form="lp-form" min="{{ $fMin }}" max="{{ $fMax }}" step="{{ $fAdim }}" value="{{ $fSecMin }}" aria-label="En düşük fiyat (₺)" data-lp-range-min>
                                <input type="range" name="max_fiyat" form="lp-form" min="{{ $fMin }}" max="{{ $fMax }}" step="{{ $fAdim }}" value="{{ $fSecMax }}" aria-label="En yüksek fiyat (₺)" data-lp-range-max>
                                <div class="lp-range-vals"><span data-lp-out="min">{{ $tl($fSecMin) }}</span> — <span data-lp-out="max">{{ $tl($fSecMax) }}</span></div>
                            </div>
                        </details>
                    @endif

                    @if(count($facets['sureler']))
                        <details class="lp-fg" open>
                            <summary>Tur süresi <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg></summary>
                            <div class="lp-fg-body">
                                @foreach($facets['sureler'] as $s)
                                    <label class="lp-check">
                                        <input type="checkbox" name="sure[]" value="{{ $s['gun'] }}" form="lp-form" data-lp-auto data-lp-group="sure" @checked(in_array($s['gun'], $filtre['sure'], true))>
                                        {{ $s['etiket'] }} <small>{{ $s['adet'] }}</small>
                                    </label>
                                @endforeach
                            </div>
                        </details>
                    @endif

                    @if(count($facets['aylar']))
                        <details class="lp-fg" open>
                            <summary>Kalkış ayı <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg></summary>
                            <div class="lp-fg-body">
                                @foreach($facets['aylar'] as $a)
                                    <label class="lp-check">
                                        <input type="checkbox" name="ay[]" value="{{ $a['ay'] }}" form="lp-form" data-lp-auto data-lp-group="ay" @checked(in_array($a['ay'], $filtre['ay'], true))>
                                        {{ $a['ad'] }} <small>{{ $a['adet'] }}</small>
                                    </label>
                                @endforeach
                            </div>
                        </details>
                    @endif

                    @if(count($facets['acentalar']))
                        <details class="lp-fg" open>
                            <summary>Acenta <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg></summary>
                            <div class="lp-fg-body">
                                @foreach($facets['acentalar'] as $ac)
                                    <label class="lp-check">
                                        <input type="checkbox" name="acenta[]" value="{{ $ac['id'] }}" form="lp-form" data-lp-auto @checked(in_array($ac['id'], $filtre['acenta'], true))>
                                        {{ $ac['ad'] }} <small>{{ $ac['adet'] }}</small>
                                    </label>
                                @endforeach
                            </div>
                        </details>
                    @endif

                    {{-- JS kapalıysa kutular kendiliğinden gönderemez; noscript düğme --}}
                    <noscript><button type="submit" form="lp-form" class="btn btn-primary" style="width:100%;margin:8px 0 10px;">Filtreleri uygula</button></noscript>
                </div>

                <div class="lp-advisor">
                    <div class="lp-advisor-ic" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9.5"/><path d="m15.5 8.5-2.2 5.8-4.8 1.2 2.2-5.8z"/></svg>
                    </div>
                    <strong>Kararsız mı kaldın?</strong>
                    <p>Sana uygun rotayı birlikte bulalım.</p>
                    <a href="{{ route('discovery.index') }}" data-lp-danisman>
                        Tur danışmanına sor
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                    </a>
                </div>
            </aside>

            <div class="lp-content">
                <div class="lp-head">
                    <div>
                        {{-- H1 = tam anahtar kelime + canlı envanter sayısı (tatilbudur kalıbı:
                             bu biçimle üç kategori sorgusunda birden 1. sırada) --}}
                        <h1>{{ $baslik }} <span>({{ $sayi }} tur)</span></h1>
                        <p>Rotaları incele, fiyatları karşılaştır. · {{ $acentaSayisi }} acentanın fiyatı karşılaştırmalı</p>
                    </div>
                    <label class="lp-sort">
                        Sırala:
                        <select name="sirala" form="lp-form" data-lp-auto aria-label="Sıralama">
                            @foreach(LandingFilter::SORTS as $anahtar => $etiket)
                                <option value="{{ $anahtar }}" @selected($filtre['sirala'] === $anahtar)>{{ $etiket }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>

                <div class="lp-grid">
                    @forelse($tours as $tour)
                        @php
                            $campaign = $tour->campaigns->first(); // controller'da aktif pencereyle eager yüklendi
                            $mIndirim = ($campaign && $tour->price > 0 && $campaign->discount_price < $tour->price)
                                ? (int) round((1 - $campaign->discount_price / $tour->price) * 100)
                                : null;
                            $favori = in_array($tour->id, $favIds, true);
                        @endphp
                        <article class="lp-card">
                            <a href="{{ route('tours.show', $tour) }}" class="lp-card-link">
                                <div class="lp-card-media">
                                    @if($tour->image)
                                        <img src="{{ $tour->image }}" alt="{{ $tour->title }}" loading="lazy" style="view-transition-name: tour-{{ $tour->id }};">
                                    @else
                                        <span aria-hidden="true">🏖️</span>
                                    @endif
                                    @if($mIndirim)
                                        <span class="m-drop-badge">%{{ $mIndirim }} İNDİRİM</span>
                                    @endif
                                </div>
                                <div class="lp-card-body">
                                    <h3 class="lp-card-title">{{ $tour->title }}</h3>
                                    <div class="lp-card-agency">{{ $tour->agency->name ?? '' }}</div>
                                    <div class="lp-card-meta">
                                        @if($tour->duration_label)
                                            <span>
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                                                {{ $tour->duration_label }}
                                            </span>
                                        @endif
                                        @if($tour->transport_short_label)
                                            <span>
                                                @if($tour->transport_type === 'ucak')
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 14l8-2 4-8 2 1-2 8 6 3-1 2-7-1-3 4-2-1 1-5z"/></svg>
                                                @else
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="3" width="16" height="15" rx="3"/><path d="M4 10h16M8 18v2M16 18v2"/></svg>
                                                @endif
                                                {{ $tour->transport_short_label }}
                                            </span>
                                        @endif
                                    </div>
                                    <div class="lp-card-foot">
                                        <div class="lp-price">
                                            @if($campaign)
                                                <strong class="kampanya">{{ $campaign->formatted_discount_price }}</strong>
                                                <s>{{ $tour->formatted_price }}</s>
                                            @else
                                                <strong>{{ $tour->formatted_price }}</strong>
                                            @endif
                                            <small>kişi başı</small>
                                        </div>
                                        <span class="lp-cta">
                                            Turu incele
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                                        </span>
                                    </div>
                                </div>
                            </a>
                            <label class="lp-cmp">
                                <input type="checkbox" data-lp-cmp="{{ $tour->id }}" aria-label="{{ $tour->title }} turunu karşılaştırmaya ekle">
                                <span>Karşılaştır</span>
                            </label>
                            <button type="button" class="m-fav {{ $favori ? 'on' : '' }}" data-tour="{{ $tour->id }}"
                                aria-label="Favorilere ekle" aria-pressed="{{ $favori ? 'true' : 'false' }}">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.8 5.6a5.2 5.2 0 0 0-7.4 0L12 7l-1.4-1.4a5.2 5.2 0 1 0-7.4 7.4L12 21.5l8.8-8.5a5.2 5.2 0 0 0 0-7.4z"/></svg>
                            </button>
                        </article>
                    @empty
                        <div class="lp-empty">
                            <div style="font-size:40px;opacity:.6;">🧭</div>
                            <h3>Bu filtrelerle tur bulunamadı</h3>
                            <p>Seçimlerden birini kaldırınca liste yeniden dolacak.</p>
                            <a href="{{ $temelUrl }}" class="btn btn-primary">Filtreleri temizle</a>
                        </div>
                    @endforelse
                </div>

                @if($tours->hasPages())
                    <div class="lp-pager">{{ $tours->links() }}</div>
                @endif
            </div>
        </div>
    @else
        {{-- Envanteri biten sayfa KAPATILMAZ (gruppal kalıbı): adres, başlık ve
             iç linkler yerinde kalır; yalnız liste yerine yönlendirme gösterilir. --}}
        <div class="lp-head" style="margin-top:28px;">
            <div>
                <h1>{{ $baslik }}</h1>
            </div>
        </div>
        <div class="card" style="padding:40px;text-align:center;margin-bottom:32px;">
            <div style="font-size:40px;margin-bottom:12px;">🗓️</div>
            <p style="font-weight:600;margin-bottom:6px;">Şu anda bu başlıkta yayında tur yok.</p>
            <p style="color:var(--text-muted);font-size:14px;margin-bottom:20px;">
                Yeni turlar eklendiğinde burada listelenecek.
            </p>
            <a href="{{ route('tours.index') }}" class="btn btn-primary">Tüm turlara göz at</a>
        </div>
    @endif

    {{-- Alt kategori kırılımı: iç link ağı. Rakiplerde kategori sayfası başına
         100–470 benzersiz iç link var; kırılım eksenleri bunun omurgası. --}}
    @if($altKategoriler->count())
        <nav class="lp-subcats" aria-label="Alt kategoriler">
            @foreach($altKategoriler as $alt)
                <a href="{{ LandingSlug::urlForCategory($alt) }}">{{ $alt->name }}</a>
            @endforeach
        </nav>
    @endif

    {{-- ── YOLA ÇIKMADAN ÖNCE: SSS + iki özet kutu ──
         Görünür SSS + FAQPage şeması aynı kaynaktan (LandingStats::faq); Google
         şemanın sayfada görünür karşılığını şart koşuyor. --}}
    @if($landingFaq)
        <section class="lp-before" aria-labelledby="landing-sss">
            <div>
                <div class="lp-before-eyebrow">Yola çıkmadan önce</div>
                <h2>{{ $stem }} hakkında merak ettiklerin</h2>
                <p>Fiyatlar, tur süreleri ve seyahat dönemleri hakkında bilmen gerekenler.</p>
                <div class="lp-tiles">
                    @if($facets['baslangic'])
                        <div class="lp-tile">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.6 13.4 13.4 20.6a2 2 0 0 1-2.8 0L3 13V3h10l7.6 7.6a2 2 0 0 1 0 2.8z"/><circle cx="7.5" cy="7.5" r="1.5"/></svg>
                            <div><small>Başlangıç fiyatı</small><strong>{{ $facets['baslangic'] }}</strong></div>
                        </div>
                    @endif
                    @if($facets['enUzun'])
                        <div class="lp-tile">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="3"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                            <div><small>En uzun tur</small><strong>{{ $facets['enUzun'] }}</strong></div>
                        </div>
                    @endif
                </div>
            </div>
            <div class="lp-faq">
                <h3 id="landing-sss" class="lp-faq-label">Sıkça Sorulan Sorular</h3>
                @foreach($landingFaq['mainEntity'] as $soru)
                    <details>
                        <summary>{{ $soru['name'] }} <i aria-hidden="true">+</i></summary>
                        <div class="lp-faq-a">{{ $soru['acceptedAnswer']['text'] }}</div>
                    </details>
                @endforeach
            </div>
        </section>
    @endif
    </div>{{-- /.lp-desk --}}

    {{-- ════════════════════════════════════════════════════════════════════
         MOBİL GÖVDE (≤768px) — ESKİ sade görünüm, olduğu gibi. Mobil ayrıca
         tasarlanacak; o güne kadar telefonda yeni düzen gösterilmez. Filtre,
         hero ve SSS bandı yok; liste filtreden etkilenmiş hâliyle basılır.
         ════════════════════════════════════════════════════════════════════ --}}
    <div class="lp-mobil">
        @php
            // Üst kategori etiketi yalnız alt kategori sayfasında (destinasyon/üst kategoride yok)
            $ustKategori = $model instanceof \App\Models\Category ? $model->parent : null;
            // Kısa satır: admin banner'ına alt başlık girildiyse o; yoksa karşılaştırma vaadi
            $kisaSatir = $hero['banner']?->subtitle
                ?: ($toplam ? $acentaSayisi.' acentanın fiyatı karşılaştırmalı' : 'Yeni turlar eklendiğinde burada listelenecek.');
            $siralaEtiket = LandingFilter::SORTS[$filtre['sirala']] ?? 'Önerilen';
        @endphp

        {{-- 1. Başlık bloğu: degrade çerçeve, üst kategori etiketi + ikon, H1, kısa satır, rozetler --}}
        <div class="lpm-baslik">
            <div class="lpm-baslik-ic">
                <div class="lpm-baslik-ust">
                    @if($ustKategori)
                        <a href="{{ LandingSlug::urlForCategory($ustKategori) }}" class="lpm-etiket">{{ $ustKategori->name }}</a>
                    @else
                        <span></span>
                    @endif
                    <span class="lpm-ikon" aria-hidden="true">
                        @if(!empty($model->icon))
                            {{ $model->icon }}
                        @else
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9.5"/><path d="m15.5 8.5-2.2 5.8-4.8 1.2 2.2-5.8z"/></svg>
                        @endif
                    </span>
                </div>
                <h1 class="lpm-h1">{{ $baslik }}</h1>
                <p class="lpm-satir">{{ $kisaSatir }}</p>
                @if($toplam)
                    <div class="lpm-rozetler">
                        <span class="lpm-rozet"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 4 3 6v14l6-2 6 2 6-2V4l-6 2-6-2z"/><path d="M9 4v14M15 6v14"/></svg>{{ $toplam }} tur</span>
                        <span class="lpm-rozet"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><path d="M16 4.5a3.5 3.5 0 0 1 0 7M21.5 20a6.5 6.5 0 0 0-4.5-6.2"/></svg>{{ $acentaSayisi }} acenta</span>
                        @if($facets['baslangic'])
                            <span class="lpm-rozet lpm-rozet-vurgu"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.6 13.4 13.4 20.6a2 2 0 0 1-2.8 0L3 13V3h10l7.6 7.6a2 2 0 0 1 0 2.8z"/><circle cx="7.5" cy="7.5" r="1.5"/></svg>En düşük {{ $facets['baslangic'] }}</span>
                        @endif
                    </div>
                @endif
            </div>
        </div>

        {{-- Alt kategori kırılımı (iç link ağı): yatay kaydırmalı çipler --}}
        @if($altKategoriler->count())
            <nav class="lpm-altkat" aria-label="Alt kategoriler">
                @foreach($altKategoriler as $alt)
                    <a href="{{ LandingSlug::urlForCategory($alt) }}">{{ $alt->name }}</a>
                @endforeach
            </nav>
        @endif

        @if($toplam)
            {{-- 2. Yapışkan filtre şeridi: Filtrele (panel) + Sırala (panel) + hızlı çipler (bağlantı) --}}
            <div class="lpm-serit" id="lpm-serit">
                <button type="button" class="lpm-filtrele" data-lpm-ac aria-haspopup="dialog" aria-controls="lpm-sheet" aria-expanded="false">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 5h18M6 12h12M10 19h4"/></svg>
                    Filtrele{{ '' }}@if($filtreSayisi) <span class="lpm-sayac">{{ $filtreSayisi }}</span>@endif
                </button>
                <button type="button" class="lpm-cip lpm-cip-sirala" data-lpm-ac="sirala" aria-haspopup="dialog" aria-controls="lpm-sheet" aria-expanded="false">
                    Sırala: <span>{{ $siralaEtiket }}</span>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                </button>
                {{-- Süre çipleri bilerek yok: gece sayısı panelde elle girilir (kullanıcı kararı) --}}
                @if($filtre['acenta'] !== [])
                    <a href="{{ $adres(['acenta' => []]) }}" class="lpm-cip on">Acenta seçimi <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg></a>
                @endif
                @if($filtre['gece'] !== null)
                    <a href="{{ $adres(['gece' => null]) }}" class="lpm-cip on">{{ $filtre['gece'] }} gece <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg></a>
                @endif
                @foreach(array_slice($facets['aylar'], 0, 3) as $a)
                    @php [$secili, $url] = $cip('ay', $a['ay']); @endphp
                    <a href="{{ $url }}" class="lpm-cip {{ $secili ? 'on' : '' }}">{{ $a['ad'] }}@if($secili) <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>@endif</a>
                @endforeach
            </div>

            {{-- 3. Tur kartları: tam genişlik --}}
            @if($sayi)
                <div class="lpm-liste">
                    @foreach($tours as $tour)
                        @php
                            $campaign = $tour->campaigns->first(); // controller'da aktif pencereyle eager yüklendi
                            $mIndirim = ($campaign && $tour->price > 0 && $campaign->discount_price < $tour->price)
                                ? (int) round((1 - $campaign->discount_price / $tour->price) * 100)
                                : null;
                            $favori = in_array($tour->id, $favIds, true);
                        @endphp
                        <article class="lpm-kart">
                            <a href="{{ route('tours.show', $tour) }}" class="lpm-kart-link">
                                <div class="lpm-kart-gorsel">
                                    @if($tour->image)
                                        {{-- İlk kart mobilde LCP adayı: lazy değil, öncelikli --}}
                                        <img src="{{ $tour->image }}" alt="{{ $tour->title }}" @if($loop->first) loading="eager" fetchpriority="high" decoding="async" @else loading="lazy" @endif>
                                    @else
                                        <span aria-hidden="true">🏖️</span>
                                    @endif
                                    @if($mIndirim)
                                        <span class="m-drop-badge">%{{ $mIndirim }} İNDİRİM</span>
                                    @endif
                                </div>
                                <div class="lpm-kart-govde">
                                    <h3 class="lpm-kart-baslik">{{ $tour->title }}</h3>
                                    <div class="lpm-kart-acenta">
                                        @include('partials.tour_card_agency', ['tour' => $tour])
                                        @if(($tour->reviews_count ?? 0) > 0)
                                            <span class="lpm-puan">
                                                <svg viewBox="0 0 24 24" fill="#f59e0b" aria-hidden="true"><path d="m12 3.6 2.6 5.4 5.9.8-4.3 4.2 1 5.9-5.2-2.8-5.2 2.8 1-5.9L3.5 9.8l5.9-.8z"/></svg>
                                                {{ number_format((float) $tour->reviews_avg_rating, 1, ',', '') }} <i>({{ $tour->reviews_count }})</i>
                                            </span>
                                        @endif
                                    </div>
                                    <div class="lpm-kart-meta">
                                        @if($tour->duration_label)
                                            <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>{{ $tour->duration_label }}</span>
                                        @endif
                                        @if($tour->transport_short_label)
                                            <span>
                                                @if($tour->transport_type === 'ucak')
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 14l8-2 4-8 2 1-2 8 6 3-1 2-7-1-3 4-2-1 1-5z"/></svg>
                                                @else
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="3" width="16" height="15" rx="3"/><path d="M4 10h16M8 18v2M16 18v2"/></svg>
                                                @endif
                                                {{ $tour->transport_short_label }}
                                            </span>
                                        @endif
                                        @if($tour->departure_label)
                                            <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>{{ $tour->departure_label }}</span>
                                        @endif
                                    </div>
                                    <div class="lpm-kart-alt">
                                        <div class="lpm-fiyat">
                                            @if($campaign)
                                                <strong class="kampanya">{{ $campaign->formatted_discount_price }}</strong>
                                                <s>{{ $tour->formatted_price }}</s>
                                            @else
                                                <strong>{{ $tour->formatted_price }}</strong>
                                            @endif
                                            <small>kişi başı</small>
                                        </div>
                                        <span class="lpm-cta">Turu incele <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg></span>
                                    </div>
                                </div>
                            </a>
                            <button type="button" class="m-fav {{ $favori ? 'on' : '' }}" data-tour="{{ $tour->id }}"
                                aria-label="Favorilere ekle" aria-pressed="{{ $favori ? 'true' : 'false' }}">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.8 5.6a5.2 5.2 0 0 0-7.4 0L12 7l-1.4-1.4a5.2 5.2 0 1 0-7.4 7.4L12 21.5l8.8-8.5a5.2 5.2 0 0 0 0-7.4z"/></svg>
                            </button>
                        </article>
                    @endforeach
                </div>
                @if($tours->hasPages())
                    <div class="lpm-sayfalama">{{ $tours->links() }}</div>
                @endif
            @else
                <div class="lpm-bos">
                    <div style="font-size:36px;opacity:.6;">🧭</div>
                    <p style="font-weight:700;margin:8px 0 4px;">Bu filtrelerle tur bulunamadı</p>
                    <p style="color:var(--text-meta);font-size:14px;margin:0 0 14px;">Seçimlerden birini kaldırınca liste yeniden dolacak.</p>
                    <a href="{{ $temelUrl }}" class="btn btn-primary">Filtreleri temizle</a>
                </div>
            @endif

            {{-- 4. Alttan açılan filtre paneli: KENDİ formu (masaüstü formu gizli ama DOM'da; onun
                 alanları gönderilmesin). Acenta filtresi bilerek yok (kullanıcı kararı). --}}
            <template id="lpm-sheet-tpl">
            <div class="lpm-ortu" data-lpm-kapat></div>
            <div class="lpm-sheet" id="lpm-sheet" role="dialog" aria-modal="true" aria-labelledby="lpm-sheet-baslik" aria-hidden="true" inert>
                <form method="GET" action="{{ $temelUrl }}" id="lpm-form" class="lpm-form" data-lpm-form data-lpm-sayac-url="{{ $temelUrl }}">
                    {{-- Mobilde acenta arayüzü bilerek yok; adresten gelen seçim gönderimde ve canlı sayaçta korunur --}}
                    @foreach($filtre['acenta'] as $acentaId)
                        <input type="hidden" name="acenta[]" value="{{ $acentaId }}">
                    @endforeach
                    <div class="lpm-tutamac" aria-hidden="true"></div>
                    <div class="lpm-sheet-head">
                        <h2 id="lpm-sheet-baslik">Filtrele</h2>
                        <div style="display:flex;align-items:center;">
                            <a href="{{ $temelUrl }}" class="lpm-temizle">Temizle</a>
                            <button type="button" class="lpm-kapat" data-lpm-kapat aria-label="Filtreleri kapat">×</button>
                        </div>
                    </div>
                    <div class="lpm-sheet-govde">
                        <fieldset class="lpm-grup" id="lpm-grup-sirala">
                            <legend>Sırala</legend>
                            <div class="lpm-cipler">
                                @foreach(LandingFilter::SORTS as $anahtar => $etiket)
                                    <label class="lpm-secim"><input type="radio" name="sirala" value="{{ $anahtar }}" @checked($filtre['sirala'] === $anahtar)><span>{{ $etiket }}</span></label>
                                @endforeach
                            </div>
                        </fieldset>

                        @if($fiyatKaydirici)
                            {{-- Elle girilen min / max (kullanıcı kararı: kaydırıcı değil, sayı). Boş = sınır yok. --}}
                            <fieldset class="lpm-grup">
                                <legend>Fiyat aralığı <small class="lpm-grup-not">bu kategoride {{ $tl($fMin) }} – {{ $tl($fMax) }}</small></legend>
                                <div class="lpm-fiyat-kutular">
                                    <label class="lpm-sayi">
                                        <span class="lpm-sayi-etiket">Min</span>
                                        <input type="number" name="min_fiyat" inputmode="numeric" min="0" step="1" placeholder="{{ $fMin }}" value="{{ $filtre['min_fiyat'] !== null ? (int) $filtre['min_fiyat'] : '' }}" aria-label="En düşük fiyat (₺)">
                                        <span class="lpm-sayi-birim">₺</span>
                                    </label>
                                    <label class="lpm-sayi">
                                        <span class="lpm-sayi-etiket">Max</span>
                                        <input type="number" name="max_fiyat" inputmode="numeric" min="0" step="1" placeholder="{{ $fMax }}" value="{{ $filtre['max_fiyat'] !== null ? (int) $filtre['max_fiyat'] : '' }}" aria-label="En yüksek fiyat (₺)">
                                        <span class="lpm-sayi-birim">₺</span>
                                    </label>
                                </div>
                            </fieldset>
                        @endif

                        {{-- Tur süresi: hazır seçenek değil, kullanıcının yazdığı gece sayısı (kullanıcı kararı) --}}
                        <fieldset class="lpm-grup">
                            <legend>Tur süresi <small class="lpm-grup-not">kaç gece kalmak istiyorsun?</small></legend>
                            <div class="lpm-fiyat-kutular">
                                <label class="lpm-sayi">
                                    <span class="lpm-sayi-etiket">Gece</span>
                                    <input type="number" name="gece" inputmode="numeric" min="0" max="365" step="1" placeholder="Örn. 4" value="{{ $filtre['gece'] ?? '' }}" aria-label="Gece sayısı">
                                    <span class="lpm-sayi-birim">gece</span>
                                </label>
                            </div>
                        </fieldset>

                        @if(count($facets['aylar']))
                            <fieldset class="lpm-grup">
                                <legend>Kalkış ayı</legend>
                                <div class="lpm-cipler">
                                    @foreach($facets['aylar'] as $a)
                                        <label class="lpm-secim"><input type="checkbox" name="ay[]" value="{{ $a['ay'] }}" @checked(in_array($a['ay'], $filtre['ay'], true))><span>{{ $a['ad'] }} <small>{{ $a['adet'] }}</small></span></label>
                                    @endforeach
                                </div>
                            </fieldset>
                        @endif

                        @if(count($facets['kalkislar']))
                            <fieldset class="lpm-grup">
                                <legend>Kalkış noktası</legend>
                                <div class="lpm-cipler">
                                    <label class="lpm-secim"><input type="radio" name="kalkis" value="" @checked($filtre['kalkis'] === null)><span>Tümü</span></label>
                                    @foreach($facets['kalkislar'] as $k)
                                        <label class="lpm-secim"><input type="radio" name="kalkis" value="{{ $k['ad'] }}" @checked($filtre['kalkis'] === $k['ad'])><span>{{ $k['ad'] }} <small>{{ $k['adet'] }}</small></span></label>
                                    @endforeach
                                </div>
                            </fieldset>
                        @endif
                    </div>
                    <div class="lpm-sheet-alt">
                        <button type="submit" class="lpm-uygula" data-lpm-uygula data-sablon=":n turu göster">{{ $sayi > 0 ? $sayi.' turu göster' : 'Bu filtrelerle tur yok' }}</button>
                        <span class="lpm-gizli" data-lpm-duyuru role="status" aria-live="polite" aria-atomic="true"></span>
                    </div>
                </form>
            </div>
            </template>
        @else
            <div class="card" style="padding:40px;text-align:center;margin:12px 0 32px;">
                <div style="font-size:40px;margin-bottom:12px;">🗓️</div>
                <p style="font-weight:600;margin-bottom:6px;">Şu anda bu başlıkta yayında tur yok.</p>
                <p style="color:var(--text-muted);font-size:14px;margin-bottom:20px;">
                    Yeni turlar eklendiğinde burada listelenecek.
                </p>
                <a href="{{ route('tours.index') }}" class="btn btn-primary">Tüm turlara göz at</a>
            </div>
        @endif
    </div>{{-- /.lp-mobil --}}

    {{-- Metin blokları LİSTENİN ALTINDA (her iki gövdede ortak): tatilsepeti
         (919 kelime, offset 661k/712k) ve MNG (541 kelime, en dip) aynı
         yerleşimi kullanıyor. Listenin üstünü metinle tıkamak kullanıcıyı
         üründen uzaklaştırıyor.

         Buradaki her rakam canlı envanterden geliyor — bayatlamaz, uydurmaz,
         maliyeti yoktur (bkz. App\Support\LandingStats). --}}
    @if($stats['var'] ?? false)
        <div class="lp-editorial">
            @if($stats['fiyat'])
                <section>
                    {{-- "Kapadokya Turları Fiyatları" değil "Kapadokya Tur Fiyatları":
                         rakiplerin tamamı bu kalıbı kullanıyor ve arama hacmi burada. --}}
                    <h2>{{ $stem }} Tur Fiyatları</h2>
                    <p>
                        Şu anda listelenen {{ $stats['fiyat']['adet'] }} turun fiyatı
                        <strong>{{ $tl($stats['fiyat']['min']) }}</strong> ile
                        <strong>{{ $tl($stats['fiyat']['max']) }}</strong> arasında değişiyor.
                        Ortanca fiyat <strong>{{ $tl($stats['fiyat']['medyan']) }}</strong>.
                        Fiyatlar acentaya, kalkış tarihine ve konaklama tipine göre farklılaşır.
                    </p>
                </section>
            @endif

            {{-- ⭐ SAYFANIN RAKİPTE OLMAYAN KISMI.
                 Ölçüm: MNG kendi sayfasında MNG adını 40+ kez, rakip adını 0 kez;
                 Jolly 280+ kez kendi adını, 0 kez rakip adını yazıyor. Tek acentalı
                 bir site bu tabloyu yapısal olarak üretemez. --}}
            @if(count($stats['acentalar']) > 1)
                <section>
                    <h2>{{ $baslik }} Acenta Fiyat Karşılaştırması</h2>
                    <p style="font-size:14px;color:var(--text-muted);margin-bottom:14px;">
                        Aynı başlıkta {{ count($stats['acentalar']) }} acentanın fiyatı yan yana.
                    </p>
                    <div class="lp-table-wrap">
                        <table class="lp-table">
                            <thead>
                                <tr>
                                    <th>Acenta</th>
                                    <th>Tur</th>
                                    <th>En düşük</th>
                                    <th>En yüksek</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($stats['acentalar'] as $i => $satir)
                                    <tr>
                                        <td style="font-weight:600;">
                                            <a href="{{ route('agencies.show', $satir['acenta']) }}" style="color:var(--text);">{{ $satir['acenta']->name }}</a>
                                            @if($i === 0)<span class="en">EN UYGUN</span>@endif
                                        </td>
                                        <td style="color:var(--text-sec);">{{ $satir['turSayisi'] }}</td>
                                        <td style="font-weight:700;color:var(--accent-ink);">{{ $tl($satir['enDusuk']) }}</td>
                                        <td style="color:var(--text-sec);">{{ $tl($satir['enYuksek']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif

            {{-- Şehir bilgisi: mevcut DestinationProfile'dan (55 şehir için zaten
                 üretilmiş). "Nasıl bir yer", "ne zaman gidilir", "kimler için
                 uygun" — rakip H2 iskeletinin veriden gelebilen kısmı. --}}
            @if($profil)
                @foreach($profil['bolumler'] as $bolum)
                    <section>
                        <h2>{{ $bolum['baslik'] }}</h2>
                        <p>{{ $bolum['metin'] }}</p>
                    </section>
                @endforeach
            @endif

            @if(count($stats['sureler']))
                <section>
                    <h2>{{ $baslik }} Kaç Gün Sürüyor?</h2>
                    <div class="lp-pills">
                        @foreach($stats['sureler'] as $sure)
                            <span class="lp-pill">{{ $sure['etiket'] }} <strong>({{ $sure['adet'] }})</strong></span>
                        @endforeach
                    </div>
                </section>
            @endif

            @if(count($stats['aylar']))
                <section>
                    <h2>{{ $baslik }} İçin Kalkış Ayları</h2>
                    <div class="lp-pills">
                        @foreach($stats['aylar'] as $ay)
                            <span class="lp-pill">{{ $ay['ay'] }} <strong>({{ $ay['adet'] }})</strong></span>
                        @endforeach
                    </div>
                </section>
            @endif

            {{-- SSS mobilde eski yerinde (masaüstünde yukarıdaki bantta; aynı kaynak) --}}
            @if($landingFaq)
                <section class="lp-mobil-sss" aria-labelledby="landing-sss-mobil">
                    <h2 id="landing-sss-mobil">Sıkça Sorulan Sorular</h2>
                    @foreach($landingFaq['mainEntity'] as $soru)
                        <details style="background:var(--white);border:1px solid var(--border);border-radius:var(--radius);margin-bottom:10px;">
                            <summary style="cursor:pointer;padding:14px 16px;font-weight:600;font-size:15px;list-style:none;">{{ $soru['name'] }}</summary>
                            <div style="padding:0 16px 14px;color:var(--text-sec);font-size:14px;line-height:1.7;">
                                {{ $soru['acceptedAnswer']['text'] }}
                            </div>
                        </details>
                    @endforeach
                </section>
            @endif

            {{-- Editoryal metin (elle veya admin panelinden girilen) --}}
            @if(!empty($model->description))
                <section>
                    <h2>{{ $baslik }} Hakkında</h2>
                    <p>{!! nl2br(e($model->description)) !!}</p>
                </section>
            @endif
        </div>
    @elseif(!empty($model->description))
        <div class="lp-editorial">
            <section>
                <h2>{{ $baslik }} Hakkında</h2>
                <p>{!! nl2br(e($model->description)) !!}</p>
            </section>
        </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
(function () {
    var form = document.querySelector('[data-lp-form]');
    if (!form) return;

    // Temiz adres: boş seçimler, varsayılan sıralama ve uçtaki fiyat
    // kaydırıcısı gönderilmez (filtre "aktif" sayılmasın, adres okunur kalsın).
    // form.elements KULLANILIR: kenar çubuğu kutuları form="lp-form" ile
    // bağlı, form'un DOM alt öğesi değil — querySelectorAll onları görmez.
    function temizle() {
        Array.prototype.forEach.call(form.elements, function (el) {
            if (!el.name) return;
            if (el.type === 'range') {
                var uc = el.hasAttribute('data-lp-range-min') ? el.min : el.max;
                if (String(el.value) === String(uc)) el.disabled = true;
                return;
            }
            if (el.value === '' || (el.name === 'sirala' && el.value === 'onerilen')) el.disabled = true;
        });
    }
    function gonder() {
        form.requestSubmit ? form.requestSubmit() : (temizle(), form.submit());
    }
    form.addEventListener('submit', temizle);

    document.querySelectorAll('[data-lp-auto]').forEach(function (el) {
        el.addEventListener('change', function () {
            // Üst seçim (tek değer) ile kenar çubuğu kutuları aynı parametreyi
            // besliyor: kutu değişince üst seçimi sıfırla, üst seçim değişince
            // o gruptaki kutuları boşalt — iki kaynak çelişmesin.
            var grup = el.getAttribute('data-lp-group');
            if (grup) {
                var ayna = document.querySelector('[data-lp-mirror="' + grup + '"]');
                if (ayna) ayna.value = '';
            }
            var mirror = el.getAttribute('data-lp-mirror');
            if (mirror) {
                document.querySelectorAll('[data-lp-group="' + mirror + '"]').forEach(function (c) { c.checked = false; });
            }
            gonder();
        });
    });

    // Çift uçlu fiyat kaydırıcısı: dolgu + etiketler; bırakınca gönderir (change)
    document.querySelectorAll('[data-lp-range]').forEach(function (kutu) {
        var a = kutu.querySelector('[data-lp-range-min]');
        var b = kutu.querySelector('[data-lp-range-max]');
        var dolgu = kutu.querySelector('[data-lp-fill]');
        var oMin = kutu.querySelector('[data-lp-out="min"]');
        var oMax = kutu.querySelector('[data-lp-out="max"]');
        var lo = Number(kutu.dataset.min), hi = Number(kutu.dataset.max);
        var tl = function (n) { return Number(n).toLocaleString('tr-TR') + ' ₺'; };
        function ciz() {
            if (Number(a.value) > Number(b.value)) { var t = a.value; a.value = b.value; b.value = t; }
            var p1 = (Number(a.value) - lo) / (hi - lo) * 100, p2 = (Number(b.value) - lo) / (hi - lo) * 100;
            dolgu.style.left = p1 + '%'; dolgu.style.right = (100 - p2) + '%';
            oMin.textContent = tl(a.value); oMax.textContent = tl(b.value);
        }
        [a, b].forEach(function (r) {
            r.addEventListener('input', ciz);
            r.addEventListener('change', gonder);
        });
        ciz();
    });

    // Karşılaştır kutuları: sitenin karşılaştırma listesiyle (localStorage) senkron
    function cmpOku() { try { return JSON.parse(localStorage.getItem('compared_tours') || '[]').map(Number); } catch (e) { return []; } }
    function cmpEsitle() {
        var ids = cmpOku();
        document.querySelectorAll('[data-lp-cmp]').forEach(function (c) { c.checked = ids.indexOf(Number(c.dataset.lpCmp)) > -1; });
    }
    document.querySelectorAll('[data-lp-cmp]').forEach(function (c) {
        c.addEventListener('change', function () {
            if (window.toggleCompare) window.toggleCompare(Number(c.dataset.lpCmp));
            cmpEsitle();
        });
    });
    cmpEsitle();
    window.addEventListener('storage', cmpEsitle);

    // "Tur danışmanına sor": sohbet balonu sayfadaysa onu açar, yoksa keşif rehberine gider
    var danisman = document.querySelector('[data-lp-danisman]');
    if (danisman) danisman.addEventListener('click', function (e) {
        var tetik = document.getElementById('cv2-trigger');
        if (tetik) { e.preventDefault(); tetik.click(); }
    });
})();

/* ── Mobil filtre paneli (≤768px) — masaüstü formundan BAĞIMSIZ kendi formu.
   Panel <template> içinde durur, ilk açılışta gövdeye takılır (DOM hafif kalsın;
   JS'siz kullanılamaz, hızlı çipler <a> olduğundan filtre JS'siz de çalışır). ── */
(function () {
    var tpl = document.getElementById('lpm-sheet-tpl');
    var serit = document.getElementById('lpm-serit');
    if (!tpl || !serit) return;

    // Yapışkan şerit mobil üst barın (.nav, akış içi sticky) ALTINA yapışmalı; bar
    // top:0'da ve daha yüksek z-index'te olduğundan ofset verilmezse şeridi örter.
    (function () {
        var navUst = function () {
            var nav = document.querySelector('.nav');
            if (!nav) return 0;
            var st = window.getComputedStyle(nav);
            return (st.position === 'sticky' || st.position === 'fixed') ? Math.round(nav.getBoundingClientRect().height) : 0;
        };
        var ayarla = function () { serit.style.setProperty('--lpm-ust', navUst() + 'px'); };
        ayarla();
        window.addEventListener('resize', ayarla);
    })();

    var sheet = null, form = null, ortu = null, kapatBtn = null, uygula = null, duyuru = null;
    var sonOdak = null, govdeKilidi = null, zamanlayici = null, sonIstek = 0;

    // iOS Safari body'de overflow:hidden'ı yok sayar: sohbet panelindeki kalıp (position:fixed + scrollY)
    function govdeKilitle(kilitle) {
        var b = document.body;
        if (kilitle) {
            if (govdeKilidi !== null) return;
            govdeKilidi = window.scrollY;
            b.style.position = 'fixed'; b.style.top = -govdeKilidi + 'px';
            b.style.left = '0'; b.style.right = '0'; b.style.width = '100%'; b.style.overflow = 'hidden';
        } else if (govdeKilidi !== null) {
            var y = govdeKilidi; govdeKilidi = null;
            b.style.position = ''; b.style.top = ''; b.style.left = ''; b.style.right = ''; b.style.width = ''; b.style.overflow = '';
            window.scrollTo(0, y);
        }
    }

    // Temiz adres: boş değer, varsayılan sıralama ve "Tümü" kalkış gönderilmez
    function gonderilecek(el) {
        if (!el.name || el.disabled) return false;
        if ((el.type === 'radio' || el.type === 'checkbox') && !el.checked) return false;
        if (el.value === '') return false;
        if (el.name === 'sirala' && el.value === 'onerilen') return false;
        return true;
    }

    // Canlı sayaç: seçim değişince "N turu göster" (aynı filtre dili, ?sayac=1 → JSON)
    function sayacGuncelle() {
        var p = new URLSearchParams();
        Array.prototype.forEach.call(form.elements, function (el) {
            if (gonderilecek(el)) p.append(el.name, el.value);
        });
        p.set('sayac', '1');
        var istek = ++sonIstek;
        fetch(form.getAttribute('data-lpm-sayac-url') + '?' + p.toString(), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (d) {
                if (!d || istek !== sonIstek || !uygula) return;
                var n = Number(d.adet);
                var metin = n > 0 ? (uygula.getAttribute('data-sablon') || ':n turu göster').replace(':n', n) : 'Bu filtrelerle tur yok';
                uygula.textContent = metin;
                if (duyuru) duyuru.textContent = metin; // ekran okuyucuya duyur
            })
            .catch(function () {});
    }
    function sayacPlanla() {
        clearTimeout(zamanlayici);
        zamanlayici = setTimeout(sayacGuncelle, 300);
    }

    function kur() {
        if (sheet) return;
        document.body.appendChild(tpl.content.cloneNode(true));
        sheet = document.getElementById('lpm-sheet');
        form = document.querySelector('[data-lpm-form]');
        ortu = document.querySelector('.lpm-ortu');
        kapatBtn = sheet.querySelector('.lpm-kapat');
        uygula = form.querySelector('[data-lpm-uygula]');
        duyuru = form.querySelector('[data-lpm-duyuru]');
        document.querySelectorAll('[data-lpm-kapat]').forEach(function (b) { b.addEventListener('click', kapat); });
        form.addEventListener('submit', function () {
            Array.prototype.forEach.call(form.elements, function (el) {
                if (el.name && !gonderilecek(el)) el.disabled = true;
            });
        });
        form.addEventListener('change', sayacPlanla);
        // Sayı kutuları yazarken de güncellensin (change yalnız odak çıkınca gelir)
        form.querySelectorAll('input[type=number]').forEach(function (el) { el.addEventListener('input', sayacPlanla); });
    }

    function ac(hedefGrup) {
        kur();
        sonOdak = document.activeElement;
        sheet.inert = false;
        sheet.removeAttribute('aria-hidden');
        // Sınıf bir sonraki karede: template'ten yeni takılan panel kayarak gelsin
        requestAnimationFrame(function () { ortu.classList.add('open'); sheet.classList.add('open'); });
        govdeKilitle(true);
        document.querySelectorAll('[data-lpm-ac][aria-expanded]').forEach(function (b) { b.setAttribute('aria-expanded', 'true'); });
        if (hedefGrup) {
            var g = document.getElementById('lpm-grup-' + hedefGrup);
            if (g) g.scrollIntoView({ block: 'start' });
        }
        if (kapatBtn) kapatBtn.focus({ preventScroll: true });
    }
    function kapat() {
        if (!sheet) return;
        var acikti = sheet.classList.contains('open');
        ortu.classList.remove('open');
        sheet.classList.remove('open');
        sheet.inert = true;
        sheet.setAttribute('aria-hidden', 'true');
        govdeKilitle(false);
        document.querySelectorAll('[data-lpm-ac][aria-expanded]').forEach(function (b) { b.setAttribute('aria-expanded', 'false'); });
        if (acikti && sonOdak && typeof sonOdak.focus === 'function') sonOdak.focus({ preventScroll: true });
        sonOdak = null;
    }
    function odaklanabilirler() {
        return Array.prototype.filter.call(
            sheet.querySelectorAll('a[href], button, input, select, textarea, [tabindex]:not([tabindex="-1"])'),
            function (el) { return !el.disabled && el.offsetParent !== null; }
        );
    }
    // Escape kapatır; Tab panel içinde döner (aria-modal'ın gerçek karşılığı)
    document.addEventListener('keydown', function (e) {
        if (!sheet || !sheet.classList.contains('open')) return;
        if (e.key === 'Escape') { e.preventDefault(); kapat(); return; }
        if (e.key !== 'Tab') return;
        var liste = odaklanabilirler();
        if (!liste.length) return;
        var ilk = liste[0], son = liste[liste.length - 1], disarida = !sheet.contains(document.activeElement);
        if (e.shiftKey && (document.activeElement === ilk || disarida)) { e.preventDefault(); son.focus(); }
        else if (!e.shiftKey && (document.activeElement === son || disarida)) { e.preventDefault(); ilk.focus(); }
    });
    document.querySelectorAll('[data-lpm-ac]').forEach(function (b) {
        b.addEventListener('click', function () { ac(b.getAttribute('data-lpm-ac') || null); });
    });
})();
</script>
@endpush
