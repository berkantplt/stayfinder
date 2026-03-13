<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Data Transfer Object representing a single hotel price offer from a partner.
 *
 * This is the normalized structure that ALL partner responses are mapped into.
 * Regardless of how Booking.com, Expedia, or Agoda structure their APIs,
 * every response is transformed into this DTO before reaching the aggregator.
 *
 * Immutable by design — once created, a DTO cannot be modified.
 */
final readonly class HotelPriceDTO
{
    public function __construct(
        public string $hotel_id,
        public string $hotel_name,
        public string $partner,
        public float  $price,
        public string $currency,
        public string $deep_link,
        public bool   $availability,
    ) {}

    /**
     * Create a DTO from an associative array.
     *
     * Used when deserializing cached data or mapping partner responses.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            hotel_id:     (string) ($data['hotel_id'] ?? ''),
            hotel_name:   (string) ($data['hotel_name'] ?? ''),
            partner:      (string) ($data['partner'] ?? ''),
            price:        (float)  ($data['price'] ?? 0.0),
            currency:     (string) ($data['currency'] ?? 'USD'),
            deep_link:    (string) ($data['deep_link'] ?? ''),
            availability: (bool)   ($data['availability'] ?? false),
        );
    }

    /**
     * Serialize the DTO to an array for JSON responses and caching.
     */
    public function toArray(): array
    {
        return [
            'hotel_id'     => $this->hotel_id,
            'hotel_name'   => $this->hotel_name,
            'partner'      => $this->partner,
            'price'        => $this->price,
            'currency'     => $this->currency,
            'deep_link'    => $this->deep_link,
            'availability' => $this->availability,
        ];
    }
}
