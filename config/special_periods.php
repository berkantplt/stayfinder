<?php

/*
|--------------------------------------------------------------------------
| Özel Günler / Dönemler (ana sayfa filtre barı)
|--------------------------------------------------------------------------
| Etstur modelindeki "Resmi Tatil ve Özel Günler" karşılığı: seçilen dönem,
| kalkış tarihi bu aralıklardan biriyle kesişen turları filtreler.
| Tarihler resmi takvime göre elle güncellenir (yılda bir bakım).
*/

return [
    'kurban-bayrami' => [
        'label' => 'Kurban Bayramı',
        'ranges' => [['2026-05-26', '2026-05-31'], ['2027-05-16', '2027-05-21']],
    ],
    'ramazan-bayrami' => [
        'label' => 'Ramazan Bayramı',
        'ranges' => [['2027-03-08', '2027-03-12']],
    ],
    '30-agustos' => [
        'label' => '30 Ağustos haftası',
        'ranges' => [['2026-08-28', '2026-08-31'], ['2027-08-27', '2027-08-31']],
    ],
    '29-ekim' => [
        'label' => '29 Ekim haftası',
        'ranges' => [['2026-10-28', '2026-11-01'], ['2027-10-28', '2027-11-01']],
    ],
    'yilbasi' => [
        'label' => 'Yılbaşı',
        'ranges' => [['2026-12-29', '2027-01-02'], ['2027-12-29', '2028-01-02']],
    ],
    'somestr' => [
        'label' => 'Sömestr tatili',
        'ranges' => [['2027-01-18', '2027-02-01']],
    ],
];
