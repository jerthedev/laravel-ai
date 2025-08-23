# Middleware Development Guide

## Overview

The Laravel AI package includes a powerful middleware system that allows you to intercept, modify, and control AI requests before they reach the provider. Middleware enables features like budget enforcement, request validation, caching, rate limiting, and custom business logic.

## Architecture

### Middleware Pipeline

```
Request → Global Middleware → Request Middleware → AI Provider → Response
    ↓           ↓                    ↓                ↓           ↓
User Input  Budget Check      Custom Logic      API Call    AI Response
            Rate Limiting     Authentication    Processing   Event Firing
            Validation        Transformation
```

### Key Components

1. **AIMiddlewareInterface** - Contract that all middleware must implement
2. **MiddlewareManager** - Manages middleware registration and execution
3. **Middleware Stack** - Laravel-style pipeline for request processing
4. **Configuration System** - Flexible middleware configuration

## Creating Custom Middleware

### Basic Middleware Structure

```php
<?php

namespace App\AI\Middleware;

use Closure;
use JTD\LaravelAI\Contracts\AIMiddlewareInterface;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Models\AIResponse;

class CustomMiddleware implements AIMiddlewareInterface
{
    /**
     * Handle the AI request.
     *
     * @param  AIMessage  $message  The message to process
     * @param  Closure  $next  The next middleware in the stack
     * @return AIResponse  The processed response
     */
    public function handle(AIMessage $message, Closure $next): AIResponse
    {
        // Pre-processing logic
        $this->beforeRequest($message);
        
        // Continue to next middleware/provider
        $response = $next($message);
        
        // Post-processing logic
        $this->afterResponse($message, $response);
        
        return $response;
    }
    
    protected function beforeRequest(AIMessage $message): void
    {
        // Modify message, validate, log, etc.
    }
    
    protected function afterResponse(AIMessage $message, AIResponse $response): void
    {
        // Process response, log, cache, etc.
    }
}
```

### Middleware Registration

#### Global Middleware

Applied to all AI requests:

```php
// In a service provider
use JTD\LaravelAI\Services\MiddlewareManager;

public function boot(): void
{
    $middlewareManager = app(MiddlewareManager::class);
    
    // Register global middleware
    $middlewareManager->registerGlobal([
        'budget_enforcement' => BudgetEnforcementMiddleware::class,
        'rate_limiting' => RateLimitingMiddleware::class,
        'audit_logging' => AuditLoggingMiddleware::class,
    ]);
}
```

#### Named Middleware

Register middleware that can be applied selectively:

```php
$middlewareManager->register('cache', CacheMiddleware::class);
$middlewareManager->register('transform', MessageTransformMiddleware::class);
$middlewareManager->register('validate', ValidationMiddleware::class);
```

#### Per-Request Middleware

Apply middleware to specific requests:

```php
$response = AI::conversation()
    ->middleware(['cache', 'transform'])
    ->message('Hello, world!')
    ->send();
```

## Built-in Middleware

### BudgetEnforcementMiddleware

Enforces spending limits before API calls:

```php
<?php

namespace JTD\LaravelAI\Middleware;

class BudgetEnforcementMiddleware implements AIMiddlewareInterface
{
    public function __construct(
        protected BudgetService $budgetService,
        protected PricingService $pricingService
    ) {}

    public function handle(AIMessage $message, Closure $next): AIResponse
    {
        // Estimate request cost
        $estimatedCost = $this->estimateRequestCost($message);
        
        // Check budget limits
        $this->budgetService->checkBudgetLimits(
            $message->user_id, 
            $estimatedCost
        );
        
        // Proceed if within budget
        return $next($message);
    }
}
```

**Configuration:**
```php
'middleware' => [
    'global' => [
        'budget_enforcement' => [
            'enabled' => env('AI_BUDGET_ENFORCEMENT_ENABLED', true),
            'daily_limit' => env('AI_DAILY_BUDGET_LIMIT', 10.00),
            'monthly_limit' => env('AI_MONTHLY_BUDGET_LIMIT', 100.00),
            'per_request_limit' => env('AI_PER_REQUEST_LIMIT', 1.00),
        ],
    ],
],
```

## Advanced Middleware Examples

### Rate Limiting Middleware

```php
<?php

namespace App\AI\Middleware;

use Illuminate\Support\Facades\RateLimiter;
use JTD\LaravelAI\Exceptions\RateLimitExceededException;

class RateLimitingMiddleware implements AIMiddlewareInterface
{
    public function handle(AIMessage $message, Closure $next): AIResponse
    {
        $key = "ai-requests:{$message->user_id}";
        
        if (RateLimiter::tooManyAttempts($key, 60)) {
            throw new RateLimitExceededException(
                'Too many AI requests. Please try again later.'
            );
        }
        
        RateLimiter::hit($key, 3600); // 1 hour window
        
        return $next($message);
    }
}
```

### Caching Middleware

```php
<?php

namespace App\AI\Middleware;

use Illuminate\Support\Facades\Cache;

class CacheMiddleware implements AIMiddlewareInterface
{
    public function handle(AIMessage $message, Closure $next): AIResponse
    {
        $cacheKey = $this->getCacheKey($message);
        
        // Check cache first
        if ($cached = Cache::get($cacheKey)) {
            return AIResponse::fromArray($cached);
        }
        
        // Get fresh response
        $response = $next($message);
        
        // Cache the response
        Cache::put($cacheKey, $response->toArray(), 3600);
        
        return $response;
    }
    
    protected function getCacheKey(AIMessage $message): string
    {
        return 'ai-response:' . md5($message->content . $message->provider . $message->model);
    }
}
```

### Content Filtering Middleware

```php
<?php

namespace App\AI\Middleware;

use JTD\LaravelAI\Exceptions\ContentFilteredException;

class ContentFilterMiddleware implements AIMiddlewareInterface
{
    protected array $bannedWords = ['spam', 'inappropriate'];
    
    public function handle(AIMessage $message, Closure $next): AIResponse
    {
        // Filter input
        $this->filterContent($message->content);
        
        $response = $next($message);
        
        // Filter output
        $this->filterContent($response->content);
        
        return $response;
    }
    
    protected function filterContent(string $content): void
    {
        foreach ($this->bannedWords as $word) {
            if (stripos($content, $word) !== false) {
                throw new ContentFilteredException(
                    "Content contains inappropriate material: {$word}"
                );
            }
        }
    }
}
```

### Authentication Middleware

```php
<?php

namespace App\AI\Middleware;

use JTD\LaravelAI\Exceptions\UnauthorizedException;

class AuthenticationMiddleware implements AIMiddlewareInterface
{
    public function handle(AIMessage $message, Closure $next): AIResponse
    {
        // Check if user is authenticated
        if (!$message->user_id || !$this->isValidUser($message->user_id)) {
            throw new UnauthorizedException('User must be authenticated for AI requests');
        }
        
        // Check user permissions
        if (!$this->hasAIPermission($message->user_id)) {
            throw new UnauthorizedException('User does not have AI access permission');
        }
        
        return $next($message);
    }
    
    protected function isValidUser(int $userId): bool
    {
        return User::where('id', $userId)->where('active', true)->exists();
    }
    
    protected function hasAIPermission(int $userId): bool
    {
        return User::find($userId)?->can('use-ai') ?? false;
    }
}
```

## Configuration

### Middleware Configuration

```php
// config/ai.php
'middleware' => [
    'enabled' => env('AI_MIDDLEWARE_ENABLED', true),
    
    'global' => [
        'budget_enforcement' => [
            'enabled' => env('AI_BUDGET_ENFORCEMENT_ENABLED', true),
            'class' => BudgetEnforcementMiddleware::class,
        ],
        'rate_limiting' => [
            'enabled' => env('AI_RATE_LIMITING_ENABLED', false),
            'class' => RateLimitingMiddleware::class,
        ],
    ],
    
    'available' => [
        'cache' => CacheMiddleware::class,
        'auth' => AuthenticationMiddleware::class,
        'filter' => ContentFilterMiddleware::class,
        'transform' => MessageTransformMiddleware::class,
    ],
    
    'performance' => [
        'log_slow_middleware' => env('AI_LOG_SLOW_MIDDLEWARE', true),
        'slow_threshold_ms' => env('AI_SLOW_MIDDLEWARE_THRESHOLD', 100),
        'max_execution_time' => env('AI_MIDDLEWARE_MAX_TIME', 30),
    ],
],
```

### Environment Variables

```bash
# Enable/disable middleware system
AI_MIDDLEWARE_ENABLED=true

# Global middleware controls
AI_BUDGET_ENFORCEMENT_ENABLED=true
AI_RATE_LIMITING_ENABLED=false

# Performance settings
AI_LOG_SLOW_MIDDLEWARE=true
AI_SLOW_MIDDLEWARE_THRESHOLD=100
AI_MIDDLEWARE_MAX_TIME=30

# Budget enforcement settings
AI_DAILY_BUDGET_LIMIT=10.00
AI_MONTHLY_BUDGET_LIMIT=100.00
AI_PER_REQUEST_LIMIT=1.00
```

## Testing Middleware

### Unit Testing

```php
<?php

namespace Tests\Unit\Middleware;

use Tests\TestCase;
use App\AI\Middleware\CustomMiddleware;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Models\AIResponse;

class CustomMiddlewareTest extends TestCase
{
    public function test_middleware_processes_request(): void
    {
        $middleware = new CustomMiddleware();
        $message = AIMessage::user('Test message');
        
        $next = function ($message) {
            return AIResponse::fromArray([
                'content' => 'Test response',
                'provider' => 'test',
                'model' => 'test-model',
            ]);
        };
        
        $response = $middleware->handle($message, $next);
        
        $this->assertInstanceOf(AIResponse::class, $response);
        $this->assertEquals('Test response', $response->content);
    }
    
    public function test_middleware_modifies_message(): void
    {
        $middleware = new MessageTransformMiddleware();
        $message = AIMessage::user('original message');
        
        $next = function ($message) {
            // Assert message was modified
            $this->assertStringContains('transformed', $message->content);
            return AIResponse::fromArray(['content' => 'response']);
        };
        
        $middleware->handle($message, $next);
    }
}
```

### Integration Testing

```php
<?php

namespace Tests\Integration;

use Tests\TestCase;
use JTD\LaravelAI\Services\MiddlewareManager;

class MiddlewareIntegrationTest extends TestCase
{
    public function test_middleware_stack_execution(): void
    {
        $manager = app(MiddlewareManager::class);
        $manager->register('test', TestMiddleware::class);
        
        $message = AIMessage::user('Test message');
        $response = $manager->process($message, ['test']);
        
        $this->assertInstanceOf(AIResponse::class, $response);
        $this->assertArrayHasKey('middleware_applied', $message->metadata);
    }
}
```

## Best Practices

### Performance

1. **Keep middleware lightweight** - Heavy processing should be done in event listeners
2. **Use early returns** - Exit middleware early when conditions aren't met
3. **Monitor execution times** - Use the built-in performance tracking
4. **Cache expensive operations** - Don't repeat expensive calculations

### Error Handling

1. **Use specific exceptions** - Create custom exceptions for different error types
2. **Log errors appropriately** - Use different log levels for different scenarios
3. **Provide helpful messages** - Make error messages user-friendly
4. **Handle failures gracefully** - Don't break the entire request for non-critical middleware

### Security

1. **Validate all inputs** - Never trust user input
2. **Sanitize content** - Clean content before and after processing
3. **Check permissions** - Verify user authorization
4. **Rate limit requests** - Prevent abuse and DoS attacks

### Configuration

1. **Make middleware configurable** - Use environment variables and config files
2. **Provide sensible defaults** - Middleware should work out of the box
3. **Document configuration options** - Make it easy for users to customize
4. **Support enabling/disabling** - Allow middleware to be turned off

The middleware system provides a powerful way to extend and customize AI request processing while maintaining clean separation of concerns and high performance.
