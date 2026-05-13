<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class AiSearchConversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'user_id',
        'session_id',
        'title',
        'current_intent',
        'last_result_tour_ids',
        'last_message_at',
    ];

    protected $casts = [
        'current_intent' => 'array',
        'last_result_tour_ids' => 'array',
        'last_message_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (AiSearchConversation $conversation) {
            if (empty($conversation->uuid)) {
                $conversation->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AiSearchMessage::class, 'conversation_id')->orderBy('id');
    }
}
