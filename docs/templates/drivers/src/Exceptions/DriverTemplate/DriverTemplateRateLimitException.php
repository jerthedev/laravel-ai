<?php

namespace JTD\LaravelAI\Exceptions\DriverTemplate;

use JTD\LaravelAI\Exceptions\RateLimitException;

/**
 * Exception thrown when DriverTemplate API rate limits are exceeded.
 *
 * This exception extends the general RateLimitException and adds
 * DriverTemplate-specific rate limit information and handling.
 */
class DriverTemplateRateLimitException extends RateLimitException
{
    /**
     * OpenAI request ID for debugging.
     */
    public $requestId = null;

    /**
     * Rate limit type (requests, tokens, etc.).
     */
    public $rateLimitType = null;

    /**
     * Organization ID if applicable.
     */
    public $organizationId = null;

    /**
     * Create a new DriverTemplate rate limit exception.
     *
     * @param  string  $message  Exception message
     * @param  int|null  $rateLimit  Rate limit value
     * @param  int|null  $remaining  Remaining requests
     * @param  int|null  $resetTime  Reset time in seconds
     * @param  string|null  $limitType  Type of limit
     * @param  string|null  $requestId  Request ID
     * @param  string|null  $rateLimitType  DriverTemplate rate limit type
     * @param  string|null  $organizationId  Organization ID
     * @param  int  $code  Exception code
     * @param  \Exception|null  $previous  Previous exception
     */
    public function __construct(string $message = 'OpenAI rate limit exceeded', ?int $rateLimit = null, ?int $remaining = null, ?int $resetTime = null, ?string $limitType = null, ?string $requestId = null, ?string $rateLimitType = null, ?string $organizationId = null, int $code = 429, ?Exception $previous = null)
    {
        // TODO: Implement __construct
    }

    /**
     * Create exception from DriverTemplate rate limit headers.
     *
     * @param  array  $headers  HTTP headers from DriverTemplate response
     * @param  string|null  $requestId  Request ID
     */
    public static function fromHeaders(array $headers, ?string $requestId = null): static
    {
        // TODO: Implement fromHeaders
    }

    /**
     * Get the DriverTemplate request ID.
     */
    public function getRequestId(): string
    {
        // TODO: Implement getRequestId
    }

    /**
     * Get the DriverTemplate rate limit type.
     */
    public function getRateLimitType(): string
    {
        // TODO: Implement getRateLimitType
    }

    /**
     * Get the organization ID.
     */
    public function getOrganizationId(): string
    {
        // TODO: Implement getOrganizationId
    }

    /**
     * Parse reset time from header value.
     */
    protected static function parseResetTime(string $resetHeader): int
    {
        // TODO: Implement parseResetTime
    }

    /**
     * Determine rate limit type from headers.
     */
    protected static function determineRateLimitType(array $headers): string
    {
        // TODO: Implement determineRateLimitType
    }

    /**
     * Build appropriate rate limit message.
     */
    protected static function buildRateLimitMessage(string $rateLimitType, int $resetTime): string
    {
        // TODO: Implement buildRateLimitMessage
    }

    /**
     * Get recommended retry delay with jitter.
     */
    public function getRetryDelay(): int
    {
        // TODO: Implement getRetryDelay
    }
}
