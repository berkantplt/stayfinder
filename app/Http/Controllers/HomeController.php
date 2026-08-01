<?php

namespace App\Http\Controllers;

use App\Models\Agency;
use App\Models\Banner;
use App\Models\Category;
use App\Models\Destination;
use App\Models\FeaturedCity;
use App\Models\PriceHistory;
use App\Models\Tour;
use App\Models\User;
use App\Services\AiSearch\DestinationKnowledgeService;
use Illuminate\Support\Facades\Cache;

class HomeController extends Controller
{
    /** Filtre barı sayaç cache'i — TourObserver tur değişiminde bunu düşürür. */
    public const FACETS_CACHE_KEY = 'home_filter_facets_v1';

    /** Yatay filtre barındaki gün bantları (etiket => [min, max]). */
    public const DAY_BANDS = [
        '1-3' => [1, 3],
        '4-6' => [4, 6],
        '7-9' => [7, 9],
        '10+' => [10, 999],
    ];

    public function index()
    {
        $query = Tour::with(['agency', 'category'])
            ->active()
            ->whereHas('agency', fn ($q) => $q->active());

        $this->applyFilterBar($query);

        // Sorting
        $sort = request('sort', 'price_asc');
        if ($sort === 'price_desc') {
            $query->orderByDesc('price_try');
        } elseif ($sort === 'date_asc') {
            // Tarihe göre: tarihsiz turlar sona
            $query->orderByRaw('departure_date IS NULL')->orderBy('departure_date');
        } else {
            $query->orderBy('price_try');
        }

        // Canlı sayaç: limit'ten ÖNCE, filtrelenmiş gerçek toplam
        $filteredCount = (clone $query)->count();

        $popularTours = $query->limit(8)->get();

        // Mobil kart rozetleri + CANLI ticker: listedeki turların son 30 gündeki
        // son iki fiyat kaydından düşüş yüzdesi (tur_id => %düşüş)
        $tourDrops = [];
        $histories = PriceHistory::whereIn('tour_id', $popularTours->pluck('id'))
            ->where('created_at', '>=', now()->subDays(30))
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('tour_id');
        foreach ($histories as $tourId => $rows) {
            if ($rows->count() >= 2) {
                $last = (float) $rows[0]->price;
                $prev = (float) $rows[1]->price;
                if ($prev > 0 && $last < $prev) {
                    $tourDrops[$tourId] = (int) round((1 - $last / $prev) * 100);
                }
            }
        }
        $tourDrops = array_filter($tourDrops);

        if (request()->ajax()) {
            return response()->json([
                'html' => view('partials.tour_grid', compact('popularTours', 'tourDrops'))->render(),
                'count' => $filteredCount,
            ]);
        }

        // Mobil hero ticker'ı: en büyük güncel düşüş
        $liveDrop = null;
        if ($tourDrops) {
            arsort($tourDrops);
            $dropTour = $popularTours->firstWhere('id', array_key_first($tourDrops));
            if ($dropTour) {
                $liveDrop = [
                    'name' => $dropTour->destination ?: \Illuminate\Support\Str::limit($dropTour->title, 24, ''),
                    'pct' => $tourDrops[array_key_first($tourDrops)],
                ];
            }
        }

        $travelerCount = User::count();

        // Get destinations from managed table with tour counts
        $destinations = Destination::active()
            ->orderBy('sort_order')
            ->limit(6)
            ->get()
            ->map(function ($dest) {
                $stats = Tour::active()->where('destination', $dest->name);
                $dest->tour_count = $stats->count();
                $dest->min_price = $stats->min('price_try'); // TL-normalize en düşük fiyat

                return $dest;
            })
            ->filter(fn ($d) => $d->tour_count > 0);

        // All active destinations for filter dropdown
        $allDestinations = Tour::active()
            ->whereNotNull('destination')
            ->distinct()
            ->pluck('destination');

        // All active categories (parent → children tree)
        $categories = Category::active()
            ->parents()
            ->with(['children' => fn ($q) => $q->active()->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get();

        $agencyCount = Agency::active()->count();
        $tourCount = Tour::active()->count();

        // Banners
        $banners = Banner::active()->orderBy('sort_order')->get();

        // Recently viewed (from session)
        $recentIds = session()->get('recently_viewed', []);
        $recentlyViewed = collect();
        if (! empty($recentIds)) {
            $recentlyViewed = Tour::with('agency')
                ->active()
                ->whereIn('id', $recentIds)
                ->get()
                ->sortBy(fn ($t) => array_search($t->id, $recentIds))
                ->values();
        }

        // Fetch Featured Cities from Database
        $featuredCities = FeaturedCity::with('images')
            ->active()
            ->orderBy('sort_order')
            ->get()
            ->map(function ($city) {
                return [
                    'name' => $city->name,
                    'country' => $city->country,
                    'link' => $city->link,
                    'images' => $city->images->map(fn ($img) => asset('storage/'.$img->image_path))->toArray(),
                    'count' => Tour::active()->where('destination', $city->name)->count(),
                ];
            })
            // Show cities that have images, even if they have 0 tours currently
            ->filter(fn ($city) => count($city['images']) > 0)
            ->values();

        // Fallback for demo if DB is empty
        if ($featuredCities->isEmpty()) {
            $featuredCities = collect([
                ['name' => 'Paris', 'country' => 'Fransa', 'images' => [asset('images/featured_cities/paris.png')], 'count' => Tour::active()->where('destination', 'Paris')->count()],
            ]);
        }

        $facets = $this->filterBarFacets($categories);
        $specialPeriods = config('special_periods', []);

        return view('home', compact('popularTours', 'destinations', 'allDestinations', 'categories', 'agencyCount', 'tourCount', 'recentlyViewed', 'banners', 'featuredCities', 'tourDrops', 'liveDrop', 'travelerCount', 'filteredCount', 'facets', 'specialPeriods'));
    }

    /**
     * Yatay filtre barı (Etstur uyarlaması): kategori ağacı, destinasyon,
     * ay, özel gün, vize, gün sayısı, kalkış, bütçe, acenta. Tüm parametreler
     * GET'te — URL paylaşılabilir kalır.
     */
    private function applyFilterBar($query): void
    {
        // Kategori: tek seçim, üst kategori çocuklarını kapsar (mevcut davranış)
        if (request('category')) {
            $cat = Category::where('slug', request('category'))->first();
            if ($cat) {
                $catIds = collect([$cat->id])->merge($cat->children()->pluck('id'));
                $query->whereIn('category_id', $catIds);
            }
        }

        // Geriye dönük uyum: eski tekil ?destination= linkleri çalışmaya devam etsin
        if (request('destination')) {
            $query->where('destination', request('destination'));
        }

        // Destinasyon (çoklu, envanter şehirleri): "Paris, Roma" rotası Paris
        // seçiminde de bulunur
        $destinations = array_filter((array) request('destinations'), 'is_string');
        if ($destinations !== []) {
            $query->where(function ($q) use ($destinations) {
                foreach ($destinations as $city) {
                    $q->orWhere('destination', 'like', '%'.$city.'%');
                }
            });
        }

        // Ay(lar): tekil kalkış tarihi VEYA tarih listesinde o aya gelecek kalkış
        $months = array_values(array_filter(array_map('intval', (array) request('months')), fn ($m) => $m >= 1 && $m <= 12));
        if ($months !== []) {
            $today = now()->toDateString();
            $query->where(function ($q) use ($months, $today) {
                $q->where(function ($qq) use ($months, $today) {
                    $qq->whereDate('departure_date', '>=', $today)
                        ->where(function ($m) use ($months) {
                            foreach ($months as $month) {
                                $m->orWhereMonth('departure_date', $month);
                            }
                        });
                })->orWhereHas('dates', function ($d) use ($months, $today) {
                    $d->whereDate('departure_date', '>=', $today)
                        ->where(function ($m) use ($months) {
                            foreach ($months as $month) {
                                $m->orWhereMonth('departure_date', $month);
                            }
                        });
                });
            });
        }

        // Özel gün/dönem: kalkışı aralıkla kesişen turlar
        $special = config('special_periods.'.request('special'));
        if ($special) {
            $query->where(function ($q) use ($special) {
                foreach ($special['ranges'] as [$start, $end]) {
                    $q->orWhereBetween('departure_date', [$start, $end])
                        ->orWhereHas('dates', fn ($d) => $d->whereBetween('departure_date', [$start, $end]));
                }
            });
        }

        // Vize: kaynak destinasyon profilleri (chatbot'la aynı, LLM-zenginleştirilmiş).
        // tours.requires_visa KULLANILMAZ: default false olduğu için "girilmemiş"
        // ile "vizesiz" ayrılamaz — yanlış veri satılmış olurdu. Profili olmayan
        // destinasyon = bilinmiyor, filtre dışı kalır (dürüst davranış).
        $visa = array_values(array_intersect((array) request('visa'), ['vizesiz', 'vizeli']));
        if ($visa !== []) {
            $cities = $this->visaCityLists();
            $wanted = [];
            foreach ($visa as $key) {
                $wanted = array_merge($wanted, $cities[$key]);
            }
            if ($wanted === []) {
                $query->whereRaw('1 = 0'); // profil verisi henüz yoksa uydurma sonuç dönme
            } else {
                $query->where(function ($q) use ($wanted) {
                    foreach ($wanted as $city) {
                        $q->orWhere('destination', 'like', '%'.$city.'%');
                    }
                });
            }
        }

        // Gün sayısı bantları
        $bands = array_intersect((array) request('days'), array_keys(self::DAY_BANDS));
        if ($bands !== []) {
            $query->where(function ($q) use ($bands) {
                foreach ($bands as $band) {
                    [$min, $max] = self::DAY_BANDS[$band];
                    $q->orWhereBetween('duration_days', [$min, $max]);
                }
            });
        }

        // Kalkış noktası: kalkış şehri VEYA yol üstü duraklar
        $departures = array_filter((array) request('departures'), 'is_string');
        if ($departures !== []) {
            $query->where(function ($q) use ($departures) {
                foreach ($departures as $city) {
                    $q->orWhere('departure_city', $city)
                        ->orWhereJsonContains('stop_cities', $city);
                }
            });
        }

        // Bütçe (bin TL, kur-normalize price_try üzerinden)
        $budget = (int) request('budget_max');
        if ($budget > 0) {
            $query->where('price_try', '<=', $budget * 1000);
        }
    }

    /**
     * Vize bilgisi NET olan şehirler (destinasyon profillerinden, 5 dk cache).
     *
     * @return array{vizesiz: array<int, string>, vizeli: array<int, string>}
     */
    private function visaCityLists(): array
    {
        return Cache::remember('home_visa_cities_v1', 300, function () {
            $profiles = \App\Models\DestinationProfile::whereNotNull('requires_visa_for_tr')
                ->get(['city', 'requires_visa_for_tr']);

            return [
                'vizesiz' => $profiles->where('requires_visa_for_tr', false)->pluck('city')->values()->all(),
                'vizeli' => $profiles->where('requires_visa_for_tr', true)->pluck('city')->values()->all(),
            ];
        });
    }

    /**
     * Filtre barındaki count rozetleri (Etstur deseni). Sayılar filtresiz
     * envantere göredir ve 5 dk cache'lenir; canlı sayaç ayrıca AJAX'la döner.
     *
     * @return array{categories: array<int, int>, visa: array{vizesiz: int, vizeli: int}, days: array<string, int>, departures: array<string, int>, destinations: array<string, array{city: string, count: int}>}
     */
    private function filterBarFacets($categories): array
    {
        $facets = Cache::remember(self::FACETS_CACHE_KEY, 300, function () {
            $base = fn () => Tour::query()->active();

            // Kategori başına tur sayısı (üst = kendi + çocukları, PHP'de toplanır)
            $byCategory = $base()->whereNotNull('category_id')
                ->selectRaw('category_id, count(*) as c')
                ->groupBy('category_id')
                ->pluck('c', 'category_id')
                ->all();

            $visaCities = $this->visaCityLists();
            $visaCount = function (array $cities) use ($base) {
                if ($cities === []) {
                    return 0;
                }

                return $base()->where(function ($q) use ($cities) {
                    foreach ($cities as $city) {
                        $q->orWhere('destination', 'like', '%'.$city.'%');
                    }
                })->count();
            };
            $visa = [
                'vizesiz' => $visaCount($visaCities['vizesiz']),
                'vizeli' => $visaCount($visaCities['vizeli']),
            ];

            $days = [];
            foreach (self::DAY_BANDS as $label => [$min, $max]) {
                $days[$label] = $base()->whereBetween('duration_days', [$min, $max])->count();
            }

            // Kalkış noktaları: yolcunun binebildiği HER şehir — kalkış şehri VE
            // yol üstü duraklar. Liste yalnız departure_city'den üretilseydi,
            // sadece durak olarak geçen bir şehir filtrede hiç görünmezdi (oysa
            // filtreleme ikisini de kapsıyor). Yeni tur eklenince yeni şehir
            // kendiliğinden listeye girer; sabit şehir listesi yok.
            //
            // Sayım SQL yerine PHP'de: şehir başına ayrı sorgu yerine tek çekim.
            $departures = [];
            $base()->select(['departure_city', 'stop_cities'])
                ->get()
                ->each(function ($tour) use (&$departures) {
                    $sehirler = collect([$tour->departure_city])
                        ->merge(is_array($tour->stop_cities) ? $tour->stop_cities : [])
                        ->map(fn ($s) => trim((string) $s))
                        ->filter()
                        ->unique();

                    foreach ($sehirler as $sehir) {
                        $departures[$sehir] = ($departures[$sehir] ?? 0) + 1;
                    }
                });
            arsort($departures);
            $departures = array_slice($departures, 0, 15, true);

            return compact('byCategory', 'visa', 'days', 'departures');
        });

        // Üst kategori sayısı = kendi + çocuklarının toplamı
        $categoryCounts = [];
        foreach ($categories as $parent) {
            $sum = $facets['byCategory'][$parent->id] ?? 0;
            foreach ($parent->children as $child) {
                $categoryCounts[$child->id] = $facets['byCategory'][$child->id] ?? 0;
                $sum += $categoryCounts[$child->id];
            }
            $categoryCounts[$parent->id] = $sum;
        }

        // Destinasyon listesi: envanter servisi zaten count'lu + cache'li
        $destinationFacet = array_slice(app(DestinationKnowledgeService::class)->inventory(), 0, 10, true);

        return [
            'categories' => $categoryCounts,
            'visa' => $facets['visa'],
            'days' => $facets['days'],
            'departures' => $facets['departures'],
            'destinations' => $destinationFacet,
        ];
    }
}
