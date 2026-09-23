<?php

namespace App\Support;

use App\Models\SocialAccount;

/**
 * Sosyal giriş açık mı? Anahtarları olmayan sağlayıcının butonu hiç basılmaz.
 *
 * Apple anahtarları hazır olana kadar (Developer Program + Services ID + .p8)
 * yalnız Google görünür; .env'e anahtar eklendiği anda buton kendiliğinden gelir,
 * kod değişmez.
 */
final class SocialAuth
{
    public static function enabled(string $provider): bool
    {
        if (! in_array($provider, SocialAccount::PROVIDERS, true)) {
            return false;
        }

        $config = config('services.'.$provider, []);

        if (($config['client_id'] ?? null) === null || $config['client_id'] === '') {
            return false;
        }

        // Apple'ın client_secret'ı .p8 özel anahtarından üretilir; ikisinden biri
        // yoksa istek "invalid_client" ile döner, butonu hiç göstermeyelim.
        if ($provider === SocialAccount::PROVIDER_APPLE) {
            return ! empty($config['private_key']) || ! empty($config['client_secret']);
        }

        return ! empty($config['client_secret']);
    }

    /** @return list<string> */
    public static function enabledProviders(): array
    {
        return array_values(array_filter(SocialAccount::PROVIDERS, self::enabled(...)));
    }

    public static function anyEnabled(): bool
    {
        return self::enabledProviders() !== [];
    }

    public static function label(string $provider): string
    {
        return SocialAccount::LABELS[$provider] ?? ucfirst($provider);
    }
}
