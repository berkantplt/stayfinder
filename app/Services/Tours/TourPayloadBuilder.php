<?php

namespace App\Services\Tours;

use App\Models\Tour;
use App\Services\TourImage\TourImageService;
use App\Support\TurkishCities;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Tur formu yükünü (pricing_options, gallery, stop_cities, itinerary…) kayda hazır
 * hale getiren TEK yer. Eskiden Agency\TourController'ın private metotlarıydı;
 * toplu içe aktarma komutu (app:bulk-import-tours) aynı sonucu üretebilsin diye
 * servise çıkarıldı. Davranış birebir korundu — panel akışı bu sınıfı çağırır,
 * mantık burada değişirse ikisi birden değişir.
 */
class TourPayloadBuilder
{
    public function __construct(private readonly TourImageService $images) {}

    /**
     * pricing_options'ı, paket matrisi olan bloklara türetilmiş "başlangıç fiyatı"
     * enjekte ederek döner — böylece prepareValidatedDatePrices (fiyat zorunlu)
     * paket-bazlı bloklarda da çalışır ve tour_dates fiyatları tutarlı olur.
     */
    public function pricingOptionsWithDerivedPrices(array $options): array
    {
        foreach ($options as $i => $option) {
            if (! is_array($option) || empty($option['packages'])) {
                continue;
            }
            $derived = $this->minAdultPrice($this->normalizePackages($option['packages']));
            $hasManual = isset($option['price']) && trim((string) $option['price']) !== '';
            if ($derived !== null && ! $hasManual) {
                $options[$i]['price'] = $derived;
            }
        }

        return $options;
    }

    /**
     * Fiyat bloklarını saklanacak yapıya çevirir: [{dates:[Y-m-d], packages:[{hotel, prices:{type:{old,new}}}]}].
     */
    public function buildPricingBlocks(array $options): ?array
    {
        $blocks = [];

        foreach ($options as $option) {
            if (! is_array($option)) {
                continue;
            }

            $packages = $this->normalizePackages($option['packages'] ?? []);

            $dates = [];
            foreach ((array) ($option['departure_dates'] ?? []) as $d) {
                $d = trim((string) $d);
                if ($d === '') {
                    continue;
                }
                try {
                    $dates[] = Carbon::parse($d)->toDateString();
                } catch (\Throwable) {
                    // geçersiz tarih atlanır
                }
            }
            $dates = array_values(array_unique($dates));
            sort($dates);

            // Paket matrisi olmayan blok saklanmaz: tarihler zaten tour_dates'te,
            // tek fiyatlı turlarda pricing_blocks null kalır (gürültü olmaz).
            if ($packages === [] || $dates === []) {
                continue;
            }

            $blocks[] = ['dates' => $dates, 'packages' => $packages];
        }

        return $blocks !== [] ? $blocks : null;
    }

    /**
     * @return array<int, array{hotel: string, prices: array<string, array{old: float|null, new: float|null, note: string|null}>}>
     */
    public function normalizePackages($raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $result = [];
        foreach ($raw as $pkg) {
            if (! is_array($pkg)) {
                continue;
            }
            $hotel = trim((string) ($pkg['hotel'] ?? ''));
            $prices = [];
            foreach (array_keys(Tour::ROOM_TYPES) as $type) {
                $old = $this->priceVal($pkg[$type]['old'] ?? null);
                $new = $this->priceVal($pkg[$type]['new'] ?? null);
                // Fiyat yoksa acenta sebep yazabilir (ör. "Kabul edilmiyor")
                $note = trim((string) ($pkg[$type]['note'] ?? ''));
                $note = mb_substr($note, 0, 60);
                if ($old !== null || $new !== null || $note !== '') {
                    $prices[$type] = [
                        'old' => $old,
                        'new' => $new,
                        'note' => $note !== '' ? $note : null,
                    ];
                }
            }
            if ($hotel === '' && $prices === []) {
                continue;
            }
            $result[] = ['hotel' => $hotel, 'prices' => $prices];
        }

        return $result;
    }

    /**
     * Paketlerdeki en düşük yetişkin indirimli fiyat — "başlangıç/kapak fiyatı".
     * KADEMELİ öncelik (importer'daki minAdultPriceFromBlocks ile aynı kural):
     * önce double_pp, yoksa single, ancak ikisi de yoksa extra_bed. İlave yatak
     * kişi başı oda fiyatından ucuz olduğundan düz min() kapak fiyatını
     * sistematik yanlış (ilave yatak) seçiyordu — Jolly vakası.
     */
    public function minAdultPrice(array $packages): ?float
    {
        foreach (Tour::ADULT_ROOM_TYPES as $type) {
            $prices = [];
            foreach ($packages as $pkg) {
                $new = $pkg['prices'][$type]['new'] ?? null;
                if ($new !== null) {
                    $prices[] = $new;
                }
            }
            if ($prices !== []) {
                return min($prices);
            }
        }

        // Hiç yetişkin fiyatı yoksa son çare: herhangi bir new fiyatı
        $prices = [];
        foreach ($packages as $pkg) {
            foreach ($pkg['prices'] as $p) {
                if (($p['new'] ?? null) !== null) {
                    $prices[] = $p['new'];
                }
            }
        }

        return $prices !== [] ? min($prices) : null;
    }

    private function priceVal($value): ?float
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }
        $num = round((float) $value, 2);

        return $num >= 0 ? $num : null;
    }

    /**
     * gallery[] (sıralı: yerel /storage yolları + uzak görsel URL'leri) → sıralı
     * /storage yolları. Uzak URL'ler indirilir, yereller korunur; en fazla 12,
     * yinelenenler atlanır. Patlayan tek görsel diğerlerini durdurmaz.
     *
     * @return array<int, string>
     */
    public function processGallery(array $items): array
    {
        $paths = [];
        foreach ($items as $item) {
            if (count($paths) >= 12) {
                break;
            }
            $item = trim((string) $item);
            if ($item === '') {
                continue;
            }
            $stored = $this->images->downloadAndStore($item);
            if ($stored !== null && ! in_array($stored, $paths, true)) {
                $paths[] = $stored;
            }
        }

        return $paths;
    }

    /**
     * Durak şehirleri temizler: geçersizleri/yinelenenleri atar, kalkış şehrini
     * çıkarır (durak değil), boşsa null döner.
     *
     * @return array<int, string>|null
     */
    public function normalizeStopCities($input, string $departureCity): ?array
    {
        if (! is_array($input)) {
            return null;
        }

        $cities = [];
        foreach ($input as $city) {
            $canonical = TurkishCities::canonical((string) $city);
            if ($canonical !== null && $canonical !== $departureCity) {
                $cities[$canonical] = true;
            }
        }
        $cities = array_keys($cities);

        return $cities !== [] ? $cities : null;
    }

    /**
     * Gün gün programı temizler: boş günleri atar, [{title, content}] döner.
     */
    public function normalizeItinerary($input): ?array
    {
        if (! is_array($input)) {
            return null;
        }

        $days = [];
        foreach ($input as $day) {
            if (! is_array($day)) {
                continue;
            }
            $title = trim((string) ($day['title'] ?? ''));
            $content = trim((string) ($day['content'] ?? ''));
            if ($title === '' && $content === '') {
                continue;
            }
            $days[] = ['title' => $title, 'content' => $content];
        }

        return $days !== [] ? $days : null;
    }

    /**
     * Tur linkinden takip parametrelerini (gclid, utm_*, _gl vb.) ayıklar —
     * hem temiz URL saklanır hem de aşırı uzun linkler kısalır.
     */
    public function cleanTourUrl(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }

        $parts = parse_url($url);
        if ($parts === false || empty($parts['host'])) {
            return $url;
        }

        $query = [];
        if (! empty($parts['query'])) {
            parse_str($parts['query'], $query);
            $strip = ['_gl', 'gclid', 'gclsrc', 'gbraid', 'wbraid', 'fbclid', 'msclkid', 'dclid', 'yclid', 'mc_eid', 'mc_cid'];
            foreach (array_keys($query) as $key) {
                $lower = strtolower((string) $key);
                if (in_array($lower, $strip, true) || str_starts_with($lower, 'utm_')) {
                    unset($query[$key]);
                }
            }
        }

        $clean = ($parts['scheme'] ?? 'https').'://'.$parts['host'].($parts['path'] ?? '');
        if ($query !== []) {
            $clean .= '?'.http_build_query($query);
        }

        return $clean;
    }

    /**
     * Vize ÜÇ SEÇENEK, iki kolon: [requires_visa, visa_on_arrival]. "Kapıda" da
     * bir vizedir (requires_visa=true), ama yolcu için işi bambaşka olduğundan
     * ayrı bayrakla taşınır. Tanınmayan girdi → [null, null] (form doğrulaması
     * bunu zaten reddeder; savunma amaçlı — silinirse geçersiz girdi sessizce
     * true olur).
     *
     * @return array{0: bool|null, 1: bool|null}
     */
    public function visaFlags(?string $input): array
    {
        return match ($input) {
            '1' => [true, false],
            'kapida' => [true, true],
            '0' => [false, false],
            default => [null, null],
        };
    }

    public function resolveBasePrice(array $dates): float
    {
        return (float) collect($dates)
            ->pluck('price')
            ->map(fn ($price) => (float) $price)
            ->min();
    }

    /** İlk gelecek kalkış; hepsi geçmişse ilk tarih. */
    public function resolvePrimaryDate(array $dates): array
    {
        $today = Carbon::today()->toDateString();
        foreach ($dates as $date) {
            if ($date['departure_date'] >= $today) {
                return $date;
            }
        }

        return $dates[0];
    }

    public function syncTourDates(Tour $tour, array $dates): void
    {
        $tour->dates()->delete();
        $tour->dates()->createMany($dates);
    }

    /**
     * Normalize and validate date+price options.
     * Supports both new pricing_options payload and old fallback payload.
     *
     * @throws ValidationException
     */
    public function prepareValidatedDatePrices(
        array $rawPricingOptions,
        array $fallbackDepartureDates,
        mixed $fallbackPrice,
        int $durationDays
    ): array {
        if ($durationDays < 1) {
            throw ValidationException::withMessages([
                'duration_days' => 'Tur süresi en az 1 gün olmalı.',
            ]);
        }

        $hasPricingOptions = collect($rawPricingOptions)->contains(function ($option) {
            if (! is_array($option)) {
                return false;
            }

            $price = trim((string) ($option['price'] ?? ''));
            $dates = array_filter(
                (array) ($option['departure_dates'] ?? []),
                fn ($date) => trim((string) $date) !== ''
            );

            return $price !== '' || count($dates) > 0;
        });

        $errors = [];
        $normalized = [];
        $seenDepartureDates = [];

        if ($hasPricingOptions) {
            foreach ($rawPricingOptions as $optionIndex => $option) {
                if (! is_array($option)) {
                    continue;
                }

                $priceRaw = trim((string) ($option['price'] ?? ''));
                $departureDates = array_values(array_filter(
                    (array) ($option['departure_dates'] ?? []),
                    fn ($date) => trim((string) $date) !== ''
                ));

                if ($priceRaw === '' && count($departureDates) === 0) {
                    continue;
                }

                if ($priceRaw === '' || ! is_numeric($priceRaw) || (float) $priceRaw < 0) {
                    $errors["pricing_options.$optionIndex.price"] = ($optionIndex + 1).'. fiyat bloğunda geçerli bir fiyat girin.';

                    continue;
                }

                $price = round((float) $priceRaw, 2);
                if (count($departureDates) === 0) {
                    $errors["pricing_options.$optionIndex.departure_dates"] = ($optionIndex + 1).'. fiyat bloğu için en az 1 gidiş tarihi seçin.';

                    continue;
                }

                foreach ($departureDates as $dateIndex => $rawDeparture) {
                    $keyPrefix = "pricing_options.$optionIndex.departure_dates.$dateIndex";
                    $rowNo = $optionIndex + 1;

                    try {
                        $departureDate = Carbon::parse($rawDeparture)->startOfDay();
                    } catch (\Throwable $e) {
                        $errors[$keyPrefix] = $rowNo.'. fiyat bloğunda geçersiz tarih var.';

                        continue;
                    }

                    $departureYmd = $departureDate->toDateString();
                    if (isset($seenDepartureDates[$departureYmd])) {
                        $errors[$keyPrefix] = $departureYmd.' tarihi birden fazla fiyat bloğunda seçildi.';

                        continue;
                    }

                    $seenDepartureDates[$departureYmd] = true;
                    $returnDate = (clone $departureDate)->addDays($durationDays - 1);
                    $normalized[] = [
                        'departure_date' => $departureYmd,
                        'return_date' => $returnDate->toDateString(),
                        'price' => $price,
                    ];
                }
            }
        } else {
            $departureDates = [];
            foreach ($fallbackDepartureDates as $rawDate) {
                $value = trim((string) $rawDate);
                if ($value === '') {
                    continue;
                }

                $departureDates[] = $value;
            }

            if (count($departureDates) === 0) {
                throw ValidationException::withMessages([
                    'pricing_options' => 'En az 1 fiyat bloğu ve tarih seçmelisiniz.',
                ]);
            }

            if ($fallbackPrice === null || $fallbackPrice === '' || ! is_numeric((string) $fallbackPrice) || (float) $fallbackPrice < 0) {
                throw ValidationException::withMessages([
                    'price' => 'Geçerli bir fiyat girin.',
                ]);
            }

            $fallbackPrice = round((float) $fallbackPrice, 2);

            foreach ($departureDates as $index => $rawDeparture) {
                try {
                    $departureDate = Carbon::parse($rawDeparture)->startOfDay();
                } catch (\Throwable $e) {
                    $errors["departure_dates.$index"] = ($index + 1).'. gidiş tarihinde geçersiz format var.';

                    continue;
                }

                $departureYmd = $departureDate->toDateString();
                if (isset($seenDepartureDates[$departureYmd])) {
                    $errors["departure_dates.$index"] = $departureYmd.' tarihi birden fazla kez seçildi.';

                    continue;
                }

                $seenDepartureDates[$departureYmd] = true;
                $returnDate = (clone $departureDate)->addDays($durationDays - 1);
                $normalized[] = [
                    'departure_date' => $departureYmd,
                    'return_date' => $returnDate->toDateString(),
                    'price' => $fallbackPrice,
                ];
            }
        }

        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
        }

        if (count($normalized) === 0) {
            throw ValidationException::withMessages([
                'pricing_options' => 'En az 1 geçerli fiyat ve tarih seçmelisiniz.',
            ]);
        }

        usort($normalized, function (array $a, array $b) {
            if ($a['departure_date'] === $b['departure_date']) {
                return $a['price'] <=> $b['price'];
            }

            return strcmp($a['departure_date'], $b['departure_date']);
        });
        $normalized = collect($normalized)
            ->unique(fn (array $item) => $item['departure_date'])
            ->values()
            ->all();

        return $normalized;
    }
}
