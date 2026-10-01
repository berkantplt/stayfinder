<?php

namespace App\Support;

use App\Models\Category;
use App\Models\CategoryBanner;
use App\Models\Destination;

/**
 * Landing sayfası hero'su: hangi banner, hangi metinler, hangi karartma.
 *
 * Çözümleme sırası (kategori):
 *   1. kategorinin kendi aktif banner'ı
 *   2. üst kategorisinin aktif banner'ı (alt kategori miras alır)
 *   3. genel varsayılan (category_id NULL)
 *   4. banner yok → kategori kart görseli → listedeki ilk tur görseli → yalnız degrade
 *
 * Destinasyon sayfaları yalnız 3 ve 4'ü kullanır (banner yönetimi kategori
 * bazlı; destinasyonun kendi görseli varsa o öne geçer).
 *
 * Metinler: banner alanı boşsa sayfa adından türetilen varsayılan basılır —
 * böylece hiçbir sayfa boş hero ile kalmaz, admin yalnız istediğini ezer.
 */
final class CategoryHero
{
    /**
     * Karartma degradesinin uçları: sol uç = koyuluk + LEFT_BOOST (metin
     * zemini), sağ uç = koyuluk × RIGHT_FACTOR (fotoğraf görünür kalsın).
     * Admin önizlemesi (JS) de aynı sabitleri @json ile alır — iki yere ayrı
     * yazılırsa admin'de görülenle sitede çıkan görüntü sessizce ayrışır.
     */
    public const LEFT_BOOST = 0.45;

    public const RIGHT_FACTOR = 0.35;

    public const OVERLAY_RGB = '6,24,22';

    /**
     * @return array{
     *   image: ?string, eyebrow: string, title: string, subtitle: string,
     *   caption: ?string, darkness: int, overlay: string, source: string, banner: ?CategoryBanner
     * }
     */
    public static function forCategory(Category $category, ?string $fallbackImage = null): array
    {
        $banner = self::resolveBanner($category);
        $source = $banner === null ? 'fallback' : match (true) {
            $banner->category_id === $category->id => 'own',
            $banner->category_id === null => 'default',
            default => 'parent',
        };

        return self::compose(
            $banner,
            $source,
            (string) $category->name,
            $banner?->image_url ?? $category->image_url ?? self::toUrl($fallbackImage),
        );
    }

    public static function forDestination(Destination $destination, ?string $fallbackImage = null): array
    {
        // Destinasyonun kendi görseli varsa genel varsayılan banner'ı ezmesin;
        // şehir sayfası şehrin fotoğrafını göstermeli.
        $kendiGorseli = $destination->image ? self::toUrl((string) $destination->image) : null;
        $banner = $kendiGorseli ? null : self::defaultBanner();

        return self::compose(
            $banner,
            $banner ? 'default' : 'fallback',
            (string) $destination->name,
            $banner?->image_url ?? $kendiGorseli ?? self::toUrl($fallbackImage),
        );
    }

    /** Kendi → üst kategori → genel varsayılan. Tek sorgu. */
    public static function resolveBanner(Category $category): ?CategoryBanner
    {
        $adaylar = CategoryBanner::query()
            ->active()
            ->where(function ($q) use ($category) {
                $q->whereIn('category_id', array_filter([$category->id, $category->parent_id]))
                    ->orWhereNull('category_id');
            })
            ->get()
            ->keyBy(fn (CategoryBanner $b) => $b->category_id ?? 0);

        return $adaylar->get($category->id)
            ?? ($category->parent_id ? $adaylar->get($category->parent_id) : null)
            ?? $adaylar->get(0);
    }

    public static function defaultBanner(): ?CategoryBanner
    {
        return CategoryBanner::query()->active()->whereNull('category_id')->first();
    }

    /**
     * Sayfa adından türeyen varsayılan metinler. "turlarını" eki her gövdede
     * aynı kalır (ek "turları" kelimesine gelir, gövdeye değil) — ünlü uyumu
     * tuzağı yok.
     *
     * @return array{eyebrow: string, title: string, subtitle: string}
     */
    public static function defaults(string $name): array
    {
        $stem = Seo::stem($name);

        return [
            'eyebrow' => mb_strtoupper($stem.' Turları', 'UTF-8'),
            'title' => 'Bir yolculuk, birçok hikâye.',
            'subtitle' => $stem.' turlarını keşfet, sana uygun rotayı bul.',
        ];
    }

    /** Karartma degradesi (CSS background). Admin önizlemesiyle aynı formül. */
    public static function overlayCss(int $darkness): string
    {
        $a = max(0, min(100, $darkness)) / 100;
        $sol = min(1, $a + self::LEFT_BOOST);
        $sag = $a * self::RIGHT_FACTOR;
        $rgb = self::OVERLAY_RGB;

        return sprintf(
            'linear-gradient(90deg, rgba(%s,%s) 0%%, rgba(%s,%s) 48%%, rgba(%s,%s) 100%%)',
            $rgb, round($sol, 3), $rgb, round($a, 3), $rgb, round($sag, 3)
        );
    }

    private static function compose(?CategoryBanner $banner, string $source, string $name, ?string $image): array
    {
        $vars = self::defaults($name);
        $darkness = $banner?->darkness ?? CategoryBanner::DEFAULT_DARKNESS;

        return [
            'image' => $image,
            'eyebrow' => self::filled($banner?->eyebrow) ?? $vars['eyebrow'],
            'title' => self::filled($banner?->title) ?? $vars['title'],
            'subtitle' => self::filled($banner?->subtitle) ?? $vars['subtitle'],
            'caption' => self::filled($banner?->caption),
            'darkness' => $darkness,
            'overlay' => self::overlayCss($darkness),
            'source' => $source,
            'banner' => $banner,
        ];
    }

    private static function filled(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * Görsel yolu → tam adres. Destinasyon ve tur görselleri ya tam URL ya
     * "/storage/..." biçiminde kök yol (TourImageService), kategori banner'ı ise
     * public disk yolu ("category-banners/x.jpg").
     */
    public static function toUrl(?string $path): ?string
    {
        $path = trim((string) $path);
        if ($path === '') {
            return null;
        }
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }
        if (str_starts_with($path, '/')) {
            return url($path);
        }

        return asset('storage/'.$path);
    }
}
