<?php

namespace App\Support;

/**
 * "Deniz tatili yeri mi?" — KÜRATÖRLÜ beyaz liste.
 *
 * Bilerek coğrafi bir çıkarım DEĞİL. "Kıyısı var mı" diye sorsaydık Antarktika,
 * Rovaniemi, Oslo hepsi geçerdi ve "Fethiye gibi bir yer" isteyene kutup turu
 * önerirdik. Buradaki soru "denize giriliyor mu, tatil oraya bunun için mi
 * yapılıyor" — bu türetilebilir bir özellik değil, bilgi. O yüzden liste.
 *
 * Listede olmayan yer otomatik olarak "deniz tatili değil" sayılır; yanlış
 * pozitif üretmesi yapısal olarak imkânsızdır. Kapsam eksiği ise TourMatcher'da
 * iki kaynakla telafi edilir: destinasyon profilindeki "beach" karakter etiketi
 * ve gevşetme zinciri (yeterli aday çıkmazsa şart düşer). Kullanan yer:
 * TourMatcher'ın sert filtresi.
 */
class SeaHolidayDestinations
{
    /**
     * Normalize edilmiş (ASCII'ye katlanmış) yer adları.
     *
     * İl adları yalnız kıyısı tatilin ASIL sebebi olanlarda: Muğla/Antalya var,
     * Balıkesir yok (Ayvalık ayrı yazılı) — çünkü "Balıkesir turu" pekâlâ iç
     * kesim turu olabilir.
     */
    public const PLACES = [
        // Ege–Akdeniz kıyısı (Türkiye)
        'mugla', 'fethiye', 'oludeniz', 'olu deniz', 'kas', 'kalkan', 'patara', 'kaputas',
        'datca', 'marmaris', 'icmeler', 'turunc', 'gocek', 'dalyan', 'sarigerme', 'akyaka',
        'bozburun', 'selimiye', 'bodrum', 'gumusluk', 'turgutreis', 'yalikavak',
        'izmir', 'cesme', 'alacati', 'urla', 'sigacik', 'seferihisar', 'foca', 'ozdere',
        'gumuldur', 'dikili', 'aydin', 'kusadasi', 'didim', 'altinkum',
        'ayvalik', 'cunda', 'sarimsakli', 'assos', 'bozcaada', 'gokceada', 'akcay',
        'antalya', 'kemer', 'olympos', 'cirali', 'adrasan', 'side', 'manavgat', 'belek',
        'alanya', 'finike', 'demre', 'kale', 'kalkan', 'konyaalti', 'lara',
        'mersin', 'kizkalesi', 'silifke', 'iskenderun', 'samandag',
        // Bölge adları — "Ege turu" pratikte kıyı turu demek
        'ege', 'likya', 'akdeniz kiyisi', 'turkuaz koylar',
        // Yurt dışı deniz tatili noktaları
        'kibris', 'girne', 'rodos', 'girit', 'kos', 'midilli', 'sakiz', 'korfu', 'zakintos',
        'santorini', 'mykonos', 'paros', 'naksos', 'malta', 'sicilya', 'sardinya',
        'mallorca', 'ibiza', 'menorca', 'kanarya', 'madeira', 'amalfi', 'capri',
        'dubrovnik', 'budva', 'saranda', 'ksamil',
        'maldivler', 'seysel', 'mauritius', 'zanzibar', 'bali', 'phuket', 'samui',
        'sarm el seyh', 'hurgada', 'marsa alam', 'aqaba', 'kizildeniz',
        'punta cana', 'kanku', 'cancun', 'bahamalar', 'kuba varadero',
    ];

    /** Deniz tatili sayılmayan aylar için eşik: bu sıcaklığın altı "denize girilmez". */
    public const MIN_DENIZ_SICAKLIGI = 18;

    /** İklim verisindeki "denize girilmez" nitelemeleri (LLM sözlüğü Türkçe). */
    public const SOGUK_KOSULLAR = ['soğuk', 'karlı'];

    /**
     * Destinasyon metni deniz tatili yeri mi? Çok parçalı destinasyonda
     * ("Fethiye, Ölüdeniz, Dalyan") parçalardan BİRİ yetiyor.
     */
    public static function matches(?string $destination): bool
    {
        foreach (self::parts($destination) as $part) {
            foreach (self::PLACES as $place) {
                if (self::partMatches($part, $place)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Destinasyon metnini normalize edilmiş parçalara böler — çağıran taraf
     * (profil aramaları) aynı bölmeyi kullanabilsin.
     *
     * @return array<int, string>
     */
    public static function parts(?string $destination): array
    {
        $destination = trim((string) $destination);
        if ($destination === '') {
            return [];
        }

        $parcalar = preg_split('/\s*[,;\/&+]\s*|\s+ve\s+|\s[-–—]\s/u', self::normalize($destination)) ?: [];

        return array_values(array_filter(array_map('trim', $parcalar), fn ($p) => $p !== ''));
    }

    /**
     * İklim verisi bu ayda denize girilmediğini SÖYLÜYOR mu?
     *
     * Veri yoksa false döner — "bilmiyoruz" ile "soğuk" karıştırılmaz, aksi
     * halde profili henüz zenginleşmemiş her kıyı yeri elenirdi.
     *
     * @param  array<int|string, mixed>|null  $climateByMonth  {1..12: {temp_c, condition}}
     */
    public static function ayDenizeUygunDegilMi(?array $climateByMonth, ?int $ay): bool
    {
        if ($ay === null || ! is_array($climateByMonth)) {
            return false;
        }

        $veri = $climateByMonth[$ay] ?? $climateByMonth[(string) $ay] ?? null;
        if (! is_array($veri)) {
            return false;
        }

        if (isset($veri['temp_c']) && is_numeric($veri['temp_c'])) {
            return (float) $veri['temp_c'] < self::MIN_DENIZ_SICAKLIGI;
        }

        $kosul = mb_strtolower(trim((string) ($veri['condition'] ?? '')), 'UTF-8');

        return $kosul !== '' && in_array($kosul, self::SOGUK_KOSULLAR, true);
    }

    /** Kelime sınırlı, Türkçe ek toleranslı eşleşme ("Fethiye'de" → fethiye). */
    private static function partMatches(string $normalizedPart, string $place): bool
    {
        $pattern = '/(?<![\p{L}\d])'.preg_quote($place, '/').'(?:\p{L}{0,4})?(?![\p{L}\d])/u';

        return preg_match($pattern, $normalizedPart) === 1;
    }

    /** DestinationProfile::normalize ile AYNI katlama — profil aramaları eşleşsin. */
    public static function normalize(string $text): string
    {
        $text = strtr(trim($text), [
            'İ' => 'i', 'I' => 'i', 'Ğ' => 'g', 'Ü' => 'u', 'Ş' => 's', 'Ö' => 'o', 'Ç' => 'c',
            'ı' => 'i', 'ğ' => 'g', 'ü' => 'u', 'ş' => 's', 'ö' => 'o', 'ç' => 'c',
        ]);

        return preg_replace('/\s+/', ' ', mb_strtolower($text, 'UTF-8')) ?? $text;
    }
}
