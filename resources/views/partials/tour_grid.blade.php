<div class="grid-4">
    @foreach($popularTours as $tour)
    @php $mDrop = ($tourDrops ?? [])[$tour->id] ?? null; @endphp
    <a href="{{ route('tours.show', $tour) }}" class="card">
        @if($tour->image)
            {{-- view-transition-name: kart görseli detay galerisine akarak geçer --}}
            <img src="{{ $tour->image }}" alt="{{ $tour->title }}" class="card-img" style="view-transition-name: tour-{{ $tour->id }};">
        @else
            <div class="card-img" style="background:linear-gradient(135deg,#e0f2fe,#f0fdf4);display:flex;align-items:center;justify-content:center;font-size:36px;">🏖️</div>
        @endif
        @if($mDrop)
            <span class="m-drop-badge">↓ %{{ $mDrop }} DÜŞTÜ</span>
        @endif
        <div class="card-body">
            <div class="card-title">{{ $tour->title }}</div>
            <div class="card-meta">{{ $tour->agency->name }} · {{ $tour->duration_label }}</div>
            @include('partials.tour_card_meta', ['tour' => $tour])
            <div class="card-price-row" style="margin-top:8px;">
                @php $campaign = $tour->activeCampaign; @endphp
                @if($campaign)
                    <span class="price-tag" style="font-size:18px; color:#059669;">{{ $campaign->formatted_discount_price }}</span>
                    <span style="text-decoration:line-through;color:#94a3b8;font-size:12px;margin-left:4px;">{{ $tour->formatted_price }}</span>
                @else
                    <span class="price-tag" style="font-size:18px;">{{ $tour->formatted_price }}</span>
                @endif
                <span class="price-sm"> / kişi başı</span>
            </div>
        </div>
    </a>
    @endforeach
</div>
@if($popularTours->isEmpty())
    <div style="text-align:center;padding:40px;color:var(--text-muted);">
        <div style="font-size:48px;margin-bottom:12px;">🔍</div>
        <p>Aradığınız kriterlere uygun tur bulunamadı.</p>
    </div>
@endif
