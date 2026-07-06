<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Uyumluluk skorunun TEK KAYNAK matematiği + log verisiyle offline değerlendirme.
 * Canlı skorlama (AiSearchController) ve kalibrasyon/replay komutları aynı
 * fonksiyonu kullanır — ağırlık değişikliği önce burada loglara karşı test edilir.
 */
class AiWeightEvaluator
{
    public const CACHE_KEY = 'ai:scoring_weights';

    /** @return array<string, float> */
    public static function defaultWeights(): array
    {
        return [
            'budget' => 0.16,
            'international' => 0.08,
            'visa' => 0.07,
            'duration' => 0.10,
            'nature' => 0.09,
            'city_escape' => 0.12,
            'lively' => 0.14,
            'month' => 0.06,
            'destination' => 0.16,
        ];
    }

    /** Aktif ağırlıklar: varsayılan + kalibrasyonla onaylanmış override'lar. */
    public static function activeWeights(): array
    {
        return array_merge(self::defaultWeights(), (array) Cache::get(self::CACHE_KEY, []));
    }

    /**
     * Hibrit uyumluluk skoru (AiSearchController::computeCompatibilityScore'un
     * taşınmış hali — davranış birebir aynı).
     *
     * @param  array<string, float>  $scores  Eksen skorları (semantic dahil)
     * @param  array<string, mixed>  $criteria  Uygulanan filtreler
     * @param  array<string, float>|null  $weights  null → aktif ağırlıklar
     */
    public static function score(array $scores, array $criteria, ?array $weights = null): float
    {
        $weights = $weights ?? self::activeWeights();

        $active = [
            'budget' => ! empty($criteria['max_budget']) && (int) $criteria['max_budget'] > 0,
            'international' => ($criteria['is_international'] ?? null) !== null,
            'visa' => ($criteria['requires_visa'] ?? null) !== null,
            'duration' => ((int) ($criteria['preferred_min_days'] ?? 0) > 0) || ((int) ($criteria['preferred_max_days'] ?? 0) > 0),
            'nature' => ($criteria['wants_nature'] ?? null) === true,
            'city_escape' => ($criteria['avoid_crowded_city'] ?? null) === true,
            'lively' => ($criteria['wants_lively'] ?? null) !== null,
            'month' => (int) ($criteria['preferred_month'] ?? 0) > 0,
            'destination' => ! empty($criteria['preferred_destination']),
        ];

        $activeCount = count(array_filter($active, fn ($isActive) => $isActive === true));
        $baseWeight = max(0.30, 0.56 - ($activeCount * 0.06));

        if (($criteria['wants_lively'] ?? null) === true && ($criteria['avoid_crowded_city'] ?? null) === true) {
            $weights['lively'] = 0.22;
            $weights['city_escape'] = 0.10;
        }

        if (! empty($criteria['preferred_destination'])) {
            $weights['destination'] = max($weights['destination'], 0.22);
        }

        $weighted = $baseWeight * (float) ($scores['semantic'] ?? 0.0);
        $totalWeight = $baseWeight;

        foreach ($weights as $key => $weight) {
            if (! ($active[$key] ?? false)) {
                continue;
            }

            $weighted += $weight * (float) ($scores[$key] ?? 0.0);
            $totalWeight += $weight;
        }

        if ($totalWeight <= 0) {
            return max(0.0, min(1.0, (float) ($scores['semantic'] ?? 0.0)));
        }

        return max(0.0, min(1.0, $weighted / $totalWeight));
    }

    /**
     * Replay değerlendirme: tıklama sinyalli loglar üzerinde verilen ağırlık
     * setiyle sıralamayı YENİDEN kurar (API'siz — bileşen skorları logda hazır)
     * ve MRR / hit@7 döner. Yalnızca bileşenleri tam kayıtlı loglar sayılır.
     *
     * @param  iterable<\App\Models\AiSearchLog>  $logs
     * @return array{n: int, mrr: float, hit_at_7: float}
     */
    public static function evaluate(iterable $logs, ?array $weights = null): array
    {
        $n = 0;
        $mrrSum = 0.0;
        $hitSum = 0;

        foreach ($logs as $log) {
            $selected = (int) $log->selected_tour_id;
            $items = collect($log->result_scores ?? []);
            if ($selected <= 0 || $items->isEmpty()) {
                continue;
            }
            // Eski loglarda bileşenler eksik olabilir — dürüstlük: onları atla
            if (! isset($items->first()['budget_score'])) {
                continue;
            }

            $criteria = (array) ($log->applied_filters ?? []);

            $ranked = $items->map(function ($item) use ($criteria, $weights) {
                $base = self::score([
                    'semantic' => (float) ($item['semantic_score'] ?? 0),
                    'budget' => (float) ($item['budget_score'] ?? 0),
                    'international' => (float) ($item['international_score'] ?? 0),
                    'visa' => (float) ($item['visa_score'] ?? 0),
                    'duration' => (float) ($item['duration_score'] ?? 0),
                    'nature' => (float) ($item['nature_score'] ?? 0),
                    'city_escape' => (float) ($item['city_escape_score'] ?? 0),
                    'lively' => (float) ($item['lively_score'] ?? 0),
                    'month' => (float) ($item['month_score'] ?? 0),
                    'destination' => (float) ($item['destination_score'] ?? 0),
                ], $criteria, $weights);

                // Skor-sonrası düzeltmeler logdan aynen uygulanır
                $final = $base
                    + (float) ($item['vibe_score'] ?? 0)
                    + (float) ($item['seasonal_bonus'] ?? 0)
                    + (float) ($item['rejection_penalty'] ?? 0);

                return ['tour_id' => (int) $item['tour_id'], 'score' => $final];
            })->sortByDesc('score')->values();

            $rank = $ranked->search(fn ($r) => $r['tour_id'] === $selected);
            if ($rank === false) {
                continue;
            }

            $n++;
            $mrrSum += 1 / ($rank + 1);
            $hitSum += ($rank < 7) ? 1 : 0;
        }

        return [
            'n' => $n,
            'mrr' => $n > 0 ? round($mrrSum / $n, 4) : 0.0,
            'hit_at_7' => $n > 0 ? round($hitSum / $n, 4) : 0.0,
        ];
    }
}
