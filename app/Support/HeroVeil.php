<?php

namespace App\Support;

use App\Models\SiteSetting;

/**
 * Ana sayfa hero'sundaki beyaz perde: koyu lacivert başlığın fotoğraf üzerinde
 * okunmasını sağlayan, soldan gelen beyaz degrade.
 *
 * TEK AYAR, TÜM GÖRSELLER (2026-08-13 kullanıcı kararı). Kısa süre banner
 * başına ayarlanabiliyordu; her banner'a ayrı değer vermek yerine tek genel
 * ayar tercih edildi.
 */
class HeroVeil
{
    public const SETTING_KEY = 'hero_white_veil';

    /** Tasarımdaki varsayılan güç. */
    public const DEFAULT = 100;

    /**
     * Degradenin durakları: [alfa, yüzde].
     *
     * TEK KAYNAK: hem ana sayfa (PHP) hem admin önizlemesi (JS, @json ile alır)
     * bu diziyi kullanır. İki yere ayrı yazılırsa admin'de görülenle sitede
     * çıkan görüntü sessizce ayrışır.
     */
    public const STOPS = [[0.94, 0], [0.86, 24], [0.58, 44], [0.14, 63], [0.0, 78]];

    /** Kayıtlı perde gücü (0-100). */
    public static function strength(): int
    {
        $deger = SiteSetting::get(self::SETTING_KEY);

        return $deger === null ? self::DEFAULT : self::clamp((int) $deger);
    }

    public static function setStrength(int $veil): void
    {
        SiteSetting::set(self::SETTING_KEY, self::clamp($veil));
    }

    /**
     * Perdenin CSS degradesi. Duraklar güçle orantılı ölçeklenir:
     * 100 → tasarımın aynısı, 0 → perde yok.
     */
    public static function css(?int $veil = null): string
    {
        $k = self::clamp($veil ?? self::strength()) / 100;

        $parts = [];
        foreach (self::STOPS as [$alpha, $pos]) {
            $parts[] = 'rgba(255,255,255,'.round($alpha * $k, 3).') '.$pos.'%';
        }

        return 'linear-gradient(97deg, '.implode(', ', $parts).')';
    }

    private static function clamp(int $veil): int
    {
        return max(0, min(100, $veil));
    }
}
