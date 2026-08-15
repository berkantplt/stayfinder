<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\Tour;
use App\Models\TourClick;
use App\Models\TourView;
use Illuminate\Http\Request;

/**
 * Admin > Trafik — hangi tur tıklanıyor, hangisi sadece görüntüleniyor.
 *
 * İki veri kaynağı var ve bilerek ayrı tutuluyor:
 *  - "Tüm zamanlar": tours.clicks_count / views_count sayaçları. Dashboard
 *    kutularındaki rakamlar da buradan geldiği için kutuya basınca sayılar tutar.
 *  - "Son N gün": ham tour_clicks / tour_views tabloları. Bu tablolar
 *    PruneAnalytics ile 180 günde budanıyor, o yüzden tarih aralığı 90 günle sınırlı.
 */
class TrafficController extends Controller
{
    /** PruneAnalytics ham tabloları bu kadar gün sonra siliyor. */
    private const RETENTION_DAYS = 180;

    private const RANGES = [
        'all' => 'Tüm zamanlar',
        '7' => 'Son 7 gün',
        '30' => 'Son 30 gün',
        '90' => 'Son 90 gün',
    ];

    public function index(Request $request)
    {
        $metric = $request->input('metric') === 'views' ? 'views' : 'clicks';
        $range = (string) $request->input('range', 'all');
        if (! array_key_exists($range, self::RANGES)) {
            $range = 'all';
        }
        $agencyId = $request->filled('agency_id') ? (int) $request->agency_id : null;
        $since = $range === 'all' ? null : now()->subDays((int) $range);

        $query = $since ? $this->rangedQuery($since) : $this->lifetimeQuery();

        if ($agencyId) {
            $query->where('tours.agency_id', $agencyId);
        }

        if ($request->filled('q')) {
            $search = $request->q;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('destination', 'like', "%{$search}%");
            });
        }

        // Son tıklama her iki modda da ham tablodan gelir (180 günle sınırlı).
        $query->selectSub(
            TourClick::selectRaw('MAX(clicked_at)')->whereColumn('tour_clicks.tour_id', 'tours.id'),
            'last_click_at'
        );

        $query->orderByDesc($metric === 'clicks' ? 'range_clicks' : 'range_views')
            ->orderByDesc($metric === 'clicks' ? 'range_views' : 'range_clicks')
            ->orderBy('tours.id');

        $tours = $query->paginate(25)->withQueryString();

        return view('admin.traffic.index', [
            'tours' => $tours,
            'metric' => $metric,
            'range' => $range,
            'ranges' => self::RANGES,
            'rangeLabel' => self::RANGES[$range],
            'agencies' => Agency::orderBy('name')->get(),
            'agencyId' => $agencyId,
            'totals' => $this->totals($since, $agencyId),
            'chart' => $this->dailySeries($since ? (int) $range : 30, $agencyId),
            'chartDays' => $since ? (int) $range : 30,
            'retentionDays' => self::RETENTION_DAYS,
        ]);
    }

    public function show(Request $request, Tour $tour)
    {
        $days = in_array((int) $request->input('days'), [7, 30, 90], true)
            ? (int) $request->input('days')
            : 30;
        $since = now()->subDays($days);

        $recentClicks = TourClick::where('tour_id', $tour->id)
            ->orderByDesc('clicked_at')
            ->limit(20)
            ->get();

        return view('admin.traffic.show', [
            'tour' => $tour->load('agency'),
            'days' => $days,
            'chart' => $this->dailySeries($days, null, $tour->id),
            'rangeClicks' => TourClick::where('tour_id', $tour->id)->where('clicked_at', '>=', $since)->count(),
            'rangeViews' => TourView::where('tour_id', $tour->id)->where('viewed_at', '>=', $since)->count(),
            'recentClicks' => $recentClicks,
            'retentionDays' => self::RETENTION_DAYS,
        ]);
    }

    /** Yaşam boyu sayaçlar — dashboard kutularıyla aynı kaynak. */
    private function lifetimeQuery()
    {
        return Tour::with('agency')
            ->select('tours.*')
            ->selectRaw('tours.clicks_count as range_clicks, tours.views_count as range_views')
            ->where(function ($q) {
                $q->where('tours.clicks_count', '>', 0)
                    ->orWhere('tours.views_count', '>', 0);
            });
    }

    /** Ham tablolardan tarih aralıklı sayım. */
    private function rangedQuery($since)
    {
        return Tour::with('agency')
            ->select('tours.*')
            ->selectSub(
                TourClick::selectRaw('COUNT(*)')
                    ->whereColumn('tour_clicks.tour_id', 'tours.id')
                    ->where('clicked_at', '>=', $since),
                'range_clicks'
            )
            ->selectSub(
                TourView::selectRaw('COUNT(*)')
                    ->whereColumn('tour_views.tour_id', 'tours.id')
                    ->where('viewed_at', '>=', $since),
                'range_views'
            )
            ->where(function ($q) use ($since) {
                $q->whereExists(function ($sub) use ($since) {
                    $sub->selectRaw('1')->from('tour_clicks')
                        ->whereColumn('tour_clicks.tour_id', 'tours.id')
                        ->where('clicked_at', '>=', $since);
                })->orWhereExists(function ($sub) use ($since) {
                    $sub->selectRaw('1')->from('tour_views')
                        ->whereColumn('tour_views.tour_id', 'tours.id')
                        ->where('viewed_at', '>=', $since);
                });
            });
    }

    /** Üstteki özet kartları. */
    private function totals($since, ?int $agencyId): array
    {
        if ($since === null) {
            $tours = Tour::query();
            if ($agencyId) {
                $tours->where('agency_id', $agencyId);
            }
            $clicks = (int) (clone $tours)->sum('clicks_count');
            $views = (int) (clone $tours)->sum('views_count');
            $withTraffic = (clone $tours)->where(function ($q) {
                $q->where('clicks_count', '>', 0)->orWhere('views_count', '>', 0);
            })->count();
        } else {
            $clickQuery = TourClick::where('clicked_at', '>=', $since);
            $viewQuery = TourView::where('viewed_at', '>=', $since);
            if ($agencyId) {
                $clickQuery->where('agency_id', $agencyId);
                $viewQuery->whereIn('tour_id', Tour::where('agency_id', $agencyId)->select('id'));
            }
            $clicks = (clone $clickQuery)->count();
            $views = (clone $viewQuery)->count();
            $withTraffic = (clone $clickQuery)->distinct()->count('tour_id')
                + (clone $viewQuery)->whereNotIn('tour_id', (clone $clickQuery)->select('tour_id'))
                    ->distinct()->count('tour_id');
        }

        return [
            'clicks' => $clicks,
            'views' => $views,
            'ctr' => $views > 0 ? round($clicks / $views * 100, 1) : null,
            'tours_with_traffic' => $withTraffic,
        ];
    }

    /** Günlük tıklama/görüntülenme serisi — grafik için. */
    private function dailySeries(int $days, ?int $agencyId, ?int $tourId = null): array
    {
        $since = now()->subDays($days - 1)->startOfDay();

        $clickQuery = TourClick::where('clicked_at', '>=', $since);
        $viewQuery = TourView::where('viewed_at', '>=', $since);

        if ($tourId) {
            $clickQuery->where('tour_id', $tourId);
            $viewQuery->where('tour_id', $tourId);
        } elseif ($agencyId) {
            $clickQuery->where('agency_id', $agencyId);
            $viewQuery->whereIn('tour_id', Tour::where('agency_id', $agencyId)->select('id'));
        }

        $dailyClicks = $clickQuery->selectRaw('DATE(clicked_at) as date, COUNT(*) as total')
            ->groupBy('date')->pluck('total', 'date');
        $dailyViews = $viewQuery->selectRaw('DATE(viewed_at) as date, COUNT(*) as total')
            ->groupBy('date')->pluck('total', 'date');

        $labels = $clicks = $views = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $day = now()->subDays($i);
            $key = $day->format('Y-m-d');
            $labels[] = $day->format('d M');
            $clicks[] = (int) ($dailyClicks[$key] ?? 0);
            $views[] = (int) ($dailyViews[$key] ?? 0);
        }

        return ['labels' => $labels, 'clicks' => $clicks, 'views' => $views];
    }
}
