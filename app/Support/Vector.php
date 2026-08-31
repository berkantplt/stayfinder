<?php

namespace App\Support;

/**
 * Embedding vektör matematiği — cosine similarity'nin tek kaynağı.
 * (Önceden KnowledgeService ve AiSearchController'da iki ayrı kopya vardı;
 * controller'daki kopyada boyut koruması yoktu.)
 */
final class Vector
{
    /**
     * İki vektör arasındaki Cosine Similarity.
     *
     * Boyut uyuşmazlığı (model değişimi → eski embedding farklı boyutta):
     * sessizce bozuk skor üretmek yerine 0 dön. Aksi halde $vecB[$i] tanımsız
     * olur, PHP 8'de uyarı + yanlış benzerlik skoru çıkar.
     *
     * @param  array<int, float|int>  $vecA
     * @param  array<int, float|int>  $vecB
     */
    public static function cosine(array $vecA, array $vecB): float
    {
        if (count($vecA) !== count($vecB) || $vecA === []) {
            return 0.0;
        }

        $dotProduct = 0;
        $normA = 0;
        $normB = 0;

        foreach ($vecA as $i => $val) {
            $dotProduct += $val * $vecB[$i];
            $normA += $val * $val;
            $normB += $vecB[$i] * $vecB[$i];
        }

        if ($normA == 0 || $normB == 0) {
            return 0.0;
        }

        return $dotProduct / (sqrt($normA) * sqrt($normB));
    }
}
