<?php

namespace App\Services\Discovery;

use App\Jobs\BuildDiscoveryCityBaseJob;
use App\Models\DiscoveryCityBase;
use Illuminate\Support\Facades\Cache;

/**
 * Şehir tabanının yaşam döngüsü: taze taban bul, arka planda üretimi
 * kuyruğa al (şehir başına kilit), üretileni kaydet (upsert), kullanım say.
 * AI çağrısı burada DEĞİL (DiscoveryGuideAiService::generateCityBase).
 */
class DiscoveryCityBaseService
{
    /**
     * Şehir başına dispatch kilidi: aynı şehir için art arda gelen rehber
     * istekleri tek taban job'ına iner. TTL kuyruk beklemesini de kapsayacak
     * kadar uzun; job bitince (başarı/hata) bırakılır.
     */
    public const BUILD_LOCK_PREFIX = 'discovery_city_base_build:';

    public const BUILD_LOCK_SECONDS = 600;

    public function findFresh(string $destinationInput): ?DiscoveryCityBase
    {
        $normalized = DiscoveryCityBase::normalizeCity($destinationInput);
        if ($normalized === '') {
            return null;
        }

        return DiscoveryCityBase::query()
            ->where('normalized_city', $normalized)
            ->fresh()
            ->first();
    }

    /**
     * Tabanı arka planda üretmek üzere kuyruğa alır. Kilit doluysa (job zaten
     * kuyrukta/çalışıyor) sessizce atlar. Dönüş: dispatch edildi mi.
     */
    public function queueBuild(string $destinationInput): bool
    {
        $normalized = DiscoveryCityBase::normalizeCity($destinationInput);
        if ($normalized === '') {
            return false;
        }

        if (! Cache::add(self::BUILD_LOCK_PREFIX.$normalized, 1, self::BUILD_LOCK_SECONDS)) {
            return false;
        }

        BuildDiscoveryCityBaseJob::dispatch(trim($destinationInput));

        return true;
    }

    public function releaseBuildLock(string $destinationInput): void
    {
        Cache::forget(self::BUILD_LOCK_PREFIX.DiscoveryCityBase::normalizeCity($destinationInput));
    }

    /**
     * Doğrulanmış taban payload'unu şehir anahtarıyla yazar; varsa yeniler
     * (hit_count korunur — yenileme kullanım geçmişini sıfırlamaz).
     *
     * @param  array<string, mixed>  $basePayload  DiscoveryGuideAiService::generateCityBase çıktısı
     */
    public function store(string $destinationInput, array $basePayload, ?string $model): DiscoveryCityBase
    {
        $adi = $basePayload['destination']['name'] ?? null;

        return DiscoveryCityBase::query()->updateOrCreate(
            ['normalized_city' => DiscoveryCityBase::normalizeCity($destinationInput)],
            [
                'display_name' => is_string($adi) && $adi !== '' ? $adi : trim($destinationInput),
                'country' => $basePayload['destination']['country'] ?? null,
                'base_payload' => $basePayload,
                'source' => DiscoveryCityBase::SOURCE_GENERATED,
                'model' => $model,
                'generated_at' => now(),
            ],
        );
    }

    /** Query builder increment: model event tetiklemez, updated_at'i de oynatmaz. */
    public function recordHit(DiscoveryCityBase $base): void
    {
        DiscoveryCityBase::query()->whereKey($base->getKey())->increment('hit_count');
    }
}
