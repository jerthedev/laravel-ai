<?php

namespace JTD\LaravelAI\Exceptions\XAI;

use JTD\LaravelAI\Exceptions\ProviderException;

/**
 * Base exception for xAI-related errors.
 */
class XAIException extends ProviderException
{
    protected string $xaiErrorType;

    public function __construct(
        string $message = 'xAI API error',
        string $xaiErrorType = 'unknown_error',
        array $details = [],
        bool $retryable = false,
        int $code = 500,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 'xai', $xaiErrorType, $details, $retryable, $code, $previous);

        $this->xaiErrorType = $xaiErrorType;
    }

    public function getXAIErrorType(): string
    {
        return $this->xaiErrorType;
    }

    public function getProvider(): string
    {
        return 'xai';
    }
}
