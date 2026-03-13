<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Exception thrown when a partner API call fails.
 *
 * Carries context about which partner failed and the original exception,
 * enabling structured logging and graceful degradation.
 */
class PartnerApiException extends RuntimeException
{
    public function __construct(
        public readonly string $partnerName,
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        $fullMessage = "[{$this->partnerName}] {$message}";
        parent::__construct($fullMessage, $code, $previous);
    }

    /**
     * Get the name of the partner that caused the exception.
     */
    public function getPartnerName(): string
    {
        return $this->partnerName;
    }
}
