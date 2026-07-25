<?php

namespace App\Http\Controllers;

use App\Services\AiSearch\ConversationService;
use App\Services\AiSearch\RecreationQuizService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Tatil Karakteri Testi uç noktaları — tamamen LLM'siz. Test sonucu konuşma
 * meta'sına (ve girişli kullanıcıya) yazılır; sonrasındaki arama normal LLM
 * hattından akarken recreation bonusu bu profilden okunur.
 */
class RecreationQuizController extends Controller
{
    public function __construct(
        private readonly RecreationQuizService $quiz,
        private readonly ConversationService $conversations,
    ) {}

    /** Soru tanımı — ağırlık içermez, ön yüz veri-güdümlü render eder. */
    public function definition(): JsonResponse
    {
        return response()->json($this->quiz->definition());
    }

    /**
     * Cevapları puanlar, profili kalıcılaştırır ve otomatik takip aramasında
     * kullanılacak doğal sorguyu döndürür. Konuşma yoksa açılır — test,
     * sohbetin ilk etkileşimi olabilir.
     */
    public function submit(Request $request): JsonResponse
    {
        $validated = $request->validate([
            // array:... beyaz listesi: bilinmeyen anahtarlar (depolama şişirme
            // vektörü — answers ham haliyle profile yazılır) 422 ile reddedilir
            'answers' => ['required', 'array:s1,s2,s3,s4,s5,s6,s7,s8a,s8b,s9'],
            'answers.s1' => ['required', 'integer', 'between:0,100'],
            'answers.s2' => ['required', 'array', 'min:1', 'max:3'],
            'answers.s2.*' => ['string', 'max:20'],
            'answers.s3' => ['required', 'integer', 'between:0,100'],
            'answers.s4' => ['required', 'integer', 'between:0,3'],
            'answers.s5' => ['required', 'integer', 'between:0,3'],
            'answers.s6' => ['required', 'integer', 'between:0,3'],
            'answers.s7' => ['required', 'integer', 'between:0,2'],
            'answers.s8a' => ['nullable', 'integer', 'between:0,2'],
            'answers.s8b' => ['nullable', 'integer', 'between:0,2'],
            'answers.s9' => ['required', 'integer', 'between:0,2'],
            'adjusted_vector' => ['nullable', 'array'],
            'adjusted_vector.*' => ['numeric', 'between:0,1'],
            'conversation_uuid' => ['nullable', 'uuid'],
        ]);

        $answers = $validated['answers'];
        $result = $this->quiz->score($answers);

        // Onay adımındaki ± düzeltmeleri: yalnız bilinen eksenler, [0,1] aralığında.
        // Kullanıcı düzeltmesi ham skordan değerlidir (litertürde %90 düzeltme oranı).
        foreach (($validated['adjusted_vector'] ?? []) as $axis => $value) {
            if (array_key_exists($axis, $result['vector'])) {
                $result['vector'][$axis] = round(max(0.0, min(1.0, (float) $value)), 2);
            }
        }
        $result['persona'] = $this->quiz->personaFor($result['vector']);

        $conversation = $this->conversations->startOrLoad($request, $validated['conversation_uuid'] ?? null);

        // Mesaj turlarıyla aynı konuşma kilidi: current_intent read-modify-write'ı
        // süren bir arama turunun yazdığını ezmesin (lost update).
        $lock = \Illuminate\Support\Facades\Cache::lock('ai_conv_lock:'.$conversation->id, 10);
        try {
            $lock->block(5);
            try {
                $conversation->refresh();
                $profile = $this->quiz->store($request->user(), $conversation, $result, $answers);
            } finally {
                $lock->release();
            }
        } catch (\Illuminate\Contracts\Cache\LockTimeoutException) {
            return response()->json(['message' => 'Sohbet şu an meşgul — birkaç saniye sonra tekrar dener misin?'], 429);
        }

        return response()->json([
            'persona' => $profile['persona'],
            'vector' => $profile['vector'],
            'tempo' => $profile['tempo'],
            'conversation_uuid' => $conversation->uuid,
            'followup_query' => $this->quiz->followupQuery($profile['vector'], $profile['tags']),
        ]);
    }
}
