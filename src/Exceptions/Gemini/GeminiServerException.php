<?php

namespace JTD\LaravelAI\Exceptions\Gemini;

/**
 * Exception for Gemini server errors.
 */
class GeminiServerException extends GeminiException
{
    /**
     * Create a new Gemini server exception.
     *
     * @param  string  $message  Exception message
     * @param  string|null  $geminiErrorType  Gemini error type
     * @param  string|null  $geminiErrorCode  Gemini error code
     * @param  string|null  $requestId  Request ID
     * @param  array  $details  Additional error details
     * @param  bool  $retryable  Whether the error is retryable
     * @param  int  $code  Exception code
     * @param  \Exception|null  $previous  Previous exception
     */
    public function __construct(
        string $message = 'Gemini server error',
        ?string $geminiErrorType = 'server_error',
        ?string $geminiErrorCode = null,
        ?string $requestId = null,
        array $details = [],
        bool $retryable = true,
        int $code = 500,
        ?\Exception $previous = null
    ) {
        parent::__construct(
            $message,
            $geminiErrorType,
            $geminiErrorCode,
            $requestId,
            $details,
            $retryable,
            $code,
            $previous
        );
    }

    /**
     * Get user-friendly error message.
     */
    public function getUserFriendlyMessage(): string
    {
        return match ($this->geminiErrorType) {
            'server_error' => 'Gemini service is experiencing technical difficulties. Please try again later.',
            'service_unavailable' => 'Gemini service is temporarily unavailable. Please try again in a few minutes.',
            'timeout' => 'Your request timed out. Please try again or consider reducing the complexity of your request.',
            'bad_gateway' => 'There was a problem connecting to Gemini. Please try again later.',
            'gateway_timeout' => 'The request to Gemini timed out. Please try again later.',
            default => 'A server error occurred. Please try again later.',
        };
    }

    /**
     * Get suggested actions for the error.
     */
    public function getSuggestedActions(): array
    {
        $baseActions = [
            'Try again in a few moments',
            'Check Gemini service status',
            'Implement retry logic with exponential backoff',
        ];

        return match ($this->geminiErrorType) {
            'timeout' => array_merge($baseActions, [
                'Consider reducing the size or complexity of your request',
                'Increase timeout settings if possible',
                'Break large requests into smaller chunks',
            ]),
            'service_unavailable' => array_merge($baseActions, [
                'Wait longer before retrying',
                'Check for scheduled maintenance announcements',
                'Consider using a different model if available',
            ]),
            default => $baseActions,
        };
    }

    /**
     * Get recommended retry configuration.
     */
    public function getRetryConfig(): array
    {
        return match ($this->geminiErrorType) {
            'timeout' => [
                'max_attempts' => 2,
                'base_delay' => 2000, // 2 seconds
                'max_delay' => 10000, // 10 seconds
                'use_exponential_backoff' => false,
            ],
            'service_unavailable' => [
                'max_attempts' => 3,
                'base_delay' => 5000, // 5 seconds
                'max_delay' => 60000, // 60 seconds
                'use_exponential_backoff' => true,
            ],
            'server_error' => [
                'max_attempts' => 3,
                'base_delay' => 1000, // 1 second
                'max_delay' => 30000, // 30 seconds
                'use_exponential_backoff' => true,
            ],
            default => [
                'max_attempts' => 2,
                'base_delay' => 1000,
                'max_delay' => 5000,
                'use_exponential_backoff' => true,
            ],
        };
    }

    /**
     * Check if this is a temporary server issue.
     */
    public function isTemporaryIssue(): bool
    {
        return in_array($this->geminiErrorType, [
            'server_error',
            'service_unavailable',
            'timeout',
            'bad_gateway',
            'gateway_timeout',
        ]);
    }

    /**
     * Convert exception to array for logging.
     */
    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'is_temporary' => $this->isTemporaryIssue(),
            'retry_config' => $this->getRetryConfig(),
        ]);
    }
}
