<?php

namespace JTD\LaravelAI\Services;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use JTD\LaravelAI\Events\FunctionCallRequested;

/**
 * AI Function Event Service
 * 
 * Provides a unified system for registering AI function calls as events.
 * Single AIFunctionEvent::listen() call registers both function definition
 * and event listener for background processing.
 */
class AIFunctionEvent
{
    /**
     * Registered function definitions for AI providers.
     *
     * @var array<string, array>
     */
    protected static array $registeredFunctions = [];

    /**
     * Registered function listeners.
     *
     * @var array<string, string>
     */
    protected static array $functionListeners = [];

    /**
     * Register a function definition and event listener.
     *
     * @param  string  $functionName  The function name
     * @param  string  $listenerClass  The listener class
     * @param  array  $parameters  Function parameters definition
     */
    public static function listen(string $functionName, string $listenerClass, array $parameters = []): void
    {
        // Register the function definition for AI providers
        static::registerFunction($functionName, $listenerClass, $parameters);

        // Register the event listener
        static::registerListener($functionName, $listenerClass);
    }

    /**
     * Register function definition with AI providers.
     *
     * @param  string  $functionName  The function name
     * @param  string  $listenerClass  The listener class
     * @param  array  $parameters  Function parameters definition
     */
    protected static function registerFunction(string $functionName, string $listenerClass, array $parameters): void
    {
        // Extract function definition from listener class or parameters
        $definition = static::buildFunctionDefinition($functionName, $listenerClass, $parameters);

        static::$registeredFunctions[$functionName] = $definition;

        // Register with AI providers that support function calling
        static::registerWithProviders($functionName, $definition);
    }

    /**
     * Register Laravel event listener for function calls.
     *
     * @param  string  $functionName  The function name
     * @param  string  $listenerClass  The listener class
     */
    protected static function registerListener(string $functionName, string $listenerClass): void
    {
        static::$functionListeners[$functionName] = $listenerClass;

        // Register Laravel event listener
        Event::listen(FunctionCallRequested::class, function ($event) use ($functionName, $listenerClass) {
            if ($event->functionName === $functionName) {
                app($listenerClass)->handle($event);
            }
        });
    }

    /**
     * Build function definition from listener class or parameters.
     *
     * @param  string  $functionName  The function name
     * @param  string  $listenerClass  The listener class
     * @param  array  $parameters  Function parameters definition
     * @return array  The function definition
     */
    protected static function buildFunctionDefinition(string $functionName, string $listenerClass, array $parameters): array
    {
        // Try to get definition from listener class first
        if (method_exists($listenerClass, 'getFunctionDefinition')) {
            return app($listenerClass)->getFunctionDefinition();
        }

        // Build from provided parameters
        return [
            'name' => $functionName,
            'description' => $parameters['description'] ?? "Execute {$functionName} action",
            'parameters' => $parameters['parameters'] ?? [
                'type' => 'object',
                'properties' => [],
            ],
        ];
    }

    /**
     * Register function with AI providers that support function calling.
     *
     * @param  string  $functionName  The function name
     * @param  array  $definition  The function definition
     */
    protected static function registerWithProviders(string $functionName, array $definition): void
    {
        // TODO: This will be implemented when we integrate with existing AI providers
        // For now, we just store the definition for later registration
        
        // Example implementation:
        // $providers = app('ai.manager')->getProviders();
        // foreach ($providers as $provider) {
        //     if (method_exists($provider, 'registerFunction')) {
        //         $provider->registerFunction($functionName, $definition);
        //     }
        // }
    }

    /**
     * Get all registered functions.
     *
     * @return array<string, array>
     */
    public static function getRegisteredFunctions(): array
    {
        return static::$registeredFunctions;
    }

    /**
     * Process a function call by firing the appropriate event.
     *
     * @param  string  $functionName  The function name
     * @param  array  $parameters  The function parameters
     * @param  array  $context  Additional context
     */
    public static function processFunctionCall(string $functionName, array $parameters, array $context = []): void
    {
        if (!isset(static::$functionListeners[$functionName])) {
            Log::warning("No listener registered for function: {$functionName}");
            return;
        }

        // Fire the event for background processing
        event(new FunctionCallRequested(
            functionName: $functionName,
            parameters: $parameters,
            userId: $context['user_id'] ?? 0,
            conversationId: $context['conversation_id'] ?? null,
            messageId: $context['message_id'] ?? null,
            context: $context
        ));
    }

    /**
     * Clear all registered functions (useful for testing).
     */
    public static function clearRegistrations(): void
    {
        static::$registeredFunctions = [];
        static::$functionListeners = [];
    }
}
