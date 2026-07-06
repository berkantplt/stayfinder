<?php

namespace App\Console\Commands;

use App\Models\AiSearchLog;
use App\Models\Tour;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * "Gösterildi ama tıklanmadı" öğrenimi: son 90 günün loglarından tur başına
 * gösterim/tıklama sayılır, Bayes-yumuşatmalı skor üretilir ve ±0.03 SINIRLI
 * bonus/ceza olarak tours.ai_ctr_bonus'a yazılır. Sınır şart: sınırsız
 * popülerlik sinyali zengin-daha-zengin döngüsüyle yeni turları gömer.
 */
class UpdateCtrPriors extends Command
{
    protected $signature = 'ai:update-ctr-priors {--days=90}';

    protected $description = 'Tur bazlı tıklama önceliklerini (sınırlı skor düzeltmesi) günceller.';

    public function handle(): void
    {
        $impressions = [];
        $clicks = [];

        AiSearchLog::query()
            ->where('created_at', '>=', now()->subDays((int) $this->option('days')))
            ->select(['result_tour_ids', 'selected_tour_id'])
            ->cursor()
            ->each(function ($log) use (&$impressions, &$clicks) {
                // Gösterim = sohbette görünen ilk 7 sonuç
                foreach (array_slice((array) ($log->result_tour_ids ?? []), 0, 7) as $id) {
                    $impressions[(int) $id] = ($impressions[(int) $id] ?? 0) + 1;
                }
                if ($log->selected_tour_id) {
                    $clicks[(int) $log->selected_tour_id] = ($clicks[(int) $log->selected_tour_id] ?? 0) + 1;
                }
            });

        if (empty($impressions)) {
            $this->info('Gösterim verisi yok — atlandı.');

            return;
        }

        $totalImpressions = array_sum($impressions);
        $totalClicks = array_sum($clicks);
        $globalCtr = $totalImpressions > 0 ? $totalClicks / $totalImpressions : 0.0;
        $k = 20; // Bayes yumuşatma: az gösterimli turlar globale çekilir

        $updated = 0;
        foreach ($impressions as $tourId => $imps) {
            $smoothed = (($clicks[$tourId] ?? 0) + $k * $globalCtr) / ($imps + $k);
            // Globale göre sapmayı ±0.03'e sıkıştır
            $bonus = $globalCtr > 0
                ? max(-0.03, min(0.03, (($smoothed / $globalCtr) - 1) * 0.03))
                : 0.0;

            DB::table('tours')->where('id', $tourId)->update(['ai_ctr_bonus' => round($bonus, 4)]);
            $updated++;
        }

        $this->info("CTR öncelikleri güncellendi: {$updated} tur (global CTR: ".round($globalCtr * 100, 1)."%).");
    }
}
