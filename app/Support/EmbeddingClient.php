<?php

namespace App\Support;

use OpenAI\Laravel\Facades\OpenAI;

/**
 * Embedding üretiminin tek kapısı: model config okuma + OpenAI çağrısı +
 * embedding dizisini dönme. Başka hiçbir şey yapmaz — retry job katmanında
 * (tries/backoff), hata yakalama/loglama çağıranda kalır; API istisnası
 * aynen yukarı fırlar.
 *
 * Kullanım ayrımı:
 * - Sorgu tarafı (arama hattı) cache için QueryEmbeddingCache'ten geçer;
 *   onun cache katmanı içeride bu embed()'i çağırır.
 * - İndeksleme tarafı (job'lar / app:generate-tour-embeddings komutu)
 *   doğrudan embed() çağırır — cache gerekmez, her içerik bir kez işlenir.
 */
class EmbeddingClient
{
    /** @return array<int, float> */
    public static function embed(string $text): array
    {
        $response = OpenAI::embeddings()->create([
            'model' => config('ai.embedding_model', 'text-embedding-3-small'),
            'input' => $text,
        ]);

        return $response->embeddings[0]->embedding;
    }
}
