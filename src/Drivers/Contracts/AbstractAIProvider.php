<?php

namespace JTD\LaravelAI\Drivers\Contracts;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use JTD\LaravelAI\Contracts\AIProviderInterface;
use JTD\LaravelAI\Exceptions\ProviderException;
use JTD\LaravelAI\Exceptions\RateLimitException;
use JTD\LaravelAI\Models\AIResponse;

/**
 * Abstract AI Provider
 *
 * Base class for all AI provider drivers with common functionality
 * including retry logic, error handling, caching, and rate limiting.
 */
abstract class AbstractAIProvider implements AIProviderInterface
{
    /**
     * Provider configuration.
     */
    protected array $config;

    /**
     * Current model.
     */
    protected string $model;

    /**
     * Request options.
     */
    protected array $options = [];

    /**
     * Retry configuration.
     */
    protected array $retryConfig = [
        'max_attempts' => 3,
        'base_delay' => 1000, // milliseconds
        'max_delay' => 30000, // milliseconds
        'backoff_multiplier' => 2,
        'jitter' => true,
    ];

    /**
     * Cache configuration.
     */
    protected array $cacheConfig = [
        'enabled' => true,
        'ttl' => [
            'models' => 3600, // 1 hour
            'costs' => 86400, // 24 hours
            'responses' => 300, // 5 minutes
        ],
        'prefix' => 'ai',
    ];

    /**
     * Rate limiting configuration.
     */
    protected array $rateLimitConfig = [
        'enabled' => true,
        'requests_per_minute' => 60,
        'tokens_per_minute' => 100000,
    ];

    /**
     * Create a new abstract AI provider instance.
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->model = $this->getDefaultModel();

        // Merge configuration overrides
        $this->retryConfig = array_merge($this->retryConfig, $config['retry'] ?? []);
        $this->cacheConfig = array_merge($this->cacheConfig, $config['cache'] ?? []);
        $this->rateLimitConfig = array_merge($this->rateLimitConfig, $config['rate_limit'] ?? []);
    }

    /**
     * Send a message with retry logic and error handling.
     */
    public function sendMessage($message, array $options = []): AIResponse
    {
        $messages = is_array($message) ? $message : [$message];
        $mergedOptions = array_merge($this->options, $options);

        return $this->executeWithRetry(function () use ($messages, $mergedOptions) {
            $this->checkRateLimit();

            // Get the primary message for event firing
            $primaryMessage = $messages[0] ?? null;

            // Fire MessageSent event before API call
            if ($primaryMessage && config('ai.events.enabled', true)) {
                event(new \JTD\LaravelAI\Events\MessageSent(
                    message: $primaryMessage,
                    provider: $this->getName(),
                    model: $mergedOptions['model'] ?? $this->getCurrentModel() ?? 'unknown',
                    options: $mergedOptions,
                    conversationId: $primaryMessage->conversation_id ?? null,
                    userId: $primaryMessage->user_id ?? null
                ));
            }

            $startTime = microtime(true);
            $response = $this->doSendMessage($messages, $mergedOptions);
            $responseTime = (microtime(true) - $startTime) * 1000;

            $response->responseTimeMs = $responseTime;

            $this->logRequest($messages, $response, $mergedOptions);

            // Fire ResponseGenerated event after API call
            if ($primaryMessage && config('ai.events.enabled', true)) {
                event(new \JTD\LaravelAI\Events\ResponseGenerated(
                    message: $primaryMessage,
                    response: $response,
                    context: [
                        'provider_level_event' => true,
                        'processing_start_time' => $startTime,
                    ],
                    totalProcessingTime: $responseTime / 1000, // Convert to seconds
                    providerMetadata: [
                        'provider' => $response->provider ?? $this->getName() ?? 'unknown',
                        'model' => $response->model ?? $this->getCurrentModel() ?? 'unknown',
                        'tokens_used' => $response->tokenUsage?->totalTokens ?? 0,
                    ]
                ));
            }

            // Fire CostCalculated event if we have token usage
            if ($primaryMessage && $response->tokenUsage && config('ai.events.enabled', true)) {
                // Calculate cost using the actual token usage from the response
                $costData = $this->calculateCost($response->tokenUsage, $response->model ?? $this->getCurrentModel());
                $cost = is_array($costData) ? ($costData['total'] ?? $costData['total_cost'] ?? 0) : $costData;

                event(new \JTD\LaravelAI\Events\CostCalculated(
                    userId: $primaryMessage->user_id ?? 0,
                    provider: $this->getName(),
                    model: $response->model ?? $this->getCurrentModel() ?? 'unknown',
                    cost: (float) $cost,
                    inputTokens: $response->tokenUsage->inputTokens ?? 0,
                    outputTokens: $response->tokenUsage->outputTokens ?? 0,
                    conversationId: $primaryMessage->conversation_id ?? null,
                    messageId: $primaryMessage->id ?? null
                ));
            }

            return $response;
        });
    }

    /**
     * Send a streaming message with retry logic.
     */
    public function sendStreamingMessage($message, array $options = []): \Generator
    {
        $messages = is_array($message) ? $message : [$message];
        $mergedOptions = array_merge($this->options, $options);

        $this->checkRateLimit();

        // Get the primary message for event firing
        $primaryMessage = $messages[0] ?? null;
        $startTime = microtime(true);

        // Fire MessageSent event before streaming starts
        if ($primaryMessage && config('ai.events.enabled', true)) {
            event(new \JTD\LaravelAI\Events\MessageSent(
                message: $primaryMessage,
                provider: $this->getName(),
                model: $mergedOptions['model'] ?? $this->getCurrentModel() ?? 'unknown',
                options: $mergedOptions,
                conversationId: $primaryMessage->conversation_id ?? null,
                userId: $primaryMessage->user_id ?? null
            ));
        }

        // Collect chunks for final event firing
        $chunks = [];
        $finalResponse = null;

        foreach ($this->doSendStreamingMessage($messages, $mergedOptions) as $chunk) {
            $chunks[] = $chunk;
            $finalResponse = $chunk; // Keep track of the last chunk
            yield $chunk;
        }

        // Fire ResponseGenerated event after streaming completes
        if ($primaryMessage && $finalResponse && config('ai.events.enabled', true)) {
            $totalTime = microtime(true) - $startTime;

            event(new \JTD\LaravelAI\Events\ResponseGenerated(
                message: $primaryMessage,
                response: $finalResponse,
                context: [
                    'provider_level_event' => true,
                    'streaming_response' => true,
                    'total_chunks' => count($chunks),
                    'processing_start_time' => $startTime,
                ],
                totalProcessingTime: $totalTime,
                providerMetadata: [
                    'provider' => $finalResponse->provider ?? $this->getName() ?? 'unknown',
                    'model' => $finalResponse->model ?? $this->getCurrentModel() ?? 'unknown',
                    'tokens_used' => $finalResponse->tokenUsage?->totalTokens ?? 0,
                ]
            ));

            // Fire CostCalculated event if we have token usage
            if ($finalResponse->tokenUsage) {
                $costData = $this->calculateCost($finalResponse->tokenUsage, $finalResponse->model ?? $this->getCurrentModel());
                $cost = is_array($costData) ? ($costData['total'] ?? $costData['total_cost'] ?? 0) : $costData;

                event(new \JTD\LaravelAI\Events\CostCalculated(
                    userId: $primaryMessage->user_id ?? 0,
                    provider: $this->getName(),
                    model: $finalResponse->model ?? $this->getCurrentModel() ?? 'unknown',
                    cost: (float) $cost,
                    inputTokens: $finalResponse->tokenUsage->inputTokens ?? 0,
                    outputTokens: $finalResponse->tokenUsage->outputTokens ?? 0,
                    conversationId: $primaryMessage->conversation_id ?? null,
                    messageId: $primaryMessage->id ?? null
                ));
            }
        }
    }

    /**
     * Get available models with caching.
     */
    public function getAvailableModels(bool $forceRefresh = false): array
    {
        if (! $this->cacheConfig['enabled'] || $forceRefresh) {
            return $this->doGetAvailableModels();
        }

        $cacheKey = $this->getCacheKey('models', $this->getName());

        return Cache::remember($cacheKey, $this->cacheConfig['ttl']['models'], function () {
            return $this->doGetAvailableModels();
        });
    }

    /**
     * Calculate cost with caching.
     */
    public function calculateCost($input, ?string $modelId = null): array
    {
        $model = $modelId ?? $this->model;
        $tokenCount = $this->estimateTokens($input, $model);

        if (! $this->cacheConfig['enabled']) {
            return $this->doCalculateCost($tokenCount, $model);
        }

        $cacheKey = $this->getCacheKey('costs', $this->getName(), $model);

        $rates = Cache::remember($cacheKey, $this->cacheConfig['ttl']['costs'], function () use ($model) {
            return $this->getCostRates($model);
        });

        return $this->calculateCostFromRates($tokenCount, $rates);
    }

    /**
     * Set the model to use for requests.
     */
    public function setModel(string $modelId): self
    {
        $this->model = $modelId;

        return $this;
    }

    /**
     * Get the currently configured model.
     */
    public function getCurrentModel(): string
    {
        return $this->model;
    }

    /**
     * Set request options.
     */
    public function setOptions(array $options): self
    {
        $this->options = array_merge($this->options, $options);

        return $this;
    }

    /**
     * Get current request options.
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Execute a function with retry logic.
     */
    protected function executeWithRetry(callable $callback)
    {
        $attempt = 1;
        $maxAttempts = $this->retryConfig['max_attempts'];

        while ($attempt <= $maxAttempts) {
            try {
                return $callback();
            } catch (RateLimitException $e) {
                if ($attempt === $maxAttempts) {
                    throw $e;
                }

                $this->handleRetryDelay($attempt, $e->getWaitTime());
            } catch (ProviderException $e) {
                if (! $e->isRetryable() || $attempt === $maxAttempts) {
                    throw $e;
                }

                $this->handleRetryDelay($attempt);
            }

            $attempt++;
        }
    }

    /**
     * Handle retry delay with exponential backoff and jitter.
     */
    protected function handleRetryDelay(int $attempt, ?int $waitTime = null): void
    {
        if ($waitTime) {
            // Use provider-specified wait time (e.g., from rate limit headers)
            $delay = min($waitTime * 1000, $this->retryConfig['max_delay']);
        } else {
            // Calculate exponential backoff delay
            $delay = min(
                $this->retryConfig['base_delay'] * pow($this->retryConfig['backoff_multiplier'], $attempt - 1),
                $this->retryConfig['max_delay']
            );
        }

        // Add jitter to prevent thundering herd
        if ($this->retryConfig['jitter']) {
            $delay = $delay + random_int(0, (int) ($delay * 0.1));
        }

        Log::info("AI Provider retry attempt {$attempt}, waiting {$delay}ms", [
            'provider' => $this->getName(),
            'attempt' => $attempt,
            'delay_ms' => $delay,
        ]);

        usleep($delay * 1000); // Convert to microseconds
    }

    /**
     * Check rate limits before making requests.
     */
    protected function checkRateLimit(): void
    {
        if (! $this->rateLimitConfig['enabled']) {
            return;
        }

        $provider = $this->getName();
        $now = time();
        $minute = floor($now / 60);

        $requestKey = "rate_limit:{$provider}:requests:{$minute}";
        $tokenKey = "rate_limit:{$provider}:tokens:{$minute}";

        $currentRequests = Cache::get($requestKey, 0);
        $currentTokens = Cache::get($tokenKey, 0);

        if ($currentRequests >= $this->rateLimitConfig['requests_per_minute']) {
            throw new RateLimitException(
                'Request rate limit exceeded',
                $this->rateLimitConfig['requests_per_minute'],
                0,
                60 - ($now % 60),
                'requests'
            );
        }

        if ($currentTokens >= $this->rateLimitConfig['tokens_per_minute']) {
            throw new RateLimitException(
                'Token rate limit exceeded',
                $this->rateLimitConfig['tokens_per_minute'],
                0,
                60 - ($now % 60),
                'tokens'
            );
        }

        // Increment counters
        Cache::put($requestKey, $currentRequests + 1, 60);
    }

    /**
     * Update token usage for rate limiting.
     */
    protected function updateTokenUsage(int $tokens): void
    {
        if (! $this->rateLimitConfig['enabled']) {
            return;
        }

        $provider = $this->getName();
        $minute = floor(time() / 60);
        $tokenKey = "rate_limit:{$provider}:tokens:{$minute}";

        $currentTokens = Cache::get($tokenKey, 0);
        Cache::put($tokenKey, $currentTokens + $tokens, 60);
    }

    /**
     * Log request and response for debugging and analytics.
     */
    protected function logRequest(array $messages, AIResponse $response, array $options): void
    {
        if (! ($this->config['logging']['enabled'] ?? true)) {
            return;
        }

        $logData = [
            'provider' => $this->getName(),
            'model' => $this->model,
            'message_count' => count($messages),
            'response_time_ms' => $response->responseTimeMs,
            'token_usage' => $response->tokenUsage->toArray(),
            'cost' => $response->getTotalCost(),
            'finish_reason' => $response->finishReason,
        ];

        if ($this->config['logging']['include_content'] ?? false) {
            $logData['messages'] = array_map(fn ($msg) => $msg->toArray(), $messages);
            $logData['response_content'] = $response->content;
        }

        Log::info('AI request completed', $logData);
    }

    /**
     * Get cache key for a specific type and parameters.
     */
    protected function getCacheKey(string $type, ...$params): string
    {
        $key = $this->cacheConfig['prefix'] . ':' . $type;
        foreach ($params as $param) {
            $key .= ':' . md5((string) $param);
        }

        return $key;
    }

    /**
     * Calculate cost from cached rates.
     */
    protected function calculateCostFromRates(int $tokens, array $rates): array
    {
        $inputCost = ($tokens * 0.6) * ($rates['input'] ?? 0) / 1000;
        $outputCost = ($tokens * 0.4) * ($rates['output'] ?? 0) / 1000;

        return [
            'total' => $inputCost + $outputCost,
            'input_cost' => $inputCost,
            'output_cost' => $outputCost,
            'currency' => $rates['currency'] ?? 'USD',
            'tokens' => $tokens,
        ];
    }

    // Abstract methods that must be implemented by concrete providers

    /**
     * Actually send the message to the provider.
     */
    abstract protected function doSendMessage(array $messages, array $options): AIResponse;

    /**
     * Actually send the streaming message to the provider.
     */
    abstract protected function doSendStreamingMessage(array $messages, array $options): \Generator;

    /**
     * Actually get available models from the provider.
     */
    abstract protected function doGetAvailableModels(): array;

    /**
     * Actually calculate cost for the given input.
     */
    abstract protected function doCalculateCost(int $tokens, string $model): array;

    /**
     * Get cost rates for a specific model.
     */
    abstract protected function getCostRates(string $model): array;
}
