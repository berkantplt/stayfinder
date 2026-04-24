<?php

namespace App\Models;

use App\Support\CategoryLicensing;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $fillable = ['name', 'slug', 'icon', 'description', 'monthly_price', 'parent_id', 'sort_order', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
        'monthly_price' => 'decimal:2',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('sort_order');
    }

    public function tours(): HasMany
    {
        return $this->hasMany(Tour::class);
    }

    public function agencyCategorySubscriptions(): HasMany
    {
        return $this->hasMany(AgencyCategorySubscription::class);
    }

    public function activeAgencyCategorySubscriptions(): HasMany
    {
        return $this->agencyCategorySubscriptions()->active();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeParents($query)
    {
        return $query->whereNull('parent_id');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function getFormattedMonthlyPriceAttribute(): string
    {
        $price = CategoryLicensing::schemaReady()
            ? (float) $this->monthly_price
            : 2000.0;

        return number_format($price, 0, ',', '.') . ' TL / ay';
    }
}
