<?php

namespace App\Console\Commands;

use App\Models\Tour;
use App\Services\TourImport\TourUrlImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Kaynak URL'si olan turları yeniden içe aktarır.
 *
 * ⚠️ ÜZERİNE YAZAR. Acentanın elle düzelttiği başlık/açıklama/fiyat/program
 * kaynak sayfadaki değerle değişir. Bu bilinçli bir karardı (ulaşım bilgisinin
 * tüm turlara gelmesi istendi); yine de --dry ile önce ne değişeceğine BAKIN.
 *
 * Yeniden çalıştırılabilir: varsayılan olarak ulaşım bilgisi ZATEN olan turları
 * atlar, yani yarıda kesilirse kaldığı yerden devam eder. --force hepsini işler.
 *
 * Boş dönen alanlar mevcut değeri EZMEZ — içe aktarma başarısız olduğunda iyi
 * veriyi silmemek için. Yalnız gerçekten gelen alanlar yazılır.
 */
class ReimportTours extends Command
{
    protected $signature = 'app:reimport-tours
        {--dry : Hiçbir şey yazma, ne değişeceğini raporla}
        {--limit=0 : En fazla bu kadar tur işle (0 = sınırsız)}
        {--id= : Yalnız bu tur id\'sini işle}
        {--force : Ulaşım bilgisi zaten olan turları da yeniden çek}
        {--sleep=2 : İstekler arası bekleme (saniye) — kaynak siteyi yormamak için}';

    protected $description = 'Kaynak URL\'si olan turları yeniden içe aktarır (ÜZERİNE YAZAR).';

    /** İçe aktarmadan gelen ve turda güncellenecek alanlar. */
    private const UPDATABLE = [
        'title', 'destination', 'description', 'duration_days', 'duration_nights',
        'transport_type', 'price', 'currency', 'included', 'excluded',
        'departure_points', 'departure_city', 'stop_cities', 'itinerary',
        'hotel_info', 'extras', 'cancellation_policy', 'guide_info', 'frequency',
        'pricing_blocks',
    ];

    public function handle(TourUrlImporter $importer): int
    {
        $dry = (bool) $this->option('dry');
        $limit = max(0, (int) $this->option('limit'));
        $bekle = max(0, (int) $this->option('sleep'));

        $query = Tour::query()
            ->whereNotNull('tour_url')
            ->where('tour_url', '!=', '')
            ->orderBy('id');

        if ($id = $this->option('id')) {
            $query->where('id', (int) $id);
        } elseif (! $this->option('force')) {
            // Yeniden çalıştırılabilirlik: hedef ulaşım bilgisiydi, olanı atla.
            $query->whereNull('transport_type');
        }

        $turlar = $limit > 0 ? $query->limit($limit)->get() : $query->get();

        if ($turlar->isEmpty()) {
            $this->info('İşlenecek tur yok. (URL\'si olan ve ulaşım bilgisi eksik tur kalmamış olabilir.)');

            return self::SUCCESS;
        }

        $urlsuz = Tour::whereNull('tour_url')->orWhere('tour_url', '')->count();

        $this->info("İşlenecek tur: {$turlar->count()}");
        if ($urlsuz > 0) {
            $this->warn("Kaynak URL'si olmayan {$urlsuz} tur bu komutla doldurulamaz — app:notify-missing-transport kullanın.");
        }
        if ($dry) {
            $this->warn('KURU ÇALIŞMA: hiçbir şey yazılmayacak.');
        } else {
            $this->warn('⚠️ Mevcut veriler kaynak sayfadaki değerlerle DEĞİŞTİRİLECEK.');
        }
        $this->newLine();

        $basarili = 0;
        $hatali = 0;
        $ulasimGelen = 0;

        foreach ($turlar as $index => $tour) {
            $etiket = "#{$tour->id} ".mb_substr($tour->title, 0, 40);

            try {
                $data = $importer->import($tour->tour_url);
            } catch (Throwable $e) {
                $hatali++;
                $this->line("  ✗ {$etiket} — ".mb_substr($e->getMessage(), 0, 70));
                Log::warning('[ReimportTours] başarısız', [
                    'tour_id' => $tour->id, 'url' => $tour->tour_url, 'message' => $e->getMessage(),
                ]);

                continue;
            }

            $degisecek = [];
            foreach (self::UPDATABLE as $alan) {
                $yeni = $data[$alan] ?? null;

                // Boş gelen alan mevcut veriyi EZMEZ.
                if ($yeni === null || $yeni === '' || $yeni === []) {
                    continue;
                }
                if ($tour->{$alan} == $yeni) {
                    continue;
                }
                $degisecek[$alan] = $yeni;
            }

            if (isset($degisecek['transport_type'])) {
                $ulasimGelen++;
            }

            if ($degisecek === []) {
                $this->line("  · {$etiket} — değişiklik yok");
            } else {
                $ozet = implode(', ', array_keys($degisecek));
                $this->line("  ✓ {$etiket} — ".mb_substr($ozet, 0, 80));

                if (! $dry) {
                    // Observer'lar tetiklensin: embedding/karakter/rubrik tazelensin.
                    $tour->fill($degisecek)->save();
                }
                $basarili++;
            }

            if ($bekle > 0 && $index < $turlar->count() - 1) {
                sleep($bekle);
            }
        }

        $this->newLine();
        $this->info("Güncellenen: {$basarili}   Hatalı: {$hatali}   Ulaşım bilgisi gelen: {$ulasimGelen}");

        if ($dry) {
            $this->comment('Kuru çalışmaydı — gerçekten yazmak için --dry olmadan çalıştırın.');
        }

        return self::SUCCESS;
    }
}
