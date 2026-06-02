<?php

namespace App\Console\Commands;

use App\Jobs\GenerateDestinationProfileJob;
use App\Models\DestinationProfile;
use App\Models\Tour;
use Illuminate\Console\Command;

class EnrichDestinations extends Command
{
    protected $signature = 'app:enrich-destinations
        {--all : Stale tüm DestinationProfile kayıtlarını da işle (sadece aktif tur destinasyonları değil)}
        {--limit=0 : En fazla bu kadar destinasyon için job dispatch et (0 = sınırsız)}
        {--dry : Sadece neyin dispatch edileceğini listele, gerçekten dispatch etme}';

    protected $description = 'Aktif tur destinasyonları için zengin profil oluşturma job\'larını kuyruğa atar.';

    public function handle(): int
    {
        $limit = max(0, (int) $this->option('limit'));
        $dryRun = (bool) $this->option('dry');
        $includeAll = (bool) $this->option('all');

        $this->info('Destinasyon profili audit başlatılıyor...');

        $candidates = $this->collectCandidates($includeAll);

        if ($candidates->isEmpty()) {
            $this->info('Zenginleştirilecek destinasyon bulunamadı — hepsi güncel.');
            return self::SUCCESS;
        }

        $this->info('Aday destinasyon sayısı: ' . $candidates->count());

        if ($limit > 0) {
            $candidates = $candidates->take($limit);
            $this->info('Limit uygulandı: ' . $candidates->count());
        }

        $bar = $this->output->createProgressBar($candidates->count());
        $bar->start();

        $dispatched = 0;
        $skipped = 0;

        foreach ($candidates as $city) {
            $normalized = DestinationProfile::normalize($city);
            $profile = DestinationProfile::where('normalized_city', $normalized)->first();

            if ($profile && !$profile->needsEnrichment()) {
                $skipped++;
                $bar->advance();
                continue;
            }

            if ($dryRun) {
                $this->newLine();
                $this->line(' DRY → ' . $city . ' (normalized=' . $normalized . ')');
                $bar->advance();
                continue;
            }

            // Placeholder kayıt yoksa oluştur — service ve observer pattern'ine uyumlu
            if (!$profile) {
                DestinationProfile::create([
                    'city' => $city,
                    'normalized_city' => $normalized,
                    'crowd_score' => 0.50,
                    'liveliness_score' => 0.50,
                    'source' => DestinationProfile::SOURCE_DEFAULT,
                    'enrichment_version' => 1,
                ]);
            }

            GenerateDestinationProfileJob::dispatch($city);
            $dispatched++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        if ($dryRun) {
            $this->info('Dry-run tamamlandı — hiçbir job dispatch edilmedi.');
        } else {
            $this->info('Dispatch edilen job: ' . $dispatched . ' (atlanan: ' . $skipped . ')');
            $this->warn('Job\'ların işlenmesi için queue worker gerekli: php artisan queue:work');
        }

        return self::SUCCESS;
    }

    /**
     * Tur destinasyonlarındaki parçaları + opsiyonel mevcut stale profile şehirlerini topla.
     */
    private function collectCandidates(bool $includeAllStale): \Illuminate\Support\Collection
    {
        $candidates = collect();

        $tourDestinations = Tour::query()
            ->where('is_active', true)
            ->whereNotNull('destination')
            ->pluck('destination')
            ->unique();

        foreach ($tourDestinations as $raw) {
            $parts = preg_split('/\s*[,;\/&]\s*|\s+ve\s+/u', (string) $raw) ?: [$raw];
            foreach ($parts as $part) {
                $part = trim($part);
                if ($part === '' || mb_strlen($part, 'UTF-8') < 3) {
                    continue;
                }
                $candidates->push($part);
            }
        }

        if ($includeAllStale) {
            $staleProfileCities = DestinationProfile::stale()->pluck('city');
            $candidates = $candidates->concat($staleProfileCities);
        }

        return $candidates
            ->map(fn($c) => trim((string) $c))
            ->filter()
            ->unique(fn($c) => DestinationProfile::normalize($c))
            ->values();
    }
}
