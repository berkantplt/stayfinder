<?php

namespace App\Console\Commands;

use App\Jobs\GenerateTourCharacterJob;
use App\Models\Tour;
use Illuminate\Console\Command;

/**
 * Mevcut turlar için tek seferlik tur karakteri doldurma. Yeni/güncellenen
 * turları TourObserver otomatik kuyruğa alır; bu komut geriye dönük tarama içindir.
 */
class EnrichTourCharacters extends Command
{
    protected $signature = 'app:enrich-tour-characters
        {--all : Pasif turlar dahil tüm turları tara}
        {--limit=0 : En fazla bu kadar tur kuyruğa alınır (0 = sınırsız)}
        {--dry : Sadece sayıyı göster, kuyruğa alma}';

    protected $description = 'Karakteri eksik/bayat turlar için GenerateTourCharacterJob kuyruğa alır';

    public function handle(): int
    {
        $query = Tour::query()
            ->where('character_version', '<', Tour::CURRENT_CHARACTER_VERSION)
            ->orderBy('id');

        if (! $this->option('all')) {
            $query->where('is_active', true);
        }

        $limit = (int) $this->option('limit');
        if ($limit > 0) {
            $query->limit($limit);
        }

        $ids = $query->pluck('id');

        if ($ids->isEmpty()) {
            $this->info('Karakteri eksik tur yok — her şey güncel.');

            return self::SUCCESS;
        }

        if ($this->option('dry')) {
            $this->info($ids->count().' tur kuyruğa alınacaktı (--dry).');

            return self::SUCCESS;
        }

        $dispatched = 0;
        foreach ($ids as $id) {
            // Observer'ın az önce kuyruğa aldığı tur ikinci kez üretilmesin
            if (! \Illuminate\Support\Facades\Cache::add(GenerateTourCharacterJob::DISPATCH_LOCK_PREFIX.$id, 1, 600)) {
                continue;
            }
            GenerateTourCharacterJob::dispatch($id);
            $dispatched++;
        }

        $this->info($dispatched.' tur karakter üretimi için kuyruğa alındı'
            .($dispatched < $ids->count() ? ' ('.($ids->count() - $dispatched).' tanesi zaten kuyrukta — atlandı)' : '').'.');
        $this->warn('Not: job\'ların işlenmesi için queue worker çalışıyor olmalı (php artisan queue:work).');

        return self::SUCCESS;
    }
}
