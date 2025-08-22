# Performance Optimization

## Overview

This guide covers performance optimization strategies for AI drivers, including caching, connection management, resource optimization, and monitoring. Proper performance optimization ensures efficient resource usage and responsive applications.

## Caching Strategies

### Model Caching

#### Intelligent Model Caching
Cache model information with appropriate TTL to reduce API calls.

```php
public function getAvailableModels(bool $refresh = false): Collection
{
    $cacheKey = $this->getModelsCacheKey();
    
    if (!$refresh && \Cache::has($cacheKey)) {
        $cached = \Cache::get($cacheKey);
        
        // Check cache age
        $cacheAge = now()->diffInHours($cached['cached_at'] ?? now());
        if ($cacheAge < 12) {
            return collect($cached['models']);
        }
    }
    
    $models = $this->fetchModelsFromApi();
    
    \Cache::put($cacheKey, [
        'models' => $models->toArray(),
        'cached_at' => now(),
    ], now()->addHours(24));
    
    return $models;
}
```

#### Layered Caching
Implement multiple cache layers for different data types.

```php
class CacheManager
{
    // L1: In-memory cache (fastest)
    protected array $memoryCache = [];
    
    // L2: Redis/Memcached (fast)
    protected string $distributedCachePrefix = 'laravel-ai';
    
    // L3: Database cache (slower but persistent)
    protected string $databaseCacheTable = 'ai_cache';
    
    public function get(string $key, callable $callback = null, int $ttl = 3600)
    {
        // Try L1 cache first
        if (isset($this->memoryCache[$key])) {
            return $this->memoryCache[$key];
        }
        
        // Try L2 cache
        $distributedKey = "{$this->distributedCachePrefix}:{$key}";
        if (\Cache::has($distributedKey)) {
            $value = \Cache::get($distributedKey);
            $this->memoryCache[$key] = $value;
            return $value;
        }
        
        // Try L3 cache
        $dbValue = $this->getFromDatabase($key);
        if ($dbValue !== null) {
            $this->memoryCache[$key] = $dbValue;
            \Cache::put($distributedKey, $dbValue, $ttl);
            return $dbValue;
        }
        
        // Generate value if callback provided
        if ($callback) {
            $value = $callback();
            $this->put($key, $value, $ttl);
            return $value;
        }
        
        return null;
    }
}
```

### Response Caching

#### Content-Based Caching
Cache responses based on message content and parameters.

```php
protected function getCacheKey(AIMessage $message, array $options): string
{
    $cacheData = [
        'provider' => $this->getName(),
        'model' => $options['model'] ?? $this->getDefaultModel(),
        'message_hash' => hash('sha256', $message->content),
        'temperature' => $options['temperature'] ?? 0.7,
        'max_tokens' => $options['max_tokens'] ?? null,
    ];
    
    return 'ai-response:' . hash('sha256', serialize($cacheData));
}

public function sendMessage(AIMessage $message, array $options = []): AIResponse
{
    // Check if response caching is enabled and appropriate
    if ($this->shouldCacheResponse($message, $options)) {
        $cacheKey = $this->getCacheKey($message, $options);
        
        if (\Cache::has($cacheKey)) {
            return \Cache::get($cacheKey);
        }
    }
    
    $response = $this->performSendMessage($message, $options);
    
    // Cache the response if appropriate
    if ($this->shouldCacheResponse($message, $options)) {
        \Cache::put($cacheKey, $response, now()->addHours(1));
    }
    
    return $response;
}

protected function shouldCacheResponse(AIMessage $message, array $options): bool
{
    // Don't cache if temperature is high (non-deterministic)
    if (($options['temperature'] ?? 0.7) > 0.5) {
        return false;
    }
    
    // Don't cache streaming responses
    if ($options['stream'] ?? false) {
        return false;
    }
    
    // Don't cache very long messages (likely unique)
    if (strlen($message->content) > 1000) {
        return false;
    }
    
    return $this->config['cache_responses'] ?? false;
}
```

## Connection Management

### HTTP Client Optimization

#### Connection Pooling
Reuse HTTP connections to reduce overhead.

```php
class OptimizedHttpClient
{
    protected static array $clients = [];
    
    public static function getClient(string $provider, array $config): HttpClient
    {
        $clientKey = $provider . ':' . hash('sha256', serialize($config));
        
        if (!isset(self::$clients[$clientKey])) {
            self::$clients[$clientKey] = Http::withOptions([
                'timeout' => $config['timeout'] ?? 30,
                'connect_timeout' => $config['connect_timeout'] ?? 10,
                'pool' => true, // Enable connection pooling
                'max_connections' => $config['max_connections'] ?? 10,
                'max_requests_per_connection' => 100,
            ]);
        }
        
        return self::$clients[$clientKey];
    }
}
```

#### Request Batching
Batch multiple requests when possible.

```php
public function sendMultipleMessages(array $messages, array $options = []): array
{
    // Group messages by model for batching
    $messageGroups = collect($messages)->groupBy(function ($item) {
        return $item['options']['model'] ?? $this->getDefaultModel();
    });
    
    $responses = [];
    
    foreach ($messageGroups as $model => $modelMessages) {
        $batchResponse = $this->sendBatchRequest($modelMessages->toArray(), [
            'model' => $model,
            ...$options
        ]);
        
        $responses = array_merge($responses, $batchResponse);
    }
    
    return $responses;
}

protected function sendBatchRequest(array $messages, array $options): array
{
    // Implementation depends on provider's batch API support
    if ($this->supportsBatchRequests()) {
        return $this->performBatchRequest($messages, $options);
    }
    
    // Fallback to individual requests with concurrency
    return $this->sendConcurrentRequests($messages, $options);
}
```

### Concurrent Processing

#### Async Request Processing
Process multiple requests concurrently.

```php
use GuzzleHttp\Promise;

public function sendConcurrentRequests(array $messages, array $options = []): array
{
    $promises = [];
    
    foreach ($messages as $index => $message) {
        $promises[$index] = $this->getHttpClient()->async()->post(
            $this->getEndpoint(),
            $this->prepareRequestData($message, $options)
        );
    }
    
    // Wait for all requests to complete
    $responses = Promise\settle($promises)->wait();
    
    $results = [];
    foreach ($responses as $index => $response) {
        if ($response['state'] === 'fulfilled') {
            $results[$index] = $this->parseResponse($response['value']);
        } else {
            $results[$index] = $this->handleRequestError($response['reason']);
        }
    }
    
    return $results;
}
```

## Memory Optimization

### Streaming Optimization

#### Memory-Efficient Streaming
Handle streaming responses without accumulating in memory.

```php
public function sendStreamingMessage(AIMessage $message, array $options = []): \Generator
{
    $stream = $this->client->chat()->createStreamed([
        'model' => $options['model'] ?? $this->getDefaultModel(),
        'messages' => $this->formatMessages([$message]),
        'stream' => true,
    ]);
    
    $buffer = '';
    $chunkCount = 0;
    
    foreach ($stream as $response) {
        $chunk = $this->parseStreamChunk($response);
        
        if ($chunk) {
            yield $chunk;
            
            // Periodic memory cleanup
            if (++$chunkCount % 100 === 0) {
                gc_collect_cycles();
            }
        }
        
        // Clear response from memory
        unset($response);
    }
    
    // Final cleanup
    unset($stream, $buffer);
    gc_collect_cycles();
}
```

#### Large Response Handling
Handle large responses efficiently.

```php
protected function handleLargeResponse($response): AIResponse
{
    // For very large responses, consider streaming to temporary file
    if ($this->isLargeResponse($response)) {
        return $this->handleLargeResponseWithTempFile($response);
    }
    
    return $this->parseResponse($response);
}

protected function isLargeResponse($response): bool
{
    $contentLength = $response->header('Content-Length');
    return $contentLength && $contentLength > 1024 * 1024; // 1MB threshold
}

protected function handleLargeResponseWithTempFile($response): AIResponse
{
    $tempFile = tempnam(sys_get_temp_dir(), 'ai_response_');
    
    try {
        file_put_contents($tempFile, $response->body());
        
        // Process file in chunks
        $handle = fopen($tempFile, 'r');
        $content = '';
        
        while (!feof($handle)) {
            $chunk = fread($handle, 8192);
            $content .= $this->processChunk($chunk);
        }
        
        fclose($handle);
        
        return $this->createResponseFromContent($content);
        
    } finally {
        if (file_exists($tempFile)) {
            unlink($tempFile);
        }
    }
}
```

## Database Optimization

### Query Optimization

#### Efficient Model Storage
Optimize database queries for model data.

```php
class ModelRepository
{
    public function getModelsForProvider(string $provider): Collection
    {
        return \Cache::remember(
            "models:{$provider}",
            now()->addHours(24),
            fn() => AIProviderModel::where('provider', $provider)
                ->where('is_active', true)
                ->select(['id', 'model_id', 'name', 'capabilities', 'context_length'])
                ->orderBy('name')
                ->get()
        );
    }
    
    public function bulkUpdateModels(string $provider, array $models): void
    {
        \DB::transaction(function () use ($provider, $models) {
            // Disable existing models
            AIProviderModel::where('provider', $provider)
                ->update(['is_active' => false]);
            
            // Batch insert/update new models
            $chunks = array_chunk($models, 100);
            
            foreach ($chunks as $chunk) {
                AIProviderModel::upsert(
                    $chunk,
                    ['provider', 'model_id'],
                    ['name', 'capabilities', 'context_length', 'is_active', 'updated_at']
                );
            }
        });
        
        // Clear cache
        \Cache::forget("models:{$provider}");
    }
}
```

### Index Optimization

#### Database Indexes
Ensure proper database indexes for performance.

```sql
-- Migration for AI provider models table
CREATE TABLE ai_provider_models (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    provider VARCHAR(50) NOT NULL,
    model_id VARCHAR(100) NOT NULL,
    name VARCHAR(200) NOT NULL,
    capabilities JSON,
    context_length INT UNSIGNED,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    -- Performance indexes
    INDEX idx_provider_active (provider, is_active),
    INDEX idx_model_lookup (provider, model_id),
    INDEX idx_capabilities (capabilities),
    
    UNIQUE KEY unique_provider_model (provider, model_id)
);
```

## Monitoring and Metrics

### Performance Monitoring

#### Response Time Tracking
Monitor API response times and performance.

```php
trait TracksPerformance
{
    protected function trackOperation(string $operation, callable $callback)
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage();
        
        try {
            $result = $callback();
            
            $this->recordMetrics($operation, [
                'status' => 'success',
                'duration_ms' => (microtime(true) - $startTime) * 1000,
                'memory_used_bytes' => memory_get_usage() - $startMemory,
                'peak_memory_bytes' => memory_get_peak_usage(),
            ]);
            
            return $result;
            
        } catch (\Exception $e) {
            $this->recordMetrics($operation, [
                'status' => 'error',
                'duration_ms' => (microtime(true) - $startTime) * 1000,
                'memory_used_bytes' => memory_get_usage() - $startMemory,
                'error' => $e->getMessage(),
            ]);
            
            throw $e;
        }
    }
    
    protected function recordMetrics(string $operation, array $metrics): void
    {
        // Log metrics
        \Log::info('AI Performance Metric', [
            'provider' => $this->getName(),
            'operation' => $operation,
            'metrics' => $metrics,
        ]);
        
        // Send to monitoring service (e.g., Prometheus, DataDog)
        if (class_exists('\Prometheus\CollectorRegistry')) {
            $this->recordPrometheusMetrics($operation, $metrics);
        }
        
        // Fire event for custom monitoring
        event(new \JTD\LaravelAI\Events\PerformanceMetricRecorded([
            'provider' => $this->getName(),
            'operation' => $operation,
            'metrics' => $metrics,
        ]));
    }
}
```

#### Resource Usage Monitoring
Monitor memory and CPU usage.

```php
class ResourceMonitor
{
    protected array $thresholds = [
        'memory_mb' => 512,
        'execution_time_seconds' => 30,
        'api_calls_per_minute' => 100,
    ];
    
    public function checkResourceUsage(): array
    {
        $usage = [
            'memory_mb' => memory_get_usage(true) / 1024 / 1024,
            'peak_memory_mb' => memory_get_peak_usage(true) / 1024 / 1024,
            'execution_time' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'],
        ];
        
        $warnings = [];
        
        foreach ($this->thresholds as $metric => $threshold) {
            if (isset($usage[$metric]) && $usage[$metric] > $threshold) {
                $warnings[] = "High {$metric}: {$usage[$metric]} (threshold: {$threshold})";
            }
        }
        
        if (!empty($warnings)) {
            \Log::warning('High resource usage detected', [
                'usage' => $usage,
                'warnings' => $warnings,
            ]);
        }
        
        return $usage;
    }
}
```

## Configuration Optimization

### Environment-Specific Settings

#### Production Optimizations
Optimize settings for production environments.

```php
// config/ai.php
'providers' => [
    'openai' => [
        // Connection settings
        'timeout' => env('OPENAI_TIMEOUT', app()->environment('production') ? 30 : 60),
        'connect_timeout' => env('OPENAI_CONNECT_TIMEOUT', 10),
        'retry_attempts' => env('OPENAI_RETRY_ATTEMPTS', app()->environment('production') ? 3 : 1),
        
        // Performance settings
        'max_connections' => env('OPENAI_MAX_CONNECTIONS', 10),
        'connection_pool_size' => env('OPENAI_POOL_SIZE', 5),
        
        // Caching settings
        'cache_models' => env('OPENAI_CACHE_MODELS', true),
        'cache_responses' => env('OPENAI_CACHE_RESPONSES', app()->environment('production')),
        'cache_ttl_hours' => env('OPENAI_CACHE_TTL', 24),
        
        // Logging settings
        'logging' => [
            'enabled' => env('AI_LOGGING_ENABLED', true),
            'level' => env('AI_LOG_LEVEL', app()->environment('production') ? 'warning' : 'debug'),
            'include_content' => env('AI_LOG_INCLUDE_CONTENT', !app()->environment('production')),
        ],
    ],
],

// Performance monitoring
'monitoring' => [
    'enabled' => env('AI_MONITORING_ENABLED', app()->environment('production')),
    'metrics_driver' => env('AI_METRICS_DRIVER', 'log'), // log, prometheus, datadog
    'slow_query_threshold_ms' => env('AI_SLOW_QUERY_THRESHOLD', 5000),
    'memory_threshold_mb' => env('AI_MEMORY_THRESHOLD', 512),
],
```

## Performance Testing

### Benchmarking

#### Load Testing
Test performance under various load conditions.

```php
<?php

namespace Tests\Performance;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;
use JTD\LaravelAI\Facades\AI;
use JTD\LaravelAI\Models\AIMessage;

#[Group('performance')]
class LoadTest extends TestCase
{
    #[Test]
    public function it_handles_concurrent_requests(): void
    {
        config(['ai.default' => 'mock']);
        
        $concurrency = 50;
        $requestsPerThread = 10;
        
        $startTime = microtime(true);
        $promises = [];
        
        for ($i = 0; $i < $concurrency; $i++) {
            $promises[] = $this->runConcurrentRequests($requestsPerThread);
        }
        
        // Wait for all to complete
        Promise\all($promises)->wait();
        
        $totalTime = microtime(true) - $startTime;
        $totalRequests = $concurrency * $requestsPerThread;
        $requestsPerSecond = $totalRequests / $totalTime;
        
        $this->assertGreaterThan(100, $requestsPerSecond);
        $this->assertLessThan(30, $totalTime); // Should complete within 30 seconds
    }
    
    #[Test]
    public function it_maintains_memory_efficiency(): void
    {
        config(['ai.default' => 'mock']);
        
        $initialMemory = memory_get_usage();
        
        // Send many requests
        for ($i = 0; $i < 1000; $i++) {
            AI::sendMessage(AIMessage::user("Test message {$i}"));
            
            // Force garbage collection every 100 requests
            if ($i % 100 === 0) {
                gc_collect_cycles();
            }
        }
        
        $finalMemory = memory_get_usage();
        $memoryIncrease = $finalMemory - $initialMemory;
        
        // Memory increase should be reasonable (less than 50MB)
        $this->assertLessThan(50 * 1024 * 1024, $memoryIncrease);
    }
    
    protected function runConcurrentRequests(int $count): Promise
    {
        return \React\Async\async(function () use ($count) {
            for ($i = 0; $i < $count; $i++) {
                AI::sendMessage(AIMessage::user("Concurrent test {$i}"));
            }
        })();
    }
}
```

## Best Practices Summary

### Caching
- Cache model information for 12-24 hours
- Use layered caching (memory → Redis → database)
- Implement intelligent cache invalidation
- Cache responses only for deterministic requests

### Connection Management
- Use connection pooling for HTTP clients
- Implement request batching when possible
- Process requests concurrently when appropriate
- Set reasonable timeouts and retry policies

### Memory Management
- Use generators for streaming responses
- Clean up large objects promptly
- Implement periodic garbage collection
- Handle large responses with temporary files

### Monitoring
- Track response times and resource usage
- Set up alerts for performance degradation
- Monitor cache hit rates and effectiveness
- Log performance metrics for analysis

### Configuration
- Use environment-specific optimizations
- Configure appropriate timeouts and limits
- Enable caching in production
- Optimize logging levels for performance

## Related Documentation

- **[Configuration System](02-Configuration.md)**: Performance-related configuration
- **[Best Practices](14-Best-Practices.md)**: General development best practices
- **[Testing Strategy](12-Testing.md)**: Performance testing approaches
- **[Troubleshooting](16-Troubleshooting.md)**: Performance troubleshooting
