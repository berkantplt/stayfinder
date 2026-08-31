<?php

namespace App\Services\AiSearch;

use App\Support\EmbeddingClient;
use Illuminate\Support\Facades\Cache;

/**
 * Sorgu embedding'lerini önbellekten sunar: aynı metin (aynı modelle) 24 saat
 * boyunca yeniden embed edilmez. Popüler aramalar ("vizesiz turlar" vb.)
 * tekrarlandığında hem API maliyeti hem gecikme sıfırlanır. Arama hattındaki
 * iki embedding noktası (tur benzerliği + RAG) bu tek kapıdan geçer.
 */
class QueryEmbeddingCache
{
    private const TTL_SECONDS = 86400;

    /** @return array<int, float> */
    public function vector(string $text): array
    {
        // Model adı anahtara girer: model değişirse eski vektörler kullanılmaz.
        $model = config('ai.embedding_model', 'text-embedding-3-small');
        $key = 'ai:emb:'.md5($model.'|'.$text);

        return Cache::remember($key, self::TTL_SECONDS, fn () => EmbeddingClient::embed($text));
    }
}
