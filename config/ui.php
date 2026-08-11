<?php

/*
|--------------------------------------------------------------------------
| Arayüz Anahtarları
|--------------------------------------------------------------------------
*/

return [

    /*
    | Ana sayfada hero'nun altındaki gezinme bloğu.
    |
    |   'filter' → yalnız filtre barı (VARSAYILAN, 2026-08-11 kullanıcı kararı)
    |   'both'   → mega menü + filtre barı birlikte
    |   'mega'   → yalnız mega menü (malitur kalıbı; filtre /turlar'da kalır)
    |
    | HİÇBİR MOD KOD SİLMEZ. Mega menü (App\Support\MegaMenu +
    | partials/mega-menu.blade.php) yerinde duruyor, yalnız render edilmiyor —
    | denendi ve kategori ağacı yerine filtre tercih edildi. Geri açmak için
    | .env'de HOME_NAV=both yazmak yeterli, geri alma commit'i gerekmez.
    */
    'home_nav' => env('HOME_NAV', 'filter'),
];
