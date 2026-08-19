<?php

namespace App\Support;

use App\Models\SiteSetting;

/**
 * Mobil hero'daki turkuaz desen katmanı (daire + dalga + kurdele kompozisyonu).
 *
 * Kullanıcının verdiği overlay görselinin SVG yeniden çizimi home.blade.php'de
 * durur; buradan yalnız iki ayar yönetilir (admin → Banner Yönetimi):
 *  - şeffaflık: katmanın opacity'si (0 = görünmez, 100 = tam)
 *  - koyuluk: brightness filtresi (0 = orijinal renk, 100 = en koyu)
 *
 * HeroVeil ile aynı kalıp: SiteSetting'e yazılır, tek ayar siteye uygulanır.
 */
class HeroDeco
{
    public const OPACITY_KEY = 'hero_deco_opacity';

    public const DARKNESS_KEY = 'hero_deco_darkness';

    public const OPACITY_DEFAULT = 100;

    public const DARKNESS_DEFAULT = 0;

    /** Koyuluk %100'ken brightness bu değere iner (0.4 = epey koyu, okunur kalır).
     * PUBLIC: admin önizlemesi @json ile aynı değeri kullanır — iki yere yazılmaz. */
    public const MIN_BRIGHTNESS = 0.4;

    /** Kayıtlı şeffaflık (0-100). */
    public static function opacity(): int
    {
        $deger = SiteSetting::get(self::OPACITY_KEY);

        return $deger === null ? self::OPACITY_DEFAULT : self::clamp((int) $deger);
    }

    /** Kayıtlı koyuluk (0-100). */
    public static function darkness(): int
    {
        $deger = SiteSetting::get(self::DARKNESS_KEY);

        return $deger === null ? self::DARKNESS_DEFAULT : self::clamp((int) $deger);
    }

    public static function set(int $opacity, int $darkness): void
    {
        SiteSetting::set(self::OPACITY_KEY, self::clamp($opacity));
        SiteSetting::set(self::DARKNESS_KEY, self::clamp($darkness));
    }

    /** SVG elemanına basılacak style değeri — tek kaynak, admin önizlemesi de bunu kullanır. */
    public static function css(?int $opacity = null, ?int $darkness = null): string
    {
        $o = self::clamp($opacity ?? self::opacity()) / 100;
        $k = self::clamp($darkness ?? self::darkness()) / 100;
        $brightness = round(1 - (1 - self::MIN_BRIGHTNESS) * $k, 3);

        return 'opacity:'.round($o, 3).';filter:brightness('.$brightness.');';
    }

    private static function clamp(int $deger): int
    {
        return max(0, min(100, $deger));
    }
}
