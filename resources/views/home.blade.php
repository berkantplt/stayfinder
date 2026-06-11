@extends('layouts.app')
@section('title', 'StayFinder — Tur Karşılaştırma Platformu')

@push('head')
<script type="application/ld+json">
{
  "@@context": "https://schema.org",
  "@@graph": [
    {
      "@@type": "WebSite",
      "url": "{{ url('/') }}",
      "name": "StayFinder",
      "potentialAction": {
        "@@type": "SearchAction",
        "target": "{{ route('tours.index') }}?q={search_term_string}",
        "query-input": "required name=search_term_string"
      }
    },
    {
      "@@type": "Organization",
      "name": "StayFinder",
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

<div class="container">
    {{-- Filter Bar --}}
    <div class="filter-bar-wrapper">
        <form id="home-filter-form" action="{{ route('home') }}" method="GET" class="filter-bar">
            <input type="hidden" name="category" id="selected-category" value="">
            
            {{-- Ana Kategoriler (1. Satır) --}}
            <div class="category-tabs-container">
                <div class="category-tabs" id="categoryTabs">
                    <button type="button" class="category-tab active" data-category="" data-children='[]'>
                        <span class="category-icon">🏷️</span>
                        <span class="category-name">Tümü</span>
                    </button>
                    @foreach($categories as $cat)
                    <button type="button" class="category-tab" data-category="{{ $cat->slug }}" data-children='@json($cat->children->map(fn($c) => ["slug" => $c->slug, "name" => $c->name, "icon" => $c->icon]))'>
                        <span class="category-icon">{{ $cat->icon }}</span>
                        <span class="category-name">{{ $cat->name }}</span>
                    </button>
                    @endforeach
                </div>
            </div>

            {{-- Alt Kategoriler (2. Satır — dinamik) --}}
            <div class="subcategory-row" id="subcategoryRow">
                <div class="subcategory-tabs" id="subcategoryTabs"></div>
            </div>

            <div class="filter-dropdowns">
                <div class="filter-select-group">
                    <select name="destination" class="home-filter-select">
                        <option value="">Destinasyon Seç</option>
                        @foreach($allDestinations as $dest)
                            <option value="{{ $dest }}" {{ request('destination') === $dest ? 'selected' : '' }}>{{ $dest }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="filter-select-group filter-agency-group" style="position:relative;">
                    <input
                        type="text"
                        name="agency"
                        list="agency-options"
                        autocomplete="off"
                        placeholder="🏢 Acenta ara..."
                        value="{{ request('agency') }}"
                        class="home-filter-select home-filter-agency"
                    >
                    <datalist id="agency-options">
                        @foreach($activeAgencies as $agency)
                            <option value="{{ $agency->name }}"></option>
                        @endforeach
                    </datalist>
                    @if(request('agency'))
                        <button
                            type="button"
                            onclick="clearAgencyFilter()"
                            title="Temizle"
                            style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;color:#94a3b8;cursor:pointer;font-size:16px;line-height:1;padding:4px;"
                        >✕</button>
                    @endif
                </div>
                <div class="filter-select-group">
                    <select name="sort" class="home-filter-select">
                        <option value="price_asc" {{ request('sort') === 'price_asc' ? 'selected' : '' }}>Fiyat (En Düşük)</option>
                        <option value="price_desc" {{ request('sort') === 'price_desc' ? 'selected' : '' }}>Fiyat (En Yüksek)</option>
                    </select>
                </div>
            </div>
        </form>
    </div>

    {{-- iPhone Style Story Cards --}}
    <div class="section" style="padding-top: 10px; margin-bottom: 0px;">
        <div class="stories-container">
            <div class="stories-scroll">
                @foreach($featuredCities as $index => $city)
                <div class="iphone-card" onclick="openStory({{ $index }})">
                    <img src="{{ $city['images'][0] }}" alt="{{ $city['name'] }}" class="iphone-card-img">
                    <div class="iphone-card-overlay">
                        <div class="iphone-card-badge">{{ $city['count'] > 0 ? $city['count'] . ' Tur' : 'Yakında' }}</div>
                        <div class="iphone-card-info">
                            <h3 class="iphone-card-city">{{ $city['name'] }}</h3>
                            <p class="iphone-card-country">{{ $city['country'] }}</p>
                        </div>
                    </div>
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
    <div class="section">
        <div class="section-header">
            <h2>Popüler Destinasyonlar</h2>
            <a href="{{ route('tours.index') }}">Tümünü gör →</a>
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

    {{-- Popular Tours --}}
    <div class="section" id="tours-section">
        <div class="section-header">
            <h2>En Uygun Turlar</h2>
            <a href="{{ route('tours.index') }}">Tümünü gör →</a>
        </div>
        
        <div id="loading-spinner" style="display:none; text-align:center; padding:40px;">
            <div style="width:40px;height:40px;border:4px solid #f1f5f9;border-top-color:var(--accent);border-radius:50%;animation:spin 1s linear infinite;margin:0 auto;"></div>
            <p style="margin-top:12px; color:var(--text-muted); font-size:14px;">Turlar güncelleniyor...</p>
        </div>
        <style>@keyframes spin { 100% { transform:rotate(360deg); } }</style>

        <div id="tour-grid-container">
            @if($popularTours->count())
                @include('partials.tour_grid', ['popularTours' => $popularTours])
            @else
                <div style="text-align:center;padding:40px;color:var(--text-muted);">
                    <div style="font-size:48px;margin-bottom:12px;">🏖️</div>
                    <p>Henüz tur bulunmuyor.</p>
                </div>
            @endif
        </div>
    </div>

    {{-- Recently Viewed --}}
    @if($recentlyViewed->count())
    <div class="section">
        <div class="section-header">
            <h2>Son Gezdiğiniz Turlar</h2>
            <span style="font-size:13px;color:var(--text-muted);">{{ $recentlyViewed->count() }} tur</span>
        </div>
        <div class="grid-4">
            @foreach($recentlyViewed as $tour)
            <a href="{{ route('tours.show', $tour) }}" class="card">
                @if($tour->image)
                    <img src="{{ $tour->image }}" alt="{{ $tour->title }}" class="card-img">
                @else
                    <div class="card-img" style="background:linear-gradient(135deg,#e0f2fe,#f0fdf4);display:flex;align-items:center;justify-content:center;font-size:36px;">🏖️</div>
                @endif
                <div class="card-body">
                    <div class="card-title">{{ $tour->title }}</div>
                    <div class="card-meta">{{ $tour->agency->name }} · {{ $tour->duration_days }} gün</div>
                    <div style="margin-top:8px;">
                        <span class="price-tag" style="font-size:18px;">{{ $tour->formatted_price }}</span>
                        <span class="price-sm"> / kişi</span>
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
        .hero-carousel { position:relative; width:100vw; margin-left:calc(-50vw + 50%); height:520px; overflow:hidden; }
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
        .filter-bar { background:white; border-radius:20px; box-shadow:0 15px 40px -10px rgba(0,0,0,0.12); padding:12px; display:flex; gap:16px; align-items:center; flex-wrap:wrap; border:1px solid #f1f5f9; }
        
        .category-tabs-container { flex:1; overflow:hidden; position:relative; }
        .category-tabs { display:flex; gap:8px; overflow-x:auto; padding-bottom:4px; scrollbar-width:none; -ms-overflow-style:none; }
        .category-tabs::-webkit-scrollbar { display:none; }
        
        .category-tab { background:#f8fafc; border:1px solid #e2e8f0; border-radius:14px; padding:10px 18px; display:flex; align-items:center; gap:8px; cursor:pointer; transition:all 0.2s; white-space:nowrap; font-family:inherit; font-weight:600; font-size:14px; color:#475569; }
        .category-tab:hover { background:#f1f5f9; border-color:#cbd5e1; color:var(--text); }
        .category-tab.active { background:var(--accent-light); border-color:var(--accent); color:var(--accent-dark); box-shadow:0 4px 12px rgba(13,148,136,0.1); }
        .category-icon { font-size:18px; }

        .filter-dropdowns { display:flex; gap:12px; }
        .home-filter-select { background:#f8fafc; border:1px solid #e2e8f0; border-radius:14px; padding:10px 16px; font-family:inherit; font-weight:600; font-size:14px; color:#475569; outline:none; cursor:pointer; min-width:160px; transition:all 0.2s; }
        .home-filter-select:focus { border-color:var(--accent); background:white; box-shadow:0 0 0 3px rgba(13,148,136,0.08); }

        /* ── Subcategory Row (2nd tier) ── */
        .subcategory-row { width:100%; max-height:0; overflow:hidden; transition:max-height 0.35s ease, opacity 0.3s ease, margin 0.3s ease; opacity:0; margin-top:0; }
        .subcategory-row.open { max-height:60px; opacity:1; margin-top:10px; }
        .subcategory-tabs { display:flex; gap:6px; overflow-x:auto; scrollbar-width:none; -ms-overflow-style:none; padding:2px 0; }
        .subcategory-tabs::-webkit-scrollbar { display:none; }
        .subcategory-tab { background:#f1f5f9; border:1px solid #e2e8f0; border-radius:100px; padding:6px 16px; display:flex; align-items:center; gap:6px; cursor:pointer; transition:all 0.2s; white-space:nowrap; font-family:inherit; font-weight:500; font-size:13px; color:#64748b; }
        .subcategory-tab:hover { background:#e2e8f0; color:#334155; }
        .subcategory-tab.active { background:var(--accent); border-color:var(--accent); color:#fff; font-weight:600; }
        .subcategory-icon { font-size:14px; }

        @media(max-width:992px) {
            .filter-bar { padding:16px; }
            .category-tabs-container { order:1; width:100%; }
            .subcategory-row { order:2; }
            .filter-dropdowns { order:3; width:100%; justify-content:space-between; }
            .home-filter-select { flex:1; }
        }

        /* ── iPhone Style Story Cards ── */
        .stories-container { margin: 0 -20px; padding: 10px 20px; overflow: hidden; }
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

// --- Home Filter AJAX Logic ---
document.addEventListener('DOMContentLoaded', function() {
    const filterForm = document.getElementById('home-filter-form');
    if (!filterForm) return;

    const categoryTabs = document.querySelectorAll('.category-tab');
    const categoryInput = document.getElementById('selected-category');
    const tourGridContainer = document.getElementById('tour-grid-container');
    const loadingSpinner = document.getElementById('loading-spinner');
    const toursSection = document.getElementById('tours-section');
    const subcategoryRow = document.getElementById('subcategoryRow');
    const subcategoryTabs = document.getElementById('subcategoryTabs');

    function fetchFilteredTours() {
        if (!filterForm || !tourGridContainer) return;

        const formData = new FormData(filterForm);
        const params = new URLSearchParams(formData);
        
        // Show loading
        if (loadingSpinner) loadingSpinner.style.display = 'block';
        if (tourGridContainer) tourGridContainer.style.opacity = '0.5';

        fetch(`${filterForm.action}?${params.toString()}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(response => response.text())
        .then(html => {
            if (tourGridContainer) {
                tourGridContainer.innerHTML = html;
                tourGridContainer.style.opacity = '1';
            }
            if (toursSection) {
                const rect = toursSection.getBoundingClientRect();
                if (rect.top < 100) {
                    window.scrollTo({
                        top: window.pageYOffset + rect.top - 100,
                        behavior: 'smooth'
                    });
                }
            }
        })
        .catch(err => {
            console.error('Filter error:', err);
            if (tourGridContainer) tourGridContainer.style.opacity = '1';
        })
        .finally(() => {
            if (loadingSpinner) loadingSpinner.style.display = 'none';
        });
    }

    function showSubcategories(children, parentSlug) {
        subcategoryTabs.innerHTML = '';
        if (!children || children.length === 0) {
            subcategoryRow.classList.remove('open');
            return;
        }
        // "Tümü" for parent
        const allBtn = document.createElement('button');
        allBtn.type = 'button';
        allBtn.className = 'subcategory-tab active';
        allBtn.dataset.category = parentSlug;
        allBtn.innerHTML = '<span class="subcategory-icon">📋</span> Tümü';
        allBtn.addEventListener('click', () => selectSubcategory(allBtn, parentSlug));
        subcategoryTabs.appendChild(allBtn);

        children.forEach(child => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'subcategory-tab';
            btn.dataset.category = child.slug;
            btn.innerHTML = `<span class="subcategory-icon">${child.icon}</span> ${child.name}`;
            btn.addEventListener('click', () => selectSubcategory(btn, child.slug));
            subcategoryTabs.appendChild(btn);
        });

        subcategoryRow.classList.add('open');
    }

    function selectSubcategory(btn, slug) {
        document.querySelectorAll('.subcategory-tab').forEach(t => t.classList.remove('active'));
        btn.classList.add('active');
        categoryInput.value = slug;
        fetchFilteredTours();
    }

    categoryTabs.forEach(tab => {
        tab.addEventListener('click', () => {
            categoryTabs.forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            const slug = tab.dataset.category;
            categoryInput.value = slug;

            // Parse children and show subcategory row
            let children = [];
            try { children = JSON.parse(tab.dataset.children || '[]'); } catch(e) {}
            showSubcategories(children, slug);

            fetchFilteredTours();
        });
    });

    // Destination / Sort select'leri anında filtrele
    filterForm.querySelectorAll('select[name="destination"], select[name="sort"]').forEach(sel => {
        sel.addEventListener('change', fetchFilteredTours);
    });

    // Acenta search input: 300ms debounce + datalist seçimi anında
    const agencyInput = filterForm.querySelector('input[name="agency"]');
    if (agencyInput) {
        let agencyTimer;
        const debouncedFetch = () => {
            clearTimeout(agencyTimer);
            agencyTimer = setTimeout(fetchFilteredTours, 300);
        };
        agencyInput.addEventListener('input', debouncedFetch);
        agencyInput.addEventListener('change', () => {
            clearTimeout(agencyTimer);
            fetchFilteredTours();
        });
        // Datalist'ten seçilen değer için de anlık tetikleme
        agencyInput.addEventListener('blur', () => {
            clearTimeout(agencyTimer);
            fetchFilteredTours();
        });
    }

    // Acenta filtresini sıfırla (✕ butonu)
    window.clearAgencyFilter = function () {
        if (agencyInput) {
            agencyInput.value = '';
            fetchFilteredTours();
        }
    };

// --- Instagram Story Logic ---
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
    document.getElementById('story-cta').href = `/turlar?q=${city.name}`;
    
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
