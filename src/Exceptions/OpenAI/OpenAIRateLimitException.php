<?php

namespace JTD\LaravelAI\Exceptions\OpenAI;

use JTD\LaravelAI\Exceptions\RateLimitException;

/**
 * Exception thrown when OpenAI API rate limits are exceeded.
 *
 * This exception extends the general RateLimitException and adds
 * OpenAI-specific rate limit information and handling.
 */
class OpenAIRateLimitException extends RateLimitException
{
    /**
     * OpenAI request ID for debugging.
     */
    public ?string $requestId = null;

    /**
     * Rate limit type (requests, tokens, etc.).
     */
    public ?string $rateLimitType = null;

    /**
     * Organization ID if applicable.
     */
    public ?string $organizationId = null;

    /**
     * Create a new OpenAI rate limit exception.
     *
     * @param  string  $message  Exception message
     * @param  int|null  $rateLimit  Rate limit value
     * @param  int|null  $remaining  Remaining requests
     * @param  int|null  $resetTime  Reset time in seconds
     * @param  string|null  $limitType  Type of limit
     * @param  string|null  $requestId  Request ID
     * @param  string|null  $rateLimitType  OpenAI rate limit type
     * @param  string|null  $organizationId  Organization ID
     * @param  int  $code  Exception code
     * @param  \Exception|null  $previous  Previous exception
     */
    public function __construct(
        string $message = 'OpenAI rate limit exceeded',
        ?int $rateLimit = null,
        ?int $remaining = null,
        ?int $resetTime = null,
        ?string $limitType = null,
        ?string $requestId = null,
        ?string $rateLimitType = null,
        ?string $organizationId = null,
        int $code = 429,
        ?\Exception $previous = null
    ) {
        parent::__construct($message, $rateLimit, $remaining, $resetTime, $limitType, $code, $previous);

        $this->requestId = $requestId;
        $this->rateLimitType = $rateLimitType;
        $this->organizationId = $organizationId;
    }

    /**
     * Create exception from OpenAI rate limit headers.
     *
     * @param  array  $headers  HTTP headers from OpenAI response
     * @param  string|null  $requestId  Request ID
     * @return static
     */
    public static function fromHeaders(array $headers, ?string $requestId = null): static
    {
        $rateLimit = isset($headers['x-ratelimit-limit-requests']) 
            ? (int) $headers['x-ratelimit-limit-requests'] 
            : null;

        $remaining = isset($headers['x-ratelimit-remaining-requests']) 
            ? (int) $headers['x-ratelimit-remaining-requests'] 
            : null;

        $resetTime = isset($headers['x-ratelimit-reset-requests']) 
            ? static::parseResetTime($headers['x-ratelimit-reset-requests']) 
            : null;

        $rateLimitType = static::determineRateLimitType($headers);
        $organizationId = $headers['openai-organization'] ?? null;

        $message = static::buildRateLimitMessage($rateLimitType, $resetTime);

        return new static(
            $message,
            $rateLimit,
            $remaining,
            $resetTime,
            'requests',
            $requestId,
            $rateLimitType,
            $organizationId
        );
    }

    /**
     * Get the OpenAI request ID.
     */
    public function getRequestId(): ?string
    {
        return $this->requestId;
    }

    /**
     * Get the OpenAI rate limit type.
     */
    public function getRateLimitType(): ?string
    {
        return $this->rateLimitType;
    }

    /**
     * Get the organization ID.
     */
    public function getOrganizationId(): ?string
    {
        return $this->organizationId;
    }

    /**
     * Parse reset time from header value.
     */
    protected static function parseResetTime(string $resetHeader): ?int
    {
        // OpenAI reset time format: "1s", "2m", "1h", etc.
        if (preg_match('/^(\d+)([smh])$/', $resetHeader, $matches)) {
            $value = (int) $matches[1];
            $unit = $matches[2];

            return match ($unit) {
                's' => $value,
                'm' => $value * 60,
                'h' => $value * 3600,
                default => $value,
            };
        }

        return null;
    }

    /**
     * Determine rate limit type from headers.
     */
    protected static function determineRateLimitType(array $headers): ?string
    {
        if (isset($headers['x-ratelimit-limit-tokens'])) {
            return 'tokens';
        }

        if (isset($headers['x-ratelimit-limit-requests'])) {
            return 'requests';
        }

        return null;
    }

    /**
     * Build appropriate rate limit message.
     */
    protected static function buildRateLimitMessage(?string $rateLimitType, ?int $resetTime): string
    {
        $message = 'OpenAI rate limit exceeded';

        if ($rateLimitType) {
            $message .= " for {$rateLimitType}";
        }

        if ($resetTime) {
            $message .= ". Retry after {$resetTime} seconds";
        }

        return $message;
    }

    /**
     * Get recommended retry delay with jitter.
     */
    public function getRetryDelay(): int
    {
        $baseDelay = $this->getWaitTime() ?? 60;
        
        // Add jitter (±25%)
        $jitter = (int) ($baseDelay * 0.25 * (mt_rand() / mt_getrandmax() - 0.5));
        
        return max(1, $baseDelay + $jitter);
    }
}
