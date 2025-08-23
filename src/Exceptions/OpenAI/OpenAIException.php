<?php

namespace JTD\LaravelAI\Exceptions\OpenAI;

use JTD\LaravelAI\Exceptions\ProviderException;

/**
 * Base exception for OpenAI-specific errors.
 *
 * This exception extends the general ProviderException and adds
 * OpenAI-specific error handling and context.
 */
class OpenAIException extends ProviderException
{
    /**
     * OpenAI error type from API response.
     */
    public ?string $openaiErrorType = null;

    /**
     * OpenAI error code from API response.
     */
    public ?string $openaiErrorCode = null;

    /**
     * OpenAI request ID for debugging.
     */
    public ?string $requestId = null;

    /**
     * Create a new OpenAI exception.
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
        string $message = 'OpenAI API error',
        ?string $openaiErrorType = null,
        ?string $openaiErrorCode = null,
        ?string $requestId = null,
        array $details = [],
        bool $retryable = false,
        int $code = 500,
        ?\Exception $previous = null
    ) {
        parent::__construct($message, 'openai', null, $details, $retryable, $code, $previous);

        $this->openaiErrorType = $openaiErrorType;
        $this->openaiErrorCode = $openaiErrorCode;
        $this->requestId = $requestId;
    }

    /**
     * Create exception from OpenAI API error response.
     *
     * @param  array  $errorData  Error data from OpenAI API
     * @param  string|null  $requestId  Request ID
     */
    public static function fromApiError(array $errorData, ?string $requestId = null): static
    {
        $message = $errorData['message'] ?? 'Unknown OpenAI API error';
        $type = $errorData['type'] ?? null;
        $code = $errorData['code'] ?? null;

        return new static(
            $message,
            $type,
            $code,
            $requestId,
            $errorData,
            static::isRetryableError($type),
            static::getHttpCodeForErrorType($type)
        );
    }

    /**
     * Get the OpenAI error type.
     */
    public function getOpenAIErrorType(): ?string
    {
        return $this->openaiErrorType;
    }

    /**
     * Get the OpenAI error code.
     */
    public function getOpenAIErrorCode(): ?string
    {
        return $this->openaiErrorCode;
    }

    /**
     * Get the request ID.
     */
    public function getRequestId(): ?string
    {
        return $this->requestId;
    }

    /**
     * Check if the error type is retryable.
     */
    protected static function isRetryableError(?string $errorType): bool
    {
        $retryableTypes = [
            'server_error',
            'rate_limit_exceeded',
            'timeout',
            'connection_error',
        ];

        return in_array($errorType, $retryableTypes);
    }

    /**
     * Get HTTP status code for error type.
     */
    protected static function getHttpCodeForErrorType(?string $errorType): int
    {
        return match ($errorType) {
            'invalid_api_key', 'invalid_request_error' => 400,
            'authentication_error' => 401,
            'permission_error' => 403,
            'not_found_error' => 404,
            'rate_limit_exceeded' => 429,
            'server_error' => 500,
            'service_unavailable' => 503,
            default => 500,
        };
    }

    /**
     * Get formatted error message for logging.
     */
    public function getFormattedMessage(): string
    {
        $parts = ['OpenAI Error'];

        if ($this->openaiErrorType) {
            $parts[] = "Type: {$this->openaiErrorType}";
        }

        if ($this->openaiErrorCode) {
            $parts[] = "Code: {$this->openaiErrorCode}";
        }

        if ($this->requestId) {
            $parts[] = "Request ID: {$this->requestId}";
        }

        $parts[] = "Message: {$this->getMessage()}";

        return implode(' | ', $parts);
    }
}
