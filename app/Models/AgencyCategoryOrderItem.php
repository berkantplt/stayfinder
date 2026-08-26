<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgencyCategoryOrderItem extends Model
{
    use HasFactory;

    public const TYPE_LICENSE = 'license';

    public const TYPE_EXTRA_SLOT = 'extra_slot';

    protected $fillable = [
        'order_id',
        'category_id',
        'category_name',
        'item_type',
        'unit_price',
        'billing_cycle',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(AgencyCategoryOrder::class, 'order_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function isExtraSlot(): bool
    {
        // Slot şeması yoksa kolon gelmez → null → false (eski davranış korunur)
        return ($this->item_type ?? self::TYPE_LICENSE) === self::TYPE_EXTRA_SLOT;
    }
}
