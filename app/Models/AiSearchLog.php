<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiSearchLog extends Model
{
    public const REJECTION_REASONS = [
        'too_expensive',
        'wrong_destination',
        'wrong_vibe',
        'other',
    ];

    protected $fillable = [
        'user_id',
        'session_id',
        'raw_query',
        'normalized_query',
        'intent',
        'applied_filters',
        'candidate_count',
        'result_tour_ids',
        'result_scores',
        'near_misses',
        'latency_ms',
        'selected_tour_id',
        'selected_rank',
        'selected_at',
        'rejected_tour_ids',
        'rejected_at',
    ];

    protected $casts = [
        'intent' => 'array',
        'applied_filters' => 'array',
        'result_tour_ids' => 'array',
        'result_scores' => 'array',
        'near_misses' => 'array',
        'rejected_tour_ids' => 'array',
        'selected_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function selectedTour(): BelongsTo
    {
        return $this->belongsTo(Tour::class, 'selected_tour_id');
    }

    /**
     * Sadece reddedilen tur ID'lerini döndürür (reason hariç).
     *
     * @return array<int, int>
     */
    public function rejectedTourIds(): array
    {
        $entries = $this->rejected_tour_ids ?? [];

        return collect($entries)
            ->map(fn($e) => is_array($e) ? ($e['tour_id'] ?? null) : (int) $e)
            ->filter()
            ->map(fn($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    public function hasRejection(int $tourId): bool
    {
        return in_array($tourId, $this->rejectedTourIds(), true);
    }

    /**
     * Reject entry'sini idempotent ekler: aynı tour_id varsa reason update,
     * yoksa append. rejected_at always son reject zamanı.
     */
    public function recordRejection(int $tourId, ?string $reason): void
    {
        $entries = $this->rejected_tour_ids ?? [];
        $now = now()->toIso8601String();
        $found = false;

        $entries = collect($entries)->map(function ($e) use ($tourId, $reason, $now, &$found) {
            $eArr = is_array($e) ? $e : ['tour_id' => (int) $e];
            if ((int) ($eArr['tour_id'] ?? 0) === $tourId) {
                $found = true;
                $eArr['reason'] = $reason;
                $eArr['at'] = $now;
            }
            return $eArr;
        })->values()->all();

        if (!$found) {
            $entries[] = [
                'tour_id' => $tourId,
                'reason' => $reason,
                'at' => $now,
            ];
        }

        $this->update([
            'rejected_tour_ids' => $entries,
            'rejected_at' => now(),
        ]);
    }
}
