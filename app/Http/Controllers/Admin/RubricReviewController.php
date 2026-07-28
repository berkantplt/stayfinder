<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TourRubricScore;
use App\Services\Matching\Rubric;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Editör paneli (brif §3.5): iki-geçiş farkı büyük çıkan puanlar (needs_review)
 * + eksik veri raporu (null kalan boyutlar — genelde tur açıklaması eksiktir,
 * LLM değil).
 */
class RubricReviewController extends Controller
{
    public function index(): \Illuminate\View\View
    {
        $scores = TourRubricScore::with('tour:id,title,destination')
            ->where('rubric_version', Rubric::VERSION)
            ->orderByRaw("review_status = 'needs_review' DESC")
            ->orderByDesc('scored_at')
            ->paginate(50);

        $nullReport = TourRubricScore::with('tour:id,title')
            ->where('rubric_version', Rubric::VERSION)
            ->get()
            ->map(fn ($s) => ['score' => $s, 'nulls' => $s->nullDimensions()])
            ->filter(fn ($row) => $row['nulls'] !== [])
            ->values();

        return view('admin.rubric-review', [
            'scores' => $scores,
            'nullReport' => $nullReport,
            'dimensions' => Rubric::dimensions(),
        ]);
    }

    public function approve(Request $request, TourRubricScore $score): RedirectResponse
    {
        $score->update(['review_status' => 'approved']);

        return back()->with('success', 'Puan onaylandı.');
    }
}
