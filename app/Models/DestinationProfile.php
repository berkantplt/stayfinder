<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DestinationProfile extends Model
{
    use HasFactory;

    public const SOURCE_MANUAL = 'manual';
    public const SOURCE_LLM = 'llm';
    public const SOURCE_DEFAULT = 'default';

    /**
     * Şema değişince mevcut profiller bu versiyonun altında kalır;
     * Observer / job onları yeniden zenginleştirir.
     */
    public const CURRENT_ENRICHMENT_VERSION = 2;

    protected $fillable = [
        'city',
        'country',
        'normalized_city',
        'crowd_score',
        'liveliness_score',
        'summary',
        'climate_by_month',
        'vibe_tags',
        'best_months',
        'crowded_months',
        'requires_visa_for_tr',
        'source',
        'enrichment_version',
        'reasoning',
        'generated_at',
    ];

    protected $casts = [
        'crowd_score' => 'float',
        'liveliness_score' => 'float',
        'climate_by_month' => 'array',
        'vibe_tags' => 'array',
        'best_months' => 'array',
        'crowded_months' => 'array',
        'requires_visa_for_tr' => 'boolean',
        'enrichment_version' => 'integer',
        'generated_at' => 'datetime',
    ];

    public static function normalize(string $city): string
    {
        // Önce büyük harfli Türkçe karakterleri ASCII'ye çevir
        // (mb_strtolower 'İ'yi combining-dot'lu 'i̇' yapıyor — onu önle)
        $normalized = strtr(trim($city), [
            'İ' => 'i', 'I' => 'i',
            'Ğ' => 'g', 'Ü' => 'u', 'Ş' => 's',
            'Ö' => 'o', 'Ç' => 'c',
            'ğ' => 'g', 'ü' => 'u', 'ş' => 's', 'ı' => 'i',
            'ö' => 'o', 'ç' => 'c',
        ]);

        $normalized = mb_strtolower($normalized, 'UTF-8');

        return preg_replace('/\s+/', ' ', $normalized);
    }

    /**
     * Çok-şehirli destinasyon string'ini ("Paris, Roma ve Floransa") tekil
     * şehirlere böler. TourObserver enrichment'ı ve envanter servisi aynı
     * bölme kuralını paylaşır.
     *
     * @return array<int, string>
     */
    public static function splitCities(string $destination): array
    {
        $destination = trim($destination);
        if ($destination === '') {
            return [];
        }

        $parts = preg_split('/\s*[,;\/&]\s*|\s+ve\s+/u', $destination) ?: [$destination];

        return collect($parts)
            ->map(fn ($part) => trim((string) $part))
            ->filter(fn ($part) => $part !== '' && mb_strlen($part, 'UTF-8') >= 3)
            ->unique(fn ($part) => self::normalize($part))
            ->values()
            ->all();
    }

    public function needsEnrichment(): bool
    {
        return ($this->enrichment_version ?? 1) < self::CURRENT_ENRICHMENT_VERSION;
    }

    public function scopeStale($query)
    {
        return $query->where('enrichment_version', '<', self::CURRENT_ENRICHMENT_VERSION);
    }
}
