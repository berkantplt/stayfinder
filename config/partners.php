<?php

/**
 * Partner API Configuration
 *
 * Each partner has its own configuration block. To add a new partner:
 * 1. Add a config entry here
 * 2. Create a service class implementing PartnerServiceInterface
 * 3. Register it in PartnerServiceProvider
 *
 * Toggle partners on/off at runtime via the 'enabled' flag or .env variables.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Mock Mode
    |--------------------------------------------------------------------------
    |
    | true → tüm partner servisleri MockPartnerService ile değiştirilir (dev/test).
    | config üzerinden okunur ki `php artisan config:cache` sonrası da geçerli olsun.
    |
    */
    'mock_enabled' => (bool) env('PARTNER_MOCK_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Global Defaults
    |--------------------------------------------------------------------------
    |
    | Default timeout and connection timeout applied to all partners
    | unless overridden in their individual configuration.
    |
    */
    'defaults' => [
        'timeout'         => (int) env('PARTNER_DEFAULT_TIMEOUT', 3),
        'connect_timeout' => (int) env('PARTNER_DEFAULT_CONNECT_TIMEOUT', 2),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | TTL for search result caching in seconds.
    | 300 seconds = 5 minutes — balances freshness with API load reduction.
    |
    */
    'cache' => [
        'ttl'    => (int) env('SEARCH_CACHE_TTL', 300),
        'driver' => env('SEARCH_CACHE_DRIVER', 'redis'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Partner: Booking.com
    |--------------------------------------------------------------------------
    */
    'booking' => [
        'enabled'         => (bool) env('PARTNER_BOOKING_ENABLED', true),
        'base_url'        => env('PARTNER_BOOKING_BASE_URL', 'https://api.booking.com/v1'),
        'api_key'         => env('PARTNER_BOOKING_API_KEY', ''),
        'timeout'         => (int) env('PARTNER_BOOKING_TIMEOUT', 3),
        'connect_timeout' => (int) env('PARTNER_BOOKING_CONNECT_TIMEOUT', 2),
    ],

    /*
    |--------------------------------------------------------------------------
    | Partner: Expedia
    |--------------------------------------------------------------------------
    */
    'expedia' => [
        'enabled'         => (bool) env('PARTNER_EXPEDIA_ENABLED', true),
        'base_url'        => env('PARTNER_EXPEDIA_BASE_URL', 'https://api.expedia.com/v2'),
        'api_key'         => env('PARTNER_EXPEDIA_API_KEY', ''),
        'timeout'         => (int) env('PARTNER_EXPEDIA_TIMEOUT', 3),
        'connect_timeout' => (int) env('PARTNER_EXPEDIA_CONNECT_TIMEOUT', 2),
    ],

    /*
    |--------------------------------------------------------------------------
    | Partner: Agoda
    |--------------------------------------------------------------------------
    */
    'agoda' => [
        'enabled'         => (bool) env('PARTNER_AGODA_ENABLED', true),
        'base_url'        => env('PARTNER_AGODA_BASE_URL', 'https://api.agoda.com/v1'),
        'api_key'         => env('PARTNER_AGODA_API_KEY', ''),
        'timeout'         => (int) env('PARTNER_AGODA_TIMEOUT', 3),
        'connect_timeout' => (int) env('PARTNER_AGODA_CONNECT_TIMEOUT', 2),
    ],
];
