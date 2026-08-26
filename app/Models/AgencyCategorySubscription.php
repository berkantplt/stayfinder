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
        'extra_tour_slots',
        'auto_renew',
        'cancelled_at',
        'next_extra_tour_slots',
        'renewal_attempted_at',
        'status',
        'started_at',
        'expires_at',
        'renewal_reminder_sent_at',
    ];

    protected $casts = [
        'monthly_price' => 'decimal:2',
        'extra_tour_slots' => 'integer',
        'auto_renew' => 'boolean',
        'cancelled_at' => 'datetime',
        'next_extra_tour_slots' => 'integer',
        'renewal_attempted_at' => 'date',
        'started_at' => 'date',
        'expires_at' => 'date',
        'renewal_reminder_sent_at' => 'datetime',
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

    /** Acenta iptal etti: dönem sonuna kadar kullanım sürer, yenileme yapılmaz. */
    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null || ! (bool) ($this->auto_renew ?? true);
    }

    /** Yeni dönemde geçerli olacak ekstra hak sayısı (azaltma planı varsa o). */
    public function slotsAfterRenewal(): int
    {
        return (int) ($this->next_extra_tour_slots ?? $this->extra_tour_slots ?? 0);
    }
}
