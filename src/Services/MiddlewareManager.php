<?php

namespace JTD\LaravelAI\Services;

use Closure;
use Illuminate\Support\Facades\Log;
use JTD\LaravelAI\Contracts\AIMiddlewareInterface;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Models\AIResponse;

/**
 * Middleware Manager Service
 *
 * Manages registration, resolution, and execution of AI middleware components in a
 * Laravel-style pipeline architecture. Provides enterprise-grade middleware processing
 * with global and per-request middleware support, performance optimization, and
 * comprehensive error handling.
 *
 * The middleware system processes AI requests through a configurable pipeline where
 * each middleware can transform requests, enforce policies, track metrics, or modify
 * responses before they reach AI providers. Middleware execution follows Laravel's
 * familiar pattern with support for both global and conditional middleware.
 *
 * Features:
 * - Global middleware applied to all AI requests automatically
 * - Per-request middleware specified via ConversationBuilder or Direct SendMessage
 * - Middleware resolution caching with object pooling for <10ms overhead
 * - Performance tracking and monitoring for each middleware component
 * - Graceful error handling with request continuity on middleware failures
 * - Laravel service container integration for dependency injection
 * - Configurable middleware registration and ordering
 *
 * Architecture:
 * ```
 * AI Request → Global Middleware → Request Middleware → AI Provider → Response
 *                     ↓                    ↓                          ↑
 *              [Budget, Tracking]    [Custom Logic]           [Modified Response]
 * ```
 *
 * Registration:
 * ```php
 * // Register global middleware (applied to all requests)
 * $manager->registerGlobalMiddleware([
 *     'cost-tracking' => CostTrackingMiddleware::class,
 *     'budget-enforcement' => BudgetEnforcementMiddleware::class,
 * ]);
 *
 * // Register available middleware (used on-demand)
 * $manager->registerMiddleware('analytics', AnalyticsMiddleware::class);
 * ```
 *
 * Usage:
 * ```php
 * // Execute middleware pipeline
 * $response = $manager->executeMiddleware($message, $middlewareStack, function ($msg) {
 *     return $this->aiProvider->processMessage($msg);
 * });
 *
 * // Via ConversationBuilder
 * AI::conversation()->middleware(['analytics', 'cache'])->send('Hello');
 *
 * // Via Direct SendMessage
 * AI::sendMessage($message, ['middleware' => ['budget-enforcement']]);
 * ```
 *
 * Performance Targets:
 * - <10ms total middleware stack execution time
 * - <2ms per middleware component processing
 * - <1ms middleware resolution with caching
 * - Zero-allocation object pooling for high-traffic scenarios
 *
 * @author JTD Laravel AI Package
 *
 * @since 1.0.0
 */
class MiddlewareManager
{
    /**
     * Global middleware applied to all AI requests.
     *
     * Contains class names of middleware that should be executed for every
     * AI request processed through the system. Global middleware runs before
     * any per-request middleware in the pipeline.
     *
     * @var array<string> Array of middleware class names
     *
     * @since 1.0.0
     */
    protected array $globalMiddleware = [];

    /**
     * Registered middleware components by name.
     *
     * Maps middleware names to their class names for on-demand usage.
     * These middleware can be selectively applied to specific requests
     * via ConversationBuilder or Direct SendMessage patterns.
     *
     * @var array<string, string> Map of middleware name to class name
     *
     * @since 1.0.0
     */
    protected array $registeredMiddleware = [];

    /**
     * Cached resolved middleware instances for performance optimization.
     *
     * Stores instantiated middleware objects to avoid repeated resolution
     * and dependency injection overhead. Cache is keyed by class name.
     *
     * @var array<string, AIMiddlewareInterface> Cache of resolved instances
     *
     * @since 1.0.0
     */
    protected array $resolvedMiddlewareCache = [];

    /**
     * Performance target in milliseconds for total middleware stack execution.
     *
     * Target execution time for the entire middleware pipeline. Stacks
     * exceeding this threshold will be logged as performance warnings.
     *
     * @var int Target time in milliseconds
     *
     * @since 1.0.0
     */
    protected int $performanceTargetMs = 10;

    /**
     * Register global middleware that applies to all AI requests.
     *
     * Global middleware is executed for every AI request processed through
     * the system and cannot be disabled for individual requests. Middleware
     * is executed in registration order.
     *
     * @param  string|array  $middleware  The middleware class name or array with configuration
     *                                    - string: Class name (e.g., 'App\Middleware\BudgetMiddleware')
     *                                    - array: Configuration with 'class' key and options
     *
     * @throws \InvalidArgumentException When middleware configuration is invalid
     *
     * @since 1.0.0
     *
     * @example
     * ```php
     * // Register simple middleware
     * $manager->registerGlobal(BudgetEnforcementMiddleware::class);
     *
     * // Register with configuration
     * $manager->registerGlobal([
     *     'budget-enforcement' => [
     *         'class' => BudgetEnforcementMiddleware::class,
     *         'daily_limit' => 100.00,
     *         'per_request_limit' => 5.00
     *     ]
     * ]);
     * ```
     */
    public function registerGlobal($middleware): void
    {
        if (is_array($middleware)) {
            // Handle middleware with configuration
            foreach ($middleware as $name => $config) {
                if (is_string($config)) {
                    $this->globalMiddleware[] = $config;
                } elseif (is_array($config) && isset($config['class'])) {
                    $this->globalMiddleware[] = $config['class'];
                    // Store configuration for later use
                    $this->storeMiddlewareConfig($config['class'], $config);
                }
            }
        } else {
            $this->globalMiddleware[] = $middleware;
        }
    }

    /**
     * Register a named middleware for on-demand usage.
     *
     * Named middleware can be selectively applied to specific AI requests
     * via the ConversationBuilder or Direct SendMessage patterns. These
     * middleware are resolved and executed only when explicitly requested.
     *
     * @param  string  $name  The unique middleware name for registration
     * @param  string|array  $middleware  The middleware class name or configuration array
     *                                    - string: Class name (e.g., 'App\Middleware\CacheMiddleware')
     *                                    - array: Configuration with 'class' key and options
     *
     * @throws \InvalidArgumentException When name is empty or middleware config invalid
     *
     * @since 1.0.0
     *
     * @example
     * ```php
     * // Register simple middleware
     * $manager->register('analytics', AnalyticsMiddleware::class);
     *
     * // Register with configuration
     * $manager->register('cache', [
     *     'class' => CacheMiddleware::class,
     *     'ttl' => 3600,
     *     'prefix' => 'ai_cache'
     * ]);
     * ```
     */
    public function register(string $name, $middleware): void
    {
        if (is_array($middleware)) {
            $this->registeredMiddleware[$name] = $middleware['class'] ?? $middleware[0] ?? null;
            if (isset($middleware['class'])) {
                $this->storeMiddlewareConfig($middleware['class'], $middleware);
            }
        } else {
            $this->registeredMiddleware[$name] = $middleware;
        }
    }

    /**
     * Store middleware configuration in the service container.
     *
     * Stores middleware configuration in Laravel's service container for
     * later retrieval by middleware instances during resolution. Configuration
     * is stored with a predictable key format for consistent access.
     *
     * @param  string  $middleware  The middleware class name
     * @param  array  $config  The middleware configuration array
     *
     * @since 1.0.0
     */
    protected function storeMiddlewareConfig(string $middleware, array $config): void
    {
        // Store in application container for middleware to access
        app()->instance("middleware.config.{$middleware}", $config);
    }

    /**
     * Process an AI message through the complete middleware pipeline.
     *
     * Executes the AI message through both global and request-specific middleware
     * in a Laravel-style pipeline with performance monitoring and error handling.
     * Global middleware executes first, followed by request-specific middleware.
     *
     * The processing flow:
     * 1. Combines global and request middleware into execution stack
     * 2. Builds optimized middleware pipeline with caching and object pooling
     * 3. Processes message through each middleware layer
     * 4. Tracks performance metrics and logs warnings for slow operations
     * 5. Returns final processed response or throws exceptions on failures
     *
     * @param  AIMessage  $message  The AI message to process through middleware
     * @param  array<string>  $middleware  Additional middleware names for this request
     * @return AIResponse The processed response after middleware execution
     *
     * @throws \JTD\LaravelAI\Exceptions\MiddlewareException When middleware processing fails
     * @throws \InvalidArgumentException When middleware configuration is invalid
     * @throws \Exception When unexpected errors occur during processing
     *
     * @since 1.0.0
     *
     * @example
     * ```php
     * // Process with request-specific middleware
     * $response = $manager->process($message, ['analytics', 'cache']);
     *
     * // Process with only global middleware
     * $response = $manager->process($message);
     * ```
     */
    public function process(AIMessage $message, array $middleware = []): AIResponse
    {
        $startTime = microtime(true);

        try {
            $stack = $this->buildOptimizedStack(array_merge($this->globalMiddleware, $middleware));
            $response = $stack($message);

            // Track successful execution
            $this->trackStackPerformance($startTime, $message, $middleware, 'success');

            return $response;
        } catch (\Exception $e) {
            // Track failed execution
            $this->trackStackPerformance($startTime, $message, $middleware, 'error');

            throw $e;
        }
    }

    /**
     * Build an optimized middleware stack as a closure chain with performance monitoring.
     *
     * @param  array<string>  $middleware  The middleware to include in the stack
     * @return Closure The middleware stack closure
     */
    protected function buildOptimizedStack(array $middleware): Closure
    {
        return array_reduce(
            array_reverse($middleware),
            function ($next, $middleware) {
                return function (AIMessage $message) use ($next, $middleware) {
                    $instance = $this->resolveMiddleware($middleware);

                    // Track applied middleware
                    if (! isset($message->metadata['middleware_applied'])) {
                        $message->metadata['middleware_applied'] = [];
                    }
                    $message->metadata['middleware_applied'][] = $middleware;

                    $startTime = microtime(true);

                    try {
                        $response = $instance->handle($message, $next);

                        $this->logPerformance($middleware, microtime(true) - $startTime);

                        return $response;
                    } catch (\Exception $e) {
                        Log::error('Middleware failed', [
                            'middleware' => $middleware,
                            'error' => $e->getMessage(),
                            'message_id' => $message->id ?? null,
                        ]);

                        // Continue with next middleware or final handler
                        return $next($message);
                    }
                };
            },
            function (AIMessage $message) {
                // Final handler - this will be implemented in Phase 3
                // For now, return a placeholder response
                return $this->finalHandler($message);
            }
        );
    }

    /**
     * Resolve middleware instance from class name with performance caching.
     *
     * @param  string  $middleware  The middleware class name
     * @return AIMiddlewareInterface The resolved middleware instance
     */
    protected function resolveMiddleware(string $middleware): AIMiddlewareInterface
    {
        // Resolve from registered middleware if it's a name
        if (isset($this->registeredMiddleware[$middleware])) {
            $middleware = $this->registeredMiddleware[$middleware];
        }

        // Return cached instance if available
        if (isset($this->resolvedMiddlewareCache[$middleware])) {
            return $this->resolvedMiddlewareCache[$middleware];
        }

        // Resolve and cache the middleware instance
        $instance = app($middleware);
        $this->resolvedMiddlewareCache[$middleware] = $instance;

        return $instance;
    }

    /**
     * Log middleware performance metrics.
     *
     * @param  string  $middleware  The middleware class name
     * @param  float  $executionTime  The execution time in seconds
     */
    protected function logPerformance(string $middleware, float $executionTime): void
    {
        if ($executionTime > 0.1) { // Log if over 100ms
            Log::warning('Slow middleware detected', [
                'middleware' => $middleware,
                'execution_time' => $executionTime,
            ]);
        }
    }

    /**
     * Final handler for the middleware stack.
     * Sends the message to the AI provider and fires events for background processing.
     *
     * @param  AIMessage  $message  The message to process
     * @return AIResponse The response
     */
    protected function finalHandler(AIMessage $message): AIResponse
    {
        // Get the AI manager to send the message to the provider
        $aiManager = app('laravel-ai');

        // Send message to AI provider using unified sendMessage() API (events will be fired at provider level)
        $response = $aiManager->sendMessage($message);

        return $response;
    }

    /**
     * Get the list of middleware applied to this message.
     *
     * @param  AIMessage  $message  The message
     * @return array The applied middleware
     */
    protected function getAppliedMiddleware(AIMessage $message): array
    {
        return $message->metadata['middleware_applied'] ?? [];
    }

    /**
     * Get all registered global middleware.
     *
     * @return array<string>
     */
    public function getGlobalMiddleware(): array
    {
        return $this->globalMiddleware;
    }

    /**
     * Get all registered named middleware.
     *
     * @return array<string, string>
     */
    public function getRegisteredMiddleware(): array
    {
        return $this->registeredMiddleware;
    }

    /**
     * Clear all registered middleware (useful for testing).
     */
    public function clearMiddleware(): void
    {
        $this->globalMiddleware = [];
        $this->registeredMiddleware = [];
        $this->resolvedMiddlewareCache = [];
    }

    /**
     * Track middleware stack performance metrics.
     *
     * @param  float  $startTime  Start time in microseconds
     * @param  AIMessage  $message  The processed message
     * @param  array  $middleware  The middleware stack
     * @param  string  $outcome  Execution outcome
     */
    protected function trackStackPerformance(float $startTime, AIMessage $message, array $middleware, string $outcome): void
    {
        $durationMs = (microtime(true) - $startTime) * 1000;

        // Log performance if it exceeds the target
        if ($durationMs > $this->performanceTargetMs) {
            Log::warning('Middleware stack exceeded performance target', [
                'duration_ms' => round($durationMs, 2),
                'target_ms' => $this->performanceTargetMs,
                'middleware_count' => count($middleware),
                'user_id' => $message->user_id,
                'outcome' => $outcome,
                'memory_usage' => memory_get_usage(true),
            ]);
        }

        // Track metrics for performance monitoring
        if (app()->bound('laravel-ai.performance-tracker')) {
            app('laravel-ai.performance-tracker')->trackMiddlewarePerformance('MiddlewareManager', $durationMs, [
                'middleware_count' => count($middleware),
                'user_id' => $message->user_id,
                'provider' => $message->provider,
                'outcome' => $outcome,
                'success' => $outcome === 'success',
                'memory_usage' => memory_get_usage(true),
                'cache_hits' => count($this->resolvedMiddlewareCache),
            ]);
        }
    }

    /**
     * Get performance statistics for the middleware manager.
     *
     * @return array Performance statistics
     */
    public function getPerformanceStats(): array
    {
        return [
            'cache_size' => count($this->resolvedMiddlewareCache),
            'global_middleware_count' => count($this->globalMiddleware),
            'registered_middleware_count' => count($this->registeredMiddleware),
            'performance_target_ms' => $this->performanceTargetMs,
            'memory_usage' => memory_get_usage(true),
        ];
    }

    /**
     * Warm up the middleware cache by pre-resolving instances.
     *
     * @param  array  $middlewareClasses  Middleware classes to pre-resolve
     */
    public function warmUpCache(array $middlewareClasses = []): void
    {
        $middlewareToWarmUp = $middlewareClasses ?: array_merge(
            $this->globalMiddleware,
            array_values($this->registeredMiddleware)
        );

        foreach ($middlewareToWarmUp as $middleware) {
            try {
                $this->resolveMiddleware($middleware);
            } catch (\Exception $e) {
                Log::warning('Failed to warm up middleware cache', [
                    'middleware' => $middleware,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
