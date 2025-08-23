<?php

namespace JTD\LaravelAI\Services;

use Illuminate\Contracts\Foundation\Application;
use JTD\LaravelAI\Contracts\AIProviderInterface;
use JTD\LaravelAI\Contracts\ConversationBuilderInterface;
use JTD\LaravelAI\Events\ResponseGenerated;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Models\AIResponse;

/**
 * AI Manager
 *
 * Central manager for coordinating AI operations and provider management.
 * Delegates driver management to DriverManager while providing high-level AI operations.
 */
class AIManager
{
    /**
     * The application instance.
     */
    protected Application $app;

    /**
     * The driver manager instance.
     */
    protected DriverManager $driverManager;

    /**
     * Create a new AI manager instance.
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->driverManager = new DriverManager($app);
    }

    /**
     * Get a driver instance.
     */
    public function driver(?string $name = null): AIProviderInterface
    {
        return $this->driverManager->driver($name);
    }

    /**
     * Get the default driver name.
     */
    public function getDefaultDriver(): string
    {
        return $this->driverManager->getDefaultDriver();
    }

    /**
     * Create a conversation builder instance.
     *
     * @param  string|null  $title  Conversation title
     */
    public function conversation(?string $title = null): ConversationBuilderInterface
    {
        $builder = $this->app->make(ConversationBuilderInterface::class);

        if ($title) {
            $builder->title($title);
        }

        return $builder;
    }

    /**
     * Send a quick message using the default provider.
     *
     * @param  string  $message  Message content
     * @param  array  $options  Request options
     */
    public function send(string $message, array $options = []): AIResponse
    {
        $provider = $this->driver();
        $aiMessage = AIMessage::user($message);
        $aiMessage->user_id = auth()->id() ?? 0;
        $aiMessage->metadata = ['processing_start_time' => microtime(true)];

        // Send message to provider
        $response = $provider->sendMessage($aiMessage, $options);

        // Fire ResponseGenerated event for background processing
        if (config('ai.events.enabled', true)) {
            event(new \JTD\LaravelAI\Events\ResponseGenerated(
                message: $aiMessage,
                response: $response,
                context: [
                    'direct_ai_manager_call' => true,
                    'processing_start_time' => $aiMessage->metadata['processing_start_time'],
                ],
                totalProcessingTime: microtime(true) - $aiMessage->metadata['processing_start_time'],
                providerMetadata: [
                    'provider' => $response->provider ?? $provider->getName() ?? 'unknown',
                    'model' => $response->model ?? $provider->getModel() ?? 'unknown',
                    'tokens_used' => $response->tokenUsage?->totalTokens ?? 0,
                ]
            ));
        }

        return $response;
    }

    /**
     * Send a streaming message using the default provider.
     *
     * @param  string  $message  Message content
     * @param  array  $options  Request options
     * @return \Generator<AIResponse>
     */
    public function stream(string $message, array $options = []): \Generator
    {
        $provider = $this->driver();
        $aiMessage = AIMessage::user($message);
        $aiMessage->user_id = auth()->id() ?? 0;
        $aiMessage->metadata = ['processing_start_time' => microtime(true)];

        $generator = $provider->sendStreamingMessage($aiMessage, $options);

        // For streaming, we'll fire the event after the stream completes
        $chunks = [];
        $startTime = $aiMessage->metadata['processing_start_time'];

        foreach ($generator as $chunk) {
            $chunks[] = $chunk;
            yield $chunk;
        }

        // Fire event after streaming completes (if events are enabled)
        if (config('ai.events.enabled', true) && ! empty($chunks)) {
            $finalChunk = end($chunks);

            event(new \JTD\LaravelAI\Events\ResponseGenerated(
                message: $aiMessage,
                response: $finalChunk,
                context: [
                    'streaming_response' => true,
                    'direct_ai_manager_call' => true,
                    'total_chunks' => count($chunks),
                    'processing_start_time' => $startTime,
                ],
                totalProcessingTime: microtime(true) - $startTime,
                providerMetadata: [
                    'provider' => $finalChunk->provider ?? $provider->getName() ?? 'unknown',
                    'model' => $finalChunk->model ?? $provider->getModel() ?? 'unknown',
                    'tokens_used' => $finalChunk->tokenUsage?->totalTokens ?? 0,
                ]
            ));
        }
    }

    /**
     * Get all configured providers.
     */
    public function getProviders(): array
    {
        $providers = [];
        $availableProviders = $this->driverManager->getAvailableProviders();

        foreach ($availableProviders as $name) {
            try {
                $info = $this->driverManager->getProviderInfo($name);
                $providers[$name] = array_merge($info, [
                    'status' => $this->getProviderStatus($name),
                    'capabilities' => $this->getProviderCapabilities($name),
                ]);
            } catch (\Exception $e) {
                $providers[$name] = [
                    'name' => $name,
                    'status' => 'error',
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $providers;
    }

    /**
     * Get available models for a provider.
     */
    public function getModels(?string $provider = null): array
    {
        $providerInstance = $this->driver($provider);

        return $providerInstance->getAvailableModels();
    }

    /**
     * Calculate cost for a message.
     *
     * @param  string  $message  Message content
     * @param  string|null  $provider  Provider name
     * @param  string|null  $model  Model name
     */
    public function calculateCost(string $message, ?string $provider = null, ?string $model = null): array
    {
        $providerInstance = $provider ? $this->driver($provider) : $this->driver();
        $aiMessage = AIMessage::user($message);

        return $providerInstance->calculateCost($aiMessage, $model);
    }

    /**
     * Validate a provider configuration.
     */
    public function validateProvider(string $provider): bool
    {
        $result = $this->driverManager->validateProvider($provider);

        return $result['status'] === 'valid';
    }

    /**
     * Get provider health status.
     */
    public function getProviderHealth(?string $provider = null): array
    {
        if ($provider) {
            return $this->driverManager->getProviderHealth($provider);
        }

        // Check all providers
        $health = [];
        foreach ($this->driverManager->getAvailableProviders() as $providerName) {
            $health[$providerName] = $this->driverManager->getProviderHealth($providerName);
        }

        return $health;
    }

    /**
     * Get usage statistics.
     *
     * @param  string  $period  Period for statistics (hour, day, month)
     */
    public function getUsageStats(string $period = 'day'): array
    {
        // This will be implemented when we add analytics
        return [
            'period' => $period,
            'total_requests' => 0,
            'total_tokens' => 0,
            'total_cost' => 0,
            'providers' => [],
        ];
    }

    /**
     * Get analytics data.
     *
     * @param  array  $filters  Analytics filters
     */
    public function getAnalytics(array $filters = []): array
    {
        // This will be implemented when we add analytics
        return [
            'filters' => $filters,
            'data' => [],
        ];
    }

    /**
     * Register a custom driver creator.
     */
    public function extend(string $driver, \Closure $callback): self
    {
        $this->driverManager->extend($driver, $callback);

        return $this;
    }

    /**
     * Estimate tokens for given input.
     */
    public function estimateTokens($input, ?string $provider = null): int
    {
        $providerInstance = $this->driver($provider);

        return $providerInstance->estimateTokens($input);
    }

    /**
     * Get provider status.
     *
     * @param  string  $provider  Provider name
     */
    protected function getProviderStatus(string $provider): string
    {
        try {
            $this->driver($provider);

            return 'available';
        } catch (\Exception $e) {
            return 'unavailable';
        }
    }

    /**
     * Get provider capabilities.
     *
     * @param  string  $provider  Provider name
     */
    protected function getProviderCapabilities(string $provider): array
    {
        try {
            $providerInstance = $this->driver($provider);

            return $providerInstance->getCapabilities();
        } catch (\Exception $e) {
            return [];
        }
    }
}
