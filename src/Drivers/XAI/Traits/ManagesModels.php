<?php

namespace JTD\LaravelAI\Drivers\XAI\Traits;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use JTD\LaravelAI\Models\AIModel;

/**
 * Manages Models for xAI
 *
 * Handles model discovery, synchronization, and caching for xAI Grok models.
 * Provides methods for retrieving available models, syncing with the API,
 * and managing model metadata.
 */
trait ManagesModels
{
    /**
     * Get available models from xAI API.
     */
    public function getAvailableModels(bool $forceRefresh = false): array
    {
        $cacheKey = $this->getModelsCacheKey();

        if (! $forceRefresh && Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        try {
            $response = $this->client->get($this->config['base_url'] . '/models');

            if (! $response->successful()) {
                Log::warning('Failed to fetch xAI models', [
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);

                return $this->getFallbackModels();
            }

            $data = $response->json();
            $models = collect($data['data'] ?? [])
                ->map(fn ($model) => $this->transformApiModel($model))
                ->filter()
                ->values()
                ->toArray();

            // Cache for 1 hour
            Cache::put($cacheKey, $models, 3600);

            return $models;
        } catch (\Exception $e) {
            Log::error('Error fetching xAI models', [
                'error' => $e->getMessage(),
                'provider' => $this->providerName,
            ]);

            return $this->getFallbackModels();
        }
    }

    /**
     * Transform API model data to our format.
     */
    protected function transformApiModel(array $apiModel): ?array
    {
        $id = $apiModel['id'] ?? null;
        if (! $id) {
            return null;
        }

        return [
            'id' => $id,
            'name' => $this->getModelDisplayName($id),
            'description' => $this->getModelDescription($id),
            'provider' => $this->providerName,
            'type' => 'chat',
            'context_length' => $this->getModelContextLength($id),
            'capabilities' => $this->getModelCapabilities($id),
            'pricing' => $this->getModelPricing($id),
            'created' => isset($apiModel['created']) ? Carbon::createFromTimestamp($apiModel['created']) : null,
            'owned_by' => $apiModel['owned_by'] ?? 'xai',
            'metadata' => [
                'object' => $apiModel['object'] ?? 'model',
                'permission' => $apiModel['permission'] ?? [],
                'root' => $apiModel['root'] ?? $id,
                'parent' => $apiModel['parent'] ?? null,
            ],
        ];
    }

    /**
     * Get fallback models when API is unavailable.
     */
    protected function getFallbackModels(): array
    {
        return [
            [
                'id' => 'grok-beta',
                'name' => 'Grok Beta',
                'description' => 'xAI\'s flagship conversational AI model (beta version)',
                'provider' => $this->providerName,
                'type' => 'chat',
                'context_length' => 131072,
                'capabilities' => ['chat', 'function_calling', 'streaming'],
                'pricing' => [
                    'input' => 0.000005,  // $5 per 1M tokens (estimated)
                    'output' => 0.000015, // $15 per 1M tokens (estimated)
                ],
                'created' => null,
                'owned_by' => 'xai',
                'metadata' => [],
            ],
            [
                'id' => 'grok-2',
                'name' => 'Grok 2',
                'description' => 'Advanced reasoning and conversation model',
                'provider' => $this->providerName,
                'type' => 'chat',
                'context_length' => 131072,
                'capabilities' => ['chat', 'function_calling', 'streaming'],
                'pricing' => [
                    'input' => 0.000002,  // $2 per 1M tokens (estimated)
                    'output' => 0.000010, // $10 per 1M tokens (estimated)
                ],
                'created' => null,
                'owned_by' => 'xai',
                'metadata' => [],
            ],
            [
                'id' => 'grok-2-mini',
                'name' => 'Grok 2 Mini',
                'description' => 'Smaller, faster version of Grok 2',
                'provider' => $this->providerName,
                'type' => 'chat',
                'context_length' => 131072,
                'capabilities' => ['chat', 'function_calling', 'streaming'],
                'pricing' => [
                    'input' => 0.000001,  // $1 per 1M tokens (estimated)
                    'output' => 0.000005, // $5 per 1M tokens (estimated)
                ],
                'created' => null,
                'owned_by' => 'xai',
                'metadata' => [],
            ],
        ];
    }

    /**
     * Synchronize models from xAI API to local database.
     */
    public function syncModels(bool $forceRefresh = false): array
    {
        $startTime = microtime(true);
        $results = [
            'synced' => 0,
            'updated' => 0,
            'errors' => 0,
            'model_details' => [],
        ];

        try {
            $models = $this->getAvailableModels($forceRefresh);

            foreach ($models as $modelData) {
                try {
                    $model = AIModel::updateOrCreate(
                        [
                            'provider' => $this->providerName,
                            'model_id' => $modelData['id'],
                        ],
                        [
                            'name' => $modelData['name'],
                            'description' => $modelData['description'],
                            'type' => $modelData['type'],
                            'context_length' => $modelData['context_length'],
                            'capabilities' => $modelData['capabilities'],
                            'pricing' => $modelData['pricing'],
                            'metadata' => $modelData['metadata'],
                            'is_active' => true,
                            'last_synced_at' => now(),
                        ]
                    );

                    if ($model->wasRecentlyCreated) {
                        $results['synced']++;
                        $status = 'created';
                    } else {
                        $results['updated']++;
                        $status = 'updated';
                    }

                    $results['model_details'][] = [
                        'id' => $modelData['id'],
                        'name' => $modelData['name'],
                        'status' => $status,
                    ];
                } catch (\Exception $e) {
                    $results['errors']++;
                    $results['model_details'][] = [
                        'id' => $modelData['id'] ?? 'unknown',
                        'name' => $modelData['name'] ?? 'unknown',
                        'status' => 'error',
                        'error' => $e->getMessage(),
                    ];

                    Log::error('Failed to sync xAI model', [
                        'model_id' => $modelData['id'] ?? 'unknown',
                        'error' => $e->getMessage(),
                        'provider' => $this->providerName,
                    ]);
                }
            }

            // Update sync timestamp
            Cache::put($this->getLastSyncCacheKey(), now(), 86400); // 24 hours

            $syncTime = (microtime(true) - $startTime) * 1000;

            Log::info('xAI models synchronized', [
                'provider' => $this->providerName,
                'synced' => $results['synced'],
                'updated' => $results['updated'],
                'errors' => $results['errors'],
                'sync_time_ms' => round($syncTime, 2),
            ]);
        } catch (\Exception $e) {
            Log::error('xAI model sync failed', [
                'provider' => $this->providerName,
                'error' => $e->getMessage(),
            ]);

            $results['errors']++;
        }

        return $results;
    }

    /**
     * Check if the provider has valid credentials configured.
     */
    public function hasValidCredentials(): bool
    {
        return ! empty($this->config['api_key']);
    }

    /**
     * Get the timestamp of the last successful model synchronization.
     */
    public function getLastSyncTime(): ?Carbon
    {
        $timestamp = Cache::get($this->getLastSyncCacheKey());

        return $timestamp ? Carbon::parse($timestamp) : null;
    }

    /**
     * Get models that can be synchronized from this provider.
     */
    public function getSyncableModels(): array
    {
        if (! $this->hasValidCredentials()) {
            return [];
        }

        $models = $this->getAvailableModels();

        return array_map(function ($model) {
            return [
                'id' => $model['id'],
                'name' => $model['name'],
                'provider' => $this->providerName,
                'syncable' => true,
            ];
        }, $models);
    }

    /**
     * Get model display name.
     */
    protected function getModelDisplayName(string $modelId): string
    {
        return match ($modelId) {
            'grok-beta' => 'Grok Beta',
            'grok-2' => 'Grok 2',
            'grok-2-mini' => 'Grok 2 Mini',
            'grok-2-1212' => 'Grok 2 (December 2024)',
            'grok-2-vision-1212' => 'Grok 2 Vision (December 2024)',
            default => ucfirst(str_replace(['-', '_'], ' ', $modelId)),
        };
    }

    /**
     * Get model description.
     */
    protected function getModelDescription(string $modelId): string
    {
        return match ($modelId) {
            'grok-beta' => 'xAI\'s flagship conversational AI model (beta version)',
            'grok-2' => 'Advanced reasoning and conversation model',
            'grok-2-mini' => 'Smaller, faster version of Grok 2',
            'grok-2-1212' => 'Latest version of Grok 2 with improved capabilities',
            'grok-2-vision-1212' => 'Grok 2 with vision capabilities for image understanding',
            default => "xAI model: {$modelId}",
        };
    }

    /**
     * Get model capabilities.
     */
    protected function getModelCapabilities(string $modelId): array
    {
        $baseCapabilities = ['chat', 'function_calling', 'streaming'];

        return match ($modelId) {
            'grok-2-vision-1212' => array_merge($baseCapabilities, ['vision']),
            default => $baseCapabilities,
        };
    }

    /**
     * Get the cache key for models.
     */
    protected function getModelsCacheKey(): string
    {
        return "ai_models_{$this->providerName}";
    }

    /**
     * Get the cache key for last sync time.
     */
    protected function getLastSyncCacheKey(): string
    {
        return "ai_models_last_sync_{$this->providerName}";
    }

    /**
     * Clear the models cache.
     */
    public function clearModelsCache(): void
    {
        Cache::forget($this->getModelsCacheKey());
        Cache::forget($this->getLastSyncCacheKey());
    }

    /**
     * Refresh the models cache.
     */
    public function refreshModelsCache(): array
    {
        $this->clearModelsCache();

        return $this->getAvailableModels(true)->toArray();
    }

    /**
     * Actually get available models from the provider (required by abstract class).
     */
    protected function doGetAvailableModels(): array
    {
        return $this->getAvailableModels()->toArray();
    }
}
