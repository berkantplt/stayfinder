<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Post extends Model
{
    protected $fillable = [
        'title', 'slug', 'excerpt', 'content', 'image',
        'category', 'meta_description', 'is_published', 'published_at',
    ];

    protected $casts = [
        'is_published'  => 'boolean',
        'published_at'  => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Post $post) {
            if (empty($post->slug)) {
                $post->slug = Str::slug($post->title) . '-' . Str::random(4);
            }
            if ($post->is_published && !$post->published_at) {
                $post->published_at = now();
            }
        });

        static::updating(function (Post $post) {
            if ($post->is_published && !$post->published_at) {
                $post->published_at = now();
            }
        });
    }

    public function scopePublished($q) { return $q->where('is_published', true)->orderByDesc('published_at'); }

    public function getRouteKeyName(): string { return 'slug'; }

    public function getReadingTimeAttribute(): int
    {
        return max(1, (int) ceil(str_word_count(strip_tags($this->content)) / 200));
    }
}
