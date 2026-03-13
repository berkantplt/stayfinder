<?php

declare(strict_types=1);

namespace App\Services\Partners;

use App\Contracts\PartnerServiceInterface;
use App\DTO\HotelPriceDTO;
use App\DTO\SearchRequestDTO;
use App\Exceptions\PartnerApiException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Abstract base class for all partner services.
 *
 * Implements the common HTTP call pattern, timeout handling, and error
 * logging so that concrete partner classes only need to define:
 *   - how to build the request URL and parameters
 *   - how to map the partner's unique JSON structure into HotelPriceDTOs
 *
 * This follows the Template Method pattern: the algorithm skeleton lives
 * here, and subclasses fill in the partner-specific steps.
 */
abstract class AbstractPartnerService implements PartnerServiceInterface
{
    /**
     * Build the full request URL for this partner's search endpoint.
     */
    abstract protected function buildUrl(SearchRequestDTO $request): string;

    /**
     * Build query parameters for the HTTP request.
     */
    abstract protected function buildQueryParams(SearchRequestDTO $request): array;

    /**
     * Build HTTP headers (e.g., API key headers).
     */
    abstract protected function buildHeaders(): array;

    /**
     * Map the raw JSON response array into an array of HotelPriceDTO.
     *
     * Each partner has a different response structure. This method
     * is where that structure is normalized.
     *
     * @param array $responseData The decoded JSON response body.
     * @return HotelPriceDTO[]
     */
    abstract protected function mapResponse(array $responseData): array;

    /**
     * Get partner-specific configuration.
     */
    protected function config(string $key, mixed $default = null): mixed
    {
        return config("partners.{$this->getPartnerName()}.{$key}", $default);
    }

    /**
     * Check if this partner is enabled via configuration.
     */
    public function isEnabled(): bool
    {
        return (bool) $this->config('enabled', true);
    }

    /**
     * Execute the search against this partner's API.
     *
     * Handles:
     * - Timeout enforcement (max 3 seconds by default)
     * - HTTP error detection
     * - Graceful failure (returns [] on any error)
     * - Structured logging of failures
     *
     * @param SearchRequestDTO $request
     * @return HotelPriceDTO[]
     */
    public function search(SearchRequestDTO $request): array
    {
        // Skip disabled partners immediately
        if (!$this->isEnabled()) {
            Log::info("[{$this->getPartnerName()}] Partner is disabled, skipping.");
            return [];
        }

        $partnerName = $this->getPartnerName();

        try {
            $timeout        = (int) $this->config('timeout', config('partners.defaults.timeout', 3));
            $connectTimeout = (int) $this->config('connect_timeout', config('partners.defaults.connect_timeout', 2));

            $response = Http::withHeaders($this->buildHeaders())
                ->timeout($timeout)
                ->connectTimeout($connectTimeout)
                ->get($this->buildUrl($request), $this->buildQueryParams($request));

            // Throw exception for 4xx/5xx HTTP status codes
            if ($response->failed()) {
                throw new PartnerApiException(
                    $partnerName,
                    "HTTP {$response->status()}: {$response->body()}"
                );
            }

            $data = $response->json();

            if (!is_array($data)) {
                throw new PartnerApiException(
                    $partnerName,
                    'Invalid JSON response from partner API'
                );
            }

            $results = $this->mapResponse($data);

            Log::info("[{$partnerName}] Returned " . count($results) . ' results.');

            return $results;

        } catch (PartnerApiException $e) {
            Log::error("[{$partnerName}] API error: {$e->getMessage()}", [
                'partner'   => $partnerName,
                'exception' => $e->getPrevious()?->getMessage(),
            ]);
            return [];

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            // Timeout or connection refused
            Log::error("[{$partnerName}] Connection timeout: {$e->getMessage()}", [
                'partner' => $partnerName,
            ]);
            return [];

        } catch (\Throwable $e) {
            // Catch-all for unexpected errors
            Log::error("[{$partnerName}] Unexpected error: {$e->getMessage()}", [
                'partner'   => $partnerName,
                'exception' => get_class($e),
                'trace'     => $e->getTraceAsString(),
            ]);
            return [];
        }
    }
}
