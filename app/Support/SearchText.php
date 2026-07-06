<?php

namespace App\Support;

/**
 * Arama metni normalizasyonu — hibrit (anahtar kelime + vektör) aramanın
 * ortak katlaması. tours.search_text kolonu ve sorgu tarafı aynı biçimden
 * geçer ki "Yüzme Molalı" ↔ "yuzme molali" birebir eşleşsin.
 */
class SearchText
{
    public static function normalize(string $text): string
    {
        $text = mb_strtolower($text, 'UTF-8');
        $text = strtr($text, [
            'ı' => 'i', 'ğ' => 'g', 'ü' => 'u', 'ş' => 's', 'ö' => 'o', 'ç' => 'c', 'i̇' => 'i', 'â' => 'a', 'î' => 'i', 'û' => 'u',
        ]);
        // Noktalama → boşluk; çoklu boşluk sıkıştır
        $text = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $text) ?? $text;

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    /**
     * Sorgudan anlamlı anahtar kelimeleri çıkarır (2 harften kısa ve dolgu
     * kelimeler elenir) — LIKE tabanlı fallback skorlaması bunları kullanır.
     *
     * @return array<int, string>
     */
    public static function keywords(string $text, int $max = 8): array
    {
        $stop = ['bir', 'bu', 'su', 'da', 'de', 'mi', 'mu', 'ile', 've', 'ya', 'ama', 'icin', 'gibi', 'daha', 'cok',
            'az', 'en', 'ne', 'olsun', 'olmasin', 'istiyorum', 'istiyoruz', 'isterim', 'var', 'yok', 'tur', 'turu', 'turlar'];

        return collect(explode(' ', self::normalize($text)))
            ->filter(fn ($w) => mb_strlen($w) >= 3 && ! in_array($w, $stop, true))
            ->unique()
            ->take($max)
            ->values()
            ->all();
    }
}
