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
                // TEK KAYNAK: job ile birebir aynı temsil (iki kopya ayrışmasın)
                $inputText = \App\Jobs\GenerateTourEmbeddingJob::textFor($tour);

                $response = \OpenAI\Laravel\Facades\OpenAI::embeddings()->create([
                    'model' => config('ai.embedding_model', 'text-embedding-3-small'),
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
