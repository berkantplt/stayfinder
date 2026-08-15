<?php

namespace App\Support;

use App\Models\Tour;

/**
 * Turun kalkış şehrini mevcut alanlardan çıkarır.
 *
 * NEDEN: "İstanbul kalkışlı Kapadokya turları" gibi sorgular rakip
 * araştırmasında en büyük boşluk çıktı — tatilsepeti ve jollytur bu adreslerde
 * 404 veriyor, orta ölçekli acenteler (SSC, Touristica) matrisi kurup 1. sırada
 * çıkıyor. O sayfaları açabilmemizin TEK girdisi bu alan.
 *
 * Kaynaklar güvenilirlikten zayıfa doğru denenir; ilk eşleşme kazanır ve
 * hangi kaynaktan geldiği raporlanır (insan denetimi için).
 *
 * Kural: tahmin yok. Destinasyondan kalkış şehri TÜRETİLMEZ — "Kapadokya turu"
 * İstanbul'dan da Ankara'dan da kalkabilir; uydurulan değer, boş alandan
 * kötüdür çünkü yanlış sayfayı yayına sokar.
 */
class DepartureCityExtractor
{
    /**
     * "İstanbul Kalkışlı", "Ankara Çıkışlı", "İzmir Hareketli" — Türk tur
     * başlıklarının standart kalıpları.
     */
    private const TITLE_PATTERNS = [
        '/([\p{L}\s]{3,25}?)\s*(?:kalkışlı|kalkişli|çıkışlı|cikisli|hareketli)/iu',
        // "Antalya\'dan Günübirlik", "İstanbul\'den hareket"
        '/([\p{L}\s]{3,25}?)[\'’]?(?:dan|den|tan|ten)\s+(?:günübirlik|hareket|kalkış)/iu',
    ];

    /**
     * @return array{city: string, source: string}|null
     */
    public static function extract(Tour $tour): ?array
    {
        foreach (self::sources($tour) as $source => $texts) {
            foreach ((array) $texts as $text) {
                $city = self::fromText((string) $text);
                if ($city !== null) {
                    return ['city' => $city, 'source' => $source];
                }
            }
        }

        return null;
    }

    /**
     * Kaynaklar güvenilirlik sırasıyla.
     *
     * @return array<string, array<int, string>>
     */
    private static function sources(Tour $tour): array
    {
        $itineraryFirstDay = '';
        if (is_array($tour->itinerary) && isset($tour->itinerary[0]) && is_array($tour->itinerary[0])) {
            $itineraryFirstDay = trim(
                ($tour->itinerary[0]['title'] ?? '').' '.($tour->itinerary[0]['content'] ?? '')
            );
        }

        return [
            // 1) Biniş noktaları: acentanın doğrudan girdiği yapısal alan.
            //    "21:00 Yenibosna" → Yenibosna İstanbul'un semti; il eşlemesi
            //    TurkishCities::canonical ile yapılır, semt tutmazsa satır atlanır.
            'departure_points' => self::lines($tour->departure_points),

            // 2) Başlık: "İstanbul Kalkışlı Kapadokya Turu"
            'title' => [(string) $tour->title],

            // 3) Programın ilk günü: "1. Gün: İstanbul – Ankara"
            'itinerary' => [$itineraryFirstDay],

            // 4) Açıklama: en zayıf kaynak, yalnız açık kalıp varsa.
            'description' => [strip_tags((string) $tour->description)],
        ];
    }

    /**
     * Metinden il adı çıkarır. Önce açık kalkış kalıpları, sonra (yalnız
     * yapısal alanlarda) düz il adı araması.
     */
    private static function fromText(string $text): ?string
    {
        $text = trim($text);
        if ($text === '') {
            return null;
        }

        foreach (self::TITLE_PATTERNS as $pattern) {
            if (preg_match($pattern, $text, $m)) {
                // Yakalanan parça "Ucuz İstanbul" gibi fazlalık taşıyabilir;
                // sondan başlayarak il adı aranır.
                if ($city = self::lastCityIn($m[1])) {
                    return $city;
                }
            }
        }

        return null;
    }

    /**
     * Serbest metindeki SON il adını döner ("Ucuz İstanbul" → İstanbul).
     */
    private static function lastCityIn(string $text): ?string
    {
        $words = preg_split('/[\s,\-–—\/]+/u', trim($text)) ?: [];

        // Çok kelimeli iller ("Afyonkarahisar" tek kelime ama
        // "Kahramanmaraş" gibi bileşikler de tek kelime) — önce ikili, sonra tekli.
        for ($size = 2; $size >= 1; $size--) {
            for ($i = count($words) - $size; $i >= 0; $i--) {
                $candidate = implode(' ', array_slice($words, $i, $size));
                if ($city = TurkishCities::canonical($candidate)) {
                    return $city;
                }
            }
        }

        return null;
    }

    /**
     * Yapısal alanda satır satır il adı arar. Burada kalkış kalıbı şartı yok:
     * alanın kendisi zaten "kalkış noktaları" anlamına geliyor.
     *
     * @return array<int, string>
     */
    private static function lines(mixed $value): array
    {
        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        $lines = preg_split('/\r\n|\r|\n/u', $value) ?: [];
        $found = [];

        foreach ($lines as $line) {
            // "21:00 Yenibosna" → saati at, kalanı il olarak dene
            $line = trim(preg_replace('/\d{1,2}[:.]\d{2}/u', '', $line) ?? '');
            if ($line !== '' && ($city = self::lastCityIn($line))) {
                // Satırdan doğrudan il çıktı; kalıp aramasına gerek yok.
                $found[] = $city.' kalkışlı';
            }
        }

        return $found;
    }
}
