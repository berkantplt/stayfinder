<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tour;
use App\Models\TourRubricScore;
use App\Services\Matching\Rubric;
use App\Services\Matching\RubricCoverage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

/**
 * Admin "Tur Puanlama": rubrik puanlama kapsamı + editör incelemesi (brif §3.5).
 *
 * Sekmeler: puansız / bayat (kuyruğa alınabilir), incelemede (Onayla),
 * puanlı, eksik veri (null boyutlar — genelde tur açıklaması eksiktir).
 * Durum hesabı RubricCoverage'da; komut da aynı servisi kullanır.
 */
class RubricReviewController extends Controller
{
    public const SEKMELER = ['puansiz', 'bayat', 'incelemede', 'puanli', 'eksik-veri'];

    private const SAYFA_BOYU = 100;

    public function index(Request $request, RubricCoverage $kapsam): View
    {
        $sekme = (string) $request->query('sekme', 'puansiz');
        if (! in_array($sekme, self::SEKMELER, true)) {
            $sekme = 'puansiz';
        }

        $rapor = $kapsam->rapor();

        $satirlar = $sekme === 'eksik-veri'
            ? $rapor['satirlar']->filter(fn ($r) => $r['score'] !== null && $r['score']->nullDimensions() !== [])
            : $rapor['satirlar']->where('durum', $sekme);
        $satirlar = $satirlar->values();

        // Durum PHP tarafında türetildiği için sayfalama da koleksiyon üstünde
        $sayfa = max(1, (int) $request->query('page', 1));
        $sayfali = new LengthAwarePaginator(
            $satirlar->forPage($sayfa, self::SAYFA_BOYU),
            $satirlar->count(),
            self::SAYFA_BOYU,
            $sayfa,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return view('admin.rubric-review', [
            'sekme' => $sekme,
            'ozet' => $rapor['ozet'],
            'satirlar' => $sayfali,
            'dimensions' => Rubric::dimensions(),
            'sekmeSayilari' => [
                'puansiz' => $rapor['ozet']['puansiz'],
                'bayat' => $rapor['ozet']['bayat'],
                'incelemede' => $rapor['ozet']['incelemede'],
                'puanli' => $rapor['ozet']['puanli'],
                'eksik-veri' => $rapor['ozet']['eksik_veri'],
            ],
        ]);
    }

    /**
     * Seçilen turları (tour_ids[]) ya da bir sekmenin tamamını (kapsam=puansiz|bayat)
     * puanlama kuyruğuna alır. Yalnız aktif turlar; kilit çift işi önler.
     */
    public function queue(Request $request, RubricCoverage $kapsam): RedirectResponse
    {
        $veri = $request->validate([
            'kapsam' => ['nullable', 'in:puansiz,bayat'],
            'sekme' => ['nullable', 'string'],
            'tour_ids' => ['required_without:kapsam', 'array', 'max:500'],
            'tour_ids.*' => ['integer'],
        ], [
            'tour_ids.required_without' => 'Önce en az bir tur seçin.',
        ]);

        if (! empty($veri['kapsam'])) {
            $ids = $kapsam->rapor()['satirlar']
                ->where('durum', $veri['kapsam'])
                ->map(fn ($r) => $r['tour']->id);
            $sekme = $veri['kapsam'];
        } else {
            $ids = Tour::where('is_active', true)
                ->whereIn('id', $veri['tour_ids'] ?? [])
                ->pluck('id');
            $sekme = in_array($veri['sekme'] ?? '', self::SEKMELER, true) ? $veri['sekme'] : 'puansiz';
        }

        $alinan = $kapsam->kuyrugaAl($ids);
        $geri = redirect()->route('admin.rubric.index', ['sekme' => $sekme]);

        if ($alinan === 0) {
            return $geri->with('warning', 'Kuyruğa alınacak tur yok ya da seçilenler son 10 dakikada zaten kuyruğa alınmış.');
        }

        return $geri->with('success', $alinan.' tur puanlama için kuyruğa alındı. Tur başına iki LLM geçişi yapılır; sonuç worker çalıştıkça bu sayfaya düşer.');
    }

    public function approve(Request $request, TourRubricScore $score): RedirectResponse
    {
        $score->update(['review_status' => TourRubricScore::STATUS_APPROVED]);

        return redirect()->route('admin.rubric.index', ['sekme' => 'incelemede'])->with('success', 'Puan onaylandı.');
    }
}
