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
    |   'both'   → filtre barı + kategori ağacı birlikte (2026-08-13 kararı)
    |   'filter' → yalnız filtre barı
    |   'mega'   → yalnız kategori ağacı (filtre /turlar'da kalır) — VARSAYILAN,
    |              2026-09-10 sadeleştirme kararı: ilk ekranda banner + üç kutulu
    |              arama, sekiz filtre /turlar'da yaşar
    |
    | 2026-08-11'de ikisi AYNI kartta üst üste durduğu için kalabalık olmuştu ve
    | 'filter'a düşülmüştü. Yeni yerleşimde çakışmıyorlar: filtre barı hero'ya
    | binen yüzen kartta, kategori ağacı dalganın altında ayrı bir şerit.
    |
    | HİÇBİR MOD KOD SİLMEZ — .env'de HOME_NAV çevirmek yeterli.
    */
    'home_nav' => env('HOME_NAV', 'mega'),

    /*
    | /turlar'da aynı turun farklı acenta teklifleri tek kartta toplanır
    | ("3 acentada · 4.499 ₺'den"). Kapatınca her teklif ayrı kart (eski davranış);
    | kod silinmez. Gruplama anahtarı tours.group_key (başlığın normalize slug'ı).
    */
    'tour_grouping' => (bool) env('TOUR_GROUPING', true),
];
