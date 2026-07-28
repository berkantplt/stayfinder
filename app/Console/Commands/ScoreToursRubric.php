<?php

namespace App\Console\Commands;

use App\Jobs\ScoreTourRubricJob;
use App\Models\Tour;
use App\Models\TourRubricScore;
use App\Services\Matching\Rubric;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Rubrik puanı eksik/bayat turları kuyruğa alır. Yeni/güncellenen turları
 * TourObserver otomatik yakalar; bu komut geriye dönük tarama içindir.
 */
class ScoreToursRubric extends Command
{
    protected $signature = 'app:score-tours-rubric
        {--all : Pasif turlar dahil}
        {--force : input_hash aynı olsa da yeniden puanla}
        {--limit=0 : En fazla bu kadar tur (0 = sınırsız)}
        {--dry : Sadece sayıyı göster}';

    protected $description = 'Rubrik (v1) puanı eksik veya bayat turlar için ScoreTourRubricJob kuyruğa alır';

    public function handle(): int
    {
        $query = Tour::query()->orderBy('id');
        if (! $this->option('all')) {
            $query->where('is_active', true);
        }

        $scored = TourRubricScore::where('rubric_version', Rubric::VERSION)->get()->keyBy('tour_id');

        $ids = $query->get(['id', 'duration_days', 'destination', 'hotel_info', 'included', 'extras', 'itinerary'])
            ->filter(function (Tour $tour) use ($scored) {
                if ($this->option('force')) {
                    return true;
                }
                $existing = $scored->get($tour->id);
                if (! $existing) {
                    return true;
                }
                $hash = hash('sha256', Rubric::VERSION.'|'.ScoreTourRubricJob::sanitizedInput($tour));

                return $existing->input_hash !== $hash; // program değişmiş → bayat
            })
            ->pluck('id');

        if ($limit = (int) $this->option('limit')) {
            $ids = $ids->take($limit);
        }

        if ($ids->isEmpty()) {
            $this->info('Puanı eksik/bayat tur yok.');

            return self::SUCCESS;
        }
        if ($this->option('dry')) {
            $this->info($ids->count().' tur kuyruğa alınacaktı (--dry). Not: tur başına 2 LLM geçişi yapılır.');

            return self::SUCCESS;
        }

        $dispatched = 0;
        foreach ($ids as $id) {
            if (! Cache::add(ScoreTourRubricJob::DISPATCH_LOCK_PREFIX.$id, 1, 600)) {
                continue;
            }
            ScoreTourRubricJob::dispatch($id, (bool) $this->option('force'));
            $dispatched++;
        }

        $this->info($dispatched.' tur rubrik puanlaması için kuyruğa alındı (tur başına 2 geçiş).');

        return self::SUCCESS;
    }
}
