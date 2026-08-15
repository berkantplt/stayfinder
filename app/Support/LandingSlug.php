<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Destination;
use Illuminate\Support\Str;

/**
 * Düz (flat) landing URL'leri: /kapadokya-turlari, /kultur-turlari
 *
 * NEDEN query string değil: rakip taramasında incelenen 9 sitenin HİÇBİRİ
 * kategori/destinasyon sayfasını query string ile sunmuyor. Gruppal ve MNG
 * sayfalarında toplam SIFIR query-string iç linki var; hepsi düz yol segmenti
 * kullanıyor (/gemi-turlari, /doga-turlari, /kapadokya-turlari).
 *
 * Kural: her landing adresi "-turlari" ile biter. Slug'lar tutarsız
 * ("kultur-turlari" ekli, "deniz-tekne" eksiz, "gunubirlik-turlar" farklı ekli)
 * olduğu için hepsi tek biçime normalize edilir; böylece rota tek bir düzenli
 * ifadeyle sınırlanabilir ve kök dizini yutan bir catch-all gerekmez.
 */
class LandingSlug
{
    public const SUFFIX = '-turlari';

    /** Rota kısıtı: yalnız "-turlari" ile biten tek segmentli adresler. */
    public const ROUTE_PATTERN = '[a-z0-9]+(?:-[a-z0-9]+)*'.self::SUFFIX;

    /**
     * Slug'ın "tur" ekinden arındırılmış gövdesi:
     * "kultur-turlari" → "kultur", "gunubirlik-turlar" → "gunubirlik",
     * "deniz-tekne" → "deniz-tekne"
     */
    public static function stem(string $slug): string
    {
        $slug = trim(strtolower($slug), '-');

        foreach (['-turlari', '-turlar', '-turu', '-tur'] as $suffix) {
            if (str_ends_with($slug, $suffix)) {
                $stripped = substr($slug, 0, -strlen($suffix));
                if ($stripped !== '') {
                    return $stripped;
                }
            }
        }

        return $slug;
    }

    /** Herhangi bir slug'dan kanonik landing slug'ı: "deniz-tekne" → "deniz-tekne-turlari" */
    public static function canonicalize(string $slug): string
    {
        return self::stem($slug).self::SUFFIX;
    }

    public static function forCategory(Category $category): string
    {
        return self::canonicalize((string) $category->slug);
    }

    public static function forDestination(Destination $destination): string
    {
        return self::canonicalize((string) $destination->slug);
    }

    public static function urlForCategory(Category $category): string
    {
        return url('/'.self::forCategory($category));
    }

    public static function urlForDestination(Destination $destination): string
    {
        return url('/'.self::forDestination($destination));
    }

    /**
     * Adresi çözer. Kategori önce denenir: kategori ağacı sitenin ana
     * gezinme omurgası, destinasyon ondan türeyen bir kesit. Bugün çakışan bir
     * çift yok, ama ileride olursa davranış belirli kalsın.
     *
     * @return array{type: 'category'|'destination', model: Category|Destination}|null
     */
    public static function resolve(string $slug): ?array
    {
        $stem = self::stem($slug);

        $category = Category::query()
            ->active()
            ->where(fn ($q) => $q->where('slug', $slug)->orWhere('slug', $stem))
            ->first();

        if ($category !== null) {
            return ['type' => 'category', 'model' => $category];
        }

        $destination = Destination::query()
            ->where(fn ($q) => $q->where('slug', $slug)->orWhere('slug', $stem))
            ->first();

        if ($destination !== null) {
            return ['type' => 'destination', 'model' => $destination];
        }

        return null;
    }

    /**
     * Görünen ad — başlık ve H1 için. Slug değil, modelin adı esas alınır.
     */
    public static function heading(Category|Destination $model): string
    {
        return Seo::listingHeading((string) $model->name);
    }

    /**
     * Kategori adından slug üretirken kullanılacak biçim (yeni kategori eklenince).
     */
    public static function fromName(string $name): string
    {
        return self::canonicalize(Str::slug($name));
    }
}
