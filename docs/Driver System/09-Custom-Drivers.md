# Creating Custom Drivers

## Overview

This guide walks you through creating a custom AI provider driver for the JTD Laravel AI package. We'll use a hypothetical "CustomAI" provider as an example.

## Step 1: Create the Driver Class

Create your driver class extending `AbstractAIProvider`:

```php
<?php

namespace App\AI\Drivers;

use Carbon\Carbon;
use JTD\LaravelAI\Contracts\AIProviderInterface;
use JTD\LaravelAI\Drivers\AbstractAIProvider;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Models\AIResponse;
use JTD\LaravelAI\Models\TokenUsage;
use Illuminate\Support\Collection;
use Illuminate\Http\Client\Factory as HttpClient;

class CustomAIDriver extends AbstractAIProvider implements AIProviderInterface
{
    protected string $name = 'customai';
    protected string $version = '1.0.0';
    protected HttpClient $http;

    public function __construct(array $config)
    {
        parent::__construct($config);
        
        $this->validateConfiguration($config);
        $this->http = app(HttpClient::class);
    }

    protected function validateConfiguration(array $config): void
    {
        $required = ['api_key', 'base_url'];
        
        foreach ($required as $key) {
            if (empty($config[$key])) {
                throw new \InvalidArgumentException("Missing required config: {$key}");
            }
        }
    }
}
```

## Step 2: Implement Core Messaging Methods

### Basic Message Sending

```php
public function sendMessage(AIMessage $message, array $options = []): AIResponse
{
    try {
        $response = $this->http
            ->withHeaders($this->getHeaders())
            ->timeout($this->config['timeout'] ?? 30)
            ->post($this->config['base_url'] . '/chat/completions', [
                'model' => $options['model'] ?? $this->getDefaultModel(),
                'messages' => $this->formatMessages([$message]),
                'temperature' => $options['temperature'] ?? 0.7,
                'max_tokens' => $options['max_tokens'] ?? null,
            ]);

        if (!$response->successful()) {
            $this->handleApiError($response);
        }

        return $this->parseResponse($response->json());

    } catch (\Exception $e) {
        $this->handleException($e);
    }
}

public function sendMessages(array $messages, array $options = []): AIResponse
{
    try {
        $response = $this->http
            ->withHeaders($this->getHeaders())
            ->timeout($this->config['timeout'] ?? 30)
            ->post($this->config['base_url'] . '/chat/completions', [
                'model' => $options['model'] ?? $this->getDefaultModel(),
                'messages' => $this->formatMessages($messages),
                'temperature' => $options['temperature'] ?? 0.7,
                'max_tokens' => $options['max_tokens'] ?? null,
            ]);

        if (!$response->successful()) {
            $this->handleApiError($response);
        }

        return $this->parseResponse($response->json());

    } catch (\Exception $e) {
        $this->handleException($e);
    }
}
```

### Helper Methods

```php
protected function getHeaders(): array
{
    return [
        'Authorization' => 'Bearer ' . $this->config['api_key'],
        'Content-Type' => 'application/json',
        'User-Agent' => 'JTD-Laravel-AI/1.0',
    ];
}

protected function formatMessages(array $messages): array
{
    return collect($messages)->map(function (AIMessage $message) {
        return [
            'role' => $message->role,
            'content' => $message->content,
        ];
    })->toArray();
}

protected function parseResponse(array $data): AIResponse
{
    $choice = $data['choices'][0] ?? null;
    
    if (!$choice) {
        throw new \JTD\LaravelAI\Exceptions\ProviderException('No response choices available');
    }

    $usage = new TokenUsage(
        promptTokens: $data['usage']['prompt_tokens'] ?? 0,
        completionTokens: $data['usage']['completion_tokens'] ?? 0,
        totalTokens: $data['usage']['total_tokens'] ?? 0
    );

    return new AIResponse(
        content: $choice['message']['content'] ?? '',
        role: $choice['message']['role'] ?? 'assistant',
        model: $data['model'] ?? 'unknown',
        usage: $usage,
        finishReason: $choice['finish_reason'] ?? null,
        rawResponse: $data
    );
}
```

## Step 3: Implement Streaming Support

```php
public function sendStreamingMessage(AIMessage $message, array $options = []): \Generator
{
    return $this->sendStreamingMessages([$message], $options);
}

public function sendStreamingMessages(array $messages, array $options = []): \Generator
{
    try {
        $response = $this->http
            ->withHeaders($this->getHeaders())
            ->timeout($this->config['timeout'] ?? 30)
            ->post($this->config['base_url'] . '/chat/completions', [
                'model' => $options['model'] ?? $this->getDefaultModel(),
                'messages' => $this->formatMessages($messages),
                'temperature' => $options['temperature'] ?? 0.7,
                'max_tokens' => $options['max_tokens'] ?? null,
                'stream' => true,
            ]);

        foreach ($response->stream() as $chunk) {
            if (str_starts_with($chunk, 'data: ')) {
                $data = substr($chunk, 6);
                
                if ($data === '[DONE]') {
                    break;
                }

                $json = json_decode($data, true);
                if ($json && isset($json['choices'][0]['delta']['content'])) {
                    yield new AIResponse(
                        content: $json['choices'][0]['delta']['content'],
                        role: 'assistant',
                        model: $json['model'] ?? 'unknown',
                        usage: null, // Usage not available in streaming
                        finishReason: $json['choices'][0]['finish_reason'] ?? null,
                        rawResponse: $json
                    );
                }
            }
        }

    } catch (\Exception $e) {
        $this->handleException($e);
    }
}

public function supportsStreaming(): bool
{
    return true;
}
```

## Step 4: Implement Model Management

```php
public function getAvailableModels(bool $refresh = false): Collection
{
    $cacheKey = $this->getModelsCacheKey();
    
    if (!$refresh && \Cache::has($cacheKey)) {
        return collect(\Cache::get($cacheKey));
    }

    try {
        $response = $this->http
            ->withHeaders($this->getHeaders())
            ->get($this->config['base_url'] . '/models');

        if (!$response->successful()) {
            $this->handleApiError($response);
        }

        $models = collect($response->json('data'))->map(function ($model) {
            return [
                'id' => $model['id'],
                'name' => $model['name'] ?? $model['id'],
                'type' => 'chat',
                'capabilities' => $this->getModelCapabilities($model['id']),
                'context_length' => $model['context_length'] ?? 4096,
                'owned_by' => $model['owned_by'] ?? 'customai',
            ];
        });

        \Cache::put($cacheKey, $models->toArray(), now()->addHours(24));

        return $models;

    } catch (\Exception $e) {
        $this->handleException($e);
    }
}

protected function getModelCapabilities(string $modelId): array
{
    $capabilities = ['chat'];
    
    // Add capabilities based on model ID patterns
    if (str_contains($modelId, 'vision')) {
        $capabilities[] = 'vision';
    }
    
    if (str_contains($modelId, 'function')) {
        $capabilities[] = 'function_calling';
    }
    
    return $capabilities;
}
```

## Step 5: Implement Sync Methods

```php
public function syncModels(bool $forceRefresh = false): array
{
    try {
        if (!$forceRefresh && !$this->shouldRefreshModels()) {
            return [
                'status' => 'skipped',
                'reason' => 'cache_valid',
                'last_sync' => $this->getLastSyncTime(),
            ];
        }

        $models = $this->getAvailableModels(true);
        
        // Store in cache
        $cacheKey = $this->getModelsCacheKey();
        \Cache::put($cacheKey, $models->toArray(), now()->addHours(24));
        \Cache::put($cacheKey . ':last_sync', now(), now()->addDays(7));

        // Generate statistics
        $stats = $this->generateModelStatistics($models);

        return [
            'status' => 'success',
            'models_synced' => $models->count(),
            'statistics' => $stats,
            'cached_until' => now()->addHours(24),
            'last_sync' => now(),
        ];

    } catch (\Exception $e) {
        throw new \JTD\LaravelAI\Exceptions\ProviderException(
            'Failed to sync models: ' . $e->getMessage(),
            0,
            $e
        );
    }
}

public function hasValidCredentials(): bool
{
    try {
        $response = $this->http
            ->withHeaders($this->getHeaders())
            ->get($this->config['base_url'] . '/models');
        
        return $response->successful();
    } catch (\Exception $e) {
        return false;
    }
}

public function getLastSyncTime(): ?Carbon
{
    $cacheKey = $this->getModelsCacheKey() . ':last_sync';
    return \Cache::get($cacheKey);
}

public function getSyncableModels(): array
{
    try {
        $response = $this->http
            ->withHeaders($this->getHeaders())
            ->get($this->config['base_url'] . '/models');
        
        return collect($response->json('data'))
            ->map(fn($model) => [
                'id' => $model['id'],
                'name' => $model['name'] ?? $model['id'],
                'owned_by' => $model['owned_by'] ?? 'customai',
                'created' => $model['created'] ?? time(),
            ])
            ->toArray();
    } catch (\Exception $e) {
        throw new \JTD\LaravelAI\Exceptions\ProviderException(
            'Failed to get syncable models: ' . $e->getMessage(),
            0,
            $e
        );
    }
}

protected function shouldRefreshModels(): bool
{
    $lastSync = $this->getLastSyncTime();
    return !$lastSync || $lastSync->diffInHours(now()) >= 12;
}

protected function getModelsCacheKey(): string
{
    return "laravel-ai:{$this->getName()}:models";
}

protected function generateModelStatistics(Collection $models): array
{
    return [
        'total_models' => $models->count(),
        'chat_models' => $models->where('type', 'chat')->count(),
        'vision_models' => $models->filter(fn($m) => in_array('vision', $m['capabilities']))->count(),
        'function_calling_models' => $models->filter(fn($m) => in_array('function_calling', $m['capabilities']))->count(),
        'updated_at' => now()->toISOString(),
    ];
}
```

## Step 6: Implement Additional Interface Methods

```php
public function calculateCost(TokenUsage $usage, string $modelId): float
{
    // Implement your provider's pricing logic
    $pricing = $this->getModelPricing($modelId);
    
    $promptCost = ($usage->promptTokens / 1000) * $pricing['prompt'];
    $completionCost = ($usage->completionTokens / 1000) * $pricing['completion'];
    
    return $promptCost + $completionCost;
}

public function validateCredentials(): array
{
    try {
        $response = $this->http
            ->withHeaders($this->getHeaders())
            ->get($this->config['base_url'] . '/models');

        if ($response->successful()) {
            return [
                'status' => 'valid',
                'message' => 'Credentials are valid',
            ];
        } else {
            return [
                'status' => 'invalid',
                'message' => 'Invalid credentials',
                'error' => $response->json('error.message') ?? 'Unknown error',
            ];
        }
    } catch (\Exception $e) {
        return [
            'status' => 'error',
            'message' => 'Failed to validate credentials',
            'error' => $e->getMessage(),
        ];
    }
}

public function getCapabilities(): array
{
    return [
        'streaming' => true,
        'function_calling' => false,
        'vision' => false,
        'embeddings' => false,
    ];
}

public function getConfig(): array
{
    $config = $this->config;
    
    // Mask sensitive data
    if (isset($config['api_key'])) {
        $config['api_key'] = 'sk-***' . substr($config['api_key'], -4);
    }
    
    return $config;
}

public function getName(): string
{
    return $this->name;
}

public function supportsFunctionCalling(): bool
{
    return false; // Implement based on your provider's capabilities
}

public function supportsVision(): bool
{
    return false; // Implement based on your provider's capabilities
}

public function getVersion(): string
{
    return $this->version;
}
```

## Step 7: Register Your Driver

Add your driver to the `DriverManager`:

```php
// In a service provider
use App\AI\Drivers\CustomAIDriver;

public function boot()
{
    $this->app->make(\JTD\LaravelAI\Services\DriverManager::class)
        ->extend('customai', function ($config) {
            return new CustomAIDriver($config);
        });
}
```

## Step 8: Add Configuration

Add your provider configuration to `config/ai.php`:

```php
'providers' => [
    // ... existing providers
    
    'customai' => [
        'driver' => 'customai',
        'api_key' => env('CUSTOMAI_API_KEY'),
        'base_url' => env('CUSTOMAI_BASE_URL', 'https://api.customai.com/v1'),
        'timeout' => (int) env('CUSTOMAI_TIMEOUT', 30),
        'retry_attempts' => (int) env('CUSTOMAI_RETRY_ATTEMPTS', 3),
    ],
],
```

## Step 9: Create Tests

Create comprehensive tests for your driver:

```php
<?php

namespace Tests\Unit\Drivers;

use Tests\TestCase;
use App\AI\Drivers\CustomAIDriver;
use JTD\LaravelAI\Models\AIMessage;

class CustomAIDriverTest extends TestCase
{
    protected CustomAIDriver $driver;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->driver = new CustomAIDriver([
            'api_key' => 'test-key',
            'base_url' => 'https://api.customai.com/v1',
        ]);
    }

    public function test_sends_message_successfully(): void
    {
        // Mock HTTP responses and test your driver
        // Follow the patterns from OpenAI driver tests
    }

    public function test_syncs_models_successfully(): void
    {
        // Test model synchronization
    }

    public function test_validates_credentials(): void
    {
        // Test credential validation
    }
}
```

## Best Practices

### Error Handling
- Map provider-specific errors to package exceptions
- Implement retry logic for transient failures
- Provide meaningful error messages

### Performance
- Cache expensive operations (models, pricing)
- Use appropriate timeouts
- Implement connection pooling if needed

### Security
- Validate all configuration
- Mask sensitive data in logs
- Use secure HTTP headers

### Testing
- Create comprehensive unit tests
- Test error scenarios
- Use mocks for external API calls

## Related Documentation

- **[Driver Interface](03-Interface.md)**: Complete interface specification
- **[OpenAI Driver](07-OpenAI-Driver.md)**: Reference implementation
- **[Driver Traits](10-Driver-Traits.md)**: Reusable trait system
- **[Testing Strategy](12-Testing.md)**: Testing guidelines
