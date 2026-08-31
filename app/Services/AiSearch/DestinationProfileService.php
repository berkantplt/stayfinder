<?php

namespace App\Services\AiSearch;

use App\Jobs\GenerateDestinationProfileJob;
use App\Models\DestinationProfile;
use App\Support\TurkishMonths;
use Illuminate\Support\Facades\Cache;

class DestinationProfileService
{
    /** Default değerler — yeni şehir görüldüğünde job tamamlanana kadar dönen orta-skor. */
    public const DEFAULT_CROWD = 0.50;
    public const DEFAULT_LIVELY = 0.50;

    /** vibe_tags whitelist'inin Türkçe karşılıkları (kullanıcıya dönük metin). */
    public const VIBE_LABELS_TR = [
        'beach' => 'plaj', 'luxury' => 'lüks', 'shopping' => 'alışveriş',
        'nightlife' => 'gece hayatı', 'cultural' => 'kültür', 'historical' => 'tarih',
        'nature' => 'doğa', 'family' => 'aile dostu', 'adventure' => 'macera',
        'spa' => 'spa/termal', 'religious' => 'inanç turizmi',
        'winter_sport' => 'kış sporu', 'cruise' => 'gemi turu',
    ];

    /** Dış kullanıcılar bu ada bağlı — ad korunur, içerik TEK kaynaktan gelir. */
    public const MONTH_NAMES_TR = TurkishMonths::NAMES;

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
     * Sayısal kalabalık skorunu kullanıcıya dönük Türkçe niteleyiciye çevirir.
     * Eşikler seeder'daki elle kalibre değerlerle hizalı (İstanbul 0.98,
     * Kapadokya 0.54, Rize 0.42).
     */
    public static function crowdLabel(float $crowd): string
    {
        return match (true) {
            $crowd >= 0.80 => 'çok kalabalık ve turistik',
            $crowd >= 0.60 => 'hareketli, turist yoğunluğu yüksek',
            $crowd <= 0.45 => 'sakin ve dingin',
            default => 'orta yoğunlukta',
        };
    }

    /** Canlılık/gece hayatı skorunu Türkçe niteleyiciye çevirir. */
    public static function livelyLabel(float $lively): string
    {
        return match (true) {
            $lively >= 0.75 => 'gece hayatı ve eğlence çok hareketli',
            $lively >= 0.55 => 'gece hayatı canlı',
            $lively <= 0.40 => 'gece hayatı sakin, dinlenme ağırlıklı',
            default => 'gece hayatı orta seviyede',
        };
    }

    /** @param array<int, string>|null $vibeTags */
    public static function vibeLabelsTr(?array $vibeTags): array
    {
        return collect($vibeTags ?? [])
            ->map(fn ($tag) => self::VIBE_LABELS_TR[$tag] ?? null)
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Profili kullanıcıya/prompta dönük tek satırlık Türkçe karakter metnine
     * çevirir. Zenginleşmemiş (default) profilde skorlar orta olduğundan yanlış
     * niteleyici yazmamak için null döner — "asla yanlış veri" kuralı.
     */
    public static function describeProfile(array $profile): ?string
    {
        if (($profile['source'] ?? null) === \App\Models\DestinationProfile::SOURCE_DEFAULT) {
            return null;
        }

        $bits = [
            self::crowdLabel((float) ($profile['crowd'] ?? self::DEFAULT_CROWD)),
            self::livelyLabel((float) ($profile['lively'] ?? self::DEFAULT_LIVELY)),
        ];

        $vibes = self::vibeLabelsTr($profile['vibe_tags'] ?? null);
        if ($vibes !== []) {
            $bits[] = 'öne çıkan: '.implode(', ', array_slice($vibes, 0, 5));
        }

        return implode('; ', $bits);
    }

    /**
     * Çok şehirli destinasyon metnini şehir başına karakter fişine çevirir:
     * splitCities → get → describeProfile üçlemesinin TEK kaynağı (4 kopya
     * buradan beslenir). Biçimlendirme çağıran tarafta kalır — üretilen prompt
     * metinleri byte-aynı korunur.
     *
     * @param  string|array<int, string>  $destination  Ham destinasyon metni ya da hazır şehir listesi
     * @param  int|null  $limit  İlk N şehir (null = tümü; controller/TurDetay 4 kullanır)
     * @param  bool  $onlyDescribed  true: karakteri null (zenginleşmemiş profil) olan şehirler elenir;
     *                               false: hepsi döner (çağıran fallback metnini/filtreyi kendi uygular)
     * @return array<int, array{city: string, character: ?string, summary: ?string}>
     */
    public function describeCities(string|array $destination, ?int $limit = 4, bool $onlyDescribed = true): array
    {
        $cities = is_array($destination)
            ? $destination
            : DestinationProfile::splitCities($destination);

        return collect($cities)
            ->when($limit !== null, fn ($c) => $c->take($limit))
            ->map(function ($city) {
                $profile = $this->get((string) $city);

                return [
                    'city' => (string) $city,
                    'character' => self::describeProfile($profile),
                    'summary' => $profile['summary'] ?? null,
                ];
            })
            ->when($onlyDescribed, fn ($c) => $c->filter(fn ($entry) => $entry['character'] !== null))
            ->values()
            ->all();
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
