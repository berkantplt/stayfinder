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
    |   'both'   → mega menü + filtre barı birlikte (varsayılan, bugünkü hal)
    |   'mega'   → yalnız mega menü (malitur kalıbı; filtre /turlar sayfasında kalır)
    |   'filter' → yalnız filtre barı (mega menü öncesi hal)
    |
    | HİÇBİR MOD KOD SİLMEZ. Üç seçenek de aynı dosyalardan render edilir;
    | fikir değişirse .env'de HOME_NAV değerini çevirmek yeterli, geri alma
    | commit'i gerekmez.
    */
    'home_nav' => env('HOME_NAV', 'both'),
];
