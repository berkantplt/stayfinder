<?php

namespace App\Observers;

use App\Jobs\GenerateTourEmbeddingJob;
use App\Models\TourDate;

/**
 * Tur tarihleri değişince ilgili turun embedding'i bayatlar: search_text ve
 * vektör, kalkış aylarını (GenerateTourEmbeddingJob → $tour->dates) içerir.
 * Tarih eklenince/silinince/değişince tura yeniden embedding üretimi tetiklenir.
 */
class TourDateObserver
{
    public function created(TourDate $date): void
    {
        $this->regenerate($date);
    }

    public function updated(TourDate $date): void
    {
        // Yalnızca aramaya giren alan (departure_date) değiştiyse yeniden üret
        if ($date->wasChanged('departure_date')) {
            $this->regenerate($date);
        }
    }

    public function deleted(TourDate $date): void
    {
        $this->regenerate($date);
    }

    private function regenerate(TourDate $date): void
    {
        if ($date->tour_id) {
            GenerateTourEmbeddingJob::dispatch($date->tour_id)->onQueue('default');
        }
    }
}
