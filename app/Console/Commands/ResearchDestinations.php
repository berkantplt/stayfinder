<?php

namespace App\Console\Commands;

use App\Jobs\ResearchDestinationJob;
use App\Models\Destination;
use App\Models\Tour;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ResearchDestinations extends Command
{
    protected $signature = 'destinations:research
                            {--force : Mevcut araştırmaları yoksay ve yeniden üret}
                            {--queue : Job\'ları kuyruğa al (default: senkron çalıştır)}
                            {--only= : Sadece belirli bir destinasyon adını araştır}
                            {--from-tours : tours.destination string\'lerinden eksik destinations kayıtlarını oluştur}';

    protected $description = 'Tüm destinasyonlar için AI web araştırması üretir veya günceller';

    public function handle(): int
    {
        if ($this->option('from-tours')) {
            $this->createDestinationsFromTours();
        }

        $only = $this->option('only');
        $force = (bool) $this->option('force');
        $useQueue = (bool) $this->option('queue');

        $query = Destination::query();
        if ($only) {
            $query->where('name', $only)->orWhere('slug', Str::slug($only));
        }

        $destinations = $query->orderBy('name')->get();
        $this->info("Hedef destinasyon sayısı: {$destinations->count()}");

        $dispatched = 0;
        $skipped = 0;
        foreach ($destinations as $destination) {
            if (!$force && $destination->aiResearchIsFresh()) {
                $skipped++;
                continue;
            }

            if ($useQueue) {
                ResearchDestinationJob::dispatch($destination->id, $force)->onQueue('default');
            } else {
                ResearchDestinationJob::dispatchSync($destination->id, $force);
            }
            $dispatched++;
            $this->line(" - {$destination->name} → " . ($useQueue ? 'queued' : 'researched'));
        }

        $this->info("Tamam. İşlenen: {$dispatched}, atlanan (taze): {$skipped}");
        return self::SUCCESS;
    }

    private function createDestinationsFromTours(): void
    {
        $tourDestinations = Tour::query()
            ->whereNotNull('destination')
            ->where('destination', '!=', '')
            ->distinct()
            ->pluck('destination')
            ->map(fn($n) => trim((string) $n))
            ->filter()
            ->unique()
            ->values();

        $created = 0;
        foreach ($tourDestinations as $name) {
            $slug = Str::slug($name);
            $exists = Destination::where('slug', $slug)->orWhere('name', $name)->exists();
            if ($exists) continue;

            Destination::create([
                'name' => $name,
                'slug' => $slug,
                'is_active' => true,
            ]);
            $created++;
        }

        $this->info("Tur'lardan oluşturulan yeni destinasyon: {$created}");
    }
}
