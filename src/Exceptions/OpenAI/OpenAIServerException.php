<?php

namespace JTD\LaravelAI\Exceptions\OpenAI;

/**
 * Exception thrown when OpenAI API encounters server errors.
 *
 * This exception extends the general OpenAIException and is used for
 * server-side errors like 500 Internal Server Error, 503 Service Unavailable, etc.
 */
class OpenAIServerException extends OpenAIException
{
    /**
     * Create a new OpenAI server exception.
     *
     * @param  string  $message  Exception message
     * @param  string|null  $openaiErrorType  OpenAI error type
     * @param  string|null  $openaiErrorCode  OpenAI error code
     * @param  string|null  $requestId  Request ID
     * @param  array  $details  Additional error details
     * @param  bool  $retryable  Whether the error is retryable
     * @param  int  $code  Exception code
     * @param  \Exception|null  $previous  Previous exception
     */
    public function __construct(
        string $message = 'OpenAI server error',
        ?string $openaiErrorType = null,
        ?string $openaiErrorCode = null,
        ?string $requestId = null,
        array $details = [],
        bool $retryable = true, // Server errors are typically retryable
        int $code = 500,
        ?\Exception $previous = null
    ) {
        parent::__construct(
            $message,
            $openaiErrorType,
            $openaiErrorCode,
            $requestId,
            $details,
            $retryable,
            $code,
            $previous
        );
    }

    /**
     * Create exception from server error response.
     *
     * @param  array  $errorData  Error data from OpenAI API
     * @param  string|null  $requestId  Request ID
     */
    public static function fromServerError(array $errorData, ?string $requestId = null): static
    {
        $message = $errorData['message'] ?? 'OpenAI server error occurred';
        $type = $errorData['type'] ?? 'server_error';
        $code = $errorData['code'] ?? null;

        return new static(
            $message,
            $type,
            $code,
            $requestId,
            $errorData,
            true, // Server errors are retryable
            static::getHttpCodeForErrorType($type)
        );
    }

    /**
     * Get HTTP status code for server error types.
     */
    protected static function getHttpCodeForErrorType(?string $errorType): int
    {
        return match ($errorType) {
            'server_error' => 500,
            'service_unavailable' => 503,
            'timeout' => 504,
            'bad_gateway' => 502,
            default => 500,
        };
    }

    /**
     * Check if this is a temporary server error that should be retried.
     */
    public function isTemporary(): bool
    {
        return in_array($this->openaiErrorType, [
            'server_error',
            'service_unavailable',
            'timeout',
            'bad_gateway',
        ]);
    }

    /**
     * Get suggested retry delay in seconds.
     */
    public function getSuggestedRetryDelay(): int
    {
        return match ($this->openaiErrorType) {
            'service_unavailable' => 30, // 30 seconds for service unavailable
            'timeout' => 10, // 10 seconds for timeout
            'server_error' => 5, // 5 seconds for general server error
            default => 5,
        };
    }
}
