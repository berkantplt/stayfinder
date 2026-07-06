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
            $inputText = self::textFor($tour);

            $response = OpenAI::embeddings()->create([
                'model' => config('ai.embedding_model', 'text-embedding-3-small'),
                'input' => $inputText,
            ]);

            $embedding = $response->embeddings[0]->embedding;

            // Observer'ın tekrar tetiklenmesini engellemek için quietly güncelle.
            // search_text: hibrit aramanın anahtar kelime kanalı — aynı temsilin
            // normalize edilmiş hali (tek kaynaktan, embedding ile senkron kalır).
            Tour::withoutEvents(function () use ($tour, $embedding, $inputText) {
                $tour->update([
                    'embedding' => $embedding,
                    'search_text' => \App\Support\SearchText::normalize($inputText),
                ]);
            });

            Log::info("[EmbeddingJob] Tour #{$tour->id} ({$tour->title}) başarıyla vektörleştirildi.");

        } catch (\Exception $e) {
            Log::error("[EmbeddingJob] Tour #{$tour->id} hata: " . $e->getMessage());
            throw $e; // Retry mekanizmasını tetikle
        }
    }

    /**
     * Turun embedding'e giren TAM temsili — AI'nin turu "tanıdığı" metin budur.
     * Program, otel, ekstralar, kalkış şehirleri ve kalkış ayları da dahil edilir:
     * "yüzme molalı", "yazlık", "İzmir'den kalkan" gibi ifadeler ancak bu alanlar
     * temsile girerse doğru eşleşir. TEK KAYNAK: hem job hem app:generate-tour-embeddings
     * komutu bunu kullanır — iki kopya ayrışmasın.
     */
    public static function textFor(Tour $tour): string
    {
        $tour->loadMissing('category', 'dates');

        $priceBand = match (true) {
            $tour->price < 7500 => 'ekonomik',
            $tour->price < 15000 => 'orta',
            default => 'premium',
        };

        // Program: gün başlıkları + kısaltılmış içerik (rota/aktivite sinyalleri)
        $itinerary = collect(is_array($tour->itinerary) ? $tour->itinerary : [])
            ->map(fn ($day) => trim(($day['title'] ?? '').' — '.Str::limit((string) ($day['content'] ?? ''), 160, '...')))
            ->filter(fn ($line) => $line !== '—')
            ->implode("\n");

        // Kalkış ayları: "yazlık / eylülde" gibi sorgular ay bilgisiyle eşleşsin
        $monthNames = [1 => 'Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'];
        $months = $tour->dates
            ->map(fn ($d) => $d->departure_date?->format('n'))
            ->filter()
            ->unique()
            ->sort()
            ->map(fn ($m) => $monthNames[(int) $m])
            ->implode(', ');

        $boardingCities = collect([$tour->departure_city])
            ->merge(is_array($tour->stop_cities) ? $tour->stop_cities : [])
            ->filter()
            ->unique()
            ->implode(', ');

        return implode("\n", array_filter([
            "Tur başlığı: {$tour->title}",
            'Kategori: '.($tour->category?->name ?? 'belirtilmemiş'),
            "Destinasyon: {$tour->destination}",
            $boardingCities !== '' ? "Kalkış şehirleri: {$boardingCities}" : null,
            "Süre (gün): {$tour->duration_days}",
            $months !== '' ? "Kalkış ayları: {$months}" : null,
            "Fiyat: {$tour->price} {$tour->currency}",
            "Fiyat bandı: {$priceBand}",
            'Yurt dışı mı: '.($tour->is_international ? 'evet' : 'hayır'),
            'Vize gerekir mi: '.($tour->requires_visa ? 'evet' : 'hayır'),
            'Dahil olanlar: '.Str::limit((string) $tour->included, 300, '...'),
            'Hariç olanlar: '.Str::limit((string) $tour->excluded, 200, '...'),
            $tour->hotel_info ? 'Konaklama: '.Str::limit((string) $tour->hotel_info, 200, '...') : null,
            $tour->extras ? 'Ekstra aktiviteler: '.Str::limit((string) $tour->extras, 250, '...') : null,
            'Açıklama: '.Str::limit((string) $tour->description, 500, '...'),
            $itinerary !== '' ? "Gün gün program:\n".Str::limit($itinerary, 1500, '...') : null,
        ]));
    }
}
