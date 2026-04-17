<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeaturedCityImage extends Model
{
    protected $fillable = ['featured_city_id', 'image_path', 'sort_order'];

    public function city()
    {
        return $this->belongsTo(FeaturedCity::class, 'featured_city_id');
    }
}
