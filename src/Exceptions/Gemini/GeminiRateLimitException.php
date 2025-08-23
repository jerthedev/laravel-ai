<?php

namespace JTD\LaravelAI\Exceptions\Gemini;

/**
 * Exception for Gemini rate limit errors.
 */
class GeminiRateLimitException extends GeminiException
{
    /**
     * Current rate limit.
     */
    public ?int $rateLimit = null;

    /**
     * Remaining requests.
     */
    public ?int $remaining = null;

    /**
     * Retry after delay in seconds.
     */
    public ?int $retryAfter = null;

    /**
     * Type of rate limit (requests, tokens, etc.).
     */
    public ?string $limitType = null;

    /**
     * Create a new Gemini rate limit exception.
     *
     * @param  string  $message  Exception message
     * @param  int|null  $rateLimit  Current rate limit
     * @param  int|null  $remaining  Remaining requests
     * @param  int|null  $retryAfter  Retry after delay in seconds
     * @param  string|null  $limitType  Type of rate limit
     * @param  string|null  $requestId  Request ID
     * @param  array  $details  Additional error details
     * @param  int  $code  Exception code
     * @param  \Exception|null  $previous  Previous exception
     */
    public function __construct(
        string $message = 'Gemini API rate limit exceeded',
        ?int $rateLimit = null,
        ?int $remaining = null,
        ?int $retryAfter = null,
        ?string $limitType = null,
        ?string $requestId = null,
        array $details = [],
        int $code = 429,
        ?\Exception $previous = null
    ) {
        parent::__construct(
            $message,
            'rate_limit_exceeded',
            null,
            $requestId,
            $details,
            true, // Retryable
            $code,
            $previous
        );

        $this->rateLimit = $rateLimit;
        $this->remaining = $remaining;
        $this->retryAfter = $retryAfter;
        $this->limitType = $limitType;
    }

    /**
     * Get the rate limit.
     */
    public function getRateLimit(): ?int
    {
        return $this->rateLimit;
    }

    /**
     * Get remaining requests.
     */
    public function getRemaining(): ?int
    {
        return $this->remaining;
    }

    /**
     * Get retry after delay in seconds.
     */
    public function getRetryAfter(): ?int
    {
        return $this->retryAfter;
    }

    /**
     * Get retry after delay in milliseconds.
     */
    public function getRetryAfterMs(): ?int
    {
        return $this->retryAfter ? $this->retryAfter * 1000 : null;
    }

    /**
     * Get the limit type.
     */
    public function getLimitType(): ?string
    {
        return $this->limitType;
    }

    /**
     * Check if retry after is specified.
     */
    public function hasRetryAfter(): bool
    {
        return $this->retryAfter !== null;
    }

    /**
     * Get user-friendly error message.
     */
    public function getUserFriendlyMessage(): string
    {
        $message = 'Rate limit exceeded. ';

        if ($this->retryAfter) {
            $message .= "Please wait {$this->retryAfter} seconds before trying again.";
        } else {
            $message .= 'Please wait a moment before trying again.';
        }

        return $message;
    }

    /**
     * Get suggested actions for the error.
     */
    public function getSuggestedActions(): array
    {
        $actions = [
            'Wait before making another request',
            'Implement exponential backoff in your application',
            'Reduce the frequency of your requests',
        ];

        if ($this->retryAfter) {
            array_unshift($actions, "Wait at least {$this->retryAfter} seconds before retrying");
        }

        if ($this->limitType === 'requests') {
            $actions[] = 'Consider batching multiple operations into fewer requests';
        } elseif ($this->limitType === 'tokens') {
            $actions[] = 'Reduce the size of your requests';
            $actions[] = 'Use shorter prompts or responses';
        }

        $actions[] = 'Consider upgrading to a higher tier plan for increased limits';

        return $actions;
    }

    /**
     * Get recommended retry delay.
     */
    public function getRecommendedRetryDelay(): int
    {
        if ($this->retryAfter) {
            return $this->retryAfter;
        }

        // Default retry delay based on limit type
        return match ($this->limitType) {
            'tokens' => 30, // 30 seconds for token limits
            'requests' => 60, // 60 seconds for request limits
            default => 60, // Default 60 seconds
        };
    }

    /**
     * Convert exception to array for logging.
     */
    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'rate_limit' => $this->rateLimit,
            'remaining' => $this->remaining,
            'retry_after' => $this->retryAfter,
            'limit_type' => $this->limitType,
            'recommended_retry_delay' => $this->getRecommendedRetryDelay(),
        ]);
    }
}
