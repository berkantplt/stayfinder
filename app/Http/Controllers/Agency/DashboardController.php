<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use App\Models\TourClick;
use App\Models\TourView;

class DashboardController extends Controller
{
    public function applicationStatus()
    {
        $agency = auth()->user()->agency;

        if ($agency?->isApproved()) {
            return redirect()->route('agency.dashboard');
        }

        return view('agency.application-status', compact('agency'));
    }

    public function index()
    {
        $agency = auth()->user()->agency;
        $agency->load('activeTours');

        $totalClicks = TourClick::where('agency_id', $agency->id)->count();
        $todayClicks = TourClick::where('agency_id', $agency->id)
            ->where('clicked_at', '>=', now()->startOfDay())->count();
        $weekClicks  = TourClick::where('agency_id', $agency->id)
            ->where('clicked_at', '>=', now()->subDays(7))->count();
        $monthClicks = TourClick::where('agency_id', $agency->id)
            ->where('clicked_at', '>=', now()->subDays(30))->count();

        // Per-tour click stats
        $tourClicks = TourClick::where('agency_id', $agency->id)
            ->selectRaw('tour_id, COUNT(*) as clicks')
            ->groupBy('tour_id')
            ->pluck('clicks', 'tour_id');

        // Daily clicks for last 30 days (chart data)
        $dailyClicks = TourClick::where('agency_id', $agency->id)
            ->where('clicked_at', '>=', now()->subDays(30))
            ->selectRaw('DATE(clicked_at) as date, COUNT(*) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('total', 'date');

        // Fill missing dates with 0
        $chartLabels = [];
        $chartData   = [];
        for ($i = 29; $i >= 0; $i--) {
            $d = now()->subDays($i)->format('Y-m-d');
            $chartLabels[] = now()->subDays($i)->format('d M');
            $chartData[]   = $dailyClicks[$d] ?? 0;
        }

        // Hourly distribution
        $hourlyClicks = TourClick::where('agency_id', $agency->id)
            ->where('clicked_at', '>=', now()->subDays(30))
            ->selectRaw('HOUR(clicked_at) as hour, COUNT(*) as total')
            ->groupBy('hour')
            ->orderBy('hour')
            ->pluck('total', 'hour');

        $hourLabels = [];
        $hourData   = [];
        for ($h = 0; $h < 24; $h++) {
            $hourLabels[] = str_pad($h, 2, '0', STR_PAD_LEFT) . ':00';
            $hourData[]   = $hourlyClicks[$h] ?? 0;
        }

        return view('agency.dashboard', compact(
            'agency', 'totalClicks', 'todayClicks', 'weekClicks', 'monthClicks',
            'tourClicks', 'chartLabels', 'chartData', 'hourLabels', 'hourData'
        ));
    }
}
