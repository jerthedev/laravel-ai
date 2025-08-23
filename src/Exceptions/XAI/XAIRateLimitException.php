<?php

namespace JTD\LaravelAI\Exceptions\XAI;

/**
 * Exception thrown when xAI rate limits are exceeded.
 */
class XAIRateLimitException extends XAIException
{
    protected int $retryAfter;

    protected string $limitType;

    public function __construct(
        string $message = 'Rate limit exceeded',
        int $retryAfter = 60,
        string $limitType = 'requests',
        array $details = [],
        int $code = 429,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 'rate_limit_error', $details, true, $code, $previous);

        $this->retryAfter = $retryAfter;
        $this->limitType = $limitType;
    }

    public function getRetryAfter(): int
    {
        return $this->retryAfter;
    }

    public function getLimitType(): string
    {
        return $this->limitType;
    }
}
