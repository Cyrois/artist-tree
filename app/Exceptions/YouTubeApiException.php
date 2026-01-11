<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\Client\Response;

/**
 * Exception thrown when YouTube API requests fail.
 */
class YouTubeApiException extends Exception
{
    public function __construct(
        string $message,
        public readonly ?Response $response = null,
        public readonly ?int $statusCode = null,
        public readonly int $quotaCost = 0,
        public readonly bool $isQuotaExhausted = false,
    ) {
        parent::__construct($message);
    }

    /**
     * Create exception from HTTP response.
     */
    public static function fromResponse(Response $response, string $context = ''): self
    {
        $statusCode = $response->status();
        $body = $response->json();

        $errorMessage = $body['error']['message'] ?? 'Unknown YouTube API error';
        $message = $context
            ? "{$context}: {$errorMessage}"
            : $errorMessage;

        // Check if this is a quota exhaustion error
        $isQuotaExhausted = $statusCode === 403 && 
            isset($body['error']['errors'][0]['reason']) &&
            in_array($body['error']['errors'][0]['reason'], ['quotaExceeded', 'dailyLimitExceeded']);

        return new self(
            message: $message,
            response: $response,
            statusCode: $statusCode,
            quotaCost: 0, // Will be set by the calling code if needed
            isQuotaExhausted: $isQuotaExhausted
        );
    }

    /**
     * Create quota exhaustion exception.
     */
    public static function quotaExhausted(int $quotaCost = 0): self
    {
        return new self(
            message: 'YouTube API quota exhausted',
            response: null,
            statusCode: 403,
            quotaCost: $quotaCost,
            isQuotaExhausted: true
        );
    }

    /**
     * Create rate limit exception.
     */
    public static function rateLimited(int $retryAfter = null): self
    {
        $message = 'YouTube API rate limit exceeded';
        if ($retryAfter) {
            $message .= " (retry after {$retryAfter} seconds)";
        }

        return new self(
            message: $message,
            response: null,
            statusCode: 429,
            quotaCost: 0,
            isQuotaExhausted: false
        );
    }

    /**
     * Check if this is a rate limit error.
     */
    public function isRateLimitError(): bool
    {
        return $this->statusCode === 429;
    }

    /**
     * Check if this is an authentication error.
     */
    public function isAuthError(): bool
    {
        return $this->statusCode === 401;
    }

    /**
     * Check if this is a not found error.
     */
    public function isNotFoundError(): bool
    {
        return $this->statusCode === 404;
    }

    /**
     * Get retry-after value from rate limit response (in seconds).
     */
    public function getRetryAfter(): ?int
    {
        if (!$this->response) {
            return null;
        }

        return (int) $this->response->header('Retry-After');
    }
}