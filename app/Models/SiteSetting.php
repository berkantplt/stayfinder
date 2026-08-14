<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Genel arayüz ayarları (anahtar/değer).
 *
 * Sayfa başına sorgu atmamak için önbelleklenir; set() yazarken kendi
 * anahtarını düşürür, yani admin kaydettiği anda site günceldir.
 */
class SiteSetting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    private static function cacheKey(string $key): string
    {
        return 'site_setting_'.$key;
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        $deger = Cache::rememberForever(self::cacheKey($key), function () use ($key) {
            // null'ı önbelleğe koyabilmek için sarmalayıcı dizi: aksi halde
            // kaydı olmayan anahtar her istekte DB'ye gider.
            return ['v' => static::query()->whereKey($key)->value('value')];
        });

        return $deger['v'] ?? $default;
    }

    public static function set(string $key, string|int|null $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => (string) $value]);
        Cache::forget(self::cacheKey($key));
    }
}
