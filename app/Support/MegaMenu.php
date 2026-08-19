<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Tour;
use Illuminate\Support\Facades\Cache;

/**
 * Ana sayfa mega menüsü — malitur kalıbı, ÜÇ KATMAN:
 *
 *     üst şerit (kova) → sol ray (ana kategori) → orta sütun (alt kategoriler)
 *
 * Kategori ağacı iki seviye olduğu için en üstteki gruplama config/mega_menu.php
 * dosyasından gelir; kovaya yazılmamış ana kategori "Diğer Turlar" kovasına
 * düşer, yani HİÇBİR kategori menüden kaybolmaz. Turu olmayan kategori de
 * görünür — menü ile filtre barındaki "Kategoriler" paneli aynı listeyi
 * göstermek zorunda, yoksa kullanıcı hangisinin doğru olduğunu bilemez.
 *
 * Linkler düz landing adresleridir (/kultur-turlari), query string DEĞİL:
 * tek facet'li query adresi zaten TourController tarafından 301 ile oraya
 * taşınıyor (bkz. App\Support\LandingSlug).
 *
 * Sayaçlar filtre barıyla aynı kuralla hesaplanır: bir kategorinin sayısı
 * kendisi + TÜM alt seviyeleridir (Category::descendantIds).
 */
class MegaMenu
{
    /**
     * DİKKAT: Dizinin ŞEKLİ her değiştiğinde sürek numarası da artmalı.
     * Aksi halde deploy sonrası eski biçimdeki önbellek okunur ve şablon
     * "Undefined array key" ile 500 verir (bir kez yaşandı).
     */
    public const CACHE_KEY = 'home_mega_menu_v4';

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array<int, array{
     *     key:string, label:string, icon:string,
     *     rail:array<int, array{
     *         key:string, icon:?string, name:string, count:int, url:string,
     *         links:array<int, array{icon:?string, label:string, url:string, count:int}>
     *     }>
     * }>
     */
    public static function build(): array
    {
        return Cache::remember(self::CACHE_KEY, 300, function () {
            $agac = self::kategoriAgaci();
            if ($agac === []) {
                return [];
            }

            $kalanlar = $agac;
            $kovalar = [];

            foreach (config('mega_menu.buckets', []) as $tanim) {
                $dallar = [];
                foreach ($tanim['categories'] ?? [] as $slug) {
                    if (isset($kalanlar[$slug])) {
                        $dallar[] = $kalanlar[$slug];
                        unset($kalanlar[$slug]);
                    }
                }

                // Kovadaki kategorilerin hiçbiri yoksa (silinmiş/pasifleşmiş)
                // boş başlık basmayalım: kullanıcı boş panele tıklamasın.
                if ($dallar === []) {
                    continue;
                }

                $kovalar[] = [
                    'key' => $tanim['key'],
                    'label' => $tanim['label'],
                    'icon' => $tanim['icon'] ?? '',
                    'rail' => array_map(self::rayOgesi(...), $dallar),
                ];
            }

            // Kovaya yazılmamış ana kategoriler kaybolmasın
            if ($kalanlar !== []) {
                $yedek = config('mega_menu.fallback', []);
                $kovalar[] = [
                    'key' => $yedek['key'] ?? 'diger',
                    'label' => $yedek['label'] ?? 'Diğer Turlar',
                    'icon' => $yedek['icon'] ?? '',
                    'rail' => array_map(self::rayOgesi(...), array_values($kalanlar)),
                ];
            }

            return $kovalar;
        });
    }

    /**
     * Sol raydaki bir dal: ana kategori + orta sütunda listelenecek linkleri.
     *
     * @param  array{slug:string, name:string, icon:?string, count:int, children:array}  $ust
     */
    private static function rayOgesi(array $ust): array
    {
        // İlk satır üst kategorinin kendisi: alt kırılımlardan birini seçmek
        // istemeyen kullanıcı tüm dalı görebilsin.
        $linkler = [[
            'icon' => $ust['icon'],
            'label' => 'Tüm '.$ust['name'],
            'url' => $ust['url'],
            'count' => $ust['count'],
        ]];

        foreach ($ust['children'] as $alt) {
            $linkler[] = [
                'icon' => $alt['icon'],
                'label' => $alt['name'],
                'url' => $alt['url'],
                'count' => $alt['count'],
            ];
        }

        return [
            'key' => $ust['slug'],
            'icon' => $ust['icon'],
            'name' => $ust['name'],
            'count' => $ust['count'],
            'url' => $ust['url'],
            'links' => $linkler,
        ];
    }

    /**
     * Kategori ağacı + sayaçlar + landing adresleri, tek çekimde.
     * Anahtar = ana kategorinin slug'ı (kova eşleştirmesi bunun üzerinden).
     *
     * @return array<string, array{slug:string, name:string, icon:?string, count:int, url:string, children:array}>
     */
    private static function kategoriAgaci(): array
    {
        $sayimlar = Tour::query()->active()
            ->whereNotNull('category_id')
            ->selectRaw('category_id, COUNT(*) as toplam')
            ->groupBy('category_id')
            ->pluck('toplam', 'category_id')
            ->all();

        $tumKategoriler = Category::select(['id', 'parent_id'])->get();

        $ustler = Category::active()
            ->parents()
            ->with(['children' => fn ($q) => $q->active()->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get();

        $agac = [];

        foreach ($ustler as $ust) {
            $agac[$ust->slug] = [
                'slug' => $ust->slug,
                'name' => $ust->name,
                'icon' => $ust->icon,
                'count' => self::toplam($ust, $tumKategoriler, $sayimlar),
                'url' => LandingSlug::urlForCategory($ust),
                'children' => $ust->children->map(fn (Category $alt) => [
                    'slug' => $alt->slug,
                    'name' => $alt->name,
                    'icon' => $alt->icon,
                    'count' => self::toplam($alt, $tumKategoriler, $sayimlar),
                    'url' => LandingSlug::urlForCategory($alt),
                ])->all(),
            ];
        }

        return $agac;
    }

    /** Kategorinin sayacı: kendisi + tüm alt seviyeleri (filtre barıyla aynı kural). */
    private static function toplam(Category $kategori, $tumKategoriler, array $sayimlar): int
    {
        $toplam = 0;
        foreach ($kategori->descendantIds($tumKategoriler) as $id) {
            $toplam += (int) ($sayimlar[$id] ?? 0);
        }

        return $toplam;
    }
}
