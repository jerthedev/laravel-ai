<?php

namespace JTD\LaravelAI\Exceptions\XAI;

/**
 * Exception thrown when xAI server errors occur.
 */
class XAIServerException extends XAIException
{
    protected int $statusCode;

    protected bool $isRetryable;

    public function __construct(
        string $message = 'Server error',
        int $statusCode = 500,
        bool $isRetryable = true,
        array $details = [],
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 'server_error', $details, $isRetryable, $code ?: $statusCode, $previous);

        $this->statusCode = $statusCode;
        $this->isRetryable = $isRetryable;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function isRetryable(): bool
    {
        return $this->isRetryable;
    }
}
