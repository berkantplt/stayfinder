<?php

namespace App\Console\Commands;

use App\Models\Tour;
use App\Support\DepartureCityExtractor;
use Illuminate\Console\Command;

/**
 * Turların kalkış şehrini mevcut alanlardan doldurur (Faz 0.2).
 *
 * Bu alan "{şehir} kalkışlı {destinasyon} turları" sayfa ailesinin tek
 * girdisi — rakip araştırmasında büyük pazaryerlerinin boş bıraktığı,
 * orta ölçekli acentelerin 1. sırada çıktığı alan.
 *
 * Komut ASLA tahmin etmez: kaynak metinde açık bir kalkış ifadesi yoksa
 * turu atlar ve raporda "kaynak yok" olarak sayar. Uydurulan kalkış şehri
 * yanlış landing page açar — boş alandan kötüdür.
 */
class BackfillDepartureCity extends Command
{
    protected $signature = 'seo:backfill-departure-city
        {--dry-run : Yalnız ne bulunacağını göster, kaydetme}
        {--force : Dolu olan alanları da güncelle}
        {--limit= : En fazla kaç tur işlensin}';

    protected $description = 'Tur başlığı, biniş noktaları ve programından kalkış şehrini çıkarıp doldurur';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        $query = Tour::query()->orderBy('id');

        if (! $force) {
            $query->where(fn ($q) => $q->whereNull('departure_city')->orWhere('departure_city', ''));
        }

        if ($limit = $this->option('limit')) {
            $query->limit((int) $limit);
        }

        $bulunan = 0;
        $bulunamayan = 0;
        $kaynakSayimi = [];
        $ornekler = [];

        foreach ($query->cursor() as $tour) {
            $sonuc = DepartureCityExtractor::extract($tour);

            if ($sonuc === null) {
                $bulunamayan++;

                continue;
            }

            $bulunan++;
            $kaynakSayimi[$sonuc['source']] = ($kaynakSayimi[$sonuc['source']] ?? 0) + 1;

            if (count($ornekler) < 15) {
                $ornekler[] = sprintf(
                    '  #%-4d %-46s → %-14s (%s)',
                    $tour->id,
                    mb_strimwidth((string) $tour->title, 0, 44, '…'),
                    $sonuc['city'],
                    $sonuc['source']
                );
            }

            if (! $dryRun) {
                // Yalnız tek kolon; model event'leri (fiyat geçmişi, embedding
                // yenileme) boşuna tetiklenmesin.
                Tour::whereKey($tour->getKey())->update(['departure_city' => $sonuc['city']]);
            }
        }

        if ($ornekler !== []) {
            $this->newLine();
            $this->line('<comment>Örnekler:</comment>');
            foreach ($ornekler as $satir) {
                $this->line($satir);
            }
        }

        $this->newLine();
        $this->info(sprintf(
            '%sBulundu: %d  |  Kaynak bulunamadı: %d',
            $dryRun ? '[KURU ÇALIŞMA] ' : '',
            $bulunan,
            $bulunamayan
        ));

        if ($kaynakSayimi !== []) {
            $this->line('Kaynak dağılımı: '.collect($kaynakSayimi)
                ->map(fn ($n, $k) => "{$k}={$n}")
                ->implode(', '));
        }

        if ($bulunamayan > 0) {
            $this->newLine();
            $this->warn(sprintf(
                '%d turda kalkış ifadesi yok. Bunlar elle doldurulmalı — komut bilerek tahmin etmiyor;',
                $bulunamayan
            ));
            $this->warn('uydurulan kalkış şehri yanlış landing page açar ve düzeltmesi zordur.');
        }

        if (! $dryRun && $bulunan > 0) {
            cache()->forget('sitemap_index_v2');
            \App\Services\AiSearch\DestinationKnowledgeService::flushInventory();
            $this->line('Sitemap ve envanter cache\'i temizlendi.');
        }

        return self::SUCCESS;
    }
}
