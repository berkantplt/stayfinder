<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Kategori landing hero banner'ı. category_id null = genel varsayılan.
 *
 * Ana sayfa karuselindeki Banner modeliyle karıştırılmamalı; çözümleme ve
 * varsayılan metinler App\Support\CategoryHero'da.
 */
class CategoryBanner extends Model
{
    public const DEFAULT_DARKNESS = 38;

    protected $fillable = [
        'category_id', 'image', 'eyebrow', 'title', 'subtitle', 'caption', 'darkness', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'darkness' => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /** Genel varsayılan mı (hiçbir kategoriye bağlı değil)? */
    public function isDefault(): bool
    {
        return $this->category_id === null;
    }

    public function getImageUrlAttribute(): string
    {
        $image = (string) $this->image;

        return str_starts_with($image, 'http://') || str_starts_with($image, 'https://')
            ? $image
            : asset('storage/'.ltrim($image, '/'));
    }
}
