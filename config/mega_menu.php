<?php

/*
|--------------------------------------------------------------------------
| Ana sayfa mega menüsü — üst şerit kovaları
|--------------------------------------------------------------------------
|
| Menü üç katmanlı (malitur kalıbı):
|
|     üst şerit (kova) → sol ray (ana kategori) → orta sütun (alt kategoriler)
|
| Kategori ağacı İKİ seviye (13 ana kategori + alt kategorileri), üçüncü katman
| yok. Bu yüzden en üstteki gruplama buradan gelir: 13 başlığı tek şeride
| dizmek üç satıra sarıyordu ve dallanma hissi vermiyordu.
|
| KURAL: burada listelenmeyen ana kategori KAYBOLMAZ — MegaMenu onları otomatik
| olarak son bir "Diğer Turlar" kovasına koyar. Admin yeni bir ana kategori
| açtığında menüde görünmeye devam eder; buraya eklemek yalnız hangi kovaya
| gireceğini seçmek içindir. (Menü ile filtre barı aynı listeyi göstermek
| zorunda; bir kategoriyi menüden gizlemek kullanıcıyı ikisi arasında
| çelişkiye düşürür.)
|
| İkon: kova başlığının yanındaki emoji. Kategorilerin kendi ikonları
| veritabanından gelir, buraya yazılmaz.
*/

return [

    'buckets' => [
        [
            'key' => 'kultur-sehir',
            'label' => 'Kültür & Şehir',
            'icon' => '🏛️',
            'categories' => ['kultur-turlari', 'sehir-turlari'],
        ],
        [
            'key' => 'doga-macera',
            'label' => 'Doğa & Macera',
            'icon' => '🏔️',
            'categories' => ['doga-turlari', 'macera-aktivite', 'kayak-kis'],
        ],
        [
            'key' => 'deniz-cruise',
            'label' => 'Deniz & Cruise',
            'icon' => '⛵',
            'categories' => ['deniz-tekne', 'gemi-cruise'],
        ],
        [
            'key' => 'kisa-kacamak',
            'label' => 'Kısa Kaçamak',
            'icon' => '🧳',
            'categories' => ['gunubirlik-turlar', 'hafta-sonu-kacamagi'],
        ],
        [
            'key' => 'tema-turlari',
            'label' => 'Tema Turları',
            'icon' => '💕',
            'categories' => ['balayi-romantik', 'gastronomi-lezzet', 'festival-etkinlik', 'aile-cocuk'],
        ],
    ],

    /* Hiçbir kovaya girmeyen ana kategorilerin toplandığı kova. */
    'fallback' => [
        'key' => 'diger',
        'label' => 'Diğer Turlar',
        'icon' => '🧭',
    ],
];
