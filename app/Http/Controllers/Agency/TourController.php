<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use App\Models\Tour;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TourController extends Controller
{
    public function index()
    {
        $tours = auth()->user()->agency
            ->tours()
            ->orderByDesc('created_at')
            ->paginate(15);

        return view('agency.tours.index', compact('tours'));
    }

    public function create()
    {
        $categories = \App\Models\Category::active()->parents()->with('children')->orderBy('sort_order')->get();
        $currencyOptions = Tour::supportedCurrencies();
        return view('agency.tours.create', compact('categories', 'currencyOptions'));
    }

    public function show(Tour $tour)
    {
        $this->authorize($tour);
        $tour->load('agency', 'dates');

        $reviews    = $tour->reviews()->with('user')->latest()->get();
        $avgRating  = $reviews->avg('rating') ? round($reviews->avg('rating'), 1) : null;
        $clickCount = \App\Models\TourClick::where('tour_id', $tour->id)->count();
        $viewCount  = \App\Models\TourView::where('tour_id', $tour->id)->count();

        return view('agency.tours.show', compact('tour', 'reviews', 'avgRating', 'clickCount', 'viewCount'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'category_id'    => 'nullable|exists:categories,id',
            'title'          => 'required|string|max:255',
            'destination'    => 'required|string|max:100',
            'description'    => 'nullable|string',
            'duration_days'  => 'required|integer|min:1',
            'currency'       => ['required', 'string', Rule::in(array_keys(Tour::supportedCurrencies()))],
            'included'       => 'nullable|string',
            'excluded'       => 'nullable|string',
            'image'          => 'nullable|image|max:5120',
            'tour_url'       => 'nullable|url',
        ]);
        $dates = $this->prepareValidatedDatePrices(
            (array) $request->input('pricing_options', []),
            (array) $request->input('departure_dates', []),
            $request->input('price'),
            (int) $validated['duration_days']
        );

        if ($request->hasFile('image')) {
            $validated['image'] = '/storage/' . $request->file('image')->store('tours', 'public');
        } else {
            unset($validated['image']);
        }

        $validated['agency_id'] = auth()->user()->agency_id;
        $validated['price'] = $this->resolveBasePrice($dates);
        $primaryDate = $this->resolvePrimaryDate($dates);
        $validated['departure_date'] = $primaryDate['departure_date'];
        $validated['return_date'] = $primaryDate['return_date'];

        $tour = Tour::create($validated);
        $this->syncTourDates($tour, $dates);

        return redirect()->route('agency.tours.index')
            ->with('success', 'Tur başarıyla eklendi.');
    }

    public function edit(Tour $tour)
    {
        $this->authorize($tour);
        $categories = \App\Models\Category::active()->parents()->with('children')->orderBy('sort_order')->get();
        $currencyOptions = Tour::supportedCurrencies();
        return view('agency.tours.edit', compact('tour', 'categories', 'currencyOptions'));
    }

    public function update(Request $request, Tour $tour)
    {
        $this->authorize($tour);

        $validated = $request->validate([
            'category_id'    => 'nullable|exists:categories,id',
            'title'          => 'required|string|max:255',
            'destination'    => 'required|string|max:100',
            'description'    => 'nullable|string',
            'duration_days'  => 'required|integer|min:1',
            'currency'       => ['required', 'string', Rule::in(array_keys(Tour::supportedCurrencies()))],
            'included'       => 'nullable|string',
            'excluded'       => 'nullable|string',
            'image'          => 'nullable|image|max:5120',
            'tour_url'       => 'nullable|url',
            'is_active'      => 'boolean',
        ]);
        $dates = $this->prepareValidatedDatePrices(
            (array) $request->input('pricing_options', []),
            (array) $request->input('departure_dates', []),
            $request->input('price'),
            (int) $validated['duration_days']
        );

        if ($request->hasFile('image')) {
            // Delete old uploaded image
            if ($tour->image && str_starts_with($tour->image, '/storage/')) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete(str_replace('/storage/', '', $tour->image));
            }
            $validated['image'] = '/storage/' . $request->file('image')->store('tours', 'public');
        } else {
            unset($validated['image']);
        }

        $validated['price'] = $this->resolveBasePrice($dates);
        $primaryDate = $this->resolvePrimaryDate($dates);
        $validated['departure_date'] = $primaryDate['departure_date'];
        $validated['return_date'] = $primaryDate['return_date'];
        $tour->update($validated);
        $this->syncTourDates($tour, $dates);

        return redirect()->route('agency.tours.index')
            ->with('success', 'Tur güncellendi.');
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

    private function resolveBasePrice(array $dates): float
    {
        return (float) collect($dates)
            ->pluck('price')
            ->map(fn($price) => (float) $price)
            ->min();
    }

    /**
     * Normalize and validate date+price options.
     * Supports both new pricing_options payload and old fallback payload.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    private function prepareValidatedDatePrices(
        array $rawPricingOptions,
        array $fallbackDepartureDates,
        mixed $fallbackPrice,
        int $durationDays
    ): array
    {
        if ($durationDays < 1) {
            throw ValidationException::withMessages([
                'duration_days' => 'Tur süresi en az 1 gün olmalı.',
            ]);
        }

        $hasPricingOptions = collect($rawPricingOptions)->contains(function ($option) {
            if (!is_array($option)) {
                return false;
            }

            $price = trim((string) ($option['price'] ?? ''));
            $dates = array_filter(
                (array) ($option['departure_dates'] ?? []),
                fn($date) => trim((string) $date) !== ''
            );

            return $price !== '' || count($dates) > 0;
        });

        $errors = [];
        $normalized = [];
        $seenDepartureDates = [];

        if ($hasPricingOptions) {
            foreach ($rawPricingOptions as $optionIndex => $option) {
                if (!is_array($option)) {
                    continue;
                }

                $priceRaw = trim((string) ($option['price'] ?? ''));
                $departureDates = array_values(array_filter(
                    (array) ($option['departure_dates'] ?? []),
                    fn($date) => trim((string) $date) !== ''
                ));

                if ($priceRaw === '' && count($departureDates) === 0) {
                    continue;
                }

                if ($priceRaw === '' || !is_numeric($priceRaw) || (float) $priceRaw < 0) {
                    $errors["pricing_options.$optionIndex.price"] = ($optionIndex + 1) . '. fiyat bloğunda geçerli bir fiyat girin.';
                    continue;
                }

                $price = round((float) $priceRaw, 2);
                if (count($departureDates) === 0) {
                    $errors["pricing_options.$optionIndex.departure_dates"] = ($optionIndex + 1) . '. fiyat bloğu için en az 1 gidiş tarihi seçin.';
                    continue;
                }

                foreach ($departureDates as $dateIndex => $rawDeparture) {
                    $keyPrefix = "pricing_options.$optionIndex.departure_dates.$dateIndex";
                    $rowNo = $optionIndex + 1;

                    try {
                        $departureDate = Carbon::parse($rawDeparture)->startOfDay();
                    } catch (\Throwable $e) {
                        $errors[$keyPrefix] = $rowNo . '. fiyat bloğunda geçersiz tarih var.';
                        continue;
                    }

                    $departureYmd = $departureDate->toDateString();
                    if (isset($seenDepartureDates[$departureYmd])) {
                        $errors[$keyPrefix] = $departureYmd . ' tarihi birden fazla fiyat bloğunda seçildi.';
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

            if ($fallbackPrice === null || $fallbackPrice === '' || !is_numeric((string) $fallbackPrice) || (float) $fallbackPrice < 0) {
                throw ValidationException::withMessages([
                    'price' => 'Geçerli bir fiyat girin.',
                ]);
            }

            $fallbackPrice = round((float) $fallbackPrice, 2);

            foreach ($departureDates as $index => $rawDeparture) {
                try {
                    $departureDate = Carbon::parse($rawDeparture)->startOfDay();
                } catch (\Throwable $e) {
                    $errors["departure_dates.$index"] = ($index + 1) . '. gidiş tarihinde geçersiz format var.';
                    continue;
                }

                $departureYmd = $departureDate->toDateString();
                if (isset($seenDepartureDates[$departureYmd])) {
                    $errors["departure_dates.$index"] = $departureYmd . ' tarihi birden fazla kez seçildi.';
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

        if (!empty($errors)) {
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
            ->unique(fn(array $item) => $item['departure_date'])
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
