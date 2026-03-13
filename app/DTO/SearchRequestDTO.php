<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Data Transfer Object encapsulating search criteria.
 *
 * Decouples the HTTP request from the service layer.
 * Partner services receive this DTO instead of raw request data,
 * making them testable and framework-agnostic.
 */
final readonly class SearchRequestDTO
{
    public function __construct(
        public string $city,
        public string $checkin,
        public string $checkout,
        public int    $adults,
    ) {}

    /**
     * Create from validated request data.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            city:     (string) $data['city'],
            checkin:  (string) $data['checkin'],
            checkout: (string) $data['checkout'],
            adults:   (int)    $data['adults'],
        );
    }

    /**
     * Generate a unique cache key for this search combination.
     *
     * Key format: hotel_search:{city}:{checkin}:{checkout}:{adults}
     */
    public function cacheKey(): string
    {
        return sprintf(
            'hotel_search:%s:%s:%s:%d',
            strtolower($this->city),
            $this->checkin,
            $this->checkout,
            $this->adults,
        );
    }
}
