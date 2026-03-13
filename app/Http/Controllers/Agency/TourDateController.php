<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use App\Models\Tour;
use App\Models\TourDate;
use Illuminate\Http\Request;

class TourDateController extends Controller
{
    public function store(Request $request, Tour $tour)
    {
        $this->authorize($tour);

        $validated = $request->validate([
            'departure_date' => 'required|date',
            'return_date'    => 'required|date|after_or_equal:departure_date',
            'label'          => 'nullable|string|max:100',
        ]);

        $tour->dates()->create($validated);

        return back()->with('success', 'Tarih eklendi.');
    }

    public function update(Request $request, Tour $tour, TourDate $date)
    {
        $this->authorize($tour);
        if ($date->tour_id !== $tour->id) abort(403);

        $validated = $request->validate([
            'departure_date' => 'required|date',
            'return_date'    => 'required|date|after_or_equal:departure_date',
            'label'          => 'nullable|string|max:100',
        ]);

        $date->update($validated);

        return back()->with('success', 'Tarih güncellendi.');
    }

    public function destroy(Tour $tour, TourDate $date)
    {
        $this->authorize($tour);
        if ($date->tour_id !== $tour->id) abort(403);

        $date->delete();

        return back()->with('success', 'Tarih silindi.');
    }

    private function authorize(Tour $tour): void
    {
        if ($tour->agency_id !== auth()->user()->agency_id) {
            abort(403);
        }
    }
}
