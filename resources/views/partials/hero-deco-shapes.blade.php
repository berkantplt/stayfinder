{{-- Mobil hero desen şekilleri (kullanıcının overlay görselinin SVG çizimi).
     TEK KAYNAK: hem ana sayfa hero'su hem admin mini önizlemesi bunu basar —
     viewBox="0 0 375 430" varsayar. İki yere ayrı çizilirse admin'de görülen
     ile sitede çıkan desen sessizce ayrışır. --}}
<defs>
    <linearGradient id="hdCircle" x1="0" y1="0" x2=".85" y2=".9">
        <stop offset="0" stop-color="#0A2733"/>
        <stop offset=".55" stop-color="#0C5462"/>
        <stop offset="1" stop-color="#0F8492"/>
    </linearGradient>
    <linearGradient id="hdAqua" x1="0" y1="0" x2="0" y2="1">
        <stop offset="0" stop-color="#3FC3CC" stop-opacity=".9"/>
        <stop offset="1" stop-color="#DCF4F5" stop-opacity=".72"/>
    </linearGradient>
    <linearGradient id="hdAqua2" x1="0" y1="0" x2="0" y2="1">
        <stop offset="0" stop-color="#37BFC9" stop-opacity=".85"/>
        <stop offset="1" stop-color="#CFF0F2" stop-opacity=".68"/>
    </linearGradient>
</defs>
{{-- 1: sol üstte koyu daire (lacivert→teal degrade) + parlak turkuaz kenar --}}
<path d="M 220 -10 A 271 271 0 0 1 -10 312 L -10 -10 Z" fill="url(#hdCircle)"/>
<path d="M 220 -10 A 271 271 0 0 1 -10 312" fill="none" stroke="#19C9D4" stroke-width="2" opacity=".9"/>
{{-- 2: sol altta açık turkuaz dalga — dairenin üstüne yarı saydam biner --}}
<path d="M -10 288 C 55 262 140 262 210 296 C 252 318 288 366 300 430 L -10 430 Z" fill="url(#hdAqua)"/>
<path d="M -10 288 C 55 262 140 262 210 296 C 252 318 288 366 300 430" fill="none" stroke="#1EC8D2" stroke-width="1.8" opacity=".9"/>
{{-- 3: sağ alttan giren açık turkuaz dalga — soldakiyle altta kesişir --}}
<path d="M 385 252 C 330 290 288 330 258 374 C 242 398 232 416 227 430 L 385 430 Z" fill="url(#hdAqua2)"/>
<path d="M 385 252 C 330 290 288 330 258 374 C 242 398 232 416 227 430" fill="none" stroke="#1EC8D2" stroke-width="1.8" opacity=".9"/>
