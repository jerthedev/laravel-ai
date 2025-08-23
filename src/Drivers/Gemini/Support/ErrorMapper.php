<?php

namespace JTD\LaravelAI\Drivers\Gemini\Support;

use JTD\LaravelAI\Exceptions\Gemini\GeminiException;
use JTD\LaravelAI\Exceptions\Gemini\GeminiInvalidCredentialsException;
use JTD\LaravelAI\Exceptions\Gemini\GeminiRateLimitException;
use JTD\LaravelAI\Exceptions\Gemini\GeminiSafetyException;
use JTD\LaravelAI\Exceptions\Gemini\GeminiServerException;

/**
 * Gemini Error Mapping and Exception Handling
 *
 * Maps Gemini API errors to appropriate exception types
 * and provides error enhancement utilities.
 */
class ErrorMapper
{
    /**
     * Error type to exception class mapping.
     */
    protected static array $exceptionMap = [
        'invalid_api_key' => GeminiInvalidCredentialsException::class,
        'authentication_error' => GeminiInvalidCredentialsException::class,
        'permission_denied' => GeminiInvalidCredentialsException::class,
        'invalid_credentials' => GeminiInvalidCredentialsException::class,
        'rate_limit_exceeded' => GeminiRateLimitException::class,
        'quota_exceeded' => GeminiRateLimitException::class,
        'safety_violation' => GeminiSafetyException::class,
        'content_blocked' => GeminiSafetyException::class,
        'safety_filter' => GeminiSafetyException::class,
        'server_error' => GeminiServerException::class,
        'service_unavailable' => GeminiServerException::class,
        'timeout' => GeminiServerException::class,
        'bad_gateway' => GeminiServerException::class,
        'gateway_timeout' => GeminiServerException::class,
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
        'bad_gateway',
        'gateway_timeout',
    ];

    /**
     * Map exception to appropriate Gemini exception type.
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

        // Default to generic Gemini exception
        return new GeminiException(
            static::enhanceErrorMessage($errorInfo['message'], $errorType),
            $errorType,
            $errorInfo['code'],
            $errorInfo['request_id'],
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
            case GeminiInvalidCredentialsException::class:
                return new GeminiInvalidCredentialsException(
                    $message,
                    $errorInfo['api_key'] ?? null,
                    $errorInfo['request_id'] ?? null,
                    $errorType,
                    $errorInfo,
                    $originalException->getCode(),
                    $originalException
                );

            case GeminiRateLimitException::class:
                return new GeminiRateLimitException(
                    $message,
                    $errorInfo['rate_limit'] ?? null,
                    $errorInfo['remaining'] ?? null,
                    static::extractRateLimitDelay($originalException),
                    $errorInfo['limit_type'] ?? null,
                    $errorInfo['request_id'] ?? null,
                    $errorInfo,
                    $originalException->getCode(),
                    $originalException
                );

            case GeminiSafetyException::class:
                return new GeminiSafetyException(
                    $message,
                    $errorInfo['safety_ratings'] ?? [],
                    $errorInfo['blocked_reason'] ?? null,
                    $errorInfo['safety_category'] ?? null,
                    $errorInfo['request_id'] ?? null,
                    $errorInfo,
                    $originalException->getCode(),
                    $originalException
                );

            case GeminiServerException::class:
                return new GeminiServerException(
                    $message,
                    $errorType,
                    $errorInfo['code'],
                    $errorInfo['request_id'] ?? null,
                    $errorInfo,
                    static::isRetryableError($originalException),
                    $originalException->getCode(),
                    $originalException
                );

            default:
                // Fallback to generic Gemini exception
                return new GeminiException(
                    $message,
                    $errorType,
                    $errorInfo['code'],
                    $errorInfo['request_id'] ?? null,
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
            'api_key' => null,
            'safety_ratings' => [],
            'blocked_reason' => null,
            'safety_category' => null,
            'rate_limit' => null,
            'remaining' => null,
            'limit_type' => null,
        ];

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
            // Handle different response types
            if (method_exists($response, 'getBody')) {
                $body = $response->getBody()->getContents();
            } elseif (method_exists($response, 'json')) {
                $data = $response->json();
            } else {
                return;
            }

            if (isset($body)) {
                $data = json_decode($body, true);
            }

            if (isset($data['error'])) {
                $error = $data['error'];

                $errorInfo['type'] = static::mapHttpStatusToErrorType($response->status());
                $errorInfo['code'] = $error['code'] ?? $response->status();
                $errorInfo['message'] = $error['message'] ?? $errorInfo['message'];

                // Extract Gemini-specific details
                if (isset($error['details'])) {
                    $errorInfo['details'] = $error['details'];
                }

                // Extract safety information if present
                if (isset($error['safetyRatings'])) {
                    $errorInfo['safety_ratings'] = $error['safetyRatings'];
                    $errorInfo['type'] = 'safety_violation';
                }
            }
        } catch (\Exception $e) {
            // Ignore parsing errors
        }
    }

    /**
     * Map HTTP status code to error type.
     */
    protected static function mapHttpStatusToErrorType(int $statusCode): string
    {
        return match ($statusCode) {
            400 => 'invalid_request',
            401, 403 => 'invalid_credentials',
            404 => 'not_found',
            429 => 'rate_limit_exceeded',
            500 => 'server_error',
            502 => 'bad_gateway',
            503 => 'service_unavailable',
            504 => 'gateway_timeout',
            default => 'unknown_error',
        };
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
        } elseif (str_contains($message, 'safety') || str_contains($message, 'blocked')) {
            $errorInfo['type'] = $errorInfo['type'] ?: 'safety_violation';
        } elseif (str_contains($message, 'timeout') || str_contains($message, 'timed out')) {
            $errorInfo['type'] = $errorInfo['type'] ?: 'timeout';
        } elseif (str_contains($message, 'server error') || str_contains($message, '500')) {
            $errorInfo['type'] = $errorInfo['type'] ?: 'server_error';
        } elseif (str_contains($message, 'service unavailable') || str_contains($message, '503')) {
            $errorInfo['type'] = $errorInfo['type'] ?: 'service_unavailable';
        }

        // Extract rate limit information
        if (preg_match('/requests per minute: (\d+)/', $message, $matches)) {
            $errorInfo['rate_limit'] = (int) $matches[1];
        }

        if (preg_match('/remaining: (\d+)/', $message, $matches)) {
            $errorInfo['remaining'] = (int) $matches[1];
        }
    }

    /**
     * Enhance error message with additional context.
     */
    public static function enhanceErrorMessage(string $message, ?string $errorType): string
    {
        $enhancements = [
            'invalid_api_key' => 'Invalid Gemini API key. Please check your API key configuration.',
            'authentication_error' => 'Authentication failed. Please verify your Gemini credentials.',
            'permission_denied' => 'Permission denied. Please check your Gemini API access.',
            'rate_limit_exceeded' => 'Gemini API rate limit exceeded. Please wait before making more requests.',
            'quota_exceeded' => 'Gemini API quota exceeded. Please check your usage limits.',
            'safety_violation' => 'Content blocked by Gemini safety filters. Please modify your request.',
            'content_blocked' => 'Content was blocked due to safety policies. Please review your input.',
            'server_error' => 'Gemini server error. Please try again later.',
            'service_unavailable' => 'Gemini service is temporarily unavailable. Please try again later.',
            'timeout' => 'Request to Gemini timed out. Please try again or increase timeout settings.',
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
