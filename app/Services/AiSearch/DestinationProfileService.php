<?php

namespace App\Services\AiSearch;

use App\Jobs\GenerateDestinationProfileJob;
use App\Models\DestinationProfile;
use Illuminate\Support\Facades\Cache;

class DestinationProfileService
{
    /** Default değerler — yeni şehir görüldüğünde job tamamlanana kadar dönen orta-skor. */
    public const DEFAULT_CROWD = 0.50;
    public const DEFAULT_LIVELY = 0.50;

    /** Yeni şehir kayıtlarının cache TTL'i — aynı request'te tekrar çağırma maliyeti olmasın. */
    private const CACHE_TTL_SECONDS = 300;

    /**
     * Şehir profili getirir. DB'de varsa onu, yoksa default + arka planda job dispatch.
     *
     * @return array{crowd: float, lively: float, source: string}
     */
    public function get(string $city): array
    {
        $city = trim($city);
        if ($city === '') {
            return $this->defaultProfile();
        }

        $normalized = DestinationProfile::normalize($city);

        // Aynı request içinde tekrar çağırma — kısa cache
        $cached = Cache::get('dest_profile:' . $normalized);
        if ($cached !== null) {
            return $cached;
        }

        // 1. Tam eşleşme (en yaygın durum)
        $profile = DestinationProfile::where('normalized_city', $normalized)->first();

        // 2. Substring match: "paris, roma" gibi multi-city string için
        // DB'de bilinen bir şehir adı (örn. "paris") destination içinde geçiyorsa onu kullan
        if (!$profile) {
            $profile = DestinationProfile::query()
                ->whereRaw('? LIKE CONCAT(\'%\', normalized_city, \'%\')', [$normalized])
                ->orderByDesc('crowd_score')
                ->first();
        }

        if ($profile) {
            $result = $this->profileToArray($profile);
            Cache::put('dest_profile:' . $normalized, $result, self::CACHE_TTL_SECONDS);

            // Schema değiştiyse arka planda yeniden zenginleştir
            if ($profile->needsEnrichment()) {
                $this->scheduleEnrichment($profile->city, $profile->normalized_city);
            }

            return $result;
        }

        // Bilinmeyen şehir: arka planda LLM ile zenginleştir, default skor dön
        $this->scheduleEnrichment($city, $normalized);

        $result = $this->defaultProfile();
        Cache::put('dest_profile:' . $normalized, $result, self::CACHE_TTL_SECONDS);

        return $result;
    }

    /**
     * @return array{crowd: float, lively: float, source: string, country: ?string, summary: ?string, climate_by_month: ?array, vibe_tags: ?array, best_months: ?array, crowded_months: ?array, requires_visa_for_tr: ?bool}
     */
    private function profileToArray(\App\Models\DestinationProfile $profile): array
    {
        return [
            'crowd' => (float) $profile->crowd_score,
            'lively' => (float) $profile->liveliness_score,
            'source' => $profile->source,
            'country' => $profile->country,
            'summary' => $profile->summary,
            'climate_by_month' => $profile->climate_by_month,
            'vibe_tags' => $profile->vibe_tags,
            'best_months' => $profile->best_months,
            'crowded_months' => $profile->crowded_months,
            'requires_visa_for_tr' => $profile->requires_visa_for_tr,
        ];
    }

    /**
     * Aynı bilinmeyen şehir için tekrar tekrar job dispatch etmeyi önle:
     * placeholder kayıt at + lock ile yarış koşulu engelle.
     */
    private function scheduleEnrichment(string $city, string $normalized): void
    {
        $lockKey = 'dest_profile_dispatch:' . $normalized;

        if (!Cache::add($lockKey, 1, 600)) {
            return;
        }

        // Default placeholder — get() bir sonraki çağrıda yine null görmesin
        DestinationProfile::firstOrCreate(
            ['normalized_city' => $normalized],
            [
                'city' => $city,
                'crowd_score' => self::DEFAULT_CROWD,
                'liveliness_score' => self::DEFAULT_LIVELY,
                'source' => DestinationProfile::SOURCE_DEFAULT,
                'reasoning' => 'Pending LLM enrichment',
            ]
        );

        GenerateDestinationProfileJob::dispatch($city);
    }

    /**
     * @return array{crowd: float, lively: float, source: string, country: null, summary: null, climate_by_month: null, vibe_tags: null, best_months: null, crowded_months: null, requires_visa_for_tr: null}
     */
    private function defaultProfile(): array
    {
        return [
            'crowd' => self::DEFAULT_CROWD,
            'lively' => self::DEFAULT_LIVELY,
            'source' => DestinationProfile::SOURCE_DEFAULT,
            'country' => null,
            'summary' => null,
            'climate_by_month' => null,
            'vibe_tags' => null,
            'best_months' => null,
            'crowded_months' => null,
            'requires_visa_for_tr' => null,
        ];
    }
}
