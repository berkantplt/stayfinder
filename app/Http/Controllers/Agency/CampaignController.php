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

    public function edit(Campaign $campaign)
    {
        $this->authorizeCampaign($campaign);

        $agency = auth()->user()->agency;
        $tours = $agency->tours()->active()->get();

        return view('agency.campaigns.edit', compact('campaign', 'tours'));
    }

    public function update(Request $request, Campaign $campaign)
    {
        $this->authorizeCampaign($campaign);

        $validated = $request->validate([
            'tour_id'        => 'required|exists:tours,id',
            'discount_price' => 'required|numeric|min:0',
            'label'          => 'required|string|max:100',
            'starts_at'      => 'required|date',
            'ends_at'        => 'required|date|after:starts_at',
        ]);

        // Yeni tour_id de bu acentaya ait olmalı (kullanıcı başka acentanın turu seçemesin)
        $tour = Tour::findOrFail($validated['tour_id']);
        if ($tour->agency_id !== auth()->user()->agency_id) {
            abort(403);
        }

        // is_active default true; explicit toggle gerekiyorsa eklenebilir
        $campaign->update($validated + ['is_active' => $request->boolean('is_active', true)]);

        return redirect()
            ->route('agency.campaigns.index')
            ->with('success', 'Kampanya güncellendi.');
    }

    public function destroy(Campaign $campaign)
    {
        $this->authorizeCampaign($campaign);

        $campaign->delete();

        return back()->with('success', 'Kampanya silindi.');
    }

    private function authorizeCampaign(Campaign $campaign): void
    {
        if ($campaign->tour->agency_id !== auth()->user()->agency_id) {
            abort(403);
        }
    }
}
