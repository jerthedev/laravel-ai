<?php

namespace JTD\LaravelAI\Drivers\OpenAI\Traits;

use JTD\LaravelAI\Drivers\OpenAI\Support\ErrorMapper;

/**
 * Handles Error Processing and Retry Logic
 *
 * Comprehensive error handling, exception mapping,
 * and retry logic with exponential backoff.
 */
trait HandlesErrors
{
    /**
     * Handle API errors and throw appropriate exceptions.
     */
    protected function handleApiError(\Exception $exception): void
    {
        // Map to appropriate exception type and throw
        throw ErrorMapper::mapException($exception);
    }

    /**
     * Execute API call with retry logic and exponential backoff.
     */
    protected function executeWithRetry(callable $apiCall, array $options = [])
    {
        $maxAttempts = $options['retry_attempts'] ?? $this->config['retry_attempts'] ?? 3;
        $baseDelay = $options['retry_delay'] ?? $this->config['retry_delay'] ?? 1000; // milliseconds
        $maxDelay = $options['max_retry_delay'] ?? 30000; // 30 seconds max

        $attempt = 1;
        $lastException = null;

        while ($attempt <= $maxAttempts) {
            try {
                return $apiCall();
            } catch (\Exception $e) {
                $lastException = $e;

                // Don't retry on the last attempt
                if ($attempt >= $maxAttempts) {
                    break;
                }

                // Check if error is retryable
                if (! ErrorMapper::isRetryableError($e)) {
                    break;
                }

                // Calculate delay for next attempt
                $delay = $this->calculateRetryDelay($attempt, $baseDelay, $maxDelay, $e);

                // Wait before retrying (skip in test environment)
                if (! $this->isTestEnvironment()) {
                    usleep($delay * 1000); // Convert to microseconds
                }

                $attempt++;
            }
        }

        // All retries exhausted, handle the error
        $this->handleApiError($lastException);
    }

    /**
     * Calculate retry delay with exponential backoff and jitter.
     */
    protected function calculateRetryDelay(int $attempt, int $baseDelay, int $maxDelay, \Exception $exception): int
    {
        // For rate limit errors, use the delay from headers if available
        if (ErrorMapper::isRateLimitError($exception)) {
            $rateLimitDelay = ErrorMapper::extractRateLimitDelay($exception);

            return min($rateLimitDelay, $maxDelay);
        }

        // Exponential backoff: delay = baseDelay * (2 ^ (attempt - 1))
        $exponentialDelay = $baseDelay * (2 ** ($attempt - 1));

        // Add jitter (random factor between 0.5 and 1.5)
        $jitter = 0.5 + (mt_rand() / mt_getrandmax());
        $delayWithJitter = (int) ($exponentialDelay * $jitter);

        // Cap at maximum delay
        return min($delayWithJitter, $maxDelay);
    }

    /**
     * Check if we're running in a test environment.
     */
    protected function isTestEnvironment(): bool
    {
        return defined('PHPUNIT_COMPOSER_INSTALL') ||
               (function_exists('app') && app()->environment('testing')) ||
               isset($_ENV['APP_ENV']) && $_ENV['APP_ENV'] === 'testing';
    }

    /**
     * Check if an error is retryable.
     */
    protected function isRetryableError(\Exception $exception): bool
    {
        return ErrorMapper::isRetryableError($exception);
    }

    /**
     * Check if error is a rate limit error.
     */
    protected function isRateLimitError(\Exception $exception): bool
    {
        return ErrorMapper::isRateLimitError($exception);
    }

    /**
     * Extract rate limit delay from exception.
     */
    protected function extractRateLimitDelay(\Exception $exception): int
    {
        return ErrorMapper::extractRateLimitDelay($exception);
    }

    /**
     * Get retry configuration for specific error type.
     */
    protected function getRetryConfig(string $errorType): array
    {
        return ErrorMapper::getRetryConfig($errorType);
    }

    /**
     * Extract comprehensive error information.
     */
    protected function extractErrorInfo(\Exception $exception): array
    {
        return ErrorMapper::extractErrorInfo($exception);
    }

    /**
     * Get HTTP status code from exception.
     */
    protected function getHttpStatusCode(\Exception $exception): int
    {
        return ErrorMapper::getHttpStatusCode($exception);
    }

    /**
     * Enhance error message with context.
     */
    protected function enhanceErrorMessage(string $message, ?string $errorType): string
    {
        return ErrorMapper::enhanceErrorMessage($message, $errorType);
    }

    /**
     * Log error for debugging (can be extended).
     */
    protected function logError(\Exception $exception, array $context = []): void
    {
        // This can be extended to integrate with Laravel's logging system
        // For now, we'll keep it simple to avoid dependencies

        if (config('app.debug', false)) {
            error_log(sprintf(
                '[OpenAI Driver] %s: %s (Context: %s)',
                get_class($exception),
                $exception->getMessage(),
                json_encode($context)
            ));
        }
    }

    /**
     * Handle specific error scenarios with custom logic.
     */
    protected function handleSpecificError(\Exception $exception, array $context = []): void
    {
        $errorInfo = $this->extractErrorInfo($exception);

        // Log the error
        $this->logError($exception, array_merge($context, $errorInfo));

        // Handle specific error types
        switch ($errorInfo['type']) {
            case 'rate_limit_exceeded':
                $this->handleRateLimitError($exception, $errorInfo);
                break;

            case 'quota_exceeded':
                $this->handleQuotaError($exception, $errorInfo);
                break;

            case 'invalid_api_key':
                $this->handleCredentialError($exception, $errorInfo);
                break;

            case 'server_error':
                $this->handleServerError($exception, $errorInfo);
                break;
        }
    }

    /**
     * Handle rate limit errors.
     */
    protected function handleRateLimitError(\Exception $exception, array $errorInfo): void
    {
        // Could implement rate limit tracking, backoff strategies, etc.
        // For now, just ensure proper exception mapping
    }

    /**
     * Handle quota exceeded errors.
     */
    protected function handleQuotaError(\Exception $exception, array $errorInfo): void
    {
        // Could implement quota monitoring, alerts, etc.
        // For now, just ensure proper exception mapping
    }

    /**
     * Handle credential errors.
     */
    protected function handleCredentialError(\Exception $exception, array $errorInfo): void
    {
        // Could implement credential validation, rotation, etc.
        // For now, just ensure proper exception mapping
    }

    /**
     * Handle server errors.
     */
    protected function handleServerError(\Exception $exception, array $errorInfo): void
    {
        // Could implement server status monitoring, fallback strategies, etc.
        // For now, just ensure proper exception mapping
    }

    /**
     * Create error context for logging and debugging.
     */
    protected function createErrorContext(array $options = [], array $additionalContext = []): array
    {
        return array_merge([
            'provider' => $this->providerName,
            'model' => $options['model'] ?? $this->defaultModel,
            'timestamp' => now()->toISOString(),
            'request_id' => $options['request_id'] ?? null,
            'user_id' => $options['user_id'] ?? null,
            'conversation_id' => $options['conversation_id'] ?? null,
        ], $additionalContext);
    }

    /**
     * Determine if we should fail fast or retry based on error type.
     */
    protected function shouldFailFast(\Exception $exception): bool
    {
        $errorInfo = $this->extractErrorInfo($exception);

        // Fail fast for these error types (no retry)
        $failFastTypes = [
            'invalid_api_key',
            'authentication_error',
            'permission_error',
            'invalid_request_error',
            'invalid_model',
        ];

        return in_array($errorInfo['type'], $failFastTypes);
    }

    /**
     * Get appropriate timeout for retry attempt.
     */
    protected function getRetryTimeout(int $attempt): int
    {
        // Increase timeout for subsequent attempts
        $baseTimeout = $this->config['timeout'] ?? 30;

        return min($baseTimeout * $attempt, 120); // Max 2 minutes
    }
}
