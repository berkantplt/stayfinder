<?php

declare(strict_types=1);

namespace App\Services\Partners;

use App\DTO\HotelPriceDTO;
use App\DTO\SearchRequestDTO;

/**
 * Booking.com Partner Service
 *
 * Maps Booking.com's API response format into the standard HotelPriceDTO.
 *
 * Expected Booking.com response:
 * {
 *   "result": [
 *     {
 *       "hotel_id": "BKG-12345",
 *       "name": "Grand Hotel Istanbul",
 *       "price_per_night": 120.50,
 *       "currency": "EUR",
 *       "url": "https://www.booking.com/hotel/...",
 *       "available": true
 *     }
 *   ]
 * }
 */
class BookingPartnerService extends AbstractPartnerService
{
    /**
     * {@inheritdoc}
     */
    public function getPartnerName(): string
    {
        return 'booking';
    }

    /**
     * {@inheritdoc}
     */
    protected function buildUrl(SearchRequestDTO $request): string
    {
        $baseUrl = $this->config('base_url', 'https://api.booking.com/v1');

        return rtrim($baseUrl, '/') . '/hotels/search';
    }

    /**
     * {@inheritdoc}
     */
    protected function buildQueryParams(SearchRequestDTO $request): array
    {
        return [
            'city'      => $request->city,
            'checkin'   => $request->checkin,
            'checkout'  => $request->checkout,
            'guests'    => $request->adults,
            'currency'  => 'EUR',
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function buildHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->config('api_key', ''),
            'Accept'        => 'application/json',
            'X-Partner'     => 'metasearch-engine',
        ];
    }

    /**
     * Map Booking.com's response structure to HotelPriceDTO[].
     *
     * Booking.com nests results under the "result" key and uses
     * "price_per_night" instead of "price", and "available" instead
     * of "availability".
     *
     * {@inheritdoc}
     */
    protected function mapResponse(array $responseData): array
    {
        $hotels = $responseData['result'] ?? [];
        $dtos   = [];

        foreach ($hotels as $hotel) {
            $dtos[] = new HotelPriceDTO(
                hotel_id:     (string) ($hotel['hotel_id'] ?? ''),
                hotel_name:   (string) ($hotel['name'] ?? ''),
                partner:      $this->getPartnerName(),
                price:        (float)  ($hotel['price_per_night'] ?? 0.0),
                currency:     (string) ($hotel['currency'] ?? 'EUR'),
                deep_link:    (string) ($hotel['url'] ?? ''),
                availability: (bool)   ($hotel['available'] ?? false),
            );
        }

        return $dtos;
    }
}
