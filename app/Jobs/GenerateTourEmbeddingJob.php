<?php

namespace App\Jobs;

use App\Models\Tour;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;

class GenerateTourEmbeddingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [10, 30, 60];

    public function __construct(
        public int $tourId
    ) {}

    public function handle(): void
    {
        $tour = Tour::with('category')->find($this->tourId);

        if (!$tour) {
            Log::warning("[EmbeddingJob] Tour #{$this->tourId} bulunamadı, atlanıyor.");
            return;
        }

        try {
            $inputText = $this->buildEmbeddingText($tour);

            $response = OpenAI::embeddings()->create([
                'model' => 'text-embedding-3-small',
                'input' => $inputText,
            ]);

            $embedding = $response->embeddings[0]->embedding;

            // Observer'ın tekrar tetiklenmesini engellemek için quietly güncelle
            Tour::withoutEvents(function () use ($tour, $embedding) {
                $tour->update(['embedding' => $embedding]);
            });

            Log::info("[EmbeddingJob] Tour #{$tour->id} ({$tour->title}) başarıyla vektörleştirildi.");

        } catch (\Exception $e) {
            Log::error("[EmbeddingJob] Tour #{$tour->id} hata: " . $e->getMessage());
            throw $e; // Retry mekanizmasını tetikle
        }
    }

    private function buildEmbeddingText(Tour $tour): string
    {
        $priceBand = match (true) {
            $tour->price < 7500 => 'ekonomik',
            $tour->price < 15000 => 'orta',
            default => 'premium',
        };

        return implode("\n", array_filter([
            "Tur başlığı: {$tour->title}",
            "Kategori: " . ($tour->category?->name ?? 'belirtilmemiş'),
            "Destinasyon: {$tour->destination}",
            "Süre (gün): {$tour->duration_days}",
            "Fiyat: {$tour->price} {$tour->currency}",
            "Fiyat bandı: {$priceBand}",
            "Yurt dışı mı: " . ($tour->is_international ? 'evet' : 'hayır'),
            "Vize gerekir mi: " . ($tour->requires_visa ? 'evet' : 'hayır'),
            "Dahil olanlar: " . Str::limit((string) $tour->included, 300, '...'),
            "Hariç olanlar: " . Str::limit((string) $tour->excluded, 200, '...'),
            "Açıklama: " . Str::limit((string) $tour->description, 500, '...'),
        ]));
    }
}
