<?php

namespace JTD\LaravelAI\Exceptions;

use Exception;

/**
 * General exception for AI provider errors.
 */
class ProviderException extends Exception
{
    /**
     * @var string|null Provider name
     */
    public ?string $provider;

    /**
     * @var string|null Error type
     */
    public ?string $errorType;

    /**
     * @var array Additional error details
     */
    public array $details;

    /**
     * @var bool Whether the error is retryable
     */
    public bool $retryable;

    /**
     * Create a new provider exception.
     *
     * @param  string  $message  Exception message
     * @param  string|null  $provider  Provider name
     * @param  string|null  $errorType  Error type
     * @param  array  $details  Additional error details
     * @param  bool  $retryable  Whether the error is retryable
     * @param  int  $code  Exception code
     * @param  Exception|null  $previous  Previous exception
     */
    public function __construct(
        string $message = 'AI provider error',
        ?string $provider = null,
        ?string $errorType = null,
        array $details = [],
        bool $retryable = false,
        int $code = 500,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);

        $this->provider = $provider;
        $this->errorType = $errorType;
        $this->details = $details;
        $this->retryable = $retryable;
    }

    /**
     * Get the provider name.
     */
    public function getProvider(): ?string
    {
        return $this->provider;
    }

    /**
     * Get the error type.
     */
    public function getErrorType(): ?string
    {
        return $this->errorType;
    }

    /**
     * Get additional error details.
     */
    public function getDetails(): array
    {
        return $this->details;
    }

    /**
     * Check if the error is retryable.
     */
    public function isRetryable(): bool
    {
        return $this->retryable;
    }
}
