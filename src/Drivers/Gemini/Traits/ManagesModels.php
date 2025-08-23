<?php

namespace JTD\LaravelAI\Drivers\Gemini\Traits;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use JTD\LaravelAI\Drivers\Gemini\Support\ModelCapabilities;
use JTD\LaravelAI\Drivers\Gemini\Support\ModelPricing;

/**
 * Manages Gemini models and their capabilities.
 */
trait ManagesModels
{
    /**
     * Cached models to avoid repeated API calls.
     */
    protected ?array $cachedModels = null;

    /**
     * Get available models from Gemini API.
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
            Log::warning('Failed to fetch models from Gemini API, using fallback', [
                'error' => $e->getMessage(),
            ]);

            // Fallback to known models if API fails
            return $this->getFallbackModels();
        }
    }

    /**
     * Actually get available models from the provider.
     */
    protected function doGetAvailableModels(): array
    {
        try {
            // Fetch models from Gemini API
            $response = $this->http
                ->withHeaders($this->getHeaders())
                ->timeout($this->getTimeout())
                ->get($this->buildModelsEndpoint());

            if ($response->successful()) {
                return $this->parseModelsResponse($response->json());
            }

            throw new \Exception('API request failed with status: ' . $response->status());
        } catch (\Exception $e) {
            Log::warning('Failed to fetch Gemini models from API, using fallback', [
                'error' => $e->getMessage(),
            ]);

            // Fall back to known models
            return $this->getFallbackModels();
        }
    }

    /**
     * Build the models API endpoint.
     */
    protected function buildModelsEndpoint(): string
    {
        $baseUrl = rtrim($this->config['base_url'], '/');
        $apiKey = $this->config['api_key'];

        return "{$baseUrl}/models?key={$apiKey}";
    }

    /**
     * Parse the models response from Gemini API.
     */
    protected function parseModelsResponse(array $response): array
    {
        $models = [];

        if (! isset($response['models'])) {
            return $this->getFallbackModels();
        }

        foreach ($response['models'] as $model) {
            $modelName = $model['name'] ?? null;
            if (! $modelName) {
                continue;
            }

            // Extract model ID from full name (e.g., "models/gemini-pro" -> "gemini-pro")
            $modelId = $this->extractModelId($modelName);

            // Only include models that support generateContent
            $supportedMethods = $model['supportedGenerationMethods'] ?? [];
            if (! in_array('generateContent', $supportedMethods)) {
                continue;
            }

            // Only include valid Gemini models
            if (! $this->isValidGeminiModel($modelId)) {
                continue;
            }

            $models[] = [
                'id' => $modelId,
                'name' => ModelCapabilities::getDisplayName($modelId),
                'description' => ModelCapabilities::getDescription($modelId),
                'context_length' => $model['inputTokenLimit'] ?? ModelCapabilities::getContextLength($modelId),
                'capabilities' => ModelCapabilities::getModelCapabilities($modelId),
                'pricing' => ModelPricing::getModelPricing($modelId),
                'created' => null,
                'owned_by' => 'google',
                'api_data' => [
                    'base_model_id' => $model['baseModelId'] ?? $modelId,
                    'version' => $model['version'] ?? null,
                    'display_name' => $model['displayName'] ?? null,
                    'input_token_limit' => $model['inputTokenLimit'] ?? null,
                    'output_token_limit' => $model['outputTokenLimit'] ?? null,
                    'supported_generation_methods' => $supportedMethods,
                    'temperature' => $model['temperature'] ?? null,
                    'max_temperature' => $model['maxTemperature'] ?? null,
                    'top_p' => $model['topP'] ?? null,
                    'top_k' => $model['topK'] ?? null,
                ],
            ];
        }

        // Sort models by preference (newer models first)
        usort($models, function ($a, $b) {
            $order = [
                'gemini-2.5-pro' => 1,
                'gemini-2.5-flash' => 2,
                'gemini-2.0-flash' => 3,
                'gemini-1.5-pro' => 4,
                'gemini-1.5-flash' => 5,
            ];

            $aOrder = $order[$a['id']] ?? 999;
            $bOrder = $order[$b['id']] ?? 999;

            return $aOrder <=> $bOrder;
        });

        // If no models from API, use fallback models
        if (empty($models)) {
            return $this->getFallbackModels();
        }

        return $models;
    }

    /**
     * Get known models as fallback.
     */
    protected function getFallbackModels(): array
    {
        $models = [];

        foreach (ModelCapabilities::getAllModels() as $modelId) {
            $models[] = [
                'id' => $modelId,
                'name' => ModelCapabilities::getDisplayName($modelId),
                'description' => ModelCapabilities::getDescription($modelId),
                'context_length' => ModelCapabilities::getContextLength($modelId),
                'capabilities' => ModelCapabilities::getModelCapabilities($modelId),
                'pricing' => ModelPricing::getModelPricing($modelId),
                'created' => null,
                'owned_by' => 'google',
                'source' => 'fallback',
            ];
        }

        return $models;
    }

    /**
     * Extract model ID from full model name.
     */
    protected function extractModelId(string $fullName): string
    {
        return str_replace('models/', '', $fullName);
    }

    /**
     * Check if model ID is a valid Gemini model.
     */
    protected function isValidGeminiModel(string $modelId): bool
    {
        return str_starts_with($modelId, 'gemini-') ||
               in_array($modelId, ModelCapabilities::getAllModels());
    }

    /**
     * Get current model.
     */
    public function getCurrentModel(): string
    {
        return $this->currentModel ?? $this->config['default_model'] ?? 'gemini-pro';
    }

    /**
     * Set current model.
     */
    public function setModel(string $model): self
    {
        $this->currentModel = $model;

        return $this;
    }

    /**
     * Get model capabilities.
     */
    public function getModelCapabilities(string $modelId): array
    {
        return ModelCapabilities::getModelCapabilities($modelId);
    }

    /**
     * Check if model supports multimodal input.
     */
    protected function isMultimodalModel(string $modelId): bool
    {
        return ModelCapabilities::supportsVision($modelId);
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
     * Clear the models cache.
     */
    public function clearModelsCache(): void
    {
        $this->cachedModels = null;
        $cacheKey = $this->getModelsCacheKey();
        Cache::forget($cacheKey);
        Cache::forget($cacheKey . ':last_sync');
        Cache::forget($cacheKey . ':stats');
    }

    /**
     * Refresh the models cache.
     */
    public function refreshModelsCache(): array
    {
        $this->clearModelsCache();

        return $this->getAvailableModels(true);
    }

    /**
     * Get the models cache key.
     */
    protected function getModelsCacheKey(): string
    {
        return 'gemini_models_' . md5($this->config['api_key'] ?? 'default');
    }

    /**
     * Synchronize models from the Gemini API to local cache/database.
     */
    public function syncModels(bool $forceRefresh = false): array
    {
        try {
            Log::info('Starting Gemini models synchronization', [
                'provider' => $this->getName(),
                'force_refresh' => $forceRefresh,
            ]);

            // Check if we need to refresh
            if (! $forceRefresh && ! $this->shouldRefreshModels()) {
                Log::info('Gemini models cache is still valid, skipping sync');

                return [
                    'status' => 'skipped',
                    'reason' => 'cache_valid',
                    'last_sync' => $this->getLastSyncTime(),
                ];
            }

            // Fetch models from Gemini API
            $models = $this->getAvailableModels(true);

            // Store in cache with 24-hour expiration
            $cacheKey = $this->getModelsCacheKey();
            Cache::put($cacheKey, $models, now()->addHours(24));

            // Store last sync timestamp
            Cache::put($cacheKey . ':last_sync', now(), now()->addDays(7));

            // Store model statistics
            $stats = $this->storeModelStatistics($models);

            Log::info('Gemini models synchronization completed', [
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
            Log::error('Gemini models synchronization failed', [
                'provider' => $this->getName(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new \JTD\LaravelAI\Exceptions\ProviderException(
                'Failed to sync Gemini models: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Get models that can be synchronized from this provider.
     */
    public function getSyncableModels(): array
    {
        try {
            // This is a lightweight preview - just get the model list without full details
            $response = $this->http
                ->withHeaders($this->getHeaders())
                ->timeout($this->getTimeout())
                ->get($this->buildModelsEndpoint());

            if (! $response->successful()) {
                throw new \Exception('API request failed with status: ' . $response->status());
            }

            $data = $response->json();
            $models = [];

            foreach ($data['models'] ?? [] as $model) {
                $modelId = $this->extractModelId($model['name'] ?? '');

                if ($this->isValidGeminiModel($modelId)) {
                    $models[] = [
                        'id' => $modelId,
                        'name' => ModelCapabilities::getDisplayName($modelId),
                        'owned_by' => 'google',
                        'version' => $model['version'] ?? null,
                    ];
                }
            }

            return $models;
        } catch (\Exception $e) {
            Log::warning('Failed to get syncable Gemini models', [
                'error' => $e->getMessage(),
            ]);

            // Return fallback list
            return array_map(function ($modelId) {
                return [
                    'id' => $modelId,
                    'name' => ModelCapabilities::getDisplayName($modelId),
                    'owned_by' => 'google',
                    'version' => null,
                ];
            }, ModelCapabilities::getAllModels());
        }
    }

    /**
     * Check if models cache should be refreshed.
     */
    protected function shouldRefreshModels(): bool
    {
        $lastSync = $this->getLastSyncTime();

        if (! $lastSync) {
            return true;
        }

        // Refresh if last sync was more than 24 hours ago
        return $lastSync->diffInHours(now()) >= 24;
    }

    /**
     * Get the last sync time.
     */
    public function getLastSyncTime(): ?\Carbon\Carbon
    {
        $cacheKey = $this->getModelsCacheKey() . ':last_sync';

        return Cache::get($cacheKey);
    }

    /**
     * Store model statistics.
     */
    protected function storeModelStatistics(array $models): array
    {
        $stats = [
            'total' => count($models),
            'by_generation' => [],
            'by_capability' => [
                'vision' => 0,
                'function_calling' => 0,
                'streaming' => 0,
            ],
        ];

        foreach ($models as $model) {
            // Count by generation
            if (str_contains($model['id'], '2.5')) {
                $stats['by_generation']['2.5'] = ($stats['by_generation']['2.5'] ?? 0) + 1;
            } elseif (str_contains($model['id'], '2.0')) {
                $stats['by_generation']['2.0'] = ($stats['by_generation']['2.0'] ?? 0) + 1;
            } elseif (str_contains($model['id'], '1.5')) {
                $stats['by_generation']['1.5'] = ($stats['by_generation']['1.5'] ?? 0) + 1;
            } else {
                $stats['by_generation']['1.0'] = ($stats['by_generation']['1.0'] ?? 0) + 1;
            }

            // Count by capabilities
            $capabilities = $model['capabilities'] ?? [];
            if (in_array('vision', $capabilities)) {
                $stats['by_capability']['vision']++;
            }
            if (in_array('function_calling', $capabilities)) {
                $stats['by_capability']['function_calling']++;
            }
            if (in_array('streaming', $capabilities)) {
                $stats['by_capability']['streaming']++;
            }
        }

        // Store statistics in cache
        $statsKey = $this->getModelsCacheKey() . ':stats';
        Cache::put($statsKey, $stats, now()->addDays(7));

        return $stats;
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
}
