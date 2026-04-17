<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Tour;
use App\Models\Category;
use App\Models\User;
use App\Models\Agency;

class ReportController extends Controller
{
    public function index()
    {
        $stats = [
            'total_tours' => Tour::count(),
            'active_tours' => Tour::active()->count(),
            'total_users' => User::count(),
            'total_agencies' => Agency::count(),
            'total_categories' => Category::count(),
        ];

        // En çok görüntülenen turlar (Top 10)
        $topViewedTours = Tour::orderByDesc('views_count')
            ->limit(10)
            ->get(['title', 'views_count', 'clicks_count', 'destination']);

        // Destinasyonlara göre tur dağılımı (Top 5 destinasyon)
        $destinations = Tour::select('destination', \DB::raw('count(*) as count'))
            ->groupBy('destination')
            ->orderByDesc('count')
            ->limit(5)
            ->get();

        return view('admin.reports.index', compact('stats', 'topViewedTours', 'destinations'));
    }
}
