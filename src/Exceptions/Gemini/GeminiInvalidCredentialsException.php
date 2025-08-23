<?php

namespace JTD\LaravelAI\Exceptions\Gemini;

/**
 * Exception for Gemini authentication and credential errors.
 */
class GeminiInvalidCredentialsException extends GeminiException
{
    /**
     * API key that caused the error (masked for security).
     */
    public ?string $apiKey = null;

    /**
     * Create a new Gemini invalid credentials exception.
     *
     * @param  string  $message  Exception message
     * @param  string|null  $apiKey  API key (will be masked)
     * @param  string|null  $requestId  Request ID
     * @param  string|null  $geminiErrorType  Gemini error type
     * @param  array  $details  Additional error details
     * @param  int  $code  Exception code
     * @param  \Exception|null  $previous  Previous exception
     */
    public function __construct(
        string $message = 'Invalid Gemini API credentials',
        ?string $apiKey = null,
        ?string $requestId = null,
        ?string $geminiErrorType = 'invalid_credentials',
        array $details = [],
        int $code = 401,
        ?\Exception $previous = null
    ) {
        parent::__construct(
            $message,
            $geminiErrorType,
            null,
            $requestId,
            $details,
            false, // Not retryable
            $code,
            $previous
        );

        $this->apiKey = $this->maskApiKey($apiKey);
    }

    /**
     * Get the masked API key.
     */
    public function getApiKey(): ?string
    {
        return $this->apiKey;
    }

    /**
     * Mask API key for security.
     */
    protected function maskApiKey(?string $apiKey): ?string
    {
        if (! $apiKey) {
            return null;
        }

        if (strlen($apiKey) <= 8) {
            return str_repeat('*', strlen($apiKey));
        }

        return substr($apiKey, 0, 4) . str_repeat('*', strlen($apiKey) - 8) . substr($apiKey, -4);
    }

    /**
     * Get user-friendly error message.
     */
    public function getUserFriendlyMessage(): string
    {
        return 'Invalid API credentials. Please check your Gemini API key and ensure it has the necessary permissions.';
    }

    /**
     * Get suggested actions for the error.
     */
    public function getSuggestedActions(): array
    {
        return [
            'Verify your Gemini API key is correct',
            'Check that your API key has the necessary permissions',
            'Ensure your API key is not expired or revoked',
            'Verify you\'re using the correct API endpoint',
            'Check if your account has access to the requested model',
        ];
    }

    /**
     * Convert exception to array for logging.
     */
    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'api_key' => $this->apiKey,
            'credential_type' => 'api_key',
        ]);
    }
}
