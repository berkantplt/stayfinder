@php
    // Kalp dolgusu: giriş yapmış kullanıcının favori tur id'leri (istek başına tek sorgu)
    $favIds = auth()->check()
        ? once(fn () => auth()->user()->favoriteTours()->pluck('tours.id')->all())
        : [];
@endphp
<div class="grid-4">
    @foreach($popularTours as $tour)
    @php
        $mDrop = ($tourDrops ?? [])[$tour->id] ?? null;
        $campaign = $tour->activeCampaign;
        // Kampanya indirimi yüzdesi (rozet): fiyat yoksa/artmışsa rozet basılmaz
        $mIndirim = ($campaign && $tour->price > 0 && $campaign->discount_price < $tour->price)
            ? (int) round((1 - $campaign->discount_price / $tour->price) * 100)
            : null;
    @endphp
    <div class="m-cardwrap">
        <a href="{{ route('tours.show', $tour) }}" class="card">
            @if($tour->image)
                {{-- view-transition-name: kart görseli detay galerisine akarak geçer --}}
                <img src="{{ $tour->image }}" alt="{{ $tour->title }}" class="card-img" style="view-transition-name: tour-{{ $tour->id }};">
            @else
                <div class="card-img" style="background:linear-gradient(135deg,#e0f2fe,#f0fdf4);display:flex;align-items:center;justify-content:center;font-size:36px;">🏖️</div>
            @endif
            @if($mIndirim)
                <span class="m-drop-badge">%{{ $mIndirim }} İNDİRİM</span>
            @elseif($mDrop)
                <span class="m-drop-badge">↓ %{{ $mDrop }} DÜŞTÜ</span>
            @endif
            <div class="card-body">
                <div class="card-title">{{ $tour->title }}</div>
                <div class="card-meta">{{ $tour->agency->name }} · {{ $tour->duration_label }}</div>
                @include('partials.tour_card_meta', ['tour' => $tour])
                <div class="card-price-row" style="margin-top:8px;">
                    @if(($tour->reviews_count ?? 0) > 0)
                        <span class="m-rating">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="m12 3.6 2.6 5.4 5.9.8-4.3 4.2 1 5.9-5.2-2.8-5.2 2.8 1-5.9L3.5 9.8l5.9-.8z"/></svg>
                            {{ number_format((float) $tour->reviews_avg_rating, 1, ',', '') }} <i>({{ $tour->reviews_count }})</i>
                        </span>
                    @endif
                    <span class="m-pricebox">
                        @if($campaign)
                            <span class="price-tag" style="font-size:18px; color:#059669;">{{ $campaign->formatted_discount_price }}</span>
                            <span style="text-decoration:line-through;color:#94a3b8;font-size:12px;margin-left:4px;">{{ $tour->formatted_price }}</span>
                        @else
                            <span class="price-tag" style="font-size:18px;">{{ $tour->formatted_price }}</span>
                        @endif
                        <span class="price-sm"> / kişi başı</span>
                    </span>
                </div>
            </div>
        </a>
        <button type="button" class="m-fav {{ in_array($tour->id, $favIds) ? 'on' : '' }}"
            data-tour="{{ $tour->id }}" aria-label="Favorilere ekle" aria-pressed="{{ in_array($tour->id, $favIds) ? 'true' : 'false' }}">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.8 5.6a5.2 5.2 0 0 0-7.4 0L12 7l-1.4-1.4a5.2 5.2 0 1 0-7.4 7.4L12 21.5l8.8-8.5a5.2 5.2 0 0 0 0-7.4z"/></svg>
        </button>
    </div>
    @endforeach
</div>
@if($popularTours->isEmpty())
    <div style="text-align:center;padding:40px;color:var(--text-muted);">
        <div style="font-size:48px;margin-bottom:12px;">🔍</div>
        <p>Aradığınız kriterlere uygun tur bulunamadı.</p>
    </div>
@endif
