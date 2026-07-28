<?php

namespace App\Services\Chat\Eval;

use App\Support\OpenAiChatParams;
use OpenAI\Laravel\Facades\OpenAI;

/**
 * Niteliksel değerlendirme: transkript senaryonun beklediği davranışı
 * karşılıyor mu? Deterministik ihlaller ScenarioRunner'da yakalanır; hakem
 * yalnız koda dökülemeyen kısmı (teşhis kurdu mu, dürüst köprü attı mı,
 * sezona aykırı öneri yaptı mı) değerlendirir.
 *
 * Hakem AYRI ve daha ucuz modelde koşar: değerlendirdiği botun kendisiyle
 * aynı model olması kendi hatasını onaylama eğilimi yaratır.
 */
class EvalJudge
{
    /**
     * @return array{gecti: bool, gerekce: string}
     */
    public function judge(array $senaryo, string $transkript, array $determistikIhlaller): array
    {
        // Deterministik ihlal varsa hakem'e sormaya gerek yok — kesin kalır
        if ($determistikIhlaller !== []) {
            return ['gecti' => false, 'gerekce' => 'Deterministik ihlal: '.implode(' | ', $determistikIhlaller)];
        }

        $prompt = $this->prompt($senaryo, $transkript);

        try {
            $response = OpenAI::chat()->create(OpenAiChatParams::json(
                config('ai.eval_judge_model', 'gpt-5.4-mini'),
                [
                    ['role' => 'system', 'content' => 'Sen bir chatbot değerlendirme hakemisin. Katı ol: '
                        .'beklenen davranış AÇIKÇA gözlenmiyorsa geçti=false ver. Sadece JSON döndür.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                600,
            ));

            $payload = json_decode($response->choices[0]->message->content ?? '', true);

            return [
                'gecti' => (bool) ($payload['gecti'] ?? false),
                'gerekce' => mb_substr((string) ($payload['gerekce'] ?? 'gerekçe yok'), 0, 400, 'UTF-8'),
            ];
        } catch (\Throwable $e) {
            return ['gecti' => false, 'gerekce' => 'Hakem çalışmadı: '.$e->getMessage()];
        }
    }

    private function prompt(array $senaryo, string $transkript): string
    {
        $beklenen = $senaryo['beklenen_davranis'] ?? '';
        $basarisizlik = $senaryo['basarisizlik_isareti'] ?? '';
        $madde = $senaryo['hangi_madde'] ?? '';

        return <<<PROMPT
        SENARYO: {$senaryo['ad']}
        SINANAN KURAL: {$madde}

        BEKLENEN DAVRANIŞ:
        {$beklenen}

        BAŞARISIZLIK İŞARETİ (bunlardan biri olduysa KESİNLİKLE geçti=false):
        {$basarisizlik}

        KONUŞMA TRANSKRİPTİ (araç çağrıları ve gösterilen kartlar dahil):
        {$transkript}

        Değerlendir. Şüphedeysen geçti=false ver.
        Çıktı: {"gecti": true|false, "gerekce": "<en fazla 2 cümle, somut kanıt göstererek>"}
        PROMPT;
    }
}
