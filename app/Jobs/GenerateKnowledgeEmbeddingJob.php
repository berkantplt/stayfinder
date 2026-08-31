<?php

namespace App\Jobs;

use App\Models\KnowledgeChunk;
use App\Support\EmbeddingClient;
use Illuminate\Support\Facades\Log;

class GenerateKnowledgeEmbeddingJob extends AiQueueJob
{
    public function __construct(
        public int $chunkId
    ) {}

    public function handle(): void
    {
        $chunk = KnowledgeChunk::find($this->chunkId);

        if (!$chunk) {
            Log::warning("[KnowledgeJob] Chunk #{$this->chunkId} bulunamadı.");
            return;
        }

        try {
            $embedding = EmbeddingClient::embed($chunk->content);

            $chunk->update([
                'embedding' => $embedding
            ]);

            Log::debug("[KnowledgeJob] Chunk #{$chunk->id} ({$chunk->source_type}) vektörleştirildi.");

        } catch (\Exception $e) {
            Log::error("[KnowledgeJob] Hata: " . $e->getMessage());
            throw $e;
        }
    }
}
