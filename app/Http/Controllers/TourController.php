<?php

namespace App\Http\Controllers;

use App\Models\Agency;
use App\Models\AiSearchLog;
use App\Models\Category;
use App\Models\Tour;
use App\Models\TourView;
use App\Support\TurkishCities;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TourController extends Controller
{
    public function index(Request $request)
    {
        $query = Tour::with('agency', 'category', 'dates')
            ->active()
            ->whereHas('agency', fn ($q) => $q->active());

        // Filters
        if ($request->filled('q')) {
            $search = $request->q;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('destination', 'like', "%{$search}%");
            });
        }

        if ($request->filled('destination')) {
            $query->where('destination', $request->destination);
        }

        // Kalkış şehrim: kalkış şehri VEYA duraklarından biri eşleşen turlar.
        // Şehir bilgisi girilmemiş turlar bu filtrede gizlenir (scopeDepartsFrom).
        if ($request->filled('departure_city')) {
            $query->departsFrom($request->departure_city);
        }

        // Filtre değerleri TL — kur-normalize price_try ile karşılaştırılır
        if ($request->filled('min_price')) {
            $query->where('price_try', '>=', $request->min_price);
        }
        if ($request->filled('max_price')) {
            $query->where('price_try', '<=', $request->max_price);
        }

        if ($request->filled('agency_id')) {
            $query->where('agency_id', $request->agency_id);
        }

        if ($request->filled('category')) {
            $cat = Category::where('slug', $request->category)->first();
            if ($cat) {
                $catIds = collect([$cat->id])->merge($cat->children()->pluck('id'));
                $query->whereIn('category_id', $catIds);
            }
        }

        if ($request->filled('min_days')) {
            $query->where('duration_days', '>=', $request->min_days);
        }
        if ($request->filled('max_days')) {
            $query->where('duration_days', '<=', $request->max_days);
        }

        if ($request->filled('date_start')) {
            $query->where(function ($q) use ($request) {
                $q->where('departure_date', '>=', $request->date_start)
                    ->orWhereHas('dates', fn ($dq) => $dq->where('departure_date', '>=', $request->date_start));
            });
        }

        if ($request->filled('date_end')) {
            $query->where(function ($q) use ($request) {
                // If a tour overlaps or starts before this date
                $q->where('departure_date', '<=', $request->date_end)
                    ->orWhereHas('dates', fn ($dq) => $dq->where('departure_date', '<=', $request->date_end));
            });
        }

        // Sort
        $sort = $request->input('sort', 'price_asc');

        if (in_array($sort, ['popular', 'reviews'])) {
            $query->withCount(['clicks', 'views', 'reviews']);
        }

        match ($sort) {
            'price_desc' => $query->orderByDesc('price_try'),
            'date' => $query->orderBy('departure_date'),
            'newest' => $query->orderByDesc('created_at'),
            'popular' => $query->orderByRaw('(clicks_count + views_count) DESC')->orderByDesc('reviews_count'),
            'reviews' => $query->orderByDesc('reviews_count')->orderByDesc('id'),
            default => $query->orderBy('price_try'),
        };

        $tours = $query->paginate(12)->withQueryString();

        $destinations = Tour::active()->select('destination')
            ->distinct()->orderBy('destination')->pluck('destination');
        $agencies = Agency::active()->orderBy('name')->get();
        $categories = Category::active()->parents()->with('children')->orderBy('sort_order')->get();
        $departureCities = TurkishCities::all();

        return view('tours.index', compact('tours', 'destinations', 'agencies', 'categories', 'departureCities'));
    }

    public function show(Request $request, Tour $tour)
    {
        abort_unless($tour->isPubliclyVisible() && $tour->agency?->is_active, 404);

        $this->captureAiSelection($request, $tour);
        $tour->load('agency', 'dates', 'category');

        // Record view
        $sessionId = session()->getId();
        $recentKey = 'tour_view_'.$tour->id.'_'.$sessionId;

        if (! cache()->has($recentKey)) {
            TourView::create([
                'tour_id' => $tour->id,
                'session_id' => $sessionId,
                'user_id' => auth()->id(),
                'viewed_at' => now(),
            ]);
            // Yaşam-boyu sayaç: ham tour_views satırları retention ile silinse de toplam
            // korunur. Query builder: model event'leri ve updated_at tetiklenmesin.
            DB::table('tours')->where('id', $tour->id)->increment('views_count');
            cache()->put($recentKey, true, now()->addHour());
        }

        // Track in session for recently viewed display
        $recentlyViewed = session()->get('recently_viewed', []);
        $recentlyViewed = array_filter($recentlyViewed, fn ($id) => $id !== $tour->id);
        array_unshift($recentlyViewed, $tour->id);
        session()->put('recently_viewed', array_slice($recentlyViewed, 0, 6));

        // Get same tour name from other agencies
        $otherOffers = Tour::with('agency')
            ->active()
            ->where('title', $tour->title)
            ->where('id', '!=', $tour->id)
            ->whereHas('agency', fn ($q) => $q->active())
            ->orderBy('price_try')
            ->get();

        // Similar tours
        $similarTours = Tour::with('agency')
            ->active()
            ->where('destination', $tour->destination)
            ->where('id', '!=', $tour->id)
            ->whereNotIn('id', $otherOffers->pluck('id'))
            ->orderBy('price_try')
            ->limit(4)
            ->get();

        $reviews = $tour->reviews()->with('user')->get();
        $avgRating = $reviews->avg('rating') ? round($reviews->avg('rating'), 1) : null;
        $userReview = auth()->check()
            ? $reviews->firstWhere('user_id', auth()->id())
            : null;

        // Price history (last 30 days)
        $priceHistory = $tour->priceHistories()
            ->where('recorded_at', '>=', now()->subDays(30))
            ->orderBy('recorded_at')
            ->get();

        $priceLabels = $priceHistory->pluck('recorded_at')->map(fn ($d) => $d->format('d M'))->values();
        $priceData = $priceHistory->pluck('price')->values();

        return view('tours.show', compact(
            'tour', 'otherOffers', 'similarTours', 'reviews', 'avgRating', 'userReview',
            'priceLabels', 'priceData'
        ));
    }

    private function captureAiSelection(Request $request, Tour $tour): void
    {
        if (! $request->filled('ai_log_id')) {
            return;
        }

        $log = AiSearchLog::find((int) $request->query('ai_log_id'));
        if (! $log) {
            return;
        }

        // Basic ownership checks: user and/or session should match.
        if ($log->user_id !== null && $log->user_id !== auth()->id()) {
            return;
        }

        $sessionId = $request->session()->getId();
        if ($log->session_id && $log->session_id !== $sessionId) {
            return;
        }

        $resultIds = is_array($log->result_tour_ids)
            ? array_map('intval', $log->result_tour_ids)
            : [];
        if (! empty($resultIds) && ! in_array((int) $tour->id, $resultIds, true)) {
            return;
        }

        if ($log->selected_tour_id) {
            return;
        }

        $rank = (int) $request->query('ai_rank', 0);
        $log->update([
            'selected_tour_id' => $tour->id,
            'selected_rank' => $rank > 0 ? $rank : null,
            'selected_at' => now(),
        ]);
    }

    public function compare(Request $request)
    {
        $ids = $request->input('ids', []);

        if (empty($ids) || ! is_array($ids)) {
            return redirect()->route('tours.index')->with('error', 'Karşılaştırılacak tur bulunamadı.');
        }

        // Limit to 3 tours for visual consistency
        $tours = Tour::whereIn('id', array_slice($ids, 0, 3))
            ->with(['agency', 'category', 'dates' => function ($q) {
                $q->orderBy('departure_date');
            }])
            ->active()
            ->get();

        if ($tours->count() < 2) {
            return redirect()->route('tours.index')->with('error', 'Karşılaştırma yapmak için en az 2 aktif tur seçmelisiniz.');
        }

        return view('tours.compare', compact('tours'));
    }
}
