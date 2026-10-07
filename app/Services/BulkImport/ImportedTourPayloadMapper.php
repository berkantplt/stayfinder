<?php

namespace App\Services\BulkImport;

use App\Models\Agency;
use App\Models\Category;
use App\Models\Tour;
use App\Services\DestinationOriginResolver;
use App\Services\Tours\TourPayloadBuilder;
use App\Support\TurkishCities;
use Illuminate\Validation\ValidationException;

/**
 * TourUrlImporter çıktısını (acentanın panelde "URL'den getir" dediğinde forma
 * dolan JSON) panelin kaydettiği yüke çevirir. Kurallar _import_panel.blade.php
 * içindeki JS ile aynı: her tarih paket (matris) olarak gelir, bloğu olmayan
 * tarihe ilk bloğun matrisi şablon uygulanır, hiç matris yoksa düz başlangıç
 * fiyatı. Sonra TourPayloadBuilder panelle birebir aynı doğrulama/normalizasyonu
 * yapar — komutla gelen tur, elle kaydedilenden ayırt edilemez.
 *
 * Panelde acentanın ELLE seçtiği iki alan burada kuralla doldurulur ve raporda
 * gösterilir: kalkış şehri (içe aktarıcı > URL slug'ı > İstanbul) ve vize
 * (liste satırındaki açık değer > slug ipucu > kategori varsayılanı).
 */
class ImportedTourPayloadMapper
{
    /** Kategori slug'ında geçen anahtar → vize varsayılanı ('0' vizesiz, '1' vizeli, 'kapida'). */
    private const VISA_BY_CATEGORY_KEYWORD = [
        'balkan' => '0',    // Sırbistan, Bosna, Karadağ, Arnavutluk, Kosova, Makedonya: TC vatandaşına vizesiz
        'sharm' => 'kapida',
        'misir' => 'kapida',
        'dubai' => '1',
        'avrupa' => '1',
        'italya' => '1',
        'ispanya' => '1',
        'orta-avrupa' => '1',
        'uzak-dogu' => '1',
        'amerika' => '1',
        'ingiltere' => '1',
    ];

    /** Bundan büyük fiyat (herhangi bir para biriminde) ayrıştırma hatası sayılır. */
    public const MAX_SANE_PRICE = 5_000_000;

    /** Yetişkin fiyatı diğer blokların medyanının bu oranından düşük blok aykırıdır. */
    public const OUTLIER_RATIO = 0.25;

    public function __construct(
        private readonly TourPayloadBuilder $payload,
        private readonly DestinationOriginResolver $origin,
    ) {}

    /**
     * @param  array<string, mixed>  $import  TourUrlImporter::import() sonucu
     * @param  string|null  $visaOverride  liste satırından: '0' | '1' | 'kapida' | null
     * @return array{attributes: array<string, mixed>, dates: array<int, array{departure_date: string, return_date: string, price: float}>, gallery_urls: string[], notes: string[]}
     *
     * @throws BulkImportException
     */
    public function map(array $import, string $sourceUrl, Agency $agency, Category $category, ?string $visaOverride = null): array
    {
        $notes = [];

        $title = mb_substr(trim((string) ($import['title'] ?? '')), 0, 255);
        if ($title === '') {
            throw new BulkImportException('Başlık çıkarılamadı.');
        }

        $destination = mb_substr(trim((string) ($import['destination'] ?? '')), 0, 100);
        if ($destination === '') {
            throw new BulkImportException('Destinasyon çıkarılamadı.');
        }

        $durationNights = $this->intOrNull($import['duration_nights'] ?? null, 0, 255);
        $durationDays = $this->resolveDurationDays($import['duration_days'] ?? null, $durationNights, $title);
        if ($durationDays === null) {
            throw new BulkImportException('Tur süresi (gün) çıkarılamadı.');
        }

        $transport = (string) ($import['transport_type'] ?? '');
        $transport = array_key_exists($transport, Tour::TRANSPORT_TYPES) ? $transport : null;

        $currency = strtoupper(trim((string) ($import['currency'] ?? 'TRY')));
        if (! array_key_exists($currency, Tour::supportedCurrencies())) {
            $notes[] = "para birimi '{$currency}' tanınmadı → TRY";
            $currency = 'TRY';
        }

        [$departureCity, $cityNote] = $this->resolveDepartureCity($import['departure_city'] ?? null, $sourceUrl);
        if ($cityNote !== null) {
            $notes[] = $cityNote;
        }

        $options = $this->buildPricingOptions($import, $notes);
        if ($options === []) {
            throw new BulkImportException('Geçerli fiyat bloğu kalmadı (hepsi ekstra tur tablosu / aykırı fiyat).');
        }
        $options = $this->payload->pricingOptionsWithDerivedPrices($options);

        try {
            $dates = $this->payload->prepareValidatedDatePrices($options, [], null, $durationDays);
        } catch (ValidationException $e) {
            throw new BulkImportException('Tarih/fiyat: '.implode(' ', array_map(fn ($m) => implode(' ', (array) $m), $e->errors())));
        }

        $this->assertSanePrices($dates, $options);

        $international = $this->origin->isInternational($destination);
        $visa = $this->resolveVisa($visaOverride, $sourceUrl.' '.$title, $category, $international);
        $notes[] = 'vize: '.($visa === null ? 'belirsiz' : ['0' => 'vizesiz', '1' => 'vizeli', 'kapida' => 'kapıda'][$visa])
            .($visaOverride !== null ? ' (listeden)' : '');
        [$requiresVisa, $visaOnArrival] = $this->payload->visaFlags($visa);

        $primary = $this->payload->resolvePrimaryDate($dates);

        $attributes = [
            'agency_id' => $agency->id,
            'category_id' => $category->id,
            'title' => $title,
            'destination' => $destination,
            'description' => $this->textOrNull($import['description'] ?? null),
            'duration_days' => $durationDays,
            'duration_nights' => $durationNights,
            'transport_type' => $transport,
            'currency' => $currency,
            'included' => $this->textOrNull($import['included'] ?? null),
            'excluded' => $this->textOrNull($import['excluded'] ?? null),
            'tour_url' => $this->payload->cleanTourUrl($sourceUrl),
            'departure_points' => $this->textOrNull($import['departure_points'] ?? null),
            'departure_city' => $departureCity,
            'stop_cities' => $this->payload->normalizeStopCities($import['stop_cities'] ?? null, $departureCity),
            'itinerary' => $this->payload->normalizeItinerary($import['itinerary'] ?? null),
            'hotel_info' => $this->textOrNull($import['hotel_info'] ?? null),
            'extras' => $this->textOrNull($import['extras'] ?? null),
            'cancellation_policy' => $this->textOrNull($import['cancellation_policy'] ?? null),
            'guide_info' => $this->textOrNull($import['guide_info'] ?? null),
            'frequency' => $this->textOrNull($import['frequency'] ?? null, 255),
            'requires_visa' => $requiresVisa,
            'visa_on_arrival' => $visaOnArrival,
            'price' => $this->payload->resolveBasePrice($dates),
            'departure_date' => $primary['departure_date'],
            'return_date' => $primary['return_date'],
            'pricing_blocks' => $this->payload->buildPricingBlocks($options),
            'is_active' => true,
        ];
        if ($international !== null) {
            $attributes['is_international'] = $international;
        }

        $gallery = array_values(array_filter(array_map(
            fn ($u) => trim((string) $u),
            (array) ($import['image_urls'] ?? [])
        ), fn (string $u) => $u !== ''));

        return [
            'attributes' => $attributes,
            'dates' => $dates,
            'gallery_urls' => $gallery,
            'notes' => $notes,
        ];
    }

    /**
     * pricing_blocks + departure_dates + price → pricing_options (form yükü).
     * Aynı matrisi taşıyan tarihler tek bloğa toplanır (tour_dates ve kapak
     * fiyatı değişmez; pricing_blocks daha derli toplu saklanır).
     *
     * @return array<int, array{price: string|float, departure_dates: string[], packages: array}>
     */
    private function buildPricingOptions(array $import, array &$notes = []): array
    {
        $blockByDate = [];
        $artifactDates = [];
        foreach ((array) ($import['pricing_blocks'] ?? []) as $block) {
            if (! is_array($block)) {
                continue;
            }
            $raw = is_array($block['packages'] ?? null) ? $block['packages'] : [];
            // "Ekstra Tur ve Aktiviteler" gibi fiyat tabloları otel paketi değildir;
            // içe aktarıcı bunları bazen tek tarihli blok sanıyor (Malitur GAP: 850 TL
            // kapak fiyatı oldu). Böyle paketler atılır; blok tümüyle bunlardan
            // oluşuyorsa tarihi de uydurmadır → kalkış listesine girmez.
            $packages = array_values(array_filter($raw, fn ($pkg) => ! $this->isExtrasPackage($pkg)));
            $onlyExtras = $raw !== [] && $packages === [];
            foreach ((array) ($block['dates'] ?? []) as $date) {
                $date = trim((string) $date);
                if ($date === '') {
                    continue;
                }
                if ($onlyExtras) {
                    $artifactDates[$date] = true;

                    continue;
                }
                $blockByDate[$date] = $packages;
            }
        }
        foreach (array_keys($artifactDates) as $date) {
            if (! isset($blockByDate[$date])) {
                $notes[] = "{$date}: yalnız ekstra tur/aktivite fiyatı içeren blok atıldı";
            }
        }

        $allDates = [];
        foreach ((array) ($import['departure_dates'] ?? []) as $date) {
            $date = trim((string) $date);
            if ($date !== '' && ! (isset($artifactDates[$date]) && ! isset($blockByDate[$date]))) {
                $allDates[$date] = true;
            }
        }
        foreach (array_keys($blockByDate) as $date) {
            $allDates[$date] = true;
        }
        $allDates = array_keys($allDates);
        sort($allDates);

        if ($allDates === []) {
            throw new BulkImportException('Sayfada doğrulanabilir kalkış tarihi bulunamadı.');
        }

        // Şablon: ilk blok tarihinin matrisi (JS ile aynı: tüm tarihler paket gelir)
        $template = null;
        $blockDates = array_keys($blockByDate);
        sort($blockDates);
        foreach ($blockDates as $d) {
            if ($blockByDate[$d] !== []) {
                $template = $blockByDate[$d];
                break;
            }
        }

        $startPrice = $import['price'] ?? null;
        $startPrice = ($startPrice === null || $startPrice === '' || ! is_numeric($startPrice)) ? null : (float) $startPrice;

        $groups = [];
        foreach ($allDates as $date) {
            $packages = ($blockByDate[$date] ?? []) !== [] ? $blockByDate[$date] : ($template ?? []);
            $converted = $this->convertPackages($packages);

            if ($converted === []) {
                if ($startPrice === null) {
                    throw new BulkImportException("Fiyat çıkarılamadı ({$date} tarihi için ne matris ne başlangıç fiyatı var).");
                }
                $key = 'flat';
                $price = $startPrice;
            } else {
                $key = md5(json_encode($converted));
                // JS'te paketli satırın fiyatı boş bırakılır → türetilen kazanır.
                // Panelin "son çare herhangi bir fiyat" kuralı (minAdultPrice) komutta
                // BİLEREK kullanılmıyor: yalnız çocuk/bebek satırı olan blok (Malitur
                // GAP vakası: 850 TL bebek fiyatı kapak fiyatı oldu) için sayfanın
                // başlangıç fiyatına düşülür; o da yoksa satır düşer (buildPricing
                // sonrası doğrulama "geçerli fiyat" hatasıyla raporlar).
                $adult = $this->minAdultOnlyPrice($this->payload->normalizePackages($converted));
                $price = $adult !== null ? '' : ($startPrice ?? '');
            }

            if (! isset($groups[$key])) {
                $groups[$key] = ['price' => $price, 'departure_dates' => [], 'packages' => $converted, '_adult' => $adult ?? null];
            }
            $groups[$key]['departure_dates'][] = $date;
        }

        $groups = $this->dropOutlierGroups(array_values($groups), $notes);

        return array_map(function (array $g) {
            unset($g['_adult']);

            return $g;
        }, $groups);
    }

    /**
     * Aykırı fiyat bloğu: yetişkin fiyatı diğer blokların medyanının dörtte
     * birinden düşükse ayrıştırma artığıdır (ekstra/opsiyonel tablo, bebek satırı
     * vb.) — tarihleriyle birlikte düşer, not bırakır. Tek blok varsa kıyas yok.
     *
     * @param  array<int, array<string, mixed>>  $groups
     * @return array<int, array<string, mixed>>
     */
    private function dropOutlierGroups(array $groups, array &$notes): array
    {
        $priced = array_values(array_filter($groups, fn ($g) => ($g['_adult'] ?? null) !== null));
        if (count($priced) < 2) {
            return $groups;
        }

        $kept = [];
        foreach ($groups as $i => $group) {
            $adult = $group['_adult'] ?? null;
            if ($adult === null) {
                $kept[] = $group;

                continue;
            }
            $others = [];
            foreach ($priced as $other) {
                if ($other !== $group) {
                    $others[] = (float) $other['_adult'];
                }
            }
            if ($others === []) {
                $kept[] = $group;

                continue;
            }
            sort($others);
            $mid = intdiv(count($others), 2);
            $median = count($others) % 2 === 1 ? $others[$mid] : ($others[$mid - 1] + $others[$mid]) / 2;

            if ($median > 0 && $adult < $median * self::OUTLIER_RATIO) {
                $notes[] = sprintf('aykırı fiyat bloğu atıldı (%s, medyan %s): %s',
                    number_format($adult, 0, ',', '.'), number_format($median, 0, ',', '.'), implode(', ', $group['departure_dates']));

                continue;
            }
            $kept[] = $group;
        }

        return $kept;
    }

    /** Otel adı "ekstra tur", "aktivite", "opsiyonel" diyorsa fiyat tablosu paket değildir. */
    private function isExtrasPackage(mixed $pkg): bool
    {
        if (! is_array($pkg)) {
            return true;
        }
        $hotel = mb_strtolower((string) ($pkg['hotel'] ?? ''), 'UTF-8');
        $hotel = str_replace(['ı', 'İ'], 'i', $hotel);

        return (bool) preg_match('/ekstra|extra|aktivite|opsiyonel|opsiyon/u', $hotel);
    }

    /**
     * Yalnız YETİŞKİN oda tiplerinden (double_pp > single > extra_bed) en düşük
     * indirimli fiyat; hiçbiri yoksa null. TourPayloadBuilder::minAdultPrice'ın
     * son-çare dalı (herhangi bir fiyat) burada yok.
     */
    private function minAdultOnlyPrice(array $packages): ?float
    {
        foreach (Tour::ADULT_ROOM_TYPES as $type) {
            $prices = [];
            foreach ($packages as $pkg) {
                $new = $pkg['prices'][$type]['new'] ?? null;
                if ($new !== null) {
                    $prices[] = (float) $new;
                }
            }
            if ($prices !== []) {
                return min($prices);
            }
        }

        return null;
    }

    /**
     * Saklanan biçim {hotel, prices:{type:{old,new,note}}} → form biçimi {hotel, type:{old,new,note}}.
     *
     * @return array<int, array<string, mixed>>
     */
    private function convertPackages(array $packages): array
    {
        $out = [];
        foreach ($packages as $pkg) {
            if (! is_array($pkg)) {
                continue;
            }
            $row = ['hotel' => trim((string) ($pkg['hotel'] ?? ''))];
            $prices = is_array($pkg['prices'] ?? null) ? $pkg['prices'] : [];
            foreach (array_keys(Tour::ROOM_TYPES) as $type) {
                $cell = $prices[$type] ?? null;
                if (! is_array($cell)) {
                    continue;
                }
                $row[$type] = [
                    'old' => $cell['old'] ?? null,
                    'new' => $cell['new'] ?? null,
                    'note' => $cell['note'] ?? null,
                ];
            }
            $out[] = $row;
        }

        return $out;
    }

    /**
     * Fiyat sağlamlık kapısı: tours.price DECIMAL(10,2) — üstü zaten DB'de patlar,
     * ama anlamsız büyük fiyat çoğunlukla ayrıştırma hatasıdır (çift para birimli
     * sayfada "559,00 EUR (53.105 TRY)" → "5590053105" birleşmesi, Malitur vakası).
     * Böyle bir satırı kaydetmek yerine açık gerekçeyle düşürürüz; panelde acenta
     * aynı değeri görür ve elle düzeltirdi, komutta düzeltecek göz yok.
     */
    private function assertSanePrices(array $dates, array $options): void
    {
        $values = collect($dates)->pluck('price')->map(fn ($p) => (float) $p);
        foreach ($options as $option) {
            foreach ((array) ($option['packages'] ?? []) as $pkg) {
                foreach (array_keys(Tour::ROOM_TYPES) as $type) {
                    foreach (['old', 'new'] as $k) {
                        $v = $pkg[$type][$k] ?? null;
                        if ($v !== null && $v !== '' && is_numeric($v)) {
                            $values->push((float) $v);
                        }
                    }
                }
            }
        }

        $max = $values->max();
        if ($max !== null && $max > self::MAX_SANE_PRICE) {
            throw new BulkImportException(sprintf(
                'Fiyat anormal (%s): sayfa fiyatı iki para biriminde gösteriyor olabilir (ör. "559,00 EUR (53.105 TRY)"), ayrıştırma birleştirmiş — satır kaydedilmedi.',
                number_format($max, 0, ',', '.')
            ));
        }
    }

    private function resolveDurationDays(mixed $days, ?int $nights, string $title): ?int
    {
        $d = $this->intOrNull($days, 1, 365);
        if ($d !== null) {
            return $d;
        }
        if ($nights !== null) {
            return $nights + 1;
        }
        if (preg_match('/(\d{1,3})\s*gece/iu', $title, $m)) {
            return (int) $m[1] + 1;
        }
        if (preg_match('/(\d{1,3})\s*g[üu]n/iu', $title, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    /**
     * Kalkış şehri: içe aktarıcıdan geleni il adına eşle; yoksa URL slug'ındaki
     * "ankara-cikisli" / "izmirden" gibi kalıpları dene; o da yoksa İstanbul.
     *
     * @return array{0: string, 1: ?string}
     */
    private function resolveDepartureCity(mixed $imported, string $sourceUrl): array
    {
        $canonical = TurkishCities::canonical(is_string($imported) ? $imported : null);
        if ($canonical !== null) {
            return [$canonical, null];
        }

        $slug = strtolower((string) parse_url($sourceUrl, PHP_URL_PATH));
        if (preg_match('/(?:^|[\/-])([a-z]+)-(?:cikisli|kalkisli|hareketli|cikis)(?:[\/-]|$)/', $slug, $m)) {
            $city = TurkishCities::canonical($m[1]);
            if ($city !== null) {
                return [$city, "kalkış şehri URL'den: {$city}"];
            }
        }
        if (preg_match('/(?:^|[\/-])([a-z]+?)(?:dan|den|tan|ten)-/', $slug, $m)) {
            $city = TurkishCities::canonical($m[1]);
            if ($city !== null) {
                return [$city, "kalkış şehri URL'den: {$city}"];
            }
        }

        return ['İstanbul', 'kalkış şehri bulunamadı → İstanbul varsayıldı'];
    }

    /**
     * '0' vizesiz, '1' vizeli, 'kapida' kapıda vize, null belirsiz.
     * Öncelik: liste satırı > slug/başlık ipucu ("vizesiz", "vize dahil") >
     * yurt içi (vizesiz) > kategori anahtar kelimesi > yurt dışı belirsiz (vizeli sayılır).
     */
    private function resolveVisa(?string $override, string $hintText, Category $category, ?bool $international): ?string
    {
        $override = $override !== null ? strtolower(trim($override)) : null;
        if (in_array($override, ['0', '1', 'kapida'], true)) {
            return $override;
        }
        if ($override !== null && $override !== '') {
            throw new BulkImportException("Vize değeri '0', '1' veya 'kapida' olmalı: '{$override}'.");
        }

        $hint = mb_strtolower($hintText, 'UTF-8');
        $hint = str_replace(['ı', 'İ'], ['i', 'i'], $hint);
        if (preg_match('/vizesiz|vize-siz|vize siz/u', $hint)) {
            return '0';
        }
        if (preg_match('/vize-dahil|vize dahil|vizeli/u', $hint)) {
            return '1';
        }

        if ($international === false) {
            return '0';
        }

        $slug = strtolower((string) $category->slug);
        foreach (self::VISA_BY_CATEGORY_KEYWORD as $keyword => $visa) {
            if (str_contains($slug, $keyword)) {
                return $visa;
            }
        }

        return $international === true ? '1' : null;
    }

    private function intOrNull(mixed $value, int $min, int $max): ?int
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }
        $int = (int) $value;

        return ($int >= $min && $int <= $max) ? $int : null;
    }

    private function textOrNull(mixed $value, ?int $max = null): ?string
    {
        if (is_array($value)) {
            $value = implode("\n", array_map('strval', $value));
        }
        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }

        return $max !== null ? mb_substr($text, 0, $max) : $text;
    }
}
