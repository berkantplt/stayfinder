<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Sosyal giriş (Google / Apple)
    |--------------------------------------------------------------------------
    |
    | Anahtarlar yoksa ilgili buton hiç görünmez (bkz. SocialAuth::enabled).
    | Callback adresleri sağlayıcı konsollarına birebir yazılır; APP_URL canlıda
    | https olmalı, aksi halde Google "redirect_uri_mismatch" döner.
    |
    */

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', rtrim((string) env('APP_URL'), '/').'/giris/google/callback'),
    ],

    /*
     | Apple client_secret'ı ES256 imzalı, ömrü en fazla 6 ay olan bir JWT'dir.
     | Elle üretip .env'e koyarsak 6 ay sonra giriş sessizce kırılır; bu yüzden
     | .p8 özel anahtarını veriyoruz ve sürücü secret'ı her istekte kendisi üretir.
     | APPLE_CLIENT_ID = Services ID (ör. com.turxtur.web), App ID DEĞİL.
     */
    'apple' => [
        'client_id' => env('APPLE_CLIENT_ID'),
        'client_secret' => env('APPLE_CLIENT_SECRET'), // boş: private_key'den üretilir
        'key_id' => env('APPLE_KEY_ID'),
        'team_id' => env('APPLE_TEAM_ID'),
        'private_key' => env('APPLE_PRIVATE_KEY'), // .p8 dosyasının mutlak yolu
        'redirect' => env('APPLE_REDIRECT_URI', rtrim((string) env('APP_URL'), '/').'/giris/apple/callback'),
    ],

];
