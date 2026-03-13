<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\PartnerServiceInterface;
use App\Services\CachedSearchService;
use App\Services\HotelSearchAggregator;
use App\Services\Partners\AgodaPartnerService;
use App\Services\Partners\BookingPartnerService;
use App\Services\Partners\ExpediaPartnerService;
use App\Services\Partners\MockPartnerService;
use Illuminate\Support\ServiceProvider;

/**
 * Partner Service Provider
 *
 * Registers all partner services and wires up dependency injection.
 *
 * To add a new partner:
 * 1. Create a new service class extending AbstractPartnerService
 * 2. Add its config block to config/partners.php
 * 3. Register it in the $partnerClasses array below
 * 4. Done — the aggregator will automatically include it
 *
 * Mock Mode:
 * Set PARTNER_MOCK_ENABLED=true in .env to use MockPartnerService
 * instead of real partner APIs. This returns realistic demo data
 * without requiring actual API credentials.
 */
class PartnerServiceProvider extends ServiceProvider
{
    /**
     * All registered partner service classes.
     *
     * Add new partners to this array — they will be automatically
     * instantiated and injected into the aggregator.
     */
    private array $partnerClasses = [
        BookingPartnerService::class,
        ExpediaPartnerService::class,
        AgodaPartnerService::class,
    ];

    /**
     * Partner names for mock mode (must match config keys).
     */
    private array $partnerNames = ['booking', 'expedia', 'agoda'];

    /**
     * Register services into the container.
     */
    public function register(): void
    {
        $useMock = (bool) env('PARTNER_MOCK_ENABLED', false);

        if ($useMock) {
            // -------------------------------------------------------
            // MOCK MODE: Use MockPartnerService for all partners
            // -------------------------------------------------------
            $this->app->singleton(HotelSearchAggregator::class, function ($app) {
                $partners = array_map(
                    fn(string $name) => new MockPartnerService($name),
                    $this->partnerNames,
                );

                return new HotelSearchAggregator($partners);
            });
        } else {
            // -------------------------------------------------------
            // PRODUCTION MODE: Use real partner service classes
            // -------------------------------------------------------
            foreach ($this->partnerClasses as $partnerClass) {
                $this->app->singleton($partnerClass);
            }

            $this->app->singleton(HotelSearchAggregator::class, function ($app) {
                $partners = array_map(
                    fn(string $class) => $app->make($class),
                    $this->partnerClasses,
                );

                return new HotelSearchAggregator($partners);
            });
        }

        // Register the cached search service (wraps aggregator)
        $this->app->singleton(CachedSearchService::class, function ($app) {
            return new CachedSearchService(
                $app->make(HotelSearchAggregator::class),
            );
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
