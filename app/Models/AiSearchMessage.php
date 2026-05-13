<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiSearchMessage extends Model
{
    use HasFactory;

    public const ROLE_USER = 'user';
    public const ROLE_ASSISTANT = 'assistant';

    protected $fillable = [
        'conversation_id',
        'role',
        'content',
        'intent_snapshot',
        'result_tour_ids',
        'result_scores',
        'latency_ms',
    ];

    protected $casts = [
        'intent_snapshot' => 'array',
        'result_tour_ids' => 'array',
        'result_scores' => 'array',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiSearchConversation::class, 'conversation_id');
    }
}
