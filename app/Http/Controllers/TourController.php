<?php

namespace App\Http\Controllers;

use App\Models\Tour;
use App\Models\Agency;
use App\Models\Category;
use Illuminate\Http\Request;

class TourController extends Controller
{
    public function index(Request $request)
    {
        $query = Tour::with('agency', 'category', 'dates')
            ->active()
            ->whereHas('agency', fn($q) => $q->active());

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

        if ($request->filled('min_price')) {
            $query->where('price', '>=', $request->min_price);
        }
        if ($request->filled('max_price')) {
            $query->where('price', '<=', $request->max_price);
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
                  ->orWhereHas('dates', fn($dq) => $dq->where('departure_date', '>=', $request->date_start));
            });
        }

        if ($request->filled('date_end')) {
            $query->where(function ($q) use ($request) {
                // If a tour overlaps or starts before this date
                $q->where('departure_date', '<=', $request->date_end)
                  ->orWhereHas('dates', fn($dq) => $dq->where('departure_date', '<=', $request->date_end));
            });
        }

        // Sort
        $sort = $request->input('sort', 'price_asc');
        match ($sort) {
            'price_desc'  => $query->orderByDesc('price'),
            'date'        => $query->orderBy('departure_date'),
            'newest'      => $query->orderByDesc('created_at'),
            default       => $query->orderBy('price'),
        };

        $tours = $query->paginate(12)->withQueryString();

        $destinations = Tour::active()->select('destination')
            ->distinct()->orderBy('destination')->pluck('destination');
        $agencies = Agency::active()->orderBy('name')->get();
        $categories = Category::active()->parents()->with('children')->orderBy('sort_order')->get();

        return view('tours.index', compact('tours', 'destinations', 'agencies', 'categories'));
    }

    public function show(Tour $tour)
    {
        $tour->load('agency', 'dates', 'category');

        // Record view
        $sessionId = session()->getId();
        $recentKey = 'tour_view_' . $tour->id . '_' . $sessionId;

        if (!cache()->has($recentKey)) {
            \App\Models\TourView::create([
                'tour_id'    => $tour->id,
                'session_id' => $sessionId,
                'user_id'    => auth()->id(),
                'viewed_at'  => now(),
            ]);
            cache()->put($recentKey, true, now()->addHour());
        }

        // Track in session for recently viewed display
        $recentlyViewed = session()->get('recently_viewed', []);
        $recentlyViewed = array_filter($recentlyViewed, fn($id) => $id !== $tour->id);
        array_unshift($recentlyViewed, $tour->id);
        session()->put('recently_viewed', array_slice($recentlyViewed, 0, 6));

        // Get same tour name from other agencies
        $otherOffers = Tour::with('agency')
            ->active()
            ->where('title', $tour->title)
            ->where('id', '!=', $tour->id)
            ->whereHas('agency', fn($q) => $q->active())
            ->orderBy('price')
            ->get();

        // Similar tours
        $similarTours = Tour::with('agency')
            ->active()
            ->where('destination', $tour->destination)
            ->where('id', '!=', $tour->id)
            ->whereNotIn('id', $otherOffers->pluck('id'))
            ->orderBy('price')
            ->limit(4)
            ->get();

        $reviews = $tour->reviews()->with('user')->get();
        $avgRating = $reviews->avg('rating') ? round($reviews->avg('rating'), 1) : null;
        $userReview = auth()->check()
            ? $reviews->firstWhere('user_id', auth()->id())
            : null;

        return view('tours.show', compact('tour', 'otherOffers', 'similarTours', 'reviews', 'avgRating', 'userReview'));
    }
}
