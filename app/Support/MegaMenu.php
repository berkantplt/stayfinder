<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Tour;
use Illuminate\Support\Facades\Cache;

/**
 * Ana sayfa mega menüsünün içeriği (malitur kalıbı: kova → sütunlar → linkler).
 *
 * TEK KURAL: envanterden türer, elle küratörlü liste yoktur. Turu olmayan
 * hiçbir başlık menüye girmez — kullanıcı asla boş sayfaya tıklamaz, ve
 * envanter büyüdükçe menü kendiliğinden zenginleşir.
 *
 * Eşik neden var: 30 alt kategorinin çoğu tek turluk. Hepsini listelemek
 * hem menüyü çöplüğe çevirir hem de Google'ın "zayıf içerik" saydığı
 * sayfalara link vermek olur.
 */
class MegaMenu
{
    public const CACHE_KEY = 'home_mega_menu_v1';

    /** Menüde görünmek için gereken en az tur sayısı. */
    private const MIN_TOURS = 2;

    /** Bir sütunda gösterilecek en fazla link. */
    private const MAX_PER_COLUMN = 10;

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array<int, array{key:string, label:string, count:int, columns:array<int, array{title:string, links:array<int, array{label:string, url:string, count:int}>}>}>
     */
    public static function build(): array
    {
        return Cache::remember(self::CACHE_KEY, 300, function () {
            $kovalar = [];

            foreach ([[false, 'yurt-ici', 'Yurt İçi Turlar'], [true, 'yurt-disi', 'Yurt Dışı Turlar']] as [$disi, $key, $label]) {
                $temel = fn () => Tour::active()
                    ->whereHas('agency', fn ($q) => $q->active())
                    ->where('is_international', $disi);

                $adet = $temel()->count();
                if ($adet === 0) {
                    continue;
                }

                $sutunlar = array_values(array_filter([
                    self::destinasyonSutunu($temel()),
                    self::kategoriSutunu($temel(), $disi),
                ]));

                if ($sutunlar === []) {
                    continue;
                }

                $kovalar[] = ['key' => $key, 'label' => $label, 'count' => $adet, 'columns' => $sutunlar];
            }

            if ($ozel = self::ozelDonemSutunu()) {
                $kovalar[] = [
                    'key' => 'ozel-gunler',
                    'label' => 'Özel Dönemler',
                    'count' => array_sum(array_column($ozel['links'], 'count')),
                    'columns' => [$ozel],
                ];
            }

            return $kovalar;
        });
    }

    /** @return array{title:string, links:array}|null */
    private static function destinasyonSutunu($query): ?array
    {
        $links = [];
        foreach (DestinationFilter::vocabulary($query) as $d) {
            if ($d['count'] < self::MIN_TOURS) {
                continue;
            }
            $links[] = [
                'label' => $d['city'],
                'url' => route('tours.index', ['destination' => $d['city']]),
                'count' => $d['count'],
            ];
            if (count($links) >= self::MAX_PER_COLUMN) {
                break;
            }
        }

        return $links === [] ? null : ['title' => 'Destinasyonlar', 'links' => $links];
    }

    /** @return array{title:string, links:array}|null */
    private static function kategoriSutunu($query, bool $disi): ?array
    {
        // whereNotNull şart: kategorisiz turlar null anahtar üretip
        // pluck()'ta deprecation uyarısına yol açıyordu.
        $sayimlar = (clone $query)
            ->whereNotNull('category_id')
            ->selectRaw('category_id, COUNT(*) as toplam')
            ->groupBy('category_id')
            ->pluck('toplam', 'category_id');

        if ($sayimlar->isEmpty()) {
            return null;
        }

        $kategoriler = Category::active()->whereIn('id', $sayimlar->keys())->get();

        $links = [];
        foreach ($kategoriler as $kategori) {
            $adet = (int) $sayimlar[$kategori->id];
            if ($adet < self::MIN_TOURS) {
                continue;
            }
            $links[] = [
                'label' => trim(($kategori->icon ? $kategori->icon.' ' : '').$kategori->name),
                'url' => route('tours.index', ['category' => $kategori->slug]),
                'count' => $adet,
            ];
        }

        usort($links, fn ($a, $b) => $b['count'] <=> $a['count']);

        return $links === [] ? null : [
            'title' => 'Kategoriler',
            'links' => array_slice($links, 0, self::MAX_PER_COLUMN),
        ];
    }

    /**
     * Özel dönemler config'ten gelir ama YALNIZ gerçekten turu olanlar listelenir —
     * "Kurban Bayramı" başlığına tıklayıp boş sayfa görmek en kötüsü.
     *
     * @return array{title:string, links:array}|null
     */
    private static function ozelDonemSutunu(): ?array
    {
        $links = [];

        foreach (config('special_periods', []) as $key => $donem) {
            $adet = Tour::active()
                ->whereHas('agency', fn ($q) => $q->active())
                ->where(function ($q) use ($donem) {
                    foreach ($donem['ranges'] ?? [] as [$bas, $bit]) {
                        $q->orWhereBetween('departure_date', [$bas, $bit])
                            ->orWhereHas('dates', fn ($d) => $d->whereBetween('departure_date', [$bas, $bit]));
                    }
                })
                ->count();

            if ($adet < 1) {
                continue;
            }

            $links[] = [
                'label' => $donem['label'] ?? $key,
                'url' => route('home', ['special' => $key]),
                'count' => $adet,
            ];
        }

        return $links === [] ? null : ['title' => 'Yaklaşan dönemler', 'links' => $links];
    }
}
