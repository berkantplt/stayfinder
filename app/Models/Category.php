<?php

namespace App\Models;

use App\Support\CategoryLicensing;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $fillable = ['name', 'slug', 'icon', 'description', 'monthly_price', 'extra_tour_price', 'parent_id', 'sort_order', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
        'monthly_price' => 'decimal:2',
        'extra_tour_price' => 'decimal:2',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('sort_order');
    }

    public function tours(): HasMany
    {
        return $this->hasMany(Tour::class);
    }

    public function agencyCategorySubscriptions(): HasMany
    {
        return $this->hasMany(AgencyCategorySubscription::class);
    }

    public function activeAgencyCategorySubscriptions(): HasMany
    {
        return $this->agencyCategorySubscriptions()->active();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeParents($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Bu kategori "seçildiğinde" kapsanan tüm kategori id'leri: kendisi + TÜM
     * alt seviyeler.
     *
     * Filtre ve facet sayacı BU metodu kullanmak zorunda — ayrı hesaplandıkları
     * dönemde filtre bir seviye, sayaç sıfır seviye iniyordu ve altında torunu
     * olan kategoriler panelde hep "0" görünüyordu (canlı vaka: "Mısır Turları 0"
     * yazarken filtre 4 tur döndürüyordu).
     *
     * @param  \Illuminate\Support\Collection<int, static>|null  $tumu  Hazır kategori
     *         listesi verilirse DB'ye gidilmez (facet döngüsünde N+1 olmasın).
     * @return array<int, int>
     */
    public function descendantIds(?\Illuminate\Support\Collection $tumu = null): array
    {
        $idler = [$this->id];

        $cocuklar = $tumu
            ? $tumu->where('parent_id', $this->id)
            : static::where('parent_id', $this->id)->get();

        foreach ($cocuklar as $cocuk) {
            $idler = array_merge($idler, $cocuk->descendantIds($tumu));
        }

        return $idler;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function getFormattedMonthlyPriceAttribute(): string
    {
        $price = CategoryLicensing::schemaReady()
            ? (float) $this->monthly_price
            : 2000.0;

        return number_format($price, 0, ',', '.') . ' TL / ay';
    }
}
