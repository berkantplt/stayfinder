<?php

namespace App\Services\AiSearch;

use App\Models\Tour;
use App\Support\DestinationFilter;
use App\Support\TurkishText;
use Illuminate\Database\Eloquent\Builder;

/**
 * Hibrit arama skorlama eksenleri: bütçe/süre/vize, destinasyon eşleşmesi ve
 * kısıtları, doğa/sakinlik/hareketlilik uyumu, ay skoru ve vibe bonusu.
 * AiSearchController'dan davranış birebir korunarak taşındı; metotlar test
 * edilebilirlik için public.
 */
class TourScorer
{
    public function scoreBudget(float $price, ?int $maxBudget): float
    {
        if (! $maxBudget || $maxBudget <= 0) {
            return 1.0;
        }
        if ($price <= $maxBudget) {
            return 1.0;
        }

        $overRatio = ($price - $maxBudget) / max(1, $maxBudget);

        return max(0.0, 1.0 - min(1.0, $overRatio));
    }

    /**
     * $actual artık null olabilir: requires_visa üç durumlu (vizeli/vizesiz/
     * belirtilmemiş). Belirtilmemiş bir tur, vize yönü İSTENEN aramada eşleşmez
     * — sert filtre (sorgudaki where) onu zaten eliyor, buradaki 0.0 ikinci
     * güvenlik ağı. Tip bool kalsaydı null sessizce false'a çökerdi ve
     * "vizesiz ara" belirtilmemiş turları vizesiz sayardı.
     */
    public function scoreExactBool(?bool $actual, ?bool $expected): float
    {
        if ($expected === null) {
            return 1.0;
        }

        return $actual === $expected ? 1.0 : 0.0;
    }

    public function scoreDuration(int $days, ?int $minDays, ?int $maxDays): float
    {
        if (! $minDays && ! $maxDays) {
            return 1.0;
        }

        $min = $minDays && $minDays > 0 ? $minDays : null;
        $max = $maxDays && $maxDays > 0 ? $maxDays : null;

        if ($min !== null && $max !== null && $min > $max) {
            [$min, $max] = [$max, $min];
        }

        if ($min !== null && $max !== null) {
            if ($days >= $min && $days <= $max) {
                return 1.0;
            }
            $distance = $days < $min ? ($min - $days) : ($days - $max);

            return max(0.0, 1.0 - min(1.0, $distance / 7));
        }

        if ($min !== null) {
            if ($days >= $min) {
                return 1.0;
            }

            return max(0.0, 1.0 - min(1.0, ($min - $days) / 7));
        }

        if ($max !== null) {
            if ($days <= $max) {
                return 1.0;
            }

            return max(0.0, 1.0 - min(1.0, ($days - $max) / 7));
        }

        return 1.0;
    }

    public function computeCompatibilityScore(array $scores, array $criteria): float
    {
        // Skor matematiği TEK KAYNAK'ta: canlı arama, replay değerlendirme ve
        // ağırlık kalibrasyonu aynı fonksiyonu kullanır. Kalibre edilmiş
        // ağırlıklar Cache override'ı ile devreye girer.
        return \App\Support\AiWeightEvaluator::score($scores, $criteria);
    }

    public function scoreDestinationMatch(Tour $tour, ?string $preferredDestination, array $excludedDestinations): float
    {
        if (! empty($excludedDestinations) && $this->matchesAnyDestination($tour, $excludedDestinations)) {
            return 0.0;
        }

        if ($preferredDestination === null) {
            return 1.0;
        }

        return $this->matchesRequestedDestination($tour, $preferredDestination) ? 1.0 : 0.05;
    }

    public function destinationSearchTerms(string $preferredDestination): array
    {
        $sanitized = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $preferredDestination);
        $sanitized = trim(preg_replace('/\s+/u', ' ', (string) $sanitized));
        $raw = mb_strtolower($sanitized, 'UTF-8');
        $normalized = TurkishText::normalize($sanitized);

        $terms = array_values(array_unique(array_filter([$raw, $normalized])));
        foreach (preg_split('/\s+/', $normalized) ?: [] as $piece) {
            if (mb_strlen($piece, 'UTF-8') >= 4) {
                $terms[] = $piece;
            }
        }

        return array_values(array_unique($terms));
    }

    public function matchesRequestedDestination(Tour $tour, string $preferredDestination): bool
    {
        $haystack = TurkishText::normalize((string) $tour->destination.' '.(string) $tour->title);
        foreach ($this->destinationSearchTerms($preferredDestination) as $term) {
            if (str_contains($haystack, TurkishText::normalize($term))) {
                return true;
            }
        }

        return false;
    }

    public function matchesAnyDestination(Tour $tour, array $destinations): bool
    {
        foreach ($destinations as $destination) {
            if ($this->matchesRequestedDestination($tour, (string) $destination)) {
                return true;
            }
        }

        return false;
    }

    public function destinationInList(string $destination, array $destinations): bool
    {
        $normalizedDestination = TurkishText::normalize($destination);
        if ($normalizedDestination === '') {
            return false;
        }

        foreach ($destinations as $item) {
            $normalizedItem = TurkishText::normalize((string) $item);
            if ($normalizedItem === '') {
                continue;
            }

            if (
                $normalizedItem === $normalizedDestination ||
                str_contains($normalizedItem, $normalizedDestination) ||
                str_contains($normalizedDestination, $normalizedItem)
            ) {
                return true;
            }
        }

        return false;
    }

    public function applyDestinationConstraint(Builder $query, string $preferredDestination): void
    {
        $terms = $this->destinationSearchTerms($preferredDestination);
        if (empty($terms)) {
            return;
        }

        // Kolon ifadesi ile terim AYNI normalizasyondan geçmeli. Eskiden terim
        // PHP'de, kolon SQL LOWER() ile küçültülüyordu; ikisi 'İ' ve 'ı' harflerinde
        // ayrışıyor ve "Ayvalık" kaydı hiçbir aramada bulunamıyordu.
        $destinasyon = DestinationFilter::columnExpression('destination');
        $baslik = DestinationFilter::columnExpression('title');

        $query->where(function (Builder $destinationQuery) use ($terms, $destinasyon, $baslik) {
            foreach ($terms as $index => $term) {
                $operator = $index === 0 ? 'whereRaw' : 'orWhereRaw';
                $like = '%'.DestinationFilter::normalize($term).'%';

                $destinationQuery->{$operator}("{$destinasyon} LIKE ?", [$like]);
                $destinationQuery->orWhereRaw("{$baslik} LIKE ?", [$like]);
            }
        });
    }

    public function applyExcludedDestinationsConstraint(Builder $query, array $excludedDestinations): void
    {
        if (empty($excludedDestinations)) {
            return;
        }

        $query->where(function (Builder $scope) use ($excludedDestinations) {
            foreach ($excludedDestinations as $destination) {
                $terms = $this->destinationSearchTerms((string) $destination);
                foreach ($terms as $term) {
                    $like = '%'.DestinationFilter::normalize($term).'%';
                    $destinasyon = DestinationFilter::columnExpression("COALESCE(destination, '')");
                    $baslik = DestinationFilter::columnExpression("COALESCE(title, '')");
                    $scope->where(function (Builder $row) use ($like, $destinasyon, $baslik) {
                        $row->whereRaw("{$destinasyon} NOT LIKE ?", [$like])
                            ->whereRaw("{$baslik} NOT LIKE ?", [$like]);
                    });
                }
            }
        });
    }

    public function scoreNatureFit(
        string $title,
        string $destination,
        string $description,
        string $included,
        ?bool $wantsNature,
        ?bool $avoidCrowdedCity
    ): float {
        if ($wantsNature !== true && $avoidCrowdedCity !== true) {
            return 1.0;
        }

        $haystack = TurkishText::normalize(trim($title.' '.$destination.' '.$description.' '.$included));
        $positiveKeywords = ['doga', 'yesil', 'orman', 'yayla', 'kamp', 'trek', 'yuruyus', 'gol', 'nehir', 'kanyon', 'koy', 'deniz', 'sahil', 'adalar', 'milli park', 'huzur', 'sakin'];
        $urbanKeywords = ['bogaz', 'sehir', 'merkez', 'metropol', 'trafik', 'avm', 'isiklar', 'taksim', 'kadikoy', 'besiktas', 'gece hayati'];

        $positiveHits = $this->countHits($haystack, $positiveKeywords);
        $urbanHits = $this->countHits($haystack, $urbanKeywords);

        $positiveScore = min(1.0, $positiveHits / 4);
        $urbanPenalty = min(1.0, $urbanHits / 4);
        $keywordScore = $this->clamp01(0.45 + ($positiveScore * 0.65) - ($urbanPenalty * 0.55));

        if ($avoidCrowdedCity === true && $this->isCrowdedCity($destination)) {
            $keywordScore *= 0.45;
        }

        return $this->clamp01($keywordScore);
    }

    /**
     * Kullanıcı niyetiyle destinasyonun vibe_tags'i arasında deterministik bonus/penalty.
     *
     * wants_nature=true + "nature" tag         => +0.10
     * wants_lively=true + "nightlife"/"luxury" => +0.10
     * avoid_crowded=true + "nightlife"/"shopping" => -0.08
     *
     * Hiçbir niyet yoksa veya tag yoksa 0 döner — diğer skorlama bozulmaz.
     *
     * @param  array<int, string>|null  $vibeTags
     */
    public function scoreVibeMatch(?bool $wantsNature, ?bool $wantsLively, ?bool $avoidCrowdedCity, ?array $vibeTags): float
    {
        if (empty($vibeTags) || ! is_array($vibeTags)) {
            return 0.0;
        }

        if ($wantsNature === null && $wantsLively === null && $avoidCrowdedCity === null) {
            return 0.0;
        }

        $score = 0.0;

        if ($wantsNature === true && in_array('nature', $vibeTags, true)) {
            $score += 0.10;
        }

        if ($wantsLively === true
            && (in_array('nightlife', $vibeTags, true) || in_array('luxury', $vibeTags, true))
        ) {
            $score += 0.10;
        }

        if ($avoidCrowdedCity === true
            && (in_array('nightlife', $vibeTags, true) || in_array('shopping', $vibeTags, true))
        ) {
            $score -= 0.08;
        }

        return $score;
    }

    public function scoreCityEscape(string $destination, ?bool $avoidCrowdedCity): float
    {
        if ($avoidCrowdedCity !== true) {
            return 1.0;
        }

        $crowd = $this->destinationDynamics($destination)['crowd'];
        if ($crowd <= 0.55) {
            return 1.0;
        }
        if ($crowd <= 0.70) {
            return 0.78;
        }
        if ($crowd <= 0.82) {
            return 0.48;
        }

        return 0.12;
    }

    public function scoreLivelyFit(
        string $title,
        string $destination,
        string $description,
        string $included,
        ?bool $wantsLively,
        ?bool $avoidCrowdedCity,
        ?float $paceScore = null
    ): float {
        if ($wantsLively === null) {
            return 1.0;
        }

        $haystack = TurkishText::normalize(trim($title.' '.$destination.' '.$description.' '.$included));
        $livelyKeywords = ['hareketli', 'canli', 'eglence', 'gece hayati', 'festival', 'bar', 'sahil', 'marina', 'club'];
        $calmKeywords = ['sakin', 'sessiz', 'huzurlu', 'kamp', 'yayla', 'doga', 'dinlenme'];

        $livelyHits = $this->countHits($haystack, $livelyKeywords);
        $calmHits = $this->countHits($haystack, $calmKeywords);

        $profile = $this->destinationDynamics($destination);
        $activitySignal = $this->clamp01(
            0.30
            + (($profile['lively'] - 0.35) * 1.10)
            + ($livelyHits * 0.08)
            - ($calmHits * 0.09)
            // Tur karakteri temposu (ölçülmüş, tur başına tek seferlik LLM):
            // kelime sayımından güvenilir ama sınırlı katsayıyla — dinlenme
            // turu (pace≈0.2) sinyali düşürür, tempolu tur (0.9) yükseltir
            + ($paceScore !== null ? ($paceScore - 0.5) * 0.20 : 0.0)
        );

        if ($wantsLively === false) {
            return $this->clamp01(1.0 - $activitySignal);
        }

        $score = $activitySignal;
        if ($avoidCrowdedCity === true) {
            $crowd = $profile['crowd'];
            if ($crowd >= 0.92) {
                $score *= 0.30;
            } elseif ($crowd >= 0.86) {
                $score *= 0.48;
            } elseif ($crowd >= 0.78) {
                $score *= 0.68;
            } elseif ($crowd <= 0.42) {
                $score *= 0.50;
            } elseif ($crowd <= 0.52) {
                $score *= 0.72;
            } elseif ($crowd >= 0.58 && $crowd <= 0.74) {
                $score = min(1.0, $score + 0.08);
            }
        }

        return $this->clamp01($score);
    }

    /**
     * Şehir profilini DestinationProfileService üzerinden alır.
     * Bilinmeyen şehir → arka planda LLM job dispatch + default 0.5/0.5.
     * Bir sonraki sorguda gerçek skor döner.
     *
     * @return array{crowd: float, lively: float}
     */
    public function destinationDynamics(string $destination): array
    {
        $profile = app(DestinationProfileService::class)->get($destination);

        return [
            'crowd' => $profile['crowd'],
            'lively' => $profile['lively'],
        ];
    }

    /**
     * Ay skoru — turun TÜM gelecekteki kalkış tarihlerine bakar: çok tarihli
     * turlarda ana tarih eylül değil diye ekimdeki kalkış görmezden gelinmesin.
     * En iyi eşleşen tarihin skoru döner.
     */
    public function scoreMonthForTour($tour, ?int $preferredMonth): float
    {
        if ($preferredMonth === null || $preferredMonth < 1 || $preferredMonth > 12) {
            return 1.0;
        }

        $candidates = [];
        if ($tour->relationLoaded('dates')) {
            foreach ($tour->dates as $tourDate) {
                if ($tourDate->departure_date && $tourDate->departure_date->greaterThanOrEqualTo(now()->startOfDay())) {
                    $candidates[] = (string) $tourDate->departure_date;
                }
            }
        }
        if (empty($candidates) && $tour->departure_date) {
            $candidates[] = (string) $tour->departure_date;
        }
        if (empty($candidates)) {
            return $this->scoreMonth('', $preferredMonth);
        }

        $best = 0.0;
        foreach ($candidates as $candidate) {
            $best = max($best, $this->scoreMonth($candidate, $preferredMonth));
            if ($best >= 1.0) {
                break;
            }
        }

        return $best;
    }

    public function scoreMonth(string $departureDate, ?int $preferredMonth): float
    {
        if ($preferredMonth === null || $preferredMonth < 1 || $preferredMonth > 12) {
            return 1.0;
        }

        if (trim($departureDate) === '') {
            return 0.55;
        }

        try {
            $month = (int) date('n', strtotime($departureDate));
            if ($month <= 0) {
                return 0.55;
            }

            if ($month === $preferredMonth) {
                return 1.0;
            }

            $distance = abs($month - $preferredMonth);
            $distance = min($distance, 12 - $distance);

            return $this->clamp01(1.0 - ($distance / 6));
        } catch (\Throwable $e) {
            return 0.55;
        }
    }

    public function isCrowdedCity(string $destination): bool
    {
        $text = TurkishText::normalize($destination);
        $crowdedCities = ['istanbul', 'ankara', 'izmir', 'bursa', 'adana', 'konya', 'gaziantep', 'kocaeli', 'mersin'];
        foreach ($crowdedCities as $city) {
            if (str_contains($text, $city)) {
                return true;
            }
        }

        return false;
    }

    public function clamp01(float $value): float
    {
        return max(0.0, min(1.0, $value));
    }

    /** Haystack içinde geçen anahtar kelime sayısı (scoreNatureFit/scoreLivelyFit ortak döngüsü). */
    private function countHits(string $haystack, array $needles): int
    {
        $hits = 0;
        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                $hits++;
            }
        }

        return $hits;
    }
}
