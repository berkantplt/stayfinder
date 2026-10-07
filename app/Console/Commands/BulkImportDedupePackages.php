<?php

namespace App\Console\Commands;

use App\Models\Tour;
use App\Support\PricingBlocks;
use Illuminate\Console\Command;

/**
 * Bir partinin turlarında kopya fiyat paketlerini temizler (aynı blokta aynı otel
 * adı + aynı iki-kişilik fiyat). İçe aktarıcının eski sürümü mobil+masaüstü çift
 * tabloyu iki paket olarak kaydediyordu; düzeltme yayına girmeden içe aktarılan
 * turlar bu komutla onarılır. Ağa çıkmaz, LLM çağrısı yapmaz. Önce --dry.
 */
class BulkImportDedupePackages extends Command
{
    protected $signature = 'app:bulk-import-dedupe-packages
        {--batch= : Parti etiketi (zorunlu)}
        {--dry : Yalnız raporla, yazma}';

    protected $description = 'Partideki turların fiyat matrisinde kopya paketleri (aynı otel + aynı fiyat) siler';

    public function handle(): int
    {
        $batch = BulkImportTours::validateBatch($this->option('batch'), $this);
        if ($batch === null) {
            return self::FAILURE;
        }
        $dry = (bool) $this->option('dry');

        $tours = Tour::query()->where('import_batch', $batch)->whereNotNull('pricing_blocks')->orderBy('id')->get();
        $touched = 0;
        $droppedTotal = 0;

        foreach ($tours as $tour) {
            ['blocks' => $blocks, 'dropped' => $dropped] = PricingBlocks::dropDuplicatePackages($tour->pricing_blocks);
            if ($dropped === 0) {
                continue;
            }
            $touched++;
            $droppedTotal += $dropped;
            $this->line(sprintf('#%d %s → %d kopya paket%s', $tour->id, $tour->title, $dropped, $dry ? ' (yazılmadı)' : ''));
            if (! $dry) {
                $tour->update(['pricing_blocks' => $blocks]);
            }
        }

        $this->info(sprintf('%d tur tarandı, %d turda %d kopya paket%s.', $tours->count(), $touched, $droppedTotal, $dry ? ' bulundu (kuru çalışma)' : ' silindi'));

        return self::SUCCESS;
    }
}
