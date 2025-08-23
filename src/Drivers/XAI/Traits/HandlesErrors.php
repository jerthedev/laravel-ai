<?php

namespace JTD\LaravelAI\Drivers\XAI\Traits;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;
use JTD\LaravelAI\Exceptions\XAI\XAIException;
use JTD\LaravelAI\Exceptions\XAI\XAIInvalidCredentialsException;
use JTD\LaravelAI\Exceptions\XAI\XAIInvalidRequestException;
use JTD\LaravelAI\Exceptions\XAI\XAIQuotaExceededException;
use JTD\LaravelAI\Exceptions\XAI\XAIRateLimitException;
use JTD\LaravelAI\Exceptions\XAI\XAIServerException;

/**
 * Handles Errors for xAI
 *
 * Comprehensive error handling for xAI API responses.
 * Maps HTTP status codes and error types to specific exceptions
 * with retry logic and detailed error information.
 */
trait HandlesErrors
{
    /**
     * Handle API errors from xAI responses.
     */
    protected function handleApiError(?Response $response, array $params = [], ?\Exception $originalException = null): void
    {
        if ($originalException && ! $response) {
            $this->handleNetworkError($originalException, $params);

            return;
        }

        if (! $response) {
            throw new XAIException('Unknown error occurred');
        }

        $statusCode = $response->status();
        $body = $response->json() ?? [];
        $error = $body['error'] ?? [];

        // Log the error for debugging
        Log::error('xAI API error', [
            'provider' => $this->providerName,
            'status_code' => $statusCode,
            'error_type' => $error['type'] ?? 'unknown',
            'error_code' => $error['code'] ?? null,
            'error_message' => $error['message'] ?? 'Unknown error',
            'model' => $params['model'] ?? 'unknown',
            'request_id' => $response->header('x-request-id'),
        ]);

        // Handle specific error types
        match ($statusCode) {
            400 => $this->handleBadRequestError($error, $params),
            401 => $this->handleAuthenticationError($error, $params),
            403 => $this->handlePermissionError($error, $params),
            404 => $this->handleNotFoundError($error, $params),
            429 => $this->handleRateLimitError($error, $response, $params),
            500, 502, 503, 504 => $this->handleServerError($error, $statusCode, $params),
            default => $this->handleGenericError($error, $statusCode, $params),
        };
    }

    /**
     * Handle bad request errors (400).
     */
    protected function handleBadRequestError($error, array $params): void
    {
        if (is_string($error)) {
            $message = $error;
            $code = null;
        } else {
            $message = $error['message'] ?? 'Bad request';
            $code = $error['code'] ?? null;
        }

        throw new XAIInvalidRequestException(
            message: $message,
            code: $code,
            details: [
                'error_type' => $error['type'] ?? 'invalid_request_error',
                'param' => $error['param'] ?? null,
                'request_params' => $this->sanitizeParams($params),
            ]
        );
    }

    /**
     * Handle authentication errors (401).
     */
    protected function handleAuthenticationError(array $error, array $params): void
    {
        $message = $error['message'] ?? 'Invalid API key or authentication failed';

        throw new XAIInvalidCredentialsException(
            message: $message,
            details: [
                'error_type' => $error['type'] ?? 'authentication_error',
                'api_key_format' => $this->getApiKeyFormat(),
            ]
        );
    }

    /**
     * Handle permission errors (403).
     */
    protected function handlePermissionError(array $error, array $params): void
    {
        $message = $error['message'] ?? 'Permission denied or insufficient quota';

        throw new XAIQuotaExceededException(
            message: $message,
            quotaType: 'permission',
            details: [
                'error_type' => $error['type'] ?? 'permission_error',
                'model' => $params['model'] ?? 'unknown',
            ]
        );
    }

    /**
     * Handle not found errors (404).
     */
    protected function handleNotFoundError($error, array $params): void
    {
        if (is_string($error)) {
            $message = $error;
        } else {
            $message = $error['message'] ?? 'Resource not found';
        }
        $model = $params['model'] ?? 'unknown';

        if (str_contains($message, 'model') || str_contains($message, $model)) {
            $message = "Model '{$model}' not found or not available";
        }

        throw new XAIInvalidRequestException(
            message: $message,
            details: [
                'error_type' => $error['type'] ?? 'not_found_error',
                'model' => $model,
                'available_models' => $this->getCapabilities()['supported_models'] ?? [],
            ]
        );
    }

    /**
     * Handle rate limit errors (429).
     */
    protected function handleRateLimitError(array $error, Response $response, array $params): void
    {
        $message = $error['message'] ?? 'Rate limit exceeded';
        $retryAfter = (int) ($response->header('retry-after') ?? 60);
        $limitType = $this->determineLimitType($error['message'] ?? '');

        throw new XAIRateLimitException(
            message: $message,
            retryAfter: $retryAfter,
            limitType: $limitType,
            details: [
                'error_type' => $error['type'] ?? 'rate_limit_error',
                'limit_type' => $limitType,
                'retry_after' => $retryAfter,
                'model' => $params['model'] ?? 'unknown',
            ]
        );
    }

    /**
     * Handle server errors (5xx).
     */
    protected function handleServerError(array $error, int $statusCode, array $params): void
    {
        $message = $error['message'] ?? "Server error (HTTP {$statusCode})";
        $isRetryable = in_array($statusCode, [500, 502, 503, 504]);

        throw new XAIServerException(
            message: $message,
            statusCode: $statusCode,
            isRetryable: $isRetryable,
            details: [
                'error_type' => $error['type'] ?? 'server_error',
                'status_code' => $statusCode,
                'retryable' => $isRetryable,
                'model' => $params['model'] ?? 'unknown',
            ]
        );
    }

    /**
     * Handle generic errors.
     */
    protected function handleGenericError(array $error, int $statusCode, array $params): void
    {
        $message = $error['message'] ?? "HTTP {$statusCode} error";

        throw new XAIException(
            message: $message,
            xaiErrorType: $error['type'] ?? 'unknown_error',
            details: [
                'status_code' => $statusCode,
                'error_type' => $error['type'] ?? 'unknown_error',
                'error_code' => $error['code'] ?? null,
                'model' => $params['model'] ?? 'unknown',
            ]
        );
    }

    /**
     * Handle network errors (connection issues, timeouts, etc.).
     */
    protected function handleNetworkError(\Exception $exception, array $params): void
    {
        $message = "Network error: {$exception->getMessage()}";

        Log::error('xAI network error', [
            'provider' => $this->providerName,
            'error' => $exception->getMessage(),
            'model' => $params['model'] ?? 'unknown',
            'exception_type' => get_class($exception),
        ]);

        throw new XAIServerException(
            message: $message,
            statusCode: 0,
            isRetryable: true,
            details: [
                'error_type' => 'network_error',
                'original_exception' => get_class($exception),
                'model' => $params['model'] ?? 'unknown',
            ],
            previous: $exception
        );
    }

    /**
     * Determine the type of rate limit from error message.
     */
    protected function determineLimitType(string $message): string
    {
        $message = strtolower($message);

        if (str_contains($message, 'token')) {
            return 'tokens';
        }

        if (str_contains($message, 'request')) {
            return 'requests';
        }

        if (str_contains($message, 'quota')) {
            return 'quota';
        }

        return 'requests'; // Default assumption
    }

    /**
     * Get API key format for error reporting.
     */
    protected function getApiKeyFormat(): string
    {
        $apiKey = $this->config['api_key'] ?? '';

        if (empty($apiKey)) {
            return 'missing';
        }

        if (str_starts_with($apiKey, 'xai-')) {
            return 'valid_format';
        }

        return 'invalid_format';
    }

    /**
     * Sanitize parameters for error reporting.
     */
    protected function sanitizeParams(array $params): array
    {
        $sanitized = $params;

        // Remove sensitive data
        unset($sanitized['api_key']);

        // Truncate long content
        if (isset($sanitized['messages'])) {
            foreach ($sanitized['messages'] as &$message) {
                if (isset($message['content']) && strlen($message['content']) > 200) {
                    $message['content'] = substr($message['content'], 0, 200) . '...';
                }
            }
        }

        return $sanitized;
    }

    /**
     * Check if an error is retryable.
     */
    protected function isRetryableError(\Exception $exception): bool
    {
        if ($exception instanceof XAIServerException) {
            return $exception->isRetryable();
        }

        if ($exception instanceof XAIRateLimitException) {
            return true;
        }

        // Network errors are generally retryable
        if (str_contains($exception->getMessage(), 'network') ||
            str_contains($exception->getMessage(), 'timeout') ||
            str_contains($exception->getMessage(), 'connection')) {
            return true;
        }

        return false;
    }

    /**
     * Get retry delay for retryable errors.
     */
    protected function getRetryDelay(\Exception $exception, int $attempt): int
    {
        if ($exception instanceof XAIRateLimitException) {
            return $exception->getRetryAfter() * 1000; // Convert to milliseconds
        }

        // Exponential backoff for other retryable errors
        $baseDelay = $this->config['retry_delay'] ?? 1000;
        $maxDelay = $this->config['max_retry_delay'] ?? 30000;

        $delay = $baseDelay * pow(2, $attempt - 1);

        return min($delay, $maxDelay);
    }

    /**
     * Log error details for monitoring.
     */
    protected function logErrorDetails(\Exception $exception, array $context = []): void
    {
        $logData = [
            'provider' => $this->providerName,
            'exception_type' => get_class($exception),
            'message' => $exception->getMessage(),
            'code' => $exception->getCode(),
        ];

        if ($exception instanceof XAIException) {
            $logData['xai_error_type'] = $exception->getXAIErrorType();
            $logData['details'] = $exception->getDetails();
        }

        $logData = array_merge($logData, $context);

        Log::error('xAI driver error', $logData);
    }

    /**
     * Create error summary for monitoring.
     */
    protected function createErrorSummary(\Exception $exception): array
    {
        return [
            'provider' => $this->providerName,
            'error_type' => get_class($exception),
            'message' => $exception->getMessage(),
            'retryable' => $this->isRetryableError($exception),
            'timestamp' => now()->toISOString(),
        ];
    }
}
