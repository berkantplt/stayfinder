<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Tour;
use Illuminate\Http\Request;

class CampaignController extends Controller
{
    public function index()
    {
        $agency = auth()->user()->agency;
        $tours = $agency->tours()->active()->get();
        $campaigns = Campaign::whereIn('tour_id', $tours->pluck('id'))
            ->with('tour')
            ->orderByDesc('created_at')
            ->get();

        return view('agency.campaigns.index', compact('tours', 'campaigns'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'tour_id'        => 'required|exists:tours,id',
            'discount_price' => 'required|numeric|min:0',
            'label'          => 'required|string|max:100',
            'starts_at'      => 'required|date',
            'ends_at'        => 'required|date|after:starts_at',
        ]);

        // Verify tour belongs to agency
        $tour = Tour::findOrFail($validated['tour_id']);
        if ($tour->agency_id !== auth()->user()->agency_id) {
            abort(403);
        }

        Campaign::create($validated);

        return back()->with('success', 'Kampanya oluşturuldu.');
    }

    public function destroy(Campaign $campaign)
    {
        if ($campaign->tour->agency_id !== auth()->user()->agency_id) {
            abort(403);
        }

        $campaign->delete();

        return back()->with('success', 'Kampanya silindi.');
    }
}
