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
    |   'both'   → filtre barı + kategori ağacı birlikte (VARSAYILAN,
    |              2026-08-13 kullanıcı kararı)
    |   'filter' → yalnız filtre barı
    |   'mega'   → yalnız kategori ağacı (filtre /turlar'da kalır)
    |
    | 2026-08-11'de ikisi AYNI kartta üst üste durduğu için kalabalık olmuştu ve
    | 'filter'a düşülmüştü. Yeni yerleşimde çakışmıyorlar: filtre barı hero'ya
    | binen yüzen kartta, kategori ağacı dalganın altında ayrı bir şerit.
    |
    | HİÇBİR MOD KOD SİLMEZ — .env'de HOME_NAV çevirmek yeterli.
    */
    'home_nav' => env('HOME_NAV', 'both'),
];
