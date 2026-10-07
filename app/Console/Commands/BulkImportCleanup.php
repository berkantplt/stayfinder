<?php

namespace App\Console\Commands;

use App\Models\Agency;
use App\Models\Tour;
use Illuminate\Console\Command;

/**
 * Bir toplu içe aktarım partisini geri alır: partinin turları arşive (soft delete)
 * taşınır; --tours-only verilmediyse partiyle açılan acentalar da arşivlenir
 * (Agency::archiveWithTours — admin "Arşivle" ile aynı yol, geri alınabilir).
 * Kalıcı silme BİLEREK yok: turlar 30 gün sonra model:prune ile kendiliğinden
 * gider, acentalar admin panelinden geri alınabilir.
 */
class BulkImportCleanup extends Command
{
    protected $signature = 'app:bulk-import-cleanup
        {--batch= : Arşivlenecek parti etiketi (zorunlu)}
        {--tours-only : Yalnız turları arşivle, acenta hesapları kalsın}
        {--force : Onay sorma}';

    protected $description = 'Toplu içe aktarım partisinin turlarını (ve acentalarını) arşive taşır';

    public function handle(): int
    {
        $batch = BulkImportTours::validateBatch($this->option('batch'), $this);
        if ($batch === null) {
            return self::FAILURE;
        }

        $toursOnly = (bool) $this->option('tours-only');
        $tours = Tour::query()->where('import_batch', $batch)->get();
        $agencies = $toursOnly ? collect() : Agency::query()->where('import_batch', $batch)->get();

        if ($tours->isEmpty() && $agencies->isEmpty()) {
            $this->info("'{$batch}' partisinde arşivlenecek kayıt yok.");

            return self::SUCCESS;
        }

        $this->warn(sprintf("'%s' partisi: %d tur%s arşivlenecek.", $batch, $tours->count(),
            $toursOnly ? '' : ' ve '.$agencies->count().' acenta (turlarıyla birlikte)'));
        if (! $this->option('force') && ! $this->confirm('Devam edilsin mi?')) {
            $this->comment('Vazgeçildi.');

            return self::SUCCESS;
        }

        $archivedTours = 0;
        foreach ($tours as $tour) {
            $tour->delete();
            $archivedTours++;
        }

        $archivedAgencies = 0;
        foreach ($agencies as $agency) {
            $agency->archiveWithTours();
            $archivedAgencies++;
        }

        $this->info("{$archivedTours} tur arşivlendi".($toursOnly ? '.' : ", {$archivedAgencies} acenta arşivlendi."));

        return self::SUCCESS;
    }
}
