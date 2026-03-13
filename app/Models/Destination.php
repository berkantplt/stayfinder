<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Destination extends Model
{
    protected $fillable = ['name', 'slug', 'image', 'sort_order', 'is_active', 'description', 'country', 'highlights'];

    protected $casts = ['is_active' => 'boolean'];

    protected static function booted(): void
    {
        static::creating(function (Destination $d) {
            if (empty($d->slug)) $d->slug = Str::slug($d->name);
        });
    }

    public function scopeActive($q) { return $q->where('is_active', true); }

    public function getRouteKeyName(): string { return 'slug'; }
}
