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
use App\Support\DestinationFilter;
use Illuminate\Support\Facades\Cache;

class HomeController extends Controller
{
    /** Filtre barı sayaç cache'i — TourObserver tur değişiminde bunu düşürür. */
    // _v2: destinasyon eşleşmesi kelime sınırlı hale gelince sayılar değişti.
    // Anahtarı artırmak, deploy sonrası eski sayıların 5 dk görünmesini önler.
    public const FACETS_CACHE_KEY = 'home_filter_facets_v2';

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
            // Kart üstündeki puan rozeti (tour_grid): tur başına ayrı sorgu yerine tek çekim
            ->withCount('reviews')
            ->withAvg('reviews', 'rating')
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
                // Kart sayacı filtreyle AYNI mantığı kullanmalı: tam eşleşmeyken
                // "Yunanistan" kartı 2 yazıyor, tıklayınca 3 tur geliyordu.
                $stats = DestinationFilter::apply(Tour::active(), $dest->name);
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
                    'count' => DestinationFilter::apply(Tour::active(), $city->name)->count(),
                ];
            })
            // Show cities that have images, even if they have 0 tours currently
            ->filter(fn ($city) => count($city['images']) > 0)
            ->values();

        // Fallback for demo if DB is empty
        if ($featuredCities->isEmpty()) {
            $featuredCities = collect([
                ['name' => 'Paris', 'country' => 'Fransa', 'images' => [asset('images/featured_cities/paris.png')], 'count' => DestinationFilter::apply(Tour::active(), 'Paris')->count()],
            ]);
        }

        $facets = $this->filterBarFacets($categories);
        $specialPeriods = config('special_periods', []);
        // "Tümünü gör" aktif filtreleri taşısın — filtresiz /turlar'a gitmesin
        $tumunuGorUrl = $this->filtreliTurlarUrl();

        return view('home', compact('popularTours', 'destinations', 'allDestinations', 'categories', 'agencyCount', 'tourCount', 'recentlyViewed', 'banners', 'featuredCities', 'tourDrops', 'liveDrop', 'travelerCount', 'filteredCount', 'facets', 'specialPeriods', 'tumunuGorUrl'));
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
                // Tüm alt seviyeler: facet sayacı da aynı metodu kullanıyor,
                // ikisi ayrı hesaplanınca panelde "0" yazıp filtre tur döndürüyordu.
                $query->whereIn('category_id', $cat->descendantIds());
            }
        }

        // Geriye dönük uyum: eski tekil ?destination= linkleri çalışmaya devam etsin
        if (request('destination')) {
            DestinationFilter::apply($query, request('destination'));
        }

        // Destinasyon (çoklu, envanter şehirleri): "Paris, Roma" rotası Paris
        // seçiminde de bulunur. Naif LIKE'tan kelime sınırlı eşleşmeye alındı —
        // aksi halde "Kos" seçimi "Kosova", "Bali" seçimi "Balıkesir" getiriyordu.
        $destinations = array_filter((array) request('destinations'), 'is_string');
        if ($destinations !== []) {
            DestinationFilter::apply($query, $destinations);
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

        // Vize: kaynak tours.requires_visa — acentanın/adminin BEYANI.
        //
        // Eskiden DestinationProfile şehir listesi kullanılıyordu; ölçüldü ve
        // yanlış çıktı: Paris, Yunanistan ve Yunan Adaları "vizesiz" listesinde
        // görünüyor, filtre 78 tur döndürüp içine Schengen turlarını karıştırıyordu.
        // Hata yapısal — vize şehrin değil TURUN özelliği; aynı şehre giden iki tur
        // farklı vize rejiminde olabilir (Yunan adalarında kapı vizesi vs Schengen).
        //
        // İşaretlenmemiş tur hiçbir yöne girmez: NULL, SQL'de zaten hiçbir değere
        // eşit değil. "Vizesiz" arayana durumu bilinmeyen tur gösterilmez.
        $visa = array_values(array_intersect((array) request('visa'), ['vizesiz', 'vizeli']));
        if ($visa !== []) {
            $query->where(function ($q) use ($visa) {
                // Kapıda vize de vizedir: requires_visa=true olduğu için "vizeli" tarafına düşer.
                if (in_array('vizeli', $visa, true)) {
                    $q->orWhere('requires_visa', true);
                }
                if (in_array('vizesiz', $visa, true)) {
                    $q->orWhere('requires_visa', false);
                }
            });
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
     * Filtre barındaki count rozetleri (Etstur deseni). Sayılar filtresiz
     * envantere göredir ve 5 dk cache'lenir; canlı sayaç ayrıca AJAX'la döner.
     *
     * @return array{categories: array<int, int>, visa: array{vizesiz: int, vizeli: int}, days: array<string, int>, departures: array<string, int>, destinations: array<string, array{city: string, count: int}>}
     */
    /**
     * "Tümünü gör" bağlantısı: ana sayfadaki AKTİF filtreleri /turlar sayfasının
     * parametrelerine çevirir.
     *
     * İki sayfa farklı parametre adları kullanıyor (ana sayfa: days[] bantları,
     * budget_max, special; /turlar: min_days/max_days, max_price, date_start/end).
     * Eşleme olmadığında buton "Mısır" seçiliyken bile SİTEDEKİ TÜM turlara
     * gidiyordu.
     *
     * TAŞINAMAYANLAR (bilerek): vize seçimi (/turlar'da böyle bir filtre yok) ve
     * birden fazla ay (tek tarih aralığına sığmıyor). Bunlar sessizce düşer —
     * sonuç kümesi filtresiz değil, yalnız daha geniş olur.
     */
    private function filtreliTurlarUrl(): string
    {
        $p = [];

        if ($slug = request('category')) {
            $p['category'] = $slug;
        }

        // Destinasyon: /turlar tekil de dizi de kabul ediyor (DestinationFilter::apply).
        $destinasyonlar = array_values(array_filter((array) request('destinations'), 'is_string'));
        if (request('destination')) {
            $destinasyonlar[] = (string) request('destination');
        }
        $destinasyonlar = array_values(array_unique(array_filter($destinasyonlar)));
        if ($destinasyonlar !== []) {
            $p['destination'] = count($destinasyonlar) === 1 ? $destinasyonlar[0] : $destinasyonlar;
        }

        // Gün bantları → min/max. Birden çok bant seçiliyse en geniş aralık.
        $bantlar = array_intersect((array) request('days'), array_keys(self::DAY_BANDS));
        if ($bantlar !== []) {
            $minler = $maxlar = [];
            foreach ($bantlar as $bant) {
                [$min, $max] = self::DAY_BANDS[$bant];
                $minler[] = $min;
                $maxlar[] = $max;
            }
            $p['min_days'] = min($minler);
            if (max($maxlar) < 999) {
                $p['max_days'] = max($maxlar);
            }
        }

        if (($butce = (int) request('budget_max')) > 0) {
            $p['max_price'] = $butce;
        }

        // Kalkış: /turlar tek şehir alıyor, ana sayfa çoklu. İlki taşınır.
        $kalkislar = array_values(array_filter((array) request('departures'), 'is_string'));
        if ($kalkislar !== []) {
            $p['departure_city'] = $kalkislar[0];
        }

        // Özel dönem → tarih aralığı. Config birden çok yıl tutuyor ("Yılbaşı"
        // hem 2026 hem 2027); min/max alınsaydı pencere araya giren bütün yılı
        // kapsardı. YAKLAŞAN aralık seçilir.
        if ($ozel = config('special_periods.'.request('special'))) {
            $bugun = now()->toDateString();
            $araliklar = $ozel['ranges'] ?? [];
            usort($araliklar, fn ($a, $b) => $a[0] <=> $b[0]);

            foreach ($araliklar as [$bas, $bit]) {
                if ($bit >= $bugun) {
                    $p['date_start'] = $bas;
                    $p['date_end'] = $bit;
                    break;
                }
            }
            // Hepsi geçmişse en sonuncusunu kullan (boş sonuç yerine dürüst sonuç)
            if (! isset($p['date_start']) && $araliklar !== []) {
                [$p['date_start'], $p['date_end']] = end($araliklar);
            }
        }

        // Tek ay seçiliyse tarih aralığına çevrilebilir; birden fazlada çevrilemez.
        $aylar = array_values(array_filter(array_map('intval', (array) request('months')), fn ($m) => $m >= 1 && $m <= 12));
        if (count($aylar) === 1 && ! isset($p['date_start'])) {
            $ay = $aylar[0];
            $yil = $ay < now()->month ? now()->year + 1 : now()->year;
            $p['date_start'] = now()->setDate($yil, $ay, 1)->startOfMonth()->toDateString();
            $p['date_end'] = now()->setDate($yil, $ay, 1)->endOfMonth()->toDateString();
        }

        if ($sort = request('sort')) {
            $p['sort'] = $sort;
        }

        return route('tours.index', $p);
    }

    /**
     * Bir kategorinin panelde görünen sayısı: kendisi + tüm alt seviyeleri.
     * Filtre ile birebir aynı id kümesi (Category::descendantIds).
     *
     * @param  \Illuminate\Support\Collection<int, Category>  $tumKategoriler
     * @param  array<int, int>  $byCategory
     */
    private function kategoriToplami(Category $kategori, $tumKategoriler, array $byCategory): int
    {
        $toplam = 0;
        foreach ($kategori->descendantIds($tumKategoriler) as $id) {
            $toplam += (int) ($byCategory[$id] ?? 0);
        }

        return $toplam;
    }

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

            // Sayaç filtreyle AYNI kolonu kullanmalı, yoksa rozetteki sayı ile
            // gelen sonuç sessizce ayrışır.
            $visa = [
                'vizesiz' => $base()->where('requires_visa', false)->count(),
                'vizeli' => $base()->where('requires_visa', true)->count(),
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

        // Her kategorinin sayacı = kendisi + TÜM alt seviyeleri. Filtre ile aynı
        // id kümesini (descendantIds) kullanmak ŞART: ayrı hesaplandıkları dönemde
        // altında torunu olan kategoriler panelde "0" yazarken filtre tur
        // döndürüyordu. Tüm kategoriler tek çekimde alınır — döngüde DB'ye gidilmez.
        $tumKategoriler = Category::select(['id', 'parent_id'])->get();
        $categoryCounts = [];
        foreach ($categories as $parent) {
            foreach ($parent->children as $child) {
                $categoryCounts[$child->id] = $this->kategoriToplami($child, $tumKategoriler, $facets['byCategory']);
            }
            $categoryCounts[$parent->id] = $this->kategoriToplami($parent, $tumKategoriler, $facets['byCategory']);
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
