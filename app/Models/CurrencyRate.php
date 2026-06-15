<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CurrencyRate extends Model
{
    protected $fillable = ['currency', 'rate_to_try', 'source', 'fetched_at'];

    protected $casts = [
        'rate_to_try' => 'decimal:4',
        'fetched_at' => 'datetime',
    ];

    /** @var array<string, float>|null request-içi kur cache'i */
    private static ?array $rateCache = null;

    /**
     * Tutarı TL'ye çevirir. Bilinmeyen para birimi TRY varsayılır (rate=1) —
     * yanlış filtrelemektense ham fiyatı kullanmak daha güvenli.
     */
    public static function toTry(float $amount, ?string $currency): float
    {
        $code = strtoupper(trim((string) $currency)) ?: 'TRY';

        return round($amount * (self::rates()[$code] ?? 1.0), 2);
    }

    /** @return array<string, float> */
    public static function rates(): array
    {
        return self::$rateCache ??= self::query()
            ->pluck('rate_to_try', 'currency')
            ->map(fn ($rate) => (float) $rate)
            ->all();
    }

    public static function resetCache(): void
    {
        self::$rateCache = null;
    }
}
