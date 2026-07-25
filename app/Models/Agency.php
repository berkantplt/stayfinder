<?php

namespace App\Models;

use App\Support\CategoryLicensing;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Agency extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'name', 'slug', 'logo', 'website_url',
        'phone', 'email', 'address', 'description', 'is_active', 'approval_status',
        'approval_notes', 'approved_at', 'approved_by', 'legacy_category_access',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'approved_at' => 'datetime',
        'legacy_category_access' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (Agency $agency) {
            if (empty($agency->slug)) {
                $baseSlug = Str::slug($agency->name);
                $baseSlug = $baseSlug !== '' ? $baseSlug : 'acenta';
                $slug = $baseSlug;
                $suffix = 1;

                while (static::where('slug', $slug)->exists()) {
                    $slug = $baseSlug . '-' . $suffix;
                    $suffix++;
                }

                $agency->slug = $slug;
            }
        });

        // Acenta pasifleşince turları görünmez olur — chatbot envanteri ve
        // bilinen destinasyon listesi bayat kalmasın
        static::updated(function (Agency $agency) {
            if ($agency->wasChanged('is_active')) {
                cache()->forget('ai_search_known_destinations_v1');
                \App\Services\AiSearch\DestinationKnowledgeService::flushInventory();
            }
        });
    }

    public function tours(): HasMany
    {
        return $this->hasMany(Tour::class);
    }

    public function activeTours(): HasMany
    {
        return $this->hasMany(Tour::class)->where('is_active', true);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function categorySubscriptions(): HasMany
    {
        return $this->hasMany(AgencyCategorySubscription::class);
    }

    public function activeCategorySubscriptions(): HasMany
    {
        return $this->categorySubscriptions()->active();
    }

    public function categoryOrders(): HasMany
    {
        return $this->hasMany(AgencyCategoryOrder::class);
    }

    public function licensedCategories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'agency_category_subscriptions')
            ->withPivot(['monthly_price', 'status', 'started_at', 'expires_at', 'last_order_id'])
            ->withTimestamps();
    }

    public function activeLicensedCategories(): BelongsToMany
    {
        return $this->licensedCategories()
            ->wherePivot('status', AgencyCategorySubscription::STATUS_ACTIVE)
            ->wherePivot('expires_at', '>=', now()->toDateString());
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopePendingApproval($query)
    {
        return $query->where('approval_status', self::STATUS_PENDING);
    }

    public function scopeApproved($query)
    {
        return $query->where('approval_status', self::STATUS_APPROVED);
    }

    public function scopeRejected($query)
    {
        return $query->where('approval_status', self::STATUS_REJECTED);
    }

    public function isPending(): bool
    {
        return $this->approval_status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->approval_status === null || $this->approval_status === self::STATUS_APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->approval_status === self::STATUS_REJECTED;
    }

    public function accessibleCategoriesQuery()
    {
        if (!CategoryLicensing::schemaReady()) {
            return Category::query()->active();
        }

        if ($this->legacy_category_access) {
            return Category::query()->active();
        }

        return Category::query()
            ->active()
            ->whereIn('id', $this->activeCategorySubscriptions()->select('category_id'));
    }

    public function accessibleCategories()
    {
        return $this->accessibleCategoriesQuery()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function accessibleCategoryIds(): array
    {
        return $this->accessibleCategoriesQuery()->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    public function hasCategoryAccess(null|int|Category $category): bool
    {
        $categoryId = $category instanceof Category ? $category->id : $category;

        if (!$categoryId) {
            return false;
        }

        if (!CategoryLicensing::schemaReady()) {
            return Category::active()->whereKey($categoryId)->exists();
        }

        if ($this->legacy_category_access) {
            return Category::active()->whereKey($categoryId)->exists();
        }

        return $this->activeCategorySubscriptions()
            ->where('category_id', $categoryId)
            ->exists();
    }
}
