{{-- Kart acenta satırı: logo (yoksa baş harf) + ad. Logo Agency.logo'da tam adres. --}}
<div class="card-meta card-agency">
    @if($tour->agency->logo)
        <img src="{{ $tour->agency->logo }}" alt="" class="card-agency-logo" loading="lazy" data-yedek-atla>
    @else
        <span class="card-agency-ini" aria-hidden="true">{{ mb_strtoupper(mb_substr($tour->agency->name, 0, 1)) }}</span>
    @endif
    <span>{{ $tour->agency->name }}</span>
</div>
