<?php

namespace JTD\LaravelAI\Drivers\OpenAI\Support;

use JTD\LaravelAI\Exceptions\OpenAI\OpenAIException;
use JTD\LaravelAI\Exceptions\OpenAI\OpenAIInvalidCredentialsException;
use JTD\LaravelAI\Exceptions\OpenAI\OpenAIQuotaExceededException;
use JTD\LaravelAI\Exceptions\OpenAI\OpenAIRateLimitException;
use JTD\LaravelAI\Exceptions\OpenAI\OpenAIServerException;

/**
 * OpenAI Error Mapping and Exception Handling
 *
 * Maps OpenAI API errors to appropriate exception types
 * and provides error enhancement utilities.
 */
class ErrorMapper
{
    /**
     * Error type to exception class mapping.
     */
    protected static array $exceptionMap = [
        'invalid_api_key' => OpenAIInvalidCredentialsException::class,
        'invalid_organization' => OpenAIInvalidCredentialsException::class,
        'invalid_project' => OpenAIInvalidCredentialsException::class,
        'authentication_error' => OpenAIInvalidCredentialsException::class,
        'permission_error' => OpenAIInvalidCredentialsException::class,
        'rate_limit_exceeded' => OpenAIRateLimitException::class,
        'quota_exceeded' => OpenAIQuotaExceededException::class,
        'insufficient_quota' => OpenAIQuotaExceededException::class,
        'billing_not_active' => OpenAIQuotaExceededException::class,
        'server_error' => OpenAIServerException::class,
        'service_unavailable' => OpenAIServerException::class,
        'timeout' => OpenAIServerException::class,
        'bad_gateway' => OpenAIServerException::class,
    ];

    /**
     * Retryable error types.
     */
    protected static array $retryableTypes = [
        'server_error',
        'service_unavailable',
        'timeout',
        'connection_error',
        'rate_limit_exceeded',
    ];

    /**
     * Map exception to appropriate OpenAI exception type.
     */
    public static function mapException(\Exception $exception): \Exception
    {
        $errorInfo = static::extractErrorInfo($exception);
        $errorType = $errorInfo['type'];

        // Map to specific exception type
        if (isset(static::$exceptionMap[$errorType])) {
            $exceptionClass = static::$exceptionMap[$errorType];

            return static::createSpecificException(
                $exceptionClass,
                $errorType,
                $errorInfo,
                $exception
            );
        }

        // Default to generic OpenAI exception
        return new OpenAIException(
            static::enhanceErrorMessage($errorInfo['message'], $errorType),
            $errorType,
            null, // openaiErrorCode
            null, // requestId
            $errorInfo,
            static::isRetryableError($exception),
            $exception->getCode(),
            $exception
        );
    }

    /**
     * Create specific exception instance with proper constructor parameters.
     */
    protected static function createSpecificException(
        string $exceptionClass,
        string $errorType,
        array $errorInfo,
        \Exception $originalException
    ): \Exception {
        $message = static::enhanceErrorMessage($errorInfo['message'], $errorType);

        // Handle different exception constructor signatures
        switch ($exceptionClass) {
            case OpenAIInvalidCredentialsException::class:
                return new OpenAIInvalidCredentialsException(
                    $message,
                    null, // account
                    null, // requestId
                    $errorType, // openaiErrorType
                    null, // organizationId
                    null, // projectId
                    $errorInfo, // details
                    $originalException->getCode(),
                    $originalException
                );

            case OpenAIRateLimitException::class:
                return new OpenAIRateLimitException(
                    $message,
                    null, // rateLimit
                    null, // remaining
                    static::extractRateLimitDelay($originalException), // resetTime
                    null, // limitType
                    null, // requestId
                    null, // rateLimitType
                    null, // organizationId
                    $originalException->getCode(),
                    $originalException
                );

            case OpenAIQuotaExceededException::class:
                return new OpenAIQuotaExceededException(
                    $message,
                    null, // currentUsage
                    null, // usageLimit
                    null, // quotaType
                    null, // billingPeriod
                    null, // organizationId
                    null, // requestId
                    $errorInfo, // details
                    $originalException->getCode(),
                    $originalException
                );

            case OpenAIServerException::class:
                return new OpenAIServerException(
                    $message,
                    $errorType, // openaiErrorType
                    null, // openaiErrorCode
                    null, // requestId
                    $errorInfo, // details
                    static::isRetryableError($originalException), // retryable
                    $originalException->getCode(),
                    $originalException
                );

            default:
                // Fallback to generic OpenAI exception
                return new OpenAIException(
                    $message,
                    $errorType,
                    null, // openaiErrorCode
                    null, // requestId
                    $errorInfo,
                    static::isRetryableError($originalException),
                    $originalException->getCode(),
                    $originalException
                );
        }
    }

    /**
     * Extract error information from various exception types.
     */
    public static function extractErrorInfo(\Exception $exception): array
    {
        $errorInfo = [
            'message' => $exception->getMessage(),
            'type' => null,
            'code' => null,
            'request_id' => null,
            'details' => [],
            'headers' => [],
            'organization' => null,
            'project' => null,
            'current_usage' => null,
            'usage_limit' => null,
            'quota_type' => null,
            'billing_period' => null,
        ];

        // Handle OpenAI SDK exceptions
        if (method_exists($exception, 'getErrorType')) {
            $errorInfo['type'] = $exception->getErrorType();
        }

        if (method_exists($exception, 'getErrorCode')) {
            $errorInfo['code'] = $exception->getErrorCode();
        }

        if (method_exists($exception, 'getRequestId')) {
            $errorInfo['request_id'] = $exception->getRequestId();
        }

        // Handle HTTP client exceptions
        if (method_exists($exception, 'getResponse')) {
            $response = $exception->getResponse();
            if ($response) {
                $errorInfo['headers'] = $response->getHeaders();
                static::parseErrorResponse($response, $errorInfo);
            }
        }

        // Parse error message for additional context
        static::parseErrorMessage($errorInfo);

        return $errorInfo;
    }

    /**
     * Parse error response body for additional information.
     */
    protected static function parseErrorResponse($response, array &$errorInfo): void
    {
        try {
            $body = $response->getBody()->getContents();
            $data = json_decode($body, true);

            if (isset($data['error'])) {
                $error = $data['error'];

                $errorInfo['type'] = $error['type'] ?? $errorInfo['type'];
                $errorInfo['code'] = $error['code'] ?? $errorInfo['code'];
                $errorInfo['message'] = $error['message'] ?? $errorInfo['message'];

                // Extract additional details
                if (isset($error['param'])) {
                    $errorInfo['details']['param'] = $error['param'];
                }
            }
        } catch (\Exception $e) {
            // Ignore parsing errors
        }
    }

    /**
     * Parse error message for additional context.
     */
    protected static function parseErrorMessage(array &$errorInfo): void
    {
        $message = strtolower($errorInfo['message']);

        // Detect error types from message content
        if (str_contains($message, 'api key') || str_contains($message, 'authentication')) {
            $errorInfo['type'] = $errorInfo['type'] ?: 'invalid_api_key';
        } elseif (str_contains($message, 'rate limit') || str_contains($message, 'too many requests')) {
            $errorInfo['type'] = $errorInfo['type'] ?: 'rate_limit_exceeded';
        } elseif (str_contains($message, 'quota') || str_contains($message, 'billing')) {
            $errorInfo['type'] = $errorInfo['type'] ?: 'quota_exceeded';
        } elseif (str_contains($message, 'timeout') || str_contains($message, 'timed out')) {
            $errorInfo['type'] = $errorInfo['type'] ?: 'timeout';
        } elseif (str_contains($message, 'server error') || str_contains($message, '500')) {
            $errorInfo['type'] = $errorInfo['type'] ?: 'server_error';
        } elseif (str_contains($message, 'service unavailable') || str_contains($message, '503')) {
            $errorInfo['type'] = $errorInfo['type'] ?: 'service_unavailable';
        }

        // Extract quota information
        if (preg_match('/current usage: (\d+)/', $message, $matches)) {
            $errorInfo['current_usage'] = (int) $matches[1];
        }

        if (preg_match('/limit: (\d+)/', $message, $matches)) {
            $errorInfo['usage_limit'] = (int) $matches[1];
        }

        // Extract organization/project info
        if (preg_match('/organization: ([a-zA-Z0-9-]+)/', $message, $matches)) {
            $errorInfo['organization'] = $matches[1];
        }

        if (preg_match('/project: ([a-zA-Z0-9-]+)/', $message, $matches)) {
            $errorInfo['project'] = $matches[1];
        }
    }

    /**
     * Enhance error message with additional context.
     */
    public static function enhanceErrorMessage(string $message, ?string $errorType): string
    {
        $enhancements = [
            'invalid_api_key' => 'Invalid OpenAI API key. Please check your API key configuration.',
            'authentication_error' => 'Authentication failed. Please verify your OpenAI credentials.',
            'rate_limit_exceeded' => 'OpenAI API rate limit exceeded. Please wait before making more requests.',
            'quota_exceeded' => 'OpenAI API quota exceeded. Please check your billing and usage limits.',
            'insufficient_quota' => 'Insufficient OpenAI quota. Please add credits to your account.',
            'billing_not_active' => 'OpenAI billing is not active. Please set up billing in your OpenAI account.',
            'server_error' => 'OpenAI server error. Please try again later.',
            'service_unavailable' => 'OpenAI service is temporarily unavailable. Please try again later.',
            'timeout' => 'Request to OpenAI timed out. Please try again or increase timeout settings.',
        ];

        if (isset($enhancements[$errorType])) {
            return $enhancements[$errorType] . ' ' . $message;
        }

        return $message;
    }

    /**
     * Check if an error is retryable.
     */
    public static function isRetryableError(\Exception $exception): bool
    {
        $errorInfo = static::extractErrorInfo($exception);

        return in_array($errorInfo['type'], static::$retryableTypes);
    }

    /**
     * Get HTTP status code from exception.
     */
    public static function getHttpStatusCode(\Exception $exception): int
    {
        if (method_exists($exception, 'getResponse')) {
            $response = $exception->getResponse();
            if ($response && method_exists($response, 'getStatusCode')) {
                return $response->getStatusCode();
            }
        }

        if (method_exists($exception, 'getCode')) {
            $code = $exception->getCode();
            if ($code >= 100 && $code < 600) {
                return $code;
            }
        }

        return 500; // Default to server error
    }

    /**
     * Check if error is a rate limit error.
     */
    public static function isRateLimitError(\Exception $exception): bool
    {
        $errorInfo = static::extractErrorInfo($exception);

        return $errorInfo['type'] === 'rate_limit_exceeded';
    }

    /**
     * Extract rate limit delay from headers.
     */
    public static function extractRateLimitDelay(\Exception $exception): int
    {
        $errorInfo = static::extractErrorInfo($exception);

        // Check for Retry-After header
        if (isset($errorInfo['headers']['Retry-After'])) {
            $retryAfter = $errorInfo['headers']['Retry-After'];
            if (is_array($retryAfter)) {
                $retryAfter = $retryAfter[0];
            }

            return (int) $retryAfter * 1000; // Convert to milliseconds
        }

        // Check for X-RateLimit-Reset header
        if (isset($errorInfo['headers']['X-RateLimit-Reset'])) {
            $resetTime = $errorInfo['headers']['X-RateLimit-Reset'];
            if (is_array($resetTime)) {
                $resetTime = $resetTime[0];
            }
            $delay = (int) $resetTime - time();

            return max($delay * 1000, 1000); // At least 1 second
        }

        // Default delay for rate limits
        return 60000; // 60 seconds
    }

    /**
     * Get retry configuration for error type.
     */
    public static function getRetryConfig(string $errorType): array
    {
        $configs = [
            'rate_limit_exceeded' => [
                'max_attempts' => 5,
                'base_delay' => 60000, // 60 seconds
                'max_delay' => 300000, // 5 minutes
                'use_exponential_backoff' => false,
            ],
            'server_error' => [
                'max_attempts' => 3,
                'base_delay' => 1000, // 1 second
                'max_delay' => 30000, // 30 seconds
                'use_exponential_backoff' => true,
            ],
            'service_unavailable' => [
                'max_attempts' => 3,
                'base_delay' => 5000, // 5 seconds
                'max_delay' => 60000, // 60 seconds
                'use_exponential_backoff' => true,
            ],
            'timeout' => [
                'max_attempts' => 2,
                'base_delay' => 2000, // 2 seconds
                'max_delay' => 10000, // 10 seconds
                'use_exponential_backoff' => false,
            ],
        ];

        return $configs[$errorType] ?? [
            'max_attempts' => 1,
            'base_delay' => 1000,
            'max_delay' => 5000,
            'use_exponential_backoff' => false,
        ];
    }
}
