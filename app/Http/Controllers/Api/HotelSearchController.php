<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\DTO\SearchRequestDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\SearchRequest;
use App\Http\Resources\ApiResponse;
use App\Services\CachedSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * Hotel Search Controller
 *
 * Single-responsibility: receives validated HTTP requests, delegates
 * to the CachedSearchService, and returns a standardized JSON response.
 *
 * This controller is intentionally thin. All business logic lives in
 * the service layer (CachedSearchService → HotelSearchAggregator →
 * PartnerServices). This follows the "Thin Controller, Fat Service" pattern.
 *
 * Endpoint: GET /api/search
 *
 * Response format:
 * {
 *   "success": true,
 *   "message": "OK",
 *   "data": {
 *     "hotels": [...],
 *     "total_results": 42,
 *     "search_time_ms": 1250.5,
 *     "page": 1,
 *     "per_page": 20,
 *     "total_pages": 3
 *   },
 *   "errors": []
 * }
 */
class HotelSearchController extends Controller
{
    public function __construct(
        private readonly CachedSearchService $searchService,
    ) {}

    /**
     * Handle a hotel search request.
     *
     * Flow:
     * 1. SearchRequest validates query parameters automatically
     * 2. Build a SearchRequestDTO from validated data
     * 3. Delegate to CachedSearchService (checks Redis → Aggregator)
     * 4. Measure total response time
     * 5. Return standardized JSON response
     */
    public function search(SearchRequest $request): JsonResponse
    {
        $overallStart = microtime(true);

        try {
            // Build immutable DTO from validated request data
            $searchDTO = SearchRequestDTO::fromArray($request->validated());

            // Pagination parameters with sensible defaults
            $page    = (int) $request->input('page', 1);
            $perPage = (int) $request->input('per_page', 20);

            // Execute search (may hit cache or call all partners)
            $result = $this->searchService->search($searchDTO, $page, $perPage);

            // Override search_time_ms with total controller-level time
            // This includes serialization overhead, giving a more accurate picture
            $totalTimeMs = round((microtime(true) - $overallStart) * 1000, 2);
            $result['search_time_ms'] = $totalTimeMs;

            Log::info("Search completed in {$totalTimeMs}ms", [
                'city'     => $searchDTO->city,
                'checkin'  => $searchDTO->checkin,
                'checkout' => $searchDTO->checkout,
                'adults'   => $searchDTO->adults,
                'results'  => $result['total_results'],
            ]);

            return ApiResponse::success($result, 'Search completed successfully.');

        } catch (\Throwable $e) {
            Log::error('Search controller error: ' . $e->getMessage(), [
                'exception' => get_class($e),
                'trace'     => $e->getTraceAsString(),
            ]);

            return ApiResponse::error(
                'An error occurred while searching for hotels. Please try again.',
                500
            );
        }
    }
}
