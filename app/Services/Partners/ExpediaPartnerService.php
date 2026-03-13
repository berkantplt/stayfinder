<?php

declare(strict_types=1);

namespace App\Services\Partners;

use App\DTO\HotelPriceDTO;
use App\DTO\SearchRequestDTO;

/**
 * Expedia Partner Service
 *
 * Maps Expedia's API response format into the standard HotelPriceDTO.
 *
 * Expected Expedia response:
 * {
 *   "hotels": [
 *     {
 *       "id": "EXP-67890",
 *       "hotelName": "Bosphorus Palace Hotel",
 *       "offers": [
 *         {
 *           "totalPrice": 135.00,
 *           "currency": "USD",
 *           "deepLink": "https://www.expedia.com/hotel/...",
 *           "isAvailable": true
 *         }
 *       ]
 *     }
 *   ]
 * }
 */
class ExpediaPartnerService extends AbstractPartnerService
{
    /**
     * {@inheritdoc}
     */
    public function getPartnerName(): string
    {
        return 'expedia';
    }

    /**
     * {@inheritdoc}
     */
    protected function buildUrl(SearchRequestDTO $request): string
    {
        $baseUrl = $this->config('base_url', 'https://api.expedia.com/v2');

        return rtrim($baseUrl, '/') . '/properties/availability';
    }

    /**
     * {@inheritdoc}
     */
    protected function buildQueryParams(SearchRequestDTO $request): array
    {
        return [
            'destination' => $request->city,
            'startDate'   => $request->checkin,
            'endDate'     => $request->checkout,
            'adults'      => $request->adults,
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function buildHeaders(): array
    {
        return [
            'X-Api-Key'   => $this->config('api_key', ''),
            'Accept'      => 'application/json',
            'Content-Type' => 'application/json',
        ];
    }

    /**
     * Map Expedia's response structure to HotelPriceDTO[].
     *
     * Expedia nests hotels under "hotels" and each hotel has an "offers"
     * array. We take the first (cheapest) offer per hotel. The field names
     * differ: "hotelName", "totalPrice", "deepLink", "isAvailable".
     *
     * {@inheritdoc}
     */
    protected function mapResponse(array $responseData): array
    {
        $hotels = $responseData['hotels'] ?? [];
        $dtos   = [];

        foreach ($hotels as $hotel) {
            // Expedia returns multiple offers per hotel; pick the first one
            $offer = $hotel['offers'][0] ?? null;

            if ($offer === null) {
                continue;
            }

            $dtos[] = new HotelPriceDTO(
                hotel_id:     (string) ($hotel['id'] ?? ''),
                hotel_name:   (string) ($hotel['hotelName'] ?? ''),
                partner:      $this->getPartnerName(),
                price:        (float)  ($offer['totalPrice'] ?? 0.0),
                currency:     (string) ($offer['currency'] ?? 'USD'),
                deep_link:    (string) ($offer['deepLink'] ?? ''),
                availability: (bool)   ($offer['isAvailable'] ?? false),
            );
        }

        return $dtos;
    }
}
