<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DTO\SearchRequestDTO;

/**
 * Contract for all hotel partner services.
 *
 * Each partner (Booking, Expedia, Agoda, etc.) must implement this interface.
 * This ensures a consistent API for the aggregator, making it trivial to
 * add new partners without modifying existing code (Open/Closed Principle).
 */
interface PartnerServiceInterface
{
    /**
     * Search for hotel prices from this partner.
     *
     * @param SearchRequestDTO $request The normalized search criteria.
     * @return \App\DTO\HotelPriceDTO[] Array of hotel price DTOs.
     */
    public function search(SearchRequestDTO $request): array;

    /**
     * Get the unique identifier name for this partner.
     *
     * Used for logging, cache keys, and response attribution.
     *
     * @return string e.g. 'booking', 'expedia', 'agoda'
     */
    public function getPartnerName(): string;

    /**
     * Check whether this partner service is currently enabled.
     *
     * Allows runtime toggling via config without code changes.
     *
     * @return bool
     */
    public function isEnabled(): bool;
}
