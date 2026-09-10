<?php

namespace App\Support;

use App\Models\Category;
use Illuminate\Database\Eloquent\Builder;

/**
 * /turlar filtre dili — tek yerde. TourController::index, boş sonuçta akıllı
 * gevşetme (bir filtreyi kaldırınca kaç tur çıkar) ve kayıtlı arama bildirimi
 * aynı uygulamayı kullanır; eskiden hepsi controller'da satır satır yazılıydı.
 *
 * Gruplar: bir gruptaki parametreler birlikte kaldırılır (tarih aralığı iki
 * alan, fiyat iki alan). Etiketler gevşetme çiplerinde okunur.
 */
final class TourListFilter
{
    public const GROUPS = [
        'q' => ['q'],
        'destination' => ['destination'],
        'departure_city' => ['departure_city'],
        'agency_id' => ['agency_id'],
        'category' => ['category'],
        'yurt' => ['yurt'],
        'visa' => ['visa'],
        'price' => ['min_price', 'max_price'],
        'days' => ['min_days', 'max_days'],
        'dates' => ['date_start', 'date_end'],
    ];

    public const LABELS = [
        'q' => 'Arama sözcüğünü kaldır',
        'destination' => 'Destinasyonu kaldır',
        'departure_city' => 'Kalkış şehrini kaldır',
        'agency_id' => 'Acenta seçimini kaldır',
        'category' => 'Kategoriyi kaldır',
        'yurt' => 'Yurt içi/dışı seçimini kaldır',
        'visa' => 'Vize seçimini kaldır',
        'price' => 'Fiyat aralığını kaldır',
        'days' => 'Süre seçimini kaldır',
        'dates' => 'Tarihi kaldır',
    ];

    /**
     * @param  array<string, mixed>  $p  istek parametreleri
     * @param  string[]  $except  uygulanmayacak gruplar (gevşetme sayımı için)
     */
    public static function apply(Builder $query, array $p, array $except = []): Builder
    {
        $dolu = fn (string $k) => isset($p[$k]) && ! is_array($p[$k]) && trim((string) $p[$k]) !== '';
        $atla = fn (string $grup) => in_array($grup, $except, true);

        if (! $atla('q') && $dolu('q')) {
            $search = (string) $p['q'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('destination', 'like', "%{$search}%");
            });
        }

        // Kelime sınırlı eşleşme: "Fethiye" seçimi "Ölüdeniz, Fethiye" turunu da
        // bulur, ama "Kas" seçimi "Kastamonu"yu getirmez (bkz. DestinationFilter).
        if (! $atla('destination') && $dolu('destination')) {
            DestinationFilter::apply($query, (string) $p['destination']);
        }

        // Kalkış şehrim: kalkış şehri VEYA duraklarından biri eşleşen turlar.
        if (! $atla('departure_city') && $dolu('departure_city')) {
            $query->departsFrom((string) $p['departure_city']);
        }

        // Filtre değerleri TL — kur-normalize price_try ile karşılaştırılır
        if (! $atla('price')) {
            if ($dolu('min_price')) {
                $query->where('price_try', '>=', (float) $p['min_price']);
            }
            if ($dolu('max_price')) {
                $query->where('price_try', '<=', (float) $p['max_price']);
            }
        }

        if (! $atla('agency_id') && $dolu('agency_id')) {
            $query->where('agency_id', (int) $p['agency_id']);
        }

        if (! $atla('category') && $dolu('category')) {
            $cat = Category::where('slug', (string) $p['category'])->first();
            if ($cat) {
                $catIds = collect([$cat->id])->merge($cat->children()->pluck('id'));
                $query->whereIn('category_id', $catIds);
            }
        }

        // Yurt içi / yurt dışı: mobil kategori kısayolları bu parametreyi kullanır
        if (! $atla('yurt') && in_array($p['yurt'] ?? null, ['ic', 'dis'], true)) {
            $query->where('is_international', $p['yurt'] === 'dis');
        }

        // Vize: kaynak tours.requires_visa; işaretlenmemiş tur hiçbir yöne girmez.
        if (! $atla('visa') && in_array($p['visa'] ?? null, ['vizesiz', 'vizeli'], true)) {
            $query->where('requires_visa', $p['visa'] === 'vizeli');
        }

        if (! $atla('days')) {
            if ($dolu('min_days')) {
                $query->where('duration_days', '>=', (int) $p['min_days']);
            }
            if ($dolu('max_days')) {
                $query->where('duration_days', '<=', (int) $p['max_days']);
            }
        }

        if (! $atla('dates')) {
            if ($dolu('date_start')) {
                $start = (string) $p['date_start'];
                $query->where(function ($q) use ($start) {
                    $q->where('departure_date', '>=', $start)
                        ->orWhereHas('dates', fn ($dq) => $dq->where('departure_date', '>=', $start));
                });
            }
            if ($dolu('date_end')) {
                $end = (string) $p['date_end'];
                $query->where(function ($q) use ($end) {
                    // If a tour overlaps or starts before this date
                    $q->where('departure_date', '<=', $end)
                        ->orWhereHas('dates', fn ($dq) => $dq->where('departure_date', '<=', $end));
                });
            }
        }

        return $query;
    }

    /** Değeri olan gruplar (gevşetme çipleri bunlardan üretilir). @return string[] */
    public static function activeGroups(array $p): array
    {
        $aktif = [];
        foreach (self::GROUPS as $grup => $alanlar) {
            foreach ($alanlar as $alan) {
                $v = $p[$alan] ?? null;
                if ($v === null || is_array($v) || trim((string) $v) === '') {
                    continue;
                }
                if ($grup === 'yurt' && ! in_array($v, ['ic', 'dis'], true)) {
                    continue;
                }
                if ($grup === 'visa' && ! in_array($v, ['vizesiz', 'vizeli'], true)) {
                    continue;
                }
                $aktif[] = $grup;
                break;
            }
        }

        return $aktif;
    }
}
