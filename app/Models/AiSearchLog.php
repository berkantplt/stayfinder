<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiSearchLog extends Model
{
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
        'latency_ms',
        'selected_tour_id',
        'selected_rank',
        'selected_at',
        'conversation_id',
        'parent_log_id',
        'turn_type',
        'ai_comment',
    ];

    protected $casts = [
        'intent' => 'array',
        'applied_filters' => 'array',
        'result_tour_ids' => 'array',
        'result_scores' => 'array',
        'selected_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function selectedTour(): BelongsTo
    {
        return $this->belongsTo(Tour::class, 'selected_tour_id');
    }
}

