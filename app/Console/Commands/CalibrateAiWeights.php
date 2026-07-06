<?php

namespace App\Console\Commands;

use App\Models\AiSearchLog;
use App\Support\AiWeightEvaluator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Skor ağırlığı kalibrasyonu: replay seti üzerinde her ekseni mevcut değerinin
 * ±0.04 komşuluğunda tarar (koordinat araması), en iyi seti KANITIYLA raporlar.
 * OTOMATİK UYGULAMAZ — insan onayı: --apply bayrağıyla bilinçli çalıştırılır.
 * Korkuluklar: min 50 örnek, eksen başına ±0.04 sınır, net iyileşme şartı.
 */
class CalibrateAiWeights extends Command
{
    protected $signature = 'ai:calibrate-weights {--days=90} {--apply : Önerilen seti aktifleştir}';

    protected $description = 'Skor ağırlıklarını log verisiyle kalibre eder (insan onaylı).';

    public function handle(): void
    {
        $logs = AiSearchLog::query()
            ->whereNotNull('selected_tour_id')
            ->where('created_at', '>=', now()->subDays((int) $this->option('days')))
            ->get();

        $current = AiWeightEvaluator::activeWeights();
        $baseline = AiWeightEvaluator::evaluate($logs, $current);

        if ($baseline['n'] < 50) {
            $this->warn("Yetersiz veri: n={$baseline['n']} (< 50). Kalibrasyon güvenilir değil — yalnızca rapor:");
            $this->table(['n', 'MRR', 'hit@7'], [[$baseline['n'], $baseline['mrr'], $baseline['hit_at_7']]]);

            return;
        }

        $this->info("Taban: n={$baseline['n']}, MRR={$baseline['mrr']}, hit@7={$baseline['hit_at_7']}");

        // Koordinat araması: her eksen tek tek ±0.04 (0.02 adım), iyileşme varsa tut
        $best = $current;
        $bestMrr = $baseline['mrr'];
        $defaults = AiWeightEvaluator::defaultWeights();

        foreach (array_keys($defaults) as $axis) {
            foreach ([-0.04, -0.02, 0.02, 0.04] as $delta) {
                $candidate = $best;
                // Korkuluk: varsayılandan en fazla ±0.04 sapma
                $newValue = round($defaults[$axis] + $delta, 2);
                if (abs($newValue - $defaults[$axis]) > 0.041 || $newValue <= 0) {
                    continue;
                }
                $candidate[$axis] = $newValue;

                $m = AiWeightEvaluator::evaluate($logs, $candidate);
                if ($m['mrr'] > $bestMrr) {
                    $best = $candidate;
                    $bestMrr = $m['mrr'];
                }
            }
        }

        if ($bestMrr <= $baseline['mrr'] + 0.005) {
            $this->info('Anlamlı iyileşme bulunamadı — mevcut ağırlıklar yerinde.');

            return;
        }

        $rows = [];
        foreach ($best as $axis => $value) {
            if (abs($value - $current[$axis]) > 0.001) {
                $rows[] = [$axis, $current[$axis], $value];
            }
        }
        $this->table(['Eksen', 'Mevcut', 'Önerilen'], $rows);
        $this->info("Önerilen set MRR: {$baseline['mrr']} → {$bestMrr}");

        if ($this->option('apply')) {
            Cache::forever(AiWeightEvaluator::CACHE_KEY, $best);
            $this->info('✓ Önerilen ağırlıklar AKTİFLEŞTİRİLDİ (Cache override). Geri almak: Cache::forget("'.AiWeightEvaluator::CACHE_KEY.'")');
        } else {
            $this->comment('Uygulamak için: php artisan ai:calibrate-weights --apply');
        }
    }
}
