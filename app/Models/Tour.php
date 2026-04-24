<?php

namespace App\Models;

use App\Observers\TourObserver;
use App\Support\CategoryLicensing;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Builder as BaseBuilder;
use Illuminate\Support\Str;

use App\Notifications\NewTourNotification;
use App\Notifications\PriceDropNotification;
use App\Models\User;

#[ObservedBy(TourObserver::class)]
class Tour extends Model
{
    use HasFactory;

    public const SUPPORTED_CURRENCIES = [
        'TRY' => ['label' => 'Türk Lirası', 'symbol' => '₺'],
        'USD' => ['label' => 'ABD Doları', 'symbol' => '$'],
        'EUR' => ['label' => 'Euro', 'symbol' => '€'],
        'GBP' => ['label' => 'İngiliz Sterlini', 'symbol' => '£'],
        'AED' => ['label' => 'BAE Dirhemi', 'symbol' => 'AED'],
        'SAR' => ['label' => 'Suudi Riyali', 'symbol' => 'SAR'],
    ];

    protected $fillable = [
        'agency_id', 'category_id', 'title', 'slug', 'destination', 'description',
        'price', 'currency', 'duration_days', 'departure_date',
        'return_date', 'included', 'excluded', 'image', 'tour_url', 'is_active',
        'views_count', 'clicks_count', 'embedding', 'is_international', 'requires_visa'
    ];

    protected $casts = [
        'price'             => 'decimal:2',
        'duration_days'     => 'integer',
        'departure_date'    => 'date',
        'return_date'       => 'date',
        'is_active'         => 'boolean',
        'embedding'         => 'array',
        'is_international'  => 'boolean',
        'requires_visa'     => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (Tour $tour) {
            if (empty($tour->slug)) {
                $tour->slug = Str::slug($tour->title) . '-' . Str::random(5);
            }
        });

        // Auto-record price history & send new tour notification
        static::created(function (Tour $tour) {
            $tour->priceHistories()->create([
                'price'       => $tour->price,
                'recorded_at' => now()->toDateString(),
            ]);

            // Notify all users about new tour
            $users = User::all();
            \Illuminate\Support\Facades\Notification::send($users, new NewTourNotification($tour));
        });

        static::updating(function (Tour $tour) {
            if ($tour->isDirty('price')) {
                $oldPrice = $tour->getOriginal('price');
                $newPrice = $tour->price;

                $tour->priceHistories()->create([
                    'price'       => $newPrice,
                    'recorded_at' => now()->toDateString(),
                ]);

                // If price dropped, notify all users
                if ($newPrice < $oldPrice) {
                    $users = User::all();
                    \Illuminate\Support\Facades\Notification::send($users, new PriceDropNotification($tour));
                }
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

    public function priceHistories()
    {
        return $this->hasMany(PriceHistory::class)->orderBy('recorded_at');
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
        if (!CategoryLicensing::schemaReady()) {
            return $query->where('is_active', true);
        }

        return $query
            ->where('is_active', true)
            ->where(function ($accessQuery) {
                $accessQuery
                    ->where(function ($legacyQuery) {
                        $legacyQuery
                            ->whereHas('agency', fn($agencyQuery) => $agencyQuery->where('legacy_category_access', true))
                            ->where(function ($categoryQuery) {
                                $categoryQuery
                                    ->whereNull('category_id')
                                    ->orWhereHas('category', fn($activeCategoryQuery) => $activeCategoryQuery->active());
                            });
                    })
                    ->orWhere(function ($licensedQuery) {
                        $licensedQuery
                            ->whereNotNull('category_id')
                            ->whereHas('category', fn($categoryQuery) => $categoryQuery->active())
                            ->whereExists(function (BaseBuilder $subscriptionQuery) {
                                $subscriptionQuery
                                    ->selectRaw('1')
                                    ->from('agency_category_subscriptions')
                                    ->whereColumn('agency_category_subscriptions.agency_id', 'tours.agency_id')
                                    ->whereColumn('agency_category_subscriptions.category_id', 'tours.category_id')
                                    ->where('agency_category_subscriptions.status', AgencyCategorySubscription::STATUS_ACTIVE)
                                    ->whereDate('agency_category_subscriptions.expires_at', '>=', now()->toDateString());
                            });
                    });
            });
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
        return number_format($this->price, 0, ',', '.') . ' ' . $this->currency_symbol;
    }

    public function getCurrencySymbolAttribute(): string
    {
        return self::currencySymbol($this->currency);
    }

    public static function supportedCurrencies(): array
    {
        return self::SUPPORTED_CURRENCIES;
    }

    public static function currencySymbol(?string $code): string
    {
        $normalized = strtoupper(trim((string) $code));
        return self::SUPPORTED_CURRENCIES[$normalized]['symbol'] ?? '₺';
    }

    public function isPubliclyVisible(): bool
    {
        $this->loadMissing('agency', 'category');

        if (!$this->is_active || !$this->agency?->is_active) {
            return false;
        }

        if (!CategoryLicensing::schemaReady()) {
            return !$this->category_id || (bool) $this->category?->is_active;
        }

        if ($this->agency->legacy_category_access) {
            return !$this->category_id || (bool) $this->category?->is_active;
        }

        if (!$this->category_id || !$this->category?->is_active) {
            return false;
        }

        return $this->agency->activeCategorySubscriptions()
            ->where('category_id', $this->category_id)
            ->exists();
    }
}
