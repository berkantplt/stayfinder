<?php

namespace App\Console\Commands;

use App\Models\AiSearchLog;
use App\Support\AiWeightEvaluator;
use Illuminate\Console\Command;

/**
 * Replay değerlendirme: tıklama sinyalli aramaları kayıtlı bileşen skorlarıyla
 * (API'siz, sıfır maliyet) yeniden sıralayıp mevcut ağırlıkların isabetini ölçer.
 * Ağırlık değişikliği tartışılırken kanıt bu komuttan gelir.
 */
class ReplayAiEval extends Command
{
    protected $signature = 'ai:replay-eval {--days=90 : Kaç günlük log}';

    protected $description = 'AI arama isabet metriklerini (MRR, hit@7) loglardan hesaplar.';

    public function handle(): void
    {
        $logs = AiSearchLog::query()
            ->whereNotNull('selected_tour_id')
            ->where('created_at', '>=', now()->subDays((int) $this->option('days')))
            ->get();

        if ($logs->isEmpty()) {
            $this->warn('Tıklama sinyalli log yok — metrik hesaplanamadı.');

            return;
        }

        $metrics = AiWeightEvaluator::evaluate($logs);

        $this->table(
            ['Örneklem (n)', 'MRR', 'hit@7'],
            [[$metrics['n'], $metrics['mrr'], $metrics['hit_at_7']]]
        );

        if ($metrics['n'] < 50) {
            $this->comment("Dikkat: n={$metrics['n']} istatistiksel olarak küçük — sonuçları yön göstergesi olarak oku, kesin hüküm verme.");
        }
        if ($metrics['n'] < $logs->count()) {
            $this->comment(($logs->count() - $metrics['n']).' log bileşen skorları eksik olduğu için atlandı (eski format).');
        }
    }
}
