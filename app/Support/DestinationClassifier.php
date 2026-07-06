<?php

namespace App\Support;

/**
 * Destinasyon metninden yurt içi/yurt dışı sınıflandırması. Turlarda
 * is_international bayrağını hiçbir form/import doldurmuyordu — İspanya turu
 * veritabanında "yurt içi" görünüyor, "yurt dışı istiyorum" filtresi boş
 * dönüyordu. Bu sınıf kayıt anında ve backfill'de bayrağı deterministik türetir;
 * AI arama heuristikleri de aynı listeleri kullanır (tek kaynak).
 */
class DestinationClassifier
{
    /** Yurt dışı ülke/şehir/bölge adları (normalize edilmiş halleriyle). */
    public const FOREIGN_PLACES = [
        // Avrupa şehirleri
        'paris', 'roma', 'londra', 'amsterdam', 'venedik', 'barselona', 'barcelona', 'prag',
        'atina', 'berlin', 'viyana', 'milano', 'floransa', 'budapeste', 'munih', 'madrid',
        'lizbon', 'porto', 'sevilla', 'granada', 'valencia', 'valensiya', 'endulus', 'napoli',
        'zurih', 'cenevre', 'brüksel', 'bruksel', 'kopenhag', 'stokholm', 'oslo', 'helsinki',
        'varsova', 'krakow', 'zagreb', 'belgrad', 'saraybosna', 'uskup', 'tiran', 'podgorica',
        'sofya', 'bukres', 'kiev', 'moskova', 'st petersburg',
        // Ülkeler
        'italya', 'ispanya', 'fransa', 'almanya', 'ingiltere', 'portekiz', 'yunanistan',
        'hollanda', 'belcika', 'avusturya', 'isvicre', 'macaristan', 'polonya', 'cekya',
        'hirvatistan', 'sirbistan', 'romanya', 'bulgaristan', 'arnavutluk', 'karadag',
        'bosna', 'makedonya', 'kosova', 'slovenya', 'slovakya', 'norvec', 'isvec',
        'finlandiya', 'danimarka', 'irlanda', 'iskocya', 'rusya', 'ukrayna', 'moldova',
        'gurcistan', 'azerbaycan', 'ermenistan', 'kazakistan', 'ozbekistan', 'turkmenistan',
        'kirgizistan', 'misir', 'fas', 'tunus', 'cezayir', 'kenya', 'tanzanya', 'zanzibar',
        'guney afrika', 'dubai', 'abu dabi', 'katar', 'doha', 'umman', 'suudi arabistan',
        'iran', 'israil', 'kudus', 'urdun', 'lubnan', 'hindistan', 'nepal', 'sri lanka',
        'tayland', 'vietnam', 'kamboca', 'kamboçya', 'laos', 'malezya', 'endonezya',
        'singapur', 'filipinler', 'japonya', 'kore', 'cin', 'tayvan', 'hong kong',
        'maldivler', 'seysel', 'mauritius', 'abd', 'amerika', 'kanada', 'meksika', 'kuba',
        'brezilya', 'arjantin', 'peru', 'sili', 'kolombiya', 'avustralya', 'yeni zelanda',
        // Popüler yurt dışı tatil noktaları
        'bali', 'phuket', 'bangkok', 'tokyo', 'kyoto', 'osaka', 'new york', 'newyork',
        'miami', 'las vegas', 'los angeles', 'san francisco', 'mykonos', 'santorini',
        'rodos', 'girit', 'kos', 'midilli', 'sakiz', 'korfu', 'zakintos', 'kibris',
        'girne', 'lefkosa', 'malta', 'sicilya', 'sardinya', 'mallorca', 'ibiza',
        'kanarya', 'madeira', 'balkanlar', 'balkan', 'avrupa', 'uzakdogu', 'uzak dogu',
        'orta avrupa', 'benelux', 'iskandinavya', 'baltik',
    ];

    /** Yurt içi bölge/tatil noktaları — 81 il TurkishCities'ten ayrıca kontrol edilir. */
    public const DOMESTIC_PLACES = [
        'kapadokya', 'ege', 'karadeniz', 'gap', 'guneydogu', 'ic anadolu', 'likya',
        'kusadasi', 'cesme', 'alacati', 'didim', 'alanya', 'side', 'kemer', 'belek',
        'bodrum', 'marmaris', 'fethiye', 'oludeniz', 'gocek', 'datca', 'kas', 'kalkan',
        'olympos', 'cirali', 'amasra', 'safranbolu', 'pamukkale', 'salda', 'sapanca',
        'abant', 'uludag', 'palandoken', 'erciyes', 'kartalkaya', 'ayder', 'uzungol',
        'sirince', 'assos', 'ayvalik', 'cunda', 'bozcaada', 'gokceada', 'urla', 'foca',
        'akyaka', 'igneada', 'kiyikoy',
    ];

    /**
     * Destinasyon yurt dışı mı? true=yurt dışı, false=yurt içi, null=belirlenemedi.
     * Çok parçalı destinasyonlarda ("Barselona, Valencia, Granada") herhangi bir
     * parça yurt dışıysa yurt dışı sayılır (karma rotalar pratikte yurt dışı turudur).
     */
    public static function isInternational(?string $destination): ?bool
    {
        $destination = trim((string) $destination);
        if ($destination === '') {
            return null;
        }

        $normalized = self::normalize($destination);
        $parts = array_filter(array_map('trim', preg_split('/[,\/&+]|\s[-–—]\s/u', $normalized) ?: []));

        $foundDomestic = false;
        foreach ($parts as $part) {
            foreach (self::FOREIGN_PLACES as $place) {
                if (self::partMatches($part, $place)) {
                    return true;
                }
            }

            if (TurkishCities::canonical($part) !== null) {
                $foundDomestic = true;

                continue;
            }
            foreach (self::DOMESTIC_PLACES as $place) {
                if (self::partMatches($part, $place)) {
                    $foundDomestic = true;

                    break;
                }
            }
        }

        return $foundDomestic ? false : null;
    }

    /** Parça içinde yer adını kelime sınırlı arar ("Kuzey İtalya" → italya eşleşir). */
    private static function partMatches(string $normalizedPart, string $place): bool
    {
        $pattern = '/(?<![\p{L}\d])'.preg_quote($place, '/').'(?:\p{L}{0,4})?(?![\p{L}\d])/u';

        return preg_match($pattern, $normalizedPart) === 1;
    }

    private static function normalize(string $text): string
    {
        $text = mb_strtolower($text, 'UTF-8');

        return trim(strtr($text, [
            'ı' => 'i', 'ğ' => 'g', 'ü' => 'u', 'ş' => 's', 'ö' => 'o', 'ç' => 'c', 'i̇' => 'i', 'â' => 'a',
        ]));
    }
}
