<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\PartnerServiceInterface;
use App\DTO\HotelPriceDTO;
use App\DTO\SearchRequestDTO;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Hotel Search Aggregator
 *
 * Orchestrates the meta-search: calls ALL registered partner services,
 * collects results, filters unavailable rooms, picks the cheapest
 * offer per hotel, and returns a sorted, paginated result set.
 *
 * Design decisions:
 * - Partners are injected as a tagged collection (Open/Closed Principle)
 * - Failed partners don't break the search (fault tolerance)
 * - Results are grouped by hotel_id to show one entry per hotel
 * - Sorting is by price ascending (cheapest first)
 *
 * Scalability note:
 * For 100k daily searches, this service is stateless and can be
 * horizontally scaled behind a load balancer. The Redis cache layer
 * (CachedSearchService) sits in front of this to absorb repeated queries.
 */
class HotelSearchAggregator
{
    /**
     * @param PartnerServiceInterface[] $partners
     */
    public function __construct(
        private readonly array $partners,
    ) {}

    /**
     * Search all partners, aggregate and deduplicate results.
     *
     * @param SearchRequestDTO $request Search criteria.
     * @param int $page Current page number (1-indexed).
     * @param int $perPage Results per page.
     * @return array{hotels: array, total_results: int}
     */
    public function search(SearchRequestDTO $request, int $page = 1, int $perPage = 20): array
    {
        $startTime  = microtime(true);
        $allResults = [];

        /*
        |----------------------------------------------------------------------
        | Step 1: Call All Partners
        |----------------------------------------------------------------------
        |
        | Each partner call is wrapped in its own try/catch inside the
        | partner service. If a partner times out or errors, it returns
        | an empty array — the search continues with remaining partners.
        |
        */
        foreach ($this->partners as $partner) {
            if (!$partner instanceof PartnerServiceInterface) {
                continue;
            }

            $partnerResults = $partner->search($request);
            $allResults     = array_merge($allResults, $partnerResults);
        }

        Log::info('Aggregator collected ' . count($allResults) . ' raw results from ' . count($this->partners) . ' partners.');

        /*
        |----------------------------------------------------------------------
        | Step 2: Filter Unavailable Rooms
        |----------------------------------------------------------------------
        |
        | Remove any hotel offers where availability is false.
        | This ensures only bookable results are shown to the user.
        |
        */
        $available = Collection::make($allResults)
            ->filter(fn(HotelPriceDTO $dto) => $dto->availability);

        /*
        |----------------------------------------------------------------------
        | Step 3: Group by Hotel, Pick Cheapest per Hotel
        |----------------------------------------------------------------------
        |
        | Multiple partners may offer the same hotel. We group by hotel_id
        | and select the offer with the lowest price for each hotel.
        |
        | This mimics Trivago's behavior: show ONE entry per hotel with
        | the best (cheapest) price and attribute it to the partner.
        |
        */
        $cheapest = $available
            ->groupBy(fn(HotelPriceDTO $dto) => $dto->hotel_id)
            ->map(function (Collection $offers) {
                // Sort offers by price ascending within each hotel group
                $sorted = $offers->sortBy(fn(HotelPriceDTO $dto) => $dto->price);

                // Return the cheapest offer DTO
                return $sorted->first();
            })
            ->filter() // Remove any null values
            ->values();

        /*
        |----------------------------------------------------------------------
        | Step 4: Sort by Price Ascending
        |----------------------------------------------------------------------
        */
        $sorted = $cheapest->sortBy(fn(HotelPriceDTO $dto) => $dto->price)->values();

        /*
        |----------------------------------------------------------------------
        | Step 5: Paginate
        |----------------------------------------------------------------------
        */
        $totalResults = $sorted->count();
        $offset       = ($page - 1) * $perPage;

        $paginated = $sorted->slice($offset, $perPage)->values();

        $searchTimeMs = round((microtime(true) - $startTime) * 1000, 2);

        Log::info("Aggregator returning {$paginated->count()} results (page {$page}) in {$searchTimeMs}ms. Total: {$totalResults}");

        return [
            'hotels'        => $paginated->map(fn(HotelPriceDTO $dto) => $dto->toArray())->all(),
            'total_results' => $totalResults,
            'search_time_ms' => $searchTimeMs,
            'page'          => $page,
            'per_page'      => $perPage,
            'total_pages'   => (int) ceil($totalResults / $perPage),
        ];
    }
}
