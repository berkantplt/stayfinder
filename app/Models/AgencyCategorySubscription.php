<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgencyCategorySubscription extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'agency_id',
        'category_id',
        'last_order_id',
        'monthly_price',
        'status',
        'started_at',
        'expires_at',
    ];

    protected $casts = [
        'monthly_price' => 'decimal:2',
        'started_at' => 'date',
        'expires_at' => 'date',
    ];

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function lastOrder(): BelongsTo
    {
        return $this->belongsTo(AgencyCategoryOrder::class, 'last_order_id');
    }

    public function scopeActive($query)
    {
        return $query
            ->where('status', self::STATUS_ACTIVE)
            ->whereDate('expires_at', '>=', now()->toDateString());
    }

    public function getIsActiveAttribute(): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && $this->expires_at !== null
            && $this->expires_at->greaterThanOrEqualTo(today());
    }
}
