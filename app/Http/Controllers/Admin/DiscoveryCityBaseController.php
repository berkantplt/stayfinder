<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DiscoveryCityBase;
use App\Services\Discovery\DiscoveryCityBaseService;
use Illuminate\Http\Request;

/**
 * Keşif Rehberi şehir tabanları: halüsinasyon emniyeti için admin görünürlüğü.
 * Yanlış içerik görülen taban silinir ya da yeniden üretilir; silinen şehrin
 * bir sonraki rehberi tam üretimle gelir ve taban arkadan temiz kurulur.
 */
class DiscoveryCityBaseController extends Controller
{
    public function __construct(private readonly DiscoveryCityBaseService $bases) {}

    public function index(Request $request)
    {
        $query = DiscoveryCityBase::query();

        if ($search = trim((string) $request->input('q'))) {
            $normalized = DiscoveryCityBase::normalizeCity($search);
            $query->where(function ($q) use ($search, $normalized) {
                $q->where('display_name', 'like', '%'.$search.'%')
                    ->orWhere('country', 'like', '%'.$search.'%')
                    ->orWhere('normalized_city', 'like', '%'.$normalized.'%');
            });
        }

        if ($request->filled('stale')) {
            $query->stale();
        }

        $bases = $query
            ->orderByDesc('hit_count')
            ->orderByDesc('generated_at')
            ->paginate(20)
            ->withQueryString();

        $stats = [
            'total' => DiscoveryCityBase::count(),
            'stale' => DiscoveryCityBase::stale()->count(),
            'hits' => (int) DiscoveryCityBase::sum('hit_count'),
        ];

        return view('admin.discovery-city-bases.index', compact('bases', 'stats'));
    }

    /**
     * Yeniden üretim kayıt anahtarı (normalized_city) üzerinden kuyruğa alınır:
     * normalize idempotent olduğu için job aynı satırı günceller (display_name
     * modelin döndürdüğü ada göre yenilenir).
     */
    public function regenerate(DiscoveryCityBase $base)
    {
        $kuyrukta = $this->bases->queueBuild($base->normalized_city);

        return back()->with('success', $kuyrukta
            ? $base->display_name.' tabanı yeniden üretim için kuyruğa alındı.'
            : $base->display_name.' için üretim zaten kuyrukta.');
    }

    public function destroy(DiscoveryCityBase $base)
    {
        $adi = $base->display_name;
        $base->delete();

        return redirect()
            ->route('admin.discovery-city-bases.index')
            ->with('success', $adi.' tabanı silindi. Bu şehrin bir sonraki rehberi tam üretimle gelir, taban arkadan yeniden kurulur.');
    }
}
