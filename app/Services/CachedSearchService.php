<?php

declare(strict_types=1);

namespace App\Services;

use App\DTO\SearchRequestDTO;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Cached Search Service
 *
 * Decorator that wraps HotelSearchAggregator with a Redis caching layer.
 *
 * Cache strategy:
 * - Key: hotel_search:{city}:{checkin}:{checkout}:{adults}
 * - TTL: 5 minutes (configurable via partners.cache.ttl)
 * - On cache hit: return instantly without hitting partner APIs
 * - On cache miss: delegate to aggregator, cache result, return
 *
 * Why 5 minutes?
 * Hotel prices change frequently but not second-by-second. A 5-minute
 * cache balances freshness with performance. At 100k daily searches,
 * many queries will be duplicates (same city, same dates), so the
 * cache hit rate should be substantial.
 *
 * This follows the Decorator pattern — it has the same interface
 * as the aggregator but adds caching behavior transparently.
 */
class CachedSearchService
{
    public function __construct(
        private readonly HotelSearchAggregator $aggregator,
    ) {}

    /**
     * Search with caching.
     *
     * @param SearchRequestDTO $request
     * @param int $page
     * @param int $perPage
     * @return array
     */
    public function search(SearchRequestDTO $request, int $page = 1, int $perPage = 20): array
    {
        $ttl = (int) config('partners.cache.ttl', 300);

        // Include pagination in cache key to avoid serving wrong pages
        $cacheKey = $request->cacheKey() . ":p{$page}:pp{$perPage}";

        // Attempt to retrieve from cache
        $cached = Cache::store(config('partners.cache.driver', 'redis'))
            ->get($cacheKey);

        if ($cached !== null) {
            Log::info("Cache HIT for key: {$cacheKey}");
            return $cached;
        }

        Log::info("Cache MISS for key: {$cacheKey}");

        // Cache miss — delegate to aggregator
        $result = $this->aggregator->search($request, $page, $perPage);

        // Store in cache with TTL
        Cache::store(config('partners.cache.driver', 'redis'))
            ->put($cacheKey, $result, $ttl);

        return $result;
    }
}
