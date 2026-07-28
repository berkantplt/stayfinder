<?php

namespace App\Console\Commands;

use App\Models\TourRubricScore;
use App\Services\Matching\Rubric;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Altın küme kalite kapısı (brif §3.5): elle puanlanmış turlarla LLM
 * puanları arasındaki ortalama mutlak hatayı ölçer. Prompt veya model her
 * değiştiğinde koşulmalı; MAE >= 0.5 ise yeni puanlar yayına alınmamalı.
 */
class RubricGoldenMae extends Command
{
    protected $signature = 'app:rubric-golden-mae';

    protected $description = 'Altın küme MAE ölçümü (hedef < 0.5, 5\'li ölçekte)';

    public function handle(): int
    {
        $golden = json_decode(File::get(resource_path('rubric/golden-set.json')), true);
        $entries = collect($golden['entries'] ?? [])->filter(fn ($e) => ! empty($e['tour_id']));

        if ($entries->isEmpty()) {
            $this->warn('Altın kümede tour_id dolu kayıt yok — editörün resources/rubric/golden-set.json dosyasını gerçek turlarla doldurması gerekiyor (hedef 25-30 tur).');

            return self::FAILURE;
        }

        $toplamFark = 0;
        $karsilastirma = 0;
        $eksik = [];

        foreach ($entries as $entry) {
            $score = TourRubricScore::where('tour_id', $entry['tour_id'])
                ->where('rubric_version', Rubric::VERSION)
                ->first();
            if (! $score) {
                $eksik[] = $entry['tour_id'];
                continue;
            }
            foreach (Rubric::dimensions() as $d) {
                $elle = $entry['scores'][$d] ?? null;
                $llm = $score->scores[$d]['value'] ?? null;
                if (is_numeric($elle) && is_numeric($llm)) {
                    $toplamFark += abs($elle - $llm);
                    $karsilastirma++;
                }
            }
        }

        if ($karsilastirma === 0) {
            $this->error('Karşılaştırılabilir boyut yok'.($eksik ? ' (LLM puanı eksik turlar: '.implode(',', $eksik).')' : '').'.');

            return self::FAILURE;
        }

        $mae = round($toplamFark / $karsilastirma, 3);
        $this->info("MAE: {$mae} ({$karsilastirma} boyut karşılaştırması, ".$entries->count().' tur)');
        if ($eksik) {
            $this->warn('LLM puanı olmayan altın küme turları: '.implode(', ', $eksik));
        }

        if ($mae >= 0.5) {
            $this->error('MAE hedefin (0.5) üstünde — bu prompt/model kombinasyonunu YAYINA ALMA.');

            return self::FAILURE;
        }
        $this->info('Kalite kapısı geçildi (MAE < 0.5).');

        return self::SUCCESS;
    }
}
