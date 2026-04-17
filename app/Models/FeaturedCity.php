<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeaturedCity extends Model
{
    protected $fillable = ['name', 'country', 'sort_order', 'is_active'];

    public function images()
    {
        return $this->hasMany(FeaturedCityImage::class)->orderBy('sort_order');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
