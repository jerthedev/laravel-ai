<?php

namespace JTD\LaravelAI\Exceptions\Gemini;

use JTD\LaravelAI\Exceptions\ProviderException;

/**
 * Base exception for Gemini-specific errors.
 *
 * This exception extends the general ProviderException and adds
 * Gemini-specific error handling and context.
 */
class GeminiException extends ProviderException
{
    /**
     * Gemini error type from API response.
     */
    public ?string $geminiErrorType = null;

    /**
     * Gemini error code from API response.
     */
    public ?string $geminiErrorCode = null;

    /**
     * Gemini request ID for debugging.
     */
    public ?string $requestId = null;

    /**
     * Safety ratings from Gemini response.
     */
    public array $safetyRatings = [];

    /**
     * Create a new Gemini exception.
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
        string $message = 'Gemini API error',
        ?string $geminiErrorType = null,
        ?string $geminiErrorCode = null,
        ?string $requestId = null,
        array $details = [],
        bool $retryable = false,
        int $code = 500,
        ?\Exception $previous = null
    ) {
        parent::__construct($message, 'gemini', null, $details, $retryable, $code, $previous);

        $this->geminiErrorType = $geminiErrorType;
        $this->geminiErrorCode = $geminiErrorCode;
        $this->requestId = $requestId;
        $this->safetyRatings = $details['safety_ratings'] ?? [];
    }

    /**
     * Create exception from Gemini API error response.
     *
     * @param  array  $errorData  Error data from Gemini API
     * @param  string|null  $requestId  Request ID
     */
    public static function fromApiError(array $errorData, ?string $requestId = null): static
    {
        $message = $errorData['message'] ?? 'Unknown Gemini API error';
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
     * Get the Gemini error type.
     */
    public function getGeminiErrorType(): ?string
    {
        return $this->geminiErrorType;
    }

    /**
     * Get the Gemini error code.
     */
    public function getGeminiErrorCode(): ?string
    {
        return $this->geminiErrorCode;
    }

    /**
     * Get the request ID.
     */
    public function getRequestId(): ?string
    {
        return $this->requestId;
    }

    /**
     * Get safety ratings.
     */
    public function getSafetyRatings(): array
    {
        return $this->safetyRatings;
    }

    /**
     * Check if the error has safety ratings.
     */
    public function hasSafetyRatings(): bool
    {
        return ! empty($this->safetyRatings);
    }

    /**
     * Get safety rating for a specific category.
     */
    public function getSafetyRating(string $category): ?array
    {
        foreach ($this->safetyRatings as $rating) {
            if (($rating['category'] ?? '') === $category) {
                return $rating;
            }
        }

        return null;
    }

    /**
     * Check if content was blocked by safety filters.
     */
    public function isContentBlocked(): bool
    {
        foreach ($this->safetyRatings as $rating) {
            $probability = $rating['probability'] ?? 'NEGLIGIBLE';
            if (in_array($probability, ['HIGH', 'MEDIUM'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the highest safety concern category.
     */
    public function getHighestSafetyConcern(): ?array
    {
        $highestRating = null;
        $highestLevel = 0;

        $levels = [
            'NEGLIGIBLE' => 1,
            'LOW' => 2,
            'MEDIUM' => 3,
            'HIGH' => 4,
        ];

        foreach ($this->safetyRatings as $rating) {
            $probability = $rating['probability'] ?? 'NEGLIGIBLE';
            $level = $levels[$probability] ?? 0;

            if ($level > $highestLevel) {
                $highestLevel = $level;
                $highestRating = $rating;
            }
        }

        return $highestRating;
    }

    /**
     * Check if an error type is retryable.
     */
    protected static function isRetryableError(?string $errorType): bool
    {
        $retryableTypes = [
            'server_error',
            'service_unavailable',
            'timeout',
            'rate_limit_exceeded',
            'bad_gateway',
            'gateway_timeout',
        ];

        return in_array($errorType, $retryableTypes);
    }

    /**
     * Get HTTP status code for error type.
     */
    protected static function getHttpCodeForErrorType(?string $errorType): int
    {
        return match ($errorType) {
            'invalid_request' => 400,
            'invalid_credentials', 'authentication_error', 'permission_denied' => 401,
            'not_found' => 404,
            'rate_limit_exceeded', 'quota_exceeded' => 429,
            'safety_violation', 'content_blocked' => 400,
            'server_error' => 500,
            'bad_gateway' => 502,
            'service_unavailable' => 503,
            'gateway_timeout' => 504,
            default => 500,
        };
    }

    /**
     * Convert exception to array for logging.
     */
    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'gemini_error_type' => $this->geminiErrorType,
            'gemini_error_code' => $this->geminiErrorCode,
            'request_id' => $this->requestId,
            'safety_ratings' => $this->safetyRatings,
            'content_blocked' => $this->isContentBlocked(),
            'highest_safety_concern' => $this->getHighestSafetyConcern(),
        ]);
    }

    /**
     * Get user-friendly error message.
     */
    public function getUserFriendlyMessage(): string
    {
        if ($this->isContentBlocked()) {
            return 'Your request was blocked by safety filters. Please modify your content and try again.';
        }

        return match ($this->geminiErrorType) {
            'invalid_credentials', 'authentication_error' => 'Invalid API credentials. Please check your Gemini API key.',
            'rate_limit_exceeded' => 'Too many requests. Please wait a moment before trying again.',
            'quota_exceeded' => 'API quota exceeded. Please check your usage limits.',
            'server_error', 'service_unavailable' => 'Gemini service is temporarily unavailable. Please try again later.',
            'timeout' => 'Request timed out. Please try again.',
            default => 'An error occurred while processing your request. Please try again.',
        };
    }

    /**
     * Get suggested actions for the error.
     */
    public function getSuggestedActions(): array
    {
        if ($this->isContentBlocked()) {
            return [
                'Review your input for potentially harmful content',
                'Modify your request to comply with safety guidelines',
                'Try rephrasing your request',
                'Check Gemini\'s usage policies',
            ];
        }

        return match ($this->geminiErrorType) {
            'invalid_credentials' => [
                'Verify your Gemini API key is correct',
                'Check that your API key has the necessary permissions',
                'Ensure your API key is not expired',
            ],
            'rate_limit_exceeded' => [
                'Wait before making another request',
                'Implement exponential backoff in your application',
                'Consider upgrading to a higher tier plan',
            ],
            'quota_exceeded' => [
                'Check your current usage in the Gemini console',
                'Upgrade your plan if needed',
                'Optimize your requests to use fewer tokens',
            ],
            'server_error', 'service_unavailable' => [
                'Try again in a few moments',
                'Check Gemini service status',
                'Implement retry logic with exponential backoff',
            ],
            default => [
                'Try again later',
                'Check your request parameters',
                'Contact support if the issue persists',
            ],
        };
    }
}
