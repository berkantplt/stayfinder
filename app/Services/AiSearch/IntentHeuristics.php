<?php

namespace App\Services\AiSearch;

use App\Models\Tour;
use App\Support\TurkishText;
use Illuminate\Support\Collection;

/**
 * Kullanıcı sorgusundan LLM'siz niyet çıkarımı: ay/sezon, destinasyon,
 * dışlanan destinasyonlar, döviz bütçe çevirimi, doğa/sakinlik/hareketlilik
 * ve yurt içi/dışı sinyalleri. AiSearchController'dan davranış birebir
 * korunarak taşındı; metotlar test edilebilirlik için public.
 */
class IntentHeuristics
{
    public function extractPreferredMonth(array $analysis, string $query): ?int
    {
        $monthFromAnalysis = isset($analysis['preferred_month']) ? (int) $analysis['preferred_month'] : null;
        if ($monthFromAnalysis !== null && $monthFromAnalysis >= 1 && $monthFromAnalysis <= 12) {
            return $monthFromAnalysis;
        }

        $normalized = TurkishText::normalize($query);
        $monthMap = [
            'ocak' => 1,
            'subat' => 2,
            'mart' => 3,
            'nisan' => 4,
            'mayis' => 5,
            'haziran' => 6,
            'temmuz' => 7,
            'agustos' => 8,
            'eylul' => 9,
            'ekim' => 10,
            'kasim' => 11,
            'aralik' => 12,
        ];

        foreach ($monthMap as $name => $value) {
            // Kelime sınırlı: "nişanlımla" 'nisan' sanılmasın; "haziranda" eşleşsin
            if (TurkishText::hasWord($normalized, $name)) {
                return $value;
            }
        }

        // Sezon / göreli tarih ifadeleri (tek ay temsilcisiyle — skorlama tek ay
        // destekliyor; sezon ortası ay seçilir)
        $seasonMap = [
            'yaz tatil' => 7, 'yaz donem' => 7, 'yaz sezon' => 7, 'yaz aylar' => 7, 'yazin' => 7, 'yazlik' => 7,
            'kis tatil' => 1, 'kis donem' => 1, 'kis sezon' => 1, 'kisin' => 1, 'kayak sezon' => 1,
            'ilkbahar' => 4, 'sonbahar' => 10,
            'somestr' => 2, 'somestir' => 2, 'yariyil tatil' => 2, 'ara tatil' => 11,
        ];
        foreach ($seasonMap as $phrase => $value) {
            if (TurkishText::hasWord($normalized, $phrase)) {
                return $value;
            }
        }

        if (TurkishText::hasWord($normalized, 'onumuzdeki ay') || TurkishText::hasWord($normalized, 'gelecek ay')) {
            return (int) now()->addMonth()->month;
        }
        if (TurkishText::hasWord($normalized, 'bu ay')) {
            return (int) now()->month;
        }

        return null;
    }

    public function extractPreferredDestination(array $analysis, string $query): ?string
    {
        $normalizedQuery = TurkishText::normalize($query);
        $fromAnalysis = trim((string) ($analysis['preferred_destination'] ?? ''));
        if ($fromAnalysis !== '') {
            $matched = $this->findKnownDestinationFromText($fromAnalysis);
            if ($matched !== null && ! $this->isDestinationExplicitlyExcludedInText($normalizedQuery, $matched)) {
                return $matched;
            }

            $fallback = trim(preg_replace('/\s+/u', ' ', mb_substr($fromAnalysis, 0, 80)));
            $wordCount = count(array_filter(preg_split('/\s+/u', TurkishText::normalize($fallback)) ?: []));
            if ($fallback !== '' && $wordCount <= 3 && ! $this->isDestinationExplicitlyExcludedInText($normalizedQuery, $fallback)) {
                return $fallback;
            }
        }

        $fromQuery = $this->findKnownDestinationFromText($query);
        if ($fromQuery !== null && ! $this->isDestinationExplicitlyExcludedInText($normalizedQuery, $fromQuery)) {
            return $fromQuery;
        }

        return null;
    }

    public function getKnownDestinations(): Collection
    {
        return cache()->remember('ai_search_known_destinations_v1', now()->addHours(6), function () {
            return Tour::query()
                ->active()
                ->whereHas('agency', fn ($agencyQuery) => $agencyQuery->active())
                ->whereNotNull('destination')
                ->where('destination', '!=', '')
                ->distinct()
                ->pluck('destination')
                ->map(fn ($destination) => trim((string) $destination))
                ->filter()
                ->unique()
                ->sortByDesc(fn ($destination) => mb_strlen($destination, 'UTF-8'))
                ->values();
        });
    }

    /**
     * Sorguda dövizle verilen bütçeyi TL'ye çevirir ("1000 euro" → kur × 1000).
     * Döviz ifadesi yoksa tutar olduğu gibi döner (TL varsayımı).
     */
    public function convertBudgetToTry(int $amount, string $query): int
    {
        $normalized = TurkishText::normalize($query);

        $currency = null;
        if (preg_match('/(?<![\p{L}\d])(euro|eur|avro)(?![\p{L}\d])/u', $normalized) === 1 || str_contains($query, '€')) {
            $currency = 'EUR';
        } elseif (preg_match('/(?<![\p{L}\d])(dolar|usd)(?![\p{L}\d])/u', $normalized) === 1 || str_contains($query, '$')) {
            $currency = 'USD';
        } elseif (preg_match('/(?<![\p{L}\d])(sterlin|pound|gbp)(?![\p{L}\d])/u', $normalized) === 1 || str_contains($query, '£')) {
            $currency = 'GBP';
        }

        if ($currency === null) {
            return $amount;
        }

        $converted = (int) round(\App\Models\CurrencyRate::toTry((float) $amount, $currency));

        return $converted > 0 ? $converted : $amount;
    }

    public function queryMentionsDestination(string $normalizedQuery, string $destination): bool
    {
        $normalizedDestination = TurkishText::normalize($destination);
        if (mb_strlen($normalizedDestination, 'UTF-8') < 3) {
            return false;
        }

        $pattern = '/\b'.preg_quote($normalizedDestination, '/').'\p{L}{0,6}\b/u';
        if (preg_match($pattern, $normalizedQuery) === 1) {
            return true;
        }

        // Çok parçalı destinasyonlar ("Salda Gölü, Pamukkale, Çeşme"): tam dize
        // eşleşmezse anlamlı parçalarından biri kelime olarak geçiyorsa da say.
        // (Eski str_contains fallback'i "kurfalı" içinde 'urfa' gibi alt-dizi
        // tuzaklarına düşüyordu — kelime sınırı korunur.)
        foreach (preg_split('/[\s,\/&+-]+/u', $normalizedDestination) ?: [] as $part) {
            if (mb_strlen($part, 'UTF-8') >= 4
                && preg_match('/\b'.preg_quote($part, '/').'\p{L}{0,6}\b/u', $normalizedQuery) === 1) {
                return true;
            }
        }

        return false;
    }

    public function findKnownDestinationFromText(string $text): ?string
    {
        $normalizedText = TurkishText::normalize($text);
        if ($normalizedText === '') {
            return null;
        }

        foreach ($this->getKnownDestinations() as $destination) {
            if ($this->queryMentionsDestination($normalizedText, $destination)) {
                return $destination;
            }
        }

        // Bulanık eşleşme: yazım hatası toleransı ("kapadokia" → Kapadokya).
        // Yalnızca ≥5 harfli tek kelimelik destinasyonlarda; kısa adlarda
        // (Van, Ege) yanlış pozitif riski yüksek olduğundan kapalı.
        $queryWords = array_values(array_filter(
            explode(' ', $normalizedText),
            fn ($w) => mb_strlen($w) >= 5
        ));
        if (! empty($queryWords)) {
            foreach ($this->getKnownDestinations() as $destination) {
                $normalizedDestination = TurkishText::normalize($destination);
                $len = mb_strlen($normalizedDestination);
                if ($len < 5 || str_contains($normalizedDestination, ' ')) {
                    continue;
                }
                $maxDistance = $len >= 7 ? 2 : 1;
                foreach ($queryWords as $word) {
                    // Türkçe ekler için kelimenin destinasyon uzunluğundaki ön eki karşılaştırılır
                    $prefix = mb_substr($word, 0, $len);
                    if (levenshtein($prefix, $normalizedDestination) <= $maxDistance) {
                        return $destination;
                    }
                }
            }
        }

        return null;
    }

    public function extractExcludedDestinations(array $analysis, string $query): array
    {
        $explicit = collect($this->normalizeDestinationArray($analysis['exclude_destinations'] ?? null))
            ->map(function (string $item) {
                $known = $this->findKnownDestinationFromText($item);

                return $known ?? $item;
            })
            ->filter(fn ($item) => trim((string) $item) !== '')
            ->values()
            ->all();
        $normalizedQuery = TurkishText::normalize($query);
        $detected = [];

        foreach ($this->getKnownDestinations() as $destination) {
            if ($this->isDestinationExplicitlyExcludedInText($normalizedQuery, (string) $destination)) {
                $detected[] = (string) $destination;
            }
        }

        $combined = array_values(array_unique(array_merge($explicit, $detected)));

        return array_values(array_filter($combined, fn ($value) => trim((string) $value) !== ''));
    }

    public function isDestinationExplicitlyExcludedInText(string $normalizedQuery, string $destination): bool
    {
        $normalizedDestination = TurkishText::normalize($destination);
        if ($normalizedDestination === '') {
            return false;
        }

        // Ek toleranslı kalıplar: "İstanbul'u istemiyorum", "İstanbula gitmek
        // istemiyorum" gibi çekimli formlar da yakalansın (apostrof opsiyonel).
        $d = preg_quote($normalizedDestination, '/');
        $patterns = [
            "/{$d}['’]?[dt][ae]n\s+(farkli|baska)/u",
            "/{$d}(?:['’]?\p{L}{0,3})?\s+(yerine|disinda|haric(?:inde)?|istemiyorum|olmasin)/u",
            "/{$d}(?:['’]?\p{L}{0,3})?\s+kadar\s+(kalabalik|yogun)\s+olmasin/u",
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $normalizedQuery) === 1) {
                return true;
            }
        }

        return false;
    }

    public function normalizeDestinationArray(mixed $value): array
    {
        if ($value === null) {
            return [];
        }

        if (is_string($value)) {
            $pieces = preg_split('/[,;]+/u', $value) ?: [];

            return array_values(array_filter(array_map('trim', $pieces)));
        }

        if (is_array($value)) {
            return array_values(array_filter(array_map(
                fn ($item) => trim((string) $item),
                $value
            )));
        }

        return [];
    }

    public function detectNatureIntent(string $query): ?bool
    {
        $text = TurkishText::normalize($query);
        $natureKeywords = ['doga', 'yesil', 'orman', 'yayla', 'dag', 'nehir', 'sakin', 'huzur', 'kafa dinle', 'kalabaliktan uzak'];
        foreach ($natureKeywords as $keyword) {
            if (TurkishText::hasWord($text, $keyword)) {
                return true;
            }
        }

        // Tuzaklı kelimeler: 'gol' golf'ü, 'deniz' Denizli'yi yakalamasın
        if (preg_match('/(?<![\p{L}\d])gol(?!f)\p{L}{0,4}(?![\p{L}\d])/u', $text) === 1) {
            return true;
        }
        if (preg_match('/(?<![\p{L}\d])deniz(?!li(?![\p{L}\d]))\p{L}{0,4}(?![\p{L}\d])/u', $text) === 1) {
            return true;
        }

        return null;
    }

    public function detectEscapeCityIntent(string $query): ?bool
    {
        $text = TurkishText::normalize($query);
        $escapeKeywords = [
            'sehir stresi',
            'sehirden uzak',
            'kalabalik istemiyorum',
            'kalabalik olmasin',
            'kadar kalabalik olmasin',
            'huzurlu',
            'sakin',
            'kafa dinle',
            'gurultuden uzak',
        ];
        foreach ($escapeKeywords as $keyword) {
            if (TurkishText::hasWord($text, $keyword)) {
                return true;
            }
        }

        return null;
    }

    public function detectLivelyIntent(string $query): ?bool
    {
        $text = TurkishText::normalize($query);
        $positiveKeywords = ['hareketli', 'canli', 'eglenceli', 'gece hayati', 'sosyal', 'aktif'];
        $negativeKeywords = ['sakin', 'sessiz', 'huzurlu', 'kafa dinle', 'dingin'];

        foreach ($positiveKeywords as $keyword) {
            if (TurkishText::hasWord($text, $keyword)) {
                return true;
            }
        }

        foreach ($negativeKeywords as $keyword) {
            if (TurkishText::hasWord($text, $keyword)) {
                return false;
            }
        }

        return null;
    }

    public function detectInternationalIntent(string $query): ?bool
    {
        $text = TurkishText::normalize($query);

        // "İstanbul kalkışlı / İzmir'den kalkan / Ankara çıkışlı" ifadelerindeki
        // şehir KALKIŞ şehridir, destinasyon değil — yurt içi/dışı sinyali sayılmasın
        // ("İstanbul kalkışlı Paris turu" yurt içine dönmesin diye metinden düşülür).
        $text = preg_replace(
            "/(?<![\p{L}\d])\p{L}+(?:['’]?(?:dan|den|tan|ten))?\s+(kalkisli|cikisli|kalkis|cikis|hareketli|kalkan)(?![\p{L}\d])/u",
            ' ',
            $text
        ) ?? $text;

        $international = false;
        $domestic = false;

        foreach ([
            'yurt disi', 'yurtdisi', 'avrupa', 'balkan', 'schengen', 'vize',
            'asya', 'amerika', 'afrika', 'orta dogu', 'ortadogu', 'uluslararasi',
        ] as $signal) {
            if (TurkishText::hasWord($text, $signal)) {
                $international = true;
                break;
            }
        }

        foreach (['yurt ici', 'yurt icinde', 'turkiye', 'ulke ici', 'anadolu'] as $signal) {
            if (TurkishText::hasWord($text, $signal)) {
                $domestic = true;
                break;
            }
        }

        // Bilinen yurt dışı şehir/ülke adları. Kısa/tuzaklı adlar ("için"→cin,
        // "balık"→bali, "fasıl"→fas) yalnızca TAM kelime olarak eşleşir.
        if (! $international) {
            foreach (['cin', 'bali', 'fas'] as $place) {
                if (TurkishText::hasWord($text, $place, 0)) {
                    $international = true;
                    break;
                }
            }
        }
        if (! $international) {
            foreach ([
                'paris', 'roma', 'londra', 'amsterdam', 'venedik', 'barselona', 'prag',
                'atina', 'berlin', 'viyana', 'milano', 'floransa', 'budapeste', 'munih',
                'maldivler', 'dubai', 'tayland', 'phuket', 'singapur', 'tokyo',
                'new york', 'newyork', 'miami', 'las vegas', 'los angeles',
                'misir', 'tunus', 'mykonos', 'santorini', 'rodos', 'girit', 'kibris',
                'malta', 'sicilya', 'korfu', 'malezya', 'endonezya', 'vietnam', 'kamboçya',
                'kamboca', 'hindistan', 'japonya', 'rusya', 'gurcistan', 'azerbaycan',
                'fransa', 'italya', 'ispanya', 'almanya', 'ingiltere', 'portekiz', 'norvec',
                'isvec', 'finlandiya', 'hollanda', 'belcika', 'avusturya', 'macaristan',
                'polonya', 'cekya', 'hirvatistan', 'sirbistan', 'romanya', 'bulgaristan',
                'arnavutluk', 'karadag', 'bosna',
            ] as $place) {
                if (TurkishText::hasWord($text, $place)) {
                    $international = true;
                    break;
                }
            }
        }

        // Bilinen yurt içi şehir/destinasyonlar. Ablatif hal ("İstanbul'dan ...")
        // kalkış anlamına gelir → yurt içi sinyali SAYILMAZ ("İstanbul'dan Paris
        // turu" yurt dışıdır). Kısa/tuzaklı adlar (van, side, kars, ağrı) tam kelime.
        if (! $domestic) {
            foreach (['van', 'side', 'kars', 'agri'] as $place) {
                if (TurkishText::hasWord($text, $place, 0)) {
                    $domestic = true;
                    break;
                }
            }
        }
        if (! $domestic) {
            foreach ([
                'istanbul', 'ankara', 'izmir', 'antalya', 'bodrum', 'fethiye', 'marmaris',
                'kapadokya', 'kuşadasi', 'kusadasi', 'cesme', 'çeşme', 'didim', 'alanya',
                'kemer', 'belek', 'bursa', 'eskisehir', 'eskişehir', 'konya',
                'rize', 'trabzon', 'artvin', 'amasra', 'amasya', 'safranbolu',
                'pamukkale', 'mardin', 'gaziantep', 'sanliurfa', 'urfa', 'diyarbakir',
                'erzurum', 'sivas', 'kayseri', 'nevsehir',
                'gocek', 'göcek', 'datca', 'datça', 'olympos', 'oludeniz', 'ölüdeniz',
                'sapanca', 'abant', 'uludag', 'palandoken',
            ] as $place) {
                $pattern = "/(?<![\p{L}\d])".preg_quote($place, '/')."(?!['’]?(?:dan|den|tan|ten)(?![\p{L}\d]))\p{L}{0,4}(?![\p{L}\d])/u";
                if (preg_match($pattern, $text) === 1) {
                    $domestic = true;
                    break;
                }
            }
        }

        // Çelişen sinyaller ("Türkiye'den Avrupa turu", "vize istemiyorum yurt içi"):
        // karar heuristiğin değil GPT'nin — null dön.
        if ($international && $domestic) {
            return null;
        }
        if ($international) {
            return true;
        }
        if ($domestic) {
            return false;
        }

        return null;
    }

    public function toNullableBool(mixed $value): ?bool
    {
        if ($value === null) {
            return null;
        }
        if (is_bool($value)) {
            return $value;
        }

        $normalized = strtolower(trim((string) $value));
        if (in_array($normalized, ['true', '1', 'yes', 'evet'], true)) {
            return true;
        }
        if (in_array($normalized, ['false', '0', 'no', 'hayir', 'hayır'], true)) {
            return false;
        }

        return null;
    }
}
