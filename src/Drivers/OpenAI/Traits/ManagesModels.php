<?php

namespace JTD\LaravelAI\Drivers\OpenAI\Traits;

use JTD\LaravelAI\Drivers\OpenAI\Support\ModelCapabilities;
use JTD\LaravelAI\Drivers\OpenAI\Support\ModelPricing;
use JTD\LaravelAI\Models\AIMessage;

/**
 * Manages OpenAI Models and Capabilities
 *
 * Handles model listing, capabilities, token estimation,
 * and model-specific functionality.
 */
trait ManagesModels
{
    /**
     * Cached models list.
     */
    protected ?array $cachedModels = null;

    /**
     * Get available models from OpenAI.
     */
    public function getAvailableModels(bool $forceRefresh = false): array
    {
        if (! $forceRefresh && $this->cachedModels !== null) {
            return $this->cachedModels;
        }

        try {
            $this->cachedModels = $this->doGetAvailableModels();

            return $this->cachedModels;
        } catch (\Exception $e) {
            $this->handleApiError($e);
        }
    }

    /**
     * Actually get available models from the provider.
     */
    protected function doGetAvailableModels(): array
    {
        $response = $this->executeWithRetry(function () {
            return $this->client->models()->list();
        });

        $models = [];
        foreach ($response->data as $model) {
            // Only include chat models
            if (ModelCapabilities::isChatModel($model->id)) {
                $models[] = [
                    'id' => $model->id,
                    'name' => ModelCapabilities::getDisplayName($model->id),
                    'description' => ModelCapabilities::getDescription($model->id),
                    'context_length' => ModelCapabilities::getContextLength($model->id),
                    'capabilities' => ModelCapabilities::getModelCapabilities($model->id),
                    'pricing' => ModelPricing::getModelPricing($model->id),
                    'created' => $model->created ?? null,
                    'owned_by' => $model->ownedBy ?? 'openai',
                ];
            }
        }

        // Sort models by preference
        usort($models, [ModelCapabilities::class, 'compareModels']);

        return $models;
    }

    /**
     * Get detailed information about a specific model.
     */
    public function getModelInfo(string $modelId): array
    {
        // For backward compatibility with existing tests
        throw new \BadMethodCallException('Method not yet implemented');
    }

    /**
     * Get provider-specific capabilities.
     */
    public function getCapabilities(): array
    {
        return [
            'streaming' => true,
            'function_calling' => true,
            'vision' => str_contains($this->getCurrentModel(), 'gpt-4o'),
            'json_mode' => true,
            'system_messages' => true,
            'conversation_history' => true,
            'temperature_control' => true,
            'max_tokens_control' => true,
            'top_p_control' => true,
            'frequency_penalty' => true,
            'presence_penalty' => true,
        ];
    }

    /**
     * Get the default model for this provider.
     */
    public function getDefaultModel(): string
    {
        return $this->config['default_model'] ?? $this->defaultModel;
    }

    /**
     * Set the model to use for requests.
     */
    public function setModel(string $modelId): self
    {
        $this->config['default_model'] = $modelId;

        return $this;
    }

    /**
     * Get the currently configured model.
     */
    public function getCurrentModel(): string
    {
        return $this->config['default_model'] ?? $this->defaultModel;
    }

    /**
     * Check if the provider supports a specific feature.
     */
    public function supportsFeature(string $feature): bool
    {
        $capabilities = $this->getCapabilities();

        return $capabilities[$feature] ?? false;
    }

    /**
     * Estimate tokens for input.
     */
    public function estimateTokens($input, ?string $modelId = null): int
    {
        if ($input instanceof AIMessage) {
            return $this->estimateMessageTokens($input);
        }

        if (is_array($input)) {
            $totalTokens = 0;
            foreach ($input as $message) {
                $totalTokens += $this->estimateMessageTokens($message);
            }

            return $totalTokens;
        }

        if (is_string($input)) {
            return $this->estimateStringTokens($input);
        }

        throw new \InvalidArgumentException('Input must be a string, AIMessage, or array of AIMessages');
    }

    /**
     * Estimate tokens for a single message.
     */
    protected function estimateMessageTokens(AIMessage $message): int
    {
        $tokens = $this->estimateStringTokens($message->content);

        // Add overhead for message structure
        $tokens += 4; // Role and message wrapper tokens

        // Add tokens for function calls if present
        if ($message->functionCalls) {
            $tokens += $this->estimateStringTokens(json_encode($message->functionCalls));
        }

        if ($message->toolCalls) {
            $tokens += $this->estimateStringTokens(json_encode($message->toolCalls));
        }

        return $tokens;
    }

    /**
     * Estimate tokens for a string using a simple approximation.
     */
    protected function estimateStringTokens(string $text): int
    {
        // Simple approximation: ~4 characters per token for English text
        // This is a rough estimate and may not be perfectly accurate
        return (int) ceil(strlen($text) / 4);
    }

    /**
     * Estimate response tokens based on input and model.
     */
    protected function estimateResponseTokens($input, string $modelId): int
    {
        $inputTokens = $this->estimateTokens($input, $modelId);

        return $this->estimateResponseTokensFromCount($inputTokens, $modelId);
    }

    /**
     * Estimate response tokens from input token count.
     */
    protected function estimateResponseTokensFromCount(int $inputTokens, string $modelId): int
    {
        // Estimate response length based on input length and model characteristics
        if (str_contains($modelId, 'gpt-4')) {
            // GPT-4 tends to give more detailed responses
            return (int) ($inputTokens * 0.6);
        } elseif (str_contains($modelId, 'gpt-3.5')) {
            // GPT-3.5 gives more concise responses
            return (int) ($inputTokens * 0.4);
        }

        // Default estimation
        return (int) ($inputTokens * 0.5);
    }

    /**
     * Check if model is a chat model.
     */
    protected function isChatModel(string $modelId): bool
    {
        return ModelCapabilities::isChatModel($modelId);
    }

    /**
     * Get model display name.
     */
    protected function getModelDisplayName(string $modelId): string
    {
        return ModelCapabilities::getDisplayName($modelId);
    }

    /**
     * Get model description.
     */
    protected function getModelDescription(string $modelId): string
    {
        return ModelCapabilities::getDescription($modelId);
    }

    /**
     * Get model context length.
     */
    protected function getModelContextLength(string $modelId): int
    {
        return ModelCapabilities::getContextLength($modelId);
    }

    /**
     * Get model capabilities.
     */
    protected function getModelCapabilities(string $modelId): array
    {
        return ModelCapabilities::getModelCapabilities($modelId);
    }

    /**
     * Compare models for sorting.
     */
    protected function compareModels(array $a, array $b): int
    {
        return ModelCapabilities::compareModels($a, $b);
    }

    /**
     * Get rate limits for the current configuration.
     */
    public function getRateLimits(): array
    {
        // For backward compatibility with existing tests
        throw new \BadMethodCallException('Method not yet implemented');
    }

    /**
     * Get the API version being used.
     */
    public function getVersion(): string
    {
        return 'v1'; // OpenAI API version
    }

    /**
     * Set options for the driver.
     */
    public function setOptions(array $options): self
    {
        $this->config = array_merge($this->config, $options);

        return $this;
    }

    /**
     * Get current driver options.
     */
    public function getOptions(): array
    {
        return $this->config;
    }

    /**
     * Clear the models cache.
     */
    public function clearModelsCache(): void
    {
        $this->cachedModels = null;
    }

    /**
     * Refresh the models cache.
     */
    public function refreshModelsCache(): array
    {
        return $this->getAvailableModels(true);
    }

    /**
     * Synchronize models from the provider API to local cache/database.
     */
    public function syncModels(bool $forceRefresh = false): array
    {
        try {
            \Log::info('Starting OpenAI models synchronization', [
                'provider' => $this->getName(),
                'force_refresh' => $forceRefresh,
            ]);

            // Check if we need to refresh
            if (! $forceRefresh && ! $this->shouldRefreshModels()) {
                \Log::info('OpenAI models cache is still valid, skipping sync');

                return [
                    'status' => 'skipped',
                    'reason' => 'cache_valid',
                    'last_sync' => $this->getLastSyncTime(),
                ];
            }

            // Fetch models from OpenAI API
            $models = $this->getAvailableModels(true);

            // Store in cache with 24-hour expiration
            $cacheKey = $this->getModelsCacheKey();
            \Cache::put($cacheKey, $models, now()->addHours(24));

            // Store last sync timestamp
            \Cache::put($cacheKey . ':last_sync', now(), now()->addDays(7));

            // Store model statistics
            $stats = $this->storeModelStatistics($models);

            \Log::info('OpenAI models synchronization completed', [
                'provider' => $this->getName(),
                'models_count' => count($models),
                'cached_until' => now()->addHours(24)->toISOString(),
                'stats' => $stats,
            ]);

            return [
                'status' => 'success',
                'models_synced' => count($models),
                'statistics' => $stats,
                'cached_until' => now()->addHours(24),
                'last_sync' => now(),
            ];
        } catch (\Exception $e) {
            \Log::error('OpenAI models synchronization failed', [
                'provider' => $this->getName(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Store failure information in cache for monitoring
            \Cache::put(
                $this->getModelsCacheKey() . ':last_failure',
                [
                    'error' => $e->getMessage(),
                    'failed_at' => now()->toISOString(),
                ],
                now()->addHours(24)
            );

            throw $e;
        }
    }

    /**
     * Check if the provider has valid credentials configured.
     */
    public function hasValidCredentials(): bool
    {
        try {
            $result = $this->validateCredentials();

            return $result['status'] === 'valid';
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get the timestamp of the last successful model synchronization.
     */
    public function getLastSyncTime(): ?\Carbon\Carbon
    {
        $cacheKey = $this->getModelsCacheKey() . ':last_sync';

        return \Cache::get($cacheKey);
    }

    /**
     * Get models that can be synchronized from this provider.
     */
    public function getSyncableModels(): array
    {
        try {
            // This is a lightweight preview - just get the model list without full details
            $response = $this->executeWithRetry(function () {
                return $this->client->models()->list();
            });

            $models = [];
            foreach ($response->data as $model) {
                if (ModelCapabilities::isChatModel($model->id)) {
                    $models[] = [
                        'id' => $model->id,
                        'name' => ModelCapabilities::getDisplayName($model->id),
                        'owned_by' => $model->ownedBy ?? 'openai',
                        'created' => $model->created ?? null,
                    ];
                }
            }

            return $models;
        } catch (\Exception $e) {
            $this->handleApiError($e);
        }
    }

    /**
     * Determine if we should refresh the models cache.
     */
    protected function shouldRefreshModels(): bool
    {
        $cacheKey = $this->getModelsCacheKey();
        $lastSync = \Cache::get($cacheKey . ':last_sync');

        // Refresh if no last sync time or if it's been more than 12 hours
        return ! $lastSync || $lastSync->diffInHours(now()) >= 12;
    }

    /**
     * Get the cache key for models.
     */
    protected function getModelsCacheKey(): string
    {
        return 'laravel-ai:openai:models';
    }

    /**
     * Store model statistics for monitoring.
     */
    protected function storeModelStatistics(array $models): array
    {
        $stats = [
            'total_models' => count($models),
            'gpt_3_5_models' => 0,
            'gpt_4_models' => 0,
            'gpt_4o_models' => 0,
            'function_calling_models' => 0,
            'vision_models' => 0,
            'updated_at' => now()->toISOString(),
        ];

        foreach ($models as $model) {
            $modelId = $model['id'];
            $capabilities = $model['capabilities'] ?? [];

            // Count model types
            if (str_contains($modelId, 'gpt-3.5')) {
                $stats['gpt_3_5_models']++;
            } elseif (str_contains($modelId, 'gpt-4o')) {
                $stats['gpt_4o_models']++;
            } elseif (str_contains($modelId, 'gpt-4')) {
                $stats['gpt_4_models']++;
            }

            // Count capabilities
            if (in_array('function_calling', $capabilities)) {
                $stats['function_calling_models']++;
            }

            if (in_array('vision', $capabilities)) {
                $stats['vision_models']++;
            }
        }

        // Store statistics
        \Cache::put($this->getModelsCacheKey() . ':stats', $stats, now()->addDays(7));

        return $stats;
    }
}
