<?php

namespace App\Jobs;

use App\Models\Destination;
use App\Services\DestinationResearcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ResearchDestinationJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 120;
    public array $backoff = [30, 120];

    public function __construct(
        public int $destinationId,
        public bool $force = false,
    ) {}

    public function uniqueId(): string
    {
        return 'destination-research-' . $this->destinationId;
    }

    public function uniqueFor(): int
    {
        return 300;
    }

    public function handle(DestinationResearcher $researcher): void
    {
        $destination = Destination::find($this->destinationId);
        if (!$destination) {
            Log::warning('[ResearchDestinationJob] Destinasyon bulunamadı', ['id' => $this->destinationId]);
            return;
        }

        if (!$this->force && $destination->aiResearchIsFresh()) {
            return;
        }

        $destination->forceFill([
            'ai_research_status' => 'researching',
        ])->saveQuietly();

        $result = $researcher->research($destination->name, $destination->country);

        if ($result === null) {
            $destination->forceFill([
                'ai_research_status' => 'failed',
            ])->saveQuietly();
            return;
        }

        $destination->forceFill([
            'ai_research' => $result,
            'ai_research_updated_at' => now(),
            'ai_research_status' => 'ok',
        ])->saveQuietly();

        Log::info('[ResearchDestinationJob] Tamamlandı', [
            'destination' => $destination->name,
            'must_see_count' => count($result['must_see'] ?? []),
        ]);
    }
}
