<?php

namespace App\Support;

/**
 * Chat Completions parametrelerini model ailesine göre kurar. gpt-5 / o-serisi
 * reasoning modelleri max_tokens ve özel temperature kabul etmez (400 döner):
 * max_completion_tokens (görünmez düşünme tokenları da bu tavandan yediği için
 * pay eklenir) + reasoning_effort=low kullanılır — kısa yapılandırılmış işlerde
 * düşünme şişmesini (maliyet + gecikme) önler. TourUrlImporter::chatParams ile
 * aynı kural; intent + job çağrıları için ortak kapı.
 */
final class OpenAiChatParams
{
    /**
     * JSON çıktılı chat çağrısı parametreleri.
     *
     * @param  array<int, array<string, string>>  $messages
     * @return array<string, mixed>
     */
    public static function json(string $model, array $messages, int $maxTokens, ?float $temperature = null): array
    {
        $params = [
            'model' => $model,
            'messages' => $messages,
            'response_format' => ['type' => 'json_object'],
        ];

        if (self::isReasoningModel($model)) {
            // Reasoning ailesi temperature kabul etmez — istense de gönderilmez
            $params['max_completion_tokens'] = $maxTokens + 4000;
            $params['reasoning_effort'] = 'low';
        } else {
            $params['max_tokens'] = $maxTokens;
            if ($temperature !== null) {
                $params['temperature'] = $temperature;
            }
        }

        return $params;
    }

    /**
     * Araç çağırma (tool calling) parametreleri.
     *
     * json()'dan farkı: response_format GÖNDERİLMEZ. json_object ile tools
     * birlikte gönderilince model içeriği JSON'a zorlanır ve araç akışı bozulur.
     *
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<int, array<string, mixed>>  $tools  OpenAI function şemaları
     * @param  string|array<string, mixed>  $toolChoice  'auto' | 'none' | 'required' | {type:function,...}
     * @return array<string, mixed>
     */
    public static function tools(
        string $model,
        array $messages,
        array $tools,
        int $maxTokens,
        string|array $toolChoice = 'auto',
        string $reasoningEffort = 'low',
        bool $parallelToolCalls = true,
    ): array {
        $params = [
            'model' => $model,
            'messages' => $messages,
            'tools' => $tools,
            'tool_choice' => $toolChoice,
        ];

        // Araçsız çağrıda (son cevap turu) tool_choice göndermenin anlamı yok
        if ($tools === []) {
            unset($params['tools'], $params['tool_choice']);
        } elseif ($parallelToolCalls) {
            // Bağımsız araçlar tek turda paralel çağrılsın — gecikme yarıya iner
            $params['parallel_tool_calls'] = true;
        }

        if (self::isReasoningModel($model)) {
            $params['max_completion_tokens'] = $maxTokens + 4000;
            $params['reasoning_effort'] = $reasoningEffort;
        } else {
            $params['max_tokens'] = $maxTokens;
        }

        return $params;
    }

    public static function isReasoningModel(string $model): bool
    {
        return (bool) preg_match('/^(gpt-5|o\d)/i', $model);
    }
}
