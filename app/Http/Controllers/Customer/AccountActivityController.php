<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\AiSearchLog;
use App\Models\DiscoveryGuide;
use App\Models\Tour;
use Illuminate\Http\Request;

/**
 * D9 — "Aramalarım": kullanıcının kendi geçmişi hesapta — AI aramaları,
 * keşif rehberleri ve sunucuya yazılan karşılaştırma listesi.
 */
class AccountActivityController extends Controller
{
    public const COMPARE_LIMIT = 3; // TourController::KARSILASTIRMA_LIMITI ile aynı

    public function index()
    {
        $user = auth()->user();

        $aiSearches = AiSearchLog::where('user_id', $user->id)
            ->latest()->orderByDesc('id')
            ->take(20)
            ->get(['id', 'raw_query', 'result_tour_ids', 'created_at']);

        $guides = config('ai.discovery_enabled')
            ? DiscoveryGuide::where('user_id', $user->id)->latest()->orderByDesc('id')->take(10)->get()
            : collect();

        $compareTours = $user->compareTours()->with('agency')->orderBy('user_compare_tours.created_at')->get();

        return view('account.activity', compact('aiSearches', 'guides', 'compareTours'));
    }

    /** Sunucudaki karşılaştırma listesi (JSON) — sayfa yüklenince JS okur. */
    public function compareIndex()
    {
        return response()->json(['ids' => $this->currentIds()]);
    }

    /** Listeyi tümden eşitler (JS her değişiklikte gönderir): en fazla 3, yalnız yayındaki turlar. */
    public function compareSync(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['present', 'array', 'max:'.self::COMPARE_LIMIT],
            'ids.*' => ['integer', 'min:1'],
        ]);

        $wanted = collect($validated['ids'])->map(fn ($id) => (int) $id)->unique()->values();
        $existing = Tour::active()->whereIn('id', $wanted)->pluck('id')->map(fn ($id) => (int) $id);
        $ids = $wanted->filter(fn ($id) => $existing->contains($id))->values();

        $user = auth()->user();
        $current = $user->compareTours()->pluck('tours.id')->map(fn ($id) => (int) $id);
        $user->compareTours()->detach($current->diff($ids)->all());
        foreach ($ids->diff($current) as $id) {
            $user->compareTours()->attach($id);
        }

        return response()->json(['ids' => $this->currentIds()]);
    }

    public function compareRemove(Tour $tour)
    {
        auth()->user()->compareTours()->detach($tour->id);

        return back()->with('success', 'Tur karşılaştırma listesinden çıkarıldı.');
    }

    /** @return array<int> */
    private function currentIds(): array
    {
        return auth()->user()->compareTours()
            ->orderBy('user_compare_tours.created_at')
            ->pluck('tours.id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
