<?php

namespace App\Services;

use App\Models\KnowledgeChunk;
use OpenAI\Laravel\Facades\OpenAI;
use Illuminate\Support\Facades\Log;

class KnowledgeService
{
    /**
     * Kullanıcı sorusuna en yakın bilgi parçalarını bulur.
     * $queryVector verilirse yeniden embedding çağrısı yapılmaz.
     * Düşük skorlu (alakasız) chunk'lar gürültü olmasın diye eşiklenir.
     */
    public function findRelevantChunks(
        string $query,
        int $limit = 5,
        ?array $queryVector = null,
        float $minScore = 0.30
    ): array {
        try {
            if ($queryVector === null) {
                $response = OpenAI::embeddings()->create([
                    'model' => 'text-embedding-3-small',
                    'input' => $query,
                ]);
                $queryVector = $response->embeddings[0]->embedding;
            }

            $chunks = KnowledgeChunk::whereNotNull('embedding')->get();

            $results = [];
            foreach ($chunks as $chunk) {
                $score = $this->cosineSimilarity($queryVector, $chunk->embedding);
                if ($score < $minScore) {
                    continue;
                }
                $results[] = ['score' => $score, 'chunk' => $chunk];
            }

            usort($results, fn($a, $b) => $b['score'] <=> $a['score']);

            return array_slice($results, 0, $limit);
        } catch (\Throwable $e) {
            Log::error('[KnowledgeService] Arama hatası: ' . $e->getMessage());
            return [];
        }
    }

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

    private function cosineSimilarity(array $vecA, array $vecB): float
    {
        if (count($vecA) !== count($vecB) || empty($vecA)) {
            return 0.0;
        }

        $dotProduct = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        foreach ($vecA as $i => $val) {
            $b = (float) ($vecB[$i] ?? 0.0);
            $dotProduct += $val * $b;
            $normA += $val * $val;
            $normB += $b * $b;
        }

        if ($normA == 0.0 || $normB == 0.0) return 0.0;

        return $dotProduct / (sqrt($normA) * sqrt($normB));
    }
}
