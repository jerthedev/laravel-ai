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
 * Manages the registration and execution of AI middleware in a Laravel-style pipeline.
 * Provides global and per-request middleware support with performance tracking and error handling.
 */
class MiddlewareManager
{
    /**
     * Global middleware applied to all requests.
     *
     * @var array<string>
     */
    protected array $globalMiddleware = [];

    /**
     * Registered middleware by name.
     *
     * @var array<string, string>
     */
    protected array $registeredMiddleware = [];

    /**
     * Register global middleware that applies to all AI requests.
     *
     * @param  string|array  $middleware  The middleware class name or array with configuration
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
     * Register a named middleware.
     *
     * @param  string  $name  The middleware name
     * @param  string|array  $middleware  The middleware class name or configuration
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
     * Store middleware configuration for later retrieval.
     *
     * @param  string  $middleware  The middleware class name
     * @param  array  $config  The configuration
     */
    protected function storeMiddlewareConfig(string $middleware, array $config): void
    {
        // Store in application container for middleware to access
        app()->instance("middleware.config.{$middleware}", $config);
    }

    /**
     * Process an AI message through the middleware stack.
     *
     * @param  AIMessage  $message  The message to process
     * @param  array<string>  $middleware  Additional middleware for this request
     * @return AIResponse  The processed response
     */
    public function process(AIMessage $message, array $middleware = []): AIResponse
    {
        $stack = $this->buildStack(array_merge($this->globalMiddleware, $middleware));

        return $stack($message);
    }

    /**
     * Build the middleware stack as a closure chain.
     *
     * @param  array<string>  $middleware  The middleware to include in the stack
     * @return Closure  The middleware stack closure
     */
    protected function buildStack(array $middleware): Closure
    {
        return array_reduce(
            array_reverse($middleware),
            function ($next, $middleware) {
                return function (AIMessage $message) use ($next, $middleware) {
                    $instance = $this->resolveMiddleware($middleware);

                    // Track applied middleware
                    if (!isset($message->metadata['middleware_applied'])) {
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
     * Resolve middleware instance from class name.
     *
     * @param  string  $middleware  The middleware class name
     * @return AIMiddlewareInterface  The resolved middleware instance
     */
    protected function resolveMiddleware(string $middleware): AIMiddlewareInterface
    {
        // Resolve from registered middleware if it's a name
        if (isset($this->registeredMiddleware[$middleware])) {
            $middleware = $this->registeredMiddleware[$middleware];
        }

        return app($middleware);
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
     * @return AIResponse  The response
     */
    protected function finalHandler(AIMessage $message): AIResponse
    {
        // Get the AI manager to send the message to the provider
        $aiManager = app('laravel-ai');

        // Send message to AI provider using the send method with message content
        $response = $aiManager->send($message->content);

        // Fire ResponseGenerated event for background processing
        event(new \JTD\LaravelAI\Events\ResponseGenerated(
            message: $message,
            response: $response,
            context: [
                'middleware_applied' => $this->getAppliedMiddleware($message),
                'processing_start_time' => $message->metadata['processing_start_time'] ?? microtime(true),
            ],
            totalProcessingTime: microtime(true) - ($message->metadata['processing_start_time'] ?? microtime(true)),
            providerMetadata: [
                'provider' => $response->provider ?? 'unknown',
                'model' => $response->model ?? 'unknown',
                'tokens_used' => $response->tokenUsage?->totalTokens ?? 0,
            ]
        ));

        return $response;
    }

    /**
     * Get the list of middleware applied to this message.
     *
     * @param  AIMessage  $message  The message
     * @return array  The applied middleware
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
    }
}
