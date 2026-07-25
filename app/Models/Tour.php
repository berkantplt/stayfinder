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

    // Fiyat matrisindeki oda/yaş tipleri (anahtar => görünen etiket). Her biri
    // opsiyonel; her tip için eski (old) + indirimli (new) fiyat tutulabilir.
    public const ROOM_TYPES = [
        'double_pp' => 'İki Kişilik Oda Kişi Başı',
        'single' => 'Tek Kişilik Oda',
        'extra_bed' => 'İlave Yatak',
        'child_0_2' => '0-1,99 Yaş',
        'child_3_5' => '3-5,99 Yaş',
        'child_7_11' => '7-11,99 Yaş',
    ];

    // "Başlangıç fiyatı" için kullanılacak yetişkin oda tipleri (çocuk hariç).
    public const ADULT_ROOM_TYPES = ['double_pp', 'single', 'extra_bed'];

    public static function roomTypes(): array
    {
        return self::ROOM_TYPES;
    }

    /**
     * Tur karakteri şeması değişince mevcut kayıtlar bu versiyonun altında kalır;
     * TourObserver / app:enrich-tour-characters onları yeniden üretir.
     */
    public const CURRENT_CHARACTER_VERSION = 1;

    protected $fillable = [
        'agency_id', 'category_id', 'title', 'slug', 'destination', 'description',
        'price', 'currency', 'duration_days', 'departure_date',
        'return_date', 'included', 'excluded', 'image', 'images', 'tour_url', 'is_active',
        'views_count', 'clicks_count', 'embedding', 'search_text', 'is_international', 'requires_visa',
        'departure_points', 'itinerary', 'hotel_info', 'extras',
        'cancellation_policy', 'guide_info', 'frequency', 'pricing_blocks',
        'departure_city', 'stop_cities',
        'character_summary', 'pace_score', 'character_version',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'price_try' => 'decimal:2',
        'duration_days' => 'integer',
        'departure_date' => 'date',
        'return_date' => 'date',
        'is_active' => 'boolean',
        'embedding' => 'array',
        'is_international' => 'boolean',
        'requires_visa' => 'boolean',
        'itinerary' => 'array', // [{title, content}, ...] — gün gün program
        'pricing_blocks' => 'array', // [{dates:[], packages:[{hotel, prices:{type:{old,new}}}]}]
        'stop_cities' => 'array', // yol üstünde yolcu alınan iller
        'images' => 'array', // sıralı galeri (/storage yolları), images[0] = kapak
        'pace_score' => 'float', // 0-1: program yoğunluğu (düşük=dinlenme, yüksek=tempolu gezi)
        'character_version' => 'integer',
    ];

    public function needsCharacterEnrichment(): bool
    {
        return ($this->character_version ?? 0) < self::CURRENT_CHARACTER_VERSION;
    }

    protected static function booted(): void
    {
        static::creating(function (Tour $tour) {
            if (empty($tour->slug)) {
                $tour->slug = Str::slug($tour->title).'-'.Str::random(5);
            }
        });

        // TL-normalize fiyat: tüm filtre/sıralama/agregasyonlar price_try kullanır
        static::saving(function (Tour $tour) {
            if ($tour->isDirty('price') || $tour->isDirty('currency') || $tour->price_try === null) {
                $tour->price_try = CurrencyRate::toTry((float) $tour->price, $tour->currency);
            }
        });

        // Auto-record price history. Bildirimler TourObserver'da: yeni tur →
        // announcements tablosuna tek satır, fiyat düşüşü → sadece favorileyenlere.
        static::created(function (Tour $tour) {
            $tour->priceHistories()->create([
                'price' => $tour->price,
                'recorded_at' => now()->toDateString(),
            ]);
        });

        static::updating(function (Tour $tour) {
            if ($tour->isDirty('price')) {
                $tour->priceHistories()->create([
                    'price' => $tour->price,
                    'recorded_at' => now()->toDateString(),
                ]);
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
        return $this->hasMany(Review::class)->latest();
    }

    public function campaigns()
    {
        return $this->hasMany(Campaign::class);
    }

    public function priceHistories()
    {
        return $this->hasMany(PriceHistory::class)->orderBy('recorded_at');
    }

    public function favoritedBy()
    {
        return $this->belongsToMany(User::class, 'favorites')->withTimestamps();
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
        // isPubliclyVisible() ile parite: pasif acentanın turları hiçbir
        // listede görünmemeli (lisans şemasından bağımsız kural)
        $query
            ->where('is_active', true)
            ->whereHas('agency', fn ($agencyQuery) => $agencyQuery->where('is_active', true));

        if (! CategoryLicensing::schemaReady()) {
            return $query;
        }

        return $query
            ->where(function ($accessQuery) {
                $accessQuery
                    ->where(function ($legacyQuery) {
                        $legacyQuery
                            ->whereHas('agency', fn ($agencyQuery) => $agencyQuery->where('legacy_category_access', true))
                            ->where(function ($categoryQuery) {
                                $categoryQuery
                                    ->whereNull('category_id')
                                    ->orWhereHas('category', fn ($activeCategoryQuery) => $activeCategoryQuery->active());
                            });
                    })
                    ->orWhere(function ($licensedQuery) {
                        $licensedQuery
                            ->whereNotNull('category_id')
                            ->whereHas('category', fn ($categoryQuery) => $categoryQuery->active())
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

    /**
     * Müşterinin şehrinden binebileceği turlar: kalkış şehri o il VEYA durak
     * şehirleri arasında o il var. Kalkış şehri girilmemiş (eski) turlar bu
     * filtrede gizlenir — şehir bilgisi olmayan tura "binilebilir" denemez.
     */
    public function scopeDepartsFrom($query, string $city)
    {
        return $query->where(function ($q) use ($city) {
            $q->where('departure_city', $city)
                ->orWhereJsonContains('stop_cities', $city);
        });
    }

    public function scopeUpcoming($query)
    {
        return $query->where('departure_date', '>=', now()->toDateString());
    }

    /**
     * Min/max TL cinsindendir — karşılaştırma kur-normalize price_try ile yapılır.
     */
    public function scopePriceBetween($query, $min, $max)
    {
        if ($min) {
            $query->where('price_try', '>=', $min);
        }
        if ($max) {
            $query->where('price_try', '<=', $max);
        }

        return $query;
    }

    public function getFormattedPriceAttribute(): string
    {
        return number_format($this->price, 0, ',', '.').' '.$this->currency_symbol;
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

        if (! $this->is_active || ! $this->agency?->is_active) {
            return false;
        }

        if (! CategoryLicensing::schemaReady()) {
            return ! $this->category_id || (bool) $this->category?->is_active;
        }

        if ($this->agency->legacy_category_access) {
            return ! $this->category_id || (bool) $this->category?->is_active;
        }

        if (! $this->category_id || ! $this->category?->is_active) {
            return false;
        }

        return $this->agency->activeCategorySubscriptions()
            ->where('category_id', $this->category_id)
            ->exists();
    }
}
