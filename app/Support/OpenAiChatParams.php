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
    public static function json(string $model, array $messages, int $maxTokens): array
    {
        $params = [
            'model' => $model,
            'messages' => $messages,
            'response_format' => ['type' => 'json_object'],
        ];

        if (self::isReasoningModel($model)) {
            $params['max_completion_tokens'] = $maxTokens + 4000;
            $params['reasoning_effort'] = 'low';
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
