<?php

declare(strict_types=1);

namespace App\Services\Partners;

use App\DTO\HotelPriceDTO;
use App\DTO\SearchRequestDTO;

/**
 * Agoda Partner Service
 *
 * Maps Agoda's API response format into the standard HotelPriceDTO.
 *
 * Expected Agoda response:
 * {
 *   "data": {
 *     "properties": [
 *       {
 *         "propertyId": "AGD-11111",
 *         "propertyName": "Ottoman Suites",
 *         "pricing": {
 *           "amount": 98.75,
 *           "currencyCode": "USD"
 *         },
 *         "bookingUrl": "https://www.agoda.com/hotel/...",
 *         "roomsAvailable": 3
 *       }
 *     ]
 *   }
 * }
 */
class AgodaPartnerService extends AbstractPartnerService
{
    /**
     * {@inheritdoc}
     */
    public function getPartnerName(): string
    {
        return 'agoda';
    }

    /**
     * {@inheritdoc}
     */
    protected function buildUrl(SearchRequestDTO $request): string
    {
        $baseUrl = $this->config('base_url', 'https://api.agoda.com/v1');

        return rtrim($baseUrl, '/') . '/properties/search';
    }

    /**
     * {@inheritdoc}
     */
    protected function buildQueryParams(SearchRequestDTO $request): array
    {
        return [
            'cityName'    => $request->city,
            'checkIn'     => $request->checkin,
            'checkOut'    => $request->checkout,
            'numberOfAdults' => $request->adults,
            'language'    => 'en',
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function buildHeaders(): array
    {
        return [
            'Authorization'  => 'ApiKey ' . $this->config('api_key', ''),
            'Accept'         => 'application/json',
        ];
    }

    /**
     * Map Agoda's response structure to HotelPriceDTO[].
     *
     * Agoda nests data under "data.properties" and uses a nested "pricing"
     * object. Availability is determined by "roomsAvailable" count > 0.
     *
     * {@inheritdoc}
     */
    protected function mapResponse(array $responseData): array
    {
        $properties = $responseData['data']['properties'] ?? [];
        $dtos       = [];

        foreach ($properties as $property) {
            $pricing = $property['pricing'] ?? [];

            $dtos[] = new HotelPriceDTO(
                hotel_id:     (string) ($property['propertyId'] ?? ''),
                hotel_name:   (string) ($property['propertyName'] ?? ''),
                partner:      $this->getPartnerName(),
                price:        (float)  ($pricing['amount'] ?? 0.0),
                currency:     (string) ($pricing['currencyCode'] ?? 'USD'),
                deep_link:    (string) ($property['bookingUrl'] ?? ''),
                availability: ((int) ($property['roomsAvailable'] ?? 0)) > 0,
            );
        }

        return $dtos;
    }
}
