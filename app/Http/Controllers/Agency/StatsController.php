<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use App\Models\Tour;
use App\Models\TourClick;
use App\Models\TourView;
use Illuminate\Support\Facades\DB;

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
            ->get();

        // Most clicked tours (agency_id ile: arşivlenmiş turun tıklamaları da sayılır)
        $topClicked = TourClick::where('agency_id', $agency->id)
            ->selectRaw('tour_id, COUNT(*) as clicks')
            ->groupBy('tour_id')
            ->orderByDesc('clicks')
            ->limit(10)
            ->get();

        // C8: satır başına Tour::find yerine tek sorgu. withTrashed: arşivdeki
        // turun geçmişi listede kalır, blade bağlantı vermez ("arşivde" yazar).
        $tourIdsToLoad = $topViewed->pluck('tour_id')->merge($topClicked->pluck('tour_id'))->unique()->values();
        $toursById = $tourIdsToLoad->isEmpty()
            ? collect()
            : Tour::withTrashed()->whereIn('id', $tourIdsToLoad)->get(['id', 'title', 'slug', 'deleted_at'])->keyBy('id');
        $topViewed->each(fn ($item) => $item->setRelation('tour', $toursById->get($item->tour_id)));
        $topClicked->each(fn ($item) => $item->setRelation('tour', $toursById->get($item->tour_id)));

        // Hourly interest (views). Saat ifadesi sürücüye göre: MySQL HOUR(),
        // sqlite (test) strftime — bu sayfanın test edilebilmesi için (C8).
        $hourExpression = DB::connection()->getDriverName() === 'sqlite'
            ? "CAST(strftime('%H', viewed_at) AS INTEGER)"
            : 'HOUR(viewed_at)';
        $hourlyViews = TourView::whereIn('tour_id', $tourIds)
            ->where('viewed_at', '>=', now()->subDays(30))
            ->selectRaw($hourExpression.' as hour, COUNT(*) as total')
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
