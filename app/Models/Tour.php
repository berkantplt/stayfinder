<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Tour extends Model
{
    use HasFactory;

    protected $fillable = [
        'agency_id', 'category_id', 'title', 'slug', 'destination', 'description',
        'price', 'currency', 'duration_days', 'departure_date',
        'return_date', 'included', 'excluded', 'image', 'tour_url', 'is_active',
    ];

    protected $casts = [
        'price'          => 'decimal:2',
        'duration_days'  => 'integer',
        'departure_date' => 'date',
        'return_date'    => 'date',
        'is_active'      => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (Tour $tour) {
            if (empty($tour->slug)) {
                $tour->slug = Str::slug($tour->title) . '-' . Str::random(5);
            }
        });
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function dates()
    {
        return $this->hasMany(TourDate::class)->orderBy('departure_date');
    }

    public function getNextDateAttribute()
    {
        return $this->dates()->where('departure_date', '>=', now())->first();
    }

    public function reviews()
    {
        return $this->hasMany(\App\Models\Review::class)->latest();
    }

    public function campaigns()
    {
        return $this->hasMany(Campaign::class);
    }

    public function getActiveCampaignAttribute()
    {
        return $this->campaigns()
            ->where('is_active', true)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now())
            ->first();
    }

    public function getAvgRatingAttribute(): float
    {
        return round($this->reviews()->avg('rating') ?? 0, 1);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeDestination($query, string $destination)
    {
        return $query->where('destination', 'like', "%{$destination}%");
    }

    public function scopeUpcoming($query)
    {
        return $query->where('departure_date', '>=', now()->toDateString());
    }

    public function scopePriceBetween($query, $min, $max)
    {
        if ($min) $query->where('price', '>=', $min);
        if ($max) $query->where('price', '<=', $max);
        return $query;
    }

    public function getFormattedPriceAttribute(): string
    {
        return number_format($this->price, 0, ',', '.') . ' ₺';
    }
}
