<?php

namespace App\Http\Controllers;

use App\Services\Matching\QuizEvaluator;
use App\Services\Matching\Rubric;
use App\Services\Matching\TourMatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Tur eşleştirme testi (brif v1) — tamamen LLM'siz.
 *
 * KAPSAM KISITI: öneri yalnızca O ANKİ test cevaplarından üretilir. Geçmiş
 * satın alma, gezinme, çerez profili veya eski test sonucu KULLANILMAZ.
 * Sonuç oturumda tutulur, kalıcı kullanıcı profiline YAZILMAZ (KVKK notu:
 * bu kısıt aydınlatma/rıza yükünü de düşürür).
 */
class RecreationQuizController extends Controller
{
    public function __construct(
        private readonly QuizEvaluator $evaluator,
        private readonly TourMatcher $matcher,
    ) {}

    /** Soru tanımı (quiz/v1.json) — şık→boyut değerleri sunucu dosyasından gelir. */
    public function definition(): JsonResponse
    {
        return response()->json(QuizEvaluator::definition());
    }

    /** Cevaplar → vektör+ağırlık → sert filtre → eşleştirme → ilk 3 + gerekçe. */
    public function submit(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'answers' => ['required', 'array:q1,q2,q3,q4,q5,q6,q7,q8'],
            'answers.q1' => ['nullable', 'integer', 'between:0,5'],
            'answers.q2' => ['nullable', 'integer', 'between:0,5'],
            'answers.q3' => ['nullable', 'integer', 'between:0,5'],
            'answers.q4' => ['nullable', 'integer', 'between:0,5'],
            'answers.q5' => ['nullable', 'integer', 'between:0,5'],
            'answers.q6' => ['nullable', 'integer', 'between:0,5'],
            'answers.q7' => ['nullable', 'integer', 'between:0,5'],
            'answers.q8' => ['nullable', 'array', 'max:2'],
            'answers.q8.*' => ['integer', 'between:0,7'],
            'baglam' => ['nullable', 'array:aylar,gun_min,gun_max,butce_max_try,kalkis_sehri,erisilebilirlik'],
            'baglam.aylar' => ['nullable', 'array', 'max:12'],
            'baglam.aylar.*' => ['integer', 'between:1,12'],
            'baglam.gun_min' => ['nullable', 'integer', 'between:1,60'],
            'baglam.gun_max' => ['nullable', 'integer', 'between:1,60'],
            'baglam.butce_max_try' => ['nullable', 'numeric', 'min:0'],
            'baglam.kalkis_sehri' => ['nullable', 'string', 'max:60'],
            'baglam.erisilebilirlik' => ['nullable', 'boolean'],
        ]);

        $profil = $this->evaluator->evaluate($validated['answers']);
        $sonuc = $this->matcher->match($profil, $validated['baglam'] ?? []);

        // %60 tabanı (brif §6): altındakiler "önerilen" değil "en yakınlar"dır.
        // Karşılıksız profiller ürün açığını gösterir → loglanır.
        if ($sonuc['below_floor']) {
            // KVKK: ham tercih değerleri loglanmaz (fiziksel/erişilebilirlik
            // sağlık verisi vekilidir). Ürün açığını görmeye bant yeter; kullanıcı
            // veya oturum kimliği hiç yazılmaz.
            $bant = fn (float $v) => $v < 34 ? 'dusuk' : ($v > 66 ? 'yuksek' : 'orta');
            \Illuminate\Support\Facades\Log::info('[RubricMatch] Taban altı eşleşme', [
                'profil_bantlari' => array_map($bant, $profil['degerler']),
                'aktif_boyutlar' => array_keys(array_filter($profil['agirliklar'], fn ($w) => $w > 0)),
                'en_iyi_skor' => $sonuc['tours'][0]['match_percent'] ?? null,
            ]);
        }

        // Oturum bazlı saklama — kalıcı profile YAZILMAZ (kapsam kısıtı)
        $request->session()->put('recreation_quiz_result', [
            'quiz_version' => QuizEvaluator::QUIZ_VERSION,
            'rubric_version' => Rubric::VERSION,
            'at' => now()->toIso8601String(),
        ]);

        return response()->json([
            'rubric_version' => Rubric::VERSION,
            'tours' => $sonuc['tours'],
            'below_floor' => $sonuc['below_floor'],
            'relaxation_notes' => $sonuc['relaxation_notes'],
            'message' => $sonuc['below_floor']
                ? 'Aradığın profile tam uyan turumuz yok — en yakınları bunlar.'
                : 'Cevaplarına göre en iyi eşleşmeler bunlar 🧭',
        ]);
    }
}
