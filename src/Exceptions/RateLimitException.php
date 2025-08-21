<?php

namespace JTD\LaravelAI\Exceptions;

use Exception;

/**
 * Exception thrown when API rate limits are exceeded.
 */
class RateLimitException extends Exception
{
    /**
     * @var int|null Rate limit (requests per period)
     */
    public ?int $rateLimit;

    /**
     * @var int|null Remaining requests in current period
     */
    public ?int $remaining;

    /**
     * @var int|null Seconds until rate limit resets
     */
    public ?int $resetTime;

    /**
     * @var string|null Rate limit type (requests, tokens, etc.)
     */
    public ?string $limitType;

    /**
     * Create a new rate limit exception.
     *
     * @param  string  $message  Exception message
     * @param  int|null  $rateLimit  Rate limit value
     * @param  int|null  $remaining  Remaining requests
     * @param  int|null  $resetTime  Reset time in seconds
     * @param  string|null  $limitType  Type of limit
     * @param  int  $code  Exception code
     * @param  Exception|null  $previous  Previous exception
     */
    public function __construct(
        string $message = 'Rate limit exceeded',
        ?int $rateLimit = null,
        ?int $remaining = null,
        ?int $resetTime = null,
        ?string $limitType = null,
        int $code = 429,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);

        $this->rateLimit = $rateLimit;
        $this->remaining = $remaining;
        $this->resetTime = $resetTime;
        $this->limitType = $limitType;
    }

    /**
     * Get the wait time until rate limit resets.
     *
     * @return int|null Seconds to wait
     */
    public function getWaitTime(): ?int
    {
        return $this->resetTime;
    }

    /**
     * Check if retry is possible.
     */
    public function canRetry(): bool
    {
        return $this->resetTime !== null && $this->resetTime > 0;
    }
}
