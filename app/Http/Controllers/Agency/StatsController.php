<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use App\Models\TourClick;
use App\Models\TourView;

class StatsController extends Controller
{
    public function index()
    {
        $agency = auth()->user()->agency;
        $tourIds = $agency->tours()->pluck('id');

        // Most viewed tours
        $topViewed = TourView::whereIn('tour_id', $tourIds)
            ->selectRaw('tour_id, COUNT(*) as views')
            ->groupBy('tour_id')
            ->orderByDesc('views')
            ->limit(10)
            ->get()
            ->map(function ($item) {
                $item->tour = \App\Models\Tour::find($item->tour_id);
                return $item;
            });

        // Most clicked tours
        $topClicked = TourClick::where('agency_id', $agency->id)
            ->selectRaw('tour_id, COUNT(*) as clicks')
            ->groupBy('tour_id')
            ->orderByDesc('clicks')
            ->limit(10)
            ->get()
            ->map(function ($item) {
                $item->tour = \App\Models\Tour::find($item->tour_id);
                return $item;
            });

        // Hourly interest (views)
        $hourlyViews = TourView::whereIn('tour_id', $tourIds)
            ->where('viewed_at', '>=', now()->subDays(30))
            ->selectRaw('HOUR(viewed_at) as hour, COUNT(*) as total')
            ->groupBy('hour')
            ->orderBy('hour')
            ->pluck('total', 'hour');

        $hourLabels = [];
        $hourData   = [];
        for ($h = 0; $h < 24; $h++) {
            $hourLabels[] = str_pad($h, 2, '0', STR_PAD_LEFT) . ':00';
            $hourData[]   = $hourlyViews[$h] ?? 0;
        }

        // Tour dates popularity (which dates get most clicks for same tour)
        $popularDates = \App\Models\TourDate::whereIn('tour_id', $tourIds)
            ->where('departure_date', '>=', now())
            ->with('tour')
            ->get()
            ->sortByDesc(function ($date) {
                return TourClick::where('tour_id', $date->tour_id)
                    ->where('clicked_at', '>=', $date->departure_date)
                    ->count();
            })
            ->take(10);

        return view('agency.stats', compact(
            'topViewed', 'topClicked', 'hourLabels', 'hourData', 'popularDates'
        ));
    }
}
