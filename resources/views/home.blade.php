@extends('layouts.app')
@section('title', 'turXtur — Tur Karşılaştırma Platformu')

@push('head')
@include('partials.json-ld', ['data' => [
    '@context' => 'https://schema.org',
    '@graph' => [
        [
            '@type' => 'WebSite',
            '@id' => url('/').'#website',
            'url' => url('/'),
            'name' => 'turXtur',
            'inLanguage' => 'tr-TR',
            'publisher' => ['@id' => url('/').'#organization'],
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => [
                    '@type' => 'EntryPoint',
                    'urlTemplate' => route('tours.index').'?q={search_term_string}',
                ],
                'query-input' => 'required name=search_term_string',
            ],
        ],
        [
            '@type' => 'Organization',
            '@id' => url('/').'#organization',
            'name' => 'turXtur',
            'url' => url('/'),
            'logo' => [
                '@type' => 'ImageObject',
                'url' => asset('images/og-default.png'),
            ],
        ],
    ],
]])
@endpush

@section('content')
{{-- Hero Banner Carousel --}}
@php
    // Yeni hero AÇIK zeminli: başlık koyu lacivert, okunurluğu soldan gelen
    // beyaz perde (.hero-overlay::before) sağlıyor. Bu yüzden varsayılan
    // karartma 40 → 12'ye indi; admin panelinden gelen banner kendi
    // darkness değerini korur.
    $carouselBanners = $banners->count() ? $banners : collect([
        (object)['title'=>'Kapadokya', 'image_url'=>asset('images/banners/cappadocia.png'), 'blur'=>0, 'darkness'=>12],
        (object)['title'=>'Bodrum', 'image_url'=>asset('images/banners/bodrum.png'), 'blur'=>0, 'darkness'=>12],
        (object)['title'=>'Mısır Piramitleri', 'image_url'=>asset('images/banners/egypt.png'), 'blur'=>0, 'darkness'=>12],
        (object)['title'=>'Karadeniz Yaylaları', 'image_url'=>asset('images/banners/karadeniz.png'), 'blur'=>0, 'darkness'=>12],
        (object)['title'=>'Güneydoğu Anadolu', 'image_url'=>asset('images/banners/guneydogu.png'), 'blur'=>0, 'darkness'=>12],
    ]);
@endphp
<div class="hero-carousel" id="heroCarousel">
    <div class="hero-slides" id="heroSlides">
        @foreach($carouselBanners as $b)
        <div class="hero-slide">
            <img src="{{ $b->image_url }}" alt="{{ $b->title }}" style="width:100%;height:100%;object-fit:cover;filter:blur({{ $b->blur }}px);position:absolute;inset:0;">
            <div style="position:absolute;inset:0;background:rgba(0,0,0,{{ $b->darkness / 100 }});"></div>
            <div class="hero-slide-label">{{ $b->title }}</div>
        </div>
        @endforeach
    </div>
    {{-- Overlay Content --}}
    <div class="hero-overlay">
        <div class="container">
            <span class="hero-badge">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3l1.9 4.7L18.6 9l-4.7 1.9L12 15.6 10.1 10.9 5.4 9l4.7-1.3z"/><path d="M18.5 15.5l.7 1.8 1.8.7-1.8.7-.7 1.8-.7-1.8-1.8-.7 1.8-.7z"/></svg>
                Birçok acenta, tek arama
            </span>
            <h1 class="hero-title">
                Hayalindeki turu<br><span>en uygun fiyatla</span> bul
            </h1>
            <p class="hero-sub">
                Yüzlerce acentanın turlarını karşılaştır,<br>sana en uygun fiyatı kolayca bul.
            </p>
            {{-- Search bar --}}
            <form action="{{ route('tours.index') }}" method="GET" class="hero-search-shell">
                <div class="hero-search-form">
                    <div class="hero-search-field full-width">
                        <span class="hsf-ico">
                            <svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0z"/><circle cx="12" cy="10" r="3"/></svg>
                        </span>
                        <span class="hsf-body">
                            <label for="heroSearchInput">Nereye?</label>
                            <input type="text" name="q" id="heroSearchInput" placeholder="Örn. Karadeniz, Bali, Yunanistan">
                        </span>
                        <span class="hsf-caret" aria-hidden="true">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                        </span>
                    </div>
                    <div class="hero-search-row">
                        <div class="hero-search-field split-field">
                            <span class="hsf-ico">
                                <svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="3"/><path d="M8 3v4M16 3v4M3 10h18"/></svg>
                            </span>
                            <span class="hsf-body">
                                <label for="heroDate">Ne zaman?</label>
                                {{-- type=text ile "Tarih seçin" yazısı görünür; odaklanınca
                                     gerçek tarih girdisine döner (JS kapalıysa metin olarak
                                     gönderilir, sunucu tarafı zaten doğruluyor). --}}
                                <input type="text" name="date_start" id="heroDate" placeholder="Tarih seçin"
                                       onfocus="this.type='date'; try { this.showPicker && this.showPicker(); } catch (e) {}"
                                       onblur="if(!this.value) this.type='text';">
                            </span>
                            <span class="hsf-caret" aria-hidden="true">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                            </span>
                        </div>
                        <div class="hero-search-field split-field">
                            <span class="hsf-ico">
                                <svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 8a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2"/><rect x="3" y="8" width="18" height="12" rx="3"/><circle cx="16" cy="14" r="1.4"/></svg>
                            </span>
                            <span class="hsf-body">
                                <label for="heroBudget">Bütçe (kişi başı)</label>
                                <select name="max_price" id="heroBudget">
                                    <option value="">Fark etmez</option>
                                    <option value="5000">Max 5.000 ₺</option>
                                    <option value="10000">Max 10.000 ₺</option>
                                    <option value="20000">Max 20.000 ₺</option>
                                    <option value="30000">Max 30.000 ₺</option>
                                    <option value="50000">Max 50.000 ₺</option>
                                </select>
                            </span>
                            <span class="hsf-caret" aria-hidden="true">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                            </span>
                        </div>
                    </div>
                    <button type="submit" class="hero-search-btn">
                        <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.6-3.6"/></svg>
                        Ara
                    </button>
                </div>
            </form>
        </div>
    </div>
    {{-- Navigation Arrows --}}
    <button class="hero-arrow hero-arrow-left" onclick="slideCarousel(-1)" aria-label="Önceki">❮</button>
    <button class="hero-arrow hero-arrow-right" onclick="slideCarousel(1)" aria-label="Sonraki">❯</button>
    {{-- Dots --}}
    <div class="hero-dots" id="heroDots">
        @foreach($carouselBanners as $i => $b)
        <span class="hero-dot {{ $i === 0 ? 'active' : '' }}" onclick="goToSlide({{ $i }})"></span>
        @endforeach
    </div>
    {{-- Hero'yu sayfa zeminine bağlayan dalga --}}
    <div class="hero-wave" aria-hidden="true">
        <svg viewBox="0 0 1440 130" preserveAspectRatio="none"><path d="M0 62c150 44 330 58 520 34s360-70 540-52 250 58 380 46V130H0Z" fill="var(--bg)"/></svg>
    </div>
</div>

<div class="container" id="homeMain">
    {{-- Filtre barı (daraltma) hero'ya binen yüzen kartta; kategori ağacı
         (gezinme + SEO) dalganın ALTINDA, sayfa zemininde ayrı bir şerit.
         İkisi farklı iş görüyor, biri diğerinin yerine geçmiyor.
         Hangisinin görüneceği config/ui.php: home_nav ile seçilir — üç modun
         hiçbiri kod silmez, .env'de HOME_NAV çevirmek yeterli. --}}
    @php $homeNav = config('ui.home_nav', 'both'); @endphp
    <div class="filter-bar-wrapper" @if($homeNav === 'mega') style="display:none;" @endif>
        <form id="home-filter-form" action="{{ route('home') }}" method="GET" class="filter-bar yfilter-card">
            <input type="hidden" name="category" id="selected-category" value="{{ request('category') }}">

            <div class="ybar" id="yBar">
                @php
                    // Hap düğmelerin ortak iskeleti: ikon kutusu + etiket + sayaç + ok.
                    $yCaret = '<span class="ycaret" aria-hidden="true"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg></span>';
                @endphp
                <button type="button" class="ybtn" data-ypop="ypCat"><i class="yico"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="2"/><rect x="14" y="3" width="7" height="7" rx="2"/><rect x="3" y="14" width="7" height="7" rx="2"/><rect x="14" y="14" width="7" height="7" rx="2"/></svg></i> Kategoriler <b class="ybadge" data-yb="category"></b> {!! $yCaret !!}</button>
                <button type="button" class="ybtn" data-ypop="ypDest"><i class="yico"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0z"/><circle cx="12" cy="10" r="3"/></svg></i> Destinasyon <b class="ybadge" data-yb="destinations"></b> {!! $yCaret !!}</button>
                <button type="button" class="ybtn" data-ypop="ypMonth"><i class="yico"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="16" rx="3"/><path d="M8 3v4M16 3v4M3 10h18"/></svg></i> Dönem <b class="ybadge" data-yb="months"></b> {!! $yCaret !!}</button>
                <button type="button" class="ybtn" data-ypop="ypSpecial"><i class="yico"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="8" width="18" height="13" rx="2"/><path d="M3 12h18M12 8v13"/><path d="M12 8S9.5 3 7.2 4.4 8.6 8 12 8zM12 8s2.5-5 4.8-3.6S15.4 8 12 8z"/></svg></i> Özel Günler <b class="ybadge" data-yb="special"></b> {!! $yCaret !!}</button>
                <button type="button" class="ybtn" data-ypop="ypVisa"><i class="yico"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="3" width="16" height="18" rx="3"/><circle cx="12" cy="10" r="2.6"/><path d="M8.5 16.5h7"/></svg></i> Vize <b class="ybadge" data-yb="visa"></b> {!! $yCaret !!}</button>
                <button type="button" class="ybtn" data-ypop="ypDays"><i class="yico"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5.5l3.5 2"/></svg></i> Süre <b class="ybadge" data-yb="days"></b> {!! $yCaret !!}</button>
                <button type="button" class="ybtn" data-ypop="ypDep"><i class="yico"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M3 20h18"/><path d="M4.5 13.6l2.2.6 3-2.6-5.3-4.1 1.9-.9 6.6 3 3.8-3.3c.9-.8 2.2-.7 2.9.2.6.8.4 2-.5 2.6l-9.3 6.6-2.9-.6z"/></svg></i> Kalkış <b class="ybadge" data-yb="departures"></b> {!! $yCaret !!}</button>
                <button type="button" class="ybtn" data-ypop="ypBudget"><i class="yico"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M3 8a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2"/><rect x="3" y="8" width="18" height="12" rx="3"/><circle cx="16" cy="14" r="1.4"/></svg></i> Bütçe <b class="ybadge" data-yb="budget_max"></b> {!! $yCaret !!}</button>

                <span class="ycount">Canlı: <b id="yLiveCount">{{ number_format($filteredCount, 0, ',', '.') }}</b> tur</span>
                <select name="sort" class="ysort" aria-label="Sıralama">
                    <option value="price_asc" {{ request('sort', 'price_asc') === 'price_asc' ? 'selected' : '' }}>Fiyat ↑</option>
                    <option value="price_desc" {{ request('sort') === 'price_desc' ? 'selected' : '' }}>Fiyat ↓</option>
                    <option value="date_asc" {{ request('sort') === 'date_asc' ? 'selected' : '' }}>Tarihe göre</option>
                </select>
                <button type="button" class="yreset" id="yReset">Sıfırla</button>
            </div>
            <div class="ychips" id="yChips"></div>

            {{-- Paneller: gerçek form alanları içerir; JS yoksa "Uygula" ile GET submit çalışır --}}
            <div class="ypop" id="ypCat" role="group" aria-label="Kategoriler">
                <h5>Kategoriler</h5>
                <button type="button" class="ycat-item {{ request('category') ? '' : 'sel' }}" data-cat="">🏷️ Tümü</button>
                @foreach($categories as $cat)
                    @php
                        // Akordeon: seçili kategori bu üst ya da onun bir altıysa dal açık başlar
                        $branchOpen = request('category') === $cat->slug
                            || $cat->children->contains(fn ($c) => request('category') === $c->slug);
                    @endphp
                    <button type="button" class="ycat-item {{ request('category') === $cat->slug ? 'sel' : '' }} {{ $branchOpen && $cat->children->isNotEmpty() ? 'open' : '' }}" data-cat="{{ $cat->slug }}" @if($cat->children->isNotEmpty()) data-parent="1" aria-expanded="{{ $branchOpen ? 'true' : 'false' }}" @endif>
                        {{ $cat->icon }} {{ $cat->name }} <i>{{ $facets['categories'][$cat->id] ?? 0 }}</i>
                        @if($cat->children->isNotEmpty())<span class="ycat-caret" aria-hidden="true">▸</span>@endif
                    </button>
                    @if($cat->children->isNotEmpty())
                        <div class="ycat-children {{ $branchOpen ? 'open' : '' }}" data-children-of="{{ $cat->slug }}">
                            @foreach($cat->children as $child)
                                <button type="button" class="ycat-item ycat-child {{ request('category') === $child->slug ? 'sel' : '' }}" data-cat="{{ $child->slug }}">
                                    {{ $child->icon }} {{ $child->name }} <i>{{ $facets['categories'][$child->id] ?? 0 }}</i>
                                </button>
                            @endforeach
                        </div>
                    @endif
                @endforeach
            </div>

            <div class="ypop" id="ypDest" role="group" aria-label="Destinasyon">
                <h5>Destinasyon</h5>
                @forelse($facets['destinations'] as $row)
                    <label class="yopt"><input type="checkbox" name="destinations[]" value="{{ $row['city'] }}" {{ in_array($row['city'], (array) request('destinations'), true) ? 'checked' : '' }}> {{ $row['city'] }} <i>{{ $row['count'] }}</i></label>
                @empty
                    <div class="yopt-empty">Envanter hazırlanıyor…</div>
                @endforelse
            </div>

            <div class="ypop" id="ypMonth" role="group" aria-label="Dönem">
                <h5>Hangi ay?</h5>
                <div class="ymonths">
                    @foreach(['Oca','Şub','Mar','Nis','May','Haz','Tem','Ağu','Eyl','Eki','Kas','Ara'] as $i => $ay)
                        <label class="ymonth {{ in_array($i + 1, array_map('intval', (array) request('months')), true) ? 'on' : '' }}">
                            <input type="checkbox" name="months[]" value="{{ $i + 1 }}" {{ in_array($i + 1, array_map('intval', (array) request('months')), true) ? 'checked' : '' }}>{{ $ay }}
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="ypop" id="ypSpecial" role="group" aria-label="Özel günler">
                <h5>Özel günler</h5>
                <label class="yopt"><input type="radio" name="special" value="" {{ request('special') ? '' : 'checked' }}> Farketmez</label>
                @foreach($specialPeriods as $key => $period)
                    <label class="yopt"><input type="radio" name="special" value="{{ $key }}" {{ request('special') === $key ? 'checked' : '' }}> {{ $period['label'] }}</label>
                @endforeach
            </div>

            <div class="ypop" id="ypVisa" role="group" aria-label="Vize durumu">
                <h5>Vize durumu</h5>
                <label class="yopt"><input type="checkbox" name="visa[]" value="vizesiz" {{ in_array('vizesiz', (array) request('visa'), true) ? 'checked' : '' }}> ✈️ Vizesiz turlar <i>{{ $facets['visa']['vizesiz'] }}</i></label>
                <label class="yopt"><input type="checkbox" name="visa[]" value="vizeli" {{ in_array('vizeli', (array) request('visa'), true) ? 'checked' : '' }}> Vizeli turlar <i>{{ $facets['visa']['vizeli'] }}</i></label>
            </div>

            <div class="ypop" id="ypDays" role="group" aria-label="Süre">
                <h5>Kaç gün?</h5>
                @foreach($facets['days'] as $band => $count)
                    <label class="yopt"><input type="checkbox" name="days[]" value="{{ $band }}" {{ in_array($band, (array) request('days'), true) ? 'checked' : '' }}> {{ $band }} gün <i>{{ $count }}</i></label>
                @endforeach
            </div>

            <div class="ypop" id="ypDep" role="group" aria-label="Kalkış noktası">
                <h5>Kalkış noktası</h5>
                @forelse($facets['departures'] as $city => $count)
                    <label class="yopt"><input type="checkbox" name="departures[]" value="{{ $city }}" {{ in_array($city, (array) request('departures'), true) ? 'checked' : '' }}> {{ $city }} <i>{{ $count }}</i></label>
                @empty
                    <div class="yopt-empty">Kalkış bilgisi olan tur yok</div>
                @endforelse
            </div>

            <div class="ypop" id="ypBudget" role="group" aria-label="Bütçe">
                <h5>Kişi başı bütçe</h5>
                <input type="range" min="5" max="100" step="5" value="{{ (int) request('budget_max') ?: 100 }}" id="yBudgetRange" style="width:100%;accent-color:var(--accent);">
                <input type="hidden" name="budget_max" id="yBudgetInput" value="{{ (int) request('budget_max') ?: '' }}">
                <div style="display:flex;justify-content:space-between;font-size:11px;color:var(--text-muted);"><span>5.000 ₺</span><b id="yBudgetLabel" style="color:var(--accent);">{{ (int) request('budget_max') ? '≤ '.(int) request('budget_max').'.000 ₺' : 'sınırsız' }}</b></div>
            </div>

            <noscript><button type="submit" class="yreset" style="margin-top:8px;">Filtrele</button></noscript>
        </form>
    </div>

    {{-- Kategori ağacı (malitur kalıbı mega menü): hero'nun hemen altında,
         güven şeridinin üstünde. Mobilde gizli — orada m-home bloğu devrede. --}}
    @if(in_array($homeNav, ['both', 'mega'], true))
        <div class="mega-wrap {{ $homeNav === 'mega' ? 'mega-wrap-solo' : '' }}">
            @include('partials.mega-menu')
        </div>
    @endif

    {{-- Hero'nun güven şeridi (masaüstü; mobilde üstteki .m-trust şeridi var) --}}
    <div class="hero-trust">
        <div class="hero-trust-item">
            <span class="hti-ico"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l7.5 3v5.5c0 4.6-3.2 8.3-7.5 9.5-4.3-1.2-7.5-4.9-7.5-9.5V6z"/><path d="M9 12.2l2.1 2.1L15.4 10"/></svg></span>
            <div><b>Güvenilir acentalar</b><span>Onaylı yüzlerce acenta</span></div>
        </div>
        <div class="hero-trust-item">
            <span class="hti-ico"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20.5 12.3l-8 8a2 2 0 0 1-2.9 0l-6-6a2 2 0 0 1-.6-1.4V4.5A1.5 1.5 0 0 1 4.5 3h8.4a2 2 0 0 1 1.4.6l6.2 6.2a1.8 1.8 0 0 1 0 2.5z"/><circle cx="8" cy="8" r="1.4"/></svg></span>
            <div><b>En iyi fiyatlar</b><span>Karşılaştır, avantajı yakala</span></div>
        </div>
        <div class="hero-trust-item">
            <span class="hti-ico"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 14v-2a8 8 0 0 1 16 0v2"/><rect x="2.5" y="13.5" width="4.5" height="6" rx="2"/><rect x="17" y="13.5" width="4.5" height="6" rx="2"/><path d="M20 19.5v.5a2.5 2.5 0 0 1-2.5 2.5H13"/></svg></span>
            <div><b>7/24 destek</b><span>Seyahatinde yanındayız</span></div>
        </div>
        <div class="hero-trust-item">
            <span class="hti-ico"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="10" width="16" height="11" rx="3"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/><circle cx="12" cy="15.5" r="1.3"/></svg></span>
            <div><b>Güvenli ödeme</b><span>256-bit SSL ile koruma</span></div>
        </div>
    </div>

    {{-- ===== Mobil ana blok: hero + arama + istatistikler (≤768px, turXtur Mobil 2a) ===== --}}
    <div class="m-home">
        <div class="m-hero">
            <img src="{{ $carouselBanners->first()->image_url }}" alt="turXtur">
            <div class="m-hero-shade"></div>
            <div class="m-hero-body">
                <div class="m-hero-pill">TUR KARŞILAŞTIRMA MOTORU</div>
                <h2 class="m-hero-title">Hayalindeki turu<br><span>en uygun fiyatla</span> bul.</h2>
                <p class="m-hero-sub">Tüm turlar tek ekranda — sürprizsiz, komisyonsuz.</p>
                <span class="m-live"><i></i>CANLI — {{ $liveDrop ? $liveDrop['name'].' az önce %'.$liveDrop['pct'].' ucuzladı' : $tourCount.' tur canlı takipte' }}</span>
            </div>
        </div>
        <form action="{{ route('tours.index') }}" method="GET" class="m-search">
            <label class="m-field">
                <span>NEREYE?</span>
                <input type="text" name="q" placeholder="Bodrum, Kapadokya, Roma...">
            </label>
            <div class="m-search-row">
                <label class="m-field">
                    <span>NE ZAMAN?</span>
                    <input type="date" name="date_start">
                </label>
                <label class="m-field">
                    <span>BÜTÇE</span>
                    <select name="max_price">
                        <option value="">Farketmez</option>
                        <option value="5000">Max 5.000 ₺</option>
                        <option value="10000">Max 10.000 ₺</option>
                        <option value="20000">Max 20.000 ₺</option>
                        <option value="30000">Max 30.000 ₺</option>
                        <option value="50000">Max 50.000 ₺</option>
                    </select>
                </label>
            </div>
            <button type="submit" class="m-search-btn">Turları Karşılaştır</button>
        </form>
        <div class="m-stats">
            <div><b>{{ number_format($travelerCount, 0, ',', '.') }}+</b><span>kayıtlı gezgin</span></div>
            <div><b>{{ $tourCount }}</b><span>aktif tur</span></div>
            <div><b>{{ $agencyCount }}</b><span>doğrulanmış acenta</span></div>
            <div><b class="m-teal">{{ $allDestinations->count() }}</b><span>destinasyon</span></div>
        </div>
    </div>

    {{-- iPhone Style Story Cards --}}
    <div class="section" id="storiesSection" style="padding-top: 10px; margin-bottom: 0px;">
        <div class="stories-container">
            <div class="stories-scroll">
                @foreach($featuredCities as $index => $city)
                <div class="story-item" onclick="openStory({{ $index }})">
                    <div class="iphone-card">
                        <img src="{{ $city['images'][0] }}" alt="{{ $city['name'] }}" class="iphone-card-img">
                        <div class="iphone-card-overlay">
                            <div class="iphone-card-badge">{{ $city['count'] > 0 ? $city['count'] . ' Tur' : 'Yakında' }}</div>
                            <div class="iphone-card-info">
                                <h3 class="iphone-card-city">{{ $city['name'] }}</h3>
                                <p class="iphone-card-country">{{ $city['country'] }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="story-mlabel">{{ $city['name'] }}</div>
                </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Story Viewer Modal --}}
    <div id="story-viewer" class="story-viewer">
        <div class="story-window">
            <div class="story-progress-container" id="story-progress">
                {{-- Progress bars will be generated here --}}
            </div>
            
            <div class="story-header">
                <div class="story-user-info">
                    <img id="story-avatar" src="" alt="" class="story-avatar-small">
                    <div>
                        <div id="story-city-name" class="story-city-title"></div>
                        <div id="story-country-name" class="story-country-subtitle"></div>
                    </div>
                </div>
                <button class="story-close" onclick="closeStory()">✕</button>
            </div>
    
            <div class="story-content">
                <img id="story-image" src="" alt="" class="story-full-img">
                <div class="story-nav-btn story-prev" onclick="prevStory()"></div>
                <div class="story-nav-btn story-next" onclick="nextStory()"></div>
            </div>
    
            <div class="story-footer">
                <a id="story-cta" href="#" class="story-btn">Turları Gör</a>
            </div>
        </div>
    </div>

    {{-- Destinations --}}
    @if($destinations->count())
    <div class="section" id="destinationsSection">
        <div class="section-header">
            <h2>Popüler Destinasyonlar</h2>
            <a href="{{ $tumunuGorUrl }}">Tümünü gör →</a>
        </div>
        <div class="grid-3">
            @foreach($destinations as $dest)
            <a href="{{ route('destinations.show', $dest) }}" class="card" style="cursor:pointer;">
                <div style="position:relative;height:160px;overflow:hidden;">
                    @if($dest->image)
                        <img src="{{ $dest->image }}" alt="{{ $dest->name }}" style="width:100%;height:100%;object-fit:cover;transition:transform .3s;" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
                    @else
                        <div style="width:100%;height:100%;background:linear-gradient(135deg,var(--accent-bg),#f0fdf4);display:flex;align-items:center;justify-content:center;font-size:48px;">🌍</div>
                    @endif
                    <div style="position:absolute;bottom:0;left:0;right:0;padding:14px 16px;background:linear-gradient(transparent,rgba(0,0,0,0.7));">
                        <div style="font-size:16px;font-weight:700;color:white;">{{ $dest->name }}</div>
                        <div style="font-size:12px;color:rgba(255,255,255,0.85);">{{ $dest->tour_count }} tur · {{ number_format($dest->min_price, 0, ',', '.') }} ₺'den başlayan</div>
                    </div>
                </div>
            </a>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Tur eşleştirme testi girişi — .js-open-quiz layouts/app.blade.php'deki
         global açıcıya bağlanır, sohbet bayrağından bağımsız çalışır.
         ⏸️ config/ai.php: quiz_enabled ile askıya alınabilir. --}}
    @if(config('ai.quiz_enabled'))
    <div class="section">
        <div style="border:1px solid var(--border);border-radius:16px;padding:24px;background:linear-gradient(135deg,#f0fdfa,#ecfeff);display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:16px;">
            <div style="flex:1;min-width:260px;">
                <div style="font-size:19px;font-weight:800;letter-spacing:-0.4px;color:#0f172a;margin-bottom:6px;">
                    Hangi tur sana uygun, bilmiyor musun?
                </div>
                <p style="font-size:14px;color:#475569;line-height:1.6;margin:0;max-width:56ch;">
                    8 kısa soru cevapla, sana en uygun turları gerekçesiyle birlikte sıralayalım.
                    Üyelik gerekmez, bir dakika sürer.
                </p>
            </div>
            <button type="button" class="btn btn-primary js-open-quiz" style="white-space:nowrap;padding:12px 22px;font-weight:700;">
                Testi başlat →
            </button>
        </div>
    </div>
    @endif

    {{-- Popular Tours --}}
    <div class="section" id="tours-section">
        <div class="section-header">
            <h2>En Uygun Turlar</h2>
            <a href="{{ $tumunuGorUrl }}">Tümünü gör →</a>
        </div>
        
        <div id="loading-spinner" style="display:none; text-align:center; padding:40px;">
            <div style="width:40px;height:40px;border:4px solid #f1f5f9;border-top-color:var(--accent);border-radius:50%;animation:spin 1s linear infinite;margin:0 auto;"></div>
            <p style="margin-top:12px; color:var(--text-muted); font-size:14px;">Turlar güncelleniyor...</p>
        </div>
        <style>@keyframes spin { 100% { transform:rotate(360deg); } }</style>

        <div id="tour-grid-container">
            @if($popularTours->count())
                @include('partials.tour_grid', ['popularTours' => $popularTours, 'tourDrops' => $tourDrops ?? []])
            @else
                <div style="text-align:center;padding:40px;color:var(--text-muted);">
                    <div style="font-size:48px;margin-bottom:12px;">🏖️</div>
                    <p>Henüz tur bulunmuyor.</p>
                </div>
            @endif
        </div>

        {{-- Mobil: tüm turlar CTA + güven kartı (≤768px) --}}
        <a href="{{ route('tours.index') }}" class="m-alltours">Tüm turları gör ({{ $tourCount }})</a>
        <div class="m-guarantee">
            <div><span>✓</span><div><b>Doğrulanmış acentalar</b><p>Her acenta onay sürecinden geçtikten sonra yayına alınır.</p></div></div>
            <div><span>✓</span><div><b>Fiyat şeffaflığı</b><p>Gizli komisyon yok; acentanın fiyatı neyse o.</p></div></div>
            <div><span>✓</span><div><b>Tarafsız karşılaştırma</b><p>Tüm acentaların turları tek ekranda, aracısız.</p></div></div>
        </div>
    </div>

    {{-- Recently Viewed --}}
    @if($recentlyViewed->count())
    <div class="section">
        <div class="section-header">
            <h2>Son Gezdiğiniz Turlar</h2>
            <span style="font-size:13px;color:var(--text-muted);">{{ $recentlyViewed->count() }} tur</span>
        </div>
        <div class="grid-4 m-hcard-list">
            @foreach($recentlyViewed as $tour)
            <a href="{{ route('tours.show', $tour) }}" class="card">
                @if($tour->image)
                    <img src="{{ $tour->image }}" alt="{{ $tour->title }}" class="card-img">
                @else
                    <div class="card-img" style="background:linear-gradient(135deg,#e0f2fe,#f0fdf4);display:flex;align-items:center;justify-content:center;font-size:36px;">🏖️</div>
                @endif
                <div class="card-body">
                    <div class="card-title">{{ $tour->title }}</div>
                    <div class="card-meta">{{ $tour->agency->name }} · {{ $tour->duration_label }}</div>
                    @include('partials.tour_card_meta', ['tour' => $tour])
                    <div style="margin-top:8px;">
                        <span class="price-tag" style="font-size:18px;">{{ $tour->formatted_price }}</span>
                        <span class="price-sm"> / kişi başı</span>
                    </div>
                </div>
            </a>
            @endforeach
        </div>
    </div>
    @endif

    {{-- CTA --}}
    <div style="background:linear-gradient(135deg,#0d9488,#0891b2);border-radius:var(--radius-lg);padding:32px;color:white;margin:32px 0;">
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px;">
            <div>
                <h3 style="font-size:20px;font-weight:700;margin-bottom:6px;">Acentanızın turlarını yayınlayın</h3>
                <p style="opacity:.9;font-size:14px;">Turlarınızı binlerce potansiyel müşteriye ücretsiz ulaştırın.</p>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <a href="{{ route('agency.register') }}" class="btn" style="background:white;color:var(--accent);font-weight:700;">Acenta Ol →</a>
                <a href="{{ route('login') }}" class="btn" style="background:rgba(255,255,255,.14);color:white;border:1px solid rgba(255,255,255,.28);font-weight:700;">Giriş Yap</a>
            </div>
        </div>
    </div>
</div>
@endsection

@section('styles')
        /* ── Hero başlık bloğu ── */
        .hero-badge { display:inline-flex; align-items:center; gap:8px; background:rgba(255,255,255,.92); color:var(--accent-dark); border:1px solid rgba(255,255,255,.85); border-radius:100px; padding:8px 17px; font-size:13.5px; font-weight:700; margin-bottom:22px; box-shadow:0 8px 22px -12px rgba(15,23,42,.5); backdrop-filter:blur(6px); }
        .hero-badge svg { color:var(--accent); flex-shrink:0; }
        .hero-title { font-size:56px; font-weight:800; letter-spacing:-1.8px; line-height:1.08; color:#0f172a; margin-bottom:18px; }
        .hero-title span { color:var(--accent); }
        .hero-sub { font-size:18px; line-height:1.55; color:#334155; font-weight:500; margin-bottom:36px; }

        /* ── Hero arama kutusu ── */
        .hero-search-shell { display:block; width:100%; max-width:950px; background:rgba(255,255,255,.5); border:1px solid rgba(255,255,255,.7); border-radius:26px; padding:9px; box-shadow:0 28px 60px -28px rgba(15,23,42,.55); backdrop-filter:blur(10px); }
        .hero-search-form { display:flex; flex-wrap:nowrap; align-items:stretch; background:#fff; border-radius:19px; padding:7px; }
        .hero-search-field { display:flex; align-items:center; gap:11px; padding:10px 16px; border-right:1px solid #eef2f6; min-width:0; }
        .hero-search-field.full-width { flex:1.5; }
        .hero-search-row { display:flex; flex:2.1; min-width:0; }
        .hero-search-field.split-field { flex:.92; min-width:0; }
        /* Bütçe etiketi ("Bütçe (kişi başı)") daha uzun — bu alan biraz geniş */
        .hero-search-row .split-field:last-child { flex:1.16; border-right:none; }
        .hsf-ico { display:flex; flex-shrink:0; color:var(--accent); }
        .hsf-body { display:flex; flex-direction:column; justify-content:center; flex:1; min-width:0; }
        .hero-search-field label { font-size:13.5px; font-weight:700; color:#0f172a; letter-spacing:-.1px; cursor:pointer; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .hero-search-field input, .hero-search-field select { border:none; outline:none; font-family:var(--font); font-size:14.5px; width:100%; background:transparent; color:#0f172a; font-weight:500; padding:0; text-overflow:ellipsis; }
        .hero-search-field input::placeholder { color:#94a3b8; }
        .hero-search-field select { cursor:pointer; appearance:none; -webkit-appearance:none; }
        .hero-search-field input[type="date"] { cursor:pointer; }
        .hsf-caret { display:flex; flex-shrink:0; color:#94a3b8; }
        .hero-search-btn { background:var(--accent); color:#fff; border:none; border-radius:15px; margin-left:7px; padding:0 34px; font-family:var(--font); font-size:16px; font-weight:700; cursor:pointer; white-space:nowrap; transition:background .2s; display:flex; align-items:center; justify-content:center; gap:9px; flex-shrink:0; }
        .hero-search-btn:hover { background:var(--accent-dark); }

        /* ── Hero Carousel ── */
        .hero-carousel { position:relative; width:100vw; margin-left:calc(-50vw + 50%); height:760px; overflow:hidden; }
        .hero-slides { display:flex; height:100%; transition:transform 0.8s cubic-bezier(0.4,0,0.2,1); }
        .hero-slide { min-width:100%; height:100%; background-size:cover; background-position:center; position:relative; }
        /* Banner adı ve noktalar sağ üstte, menü hapının altındaki boş fotoğraf
           alanında durur — arama kutusuyla ve filtre barıyla çakışmaz. */
        .hero-slide-label { position:absolute; top:118px; right:36px; bottom:auto; background:rgba(255,255,255,.75); backdrop-filter:blur(8px); color:#0f172a; padding:7px 16px; border-radius:100px; font-size:12.5px; font-weight:700; z-index:2; border:1px solid rgba(255,255,255,.8); }
        .hero-overlay { position:absolute; inset:0; z-index:3; display:flex; align-items:center; pointer-events:none; padding-bottom:130px; }
        /* Koyu başlık her fotoğrafta okunsun diye soldan gelen beyaz perde */
        .hero-overlay::before { content:''; position:absolute; inset:0; background:linear-gradient(97deg, rgba(255,255,255,.94) 0%, rgba(255,255,255,.86) 24%, rgba(255,255,255,.58) 44%, rgba(255,255,255,.14) 63%, rgba(255,255,255,0) 78%); }
        .hero-overlay .container { pointer-events:auto; position:relative; }
        /* Referans tasarımda ok yok — otomatik döner, noktalarla da gezilir.
           Geri istenirse bu satırı silmek yeterli. */
        .hero-arrow { display:none; }
        .hero-dots { position:absolute; top:164px; right:38px; bottom:auto; left:auto; transform:none; z-index:6; display:flex; gap:9px; }
        .hero-dot { width:9px; height:9px; border-radius:50%; background:rgba(255,255,255,.7); border:1px solid rgba(15,23,42,.15); cursor:pointer; transition:all 0.3s; }
        .hero-dot.active { background:var(--accent); border-color:var(--accent); transform:scale(1.25); }
        .hero-dot:hover { background:#fff; }
        .hero-wave { position:absolute; left:0; right:0; bottom:-1px; z-index:5; line-height:0; pointer-events:none; }
        .hero-wave svg { display:block; width:100%; height:120px; }

        /* ── Hero güven şeridi ── */
        /* ── Kategori ağacı şeridi (mega menü) ──
           Filtre barından sonraki ilk blok. Üstündeki boşluğu .filter-bar-wrapper'ın
           margin-bottom'u veriyor (bkz. aşağısı), bu yüzden burada margin-top yok. */
        /* Katman sırası (hepsi dalganın z:5 üstünde):
           filtre barı 30 > kategori ağacı 20 > güven şeridi 6.
           Böylece filtre panelleri kategori şeridinin, kategori panelleri de
           güven şeridinin üstüne düşüyor. (position+z-index yığın bağlamı
           kurduğu için içerideki panellerin z:60'ı bu sırayı aşamaz.) */
        {{-- Yalnız yerleşim burada; şeridin kendi görünümü partials/mega-menu
             içinde durur. Bu @section('styles') bloğu HER modda sayfaya
             basıldığı için menüye özgü seçiciler (ve onları anan yorumlar)
             buraya yazılmamalı: 'filter' modunda menü hiç render edilmediği
             hâlde adı sayfaya sızar, HomeNavTest'in bekçisi haklı olarak kırılır.
             Blade yorumu kullanıldı — CSS yorumu çıktıya gider. --}}
        .mega-wrap { position:relative; z-index:20; margin:0 0 24px; }
        /* Filtre barı kapalıyken (HOME_NAV=mega) hero'ya bindirme olmadığı için
           boşluğu bu blok kendi verir. */
        .mega-wrap-solo { margin-top:40px; }

        .hero-trust { display:grid; grid-template-columns:repeat(4,1fr); gap:22px; margin-bottom:36px; padding-top:24px; border-top:1px solid var(--border); position:relative; z-index:6; }
        .hero-trust-item { display:flex; align-items:center; gap:13px; }
        .hti-ico { display:flex; align-items:center; justify-content:center; width:32px; height:32px; color:var(--accent); flex-shrink:0; }
        .hti-ico svg { width:28px; height:28px; }
        .hero-trust-item div { display:flex; flex-direction:column; line-height:1.35; min-width:0; }
        .hero-trust-item div b { font-size:15px; font-weight:800; color:#0f172a; }
        .hero-trust-item div span { font-size:13.5px; color:#64748b; font-weight:500; }

        /* ── Home Filter Bar ── */
        /* Hero'nun içine taşan yüzen hap barı: dalganın üstünde, fotoğrafın üzerinde durur */
        /* margin-bottom, hero'ya bindirme payını + dalganın yüksekliğini telafi eder:
           kendisinden sonraki blok (kategori ağacı ya da güven şeridi) her zaman
           dalganın ALTINDA, sayfa zemininde başlar. Komşu margin'ler birleştiği
           için sonraki bloğun kendi margin-top'u vermesine gerek yok. */
        .filter-bar-wrapper { margin-top:-210px; position:relative; z-index:30; margin-bottom:130px; }
        .filter-bar { background:rgba(255,255,255,.55); backdrop-filter:blur(10px); border:1px solid rgba(255,255,255,.7); border-radius:24px; box-shadow:0 24px 52px -26px rgba(15,23,42,.5); padding:10px 12px; }

        /* ── Yatay filtre barı (Etstur uyarlaması G2) ── */
        .yfilter-card { position:relative; display:block; }
        /* Masaüstünde haplar ortalanır ve sığmazsa alt satıra iner (yatay kaydırma
           kalırsa soldaki haplara ulaşılamıyordu); mobilde tek satır + kaydırma. */
        .ybar { display:flex; flex-wrap:wrap; gap:9px; align-items:center; justify-content:center; padding:2px; scrollbar-width:none; -ms-overflow-style:none; }
        .ybar::-webkit-scrollbar { display:none; }
        .ybtn { display:inline-flex; align-items:center; gap:9px; border:1px solid #eef2f6; background:#fff; border-radius:100px; padding:7px 16px 7px 7px; font-size:13.5px; font-weight:700; color:#0f172a; cursor:pointer; font-family:inherit; white-space:nowrap; flex-shrink:0; transition:all .18s; box-shadow:0 6px 14px -8px rgba(15,23,42,.35); }
        .ybtn:hover { border-color:#cbd5e1; transform:translateY(-1px); box-shadow:0 10px 20px -10px rgba(15,23,42,.4); }
        .ybtn.set { border-color:var(--accent); background:#f0fdfa; color:var(--accent-dark); }
        .yico { display:inline-flex; align-items:center; justify-content:center; width:30px; height:30px; border-radius:10px; background:#f0fdfa; color:var(--accent); flex-shrink:0; }
        .ybtn.set .yico { background:var(--accent); color:#fff; }
        .ycaret { display:inline-flex; color:#94a3b8; }
        .ybadge { display:none; font-style:normal; background:var(--accent); color:#fff; border-radius:100px; padding:1px 7px; font-size:10.5px; font-weight:800; }
        .ybadge.show { display:inline-block; }
        .ycount { margin-left:6px; font-size:12.5px; color:#475569; font-weight:700; white-space:nowrap; flex-shrink:0; }
        .ycount b { color:var(--accent); }
        .ysort { border:1px solid #eef2f6; background:#fff; border-radius:100px; padding:9px 12px; font-size:12.5px; font-weight:700; color:#475569; cursor:pointer; font-family:inherit; outline:none; flex-shrink:0; box-shadow:0 6px 14px -8px rgba(15,23,42,.35); }
        .yreset { border:none; background:none; color:#64748b; font-size:12px; font-weight:700; cursor:pointer; text-decoration:underline; font-family:inherit; flex-shrink:0; }
        .ychips { display:none; gap:5px; flex-wrap:wrap; justify-content:center; margin-top:8px; }
        .ychips.show { display:flex; }
        .ychips span { background:var(--accent-light); color:var(--accent-dark); border-radius:100px; padding:3px 10px; font-size:11.5px; font-weight:800; cursor:pointer; }

        .ypop { display:none; position:absolute; top:calc(100% + 6px); left:12px; background:#fff; border:1px solid #e2e8f0; border-radius:14px; box-shadow:0 18px 40px rgba(15,23,42,.18); padding:14px; z-index:60; width:260px; max-height:340px; overflow-y:auto; }
        .ypop.open { display:block; }
        .ypop h5 { margin:0 0 8px; font-size:11px; letter-spacing:.07em; text-transform:uppercase; color:#94a3b8; }
        .yopt { display:flex; align-items:center; gap:8px; font-size:13px; color:#475569; padding:5px 0; cursor:pointer; font-weight:500; }
        .yopt input { accent-color:var(--accent); width:15px; height:15px; }
        .yopt i { margin-left:auto; font-style:normal; font-size:10.5px; font-weight:700; color:#94a3b8; }
        .yopt-empty { font-size:12px; color:#94a3b8; padding:4px 0; }
        .ycat-item { width:100%; display:flex; align-items:center; gap:6px; border:none; background:none; padding:7px 8px; border-radius:9px; font-size:13px; font-weight:600; color:#334155; cursor:pointer; text-align:left; font-family:inherit; }
        .ycat-item:hover { background:#f1f5f9; }
        .ycat-item.sel { background:var(--accent-light); color:var(--accent-dark); font-weight:800; }
        .ycat-item i { margin-left:auto; font-style:normal; font-size:10.5px; font-weight:700; color:#94a3b8; }
        .ycat-child { padding-left:26px; font-weight:500; font-size:12.5px; }
        .ycat-children { display:none; }
        .ycat-children.open { display:block; }
        .ycat-caret { font-size:10px; color:#94a3b8; transition:transform .15s; display:inline-block; }
        .ycat-item.open .ycat-caret { transform:rotate(90deg); }
        .ymonths { display:grid; grid-template-columns:repeat(4,1fr); gap:6px; }
        .ymonth { display:flex; align-items:center; justify-content:center; border:1px solid #e2e8f0; background:#fff; border-radius:9px; padding:7px 0; font-size:11.5px; font-weight:700; cursor:pointer; color:#475569; }
        .ymonth input { position:absolute; opacity:0; pointer-events:none; }
        .ymonth.on { background:var(--accent); color:#fff; border-color:var(--accent); }

        /* ── Kırılım noktaları ──
           DİKKAT: bu blok, üstündeki koşulsuz .hero-* / .filter-bar-* kurallarından
           SONRA gelmeli. Aynı seçici + aynı özgüllükte son yazan kazanır; media
           query yukarı taşınırsa (önceden öyleydi) tablet düzeni hiç uygulanmaz. */
        @media(max-width:1150px) {
            .hero-carousel { height:700px; }
            .hero-title { font-size:44px; letter-spacing:-1.2px; }
            .hero-sub { font-size:16.5px; margin-bottom:28px; }
        }

        @media(max-width:992px) {
            .ycount { display:none; }
            .ypop { left:8px; right:8px; width:auto; }
            .ybar { flex-wrap:nowrap; justify-content:flex-start; overflow-x:auto; }
            .ychips { justify-content:flex-start; }
        }

        /* Tablet (769–980): arama kutusu alt alta iner, bu yüzden hero uzar ve
           filtre barı / kategori ağacı / güven şeridi buna göre kayar. */
        @media(max-width:980px) {
            .hero-carousel { height:920px; }
            .hero-overlay { padding-bottom:150px; }
            .hero-title { font-size:38px; }
            .hero-search-form { flex-wrap:wrap; }
            .hero-search-field { padding:9px 14px; }
            .hero-search-field.full-width { flex:0 0 100%; border-right:none; border-bottom:1px solid #eef2f6; }
            .hero-search-row { flex:0 0 100%; }
            .hero-search-row .split-field:first-child { border-right:1px solid #eef2f6; }
            .hero-search-btn { flex:0 0 100%; margin:8px 0 0; padding:14px 0; border-radius:14px; }
            .filter-bar-wrapper { margin-top:-180px; margin-bottom:160px; }
            .hero-trust { grid-template-columns:repeat(2,1fr); gap:18px; }
        }

        /* ── iPhone Style Story Cards ── */
        .stories-container { margin: 0 -20px; padding: 10px 20px; overflow: hidden; }
        /* Filtre aktifken: storyler mini avatar şeridine büzülür, Popüler
           Destinasyonlar gizlenir — filtrelenen turlar hemen görünür olur.
           (.iphone-card'daki transition:all morfu yumuşatır) */
        body.filters-active #storiesSection { padding-top:4px; }
        body.filters-active .iphone-card { width:56px; height:56px; border-radius:50%; box-shadow:0 4px 10px rgba(0,0,0,0.18); }
        body.filters-active .iphone-card-badge,
        body.filters-active .iphone-card-info { display:none; }
        body.filters-active .iphone-card:hover { transform:scale(1.1); }
        body.filters-active #destinationsSection { display:none; }
        .stories-scroll { display: flex; gap: 15px; overflow-x: auto; padding: 10px 0 20px; scrollbar-width: none; -ms-overflow-style: none; scroll-snap-type: x mandatory; }
        .stories-scroll::-webkit-scrollbar { display: none; }
        
        .iphone-card { flex-shrink: 0; width: 140px; height: 260px; border-radius: 20px; overflow: hidden; position: relative; cursor: pointer; transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); scroll-snap-align: start; box-shadow: 0 10px 25px rgba(0,0,0,0.15); border: 1px solid rgba(255,255,255,0.08); }
        .iphone-card:hover { transform: scale(1.03) translateY(-6px); box-shadow: 0 15px 35px rgba(0,0,0,0.25); }
        .iphone-card:active { transform: scale(0.96); }
        
        .iphone-card-img { width: 100%; height: 100%; object-fit: cover; transition: transform 6s linear; }
        .iphone-card:hover .iphone-card-img { transform: scale(1.15); }
        
        .iphone-card-overlay { position: absolute; inset: 0; background: linear-gradient(to top, rgba(0,0,0,0.85) 0%, rgba(0,0,0,0.2) 50%, transparent 100%); display: flex; flex-direction: column; justify-content: space-between; padding: 12px; color: #fff; }
        
        .iphone-card-badge { align-self: flex-start; background: rgba(255,255,255,0.15); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); padding: 4px 10px; border-radius: 100px; font-size: 9px; font-weight: 600; border: 1px solid rgba(255,255,255,0.15); }
        
        .iphone-card-info { transform: translateY(0); transition: transform 0.4s; }
        .iphone-card-city { font-size: 16px; font-weight: 800; margin-bottom: 0; letter-spacing: -0.3px; }
        .iphone-card-country { font-size: 11px; opacity: 0.8; font-weight: 500; }
        
        /* Premium Glow Effect */
        .iphone-card::after { content: ''; position: absolute; inset: 0; border-radius: 20px; box-shadow: inset 0 0 20px rgba(255,255,255,0.1); pointer-events: none; }
        .iphone-card:hover { transform: scale(1.05) translateY(-8px); box-shadow: 0 20px 40px rgba(0,0,0,0.3); }
        .iphone-card:hover::after { box-shadow: inset 0 0 30px rgba(255,255,255,0.2); }

        @media(min-width: 1200px) {
            .stories-scroll { justify-content: center; overflow: visible; gap: 20px; }
            .iphone-card { width: 160px; height: 300px; }
        }

        /* ── Story Viewer Modal (Backdrop) ── */
        .story-viewer { position: fixed; inset: 0; background: rgba(0,0,0,0.4); z-index: 9999; display: none; align-items: center; justify-content: center; color: #fff; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); }
        .story-viewer.active { display: flex; }
        
        /* ── Story Window (The focused content) ── */
        .story-window { width: 100%; height: 100%; background: #000; position: relative; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 30px 60px rgba(0,0,0,0.5); }
        
        .story-progress-container { position: absolute; top: 15px; left: 10px; right: 10px; display: flex; gap: 5px; z-index: 10; }
        .story-progress-bar { flex: 1; height: 2px; background: rgba(255,255,255,0.3); border-radius: 2px; overflow: hidden; }
        .story-progress-fill { height: 100%; background: #fff; width: 0%; transition: width 0.1s linear; }
        
        .story-header { position: absolute; top: 30px; left: 0; right: 0; padding: 0 15px; display: flex; justify-content: space-between; align-items: center; z-index: 10; }
        .story-user-info { display: flex; align-items: center; gap: 10px; }
        .story-avatar-small { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; border: 1px solid rgba(255,255,255,0.2); }
        .story-city-title { font-size: 14px; font-weight: 600; text-shadow: 0 1px 4px rgba(0,0,0,0.5); }
        .story-country-subtitle { font-size: 11px; opacity: 0.8; text-shadow: 0 1px 4px rgba(0,0,0,0.5); }
        .story-close { background: rgba(0,0,0,0.3); border: none; color: #fff; font-size: 18px; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); transition: all 0.2s; }
        .story-close:hover { background: rgba(255,255,255,0.2); transform: scale(1.1); }
        
        .story-content { flex: 1; position: relative; display: flex; align-items: center; justify-content: center; overflow: hidden; }
        .story-full-img { width: 100%; height: 100%; object-fit: cover; }
        
        .story-nav-btn { position: absolute; top: 0; bottom: 0; width: 25%; z-index: 5; cursor: pointer; }
        .story-prev { left: 0; }
        .story-next { right: 0; }
        
        .story-footer { position: absolute; bottom: 40px; left: 0; right: 0; padding: 0 20px; text-align: center; z-index: 10; }
        .story-btn { display: inline-block; background: #fff; color: #000; padding: 12px 40px; border-radius: 100px; font-weight: 700; font-size: 14px; text-decoration: none; transition: all 0.3s; box-shadow: 0 4px 15px rgba(0,0,0,0.3); }
        .story-btn:hover { transform: scale(1.05); box-shadow: 0 6px 20px rgba(0,0,0,0.4); }

        @media(min-width: 600px) {
            .story-window { width: 420px; height: 85vh; border-radius: 24px; border: 1px solid rgba(255,255,255,0.1); }
        }

        /* ================= MOBİL ANA SAYFA (turXtur Mobil 2a tasarımı) ================= */
        .story-item { flex:none; scroll-snap-align:start; }
        .story-mlabel { display:none; }
        .m-home, .m-alltours, .m-guarantee, .m-drop-badge { display:none; }

        @media(max-width:768px) {
            /* Tasarım sırası: storyler → hero/arama/istatistik → kategoriler → turlar */
            #homeMain { display:flex; flex-direction:column; }
            #homeMain > * { order:5; }
            #homeMain > #storiesSection { order:1; }
            #homeMain > .m-home { order:2; }
            #homeMain > .filter-bar-wrapper { order:3; }
            #homeMain > #tours-section { order:4; }
            .hero-carousel { display:none; }
            .hero-trust, .mega-wrap { display:none; }
            #destinationsSection { display:none !important; }

            /* Storyler: daire avatar şeridi (beyaz tam genişlik bant) */
            #storiesSection { padding:0 !important; margin:0 -16px !important; background:#fff; border-bottom:1px solid rgba(15,36,33,.07); }
            .stories-container { margin:0; padding:10px 16px 6px; }
            .stories-scroll { gap:14px; padding:4px 0 8px; }
            .story-item { display:flex; flex-direction:column; align-items:center; gap:6px; cursor:pointer; }
            .iphone-card, body.filters-active .iphone-card { width:64px; height:64px; border-radius:50%; padding:3px; background:linear-gradient(135deg,#0d9488,#5eead4); box-shadow:none; border:none; }
            .iphone-card-img { border-radius:50%; border:2.5px solid #fff; }
            .iphone-card-overlay { display:none; }
            .story-mlabel { display:block; font-size:10.5px; font-weight:700; color:#42544f; font-family:'Manrope',var(--font); }

            /* Mobil hero */
            .m-home { display:block; margin:0 -16px; font-family:'Manrope',var(--font); }
            .m-hero { position:relative; height:400px; overflow:hidden; }
            .m-hero > img { position:absolute; inset:0; width:100%; height:100%; object-fit:cover; animation:mkb 26s ease-in-out infinite alternate; }
            .m-hero-shade { position:absolute; inset:0; background:linear-gradient(to bottom, rgba(5,28,25,.35) 0%, rgba(5,28,25,.55) 55%, rgba(5,28,25,.85) 100%); }
            .m-hero-body { position:absolute; left:0; right:0; bottom:34px; padding:0 18px; }
            .m-hero-pill { display:inline-flex; background:rgba(94,234,212,.14); border:1px solid rgba(94,234,212,.35); color:#8ff5e2; font-size:10px; font-weight:700; letter-spacing:1.2px; padding:6px 12px; border-radius:100px; backdrop-filter:blur(6px); }
            .m-hero-title { margin:14px 0 8px; font-size:30px; line-height:1.1; font-weight:800; letter-spacing:-.8px; color:#fff; }
            .m-hero-title span { color:#5eead4; }
            .m-hero-sub { margin:0; font-size:13.5px; font-weight:500; color:rgba(255,255,255,.75); }
            .m-live { display:inline-flex; align-items:center; gap:7px; background:rgba(4,24,21,.55); backdrop-filter:blur(8px); border:1px solid rgba(255,255,255,.14); color:#fff; font-size:11px; font-weight:600; padding:7px 12px; border-radius:100px; margin-top:14px; }
            .m-live i { width:6px; height:6px; border-radius:50%; background:#5eead4; animation:mpulse 1.6s infinite; }

            /* Mobil arama kartı */
            .m-search { margin:-14px 16px 0; position:relative; z-index:5; background:#fff; border-radius:18px; box-shadow:0 16px 40px rgba(4,24,21,.18); padding:10px; display:flex; flex-direction:column; gap:8px; }
            .m-field { display:block; padding:9px 14px; border:1px solid rgba(15,36,33,.1); border-radius:12px; }
            .m-field span { display:block; font-size:9.5px; font-weight:800; letter-spacing:1px; color:#8a9a95; }
            .m-field input, .m-field select { border:none; outline:none; width:100%; font-family:'Manrope',var(--font); font-size:14px; font-weight:700; margin-top:2px; background:transparent; color:#0f2421; -webkit-appearance:none; appearance:none; }
            .m-search-row { display:grid; grid-template-columns:1fr 1fr; gap:8px; }
            .m-search-btn { background:linear-gradient(135deg,#0d9488,#0c6e63); color:#fff; border:none; font-family:'Manrope',var(--font); font-size:15px; font-weight:800; padding:14px; border-radius:12px; cursor:pointer; }

            /* İstatistikler 2x2 */
            .m-stats { display:grid; grid-template-columns:1fr 1fr; gap:10px; padding:22px 16px 0; }
            .m-stats > div { background:#fff; border:1px solid rgba(15,36,33,.07); border-radius:14px; padding:14px 16px; }
            .m-stats b { display:block; font-size:20px; font-weight:800; color:#0c332e; }
            .m-stats b.m-teal { color:var(--accent); }
            .m-stats span { font-size:11.5px; font-weight:600; color:#8a9a95; }

            /* Filtre kartı → sade pill şeridi */
            /* Masaüstünün hero'ya bindirme margin'i (-40px) mobilde sıfırlanır */
            .filter-bar-wrapper { background:transparent; box-shadow:none; border:none; padding:14px 0 0; margin:0 0 4px; }
            .filter-bar { background:transparent; box-shadow:none; border:none; padding:0; }
            .category-tab { border-radius:100px; font-size:12.5px; font-weight:600; padding:8px 15px; background:#fff; border:1px solid rgba(15,36,33,.1); color:#42544f; }
            .category-tab.active { background:var(--accent); border-color:var(--accent); color:#fff; box-shadow:none; }
            .filter-select-group { display:none; }

            /* Tur kartları: yatay düzen */
            #tour-grid-container .grid-4 { display:flex; flex-direction:column; gap:12px; }
            #tour-grid-container .card { position:relative; display:flex; flex-direction:row; border-radius:16px; border:1px solid rgba(15,36,33,.09); overflow:hidden; }
            #tour-grid-container .card-img { width:118px; min-width:118px; height:auto; min-height:110px; object-fit:cover; }
            #tour-grid-container .card-body { padding:12px 14px; flex:1; display:flex; flex-direction:column; gap:5px; }
            #tour-grid-container .card-title { font-size:13px; font-weight:700; line-height:1.35; font-family:'Manrope',var(--font); }
            #tour-grid-container .card-meta { font-family:ui-monospace,monospace; font-size:10.5px; color:#7d938d; }
            #tour-grid-container .card-price-row { margin-top:auto !important; border-top:1px dashed rgba(15,36,33,.12); padding-top:8px; display:flex; align-items:baseline; gap:4px; flex-wrap:wrap; }
            #tour-grid-container .price-tag { font-family:'Space Grotesk',var(--font); font-size:16px !important; color:#08211d; }
            .m-drop-badge { display:inline-block; position:absolute; top:0; left:0; background:#08211d; color:#5eead4; font-family:ui-monospace,monospace; font-size:9px; font-weight:700; padding:4px 8px; border-radius:0 0 10px 0; z-index:2; }

            /* CTA + güven kartı */
            .m-alltours { display:block; text-align:center; margin-top:14px; border:1.5px solid rgba(15,36,33,.12); color:#0c6e63; font-size:13.5px; font-weight:700; padding:12px; border-radius:12px; text-decoration:none; font-family:'Manrope',var(--font); }
            .m-guarantee { display:flex; flex-direction:column; gap:18px; margin-top:26px; background:#0c332e; border-radius:18px; padding:22px 20px; font-family:'Manrope',var(--font); }
            .m-guarantee > div { display:flex; gap:13px; align-items:flex-start; }
            .m-guarantee span { width:38px; height:38px; flex:none; border-radius:11px; background:rgba(94,234,212,.14); color:#5eead4; display:flex; align-items:center; justify-content:center; font-size:16px; font-weight:800; }
            .m-guarantee b { display:block; font-size:14px; font-weight:800; color:#fff; }
            .m-guarantee p { font-size:12px; color:rgba(255,255,255,.6); margin:3px 0 0; line-height:1.5; }
        }
        @keyframes mkb { from { transform:scale(1); } to { transform:scale(1.08); } }
        @keyframes mpulse { 0%,100% { opacity:1; } 50% { opacity:.35; } }
@endsection

@push('scripts')
<script>
(function() {
    let currentSlide = 0;
    const slides = document.querySelectorAll('.hero-slide');
    const slidesContainer = document.getElementById('heroSlides');
    const dots = document.querySelectorAll('.hero-dot');
    const totalSlides = slides.length;
    let autoPlay;

    function updateSlide() {
        slidesContainer.style.transform = 'translateX(-' + (currentSlide * 100) + '%)';
        dots.forEach((d, i) => d.classList.toggle('active', i === currentSlide));
    }

    window.slideCarousel = function(dir) {
        currentSlide = (currentSlide + dir + totalSlides) % totalSlides;
        updateSlide();
        resetAutoPlay();
    };

    window.goToSlide = function(index) {
        currentSlide = index;
        updateSlide();
        resetAutoPlay();
    };

    function resetAutoPlay() {
        clearInterval(autoPlay);
        autoPlay = setInterval(function() { window.slideCarousel(1); }, 5000);
    }

    resetAutoPlay();
})();

// --- Animated Search Placeholder ---
// Referans tasarımda "Nereye?" alanı sabit örnek metin gösteriyor
// ("Örn. Karadeniz, Bali, Yunanistan"), daktilo animasyonu yok. Animasyonu
// geri istersen DAKTILO_ACIK'i true yap, kod olduğu gibi duruyor.
(function() {
    const DAKTILO_ACIK = false;
    const input = document.getElementById('heroSearchInput');
    if (!input || !DAKTILO_ACIK) return;
    const phrases = [
        'Kapadokya turlarını keşfet...',
        'Bodrum tatil fırsatları...',
        'Antalya rafting turları...',
        'Yunan Adaları cruise...',
        'Karadeniz yaylaları...',
        'Maldivler balayı...'
    ];
    let phraseIdx = 0, charIdx = 0, deleting = false;

    function tick() {
        if (document.activeElement === input || input.value !== '') return;
        const phrase = phrases[phraseIdx];
        if (!deleting) {
            charIdx++;
            input.placeholder = phrase.slice(0, charIdx);
            if (charIdx === phrase.length) {
                deleting = true;
                setTimeout(tick, 2000);
                return;
            }
            setTimeout(tick, 80);
        } else {
            charIdx--;
            input.placeholder = phrase.slice(0, charIdx);
            if (charIdx === 0) {
                deleting = false;
                phraseIdx = (phraseIdx + 1) % phrases.length;
                setTimeout(tick, 400);
                return;
            }
            setTimeout(tick, 40);
        }
    }
    setTimeout(tick, 1500);

    input.addEventListener('focus', () => { input.placeholder = 'Tur veya destinasyon ara...'; });
    input.addEventListener('blur', () => { if (input.value === '') { charIdx = 0; deleting = false; setTimeout(tick, 500); } });
})();

// --- Yatay Filtre Barı (G2 Etstur uyarlaması): panel + canlı sayaç + AJAX ---
document.addEventListener('DOMContentLoaded', function() {
    const filterForm = document.getElementById('home-filter-form');
    if (!filterForm) return;

    const tourGridContainer = document.getElementById('tour-grid-container');
    const loadingSpinner = document.getElementById('loading-spinner');
    const categoryInput = document.getElementById('selected-category');
    const liveCount = document.getElementById('yLiveCount');
    const chipsRow = document.getElementById('yChips');

    // ---- panel aç/kapa ----
    document.querySelectorAll('#yBar .ybtn').forEach(btn => btn.addEventListener('click', (e) => {
        e.stopPropagation();
        const pop = document.getElementById(btn.dataset.ypop);
        const wasOpen = pop.classList.contains('open');
        filterForm.querySelectorAll('.ypop').forEach(p => p.classList.remove('open'));
        if (!wasOpen) {
            const maxLeft = Math.max(12, filterForm.clientWidth - 280);
            pop.style.left = Math.min(btn.offsetLeft, maxLeft) + 'px';
            pop.classList.add('open');
        }
    }));
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.ypop') && !e.target.closest('.ybtn'))
            filterForm.querySelectorAll('.ypop').forEach(p => p.classList.remove('open'));
    });

    // ---- form durumu okuma ----
    function formState() {
        const fd = new FormData(filterForm);
        return {
            category: (fd.get('category') || '').trim(),
            destinations: fd.getAll('destinations[]'),
            months: fd.getAll('months[]'),
            special: (fd.get('special') || '').trim(),
            visa: fd.getAll('visa[]'),
            days: fd.getAll('days[]'),
            departures: fd.getAll('departures[]'),
            budget_max: (fd.get('budget_max') || '').trim(),
        };
    }

    function filtersAreActive() {
        const s = formState();
        return !!(s.category || s.destinations.length || s.months.length || s.special
            || s.visa.length || s.days.length || s.departures.length || s.budget_max);
    }

    function updateStoriesCompact() {
        document.body.classList.toggle('filters-active', filtersAreActive());
    }

    // ---- rozetler + çipler ----
    const AY = ['', 'Oca', 'Şub', 'Mar', 'Nis', 'May', 'Haz', 'Tem', 'Ağu', 'Eyl', 'Eki', 'Kas', 'Ara'];
    const SPECIAL_LABELS = @json(collect($specialPeriods)->map(fn ($p) => $p['label']));

    function setBadge(key, count) {
        const b = document.querySelector('[data-yb="' + key + '"]');
        if (!b) return;
        b.textContent = count;
        b.classList.toggle('show', count > 0);
        b.closest('.ybtn').classList.toggle('set', count > 0);
    }

    function chip(label, onRemove) {
        const s = document.createElement('span');
        s.textContent = label + ' ✕';
        s.addEventListener('click', () => { onRemove(); onChanged(); });
        chipsRow.appendChild(s);
    }

    function refreshBarUi() {
        const s = formState();
        setBadge('category', s.category ? 1 : 0);
        setBadge('destinations', s.destinations.length);
        setBadge('months', s.months.length);
        setBadge('special', s.special ? 1 : 0);
        setBadge('visa', s.visa.length);
        setBadge('days', s.days.length);
        setBadge('departures', s.departures.length);
        setBadge('budget_max', s.budget_max ? 1 : 0);

        chipsRow.innerHTML = '';
        const selCat = filterForm.querySelector('.ycat-item.sel');
        if (s.category && selCat) chip(selCat.textContent.replace(/\d+\s*$/, '').trim(), () => selectCategory(''));
        s.destinations.forEach(v => chip('📍 ' + v, () => uncheck('destinations[]', v)));
        s.months.forEach(v => chip('🗓️ ' + (AY[parseInt(v, 10)] || v), () => uncheck('months[]', v)));
        if (s.special) chip('🎉 ' + (SPECIAL_LABELS[s.special] || s.special), () => {
            const r = filterForm.querySelector('input[name="special"][value=""]');
            if (r) r.checked = true;
        });
        s.visa.forEach(v => chip(v === 'vizesiz' ? '✈️ Vizesiz' : '🛂 Vizeli', () => uncheck('visa[]', v)));
        s.days.forEach(v => chip('⏱️ ' + v + ' gün', () => uncheck('days[]', v)));
        s.departures.forEach(v => chip('🛫 ' + v, () => uncheck('departures[]', v)));
        if (s.budget_max) chip('💸 ≤ ' + s.budget_max + '.000 ₺', clearBudget);
        const anyChip = chipsRow.children.length > 0;
        if (anyChip) {
            const c = document.createElement('span');
            c.style.background = 'transparent'; c.style.color = '#94a3b8'; c.style.textDecoration = 'underline';
            c.textContent = 'Temizle';
            c.addEventListener('click', resetAll);
            chipsRow.appendChild(c);
        }
        chipsRow.classList.toggle('show', anyChip);
    }

    function uncheck(name, value) {
        filterForm.querySelectorAll('input[name="' + name + '"]').forEach(i => {
            if (i.value === value) i.checked = false;
        });
        syncMonthPills();
    }

    // ---- AJAX ----
    let fetchTimer = null;
    function fetchFilteredTours() {
        if (!tourGridContainer) return;
        updateStoriesCompact();

        const params = new URLSearchParams(new FormData(filterForm));
        for (const [k, v] of [...params]) { if (!v) params.delete(k); }
        const qs = params.toString();
        history.replaceState({}, '', qs ? (filterForm.action + '?' + qs) : filterForm.action);

        if (loadingSpinner) loadingSpinner.style.display = 'block';
        tourGridContainer.style.opacity = '0.5';

        fetch(filterForm.action + (qs ? '?' + qs : ''), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.json())
        .then(data => {
            const swap = () => {
                tourGridContainer.innerHTML = data.html;
                tourGridContainer.style.opacity = '1';
            };
            if (document.startViewTransition) document.startViewTransition(swap);
            else swap();
            if (liveCount && typeof data.count === 'number') {
                liveCount.textContent = new Intl.NumberFormat('tr-TR').format(data.count);
            }
        })
        .catch(err => {
            console.error('Filter error:', err);
            tourGridContainer.style.opacity = '1';
        })
        .finally(() => {
            if (loadingSpinner) loadingSpinner.style.display = 'none';
        });
    }

    function onChanged(debounceMs) {
        refreshBarUi();
        clearTimeout(fetchTimer);
        fetchTimer = setTimeout(fetchFilteredTours, debounceMs || 0);
    }

    // ---- kategori (tek seçim + üst kategori akordeonu) ----
    function collapseCategoryBranches() {
        filterForm.querySelectorAll('.ycat-children.open').forEach(p => p.classList.remove('open'));
        filterForm.querySelectorAll('.ycat-item.open').forEach(b => {
            b.classList.remove('open');
            b.setAttribute('aria-expanded', 'false');
        });
    }
    function selectCategory(slug) {
        categoryInput.value = slug;
        filterForm.querySelectorAll('.ycat-item').forEach(b => b.classList.toggle('sel', b.dataset.cat === slug));
        if (!slug) collapseCategoryBranches(); // Tümü/temizle: dallar kapanır
    }
    filterForm.querySelectorAll('.ycat-item').forEach(b => b.addEventListener('click', () => {
        const deselecting = b.dataset.cat === categoryInput.value;
        selectCategory(deselecting ? '' : b.dataset.cat);
        // Üst kategori: seçilince kendi dalı açılır (diğerleri kapanır),
        // tekrar tıklanıp bırakılınca kapanır; alt kategori dalları etkilemez
        if (b.hasAttribute('data-parent')) {
            const panel = filterForm.querySelector('.ycat-children[data-children-of="' + b.dataset.cat + '"]');
            collapseCategoryBranches();
            if (panel && !deselecting) {
                panel.classList.add('open');
                b.classList.add('open');
                b.setAttribute('aria-expanded', 'true');
            }
        }
        onChanged();
    }));

    // ---- ay çipleri (checkbox + görsel durum) ----
    function syncMonthPills() {
        filterForm.querySelectorAll('.ymonth').forEach(l => {
            const i = l.querySelector('input');
            l.classList.toggle('on', i.checked);
        });
    }
    filterForm.querySelectorAll('.ymonth input').forEach(i => i.addEventListener('change', () => {
        syncMonthPills(); onChanged();
    }));

    // ---- diğer alanlar ----
    filterForm.querySelectorAll('#ypDest input, #ypSpecial input, #ypVisa input, #ypDays input, #ypDep input')
        .forEach(i => i.addEventListener('change', () => onChanged()));
    filterForm.querySelector('.ysort').addEventListener('change', () => onChanged());

    const budgetRange = document.getElementById('yBudgetRange');
    const budgetInput = document.getElementById('yBudgetInput');
    const budgetLabel = document.getElementById('yBudgetLabel');
    budgetRange.addEventListener('input', () => {
        const v = parseInt(budgetRange.value, 10);
        budgetInput.value = v >= 100 ? '' : v;
        budgetLabel.textContent = v >= 100 ? 'sınırsız' : '≤ ' + v + '.000 ₺';
        onChanged(250);
    });
    function clearBudget() {
        budgetRange.value = 100; budgetInput.value = '';
        budgetLabel.textContent = 'sınırsız';
    }

    // ---- sıfırla ----
    function resetAll() {
        selectCategory('');
        filterForm.querySelectorAll('input[type="checkbox"]').forEach(i => i.checked = false);
        const noSpecial = filterForm.querySelector('input[name="special"][value=""]');
        if (noSpecial) noSpecial.checked = true;
        clearBudget();
        syncMonthPills();
        onChanged();
    }
    document.getElementById('yReset').addEventListener('click', resetAll);

    // Sayfa URL parametreleriyle geldiyse rozet/çip/kompakt durumla başla
    refreshBarUi();
    updateStoriesCompact();
});

// --- Instagram Story Logic (kendi DOMContentLoaded sarmalayıcısında; kapanışı window.* atamalarından sonraki `});`) ---
document.addEventListener('DOMContentLoaded', function() {
let currentCityIndex = 0;
let currentSlideIndex = 0;
let storyTimer = null;
const storyDuration = 5000; // 5 seconds
const featuredCities = @json($featuredCities);
const storyViewer = document.getElementById('story-viewer');
const progressContainer = document.getElementById('story-progress');

function openStory(index) {
    currentCityIndex = index;
    currentSlideIndex = 0;
    storyViewer.classList.add('active');
    document.body.style.overflow = 'hidden'; // Prevent scroll
    showStory();
}

function closeStory() {
    storyViewer.classList.remove('active');
    document.body.style.overflow = '';
    clearTimeout(storyTimer);
}

function createProgressBars() {
    progressContainer.innerHTML = '';
    const city = featuredCities[currentCityIndex];
    city.images.forEach((_, i) => {
        const bar = document.createElement('div');
        bar.className = 'story-progress-bar';
        const fill = document.createElement('div');
        fill.className = 'story-progress-fill';
        bar.appendChild(fill);
        progressContainer.appendChild(bar);
    });
}

function showStory() {
    clearTimeout(storyTimer);
    const city = featuredCities[currentCityIndex];
    const images = city.images;
    
    // If it's the first slide of a city, recreate progress bars
    if (currentSlideIndex === 0 || progressContainer.children.length !== images.length) {
        createProgressBars();
    }
    
    // Update content
    document.getElementById('story-image').src = images[currentSlideIndex];
    document.getElementById('story-avatar').src = images[0];
    document.getElementById('story-city-name').textContent = city.name;
    document.getElementById('story-country-name').textContent = city.country;
    document.getElementById('story-cta').href = (city.link && city.link.trim() !== '')
        ? city.link
        : `/turlar?q=${encodeURIComponent(city.name)}`;
    
    // Update progress bars
    const fills = document.querySelectorAll('.story-progress-fill');
    fills.forEach((fill, i) => {
        fill.style.transition = 'none';
        if (i < currentSlideIndex) {
            fill.style.width = '100%';
        } else if (i > currentSlideIndex) {
            fill.style.width = '0%';
        } else {
            fill.style.width = '0%';
            // Trigger reflow to apply transition
            fill.offsetHeight; 
            fill.style.transition = `width ${storyDuration}ms linear`;
            fill.style.width = '100%';
        }
    });

    storyTimer = setTimeout(nextStory, storyDuration);
}

function nextStory() {
    const city = featuredCities[currentCityIndex];
    if (currentSlideIndex < city.images.length - 1) {
        // Next slide in same city
        currentSlideIndex++;
        showStory();
    } else {
        // Move to next city
        if (currentCityIndex < featuredCities.length - 1) {
            currentCityIndex++;
            currentSlideIndex = 0;
            showStory();
        } else {
            closeStory();
        }
    }
}

function prevStory() {
    if (currentSlideIndex > 0) {
        // Previous slide in same city
        currentSlideIndex--;
        showStory();
    } else {
        // Move to previous city's last slide
        if (currentCityIndex > 0) {
            currentCityIndex--;
            currentSlideIndex = featuredCities[currentCityIndex].images.length - 1;
            showStory();
        } else {
            // Restart first slide of first city
            currentSlideIndex = 0;
            showStory();
        }
    }
}

// Global functions for HTML onclick
window.openStory = openStory;
window.closeStory = closeStory;
window.nextStory = nextStory;
window.prevStory = prevStory;

});
</script>
@endpush
