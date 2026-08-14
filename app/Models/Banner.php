<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    protected $fillable = [
        'title', 'image', 'blur', 'darkness', 'sort_order', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'blur' => 'integer',
        'darkness' => 'integer',
        'sort_order' => 'integer',
    ];

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    public function getImageUrlAttribute(): string
    {
        return asset('storage/' . $this->image);
    }
}
