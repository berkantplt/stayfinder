<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Campaign extends Model
{
    protected $fillable = ['tour_id', 'discount_price', 'label', 'starts_at', 'ends_at', 'is_active'];

    protected $casts = [
        'discount_price' => 'decimal:2',
        'starts_at'      => 'datetime',
        'ends_at'        => 'datetime',
        'is_active'      => 'boolean',
    ];

    public function tour(): BelongsTo { return $this->belongsTo(Tour::class); }

    public function isRunning(): bool
    {
        return $this->is_active && now()->between($this->starts_at, $this->ends_at);
    }

    public function getFormattedDiscountPriceAttribute(): string
    {
        $symbol = $this->tour?->currency_symbol ?? \App\Models\Tour::currencySymbol('TRY');
        return number_format($this->discount_price, 0, ',', '.') . ' ' . $symbol;
    }
}
