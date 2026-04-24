<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgencyCategoryOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'category_id',
        'category_name',
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
}
