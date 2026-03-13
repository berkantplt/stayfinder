@extends('layouts.app')
@section('title', 'StayFinder — Tur Karşılaştırma Platformu')

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
            <form action="{{ route('tours.index') }}" method="GET" style="display:flex;border-radius:100px;overflow:hidden;background:rgba(255,255,255,0.95);backdrop-filter:blur(12px);box-shadow:0 8px 32px rgba(0,0,0,0.15);max-width:560px;">
                <div style="flex:1;padding:16px 22px;display:flex;align-items:center;gap:10px;">
                    <span style="font-size:18px;">🔍</span>
                    <input type="text" name="q" placeholder="Tur veya destinasyon ara..." style="border:none;outline:none;font-family:var(--font);font-size:16px;width:100%;background:transparent;color:#0f172a;">
                </div>
                <button type="submit" style="background:var(--accent);color:white;border:none;padding:0 32px;font-family:var(--font);font-size:15px;font-weight:700;cursor:pointer;white-space:nowrap;transition:background 0.2s;">Ara</button>
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
    @if($popularTours->count())
    <div class="section">
        <div class="section-header">
            <h2>En Uygun Turlar</h2>
            <a href="{{ route('tours.index') }}">Tümünü gör →</a>
        </div>
        <div class="grid-4">
            @foreach($popularTours as $tour)
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
            <a href="{{ route('login') }}" class="btn" style="background:white;color:var(--accent);font-weight:700;">Acenta Girişi →</a>
        </div>
    </div>
</div>
@endsection

@section('styles')
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
            .hero-carousel { height:400px; }
            .hero-overlay h1 { font-size:28px !important; }
            .hero-arrow { width:36px; height:36px; font-size:14px; }
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
</script>
@endpush
