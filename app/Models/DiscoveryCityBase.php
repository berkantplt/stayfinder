<?php

namespace App\Models;

use App\Support\DestinationFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Keşif Rehberi şehir tabanı: bir şehrin PARAMETRESİZ, etiketli içerik
 * havuzu. Rehber isteğinin gezgin tipi / ilgi alanı / tempo / bütçesi
 * tabanı değiştirmez; taban üzerinden yalnız günlük plan üretilir.
 *
 * Halüsinasyon kurumsallaşmasın diye: tanınmayan destinasyona taban
 * YAZILMAZ, taban MAX_AGE_DAYS sonra bayat sayılır (tam üretime düşülür ve
 * arka planda yenilenir), admin listeden silebilir/yeniletebilir.
 */
class DiscoveryCityBase extends Model
{
    /** Bu süreden eski taban bayat: kullanılmaz, arka planda yeniden üretilir. */
    public const MAX_AGE_DAYS = 90;

    public const SOURCE_GENERATED = 'generated';

    /** Havuz bölümleri — base_payload'daki liste anahtarları (sıra arayüz sırası). */
    public const SECTIONS = ['highlights', 'things_to_do', 'historical_places', 'museums', 'local_foods'];

    /** Öğe etiketlerinin kabul edilen sözlüğü (AI çıktısı buna göre temizlenir). */
    public const TAG_TIMES = ['morning', 'afternoon', 'evening'];

    protected $fillable = [
        'normalized_city', 'display_name', 'country', 'base_payload',
        'source', 'model', 'hit_count', 'generated_at',
    ];

    protected $casts = [
        'base_payload' => 'array',
        'generated_at' => 'datetime',
        'hit_count' => 'integer',
    ];

    public static function normalizeCity(string $input): string
    {
        return DestinationFilter::normalize($input);
    }

    public function isStale(): bool
    {
        return $this->generated_at === null
            || $this->generated_at->lt(now()->subDays(self::MAX_AGE_DAYS));
    }

    public function scopeFresh(Builder $query): Builder
    {
        return $query->where('generated_at', '>=', now()->subDays(self::MAX_AGE_DAYS));
    }

    public function scopeStale(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereNull('generated_at')
                ->orWhere('generated_at', '<', now()->subDays(self::MAX_AGE_DAYS));
        });
    }

    /** @return array<int, array<string, mixed>> */
    public function section(string $key): array
    {
        $liste = $this->base_payload[$key] ?? [];

        return is_array($liste) ? array_values($liste) : [];
    }

    public function itemCount(): int
    {
        $toplam = 0;
        foreach (self::SECTIONS as $bolum) {
            $toplam += count($this->section($bolum));
        }

        return $toplam;
    }

    public function summary(): ?string
    {
        $ozet = $this->base_payload['destination']['summary'] ?? null;

        return is_string($ozet) && $ozet !== '' ? $ozet : null;
    }
}
