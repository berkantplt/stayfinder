<?php

namespace App\Support;

use App\Models\DestinationProfile;

/**
 * Landing sayfasının destinasyon bilgisi bölümleri.
 *
 * KAYNAK: mevcut DestinationProfile tablosu — 55 şehir için zaten üretilmiş
 * (summary, best_months, crowded_months, climate_by_month, vibe_tags,
 * crowd_score, liveliness_score). Üretim maliyeti çoktan ödenmiş bu içerik
 * landing sayfalarında hiç kullanılmıyordu.
 *
 * Yani buradaki hiçbir bölüm YENİ bir LLM çağrısı yapmaz. Rakip H2
 * iskeletindeki "Ne Zaman Gidilir", "Nasıl Bir Yer", "İklim" başlıklarının
 * karşılığı bu tablodan geliyor.
 */
class LandingProfile
{
    private const AYLAR = [
        1 => 'Ocak', 2 => 'Şubat', 3 => 'Mart', 4 => 'Nisan', 5 => 'Mayıs', 6 => 'Haziran',
        7 => 'Temmuz', 8 => 'Ağustos', 9 => 'Eylül', 10 => 'Ekim', 11 => 'Kasım', 12 => 'Aralık',
    ];

    /**
     * Karakter etiketlerinin Türkçe karşılığı. Etiketler İngilizce üretiliyor
     * (LLM şeması öyle); sayfada Türkçe görünmeli.
     *
     * HEPSİ İSİM olmalı — sıfatla ("kültürel") isim ("doğa") karışınca cümle
     * bozuluyordu: "doğa, kültürel, tarihi ve macera bir destinasyon".
     */
    private const VIBE = [
        'cultural' => 'kültür',
        'historical' => 'tarih',
        'nightlife' => 'gece hayatı',
        'shopping' => 'alışveriş',
        'nature' => 'doğa',
        'beach' => 'deniz ve plaj',
        'adventure' => 'macera',
        'romantic' => 'romantizm',
        'family' => 'aile tatili',
        'luxury' => 'lüks konaklama',
        'gastronomy' => 'gastronomi',
        'relaxing' => 'dinlenme',
        'party' => 'eğlence',
        'winter' => 'kış turizmi',
        'ski' => 'kayak',
        'diving' => 'dalış',
        'hiking' => 'doğa yürüyüşü',
        'photography' => 'fotoğrafçılık',
    ];

    /**
     * @return array<string, mixed>|null
     */
    public static function forName(?string $name): ?array
    {
        if ($name === null || trim($name) === '') {
            return null;
        }

        $profile = DestinationProfile::query()
            ->where('normalized_city', DestinationProfile::normalize($name))
            ->first();

        if ($profile === null) {
            return null;
        }

        $bolumler = array_values(array_filter([
            self::ozet($profile, $name),
            self::neZaman($profile, $name),
            self::karakter($profile, $name),
        ]));

        return $bolumler === [] ? null : [
            'profil' => $profile,
            'bolumler' => $bolumler,
        ];
    }

    /**
     * @return array{baslik: string, metin: string}|null
     */
    private static function ozet(DestinationProfile $profile, string $name): ?array
    {
        $summary = trim((string) $profile->summary);

        return $summary === '' ? null : [
            'baslik' => $name.' Nasıl Bir Yer?',
            'metin' => $summary,
        ];
    }

    /**
     * "Ne zaman gidilir" — rakip H2 iskeletinin sabit başlıklarından.
     * En iyi aylar + kalabalık aylar + o ayların sıcaklığı birleştirilir.
     *
     * @return array{baslik: string, metin: string}|null
     */
    private static function neZaman(DestinationProfile $profile, string $name): ?array
    {
        $best = self::aylar($profile->best_months);
        $crowded = self::aylar($profile->crowded_months);

        if ($best === [] && $crowded === []) {
            return null;
        }

        $parcalar = [];

        if ($best !== []) {
            $cumle = $name.' için en uygun aylar '.self::liste($best).'.';

            if ($sicaklik = self::sicaklikOzeti($profile, array_keys($best))) {
                $cumle .= ' Bu dönemde ortalama sıcaklık '.$sicaklik.'.';
            }

            $parcalar[] = $cumle;
        }

        if ($crowded !== []) {
            $parcalar[] = self::liste($crowded).' ayları en yoğun dönem; '
                .'bu aylarda fiyatlar yükselir ve yerler erken dolar.';
        }

        return [
            'baslik' => $name.' Turlarına Ne Zaman Gidilir?',
            'metin' => implode(' ', $parcalar),
        ];
    }

    /**
     * @return array{baslik: string, metin: string}|null
     */
    private static function karakter(DestinationProfile $profile, string $name): ?array
    {
        $etiketler = [];
        foreach ((array) $profile->vibe_tags as $tag) {
            if (isset(self::VIBE[$tag])) {
                $etiketler[] = self::VIBE[$tag];
            }
        }

        if ($etiketler === []) {
            return null;
        }

        $metin = $name.'; '.self::liste(array_slice($etiketler, 0, 5)).' öne çıkan bir destinasyon.';

        // crowd_score 0-1: 0.7 üstü "kalabalık" sayılır. Kullanıcının en çok
        // sorduğu şeylerden biri, veriden cevaplanabiliyor.
        $crowd = (float) $profile->crowd_score;
        if ($crowd > 0) {
            $metin .= $crowd >= 0.7
                ? ' Yoğun sezonda kalabalık olabilir; sakin bir program arıyorsanız omuz sezonu tercih edin.'
                : ($crowd <= 0.4 ? ' Genel olarak sakin bir destinasyon.' : '');
        }

        return [
            'baslik' => $name.' Turları Kimler İçin Uygun?',
            'metin' => trim($metin),
        ];
    }

    /**
     * @param  mixed  $months
     * @return array<int, string> ay numarası => ay adı
     */
    private static function aylar(mixed $months): array
    {
        $out = [];
        foreach ((array) $months as $m) {
            $m = (int) $m;
            if (isset(self::AYLAR[$m])) {
                $out[$m] = self::AYLAR[$m];
            }
        }
        ksort($out);

        return $out;
    }

    /**
     * @param  array<int, int>  $aylar
     */
    private static function sicaklikOzeti(DestinationProfile $profile, array $aylar): ?string
    {
        $iklim = $profile->climate_by_month;
        if (! is_array($iklim) || $aylar === []) {
            return null;
        }

        $dereceler = [];
        foreach ($aylar as $ay) {
            $temp = $iklim[(string) $ay]['temp_c'] ?? $iklim[$ay]['temp_c'] ?? null;
            if (is_numeric($temp)) {
                $dereceler[] = (int) $temp;
            }
        }

        if ($dereceler === []) {
            return null;
        }

        $min = min($dereceler);
        $max = max($dereceler);

        return $min === $max ? "{$min}°C" : "{$min}–{$max}°C";
    }

    /**
     * @param  array<int|string, string>  $items
     */
    private static function liste(array $items): string
    {
        $items = array_values($items);
        $son = array_pop($items);

        return $items === [] ? $son : implode(', ', $items).' ve '.$son;
    }
}
