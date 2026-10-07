<?php

namespace App\Services\BulkImport;

use App\Models\Agency;
use App\Models\Category;
use App\Models\Tour;
use App\Observers\TourObserver;
use App\Services\TourImport\TourUrlImporter;
use App\Services\Tours\TourPayloadBuilder;
use Throwable;

/**
 * Tek bir liste satırını (acenta + kategori + kaynak URL) tura çevirir:
 * TourUrlImporter → ImportedTourPayloadMapper → Tour + TourDate + galeri.
 * Panelden "URL'den getir → Kaydet" ile aynı yol; yalnız "Yeni Tur Eklendi!"
 * duyurusu susturulur (yüzlerce duyuru üretmesin). Embedding/karakter/rubrik
 * kuyruk işleri normal tetiklenir.
 *
 * Aynı kaynak URL (temizlenmiş) o acentada zaten varsa (arşiv dahil) atlanır —
 * komut yarıda kesilirse yeniden koşulduğunda kaldığı yerden devam eder.
 */
class BulkTourImporter
{
    public const STATUS_CREATED = 'eklendi';

    public const STATUS_SKIPPED = 'atlandı';

    public const STATUS_FAILED = 'başarısız';

    public const STATUS_DRY = 'kuru';

    public function __construct(
        private readonly TourUrlImporter $importer,
        private readonly ImportedTourPayloadMapper $mapper,
        private readonly TourPayloadBuilder $payload,
    ) {}

    /**
     * @return array{status: string, reason: ?string, tour_id: ?int, title: ?string, dates: int, price: ?float, currency: ?string, visa: ?string, images: int, departure_city: ?string, notes: string[], warnings: string[]}
     */
    public function importOne(string $url, Agency $agency, Category $category, string $batch, ?string $visaOverride, bool $dry): array
    {
        $base = [
            'status' => self::STATUS_FAILED, 'reason' => null, 'tour_id' => null, 'title' => null,
            'dates' => 0, 'price' => null, 'currency' => null, 'visa' => null, 'images' => 0,
            'departure_city' => null, 'notes' => [], 'warnings' => [],
        ];

        $cleanUrl = $this->payload->cleanTourUrl($url);
        $existing = Tour::withTrashed()
            ->where('agency_id', $agency->id)
            ->where(fn ($q) => $q->where('tour_url', $cleanUrl)->orWhere('tour_url', $url))
            ->first();
        if ($existing) {
            return array_merge($base, ['status' => self::STATUS_SKIPPED, 'reason' => "zaten var (#{$existing->id})", 'tour_id' => $existing->id, 'title' => $existing->title]);
        }

        try {
            $import = $this->importer->import($url);
        } catch (Throwable $e) {
            return array_merge($base, ['reason' => 'içe aktarma: '.$e->getMessage()]);
        }

        try {
            $mapped = $this->mapper->map($import, $url, $agency, $category, $visaOverride);
        } catch (BulkImportException $e) {
            return array_merge($base, [
                'reason' => $e->getMessage(),
                'title' => isset($import['title']) ? (string) $import['title'] : null,
                'warnings' => (array) ($import['warnings'] ?? []),
            ]);
        }

        $attributes = $mapped['attributes'];
        $summary = [
            'title' => $attributes['title'],
            'dates' => count($mapped['dates']),
            'price' => (float) $attributes['price'],
            'currency' => $attributes['currency'],
            'visa' => $this->visaLabel($attributes['requires_visa'], $attributes['visa_on_arrival']),
            'images' => count($mapped['gallery_urls']),
            'departure_city' => $attributes['departure_city'],
            'notes' => $mapped['notes'],
            'warnings' => (array) ($import['warnings'] ?? []),
        ];

        if ($dry) {
            return array_merge($base, $summary, ['status' => self::STATUS_DRY]);
        }

        try {
            $gallery = $this->payload->processGallery($mapped['gallery_urls']);
            $attributes['images'] = $gallery ?: null;
            $attributes['image'] = $gallery[0] ?? null;
            $attributes['import_batch'] = $batch;

            $tour = TourObserver::withoutNewTourAnnouncements(function () use ($attributes, $mapped) {
                $tour = Tour::create($attributes);
                $this->payload->syncTourDates($tour, $mapped['dates']);

                return $tour;
            });
        } catch (Throwable $e) {
            return array_merge($base, $summary, ['reason' => 'kayıt: '.$e->getMessage()]);
        }

        return array_merge($base, $summary, [
            'status' => self::STATUS_CREATED,
            'tour_id' => $tour->id,
            'images' => count($gallery),
        ]);
    }

    private function visaLabel(?bool $requires, ?bool $onArrival): ?string
    {
        if ($requires === null) {
            return null;
        }
        if (! $requires) {
            return 'vizesiz';
        }

        return $onArrival ? 'kapıda' : 'vizeli';
    }
}
