<?php

use App\Http\Controllers\Api\HotelSearchController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Hotel Meta-Search API endpoints.
|
| Rate limiting is applied via the 'api' and 'search' throttle middleware.
| The 'search' limiter allows 60 requests/minute per IP to prevent abuse
| while supporting legitimate high-traffic usage.
|
*/

Route::prefix('v1')->group(function () {

    /*
    |----------------------------------------------------------------------
    | Hotel Search
    |----------------------------------------------------------------------
    |
    | GET /api/v1/search
    |
    | Query Parameters:
    |   - city      (required) City name, e.g. "Istanbul"
    |   - checkin   (required) Check-in date, e.g. "2026-03-01"
    |   - checkout  (required) Check-out date, e.g. "2026-03-05"
    |   - adults    (required) Number of guests, e.g. 2
    |   - page      (optional) Page number, default 1
    |   - per_page  (optional) Results per page, default 20, max 50
    |
    */
    Route::middleware('throttle:search')
        ->get('/search', [HotelSearchController::class, 'search'])
        ->name('api.search');
});

/*
|--------------------------------------------------------------------------
| Health Check (unversioned)
|--------------------------------------------------------------------------
*/
Route::get('/health', function () {
    return response()->json([
        'status'  => 'ok',
        'service' => 'hotel-metasearch-api',
        'version' => '1.0.0',
    ]);
})->name('api.health');
