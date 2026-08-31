<?php

namespace App\Support;

/**
 * LLM chat yanıtını JSON'a çözen ortak kapı. Üç AI job'ındaki
 * "content al + json_decode + dizi değilse aynı hata" kalıbının tek kaynağı.
 *
 * Davranış sözleşmesi (mevcut job'larla birebir):
 * - content null/boş ise json_decode null döner → RuntimeException fırlar
 *   (bu yüzden '?? {}' DEĞİL '?? \'\'' kullanılır: boş içerik hata sayılır,
 *   sessizce boş diziye düşmez).
 * - İstisna tipi ve metni üç job'daki ile aynı: RuntimeException('LLM JSON parse hatası').
 * - Loglama çağıranda kalır (her job kendi bağlamıyla loglar).
 */
class AiJson
{
    /**
     * @param  mixed  $response  OpenAI chat yanıt nesnesi (choices[0]->message->content okunur)
     * @return array<mixed>
     */
    public static function decode(mixed $response): array
    {
        $payload = json_decode($response->choices[0]->message->content ?? '', true);

        if (! is_array($payload)) {
            throw new \RuntimeException('LLM JSON parse hatası');
        }

        return $payload;
    }
}
