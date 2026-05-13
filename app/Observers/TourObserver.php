<?php

namespace App\Observers;

use App\Jobs\GenerateTourEmbeddingJob;
use App\Jobs\ResearchDestinationJob;
use App\Models\Destination;
use App\Models\Tour;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TourObserver
{
    /**
     * Yeni tur oluşturulduğunda embedding'ini arka planda üret +
     * destinasyon AI araştırmasını tetikle.
     */
    public function created(Tour $tour): void
    {
        Log::info("[TourObserver] Yeni tur eklendi: #{$tour->id} ({$tour->title}). Embedding kuyruğa alınıyor...");
        GenerateTourEmbeddingJob::dispatch($tour->id)->onQueue('default');
        $this->triggerDestinationResearch($tour);
    }

    /**
     * Tur güncellendiğinde, AI aramasını etkileyen bir alan değiştiyse
     * embedding'i yeniden oluştur. Destinasyon değiştiyse yeni destinasyonu araştır.
     */
    public function updated(Tour $tour): void
    {
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

        if ($tour->wasChanged('destination')) {
            $this->triggerDestinationResearch($tour);
        }
    }

    /**
     * Tur destinasyonunu Destination tablosunda bul/oluştur,
     * AI araştırması gerekiyorsa job dispatch et.
     */
    protected function triggerDestinationResearch(Tour $tour): void
    {
        $name = trim((string) $tour->destination);
        if ($name === '') return;

        $destination = Destination::findByName($name);
        if (!$destination) {
            $destination = Destination::create([
                'name' => $name,
                'slug' => Str::slug($name),
                'is_active' => true,
            ]);
        }

        if (!$destination->aiResearchIsFresh()) {
            ResearchDestinationJob::dispatch($destination->id)->onQueue('default');
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
