@extends('layouts.app')
@section('title', 'turXtur — Tur Karşılaştırma Platformu')

@push('head')
<script type="application/ld+json">
{
  "@@context": "https://schema.org",
  "@@graph": [
    {
      "@@type": "WebSite",
      "url": "{{ url('/') }}",
      "name": "turXtur",
      "potentialAction": {
        "@@type": "SearchAction",
        "target": "{{ route('tours.index') }}?q={search_term_string}",
        "query-input": "required name=search_term_string"
      }
    },
    {
      "@@type": "Organization",
      "name": "turXtur",
      "url": "{{ url('/') }}",
      "logo": "{{ asset('images/og-default.png') }}"
    }
  ]
}
</script>
@endpush

@section('content')
{{-- Hero Banner Carousel --}}
@php
    $carouselBanners = $banners->count() ? $banners : collect([
        (object)['title'=>'Kapadokya', 'image_url'=>asset('images/banners/cappadocia.png'), 'blur'=>0, 'darkness'=>40],
        (object)['title'=>'Bodrum', 'image_url'=>asset('images/banners/bodrum.png'), 'blur'=>0, 'darkness'=>40],
        (object)['title'=>'Mısır Piramitleri', 'image_url'=>asset('images/banners/egypt.png'), 'blur'=>0, 'darkness'=>40],
        (object)['title'=>'Karadeniz Yaylaları', 'image_url'=>asset('images/banners/karadeniz.png'), 'blur'=>0, 'darkness'=>40],
        (object)['title'=>'Güneydoğu Anadolu', 'image_url'=>asset('images/banners/guneydogu.png'), 'blur'=>0, 'darkness'=>40],
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
            <h1 style="font-size:42px;font-weight:800;letter-spacing:-1px;margin-bottom:12px;line-height:1.15;color:#fff;text-shadow:0 2px 12px rgba(0,0,0,0.3);">
                Hayalindeki turu<br><span style="color:#5eead4;">en uygun fiyatla</span> bul
            </h1>
            <p style="font-size:17px;color:rgba(255,255,255,0.9);margin-bottom:32px;text-shadow:0 1px 6px rgba(0,0,0,0.3);">
                {{ $agencyCount }} acentadan {{ $tourCount }} tur karşılaştırıyoruz
            </p>
            {{-- Search bar --}}
            <form action="{{ route('tours.index') }}" method="GET" class="hero-search-form">
                <div class="hero-search-field full-width">
                    <label>Nereye?</label>
                    <input type="text" name="q" id="heroSearchInput" placeholder="Tur veya destinasyon ara...">
                </div>
                <div class="hero-search-row">
                    <div class="hero-search-field split-field">
                        <label>Ne Zaman?</label>
                        <input type="date" name="date_start">
                    </div>
                    <div class="hero-search-field split-field">
                        <label>Bütçe (Kişi başı)</label>
                        <select name="max_price">
                            <option value="">Farketmez</option>
                            <option value="5000">Max 5.000 ₺</option>
                            <option value="10000">Max 10.000 ₺</option>
                            <option value="20000">Max 20.000 ₺</option>
                            <option value="30000">Max 30.000 ₺</option>
                            <option value="50000">Max 50.000 ₺</option>
                        </select>
                    </div>
                </div>
                <button type="submit" class="hero-search-btn">
                    Ara
                </button>
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
</div>

<div class="container" id="homeMain">
    {{-- Mega menü (gezinme) + filtre barı (daraltma) tek kartta: menü üstte,
         filtre altında. İkisi farklı iş görüyor, biri diğerinin yerine geçmiyor.
         Hangisinin görüneceği config/ui.php: home_nav ile seçilir — üç modun
         hiçbiri kod silmez, .env'de HOME_NAV çevirmek yeterli. --}}
    @php $homeNav = config('ui.home_nav', 'both'); @endphp
    <div class="filter-bar-wrapper">
        @if(in_array($homeNav, ['both', 'mega'], true))
            @include('partials.mega-menu')
        @endif
        <form id="home-filter-form" action="{{ route('home') }}" method="GET" class="filter-bar yfilter-card"
              @if($homeNav === 'mega') style="display:none;" @endif>
            <input type="hidden" name="category" id="selected-category" value="{{ request('category') }}">

            <div class="ybar" id="yBar">
                <button type="button" class="ybtn" data-ypop="ypCat">🏷️ Kategoriler <b class="ybadge" data-yb="category"></b> <span>▾</span></button>
                <button type="button" class="ybtn" data-ypop="ypDest">📍 Destinasyon <b class="ybadge" data-yb="destinations"></b> <span>▾</span></button>
                <button type="button" class="ybtn" data-ypop="ypMonth">🗓️ Dönem <b class="ybadge" data-yb="months"></b> <span>▾</span></button>
                <button type="button" class="ybtn" data-ypop="ypSpecial">🎉 Özel Günler <b class="ybadge" data-yb="special"></b> <span>▾</span></button>
                <button type="button" class="ybtn" data-ypop="ypVisa">🛂 Vize <b class="ybadge" data-yb="visa"></b> <span>▾</span></button>
                <button type="button" class="ybtn" data-ypop="ypDays">⏱️ Süre <b class="ybadge" data-yb="days"></b> <span>▾</span></button>
                <button type="button" class="ybtn" data-ypop="ypDep">🛫 Kalkış <b class="ybadge" data-yb="departures"></b> <span>▾</span></button>
                <button type="button" class="ybtn" data-ypop="ypBudget">💸 Bütçe <b class="ybadge" data-yb="budget_max"></b> <span>▾</span></button>

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
        /* ── Hero Search Form ── */
        .hero-search-form { display:flex; flex-wrap:nowrap; border-radius:100px; overflow:hidden; background:rgba(255,255,255,0.95); backdrop-filter:blur(12px); box-shadow:0 8px 32px rgba(0,0,0,0.15); width:100%; max-width:800px; align-items:stretch; }
        .hero-search-field { padding:12px 24px; display:flex; flex-direction:column; justify-content:center; border-right:1px solid #e2e8f0; }
        .hero-search-field.full-width { flex:2; min-width:200px; }
        .hero-search-row { display:flex; flex:3; }
        .hero-search-field.split-field { flex:1; min-width:150px; }
        .hero-search-field label { font-size:11px; font-weight:800; text-transform:uppercase; color:#0f172a; margin-bottom:2px; letter-spacing:0.5px; }
        .hero-search-field input, .hero-search-field select { border:none; outline:none; font-family:var(--font); font-size:15px; width:100%; background:transparent; color:#475569; font-weight:500; }
        .hero-search-field select { cursor:pointer; appearance:none; font-size:14px; }
        .hero-search-field input[type="date"] { font-size:14px; cursor:pointer; }
        .hero-search-btn { background:var(--accent); color:white; border:none; padding:0 32px; font-family:var(--font); font-size:16px; font-weight:700; cursor:pointer; white-space:nowrap; transition:background 0.2s; display:flex; align-items:center; justify-content:center; gap:8px; }
        .hero-search-btn span { display:inline-block; padding:22px 0; }
        .hero-search-btn:hover { background:var(--accent-dark); }
        
        @media(max-width: 768px) {
            .hero-carousel { height:640px !important; }
            .hero-overlay { align-items:flex-end !important; padding-bottom:100px; }
            .hero-overlay h1 { font-size:26px !important; margin-bottom:8px !important; }
            .hero-overlay p { font-size:14px !important; margin-bottom:20px !important; }
            
            .hero-search-form { 
                flex-direction:column; 
                border-radius:16px; 
                background:#fff; 
                padding:16px; 
                box-shadow:0 12px 40px rgba(0,0,0,0.15);
            }
            .hero-search-field.full-width { 
                width:100% !important; 
                border-right:none !important; 
                border-bottom:1px solid #e5e7eb; 
                padding:4px 0 16px 0 !important; 
            }
            .hero-search-row { display:flex; width:100%; border-bottom:1px solid #e5e7eb; }
            .hero-search-field.split-field { 
                width:50% !important; 
                border-bottom:none !important; 
                border-right:none !important;
                padding:16px 0 !important; 
            }
            .hero-search-field.split-field:first-child { 
                border-right:1px solid #e5e7eb !important; 
                padding-right:16px !important; 
            }
            .hero-search-field.split-field:last-child { 
                padding-left:16px !important; 
            }
            .hero-search-btn { 
                width:100%; 
                border-radius:10px; 
                padding:14px; 
                margin-top:16px;
            }
            .hero-search-btn span { padding:0 !important; }
        }

        /* ── Hero Carousel ── */
        .hero-carousel { position:relative; width:100vw; margin-left:calc(-50vw + 50%); height:650px; overflow:hidden; }
        .hero-slides { display:flex; height:100%; transition:transform 0.8s cubic-bezier(0.4,0,0.2,1); }
        .hero-slide { min-width:100%; height:100%; background-size:cover; background-position:center; position:relative; }
        .hero-slide::after { content:''; position:absolute; inset:0; background:linear-gradient(to right, rgba(0,0,0,0.55) 0%, rgba(0,0,0,0.25) 50%, rgba(0,0,0,0.1) 100%); }
        .hero-slide-label { position:absolute; bottom:24px; right:32px; background:rgba(255,255,255,0.15); backdrop-filter:blur(8px); color:white; padding:8px 18px; border-radius:100px; font-size:13px; font-weight:600; z-index:2; letter-spacing:0.3px; border:1px solid rgba(255,255,255,0.2); }
        .hero-overlay { position:absolute; inset:0; z-index:3; display:flex; align-items:center; pointer-events:none; }
        .hero-overlay .container { pointer-events:auto; }
        .hero-arrow { position:absolute; top:50%; transform:translateY(-50%); z-index:4; background:rgba(255,255,255,0.15); backdrop-filter:blur(6px); border:1px solid rgba(255,255,255,0.2); color:white; width:48px; height:48px; border-radius:50%; font-size:18px; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:all 0.3s; }
        .hero-arrow:hover { background:rgba(255,255,255,0.3); transform:translateY(-50%) scale(1.05); }
        .hero-arrow-left { left:20px; }
        .hero-arrow-right { right:20px; }
        .hero-dots { position:absolute; bottom:24px; left:50%; transform:translateX(-50%); z-index:4; display:flex; gap:10px; }
        .hero-dot { width:10px; height:10px; border-radius:50%; background:rgba(255,255,255,0.4); cursor:pointer; transition:all 0.3s; border:2px solid transparent; }
        .hero-dot.active { background:white; transform:scale(1.2); border-color:rgba(255,255,255,0.6); box-shadow:0 0 8px rgba(255,255,255,0.4); }
        .hero-dot:hover { background:rgba(255,255,255,0.7); }
        @media(max-width:768px) {
            .hero-carousel { height:520px; }
            .hero-overlay h1 { font-size:28px !important; }
            .hero-arrow { width:36px; height:36px; font-size:14px; }
        }

        /* ── Home Filter Bar ── */
        .filter-bar-wrapper { margin-top:-40px; position:relative; z-index:10; margin-bottom:32px; }
        .filter-bar { background:white; border-radius:20px; box-shadow:0 15px 40px -10px rgba(0,0,0,0.12); padding:12px; border:1px solid #f1f5f9; }

        /* ── Yatay filtre barı (Etstur uyarlaması G2) ── */
        .yfilter-card { position:relative; display:block; }
        .ybar { display:flex; gap:8px; align-items:center; overflow-x:auto; padding:2px; scrollbar-width:none; -ms-overflow-style:none; }
        .ybar::-webkit-scrollbar { display:none; }
        .ybtn { display:inline-flex; align-items:center; gap:6px; border:1.5px solid #e2e8f0; background:#f8fafc; border-radius:100px; padding:9px 14px; font-size:13px; font-weight:700; color:#334155; cursor:pointer; font-family:inherit; white-space:nowrap; flex-shrink:0; transition:all .15s; }
        .ybtn:hover { border-color:#cbd5e1; background:#f1f5f9; }
        .ybtn.set { border-color:var(--accent); background:#f0fdfa; color:var(--accent-dark); }
        .ybtn > span { color:#94a3b8; font-size:10px; }
        .ybadge { display:none; font-style:normal; background:var(--accent); color:#fff; border-radius:100px; padding:1px 7px; font-size:10.5px; font-weight:800; }
        .ybadge.show { display:inline-block; }
        .ycount { margin-left:auto; font-size:12.5px; color:#475569; font-weight:700; white-space:nowrap; flex-shrink:0; padding-left:8px; }
        .ycount b { color:var(--accent); }
        .ysort { border:1.5px solid #e2e8f0; background:#f8fafc; border-radius:100px; padding:8px 12px; font-size:12.5px; font-weight:700; color:#475569; cursor:pointer; font-family:inherit; outline:none; flex-shrink:0; }
        .yreset { border:none; background:none; color:#94a3b8; font-size:12px; font-weight:700; cursor:pointer; text-decoration:underline; font-family:inherit; flex-shrink:0; }
        .ychips { display:none; gap:5px; flex-wrap:wrap; margin-top:8px; }
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

        @media(max-width:992px) {
            .ycount { display:none; }
            .ypop { left:8px; right:8px; width:auto; }
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
(function() {
    const input = document.getElementById('heroSearchInput');
    if (!input) return;
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
