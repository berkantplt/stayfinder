<?php

namespace App\Support;

use App\Models\DestinationProfile;
use Illuminate\Database\Eloquent\Builder;

/**
 * Destinasyon eşleştirmesinin TEK doğru yolu.
 *
 * `tours.destination` serbest metin: "Fethiye" da olabilir "Ölüdeniz, Fethiye" da.
 * Tam eşleşme (`where('destination', $x)`) bu yüzden sonuç kaybediyordu — "Fethiye"
 * seçen kullanıcı "Ölüdeniz, Fethiye" turunu göremiyordu.
 *
 * İKİ TASARIM KARARI, ikisi de bilerek:
 *
 * 1. Collation'a ve SQL LOWER()'a GÜVENİLMEZ. Harf katlaması açık REPLACE
 *    zinciriyle yapılır. Sebebi ölçüldü: PHP mb_strtolower('İstanbul') birleşik
 *    noktalı iki kod noktası üretirken MySQL LOWER() tek baytlı 'i' üretiyor —
 *    ikisi asla eşleşmiyor (aynı tuzak TourMatcher.php:351'de de not edilmiş).
 *    Ayrıca MySQL utf8mb4_unicode_ci 'ı' ile 'i'yi katlamıyor: canlı veride
 *    "Ayvalık" kaydı "ayvalik" aramasıyla bulunamıyordu. Katlamayı kendimiz
 *    yapınca MySQL, MariaDB ve SQLite aynı sonucu veriyor — testler üretimi
 *    gerçekten temsil ediyor.
 *
 * 2. CONCAT KULLANILMAZ. SQLite'ta CONCAT yok (|| var), testler SQLite'ta koşuyor.
 *    Kelime sınırı bu yüzden dört LIKE kalıbıyla kuruluyor (aşağıda). Böylece
 *    "Kas" araması "Kastamonu"yu, "Roma" araması "Romanya"yı getirmiyor.
 */
class DestinationFilter
{
    /** Ayraç → boşluk. Tireye DİKKAT: bkz. splitCities(). */
    private const SEPARATORS = [
        ',' => ' ', ';' => ' ', '/' => ' ', '&' => ' ', '|' => ' ', '>' => ' ',
        '(' => ' ', ')' => ' ',
    ];

    /**
     * Karşılaştırma metni: Türkçe harfler katlanır, ayraçlar boşluğa döner.
     * PHP tarafının SQL tarafıyla birebir aynı sonucu vermesi ŞART.
     */
    public static function normalize(string $text): string
    {
        // DestinationProfile::normalize zaten harf katlama + küçültme yapıyor
        // (İ→i tuzağını da orada çözmüşüz), tekrar yazmıyoruz.
        $normalized = DestinationProfile::normalize(strtr($text, self::SEPARATORS));

        return trim(preg_replace('/\s+/u', ' ', $normalized) ?? '');
    }

    /**
     * Serbest metni tekil şehirlere böler.
     *
     * Tire yalnızca BOŞLUKLU halde ayraç sayılır (" - "). Sebebi: "Bosna-Hersek"
     * gibi bitişik tireli adları ikiye bölmemek. Bu yüzden bozuk yazılmış
     * "CAPRI -POSİTANO-AMALFİ" kaydı tam ayrışmaz — onun çözümü veri temizliği,
     * filtrenin kendini zorlaması değil.
     *
     * @return array<int, string> Ham (normalize edilmemiş) parçalar
     */
    public static function splitCities(string $destination): array
    {
        $parts = preg_split('/\s*[,;\/&|>]\s*|\s+-\s+|\s+ve\s+/u', trim($destination)) ?: [];

        $temiz = [];
        foreach ($parts as $part) {
            $part = trim((string) $part, " \t\n\r\0\x0B-–—");
            if ($part !== '' && mb_strlen($part, 'UTF-8') >= 3) {
                $temiz[] = $part;
            }
        }

        return $temiz;
    }

    /**
     * Kolonu normalize eden SQL ifadesi. Sabit dizeden üretilir, kullanıcı
     * girdisi içermez — aranan değer her zaman bind parametresidir.
     */
    public static function columnExpression(string $column = 'destination'): string
    {
        $expr = $column;

        // Büyük harfli Türkçe karakterler doğrudan küçük ASCII'ye
        // (LOWER()'a bırakılırsa MySQL ile SQLite ayrışır).
        $harfler = [
            'İ' => 'i', 'I' => 'i', 'Ğ' => 'g', 'Ü' => 'u', 'Ş' => 's', 'Ö' => 'o', 'Ç' => 'c',
            'ğ' => 'g', 'ü' => 'u', 'ş' => 's', 'ı' => 'i', 'ö' => 'o', 'ç' => 'c',
        ];

        foreach ($harfler + self::SEPARATORS as $from => $to) {
            $expr = "REPLACE({$expr}, '{$from}', '{$to}')";
        }

        // Türkçe harfler yukarıda katlandı; LOWER'a yalnız düz ASCII kaldı.
        return "LOWER(TRIM({$expr}))";
    }

    /**
     * Kelime sınırlı destinasyon filtresi. Birden fazla şehir VEYA mantığıyla.
     *
     * @param  string|array<int, string>  $cities
     */
    public static function apply(Builder $query, string|array $cities, string $column = 'destination'): Builder
    {
        $temiz = [];
        foreach ((array) $cities as $city) {
            $city = self::normalize((string) $city);
            if ($city !== '') {
                $temiz[] = $city;
            }
        }

        if ($temiz === []) {
            return $query;
        }

        $expr = self::columnExpression($column);

        return $query->where(function ($q) use ($temiz, $expr) {
            foreach (array_unique($temiz) as $city) {
                $kacisli = addcslashes($city, '%_\\');

                // Kelime sınırı, CONCAT'siz: tam eşit / başta / sonda / ortada.
                $q->orWhere(function ($inner) use ($expr, $city, $kacisli) {
                    $inner->whereRaw("{$expr} = ?", [$city])
                        ->orWhereRaw("{$expr} LIKE ?", [$kacisli.' %'])
                        ->orWhereRaw("{$expr} LIKE ?", ['% '.$kacisli])
                        ->orWhereRaw("{$expr} LIKE ?", ['% '.$kacisli.' %']);
                });
            }
        });
    }

    /**
     * Filtre açılır listesi. `DISTINCT destination` YERİNE kullanılır: serbest
     * metinleri şehirlere bölüp normalize anahtarla tekilleştirir, tur sayısıyla
     * döner. Böylece "Kapadokya, Nevşehir" listeden kalkar, yerine "Kapadokya" ve
     * "Nevşehir" ayrı ayrı — ve ikisi de gerçekten seçilebilir olur.
     *
     * @return array<int, array{city: string, count: int}> Tur sayısına göre azalan
     */
    public static function vocabulary(Builder $baseQuery): array
    {
        $sehirler = [];

        foreach ($baseQuery->pluck('destination') as $ham) {
            foreach (self::splitCities((string) $ham) as $sehir) {
                $anahtar = self::normalize($sehir);
                if ($anahtar === '') {
                    continue;
                }

                $sehirler[$anahtar] ??= ['city' => self::displayLabel($sehir), 'count' => 0];
                $sehirler[$anahtar]['count']++;
            }
        }

        uasort($sehirler, fn ($a, $b) => [$b['count'], $a['city']] <=> [$a['count'], $b['city']]);

        return array_values($sehirler);
    }

    /**
     * Listede gösterilecek etiket. Tamamı büyük harfle yazılmış kayıtlar
     * ("NAPOLI") listeyi çirkinleştirdiği için başlık biçimine çekilir.
     * Veriye DOKUNMAZ — yalnız görüntüleme.
     */
    private static function displayLabel(string $city): string
    {
        if (mb_strlen($city, 'UTF-8') < 4 || mb_strtoupper($city, 'UTF-8') !== $city) {
            return $city;
        }

        // Türkçe'de mb_convert_case 'I'yı 'i' yapar (doğrusu 'ı'), bu yüzden
        // önce noktasız/noktalı ayrımını koruyacak şekilde elle çeviriyoruz.
        $kelimeler = preg_split('/(\s+)/u', $city, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];

        $sonuc = '';
        foreach ($kelimeler as $kelime) {
            if (trim($kelime) === '') {
                $sonuc .= $kelime;

                continue;
            }

            $ilk = mb_substr($kelime, 0, 1, 'UTF-8');
            $kalan = mb_substr($kelime, 1, null, 'UTF-8');
            $sonuc .= $ilk.strtr(mb_strtolower($kalan, 'UTF-8'), ['i̇' => 'i']);
        }

        return $sonuc;
    }
}
