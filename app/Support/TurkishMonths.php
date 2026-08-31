<?php

namespace App\Support;

/**
 * Türkçe ay adları — GÖRÜNTÜLEME için tek kaynak (1..12 → 'Ocak'..'Aralık').
 *
 * DİKKAT: TourUrlImporter buraya BAĞLANMAZ ve bağlanmamalı — import tarafının
 * ihtiyacı görüntüleme değil ayrıştırmadır (ASCII varyantlı regex'ler: Subat,
 * Mayis, Agustos... + ad→sayı haritası + tarayıcı-içi JS MONX). O kopyalar
 * bilinçli olarak dosyaya özel tutulur; buradan beslenmeleri tarih hasadını
 * sessizce bozar.
 */
final class TurkishMonths
{
    public const NAMES = [1 => 'Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'];

    public static function name(int $month): string
    {
        return self::NAMES[$month] ?? '';
    }
}
