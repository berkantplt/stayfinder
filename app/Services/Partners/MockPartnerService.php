<?php

declare(strict_types=1);

namespace App\Services\Partners;

use App\Contracts\PartnerServiceInterface;
use App\DTO\HotelPriceDTO;
use App\DTO\SearchRequestDTO;
use Illuminate\Support\Facades\Log;

/**
 * Mock Partner Service for Development & Testing
 *
 * Returns realistic-looking hotel data without making any external API calls.
 * This is used when PARTNER_MOCK_ENABLED=true in .env.
 *
 * In production, this service should be disabled. It demonstrates the
 * system architecture working end-to-end without real partner credentials.
 */
class MockPartnerService implements PartnerServiceInterface
{
    /**
     * Simulated hotel databases per partner.
     *
     * Each partner returns different hotels and prices, just like real
     * partner APIs would.
     */
    private const MOCK_DATA = [
        'booking' => [
            ['hotel_id' => 'HTL-001', 'hotel_name' => 'Grand Hyatt Istanbul',           'price' => 185.00, 'currency' => 'EUR', 'deep_link' => 'https://www.booking.com/hotel/tr/grand-hyatt-istanbul', 'availability' => true],
            ['hotel_id' => 'HTL-002', 'hotel_name' => 'Four Seasons Bosphorus',          'price' => 420.00, 'currency' => 'EUR', 'deep_link' => 'https://www.booking.com/hotel/tr/four-seasons-bosphorus', 'availability' => true],
            ['hotel_id' => 'HTL-003', 'hotel_name' => 'Pera Palace Hotel',               'price' => 210.00, 'currency' => 'EUR', 'deep_link' => 'https://www.booking.com/hotel/tr/pera-palace', 'availability' => true],
            ['hotel_id' => 'HTL-004', 'hotel_name' => 'Raffles Istanbul',                'price' => 350.00, 'currency' => 'EUR', 'deep_link' => 'https://www.booking.com/hotel/tr/raffles-istanbul', 'availability' => true],
            ['hotel_id' => 'HTL-005', 'hotel_name' => 'Ciragan Palace Kempinski',        'price' => 550.00, 'currency' => 'EUR', 'deep_link' => 'https://www.booking.com/hotel/tr/ciragan-palace', 'availability' => true],
            ['hotel_id' => 'HTL-006', 'hotel_name' => 'The Ritz-Carlton Istanbul',       'price' => 290.00, 'currency' => 'EUR', 'deep_link' => 'https://www.booking.com/hotel/tr/ritz-carlton', 'availability' => false],
        ],
        'expedia' => [
            ['hotel_id' => 'HTL-001', 'hotel_name' => 'Grand Hyatt Istanbul',           'price' => 192.50, 'currency' => 'EUR', 'deep_link' => 'https://www.expedia.com/hotel/grand-hyatt-istanbul', 'availability' => true],
            ['hotel_id' => 'HTL-002', 'hotel_name' => 'Four Seasons Bosphorus',          'price' => 405.00, 'currency' => 'EUR', 'deep_link' => 'https://www.expedia.com/hotel/four-seasons-bosphorus', 'availability' => true],
            ['hotel_id' => 'HTL-003', 'hotel_name' => 'Pera Palace Hotel',               'price' => 198.00, 'currency' => 'EUR', 'deep_link' => 'https://www.expedia.com/hotel/pera-palace', 'availability' => true],
            ['hotel_id' => 'HTL-007', 'hotel_name' => 'Shangri-La Bosphorus',            'price' => 320.00, 'currency' => 'EUR', 'deep_link' => 'https://www.expedia.com/hotel/shangri-la-bosphorus', 'availability' => true],
            ['hotel_id' => 'HTL-008', 'hotel_name' => 'Swissotel The Bosphorus',         'price' => 175.00, 'currency' => 'EUR', 'deep_link' => 'https://www.expedia.com/hotel/swissotel-bosphorus', 'availability' => true],
        ],
        'agoda' => [
            ['hotel_id' => 'HTL-001', 'hotel_name' => 'Grand Hyatt Istanbul',           'price' => 178.00, 'currency' => 'EUR', 'deep_link' => 'https://www.agoda.com/hotel/grand-hyatt-istanbul', 'availability' => true],
            ['hotel_id' => 'HTL-002', 'hotel_name' => 'Four Seasons Bosphorus',          'price' => 430.00, 'currency' => 'EUR', 'deep_link' => 'https://www.agoda.com/hotel/four-seasons-bosphorus', 'availability' => true],
            ['hotel_id' => 'HTL-004', 'hotel_name' => 'Raffles Istanbul',                'price' => 335.00, 'currency' => 'EUR', 'deep_link' => 'https://www.agoda.com/hotel/raffles-istanbul', 'availability' => true],
            ['hotel_id' => 'HTL-005', 'hotel_name' => 'Ciragan Palace Kempinski',        'price' => 520.00, 'currency' => 'EUR', 'deep_link' => 'https://www.agoda.com/hotel/ciragan-palace', 'availability' => true],
            ['hotel_id' => 'HTL-009', 'hotel_name' => 'W Istanbul',                      'price' => 245.00, 'currency' => 'EUR', 'deep_link' => 'https://www.agoda.com/hotel/w-istanbul', 'availability' => true],
            ['hotel_id' => 'HTL-010', 'hotel_name' => 'Park Hyatt Istanbul',             'price' => 295.00, 'currency' => 'EUR', 'deep_link' => 'https://www.agoda.com/hotel/park-hyatt-istanbul', 'availability' => true],
        ],
    ];

    public function __construct(
        private readonly string $partnerName,
    ) {}

    /**
     * {@inheritdoc}
     */
    public function getPartnerName(): string
    {
        return $this->partnerName;
    }

    /**
     * {@inheritdoc}
     */
    public function isEnabled(): bool
    {
        return true;
    }

    /**
     * Return mock hotel data for demonstration.
     *
     * Simulates a small random delay (10-50ms) to mimic network latency.
     *
     * {@inheritdoc}
     */
    public function search(SearchRequestDTO $request): array
    {
        // Simulate network latency
        usleep(random_int(10000, 50000));

        $partnerData = self::MOCK_DATA[$this->partnerName] ?? [];

        Log::info("[MOCK:{$this->partnerName}] Returning " . count($partnerData) . ' mock results.');

        return array_map(
            fn(array $hotel) => new HotelPriceDTO(
                hotel_id:     $hotel['hotel_id'],
                hotel_name:   $hotel['hotel_name'],
                partner:      $this->partnerName,
                price:        $hotel['price'],
                currency:     $hotel['currency'],
                deep_link:    $hotel['deep_link'],
                availability: $hotel['availability'],
            ),
            $partnerData,
        );
    }
}
