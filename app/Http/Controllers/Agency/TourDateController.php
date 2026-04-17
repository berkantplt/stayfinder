<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use App\Models\Tour;
use App\Models\TourDate;
use Carbon\Carbon;
use Illuminate\Http\Request;

class TourDateController extends Controller
{
    public function store(Request $request, Tour $tour)
    {
        $this->authorize($tour);

        $validated = $request->validate([
            'departure_date' => 'required|date',
            'price'          => 'required|numeric|min:0',
            'label'          => 'nullable|string|max:100',
        ]);

        $departure = Carbon::parse($validated['departure_date'])->startOfDay();
        $return = (clone $departure)->addDays(max(1, (int) $tour->duration_days) - 1);

        $tour->dates()->create([
            'departure_date' => $departure->toDateString(),
            'return_date' => $return->toDateString(),
            'price' => round((float) $validated['price'], 2),
            'label' => $validated['label'] ?? null,
        ]);
        $this->syncTourSummary($tour);

        return back()->with('success', 'Tarih eklendi.');
    }

    public function update(Request $request, Tour $tour, TourDate $date)
    {
        $this->authorize($tour);
        if ($date->tour_id !== $tour->id) abort(403);

        $validated = $request->validate([
            'departure_date' => 'required|date',
            'price'          => 'required|numeric|min:0',
            'label'          => 'nullable|string|max:100',
        ]);

        $departure = Carbon::parse($validated['departure_date'])->startOfDay();
        $return = (clone $departure)->addDays(max(1, (int) $tour->duration_days) - 1);

        $date->update([
            'departure_date' => $departure->toDateString(),
            'return_date' => $return->toDateString(),
            'price' => round((float) $validated['price'], 2),
            'label' => $validated['label'] ?? null,
        ]);
        $this->syncTourSummary($tour);

        return back()->with('success', 'Tarih güncellendi.');
    }

    public function destroy(Tour $tour, TourDate $date)
    {
        $this->authorize($tour);
        if ($date->tour_id !== $tour->id) abort(403);

        $date->delete();
        $this->syncTourSummary($tour);

        return back()->with('success', 'Tarih silindi.');
    }

    private function authorize(Tour $tour): void
    {
        if ($tour->agency_id !== auth()->user()->agency_id) {
            abort(403);
        }
    }

    private function syncTourSummary(Tour $tour): void
    {
        $dates = $tour->dates()
            ->orderBy('departure_date')
            ->get(['departure_date', 'return_date', 'price']);

        if ($dates->isEmpty()) {
            $tour->update([
                'departure_date' => null,
                'return_date' => null,
            ]);
            return;
        }

        $today = Carbon::today()->toDateString();
        $primaryDate = $dates->firstWhere('departure_date', '>=', $today) ?? $dates->first();
        $minPrice = (float) $dates->min('price');

        $tour->update([
            'price' => $minPrice,
            'departure_date' => $primaryDate?->departure_date,
            'return_date' => $primaryDate?->return_date,
        ]);
    }
}
