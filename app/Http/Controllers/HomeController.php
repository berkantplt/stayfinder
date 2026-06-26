<?php

namespace App\Http\Controllers;

use App\Models\Agency;
use App\Models\Banner;
use App\Models\Category;
use App\Models\Destination;
use App\Models\FeaturedCity;
use App\Models\Tour;

class HomeController extends Controller
{
    public function index()
    {
        $query = Tour::with(['agency', 'category'])
            ->active()
            ->whereHas('agency', fn ($q) => $q->active());

        // Filtering
        if (request('category')) {
            $cat = Category::where('slug', request('category'))->first();
            if ($cat) {
                $catIds = collect([$cat->id])->merge($cat->children()->pluck('id'));
                $query->whereIn('category_id', $catIds);
            }
        }

        if (request('destination')) {
            $query->where('destination', request('destination'));
        }

        // Acenta arama (kısmi eşleşme — kullanıcı "Jolly" yazabilir "Jolly Tur" bulunur)
        if ($agencyQuery = trim((string) request('agency'))) {
            $query->whereHas('agency', fn ($aQ) => $aQ->where('name', 'like', '%'.$agencyQuery.'%'));
        }

        // Sorting
        $sort = request('sort', 'price_asc');
        if ($sort === 'price_desc') {
            $query->orderByDesc('price_try');
        } else {
            $query->orderBy('price_try');
        }

        $popularTours = $query->limit(8)->get();

        if (request()->ajax()) {
            return view('partials.tour_grid', compact('popularTours'))->render();
        }

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

        // Acenta arama datalist'i için aktif + onaylı acentalar
        $activeAgencies = Agency::active()
            ->approved()
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

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

        return view('home', compact('popularTours', 'destinations', 'allDestinations', 'activeAgencies', 'categories', 'agencyCount', 'tourCount', 'recentlyViewed', 'banners', 'featuredCities'));
    }
}
