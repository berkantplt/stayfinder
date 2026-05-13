<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Destination extends Model
{
    protected $fillable = [
        'name', 'slug', 'image', 'sort_order', 'is_active', 'description', 'country', 'highlights',
        'ai_research', 'ai_research_updated_at', 'ai_research_status',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'ai_research' => 'array',
        'ai_research_updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Destination $d) {
            if (empty($d->slug)) $d->slug = Str::slug($d->name);
        });
    }

    public function scopeActive($q) { return $q->where('is_active', true); }

    public function getRouteKeyName(): string { return 'slug'; }

    public function aiResearchIsFresh(int $maxAgeDays = 180): bool
    {
        if (empty($this->ai_research) || !$this->ai_research_updated_at) {
            return false;
        }
        return $this->ai_research_updated_at->gt(now()->subDays($maxAgeDays));
    }

    public static function findByName(string $name): ?self
    {
        $slug = Str::slug($name);
        return static::where('slug', $slug)
            ->orWhere('name', $name)
            ->first();
    }
}
