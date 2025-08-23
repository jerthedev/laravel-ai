<?php

namespace JTD\LaravelAI\Drivers\Gemini\Traits;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;
use JTD\LaravelAI\Drivers\Gemini\Support\ErrorMapper;

/**
 * Handles Error Processing and Retry Logic for Gemini
 *
 * Comprehensive error handling, exception mapping,
 * and retry logic with exponential backoff for Google Gemini API.
 */
trait HandlesErrors
{
    /**
     * Gemini-specific error codes and their mappings.
     */
    protected array $geminiErrorMap = [
        400 => 'invalid_request',
        401 => 'invalid_credentials',
        403 => 'permission_denied',
        404 => 'not_found',
        429 => 'rate_limit_exceeded',
        500 => 'server_error',
        502 => 'bad_gateway',
        503 => 'service_unavailable',
        504 => 'gateway_timeout',
    ];

    /**
     * Retryable error types.
     */
    protected array $retryableErrors = [
        'rate_limit_exceeded',
        'server_error',
        'bad_gateway',
        'service_unavailable',
        'gateway_timeout',
        'network_error',
        'timeout',
    ];

    /**
     * Handle API errors and throw appropriate exceptions.
     */
    protected function handleApiError(\Exception $exception): void
    {
        // Use ErrorMapper to map to appropriate exception type and throw
        throw ErrorMapper::mapException($exception);
    }

    /**
     * Map HTTP exceptions to specific exception types.
     */
    protected function mapHttpException(\Illuminate\Http\Client\RequestException $exception): \Exception
    {
        $response = $exception->response;
        $statusCode = $response->status();
        $data = $response->json();

        $error = $data['error'] ?? [];
        $message = $error['message'] ?? $exception->getMessage();
        $errorCode = $error['code'] ?? $statusCode;

        // Map based on status code
        switch ($statusCode) {
            case 400:
                return new \JTD\LaravelAI\Exceptions\InvalidRequestException(
                    $this->enhanceErrorMessage($message, 'invalid_request'),
                    $errorCode,
                    $exception
                );

            case 401:
            case 403:
                return new \JTD\LaravelAI\Exceptions\InvalidCredentialsException(
                    $this->enhanceErrorMessage($message, 'invalid_credentials'),
                    $errorCode,
                    $exception
                );

            case 404:
                return new \JTD\LaravelAI\Exceptions\ModelNotFoundException(
                    $this->enhanceErrorMessage($message, 'not_found'),
                    $errorCode,
                    $exception
                );

            case 429:
                $retryAfter = $this->extractRetryAfter($response);

                return new \JTD\LaravelAI\Exceptions\RateLimitException(
                    $this->enhanceErrorMessage($message, 'rate_limit_exceeded'),
                    $errorCode,
                    $exception,
                    $retryAfter
                );

            case 500:
            case 502:
            case 503:
            case 504:
                return new \JTD\LaravelAI\Exceptions\ServerException(
                    $this->enhanceErrorMessage($message, 'server_error'),
                    $errorCode,
                    $exception
                );

            default:
                return new \JTD\LaravelAI\Exceptions\ProviderException(
                    "Gemini API error: {$message}",
                    $errorCode,
                    $exception
                );
        }
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
                if (! $this->isRetryableError($e)) {
                    break;
                }

                // Calculate delay for next attempt
                $delay = $this->calculateRetryDelay($attempt, $baseDelay, $maxDelay, $e);

                // Log retry attempt
                $this->logRetryAttempt($attempt, $delay, $e);

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
        if ($this->isRateLimitError($exception)) {
            $rateLimitDelay = $this->extractRateLimitDelay($exception);
            if ($rateLimitDelay > 0) {
                return min($rateLimitDelay, $maxDelay);
            }
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
        if ($exception instanceof \Illuminate\Http\Client\RequestException) {
            return $exception->response->status() === 429;
        }

        return false;
    }

    /**
     * Extract rate limit delay from exception.
     */
    protected function extractRateLimitDelay(\Exception $exception): int
    {
        if ($exception instanceof \Illuminate\Http\Client\RequestException) {
            $response = $exception->response;

            // Check for Retry-After header
            $retryAfter = $response->header('Retry-After');
            if ($retryAfter) {
                return (int) $retryAfter * 1000; // Convert to milliseconds
            }

            // Check for X-RateLimit-Reset header
            $resetTime = $response->header('X-RateLimit-Reset');
            if ($resetTime) {
                $delay = max(0, $resetTime - time());

                return $delay * 1000; // Convert to milliseconds
            }
        }

        return 0;
    }

    /**
     * Extract retry after value from response.
     */
    protected function extractRetryAfter(Response $response): ?int
    {
        $retryAfter = $response->header('Retry-After');

        return $retryAfter ? (int) $retryAfter : null;
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
     * Enhance error message with context.
     */
    protected function enhanceErrorMessage(string $message, string $errorType): string
    {
        $enhancements = [
            'invalid_request' => 'Invalid request to Gemini API',
            'invalid_credentials' => 'Invalid Gemini API credentials',
            'permission_denied' => 'Permission denied for Gemini API',
            'not_found' => 'Gemini model or resource not found',
            'rate_limit_exceeded' => 'Gemini API rate limit exceeded',
            'server_error' => 'Gemini API server error',
        ];

        $enhancement = $enhancements[$errorType] ?? 'Gemini API error';

        return "{$enhancement}: {$message}";
    }

    /**
     * Log retry attempt for debugging.
     */
    protected function logRetryAttempt(int $attempt, int $delay, \Exception $exception): void
    {
        if (config('app.debug', false)) {
            Log::warning('Gemini API retry attempt', [
                'provider' => $this->providerName,
                'attempt' => $attempt,
                'delay_ms' => $delay,
                'error' => $exception->getMessage(),
                'error_type' => get_class($exception),
            ]);
        }
    }

    /**
     * Log error for debugging.
     */
    protected function logError(\Exception $exception, array $context = []): void
    {
        if (config('app.debug', false)) {
            Log::error('Gemini API error', array_merge([
                'provider' => $this->providerName,
                'error' => $exception->getMessage(),
                'error_type' => get_class($exception),
                'trace' => $exception->getTraceAsString(),
            ], $context));
        }
    }

    /**
     * Create error context for logging and debugging.
     */
    protected function createErrorContext(array $options = [], array $additionalContext = []): array
    {
        return array_merge([
            'provider' => $this->providerName,
            'model' => $options['model'] ?? $this->getCurrentModel(),
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
        if ($exception instanceof \Illuminate\Http\Client\RequestException) {
            $statusCode = $exception->response->status();

            // Fail fast for these status codes (no retry)
            $failFastCodes = [400, 401, 403, 404];

            return in_array($statusCode, $failFastCodes);
        }

        return false;
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
