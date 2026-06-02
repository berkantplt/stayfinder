<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateDestinationProfileJob;
use App\Models\DestinationProfile;
use Illuminate\Http\Request;

class DestinationProfileController extends Controller
{
    public function index(Request $request)
    {
        $query = DestinationProfile::query();

        if ($search = trim((string) $request->input('q'))) {
            $normalized = DestinationProfile::normalize($search);
            $query->where(function ($q) use ($search, $normalized) {
                $q->where('city', 'like', '%' . $search . '%')
                  ->orWhere('country', 'like', '%' . $search . '%')
                  ->orWhere('normalized_city', 'like', '%' . $normalized . '%');
            });
        }

        if ($source = $request->input('source')) {
            if (in_array($source, [
                DestinationProfile::SOURCE_MANUAL,
                DestinationProfile::SOURCE_LLM,
                DestinationProfile::SOURCE_DEFAULT,
            ], true)) {
                $query->where('source', $source);
            }
        }

        if ($request->filled('stale')) {
            $query->stale();
        }

        $profiles = $query
            ->orderByDesc('updated_at')
            ->paginate(20)
            ->withQueryString();

        $stats = [
            'total' => DestinationProfile::count(),
            'manual' => DestinationProfile::where('source', DestinationProfile::SOURCE_MANUAL)->count(),
            'llm' => DestinationProfile::where('source', DestinationProfile::SOURCE_LLM)->count(),
            'default' => DestinationProfile::where('source', DestinationProfile::SOURCE_DEFAULT)->count(),
            'stale' => DestinationProfile::stale()->count(),
        ];

        return view('admin.destination-profiles.index', compact('profiles', 'stats'));
    }

    public function edit(DestinationProfile $profile)
    {
        return view('admin.destination-profiles.edit', [
            'profile' => $profile,
            'allowedVibeTags' => [
                'beach', 'luxury', 'shopping', 'nightlife', 'cultural', 'historical',
                'nature', 'family', 'adventure', 'spa', 'religious', 'winter_sport', 'cruise',
            ],
            'reasonOptions' => ['Çok pahalı', 'Yanlış destinasyon', 'Tarz uymadı', 'Diğer'],
        ]);
    }

    public function update(Request $request, DestinationProfile $profile)
    {
        $validated = $request->validate([
            'city' => 'required|string|max:120',
            'country' => 'nullable|string|max:100',
            'summary' => 'nullable|string|max:1000',
            'crowd_score' => 'required|numeric|min:0|max:1',
            'liveliness_score' => 'required|numeric|min:0|max:1',
            'vibe_tags' => 'nullable|string|max:500',
            'best_months' => 'nullable|string|max:100',
            'crowded_months' => 'nullable|string|max:100',
            'requires_visa_for_tr' => 'nullable|in:0,1,unknown',
            'climate_by_month' => 'nullable|string|max:5000',
        ]);

        // CSV → arrays
        $vibeTags = $this->parseVibeTags($validated['vibe_tags'] ?? '');
        $bestMonths = $this->parseMonths($validated['best_months'] ?? '');
        $crowdedMonths = $this->parseMonths($validated['crowded_months'] ?? '');

        // climate_by_month JSON validate
        $climate = null;
        if (!empty($validated['climate_by_month'])) {
            $climate = json_decode($validated['climate_by_month'], true);
            if (!is_array($climate)) {
                return back()
                    ->withInput()
                    ->withErrors(['climate_by_month' => 'Climate verisi geçerli JSON olmalı.']);
            }
        }

        $visa = $request->input('requires_visa_for_tr');
        $visaBool = match ($visa) {
            '1' => true,
            '0' => false,
            default => null,
        };

        $profile->update([
            'city' => $validated['city'],
            'normalized_city' => DestinationProfile::normalize($validated['city']),
            'country' => $validated['country'] ?? null,
            'summary' => $validated['summary'] ?? null,
            'crowd_score' => round((float) $validated['crowd_score'], 2),
            'liveliness_score' => round((float) $validated['liveliness_score'], 2),
            'vibe_tags' => empty($vibeTags) ? null : $vibeTags,
            'best_months' => empty($bestMonths) ? null : $bestMonths,
            'crowded_months' => empty($crowdedMonths) ? null : $crowdedMonths,
            'requires_visa_for_tr' => $visaBool,
            'climate_by_month' => $climate,
            // Manuel düzenleme → kaynak işareti güncelle, version current
            'source' => DestinationProfile::SOURCE_MANUAL,
            'enrichment_version' => DestinationProfile::CURRENT_ENRICHMENT_VERSION,
            'reasoning' => 'Admin tarafından manuel düzenlendi (' . now()->toDateTimeString() . ')',
            'generated_at' => now(),
        ]);

        // Service cache invalidation
        \Illuminate\Support\Facades\Cache::forget('dest_profile:' . $profile->normalized_city);

        return redirect()
            ->route('admin.destination-profiles.edit', $profile)
            ->with('success', $profile->city . ' profili güncellendi.');
    }

    public function regenerate(DestinationProfile $profile)
    {
        GenerateDestinationProfileJob::dispatch($profile->city);

        return redirect()
            ->route('admin.destination-profiles.edit', $profile)
            ->with('success', $profile->city . ' için yeniden LLM zenginleştirme kuyruğa alındı.');
    }

    public function destroy(DestinationProfile $profile)
    {
        // Sadece default veya confirm=1 ile sil
        if ($profile->source !== DestinationProfile::SOURCE_DEFAULT
            && (int) request('confirm') !== 1
        ) {
            return back()->withErrors(
                'Manuel veya LLM kaynaklı profili silmek için confirm=1 onayı gerekir.'
            );
        }

        \Illuminate\Support\Facades\Cache::forget('dest_profile:' . $profile->normalized_city);
        $city = $profile->city;
        $profile->delete();

        return redirect()
            ->route('admin.destination-profiles.index')
            ->with('success', $city . ' profili silindi.');
    }

    /**
     * @return array<int, string>
     */
    private function parseVibeTags(string $csv): array
    {
        $allowed = [
            'beach', 'luxury', 'shopping', 'nightlife', 'cultural', 'historical',
            'nature', 'family', 'adventure', 'spa', 'religious', 'winter_sport', 'cruise',
        ];

        return collect(explode(',', $csv))
            ->map(fn($t) => mb_strtolower(trim($t), 'UTF-8'))
            ->filter()
            ->filter(fn($t) => in_array($t, $allowed, true))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, int>
     */
    private function parseMonths(string $csv): array
    {
        return collect(explode(',', $csv))
            ->map(fn($m) => (int) trim($m))
            ->filter(fn($m) => $m >= 1 && $m <= 12)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }
}
