<?php

namespace App\Services;

use App\Models\KnowledgeChunk;
use App\Services\AiSearch\QueryEmbeddingCache;
use Illuminate\Support\Facades\Log;

class KnowledgeService
{
    /**
     * Kullanıcı sorusuna en yakın bilgi parçalarını bulur.
     */
    public function findRelevantChunks(string $query, int $limit = 5): array
    {
        try {
            // 1. Sorunun embedding'ini al (önbellekli — tekrar eden sorgular API'ye gitmez)
            $queryVector = app(QueryEmbeddingCache::class)->vector($query);

            // 2. Tüm vektörlü chunk'ları getir (Şu an sayı az olduğu için PHP ile dot product yapıyoruz)
            $chunks = KnowledgeChunk::whereNotNull('embedding')->get();
            
            $results = [];
            foreach ($chunks as $chunk) {
                $score = $this->cosineSimilarity($queryVector, $chunk->embedding);
                $results[] = [
                    'score' => $score,
                    'chunk' => $chunk
                ];
            }

            // 3. Skora göre sırala ve limitli dön
            usort($results, fn($a, $b) => $b['score'] <=> $a['score']);

            return array_slice($results, 0, $limit);

        } catch (\Exception $e) {
            Log::error("[KnowledgeService] Arama hatası: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Bulunan parçaları GPT için bir context metnine dönüştürür.
     */
    public function buildContext(array $relevantChunks): string
    {
        if (empty($relevantChunks)) return "Ek bilgi bulunamadı.";

        $context = "İşte site içerisinden bulduğum alakalı bilgiler:\n\n";
        foreach ($relevantChunks as $item) {
            $chunk = $item['chunk'];
            $context .= "--- KAYNAK ({$chunk->source_type}): {$chunk->title} ---\n";
            $context .= "{$chunk->content}\n\n";
        }

        return $context;
    }

    /**
     * İki vektör arasındaki Cosine Similarity'yi hesaplar.
     */
    private function cosineSimilarity(array $vecA, array $vecB): float
    {
        $dotProduct = 0;
        $normA = 0;
        $normB = 0;

        foreach ($vecA as $i => $val) {
            $dotProduct += $val * $vecB[$i];
            $normA += $val * $val;
            $normB += $vecB[$i] * $vecB[$i];
        }

        if ($normA == 0 || $normB == 0) return 0;

        return $dotProduct / (sqrt($normA) * sqrt($normB));
    }
}
