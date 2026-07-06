<?php

namespace App\Console\Commands;

use App\Models\Tour;
use App\Support\DestinationClassifier;
use Illuminate\Console\Command;

/**
 * Mevcut turların is_international bayrağını destinasyondan türetip düzeltir
 * (tek seferlik backfill; kayıt anında otomatik türetme zaten devrede).
 * Bayrak değişen turların embedding'i null'lanır → saatlik güvenlik ağı yeni
 * temsille yeniden üretir ("Yurt dışı mı: evet" embedding metnine giriyor).
 */
class ClassifyTourDestinations extends Command
{
    protected $signature = 'app:classify-tour-destinations {--dry : Sadece raporla, yazma}';

    protected $description = 'Turların yurt içi/dışı bayrağını destinasyondan türetir (backfill).';

    public function handle(): void
    {
        $changed = 0;
        $unknown = 0;

        Tour::query()->orderBy('id')->chunkById(200, function ($tours) use (&$changed, &$unknown) {
            foreach ($tours as $tour) {
                $classified = DestinationClassifier::isInternational($tour->destination);

                if ($classified === null) {
                    $unknown++;
                    $this->line("  ? Tur #{$tour->id} '{$tour->destination}' sınıflandırılamadı — mevcut değer korundu.");

                    continue;
                }

                if ($classified === (bool) $tour->is_international) {
                    continue;
                }

                $changed++;
                $this->info("  ✓ Tur #{$tour->id} '{$tour->destination}' → ".($classified ? 'YURT DIŞI' : 'yurt içi'));

                if (! $this->option('dry')) {
                    // Observer bildirimleri tetiklenmesin; embedding null'lanır ki
                    // saatlik güvenlik ağı doğru bayrağı içeren temsille yeniden üretsin
                    Tour::withoutEvents(function () use ($tour, $classified) {
                        $tour->update(['is_international' => $classified, 'embedding' => null]);
                    });
                }
            }
        });

        $this->info("Bitti: {$changed} tur güncellendi".($this->option('dry') ? ' (dry-run, yazılmadı)' : '').", {$unknown} tur sınıflandırılamadı.");
        if ($changed > 0 && ! $this->option('dry')) {
            $this->comment('Değişen turların embedding\'leri saatlik güvenlik ağıyla yenilenecek; hemen istersen: php artisan app:generate-tour-embeddings');
        }
    }
}
