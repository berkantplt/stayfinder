<?php

namespace App\Support;

/**
 * Arama/sohbet tarafının Türkçe metin yardımcıları: aksan/büyük-küçük
 * normalizasyonu ve kelime-sınırlı ifade arama.
 *
 * Arama/sohbet tarafının normalizasyonu — TourUrlImporter::foldTr ve
 * TurkishCities::fold AYRI kalır (import parser'ı noktalama/rakam korur),
 * buraya BAĞLANMAZ.
 */
class TurkishText
{
    public static function normalize(string $text): string
    {
        $normalized = mb_strtolower($text, 'UTF-8');

        if (class_exists(\Normalizer::class)) {
            $decomposed = \Normalizer::normalize($normalized, \Normalizer::FORM_D);
            if (is_string($decomposed)) {
                $normalized = preg_replace('/\p{Mn}+/u', '', $decomposed) ?? $decomposed;
            }
        }

        $normalized = str_replace('i̇', 'i', $normalized);
        $normalized = strtr($normalized, [
            'ı' => 'i',
            'ğ' => 'g',
            'ü' => 'u',
            'ş' => 's',
            'ö' => 'o',
            'ç' => 'c',
        ]);

        return trim(preg_replace('/\s+/u', ' ', $normalized) ?? $normalized);
    }

    /**
     * Normalize edilmiş metinde ifadeyi KELİME SINIRLI arar — "için" içindeki
     * 'cin', "nişanlımla" içindeki 'nisan', "Denizli" içindeki 'deniz' gibi
     * alt-dizi tuzaklarını önler. Türkçe çekim ekleri için sınırlı sonek
     * toleransı vardır ("parise", "italyada" eşleşir; $maxSuffix=0 tam kelime).
     */
    public static function hasWord(string $normalizedHaystack, string $word, int $maxSuffix = 4): bool
    {
        $pattern = '/(?<![\p{L}\d])'.preg_quote($word, '/').'\p{L}{0,'.$maxSuffix.'}(?![\p{L}\d])/u';

        return preg_match($pattern, $normalizedHaystack) === 1;
    }
}
