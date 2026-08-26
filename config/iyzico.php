<?php

return [

    'mode' => env('IYZICO_MODE', 'sandbox'),

    'api_key' => env('IYZICO_API_KEY'),
    'secret_key' => env('IYZICO_SECRET_KEY'),

    'base_uri' => env('IYZICO_BASE_URI', env('IYZICO_MODE', 'sandbox') === 'production'
        ? 'https://api.iyzipay.com'
        : 'https://sandbox-api.iyzipay.com'),

    'currency' => env('IYZICO_CURRENCY', 'TRY'),
    'locale' => env('IYZICO_LOCALE', 'tr'),

    /*
    | Otomatik aylık yenileme (kart saklama + tekrarlayan çekim). iyzico
    | hesabında kart saklama ve 3DS'siz çekim izni AÇIK olmadan true yapma —
    | tüm otomatik yenilemeler hata verir. Kapalıyken sistem eski davranışta:
    | hatırlatma bildirimi + manuel yenileme.
    */
    'auto_renew_enabled' => (bool) env('IYZICO_AUTO_RENEW', false),

    'callback_route' => env('IYZICO_CALLBACK_ROUTE', 'agency.category-licenses.iyzico.callback'),
];
