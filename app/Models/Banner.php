<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    protected $fillable = [
        'title', 'image', 'blur', 'darkness', 'white_veil', 'sort_order', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'blur' => 'integer',
        'darkness' => 'integer',
        'white_veil' => 'integer',
        'sort_order' => 'integer',
    ];

    /**
     * Beyaz perde degradesinin durakları: [alfa, yüzde].
     *
     * TEK KAYNAK: hem ana sayfa (PHP) hem admin önizlemesi (JS, @json ile alır)
     * bu diziyi kullanır. İki yere ayrı yazılırsa admin'de gördüğüyle sitede
     * çıkan görüntü sessizce ayrışır.
     */
    public const VEIL_STOPS = [[0.94, 0], [0.86, 24], [0.58, 44], [0.14, 63], [0.0, 78]];

    /**
     * Hero'daki beyaz perdenin CSS degradesi.
     *
     * Duraklar white_veil ile ölçeklenir: 100 → tasarımın aynısı, 0 → perde yok.
     */
    public static function veilGradient(int $veil): string
    {
        $k = max(0, min(100, $veil)) / 100;

        $parts = [];
        foreach (self::VEIL_STOPS as [$alpha, $pos]) {
            $parts[] = 'rgba(255,255,255,'.round($alpha * $k, 3).') '.$pos.'%';
        }

        return 'linear-gradient(97deg, '.implode(', ', $parts).')';
    }

    public function getVeilCssAttribute(): string
    {
        return static::veilGradient((int) ($this->white_veil ?? 100));
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    public function getImageUrlAttribute(): string
    {
        return asset('storage/' . $this->image);
    }
}
