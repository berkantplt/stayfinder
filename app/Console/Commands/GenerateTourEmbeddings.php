<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class GenerateTourEmbeddings extends Command
{
    protected $signature = 'app:generate-tour-embeddings {--force : Hali hazırda vektörü olan turları da tekrar işle}';
    protected $description = 'Tüm turları OpenAI kullanarak vektörleştirir ve arama için hazır hale getirir.';

    public function handle()
    {
        $query = \App\Models\Tour::query();
        
        if (!$this->option('force')) {
            $query->whereNull('embedding');
        }

        $tours = $query->with('category')->get();

        if ($tours->isEmpty()) {
            $this->info('Vektörleştirilecek yeni tur bulunamadı.');
            return;
        }

        $this->info($tours->count() . ' tur işleniyor...');
        $bar = $this->output->createProgressBar($tours->count());

        foreach ($tours as $tour) {
            try {
                // OpenAI vektör API'sine gönderilecek metni oluştur
                $priceBand = match (true) {
                    $tour->price < 7500 => 'ekonomik',
                    $tour->price < 15000 => 'orta',
                    default => 'premium',
                };

                $inputText = implode("\n", array_filter([
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
                
                $response = \OpenAI\Laravel\Facades\OpenAI::embeddings()->create([
                    'model' => 'text-embedding-3-small',
                    'input' => $inputText,
                ]);

                $embedding = $response->embeddings[0]->embedding;

                $tour->update(['embedding' => $embedding]);
                $bar->advance();
            } catch (\Exception $e) {
                $this->error("\nTur #{$tour->id} işlenirken hata oluştu: " . $e->getMessage());
            }
        }

        $bar->finish();
        $this->info("\nİşlem başarıyla tamamlandı.");
    }
}
