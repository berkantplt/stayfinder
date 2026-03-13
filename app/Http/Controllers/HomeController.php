<?php

namespace App\Http\Controllers;

use App\Models\Destination;
use App\Models\Tour;
use App\Models\Agency;

use App\Models\Banner;

class HomeController extends Controller
{
    public function index()
    {
        $popularTours = Tour::with('agency')
            ->active()
            ->whereHas('agency', fn($q) => $q->active())
            ->orderBy('price')
            ->limit(8)
            ->get();

        // Get destinations from managed table with tour counts
        $destinations = Destination::active()
            ->orderBy('sort_order')
            ->limit(6)
            ->get()
            ->map(function ($dest) {
                $stats = Tour::active()->where('destination', $dest->name);
                $dest->tour_count = $stats->count();
                $dest->min_price  = $stats->min('price');
                return $dest;
            })
            ->filter(fn($d) => $d->tour_count > 0);

        $agencyCount = Agency::active()->count();
        $tourCount   = Tour::active()->count();

        // Banners
        $banners = Banner::active()->orderBy('sort_order')->get();

        // Recently viewed (from session)
        $recentIds      = session()->get('recently_viewed', []);
        $recentlyViewed = collect();
        if (!empty($recentIds)) {
            $recentlyViewed = Tour::with('agency')
                ->active()
                ->whereIn('id', $recentIds)
                ->get()
                ->sortBy(fn($t) => array_search($t->id, $recentIds))
                ->values();
        }

        return view('home', compact('popularTours', 'destinations', 'agencyCount', 'tourCount', 'recentlyViewed', 'banners'));
    }
}
