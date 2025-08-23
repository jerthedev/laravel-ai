<?php

namespace JTD\LaravelAI\Exceptions\DriverTemplate;

use JTD\LaravelAI\Exceptions\ProviderException;

/**
 * Base exception for DriverTemplate-specific errors.
 *
 * This exception extends the general ProviderException and adds
 * DriverTemplate-specific error handling and context.
 */
class DriverTemplateException extends ProviderException
{
    /**
     * OpenAI error type from API response.
     */
    public $openaiErrorType = null;

    /**
     * OpenAI error code from API response.
     */
    public $openaiErrorCode = null;

    /**
     * OpenAI request ID for debugging.
     */
    public $requestId = null;

    /**
     * Create a new DriverTemplate exception.
     *
     * @param  string  $message  Exception message
     * @param  string|null  $drivertemplateErrorType  DriverTemplate error type
     * @param  string|null  $drivertemplateErrorCode  DriverTemplate error code
     * @param  string|null  $requestId  Request ID
     * @param  array  $details  Additional error details
     * @param  bool  $retryable  Whether the error is retryable
     * @param  int  $code  Exception code
     * @param  \Exception|null  $previous  Previous exception
     */
    public function __construct(string $message = 'OpenAI API error', ?string $openaiErrorType = null, ?string $openaiErrorCode = null, ?string $requestId = null, array $details = [], bool $retryable = false, int $code = 500, ?Exception $previous = null)
    {
        // TODO: Implement __construct
    }

    /**
     * Create exception from DriverTemplate API error response.
     *
     * @param  array  $errorData  Error data from DriverTemplate API
     * @param  string|null  $requestId  Request ID
     */
    public static function fromApiError(array $errorData, ?string $requestId = null): static
    {
        // TODO: Implement fromApiError
    }

    /**
     * Get the DriverTemplate error type.
     */
    public function getOpenAIErrorType(): string
    {
        // TODO: Implement getOpenAIErrorType
    }

    /**
     * Get the DriverTemplate error code.
     */
    public function getOpenAIErrorCode(): string
    {
        // TODO: Implement getOpenAIErrorCode
    }

    /**
     * Get the request ID.
     */
    public function getRequestId(): string
    {
        // TODO: Implement getRequestId
    }

    /**
     * Check if the error type is retryable.
     */
    protected static function isRetryableError(string $errorType): bool
    {
        // TODO: Implement isRetryableError
    }

    /**
     * Get HTTP status code for error type.
     */
    protected static function getHttpCodeForErrorType(string $errorType): int
    {
        // TODO: Implement getHttpCodeForErrorType
    }

    /**
     * Get formatted error message for logging.
     */
    public function getFormattedMessage(): string
    {
        // TODO: Implement getFormattedMessage
    }
}
