# Error Handling

## Overview

The JTD Laravel AI package implements a comprehensive error handling system that maps provider-specific errors to a unified exception hierarchy, provides retry logic, and ensures graceful degradation of service.

## Exception Hierarchy

### Base Exceptions

```php
<?php

namespace JTD\LaravelAI\Exceptions;

// Base exception for all AI-related errors
class AIException extends \Exception
{
    protected array $context = [];

    public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null, array $context = [])
    {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
    }

    public function getContext(): array
    {
        return $this->context;
    }
}

// Provider-specific base exception
abstract class ProviderException extends AIException
{
    abstract public function getProviderName(): string;
}

// Authentication and credential errors
class InvalidCredentialsException extends ProviderException
{
    public function getProviderName(): string
    {
        return 'generic';
    }
}

// Rate limiting errors
class RateLimitException extends ProviderException
{
    protected int $retryAfter = 60;

    public function getRetryAfter(): int
    {
        return $this->retryAfter;
    }

    public function setRetryAfter(int $seconds): self
    {
        $this->retryAfter = $seconds;
        return $this;
    }
}

// Server and service errors
class ServerException extends ProviderException
{
    public function getProviderName(): string
    {
        return 'generic';
    }
}

// Quota and billing errors
class QuotaExceededException extends ProviderException
{
    public function getProviderName(): string
    {
        return 'generic';
    }
}
```

### Provider-Specific Exceptions

#### OpenAI Exceptions

```php
<?php

namespace JTD\LaravelAI\Exceptions\OpenAI;

use JTD\LaravelAI\Exceptions\{
    ProviderException,
    InvalidCredentialsException,
    RateLimitException,
    ServerException,
    QuotaExceededException
};

class OpenAIException extends ProviderException
{
    public function getProviderName(): string
    {
        return 'openai';
    }
}

class OpenAIInvalidCredentialsException extends InvalidCredentialsException
{
    public function getProviderName(): string
    {
        return 'openai';
    }
}

class OpenAIRateLimitException extends RateLimitException
{
    public function getProviderName(): string
    {
        return 'openai';
    }

    public static function fromResponse(array $response): self
    {
        $exception = new self($response['error']['message'] ?? 'Rate limit exceeded');
        
        // Extract retry-after from headers or response
        if (isset($response['retry_after'])) {
            $exception->setRetryAfter($response['retry_after']);
        }
        
        return $exception;
    }
}

class OpenAIQuotaExceededException extends QuotaExceededException
{
    public function getProviderName(): string
    {
        return 'openai';
    }
}

class OpenAIServerException extends ServerException
{
    public function getProviderName(): string
    {
        return 'openai';
    }
}
```

#### Gemini Exceptions

```php
<?php

namespace JTD\LaravelAI\Exceptions\Gemini;

use JTD\LaravelAI\Exceptions\{ProviderException, InvalidCredentialsException};

class GeminiException extends ProviderException
{
    public function getProviderName(): string
    {
        return 'gemini';
    }
}

class GeminiInvalidCredentialsException extends InvalidCredentialsException
{
    public function getProviderName(): string
    {
        return 'gemini';
    }
}

class GeminiSafetyException extends GeminiException
{
    protected array $safetyRatings = [];

    public function setSafetyRatings(array $ratings): self
    {
        $this->safetyRatings = $ratings;
        return $this;
    }

    public function getSafetyRatings(): array
    {
        return $this->safetyRatings;
    }
}
```

## Error Mapping

### OpenAI Error Mapping

```php
<?php

namespace JTD\LaravelAI\Drivers\OpenAI\Support;

use JTD\LaravelAI\Exceptions\OpenAI\{
    OpenAIException,
    OpenAIInvalidCredentialsException,
    OpenAIRateLimitException,
    OpenAIQuotaExceededException,
    OpenAIServerException
};

class ErrorMapper
{
    public static function mapException(\Exception $e): \Exception
    {
        $message = $e->getMessage();
        $code = $e->getCode();

        // Rate limiting
        if (str_contains($message, 'rate_limit_exceeded') || 
            str_contains($message, 'Rate limit reached')) {
            return new OpenAIRateLimitException($message, $code, $e);
        }

        // Authentication errors
        if (str_contains($message, 'invalid_api_key') ||
            str_contains($message, 'Incorrect API key') ||
            str_contains($message, 'authentication')) {
            return new OpenAIInvalidCredentialsException($message, $code, $e);
        }

        // Quota and billing
        if (str_contains($message, 'insufficient_quota') ||
            str_contains($message, 'billing') ||
            str_contains($message, 'quota')) {
            return new OpenAIQuotaExceededException($message, $code, $e);
        }

        // Server errors
        if ($code >= 500 || str_contains($message, 'server_error')) {
            return new OpenAIServerException($message, $code, $e);
        }

        // Generic OpenAI error
        return new OpenAIException($message, $code, $e);
    }

    public static function extractRetryAfter(\Exception $e): ?int
    {
        $message = $e->getMessage();
        
        // Try to extract retry-after from message
        if (preg_match('/retry after (\d+) seconds?/i', $message, $matches)) {
            return (int) $matches[1];
        }
        
        if (preg_match('/try again in (\d+)s/i', $message, $matches)) {
            return (int) $matches[1];
        }
        
        return null;
    }
}
```

### Gemini Error Mapping

```php
<?php

namespace JTD\LaravelAI\Drivers\Gemini\Support;

use JTD\LaravelAI\Exceptions\Gemini\{
    GeminiException,
    GeminiInvalidCredentialsException,
    GeminiSafetyException
};

class ErrorMapper
{
    public static function mapException(\Exception $e): \Exception
    {
        $message = $e->getMessage();
        $code = $e->getCode();

        // Authentication errors
        if (str_contains($message, 'API_KEY_INVALID') ||
            str_contains($message, 'authentication')) {
            return new GeminiInvalidCredentialsException($message, $code, $e);
        }

        // Safety filtering
        if (str_contains($message, 'SAFETY') ||
            str_contains($message, 'blocked')) {
            $exception = new GeminiSafetyException($message, $code, $e);
            
            // Extract safety ratings if available
            if (method_exists($e, 'getResponse')) {
                $response = $e->getResponse();
                if (isset($response['safetyRatings'])) {
                    $exception->setSafetyRatings($response['safetyRatings']);
                }
            }
            
            return $exception;
        }

        // Generic Gemini error
        return new GeminiException($message, $code, $e);
    }
}
```

## Retry Logic

### Exponential Backoff Implementation

```php
<?php

namespace JTD\LaravelAI\Support;

class RetryHandler
{
    protected int $maxAttempts;
    protected int $initialDelay;
    protected int $maxDelay;
    protected float $multiplier;

    public function __construct(
        int $maxAttempts = 3,
        int $initialDelay = 1000, // milliseconds
        int $maxDelay = 30000,
        float $multiplier = 2.0
    ) {
        $this->maxAttempts = $maxAttempts;
        $this->initialDelay = $initialDelay;
        $this->maxDelay = $maxDelay;
        $this->multiplier = $multiplier;
    }

    public function execute(callable $callback)
    {
        $lastException = null;

        for ($attempt = 1; $attempt <= $this->maxAttempts; $attempt++) {
            try {
                return $callback();
            } catch (\Exception $e) {
                $lastException = $e;

                if ($attempt === $this->maxAttempts || !$this->shouldRetry($e)) {
                    throw $e;
                }

                $delay = $this->calculateDelay($attempt);
                
                \Log::warning('Retrying after error', [
                    'attempt' => $attempt,
                    'max_attempts' => $this->maxAttempts,
                    'delay_ms' => $delay,
                    'error' => $e->getMessage(),
                ]);

                usleep($delay * 1000); // Convert to microseconds
            }
        }

        throw $lastException;
    }

    protected function shouldRetry(\Exception $e): bool
    {
        // Don't retry authentication errors
        if ($e instanceof InvalidCredentialsException) {
            return false;
        }

        // Don't retry quota exceeded errors
        if ($e instanceof QuotaExceededException) {
            return false;
        }

        // Retry rate limits, server errors, and network issues
        return $e instanceof RateLimitException ||
               $e instanceof ServerException ||
               $this->isNetworkError($e);
    }

    protected function calculateDelay(int $attempt): int
    {
        $delay = $this->initialDelay * pow($this->multiplier, $attempt - 1);
        return min($delay, $this->maxDelay);
    }

    protected function isNetworkError(\Exception $e): bool
    {
        $message = strtolower($e->getMessage());
        
        return str_contains($message, 'connection') ||
               str_contains($message, 'timeout') ||
               str_contains($message, 'network') ||
               str_contains($message, 'dns');
    }
}
```

### Using Retry Logic in Drivers

```php
<?php

namespace JTD\LaravelAI\Drivers\OpenAI\Traits;

use JTD\LaravelAI\Support\RetryHandler;

trait HandlesRetries
{
    protected function executeWithRetry(callable $callback)
    {
        $retryHandler = new RetryHandler(
            maxAttempts: $this->config['retry_attempts'] ?? 3,
            initialDelay: $this->config['retry_delay'] ?? 1000,
            maxDelay: $this->config['max_retry_delay'] ?? 30000
        );

        return $retryHandler->execute($callback);
    }
}
```

## Error Handling Patterns

### Driver-Level Error Handling

```php
<?php

namespace JTD\LaravelAI\Drivers\OpenAI\Traits;

use JTD\LaravelAI\Drivers\OpenAI\Support\ErrorMapper;

trait HandlesErrors
{
    protected function handleApiError(\Exception $e): void
    {
        // Map to provider-specific exception
        $mappedException = ErrorMapper::mapException($e);
        
        // Log the error with context
        $this->logError($mappedException, [
            'provider' => $this->getName(),
            'original_error' => $e->getMessage(),
            'mapped_error' => $mappedException->getMessage(),
        ]);

        // Fire error event
        event(new \JTD\LaravelAI\Events\ErrorOccurred([
            'provider' => $this->getName(),
            'exception' => $mappedException,
            'context' => $this->getErrorContext(),
        ]));

        throw $mappedException;
    }

    protected function logError(\Exception $e, array $context = []): void
    {
        $level = $this->getLogLevel($e);
        
        \Log::log($level, 'AI Provider Error', array_merge([
            'provider' => $this->getName(),
            'exception' => get_class($e),
            'message' => $e->getMessage(),
            'code' => $e->getCode(),
        ], $context));
    }

    protected function getLogLevel(\Exception $e): string
    {
        if ($e instanceof InvalidCredentialsException) {
            return 'error';
        }

        if ($e instanceof RateLimitException) {
            return 'warning';
        }

        if ($e instanceof ServerException) {
            return 'error';
        }

        return 'info';
    }

    protected function getErrorContext(): array
    {
        return [
            'timestamp' => now()->toISOString(),
            'provider' => $this->getName(),
            'version' => $this->getVersion(),
        ];
    }
}
```

### Application-Level Error Handling

```php
<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use JTD\LaravelAI\Exceptions\{
    AIException,
    InvalidCredentialsException,
    RateLimitException,
    QuotaExceededException
};

class Handler extends ExceptionHandler
{
    public function register()
    {
        $this->reportable(function (AIException $e) {
            // Custom reporting for AI exceptions
            \Log::channel('ai-errors')->error('AI Exception', [
                'provider' => method_exists($e, 'getProviderName') ? $e->getProviderName() : 'unknown',
                'message' => $e->getMessage(),
                'context' => method_exists($e, 'getContext') ? $e->getContext() : [],
            ]);
        });

        $this->renderable(function (InvalidCredentialsException $e) {
            return response()->json([
                'error' => 'Invalid AI provider credentials',
                'message' => 'Please check your API configuration',
                'provider' => $e->getProviderName(),
            ], 401);
        });

        $this->renderable(function (RateLimitException $e) {
            return response()->json([
                'error' => 'Rate limit exceeded',
                'message' => 'Please try again later',
                'retry_after' => $e->getRetryAfter(),
                'provider' => $e->getProviderName(),
            ], 429);
        });

        $this->renderable(function (QuotaExceededException $e) {
            return response()->json([
                'error' => 'Quota exceeded',
                'message' => 'Please check your billing and usage limits',
                'provider' => $e->getProviderName(),
            ], 402);
        });
    }
}
```

## Graceful Degradation

### Fallback Strategies

```php
<?php

namespace App\Services;

use JTD\LaravelAI\Facades\AI;
use JTD\LaravelAI\Exceptions\{ProviderException, RateLimitException};

class AIService
{
    protected array $providers = ['openai', 'gemini', 'xai'];

    public function sendMessageWithFallback(string $message): string
    {
        foreach ($this->providers as $provider) {
            try {
                $response = AI::provider($provider)->sendMessage(
                    \JTD\LaravelAI\Models\AIMessage::user($message)
                );
                
                return $response->content;
                
            } catch (RateLimitException $e) {
                \Log::warning("Rate limit hit for {$provider}, trying next provider");
                continue;
                
            } catch (ProviderException $e) {
                \Log::error("Provider {$provider} failed: " . $e->getMessage());
                continue;
            }
        }

        throw new \Exception('All AI providers failed');
    }

    public function sendMessageWithRetryAndFallback(string $message, int $maxRetries = 3): string
    {
        $attempt = 0;
        
        while ($attempt < $maxRetries) {
            try {
                return $this->sendMessageWithFallback($message);
            } catch (\Exception $e) {
                $attempt++;
                
                if ($attempt >= $maxRetries) {
                    throw $e;
                }
                
                $delay = pow(2, $attempt) * 1000; // Exponential backoff
                usleep($delay * 1000);
            }
        }
    }
}
```

## Testing Error Scenarios

### Unit Tests for Error Handling

```php
<?php

namespace Tests\Unit\ErrorHandling;

use Tests\TestCase;
use JTD\LaravelAI\Drivers\OpenAI\Support\ErrorMapper;
use JTD\LaravelAI\Exceptions\OpenAI\{
    OpenAIRateLimitException,
    OpenAIInvalidCredentialsException
};

class ErrorMappingTest extends TestCase
{
    public function test_maps_rate_limit_error(): void
    {
        $originalException = new \Exception('Rate limit exceeded. Try again in 60 seconds.');
        
        $mappedException = ErrorMapper::mapException($originalException);
        
        $this->assertInstanceOf(OpenAIRateLimitException::class, $mappedException);
    }

    public function test_maps_authentication_error(): void
    {
        $originalException = new \Exception('Incorrect API key provided');
        
        $mappedException = ErrorMapper::mapException($originalException);
        
        $this->assertInstanceOf(OpenAIInvalidCredentialsException::class, $mappedException);
    }

    public function test_extracts_retry_after(): void
    {
        $exception = new \Exception('Rate limit exceeded. Try again in 120 seconds.');
        
        $retryAfter = ErrorMapper::extractRetryAfter($exception);
        
        $this->assertEquals(120, $retryAfter);
    }
}
```

### Integration Tests

```php
<?php

namespace Tests\Integration;

use Tests\TestCase;
use JTD\LaravelAI\Facades\AI;
use JTD\LaravelAI\Exceptions\OpenAI\OpenAIRateLimitException;

class ErrorHandlingIntegrationTest extends TestCase
{
    public function test_handles_rate_limit_gracefully(): void
    {
        // Configure mock to simulate rate limit
        config([
            'ai.default' => 'mock',
            'ai.providers.mock.mock_responses.default' => 'SIMULATE_RATE_LIMIT'
        ]);

        $this->expectException(OpenAIRateLimitException::class);

        AI::sendMessage(\JTD\LaravelAI\Models\AIMessage::user('Test'));
    }
}
```

## Best Practices

### Error Handling Guidelines
- **Map Provider Errors**: Always map provider-specific errors to package exceptions
- **Provide Context**: Include relevant context in exception messages
- **Log Appropriately**: Use appropriate log levels for different error types
- **Implement Retries**: Use exponential backoff for transient errors
- **Graceful Degradation**: Implement fallback strategies where appropriate

### Exception Design
- **Specific Exceptions**: Create specific exception types for different error scenarios
- **Consistent Hierarchy**: Maintain a consistent exception hierarchy
- **Rich Information**: Include additional information in exceptions (retry times, context)
- **Serializable**: Ensure exceptions can be serialized for logging and monitoring

## Related Documentation

- **[Driver Interface](03-Interface.md)**: Understanding error handling requirements
- **[OpenAI Driver](07-OpenAI-Driver.md)**: Example error handling implementation
- **[Driver Traits](10-Driver-Traits.md)**: Error handling traits
- **[Testing Strategy](12-Testing.md)**: Testing error scenarios
