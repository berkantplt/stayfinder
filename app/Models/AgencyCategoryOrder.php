<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AgencyCategoryOrder extends Model
{
    use HasFactory;

    public const STATUS_PAID = 'paid';

    protected $fillable = [
        'agency_id',
        'order_number',
        'billing_cycle',
        'subtotal',
        'currency',
        'status',
        'purchased_at',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'purchased_at' => 'datetime',
    ];

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(AgencyCategoryOrderItem::class, 'order_id');
    }
}
