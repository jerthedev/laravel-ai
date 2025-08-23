# Sync Implementation Guide

## Overview

This guide explains how to implement model synchronization functionality in AI provider drivers. The sync system provides automated model discovery, caching, and statistics tracking across all providers.

## Core Sync Methods

Every driver must implement these four sync-related methods from `AIProviderInterface`:

### 1. syncModels(bool $forceRefresh = false): array

The main synchronization method that fetches models from the provider API and stores them locally.

```php
public function syncModels(bool $forceRefresh = false): array
{
    try {
        \Log::info('Starting models synchronization', [
            'provider' => $this->getName(),
            'force_refresh' => $forceRefresh,
        ]);

        // Check if we need to refresh
        if (!$forceRefresh && !$this->shouldRefreshModels()) {
            \Log::info('Models cache is still valid, skipping sync');
            return [
                'status' => 'skipped',
                'reason' => 'cache_valid',
                'last_sync' => $this->getLastSyncTime(),
            ];
        }

        // Fetch models from provider API
        $models = $this->getAvailableModels(true);

        // Store in cache with TTL
        $cacheKey = $this->getModelsCacheKey();
        \Cache::put($cacheKey, $models->toArray(), now()->addHours(24));

        // Store last sync timestamp
        \Cache::put($cacheKey . ':last_sync', now(), now()->addDays(7));

        // Generate and store statistics
        $stats = $this->storeModelStatistics($models->toArray());

        \Log::info('Models synchronization completed', [
            'provider' => $this->getName(),
            'models_count' => count($models),
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
        \Log::error('Models synchronization failed', [
            'provider' => $this->getName(),
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        // Store failure information for monitoring
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
```

### 2. hasValidCredentials(): bool

Quick check to determine if the provider has valid credentials configured.

```php
public function hasValidCredentials(): bool
{
    try {
        $result = $this->validateCredentials();
        return $result['status'] === 'valid';
    } catch (\Exception $e) {
        return false;
    }
}
```

### 3. getLastSyncTime(): ?\Carbon\Carbon

Returns the timestamp of the last successful synchronization.

```php
public function getLastSyncTime(): ?\Carbon\Carbon
{
    $cacheKey = $this->getModelsCacheKey() . ':last_sync';
    return \Cache::get($cacheKey);
}
```

### 4. getSyncableModels(): array

Returns a preview of models available for synchronization without actually syncing.

```php
public function getSyncableModels(): array
{
    try {
        // Lightweight preview - just get the model list
        $response = $this->executeWithRetry(function () {
            return $this->client->models()->list();
        });

        $models = [];
        foreach ($response->data as $model) {
            if ($this->isValidModel($model)) {
                $models[] = [
                    'id' => $model->id,
                    'name' => $this->getDisplayName($model->id),
                    'owned_by' => $model->ownedBy ?? $this->getName(),
                    'created' => $model->created ?? null,
                ];
            }
        }

        return $models;
    } catch (\Exception $e) {
        $this->handleApiError($e);
    }
}
```

## Helper Methods

### shouldRefreshModels(): bool

Determines if models should be refreshed based on cache age.

```php
protected function shouldRefreshModels(): bool
{
    $lastSync = $this->getLastSyncTime();
    
    // Refresh if no last sync time or if it's been more than 12 hours
    return !$lastSync || $lastSync->diffInHours(now()) >= 12;
}
```

### getModelsCacheKey(): string

Returns the cache key for storing models.

```php
protected function getModelsCacheKey(): string
{
    return "laravel-ai:{$this->getName()}:models";
}
```

### storeModelStatistics(array $models): array

Generates and stores statistics about the synchronized models.

```php
protected function storeModelStatistics(array $models): array
{
    $stats = [
        'total_models' => count($models),
        'updated_at' => now()->toISOString(),
    ];

    // Provider-specific statistics
    foreach ($models as $model) {
        $modelId = $model['id'];
        $capabilities = $model['capabilities'] ?? [];

        // Count by model families (customize for your provider)
        if (str_contains($modelId, 'gpt-4o')) {
            $stats['gpt_4o_models'] = ($stats['gpt_4o_models'] ?? 0) + 1;
        } elseif (str_contains($modelId, 'gpt-4')) {
            $stats['gpt_4_models'] = ($stats['gpt_4_models'] ?? 0) + 1;
        } elseif (str_contains($modelId, 'gpt-3.5')) {
            $stats['gpt_3_5_models'] = ($stats['gpt_3_5_models'] ?? 0) + 1;
        }

        // Count by capabilities
        if (in_array('function_calling', $capabilities)) {
            $stats['function_calling_models'] = ($stats['function_calling_models'] ?? 0) + 1;
        }

        if (in_array('vision', $capabilities)) {
            $stats['vision_models'] = ($stats['vision_models'] ?? 0) + 1;
        }
    }

    // Store statistics in cache
    \Cache::put($this->getModelsCacheKey() . ':stats', $stats, now()->addDays(7));

    return $stats;
}
```

## Provider-Specific Examples

### OpenAI Implementation

```php
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

    \Cache::put($this->getModelsCacheKey() . ':stats', $stats, now()->addDays(7));
    return $stats;
}
```

### Gemini Implementation

```php
protected function storeModelStatistics(array $models): array
{
    $stats = [
        'total_models' => count($models),
        'gemini_pro_models' => 0,
        'gemini_flash_models' => 0,
        'multimodal_models' => 0,
        'safety_filtered_models' => 0,
        'updated_at' => now()->toISOString(),
    ];

    foreach ($models as $model) {
        $modelId = $model['id'];
        $capabilities = $model['capabilities'] ?? [];

        // Count model types
        if (str_contains($modelId, 'gemini-pro')) {
            $stats['gemini_pro_models']++;
        } elseif (str_contains($modelId, 'gemini-flash')) {
            $stats['gemini_flash_models']++;
        }

        // Count capabilities
        if (in_array('multimodal', $capabilities)) {
            $stats['multimodal_models']++;
        }

        if (in_array('safety_filtering', $capabilities)) {
            $stats['safety_filtered_models']++;
        }
    }

    \Cache::put($this->getModelsCacheKey() . ':stats', $stats, now()->addDays(7));
    return $stats;
}
```

### xAI Implementation

```php
protected function storeModelStatistics(array $models): array
{
    $stats = [
        'total_models' => count($models),
        'grok_models' => 0,
        'reasoning_models' => 0,
        'updated_at' => now()->toISOString(),
    ];

    foreach ($models as $model) {
        $modelId = $model['id'];
        $capabilities = $model['capabilities'] ?? [];

        // Count model types
        if (str_contains($modelId, 'grok')) {
            $stats['grok_models']++;
        }

        // Count capabilities
        if (in_array('reasoning', $capabilities)) {
            $stats['reasoning_models']++;
        }
    }

    \Cache::put($this->getModelsCacheKey() . ':stats', $stats, now()->addDays(7));
    return $stats;
}
```

## Error Handling

### Exception Types

Use appropriate exceptions for different error scenarios:

```php
try {
    $models = $this->getAvailableModels(true);
} catch (\JTD\LaravelAI\Exceptions\InvalidCredentialsException $e) {
    throw $e; // Re-throw credential errors
} catch (\JTD\LaravelAI\Exceptions\RateLimitException $e) {
    // Handle rate limiting with backoff
    sleep($e->getRetryAfter());
    throw $e;
} catch (\Exception $e) {
    throw new \JTD\LaravelAI\Exceptions\ProviderException(
        'Failed to sync models: ' . $e->getMessage(),
        0,
        $e
    );
}
```

### Failure Tracking

Store failure information for monitoring:

```php
\Cache::put(
    $this->getModelsCacheKey() . ':last_failure',
    [
        'error' => $e->getMessage(),
        'error_type' => get_class($e),
        'failed_at' => now()->toISOString(),
        'retry_count' => $retryCount ?? 0,
    ],
    now()->addHours(24)
);
```

## Testing Sync Implementation

### Unit Tests

```php
public function test_syncs_models_successfully(): void
{
    $driver = $this->createMockDriver();
    $driver->shouldReceive('getAvailableModels')
        ->with(true)
        ->andReturn(collect([
            ['id' => 'model-1', 'capabilities' => ['function_calling']],
            ['id' => 'model-2', 'capabilities' => ['vision']],
        ]));

    $result = $driver->syncModels(true);

    $this->assertEquals('success', $result['status']);
    $this->assertEquals(2, $result['models_synced']);
    $this->assertArrayHasKey('statistics', $result);
}

public function test_skips_sync_when_cache_valid(): void
{
    $driver = $this->createMockDriver();
    $driver->shouldReceive('shouldRefreshModels')->andReturn(false);
    $driver->shouldReceive('getLastSyncTime')->andReturn(now()->subHours(6));

    $result = $driver->syncModels(false);

    $this->assertEquals('skipped', $result['status']);
    $this->assertEquals('cache_valid', $result['reason']);
}
```

### Integration Tests

```php
public function test_sync_integration_with_cache(): void
{
    $driver = new YourDriver($config);
    
    // First sync should fetch from API
    $result1 = $driver->syncModels(true);
    $this->assertEquals('success', $result1['status']);
    
    // Second sync should skip due to cache
    $result2 = $driver->syncModels(false);
    $this->assertEquals('skipped', $result2['status']);
    
    // Force refresh should sync again
    $result3 = $driver->syncModels(true);
    $this->assertEquals('success', $result3['status']);
}
```

## Best Practices

### Performance
- Use appropriate cache TTL (24 hours for models)
- Implement efficient API calls
- Avoid unnecessary data processing

### Reliability
- Implement proper error handling
- Store failure information for monitoring
- Use retry logic for transient failures

### Monitoring
- Log all sync operations
- Track sync statistics
- Monitor failure rates

### Caching
- Use consistent cache key patterns
- Set appropriate TTL values
- Clean up old cache entries

## Related Documentation

- **[Sync System Overview](04-Sync-System.md)**: Understanding the sync architecture
- **[Sync Commands](06-Sync-Commands.md)**: Using the ai:sync-models command
- **[Driver Interface](03-Interface.md)**: Complete interface specification
- **[OpenAI Driver](07-OpenAI-Driver.md)**: Reference sync implementation
