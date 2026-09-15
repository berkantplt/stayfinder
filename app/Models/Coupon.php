<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Coupon extends Model
{
    use SoftDeletes; // A10: silme = arşiv, geri alınabilir

    use HasFactory;

    protected $fillable = [
        'agency_id',
        'code',
        'discount_type',
        'discount_value',
        'min_purchase_amount',
        'max_uses',
        'used_count',
        'starts_at',
        'expires_at',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'discount_value' => 'decimal:2',
        'min_purchase_amount' => 'decimal:2',
    ];

    /** C13 — yüzde indirimin üst sınırı; formda ve doğrulamada aynı sayı. */
    public const MAX_PERCENT = 100;

    /** C13 — sabit indirim üst sınırı (TL); "%500" gibi anlamsız değerlerin sabit eşi. */
    public const MAX_FIXED = 1000000;

    /**
     * C13 — İndirim değeri kuralı türe bağlıdır: yüzde ≤ 100, sabit ≤ 1.000.000 TL,
     * her ikisinde 0 anlamsız. Acenta ve admin formları aynı kuralı kullanır.
     *
     * @return array<int, string>
     */
    public static function discountValueRules(?string $type): array
    {
        $max = $type === 'percent' ? self::MAX_PERCENT : self::MAX_FIXED;

        return ['required', 'numeric', 'gt:0', 'max:'.$max];
    }

    /** @return array<string, string> */
    public static function discountValueMessages(?string $type): array
    {
        return [
            'discount_value.gt' => 'İndirim değeri 0\'dan büyük olmalı.',
            'discount_value.max' => $type === 'percent'
                ? 'Yüzde indirim en fazla %'.self::MAX_PERCENT.' olabilir.'
                : 'Sabit indirim en fazla '.number_format(self::MAX_FIXED, 0, ',', '.').' ₺ olabilir.',
        ];
    }

    public function usages()
    {
        return $this->hasMany(CouponUsage::class);
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    /**
     * Müşteriye gösterilebilir kuponlar:
     *  - is_active = true
     *  - starts_at NULL veya geçmişte
     *  - expires_at NULL veya gelecekte
     *  - max_uses NULL veya used_count < max_uses
     */
    public function scopeAvailable($query)
    {
        return $query
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>=', now());
            })
            ->where(function ($q) {
                $q->whereNull('max_uses')->orWhereColumn('used_count', '<', 'max_uses');
            });
    }

    public function getRemainingUsesAttribute(): ?int
    {
        if ($this->max_uses === null) {
            return null; // sınırsız
        }

        return max(0, (int) $this->max_uses - (int) $this->used_count);
    }

    public function getFormattedDiscountAttribute(): string
    {
        if ($this->discount_type === 'percent') {
            return '%' . rtrim(rtrim(number_format((float) $this->discount_value, 2, ',', ''), '0'), ',');
        }

        return number_format((float) $this->discount_value, 0, ',', '.') . ' ₺';
    }
}
