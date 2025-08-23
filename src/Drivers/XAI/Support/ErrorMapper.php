<?php

namespace JTD\LaravelAI\Drivers\XAI\Support;

use Illuminate\Http\Client\Response;
use JTD\LaravelAI\Exceptions\XAI\XAIException;
use JTD\LaravelAI\Exceptions\XAI\XAIInvalidCredentialsException;
use JTD\LaravelAI\Exceptions\XAI\XAIInvalidRequestException;
use JTD\LaravelAI\Exceptions\XAI\XAIQuotaExceededException;
use JTD\LaravelAI\Exceptions\XAI\XAIRateLimitException;
use JTD\LaravelAI\Exceptions\XAI\XAIServerException;

/**
 * xAI Error Mapping and Exception Handling
 *
 * Maps xAI API errors to appropriate exception types
 * and provides error enhancement utilities.
 */
class ErrorMapper
{
    /**
     * Error type to exception class mapping.
     */
    protected static $exceptionMap = [
        'authentication_error' => XAIInvalidCredentialsException::class,
        'invalid_request_error' => XAIInvalidRequestException::class,
        'permission_error' => XAIQuotaExceededException::class,
        'rate_limit_error' => XAIRateLimitException::class,
        'server_error' => XAIServerException::class,
        'quota_exceeded' => XAIQuotaExceededException::class,
        'insufficient_quota' => XAIQuotaExceededException::class,
        'model_not_found' => XAIInvalidRequestException::class,
        'invalid_api_key' => XAIInvalidCredentialsException::class,
        'billing_not_active' => XAIQuotaExceededException::class,
    ];

    /**
     * Retryable error types.
     */
    protected static $retryableTypes = [
        'server_error',
        'rate_limit_error',
        'timeout',
        'network_error',
        'service_unavailable',
    ];

    /**
     * Map exception to appropriate xAI exception type.
     */
    public static function mapException(\Exception $exception): \Exception
    {
        $errorInfo = self::extractErrorInfo($exception);
        $errorType = $errorInfo['type'] ?? 'unknown_error';

        if (isset(self::$exceptionMap[$errorType])) {
            $exceptionClass = self::$exceptionMap[$errorType];

            return self::createSpecificException($exceptionClass, $errorType, $errorInfo, $exception);
        }

        // Default to generic xAI exception
        return new XAIException(
            message: $errorInfo['message'] ?? $exception->getMessage(),
            xaiErrorType: $errorType,
            details: $errorInfo
        );
    }

    /**
     * Create specific exception instance with proper constructor parameters.
     */
    protected static function createSpecificException(string $exceptionClass, string $errorType, array $errorInfo, \Exception $originalException): \Exception
    {
        $message = $errorInfo['message'] ?? $originalException->getMessage();
        $details = $errorInfo;

        return match ($exceptionClass) {
            XAIRateLimitException::class => new XAIRateLimitException(
                message: $message,
                retryAfter: $errorInfo['retry_after'] ?? 60,
                limitType: $errorInfo['limit_type'] ?? 'requests',
                details: $details
            ),
            XAIQuotaExceededException::class => new XAIQuotaExceededException(
                message: $message,
                quotaType: $errorInfo['quota_type'] ?? 'requests',
                currentUsage: $errorInfo['current_usage'] ?? null,
                details: $details
            ),
            XAIServerException::class => new XAIServerException(
                message: $message,
                statusCode: $errorInfo['status_code'] ?? 500,
                isRetryable: $errorInfo['retryable'] ?? true,
                details: $details
            ),
            XAIInvalidRequestException::class => new XAIInvalidRequestException(
                message: $message,
                code: $errorInfo['code'] ?? null,
                details: $details
            ),
            XAIInvalidCredentialsException::class => new XAIInvalidCredentialsException(
                message: $message,
                details: $details
            ),
            default => new XAIException(
                message: $message,
                xaiErrorType: $errorType,
                details: $details
            ),
        };
    }

    /**
     * Extract error information from various exception types.
     */
    public static function extractErrorInfo(\Exception $exception): array
    {
        $errorInfo = [
            'message' => $exception->getMessage(),
            'code' => $exception->getCode(),
            'type' => 'unknown_error',
        ];

        // Handle HTTP client exceptions
        if (method_exists($exception, 'response')) {
            $response = $exception->response;
            if ($response instanceof Response) {
                $errorInfo['status_code'] = $response->status();
                $errorInfo['retryable'] = in_array($response->status(), [500, 502, 503, 504]);

                self::parseErrorResponse($response, $errorInfo);
            }
        }

        // Handle network/timeout errors
        if (str_contains($exception->getMessage(), 'timeout') ||
            str_contains($exception->getMessage(), 'network') ||
            str_contains($exception->getMessage(), 'connection')) {
            $errorInfo['type'] = 'network_error';
            $errorInfo['retryable'] = true;
        }

        self::parseErrorMessage($errorInfo);

        return $errorInfo;
    }

    /**
     * Parse error response body for additional information.
     */
    protected static function parseErrorResponse($response, array &$errorInfo): void
    {
        try {
            $body = $response->json();
            $error = $body['error'] ?? [];

            if (! empty($error)) {
                $errorInfo['type'] = $error['type'] ?? $errorInfo['type'];
                $errorInfo['message'] = $error['message'] ?? $errorInfo['message'];
                $errorInfo['code'] = $error['code'] ?? $errorInfo['code'];
                $errorInfo['param'] = $error['param'] ?? null;

                // Extract rate limit information
                if ($errorInfo['type'] === 'rate_limit_error') {
                    $errorInfo['retry_after'] = (int) ($response->header('retry-after') ?? 60);
                    $errorInfo['limit_type'] = self::determineLimitType($errorInfo['message']);
                }

                // Extract quota information
                if (str_contains($errorInfo['message'], 'quota') || str_contains($errorInfo['message'], 'billing')) {
                    $errorInfo['quota_type'] = self::determineQuotaType($errorInfo['message']);
                }
            }
        } catch (\Exception $e) {
            // Ignore JSON parsing errors
        }
    }

    /**
     * Parse error message for additional context.
     */
    protected static function parseErrorMessage(array &$errorInfo): void
    {
        $message = strtolower($errorInfo['message']);

        // Detect error types from message content
        if (str_contains($message, 'api key') || str_contains($message, 'unauthorized')) {
            $errorInfo['type'] = 'authentication_error';
        } elseif (str_contains($message, 'rate limit') || str_contains($message, 'too many requests')) {
            $errorInfo['type'] = 'rate_limit_error';
        } elseif (str_contains($message, 'quota') || str_contains($message, 'billing')) {
            $errorInfo['type'] = 'quota_exceeded';
        } elseif (str_contains($message, 'model') && str_contains($message, 'not found')) {
            $errorInfo['type'] = 'model_not_found';
        } elseif (str_contains($message, 'invalid') || str_contains($message, 'bad request')) {
            $errorInfo['type'] = 'invalid_request_error';
        } elseif (str_contains($message, 'server error') || str_contains($message, 'internal error')) {
            $errorInfo['type'] = 'server_error';
        }
    }

    /**
     * Enhance error message with additional context.
     */
    public static function enhanceErrorMessage(string $message, string $errorType): string
    {
        return match ($errorType) {
            'authentication_error' => $message . ' (Check your xAI API key)',
            'rate_limit_error' => $message . ' (Consider implementing exponential backoff)',
            'quota_exceeded' => $message . ' (Check your xAI billing and usage limits)',
            'model_not_found' => $message . ' (Available models: grok-beta, grok-2, grok-2-mini)',
            'server_error' => $message . ' (xAI server error - this request can be retried)',
            default => $message,
        };
    }

    /**
     * Check if an error is retryable.
     */
    public static function isRetryableError(\Exception $exception): bool
    {
        $errorInfo = self::extractErrorInfo($exception);
        $errorType = $errorInfo['type'] ?? 'unknown_error';

        return in_array($errorType, self::$retryableTypes) ||
               ($errorInfo['retryable'] ?? false);
    }

    /**
     * Get HTTP status code from exception.
     */
    public static function getHttpStatusCode(\Exception $exception): int
    {
        $errorInfo = self::extractErrorInfo($exception);

        return $errorInfo['status_code'] ?? 0;
    }

    /**
     * Check if error is a rate limit error.
     */
    public static function isRateLimitError(\Exception $exception): bool
    {
        $errorInfo = self::extractErrorInfo($exception);

        return $errorInfo['type'] === 'rate_limit_error';
    }

    /**
     * Extract rate limit delay from headers.
     */
    public static function extractRateLimitDelay(\Exception $exception): int
    {
        $errorInfo = self::extractErrorInfo($exception);

        return $errorInfo['retry_after'] ?? 60;
    }

    /**
     * Get retry configuration for error type.
     */
    public static function getRetryConfig(string $errorType): array
    {
        return match ($errorType) {
            'rate_limit_error' => [
                'max_attempts' => 5,
                'base_delay' => 1000,
                'max_delay' => 60000,
                'backoff_multiplier' => 2,
            ],
            'server_error' => [
                'max_attempts' => 3,
                'base_delay' => 1000,
                'max_delay' => 30000,
                'backoff_multiplier' => 2,
            ],
            'network_error' => [
                'max_attempts' => 3,
                'base_delay' => 2000,
                'max_delay' => 30000,
                'backoff_multiplier' => 2,
            ],
            default => [
                'max_attempts' => 1,
                'base_delay' => 0,
                'max_delay' => 0,
                'backoff_multiplier' => 1,
            ],
        };
    }

    /**
     * Determine limit type from error message.
     */
    protected static function determineLimitType(string $message): string
    {
        $message = strtolower($message);

        if (str_contains($message, 'token')) {
            return 'tokens';
        }

        if (str_contains($message, 'request')) {
            return 'requests';
        }

        return 'requests'; // Default
    }

    /**
     * Determine quota type from error message.
     */
    protected static function determineQuotaType(string $message): string
    {
        $message = strtolower($message);

        if (str_contains($message, 'billing')) {
            return 'billing';
        }

        if (str_contains($message, 'credit')) {
            return 'credits';
        }

        return 'quota'; // Default
    }
}
