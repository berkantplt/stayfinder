<?php

namespace App\Observers;

use App\Jobs\GenerateTourEmbeddingJob;
use App\Models\Tour;
use Illuminate\Support\Facades\Log;

class TourObserver
{
    /**
     * Yeni tur oluşturulduğunda embedding'ini arka planda üret.
     */
    public function created(Tour $tour): void
    {
        Log::info("[TourObserver] Yeni tur eklendi: #{$tour->id} ({$tour->title}). Embedding kuyruğa alınıyor...");
        GenerateTourEmbeddingJob::dispatch($tour->id)->onQueue('default');
    }

    /**
     * Tur güncellendiğinde, AI aramasını etkileyen bir alan değiştiyse
     * embedding'i yeniden oluştur.
     */
    public function updated(Tour $tour): void
    {
        // Sadece embedding'i etkileyen alanlar değiştiyse yeniden üret
        $embeddingFields = [
            'title', 'destination', 'description', 'price', 'currency',
            'duration_days', 'included', 'excluded', 'is_international',
            'requires_visa', 'category_id',
        ];

        $needsRegeneration = false;
        foreach ($embeddingFields as $field) {
            if ($tour->wasChanged($field)) {
                $needsRegeneration = true;
                break;
            }
        }

        if ($needsRegeneration) {
            Log::info("[TourObserver] Tur güncellendi: #{$tour->id} ({$tour->title}). Embedding yeniden oluşturulacak...");
            GenerateTourEmbeddingJob::dispatch($tour->id)->onQueue('default');
        }
    }

    /**
     * Tur silindiğinde embedding cache'ini temizle.
     */
    public function deleted(Tour $tour): void
    {
        Log::info("[TourObserver] Tur silindi: #{$tour->id} ({$tour->title}). Destinasyon cache temizleniyor...");
        cache()->forget('ai_search_known_destinations_v1');
    }
}
