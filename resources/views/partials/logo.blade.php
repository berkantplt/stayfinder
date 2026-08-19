{{-- turXtur logo (varyant A: antrasit harfler + turkuaz swoosh). $height ile boyutlanır.
     $light => koyu zemin üstünde beyaz harf + mint swoosh (mobil hero header'ı). --}}
@php
    $lg_text = ($light ?? false) ? '#ffffff' : '#0f172a';
    $lg_swoosh = ($light ?? false) ? '#5eead4' : '#0d9488';
@endphp
<svg height="{{ $height ?? 30 }}" viewBox="0 0 440 126" role="img" aria-label="turXtur" xmlns="http://www.w3.org/2000/svg" style="display:block;">
    <g transform="translate(220,114)">
        <text x="-62" y="0" text-anchor="end" style="font-family:'Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;font-weight:800;font-size:110px;letter-spacing:-3px" fill="{{ $lg_text }}">tur</text>
        <text x="62" y="0" text-anchor="start" style="font-family:'Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;font-weight:800;font-size:110px;letter-spacing:-3px" fill="{{ $lg_text }}">tur</text>
        <g transform="translate(0,-52)">
            <line x1="-28" y1="-46" x2="28" y2="44" stroke="{{ $lg_text }}" stroke-width="26" stroke-linecap="round"/>
            <path d="M -52 51 Q -29 -26 39 -57 Q 47 -57 49 -47 Q 1 0 -44 57 Q -50 58 -52 51 Z" fill="{{ $lg_swoosh }}"/>
        </g>
    </g>
</svg>
