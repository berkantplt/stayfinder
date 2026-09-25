<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Tour;
use Illuminate\Support\Facades\Cache;

/**
 * Ana sayfa kategori ağacı (mega menü) — yönetim panelindeki ağacın KENDİSİ,
 * iki katman:
 *
 *     kapalı: üst şerit  = üst kategoriler (admin › Üst Kategori Yönetimi)
 *     açık:   panel      = o üst kategorinin alt kategorileri + kart görseli
 *
 * 2026-09-25 tasarımı: eskiden araya config/mega_menu.php'den gelen bir "kova"
 * katmanı giriyordu (13 başlık tek şeride sığmıyordu). Kaldırıldı — şerit
 * doğrudan yöneticinin kurduğu ağacı gösterir; gruplama artık yöneticinin
 * kendi elinde (üst kategori = şeritteki başlık).
 *
 * Değişmeyen kurallar:
 *  - Hiçbir aktif üst kategori menüden kaybolmaz; turu olmayan da görünür.
 *    Menü ile filtre barındaki "Kategoriler" paneli aynı listeyi göstermek
 *    zorunda, yoksa kullanıcı hangisinin doğru olduğunu bilemez.
 *  - Linkler düz landing adresleridir (/kultur-turlari), query string DEĞİL:
 *    tek facet'li query adresi zaten TourController tarafından 301 ile oraya
 *    taşınıyor (bkz. App\Support\LandingSlug).
 *  - Sayaçlar filtre barıyla aynı kuralla hesaplanır: bir kategorinin sayısı
 *    kendisi + TÜM alt seviyeleridir (Category::descendantIds).
 */
class MegaMenu
{
    /**
     * DİKKAT: Dizinin ŞEKLİ her değiştiğinde sürek numarası da artmalı.
     * Aksi halde deploy sonrası eski biçimdeki önbellek okunur ve şablon
     * "Undefined array key" ile 500 verir (bir kez yaşandı).
     * v6: kova katmanı kalktı, kök = üst kategoriler (image/description eklendi).
     */
    public const CACHE_KEY = 'home_mega_menu_v6';

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Üst kategoriler sort_order sırasıyla; her biri kendi alt kategorilerini taşır.
     *
     * @return array<int, array{
     *     key:string, name:string, icon:?string, description:?string, image:?string,
     *     count:int, url:string,
     *     children:array<int, array{key:string, name:string, icon:?string, count:int, url:string}>
     * }>
     */
    public static function build(): array
    {
        return Cache::remember(self::CACHE_KEY, 300, function () {
            $sayimlar = Tour::query()->active()
                ->whereNotNull('category_id')
                ->selectRaw('category_id, COUNT(*) as toplam')
                ->groupBy('category_id')
                ->pluck('toplam', 'category_id')
                ->all();

            // descendantIds'e hazır liste veriliyor ki döngüde N+1 olmasın.
            $tumKategoriler = Category::select(['id', 'parent_id'])->get();

            $ustler = Category::active()
                ->parents()
                ->with(['children' => fn ($q) => $q->active()->orderBy('sort_order')])
                ->orderBy('sort_order')
                ->get();

            return $ustler->map(fn (Category $ust) => [
                'key' => $ust->slug,
                'name' => $ust->name,
                'icon' => $ust->icon,
                'description' => $ust->description ?: null,
                'image' => $ust->image_url,
                'count' => self::toplam($ust, $tumKategoriler, $sayimlar),
                'url' => LandingSlug::urlForCategory($ust),
                'children' => $ust->children->map(fn (Category $alt) => [
                    'key' => $alt->slug,
                    'name' => $alt->name,
                    'icon' => $alt->icon,
                    'count' => self::toplam($alt, $tumKategoriler, $sayimlar),
                    'url' => LandingSlug::urlForCategory($alt),
                ])->values()->all(),
            ])->values()->all();
        });
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
