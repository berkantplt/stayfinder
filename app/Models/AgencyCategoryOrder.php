<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AgencyCategoryOrder extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    public const PROVIDER_IYZICO = 'iyzico';

    public const PROVIDER_MANUAL = 'manual';

    public const BUYER_INDIVIDUAL = 'individual';

    public const BUYER_CORPORATE = 'corporate';

    protected $fillable = [
        'agency_id',
        'order_number',
        'billing_cycle',
        'subtotal',
        'currency',
        'status',
        'payment_provider',
        'provider_token',
        'provider_payment_id',
        'buyer_type',
        'buyer_snapshot',
        'purchased_at',
        'paid_at',
        'failure_reason',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'purchased_at' => 'datetime',
        'paid_at' => 'datetime',
        // TC kimlik dahil kişisel veri — at-rest şifreli (APP_KEY'e bağlı)
        'buyer_snapshot' => 'encrypted:array',
    ];

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(AgencyCategoryOrderItem::class, 'order_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function isFailed(): bool
    {
        return in_array($this->status, [self::STATUS_FAILED, self::STATUS_CANCELLED], true);
    }
}
