<?php

namespace App\Observers;

use App\Console\Commands\SyncKnowledgeBase;
use App\Models\Destination;
use App\Models\KnowledgeChunk;
use Illuminate\Support\Facades\Log;

class DestinationObserver
{
    public function saved(Destination $destination): void
    {
        if (!$destination->is_active) {
            KnowledgeChunk::where('source_type', 'destination')->where('source_id', $destination->id)->delete();
            return;
        }

        $relevant = ['name', 'country', 'highlights', 'description', 'is_active'];
        if ($destination->exists && !$destination->wasRecentlyCreated) {
            $changed = false;
            foreach ($relevant as $field) {
                if ($destination->wasChanged($field)) {
                    $changed = true;
                    break;
                }
            }
            if (!$changed) return;
        }

        $content = "Destinasyon: {$destination->name}\n" .
            "Ülke: {$destination->country}\n" .
            "Öne Çıkanlar: {$destination->highlights}\n" .
            "Bilgi: " . strip_tags((string) $destination->description);

        Log::info("[DestinationObserver] Knowledge sync: destination #{$destination->id}");
        SyncKnowledgeBase::syncSingle('destination', $destination->id, $destination->name, $content);
    }

    public function deleted(Destination $destination): void
    {
        KnowledgeChunk::where('source_type', 'destination')->where('source_id', $destination->id)->delete();
    }
}
