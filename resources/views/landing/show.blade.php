@extends('layouts.app')

@php
    use App\Support\LandingSlug;
    use App\Support\Seo;

    $baslik = LandingSlug::heading($model);
    $sayi = $tours->total();
    $enDusuk = $tours->getCollection()->min('price_try');
    $acentaSayisi = $tours->getCollection()->pluck('agency.id')->filter()->unique()->count();
@endphp

@section('title', Seo::listingTitle($model->name))
@section('description', trim(($model->description ?: '')) ?:
    $baslik.' — '.$sayi.' tur, '.$acentaSayisi.' acenta karşılaştırmalı.'
    .($enDusuk ? ' '.number_format($enDusuk, 0, ',', '.').' ₺\'den başlayan fiyatlar.' : ''))

@if(!empty($model->image))
    @section('og_image', url($model->image))
@endif

@push('head')
    @include('partials.json-ld', [
        'data' => \App\Support\TourSchema::itemList($tours, Seo::canonical()) ?? [],
    ])
    @include('partials.pagination-seo', ['paginator' => $tours])
@endpush

@section('content')
<div class="container">
    <div style="padding:24px 0 8px;">
        @include('partials.breadcrumb', ['items' => $breadcrumb])
    </div>

    {{-- H1 = tam anahtar kelime + canlı envanter sayısı (tatilbudur kalıbı:
         bu biçimle üç kategori sorgusunda birden 1. sırada) --}}
    <h1 style="font-size:30px;font-weight:800;letter-spacing:-0.6px;margin-bottom:8px;">
        {{ $baslik }}
        @if($sayi)<span style="font-weight:600;color:var(--text-muted);font-size:24px;">({{ $sayi }})</span>@endif
    </h1>

    @if($sayi)
        <p style="color:var(--text-sec);font-size:15px;margin-bottom:24px;">
            {{ $acentaSayisi }} acentanın fiyatı karşılaştırmalı
            @if($enDusuk)
                · <strong>{{ number_format($enDusuk, 0, ',', '.') }} ₺</strong>'den başlayan fiyatlar
            @endif
        </p>
    @endif

    {{-- Alt kategori kırılımı: iç link ağı. Rakiplerde kategori sayfası başına
         100–470 benzersiz iç link var; kırılım eksenleri bunun omurgası. --}}
    @if($altKategoriler->count())
        <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:28px;">
            @foreach($altKategoriler as $alt)
                <a href="{{ LandingSlug::urlForCategory($alt) }}"
                   style="border:1px solid var(--border);background:var(--white);border-radius:999px;padding:8px 16px;font-size:13.5px;font-weight:600;color:var(--text-sec);">
                    {{ $alt->name }}
                </a>
            @endforeach
        </div>
    @endif

    @if($sayi)
        <div class="grid-4" style="margin-bottom:32px;">
            @foreach($tours as $tour)
                <a href="{{ route('tours.show', $tour) }}" class="card">
                    @if($tour->image)
                        <img src="{{ $tour->image }}" alt="{{ $tour->title }}" class="card-img" loading="lazy">
                    @else
                        <div class="card-img" style="background:linear-gradient(135deg,#e0f2fe,#f0fdf4);display:flex;align-items:center;justify-content:center;font-size:36px;">🏖️</div>
                    @endif
                    <div class="card-body">
                        <div class="card-title">{{ $tour->title }}</div>
                        <div class="card-meta">{{ $tour->agency->name ?? '' }} · {{ $tour->duration_label }}</div>
                        <div class="card-price-row" style="margin-top:8px;">
                            <span class="price-tag" style="font-size:18px;">{{ $tour->formatted_price }}</span>
                            <span class="price-sm"> / kişi başı</span>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>

        <div style="margin-bottom:40px;">{{ $tours->links() }}</div>
    @else
        {{-- Envanteri biten sayfa KAPATILMAZ (gruppal kalıbı): adres, başlık ve
             iç linkler yerinde kalır; yalnız liste yerine yönlendirme gösterilir. --}}
        <div class="card" style="padding:40px;text-align:center;margin-bottom:32px;">
            <div style="font-size:40px;margin-bottom:12px;">🗓️</div>
            <p style="font-weight:600;margin-bottom:6px;">Şu anda bu başlıkta yayında tur yok.</p>
            <p style="color:var(--text-muted);font-size:14px;margin-bottom:20px;">
                Yeni turlar eklendiğinde burada listelenecek.
            </p>
            <a href="{{ route('tours.index') }}" class="btn btn-primary">Tüm turlara göz at</a>
        </div>
    @endif

    {{-- Metin blokları LİSTENİN ALTINDA: tatilsepeti (919 kelime, offset
         661k/712k) ve MNG (541 kelime, en dip) aynı yerleşimi kullanıyor.
         Listenin üstünü metinle tıkamak kullanıcıyı üründen uzaklaştırıyor.

         Buradaki her rakam canlı envanterden geliyor — bayatlamaz, uydurmaz,
         maliyeti yoktur (bkz. App\Support\LandingStats). --}}
    @if($stats['var'] ?? false)
        @php
            $tl = fn ($n) => number_format((float) $n, 0, ',', '.').' ₺';
        @endphp

        <div style="max-width:900px;margin-bottom:48px;">

            @if($stats['fiyat'])
                <section style="margin-bottom:36px;">
                    {{-- "Kapadokya Turları Fiyatları" değil "Kapadokya Tur Fiyatları":
                         rakiplerin tamamı bu kalıbı kullanıyor ve arama hacmi burada. --}}
                    <h2 style="font-size:20px;font-weight:700;margin-bottom:12px;">{{ \App\Support\Seo::stem($model->name) }} Tur Fiyatları</h2>
                    <p style="color:var(--text-sec);font-size:15px;line-height:1.8;margin-bottom:16px;">
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
                <section style="margin-bottom:36px;">
                    <h2 style="font-size:20px;font-weight:700;margin-bottom:6px;">
                        {{ $baslik }} Acenta Fiyat Karşılaştırması
                    </h2>
                    <p style="color:var(--text-muted);font-size:14px;margin-bottom:14px;">
                        Aynı başlıkta {{ count($stats['acentalar']) }} acentanın fiyatı yan yana.
                    </p>
                    <div style="overflow-x:auto;">
                        <table style="width:100%;border-collapse:collapse;font-size:14.5px;min-width:460px;">
                            <thead>
                                <tr style="background:var(--border-light);text-align:left;">
                                    <th style="padding:10px 14px;font-weight:600;">Acenta</th>
                                    <th style="padding:10px 14px;font-weight:600;">Tur</th>
                                    <th style="padding:10px 14px;font-weight:600;">En düşük</th>
                                    <th style="padding:10px 14px;font-weight:600;">En yüksek</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($stats['acentalar'] as $i => $satir)
                                    <tr style="border-top:1px solid var(--border-light);">
                                        <td style="padding:10px 14px;font-weight:600;">
                                            <a href="{{ route('agencies.show', $satir['acenta']) }}" style="color:var(--text);">
                                                {{ $satir['acenta']->name }}
                                            </a>
                                            @if($i === 0)
                                                <span style="background:var(--green-bg);color:var(--green-text);font-size:11px;font-weight:700;padding:2px 7px;border-radius:999px;margin-left:6px;">EN UYGUN</span>
                                            @endif
                                        </td>
                                        <td style="padding:10px 14px;color:var(--text-sec);">{{ $satir['turSayisi'] }}</td>
                                        <td style="padding:10px 14px;font-weight:700;color:var(--accent);">{{ $tl($satir['enDusuk']) }}</td>
                                        <td style="padding:10px 14px;color:var(--text-sec);">{{ $tl($satir['enYuksek']) }}</td>
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
                    <section style="margin-bottom:36px;">
                        <h2 style="font-size:20px;font-weight:700;margin-bottom:12px;">{{ $bolum['baslik'] }}</h2>
                        <p style="color:var(--text-sec);font-size:15px;line-height:1.8;">{{ $bolum['metin'] }}</p>
                    </section>
                @endforeach
            @endif

            @if(count($stats['sureler']))
                <section style="margin-bottom:36px;">
                    <h2 style="font-size:20px;font-weight:700;margin-bottom:12px;">{{ $baslik }} Kaç Gün Sürüyor?</h2>
                    <div style="display:flex;flex-wrap:wrap;gap:8px;">
                        @foreach($stats['sureler'] as $sure)
                            <span style="border:1px solid var(--border);background:var(--white);border-radius:999px;padding:7px 14px;font-size:13.5px;color:var(--text-sec);">
                                {{ $sure['etiket'] }} <strong style="color:var(--text);">({{ $sure['adet'] }})</strong>
                            </span>
                        @endforeach
                    </div>
                </section>
            @endif

            @if(count($stats['aylar']))
                <section style="margin-bottom:36px;">
                    <h2 style="font-size:20px;font-weight:700;margin-bottom:12px;">{{ $baslik }} İçin Kalkış Ayları</h2>
                    <div style="display:flex;flex-wrap:wrap;gap:8px;">
                        @foreach($stats['aylar'] as $ay)
                            <span style="border:1px solid var(--border);background:var(--white);border-radius:999px;padding:7px 14px;font-size:13.5px;color:var(--text-sec);">
                                {{ $ay['ay'] }} <strong style="color:var(--text);">({{ $ay['adet'] }})</strong>
                            </span>
                        @endforeach
                    </div>
                </section>
            @endif

            {{-- Görünür SSS + FAQPage şeması aynı kaynaktan; Google şemanın
                 sayfada görünür karşılığını şart koşuyor. --}}
            @php
                $landingFaq = \App\Support\LandingStats::faq($stats, $baslik);
            @endphp
            @if($landingFaq)
                <section style="margin-bottom:36px;" aria-labelledby="landing-sss">
                    <h2 id="landing-sss" style="font-size:20px;font-weight:700;margin-bottom:16px;">Sıkça Sorulan Sorular</h2>
                    @foreach($landingFaq['mainEntity'] as $soru)
                        <details style="background:var(--white);border:1px solid var(--border);border-radius:var(--radius);margin-bottom:10px;">
                            <summary style="cursor:pointer;padding:14px 16px;font-weight:600;font-size:15px;list-style:none;">{{ $soru['name'] }}</summary>
                            <div style="padding:0 16px 14px;color:var(--text-sec);font-size:14px;line-height:1.7;">
                                {{ $soru['acceptedAnswer']['text'] }}
                            </div>
                        </details>
                    @endforeach
                </section>
                @push('head')
                    @include('partials.json-ld', ['data' => ['@context' => 'https://schema.org'] + $landingFaq])
                @endpush
            @endif

            {{-- Editoryal metin (elle veya admin panelinden girilen) --}}
            @if(!empty($model->description))
                <section>
                    <h2 style="font-size:20px;font-weight:700;margin-bottom:12px;">{{ $baslik }} Hakkında</h2>
                    <div style="color:var(--text-sec);font-size:15px;line-height:1.8;">
                        {!! nl2br(e($model->description)) !!}
                    </div>
                </section>
            @endif
        </div>
    @elseif(!empty($model->description))
        <section style="max-width:820px;margin-bottom:48px;">
            <h2 style="font-size:20px;font-weight:700;margin-bottom:12px;">{{ $baslik }} Hakkında</h2>
            <div style="color:var(--text-sec);font-size:15px;line-height:1.8;">
                {!! nl2br(e($model->description)) !!}
            </div>
        </section>
    @endif
</div>
@endsection
