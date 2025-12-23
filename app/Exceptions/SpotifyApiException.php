<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\Client\Response;

/**
 * Exception thrown when Spotify API requests fail.
 */
class SpotifyApiException extends Exception
{
    public function __construct(
        string $message,
        public readonly ?Response $response = null,
        public readonly ?int $statusCode = null,
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

        $errorMessage = $body['error']['message'] ?? 'Unknown Spotify API error';
        $message = $context
            ? "{$context}: {$errorMessage}"
            : $errorMessage;

        return new self($message, $response, $statusCode);
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
     * Get retry-after value from rate limit response (in seconds).
     */
    public function getRetryAfter(): ?int
    {
        if (! $this->response) {
            return null;
        }

        return (int) $this->response->header('Retry-After');
    }
}
