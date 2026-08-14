<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Tour;
use Illuminate\Support\Facades\Cache;

/**
 * Ana sayfa kategori ağacı (malitur kalıbı: üst kategori → alt kategoriler).
 *
 * TEK KURAL: kategori ağacının tamamı basılır — filtre barındaki "Kategoriler"
 * paneliyle BİREBİR aynı liste. Turu olmayan kategori de görünür; menü ile
 * filtre farklı şeyler gösterirse kullanıcı hangisinin doğru olduğunu bilemez.
 *
 * (2026-08-13 öncesi burası envanterden türeyen kovalar üretiyordu — "Yurt İçi
 * Turlar / Yurt Dışı Turlar / Özel Dönemler" — ve en az 2 turu olmayan başlığı
 * gizliyordu. Kullanıcı kararıyla kategori ağacına çevrildi.)
 *
 * Sayaçlar filtre barıyla aynı kuralla hesaplanır: bir kategorinin sayısı
 * kendisi + TÜM alt seviyeleridir (Category::descendantIds). Ayrı hesaplanırsa
 * menü ile filtre farklı rakam gösterir.
 */
class MegaMenu
{
    /**
     * DİKKAT: Dizinin ŞEKLİ her değiştiğinde sürek numarası da artmalı.
     * Aksi halde deploy sonrası eski biçimdeki önbellek okunur ve şablon
     * "Undefined array key" ile 500 verir (bir kez yaşandı).
     */
    public const CACHE_KEY = 'home_mega_menu_v3';

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array<int, array{key:string, icon:?string, name:string, count:int, url:string, columns:array<int, array{title:string, links:array<int, array{icon:?string, label:string, url:string, count:int}>}>}>
     */
    public static function build(): array
    {
        return Cache::remember(self::CACHE_KEY, 300, function () {
            // Tur::active() pasif acentayı ve lisanssız kategoriyi zaten eler;
            // HomeController'ın facet sorgusuyla aynı taban kullanılıyor ki
            // menü ile filtre aynı rakamı göstersin.
            $sayimlar = Tour::query()->active()
                ->whereNotNull('category_id')
                ->selectRaw('category_id, COUNT(*) as toplam')
                ->groupBy('category_id')
                ->pluck('toplam', 'category_id')
                ->all();

            // Torun toplamı için tek çekim — döngüde DB'ye gidilmez.
            $tumKategoriler = Category::select(['id', 'parent_id'])->get();

            $ustler = Category::active()
                ->parents()
                ->with(['children' => fn ($q) => $q->active()->orderBy('sort_order')])
                ->orderBy('sort_order')
                ->get();

            $kovalar = [];

            foreach ($ustler as $ust) {
                $linkler = [];
                foreach ($ust->children as $alt) {
                    $linkler[] = [
                        'icon' => $alt->icon,
                        'label' => $alt->name,
                        'url' => route('tours.index', ['category' => $alt->slug]),
                        'count' => self::toplam($alt, $tumKategoriler, $sayimlar),
                    ];
                }

                $kovalar[] = [
                    'key' => $ust->slug,
                    // İkon ayrı taşınır: şablonda kendi <span>'ında basılınca
                    // flex gap'i devreye giriyor, "🏛️Kültür" gibi bitişik durmuyor.
                    'icon' => $ust->icon,
                    'name' => $ust->name,
                    'count' => self::toplam($ust, $tumKategoriler, $sayimlar),
                    'url' => route('tours.index', ['category' => $ust->slug]),
                    // Alt kategorisi yoksa panel de yok: tetikleyici düz link olur.
                    'columns' => $linkler === [] ? [] : [
                        ['title' => 'Alt kategoriler', 'links' => $linkler],
                    ],
                ];
            }

            return $kovalar;
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
