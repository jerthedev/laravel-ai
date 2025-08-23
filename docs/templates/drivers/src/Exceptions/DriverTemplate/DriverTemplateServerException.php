<?php

namespace JTD\LaravelAI\Exceptions\DriverTemplate;

/**
 * Exception thrown when DriverTemplate API encounters server errors.
 *
 * This exception extends the general DriverTemplateException and is used for
 * server-side errors like 500 Internal Server Error, 503 Service Unavailable, etc.
 */
class DriverTemplateServerException extends DriverTemplateException
{
    /**
     * Create a new DriverTemplate server exception.
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
    public function __construct(string $message = 'OpenAI server error', ?string $openaiErrorType = null, ?string $openaiErrorCode = null, ?string $requestId = null, array $details = [], bool $retryable = true, int $code = 500, ?Exception $previous = null)
    {
        // TODO: Implement __construct
    }

    /**
     * Create exception from server error response.
     *
     * @param  array  $errorData  Error data from DriverTemplate API
     * @param  string|null  $requestId  Request ID
     */
    public static function fromServerError(array $errorData, ?string $requestId = null): static
    {
        // TODO: Implement fromServerError
    }

    /**
     * Get HTTP status code for server error types.
     */
    protected static function getHttpCodeForErrorType(string $errorType): int
    {
        // TODO: Implement getHttpCodeForErrorType
    }

    /**
     * Check if this is a temporary server error that should be retried.
     */
    public function isTemporary(): bool
    {
        // TODO: Implement isTemporary
    }

    /**
     * Get suggested retry delay in seconds.
     */
    public function getSuggestedRetryDelay(): int
    {
        // TODO: Implement getSuggestedRetryDelay
    }
}
