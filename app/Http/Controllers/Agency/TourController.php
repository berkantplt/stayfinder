<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use App\Models\Tour;
use Illuminate\Http\Request;

class TourController extends Controller
{
    public function index()
    {
        $tours = auth()->user()->agency
            ->tours()
            ->orderByDesc('created_at')
            ->paginate(15);

        return view('agency.tours.index', compact('tours'));
    }

    public function create()
    {
        $categories = \App\Models\Category::active()->parents()->with('children')->orderBy('sort_order')->get();
        return view('agency.tours.create', compact('categories'));
    }

    public function show(Tour $tour)
    {
        $this->authorize($tour);
        $tour->load('agency', 'dates');

        $reviews    = $tour->reviews()->with('user')->latest()->get();
        $avgRating  = $reviews->avg('rating') ? round($reviews->avg('rating'), 1) : null;
        $clickCount = \App\Models\TourClick::where('tour_id', $tour->id)->count();
        $viewCount  = \App\Models\TourView::where('tour_id', $tour->id)->count();

        return view('agency.tours.show', compact('tour', 'reviews', 'avgRating', 'clickCount', 'viewCount'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'category_id'    => 'nullable|exists:categories,id',
            'title'          => 'required|string|max:255',
            'destination'    => 'required|string|max:100',
            'description'    => 'nullable|string',
            'price'          => 'required|numeric|min:0',
            'duration_days'  => 'required|integer|min:1',
            'departure_date' => 'nullable|date',
            'return_date'    => 'nullable|date|after_or_equal:departure_date',
            'included'       => 'nullable|string',
            'excluded'       => 'nullable|string',
            'image'          => 'nullable|image|max:5120',
            'tour_url'       => 'nullable|url',
        ]);

        if ($request->hasFile('image')) {
            $validated['image'] = '/storage/' . $request->file('image')->store('tours', 'public');
        } else {
            unset($validated['image']);
        }

        $validated['agency_id'] = auth()->user()->agency_id;
        $validated['currency']  = 'TRY';

        Tour::create($validated);

        return redirect()->route('agency.tours.index')
            ->with('success', 'Tur başarıyla eklendi.');
    }

    public function edit(Tour $tour)
    {
        $this->authorize($tour);
        $categories = \App\Models\Category::active()->parents()->with('children')->orderBy('sort_order')->get();
        return view('agency.tours.edit', compact('tour', 'categories'));
    }

    public function update(Request $request, Tour $tour)
    {
        $this->authorize($tour);

        $validated = $request->validate([
            'category_id'    => 'nullable|exists:categories,id',
            'title'          => 'required|string|max:255',
            'destination'    => 'required|string|max:100',
            'description'    => 'nullable|string',
            'price'          => 'required|numeric|min:0',
            'duration_days'  => 'required|integer|min:1',
            'departure_date' => 'nullable|date',
            'return_date'    => 'nullable|date|after_or_equal:departure_date',
            'included'       => 'nullable|string',
            'excluded'       => 'nullable|string',
            'image'          => 'nullable|image|max:5120',
            'tour_url'       => 'nullable|url',
            'is_active'      => 'boolean',
        ]);

        if ($request->hasFile('image')) {
            // Delete old uploaded image
            if ($tour->image && str_starts_with($tour->image, '/storage/')) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete(str_replace('/storage/', '', $tour->image));
            }
            $validated['image'] = '/storage/' . $request->file('image')->store('tours', 'public');
        } else {
            unset($validated['image']);
        }

        $tour->update($validated);

        return redirect()->route('agency.tours.index')
            ->with('success', 'Tur güncellendi.');
    }

    public function destroy(Tour $tour)
    {
        $this->authorize($tour);
        $tour->delete();

        return redirect()->route('agency.tours.index')
            ->with('success', 'Tur silindi.');
    }

    private function authorize(Tour $tour): void
    {
        if ($tour->agency_id !== auth()->user()->agency_id) {
            abort(403);
        }
    }
}
