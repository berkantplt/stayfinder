<?php

namespace App\Support;

use App\Models\Tour;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Landing sayfası (/balkan-turlari) filtre dili — TEK yerde.
 *
 * /turlar'daki TourListFilter'dan ayrı: orada parametreler serbest metin ve
 * aralık; burada her filtre sayfanın KENDİ envanterinden türeyen bir seçenek
 * kümesi (facet). Kullanıcı var olmayan bir ay ya da süre seçemez; seçenekler
 * ve sayaçları filtrelenmemiş kümeden gelir ki daraltınca kaybolmasınlar.
 *
 * Parametreler (Türkçe, kısa — adres çubuğunda okunur):
 *   kalkis=İstanbul        ay[]=9            sure[]=5
 *   gece=4                 acenta[]=12       min_fiyat=5000    max_fiyat=20000
 *   sirala=fiyat_azalan
 *
 * gece: kullanıcının yazdığı gece sayısı (mobil panel, 2026-10-03 kullanıcı kararı —
 * hazır seçeneklerden seçmek yerine serbest sayı). duration_nights eşleşir; gece
 * girilmemiş eski turlarda gün-1 kuralı (Tour::duration_label ile aynı).
 */
final class LandingFilter
{
    public const SORTS = [
        'onerilen' => 'Önerilen',
        'fiyat_artan' => 'Fiyat (artan)',
        'fiyat_azalan' => 'Fiyat (azalan)',
        'sure' => 'Süre (kısa → uzun)',
        'tarih' => 'Tarih (yakın)',
    ];

    /**
     * İstek parametrelerini normalize eder; geçersiz değerler sessizce düşer.
     *
     * @param  array<string, mixed>  $query
     * @return array{kalkis: ?string, ay: int[], sure: int[], gece: ?int, acenta: int[], min_fiyat: ?float, max_fiyat: ?float, sirala: string}
     */
    public static function parse(array $query): array
    {
        $kalkis = $query['kalkis'] ?? null;
        $kalkis = is_string($kalkis) && trim($kalkis) !== '' ? trim($kalkis) : null;

        $sirala = $query['sirala'] ?? 'onerilen';
        if (! is_string($sirala) || ! isset(self::SORTS[$sirala])) {
            $sirala = 'onerilen';
        }

        return [
            'kalkis' => $kalkis,
            'ay' => self::ints($query['ay'] ?? null, 1, 12),
            'sure' => self::ints($query['sure'] ?? null, 1, 365),
            'gece' => self::ints($query['gece'] ?? null, 0, 365)[0] ?? null,
            'acenta' => self::ints($query['acenta'] ?? null, 1, PHP_INT_MAX),
            'min_fiyat' => self::money($query['min_fiyat'] ?? null),
            'max_fiyat' => self::money($query['max_fiyat'] ?? null),
            'sirala' => $sirala,
        ];
    }

    /** Sıralama dışında herhangi bir filtre seçili mi? */
    public static function isActive(array $f): bool
    {
        return $f['kalkis'] !== null
            || $f['ay'] !== []
            || $f['sure'] !== []
            || $f['gece'] !== null
            || $f['acenta'] !== []
            || $f['min_fiyat'] !== null
            || $f['max_fiyat'] !== null;
    }

    /** Aktif filtre sayısı (mobil "Filtrele (2)" düğmesi için). */
    public static function count(array $f): int
    {
        return ($f['kalkis'] !== null ? 1 : 0)
            + ($f['gece'] !== null ? 1 : 0)
            + count($f['ay']) + count($f['sure']) + count($f['acenta'])
            + ($f['min_fiyat'] !== null || $f['max_fiyat'] !== null ? 1 : 0);
    }

    public static function apply(Builder $query, array $f): Builder
    {
        if ($f['kalkis'] !== null) {
            $query->departsFrom($f['kalkis']);
        }

        if ($f['ay'] !== []) {
            $query->where(function ($q) use ($f) {
                foreach ($f['ay'] as $ay) {
                    $q->orWhereMonth('departure_date', $ay);
                }
            });
        }

        if ($f['sure'] !== []) {
            $query->whereIn('duration_days', $f['sure']);
        }

        // Gece sayısı: duration_nights; girilmemişse (eski turlar) gün-1 kuralı
        if ($f['gece'] !== null) {
            $gece = $f['gece'];
            $query->where(function ($q) use ($gece) {
                $q->where('duration_nights', $gece)
                    ->orWhere(function ($q2) use ($gece) {
                        $q2->whereNull('duration_nights')->where('duration_days', $gece + 1);
                    });
            });
        }

        if ($f['acenta'] !== []) {
            $query->whereIn('agency_id', $f['acenta']);
        }

        // Filtre değerleri TL — kur-normalize price_try ile karşılaştırılır
        if ($f['min_fiyat'] !== null) {
            $query->where('price_try', '>=', $f['min_fiyat']);
        }
        if ($f['max_fiyat'] !== null) {
            $query->where('price_try', '<=', $f['max_fiyat']);
        }

        return $query;
    }

    public static function sort(Builder $query, string $sirala): Builder
    {
        return match ($sirala) {
            'fiyat_azalan' => $query->orderByDesc('price_try')->orderBy('id'),
            'sure' => $query->orderBy('duration_days')->orderBy('price_try'),
            'tarih' => $query->orderBy('departure_date')->orderBy('price_try'),
            default => $query->orderBy('price_try')->orderBy('id'),
        };
    }

    /**
     * Seçenek kümeleri + sayaçlar, FİLTRELENMEMİŞ kümeden (tek çekim).
     *
     * @return array{
     *   kalkislar: array<int, array{ad: string, adet: int}>,
     *   aylar: array<int, array{ay: int, ad: string, adet: int}>,
     *   sureler: array<int, array{gun: int, etiket: string, adet: int}>,
     *   acentalar: array<int, array{id: int, ad: string, adet: int}>,
     *   fiyat: ?array{min: float, max: float},
     *   baslangic: ?string,
     *   ilkGorsel: ?string,
     *   enUzun: ?string
     * }
     */
    public static function facets(Builder $base): array
    {
        $tours = (clone $base)
            ->with('agency:id,name')
            ->orderBy('price_try')
            ->get(['id', 'agency_id', 'price', 'price_try', 'currency', 'duration_days', 'duration_nights', 'departure_date', 'departure_city', 'image']);

        $fiyatlar = $tours->pluck('price_try')->map(fn ($p) => (float) $p)->filter(fn ($p) => $p > 0);

        return [
            'kalkislar' => $tours
                ->filter(fn (Tour $t) => filled($t->departure_city))
                ->groupBy('departure_city')
                ->map(fn (Collection $g, $sehir) => ['ad' => (string) $sehir, 'adet' => $g->count()])
                ->sortBy('ad', SORT_LOCALE_STRING)
                ->values()
                ->all(),
            'aylar' => $tours
                ->filter(fn (Tour $t) => $t->departure_date !== null)
                ->groupBy(fn (Tour $t) => $t->departure_date->month)
                ->map(fn (Collection $g, $ay) => ['ay' => (int) $ay, 'ad' => TurkishMonths::name((int) $ay), 'adet' => $g->count()])
                ->sortBy('ay')
                ->values()
                ->all(),
            'sureler' => $tours
                ->filter(fn (Tour $t) => (int) $t->duration_days > 0)
                ->groupBy(fn (Tour $t) => (int) $t->duration_days)
                ->map(fn (Collection $g, $gun) => ['gun' => (int) $gun, 'etiket' => $g->first()->duration_label, 'adet' => $g->count()])
                ->sortBy('gun')
                ->values()
                ->all(),
            'acentalar' => $tours
                ->filter(fn (Tour $t) => $t->agency !== null)
                ->groupBy('agency_id')
                ->map(fn (Collection $g) => ['id' => (int) $g->first()->agency_id, 'ad' => (string) $g->first()->agency->name, 'adet' => $g->count()])
                ->sortBy('ad', SORT_LOCALE_STRING)
                ->values()
                ->all(),
            'fiyat' => $fiyatlar->isEmpty() ? null : [
                'min' => (float) floor($fiyatlar->min()),
                'max' => (float) ceil($fiyatlar->max()),
            ],
            // Hero rozeti: tüm turlar aynı para birimindeyse o birimle ("599 €"),
            // karışıksa TL karşılığıyla — kullanıcı kartta gördüğü rakamı görür.
            'baslangic' => self::baslangicEtiketi($tours),
            'ilkGorsel' => $tours->first(fn (Tour $t) => filled($t->image))?->image,
            'enUzun' => $tours->sortByDesc('duration_days')->first(fn (Tour $t) => (int) $t->duration_days > 0)?->duration_label,
        ];
    }

    private static function baslangicEtiketi(Collection $tours): ?string
    {
        $fiyatli = $tours->filter(fn (Tour $t) => (float) $t->price > 0);
        if ($fiyatli->isEmpty()) {
            return null;
        }

        $birimler = $fiyatli->pluck('currency')->map(fn ($c) => strtoupper((string) $c))->unique();
        if ($birimler->count() === 1) {
            $enUcuz = $fiyatli->sortBy(fn (Tour $t) => (float) $t->price)->first();

            return $enUcuz->formatted_price;
        }

        $min = $fiyatli->pluck('price_try')->map(fn ($p) => (float) $p)->filter(fn ($p) => $p > 0)->min();

        return $min ? number_format($min, 0, ',', '.').' ₺' : null;
    }

    /** @return int[] */
    private static function ints(mixed $raw, int $min, int $max): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }

        $list = is_array($raw) ? $raw : [$raw];
        $out = [];
        foreach ($list as $v) {
            if (is_string($v) || is_int($v)) {
                $v = (string) $v;
                if (ctype_digit($v) && (int) $v >= $min && (int) $v <= $max) {
                    $out[] = (int) $v;
                }
            }
        }

        return array_values(array_unique($out));
    }

    private static function money(mixed $raw): ?float
    {
        if (! is_string($raw) && ! is_int($raw) && ! is_float($raw)) {
            return null;
        }
        $raw = str_replace(['.', ' '], '', (string) $raw);
        $raw = str_replace(',', '.', $raw);

        return is_numeric($raw) && (float) $raw >= 0 ? (float) $raw : null;
    }
}
