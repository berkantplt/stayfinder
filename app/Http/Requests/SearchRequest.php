<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use App\Http\Resources\ApiResponse;

/**
 * Search Request Validation
 *
 * Validates all incoming search parameters before they reach the controller.
 * On validation failure, returns a standardized JSON error response (not HTML).
 *
 * Rules:
 * - city:     required, string, max 100 characters
 * - checkin:  required, valid date, today or future
 * - checkout: required, valid date, must be after checkin
 * - adults:   required, integer between 1 and 10
 * - page:     optional, integer >= 1
 * - per_page: optional, integer between 1 and 50
 */
class SearchRequest extends FormRequest
{
    /**
     * All API requests are authorized (no auth gate here).
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Define validation rules.
     */
    public function rules(): array
    {
        return [
            'city'     => ['required', 'string', 'max:100'],
            'checkin'  => ['required', 'date', 'date_format:Y-m-d', 'after_or_equal:today'],
            'checkout' => ['required', 'date', 'date_format:Y-m-d', 'after:checkin'],
            'adults'   => ['required', 'integer', 'min:1', 'max:10'],
            'page'     => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ];
    }

    /**
     * Custom error messages for better developer experience.
     */
    public function messages(): array
    {
        return [
            'city.required'           => 'The city parameter is required.',
            'city.max'                => 'The city name must not exceed 100 characters.',
            'checkin.required'        => 'The check-in date is required.',
            'checkin.date_format'     => 'The check-in date must be in Y-m-d format (e.g., 2026-03-15).',
            'checkin.after_or_equal'  => 'The check-in date must be today or a future date.',
            'checkout.required'       => 'The check-out date is required.',
            'checkout.date_format'    => 'The check-out date must be in Y-m-d format (e.g., 2026-03-18).',
            'checkout.after'          => 'The check-out date must be after the check-in date.',
            'adults.required'         => 'The number of adults is required.',
            'adults.integer'          => 'The number of adults must be an integer.',
            'adults.min'              => 'At least 1 adult is required.',
            'adults.max'              => 'Maximum 10 adults allowed per search.',
            'page.integer'            => 'Page must be an integer.',
            'page.min'                => 'Page must be at least 1.',
            'per_page.integer'        => 'Per page must be an integer.',
            'per_page.min'            => 'Per page must be at least 1.',
            'per_page.max'            => 'Per page must not exceed 50.',
        ];
    }

    /**
     * Override failed validation to return JSON instead of redirect.
     *
     * This is critical for API-only applications — we never want
     * a 302 redirect on validation failure.
     */
    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(
            ApiResponse::validationError(
                $validator->errors()->toArray(),
                'Invalid search parameters.'
            )
        );
    }
}
