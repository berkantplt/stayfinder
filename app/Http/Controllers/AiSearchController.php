<?php

namespace App\Http\Controllers;

use App\Models\AiSearchLog;
use App\Models\Tour;
use App\Services\AiSearch\ClarificationAdvisor;
use App\Services\AiSearch\TourSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * AI arama HTTP uçları — ince katman. Arama motoru (niyet + skorlama + gevşetme
 * merdiveni + yorum üretimi) TourSearchService'te; netleştirme mantığı
 * ClarificationAdvisor'da.
 *
 * Sohbet (v1 konuşma asistanı) kaldırıldı: burada kalan uçlar durumsuz arama
 * uçlarıdır — JSON arama (searchApi), tüm sonuçlar sayfası (showResults) ve
 * negatif geri bildirim (rejectTour).
 */
class AiSearchController extends Controller
{
    public function __construct(
        private readonly TourSearchService $search,
        private readonly ClarificationAdvisor $clarifier,
    ) {
    }

    /**
     * Log sahipliği kontrolü: auth user için user_id, anonim için session_id
     * eşleşmeli — showResults ve rejectTour'un ortak bekçisi (403 davranışı aynı).
     */
    private function authorizeLogOwnership(Request $request, AiSearchLog $log): void
    {
        $userId = $request->user()?->id;
        if ($log->user_id !== null) {
            abort_if($log->user_id !== $userId, 403);
        } else {
            abort_if((string) $log->session_id !== (string) $request->session()->getId(), 403);
        }
    }

    /**
     * "Tüm eşleşen turları gör" sayfası: aramada en isabetli 7 tur gösterilir,
     * bu sayfa aramanın eşleştirdiği TÜM turları uyum sırasıyla listeler.
     */
    public function showResults(Request $request, AiSearchLog $log)
    {
        $this->authorizeLogOwnership($request, $log);

        $ids = collect($log->result_tour_ids ?? [])->map(fn ($v) => (int) $v)->values();
        abort_if($ids->isEmpty(), 404);

        // Skor haritası: uyum yüzdesi kartlarda gösterilir, sıralama korunur
        $scores = collect($log->result_scores ?? [])->keyBy('tour_id');

        $tours = Tour::with('agency')
            ->whereIn('id', $ids)
            ->active()
            ->get()
            ->keyBy('id');

        $orderedTours = $ids
            ->map(fn ($id) => $tours->get($id))
            ->filter()
            ->values()
            ->map(function ($tour, $index) use ($scores) {
                $tour->compatibility_score = $scores[$tour->id]['compatibility_score'] ?? null;
                $tour->rank = $index + 1;

                return $tour;
            });

        return view('tours.ai-results', [
            'results' => $orderedTours,
            'aiComment' => null,
            'logId' => $log->id,
            'query' => $log->raw_query,
        ]);
    }

    /**
     * Negatif feedback — kullanıcı bir önerinin "uymadığını" işaretler.
     * Log'a tour_id + reason eklenir; performAiSearch sonraki aramalarda bu turu
     * filtreler ve embedding olarak benzer turları cezalandırır.
     */
    public function rejectTour(Request $request, AiSearchLog $log): JsonResponse
    {
        $this->authorizeLogOwnership($request, $log);

        $validated = $request->validate([
            'tour_id' => 'required|integer|exists:tours,id',
            'reason' => 'nullable|string|in:'.implode(',', AiSearchLog::REJECTION_REASONS),
        ]);

        // Sadece bu log'un result_tour_ids'inde olan turlar reddedilebilir
        // (kullanıcının "şu listeden bu uymaz" feedback'i; rastgele tour reddi engellenir)
        $resultIds = collect($log->result_tour_ids ?? [])->map(fn ($v) => (int) $v)->all();
        if (! in_array((int) $validated['tour_id'], $resultIds, true)) {
            return response()->json([
                'error' => 'Bu tur bu arama sonuçlarında yok.',
            ], 422);
        }

        $log->recordRejection((int) $validated['tour_id'], $validated['reason'] ?? null);

        return response()->json([
            'ok' => true,
            'rejected_tour_ids' => $log->fresh()->rejectedTourIds(),
        ]);
    }

    /**
     * Tek-shot JSON arama ucu — sohbetten bağımsızdır, durum tutmaz.
     * Niyet çok genelse arama yapılmaz; `aiComment`'a netleştirme sorusu yazılır
     * ve `results: []` döner. Netleştirme durumu (sayaç, biriken bağlam, son
     * sorulan soru) OTURUMDA tutulur — konuşma kaydı yoktur.
     */
    public function searchApi(Request $request)
    {
        // Kart/TC gibi hassas numaralar loglara ve LLM'e ham gitmesin
        $query = \App\Services\AiSearch\PiiMasker::mask((string) $request->input('q', ''))['text'];

        // Uç stateless olduğundan soru sayacı ve soru-cevap bağlamı session'da
        // tutulur — aksi halde her dürüst tek-eksenli cevap ("40 bin") sonsuza dek
        // yeni bir netleştirme sorusuyla karşılanıyor ve arama hiç çalışmıyordu.
        $askedCount = (int) $request->session()->get('ai_widget_clarifications', 0);
        $context = trim((string) $request->session()->get('ai_widget_context', ''));
        $lastQuestion = trim((string) $request->session()->get('ai_widget_last_question', ''));
        $combinedQuery = trim($context.' '.$query);

        if (trim($query) !== '' && $askedCount < ClarificationAdvisor::MAX_CLARIFICATIONS) {
            $clarification = $this->clarifier->maybeAskClarification($combinedQuery, []);

            // Aynı soru tekrar oluştuysa kullanıcının cevabı yeni eksen eklememiş
            // demektir ("farketmez", "bilmem") — tekrarlamak yerine eldekiyle ara
            if ($clarification !== null && trim($clarification) === $lastQuestion) {
                $clarification = null;
            }

            if ($clarification !== null) {
                $request->session()->put('ai_widget_clarifications', $askedCount + 1);
                $request->session()->put('ai_widget_context', Str::limit($combinedQuery, 500, ''));
                $request->session()->put('ai_widget_last_question', trim($clarification));

                return response()->json([
                    'aiComment' => $clarification,
                    'results' => [],
                    'is_clarification' => true,
                    'log_id' => null,
                ]);
            }
        }

        // Arama yapılıyor: sayaç ve bağlam sıfırlanır (soru-cevap bilgisi sorguya taşındı)
        $request->session()->forget(['ai_widget_clarifications', 'ai_widget_context', 'ai_widget_last_question']);

        $data = $this->search->performAiSearch($request, $combinedQuery !== '' ? $combinedQuery : $query);
        if (isset($data['error'])) {
            return response()->json(['error' => $data['error']], 500);
        }

        return response()->json($this->publicSearchPayload($data));
    }

    /**
     * performAiSearch çıktısındaki İÇ alanları (tam Tour/Agency modelleri —
     * embedding vektörleri, search_text, acenta e-posta/onay notları — ve
     * ham intent) dış yanıttan ayıklar. Yalnızca istemcinin ihtiyacı olan
     * beyaz-listedeki alanlar döner.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function publicSearchPayload(array $data): array
    {
        $whitelist = [
            'results', 'aiComment', 'log_id', 'relaxation_note',
            'total_matches', 'all_results_url', 'applied_filters',
            'latency_ms',
        ];

        return array_intersect_key($data, array_flip($whitelist));
    }
}
