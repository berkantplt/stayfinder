<?php

namespace App\Services\Matching;

use Illuminate\Support\Facades\File;

/**
 * Rubrik tanımı tek kaynaktan (resources/rubric/v1.json) okunur:
 * boyutlar, ceza tipleri (mesafe/tavan/taban), ağırlık sınırları,
 * sonuç kuralları ve çıpa turlar. Versiyon kilidi: puan kayıtları
 * rubric_version taşır; boyut seti değişirse yeni versiyon dosyası açılır.
 */
class Rubric
{
    public const VERSION = 'v1';

    private static ?array $cache = null;

    public static function load(string $version = self::VERSION): array
    {
        if (self::$cache !== null && (self::$cache['rubric_version'] ?? null) === $version) {
            return self::$cache;
        }

        return self::$cache = json_decode(
            File::get(resource_path("rubric/{$version}.json")),
            true,
            flags: JSON_THROW_ON_ERROR
        );
    }

    /** @return string[] boyut anahtarları */
    public static function dimensions(): array
    {
        return array_keys(self::load()['dimensions']);
    }

    public static function type(string $dimension): string
    {
        return self::load()['dimensions'][$dimension]['tip'] ?? 'mesafe';
    }

    public static function label(string $dimension): string
    {
        return self::load()['dimensions'][$dimension]['ad'] ?? $dimension;
    }

    public static function penalty(): array
    {
        return self::load()['penalty'];
    }

    public static function weightBounds(): array
    {
        return self::load()['weight_bounds'];
    }

    public static function resultRules(): array
    {
        return self::load()['result_rules'];
    }

    public static function anchorTours(): array
    {
        return self::load()['anchor_tours'];
    }
}
