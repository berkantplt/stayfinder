<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\Category;
use App\Models\Tour;
use App\Models\TourClick;
use App\Models\TourView;
use App\Support\TurkishCities;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TourController extends Controller
{
    public function index()
    {
        $agency = auth()->user()->agency;

        $tours = $agency
            ->tours()
            ->with('category')
            ->orderByDesc('created_at')
            ->paginate(15);

        $canCreateTours = $agency->legacy_category_access || count($agency->accessibleCategoryIds()) > 0;

        return view('agency.tours.index', compact('tours', 'canCreateTours'));
    }

    public function create()
    {
        $agency = auth()->user()->agency;
        $categories = $this->resolveAgencyCategoryTree($agency);
        $currencyOptions = Tour::supportedCurrencies();
        $canCreateTours = $agency->legacy_category_access || $categories->isNotEmpty();

        return view('agency.tours.create', compact('categories', 'currencyOptions', 'canCreateTours'));
    }

    public function show(Tour $tour)
    {
        $this->authorize($tour);
        $tour->load('agency', 'dates');

        $reviews = $tour->reviews()->with('user')->latest()->get();
        $avgRating = $reviews->avg('rating') ? round($reviews->avg('rating'), 1) : null;
        $clickCount = TourClick::where('tour_id', $tour->id)->count();
        $viewCount = TourView::where('tour_id', $tour->id)->count();

        return view('agency.tours.show', compact('tour', 'reviews', 'avgRating', 'clickCount', 'viewCount'));
    }

    public function store(Request $request)
    {
        $agency = auth()->user()->agency;

        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'title' => 'required|string|max:255',
            'destination' => 'required|string|max:100',
            'departure_city' => ['required', 'string', Rule::in(TurkishCities::all())],
            'stop_cities' => 'nullable|array',
            'stop_cities.*' => ['string', Rule::in(TurkishCities::all())],
            'description' => 'nullable|string',
            'duration_days' => 'required|integer|min:1',
            'currency' => ['required', 'string', Rule::in(array_keys(Tour::supportedCurrencies()))],
            'included' => 'nullable|string',
            'excluded' => 'nullable|string',
            'image' => 'nullable|image|max:5120',
            'tour_url' => 'nullable|url',
            'departure_points' => 'nullable|string',
            'itinerary' => 'nullable|array',
            'itinerary.*.title' => 'nullable|string|max:255',
            'itinerary.*.content' => 'nullable|string',
            'hotel_info' => 'nullable|string',
            'extras' => 'nullable|string',
            'cancellation_policy' => 'nullable|string',
            'guide_info' => 'nullable|string',
            'frequency' => 'nullable|string|max:255',
        ], [
            'departure_city.required' => 'Kalkış şehrini seçin.',
            'departure_city.in' => 'Geçerli bir kalkış şehri seçin.',
        ]);
        $this->ensureAgencyHasCategoryAccess($agency, (int) $validated['category_id']);

        $pricingOptions = $this->pricingOptionsWithDerivedPrices($request);
        $dates = $this->prepareValidatedDatePrices(
            $pricingOptions,
            (array) $request->input('departure_dates', []),
            $request->input('price'),
            (int) $validated['duration_days']
        );

        if ($request->hasFile('image')) {
            $validated['image'] = '/storage/'.$request->file('image')->store('tours', 'public');
        } else {
            unset($validated['image']);
        }

        $validated['stop_cities'] = $this->normalizeStopCities($validated['stop_cities'] ?? null, $validated['departure_city']);
        $validated['agency_id'] = $agency->id;
        $validated['price'] = $this->resolveBasePrice($dates);
        $primaryDate = $this->resolvePrimaryDate($dates);
        $validated['departure_date'] = $primaryDate['departure_date'];
        $validated['return_date'] = $primaryDate['return_date'];
        $validated['tour_url'] = $this->cleanTourUrl($validated['tour_url'] ?? null);
        $validated['itinerary'] = $this->normalizeItinerary($request->input('itinerary'));
        $validated['pricing_blocks'] = $this->buildPricingBlocks($pricingOptions);

        $tour = Tour::create($validated);
        $this->syncTourDates($tour, $dates);

        return redirect()->route('agency.tours.index')
            ->with('success', 'Tur başarıyla eklendi.');
    }

    public function edit(Tour $tour)
    {
        $this->authorize($tour);
        $agency = auth()->user()->agency;
        $categories = $this->resolveAgencyCategoryTree($agency);
        $currencyOptions = Tour::supportedCurrencies();
        $currentCategoryAccessible = $agency->hasCategoryAccess($tour->category_id);

        return view('agency.tours.edit', compact('tour', 'categories', 'currencyOptions', 'currentCategoryAccessible'));
    }

    public function update(Request $request, Tour $tour)
    {
        $this->authorize($tour);
        $agency = auth()->user()->agency;

        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'title' => 'required|string|max:255',
            'destination' => 'required|string|max:100',
            'departure_city' => ['required', 'string', Rule::in(TurkishCities::all())],
            'stop_cities' => 'nullable|array',
            'stop_cities.*' => ['string', Rule::in(TurkishCities::all())],
            'description' => 'nullable|string',
            'duration_days' => 'required|integer|min:1',
            'currency' => ['required', 'string', Rule::in(array_keys(Tour::supportedCurrencies()))],
            'included' => 'nullable|string',
            'excluded' => 'nullable|string',
            'image' => 'nullable|image|max:5120',
            'tour_url' => 'nullable|url',
            'is_active' => 'boolean',
            'departure_points' => 'nullable|string',
            'itinerary' => 'nullable|array',
            'itinerary.*.title' => 'nullable|string|max:255',
            'itinerary.*.content' => 'nullable|string',
            'hotel_info' => 'nullable|string',
            'extras' => 'nullable|string',
            'cancellation_policy' => 'nullable|string',
            'guide_info' => 'nullable|string',
            'frequency' => 'nullable|string|max:255',
        ], [
            'departure_city.required' => 'Kalkış şehrini seçin.',
            'departure_city.in' => 'Geçerli bir kalkış şehri seçin.',
        ]);
        $this->ensureAgencyHasCategoryAccess($agency, (int) $validated['category_id']);
        $validated['stop_cities'] = $this->normalizeStopCities($validated['stop_cities'] ?? null, $validated['departure_city']);

        $pricingOptions = $this->pricingOptionsWithDerivedPrices($request);
        $dates = $this->prepareValidatedDatePrices(
            $pricingOptions,
            (array) $request->input('departure_dates', []),
            $request->input('price'),
            (int) $validated['duration_days']
        );

        if ($request->hasFile('image')) {
            // Delete old uploaded image
            if ($tour->image && str_starts_with($tour->image, '/storage/')) {
                Storage::disk('public')->delete(str_replace('/storage/', '', $tour->image));
            }
            $validated['image'] = '/storage/'.$request->file('image')->store('tours', 'public');
        } else {
            unset($validated['image']);
        }

        $validated['price'] = $this->resolveBasePrice($dates);
        $primaryDate = $this->resolvePrimaryDate($dates);
        $validated['departure_date'] = $primaryDate['departure_date'];
        $validated['return_date'] = $primaryDate['return_date'];
        $validated['tour_url'] = $this->cleanTourUrl($validated['tour_url'] ?? null);
        $validated['itinerary'] = $this->normalizeItinerary($request->input('itinerary'));
        $validated['pricing_blocks'] = $this->buildPricingBlocks($pricingOptions);
        $tour->update($validated);
        $this->syncTourDates($tour, $dates);

        return redirect()->route('agency.tours.index')
            ->with('success', 'Tur güncellendi.');
    }

    /**
     * pricing_options'ı, paket matrisi olan bloklara türetilmiş "başlangıç fiyatı"
     * enjekte ederek döner — böylece prepareValidatedDatePrices (fiyat zorunlu)
     * paket-bazlı bloklarda da çalışır ve tour_dates fiyatları tutarlı olur.
     */
    private function pricingOptionsWithDerivedPrices(Request $request): array
    {
        $options = (array) $request->input('pricing_options', []);

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
    private function buildPricingBlocks(array $options): ?array
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
    private function normalizePackages($raw): array
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
     * Paketlerdeki en düşük yetişkin (çocuk hariç) indirimli fiyat — "başlangıç fiyatı".
     */
    private function minAdultPrice(array $packages): ?float
    {
        $prices = [];
        foreach ($packages as $pkg) {
            foreach (Tour::ADULT_ROOM_TYPES as $type) {
                $new = $pkg['prices'][$type]['new'] ?? null;
                if ($new !== null) {
                    $prices[] = $new;
                }
            }
        }
        // Yetişkin fiyatı yoksa herhangi bir new fiyatına düş
        if ($prices === []) {
            foreach ($packages as $pkg) {
                foreach ($pkg['prices'] as $p) {
                    if (($p['new'] ?? null) !== null) {
                        $prices[] = $p['new'];
                    }
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
     * Durak şehirleri temizler: geçersizleri/yinelenenleri atar, kalkış şehrini
     * çıkarır (durak değil), boşsa null döner.
     *
     * @return array<int, string>|null
     */
    private function normalizeStopCities($input, string $departureCity): ?array
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
    private function normalizeItinerary($input): ?array
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
    private function cleanTourUrl(?string $url): ?string
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

    public function destroy(Tour $tour)
    {
        $this->authorize($tour);
        $tour->delete();

        return redirect()->route('agency.tours.index')
            ->with('success', 'Tur silindi.');
    }

    private function authorize(Tour $tour): void
    {
        if ($tour->agency_id !== auth()->user()->agency_id) {
            abort(403);
        }
    }

    private function ensureAgencyHasCategoryAccess(Agency $agency, int $categoryId): void
    {
        if ($agency->hasCategoryAccess($categoryId)) {
            return;
        }

        throw ValidationException::withMessages([
            'category_id' => 'Bu kategoride tur yayınlamak için önce Kategori Yetkilendirme Merkezi üzerinden aylık yetki satın almalısınız.',
        ]);
    }

    private function resolveAgencyCategoryTree(Agency $agency)
    {
        $accessibleCategoryIds = $agency->accessibleCategoryIds();

        if (empty($accessibleCategoryIds)) {
            return collect();
        }

        return Category::active()
            ->parents()
            ->where(function ($query) use ($accessibleCategoryIds) {
                $query
                    ->whereIn('id', $accessibleCategoryIds)
                    ->orWhereHas('children', function ($childQuery) use ($accessibleCategoryIds) {
                        $childQuery->active()->whereIn('id', $accessibleCategoryIds);
                    });
            })
            ->with([
                'children' => function ($childQuery) use ($accessibleCategoryIds) {
                    $childQuery
                        ->active()
                        ->whereIn('id', $accessibleCategoryIds)
                        ->orderBy('sort_order');
                },
            ])
            ->orderBy('sort_order')
            ->get();
    }

    private function resolveBasePrice(array $dates): float
    {
        return (float) collect($dates)
            ->pluck('price')
            ->map(fn ($price) => (float) $price)
            ->min();
    }

    /**
     * Normalize and validate date+price options.
     * Supports both new pricing_options payload and old fallback payload.
     *
     * @throws ValidationException
     */
    private function prepareValidatedDatePrices(
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

    private function syncTourDates(Tour $tour, array $dates): void
    {
        $tour->dates()->delete();
        $tour->dates()->createMany($dates);
    }

    private function resolvePrimaryDate(array $dates): array
    {
        $today = Carbon::today()->toDateString();
        foreach ($dates as $date) {
            if ($date['departure_date'] >= $today) {
                return $date;
            }
        }

        return $dates[0];
    }
}
