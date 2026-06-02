<?php

namespace App\Jobs;

use App\Models\KnowledgeChunk;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use OpenAI\Laravel\Facades\OpenAI;
use Illuminate\Support\Facades\Log;

class GenerateKnowledgeEmbeddingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [10, 30, 60];

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
            $response = OpenAI::embeddings()->create([
                'model' => config('ai.embedding_model', 'text-embedding-3-small'),
                'input' => $chunk->content,
            ]);

            $embedding = $response->embeddings[0]->embedding;

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
