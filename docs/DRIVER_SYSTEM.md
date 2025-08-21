# Driver System

## Overview

JTD Laravel AI uses a driver-based architecture similar to Laravel's database system. This allows for seamless switching between AI providers while maintaining a consistent interface. The driver system is extensible, allowing you to create custom drivers for new AI providers or specialized use cases.

## Driver Architecture

### Core Components

```
AIManager
├── DriverManager
│   ├── OpenAIDriver
│   ├── XAIDriver
│   ├── GeminiDriver
│   ├── OllamaDriver
│   └── CustomDriver
├── ProviderRegistry
├── ModelManager
└── CostCalculator
```

### Driver Interface

All drivers must implement the `AIProviderInterface`:

```php
<?php

namespace JTD\LaravelAI\Contracts;

use Illuminate\Support\Collection;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Models\AIResponse;
use JTD\LaravelAI\Models\TokenUsage;

interface AIProviderInterface
{
    /**
     * Send a message to the AI provider
     */
    public function sendMessage(AIMessage $message, array $options = []): AIResponse;
    
    /**
     * Send multiple messages in batch
     */
    public function sendBatch(array $messages, array $options = []): Collection;
    
    /**
     * Get available models from the provider
     */
    public function getAvailableModels(): Collection;
    
    /**
     * Sync models from the provider
     */
    public function syncModels(): void;
    
    /**
     * Calculate cost for token usage
     */
    public function calculateCost(TokenUsage $usage, string $modelId): float;
    
    /**
     * Validate provider credentials
     */
    public function validateCredentials(): bool;
    
    /**
     * Get provider capabilities
     */
    public function getCapabilities(): array;
    
    /**
     * Get provider name
     */
    public function getName(): string;
    
    /**
     * Check if provider supports streaming
     */
    public function supportsStreaming(): bool;
    
    /**
     * Stream a message response
     */
    public function streamMessage(AIMessage $message, callable $callback, array $options = []): AIResponse;
}
```

## Built-in Drivers

### OpenAI Driver

```php
<?php

namespace JTD\LaravelAI\Drivers;

use JTD\LaravelAI\Contracts\AIProviderInterface;
use OpenAI\Client as OpenAIClient;

class OpenAIDriver implements AIProviderInterface
{
    protected OpenAIClient $client;
    protected array $config;
    
    public function __construct(array $config)
    {
        $this->config = $config;
        $this->client = OpenAI::client($config['api_key']);
    }
    
    public function sendMessage(AIMessage $message, array $options = []): AIResponse
    {
        $response = $this->client->chat()->create([
            'model' => $options['model'] ?? 'gpt-3.5-turbo',
            'messages' => $this->formatMessages($message),
            'temperature' => $options['temperature'] ?? 0.7,
            'max_tokens' => $options['max_tokens'] ?? null,
        ]);
        
        return $this->formatResponse($response);
    }
    
    public function getAvailableModels(): Collection
    {
        $models = $this->client->models()->list();
        
        return collect($models->data)
            ->filter(fn($model) => str_starts_with($model->id, 'gpt'))
            ->map(fn($model) => [
                'id' => $model->id,
                'name' => $model->id,
                'type' => 'chat',
                'context_length' => $this->getContextLength($model->id),
                'capabilities' => $this->getModelCapabilities($model->id),
            ]);
    }
    
    // ... other interface methods
}
```

### Gemini Driver

```php
<?php

namespace JTD\LaravelAI\Drivers;

use JTD\LaravelAI\Contracts\AIProviderInterface;
use Google\Cloud\AIPlatform\V1\PredictionServiceClient;

class GeminiDriver implements AIProviderInterface
{
    protected PredictionServiceClient $client;
    protected array $config;
    
    public function __construct(array $config)
    {
        $this->config = $config;
        $this->client = new PredictionServiceClient([
            'apiEndpoint' => $config['base_url'],
            'credentials' => ['key' => $config['api_key']],
        ]);
    }
    
    public function sendMessage(AIMessage $message, array $options = []): AIResponse
    {
        // Gemini-specific implementation
        $request = $this->buildGeminiRequest($message, $options);
        $response = $this->client->predict($request);
        
        return $this->formatResponse($response);
    }
    
    // ... other interface methods
}
```

## Creating Custom Drivers

### Basic Custom Driver

```php
<?php

namespace App\AI\Drivers;

use JTD\LaravelAI\Contracts\AIProviderInterface;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Models\AIResponse;
use JTD\LaravelAI\Models\TokenUsage;
use Illuminate\Support\Collection;
use Illuminate\Http\Client\Factory as HttpClient;

class CustomAIDriver implements AIProviderInterface
{
    protected HttpClient $http;
    protected array $config;
    
    public function __construct(array $config)
    {
        $this->config = $config;
        $this->http = app(HttpClient::class);
    }
    
    public function getName(): string
    {
        return 'custom-ai';
    }
    
    public function sendMessage(AIMessage $message, array $options = []): AIResponse
    {
        $response = $this->http
            ->withHeaders([
                'Authorization' => 'Bearer ' . $this->config['api_key'],
                'Content-Type' => 'application/json',
            ])
            ->timeout($this->config['timeout'] ?? 30)
            ->post($this->config['base_url'] . '/chat/completions', [
                'model' => $options['model'] ?? $this->config['default_model'],
                'messages' => $this->formatMessages($message),
                'temperature' => $options['temperature'] ?? 0.7,
                'max_tokens' => $options['max_tokens'] ?? 1000,
            ]);
        
        if ($response->failed()) {
            throw new \Exception('API request failed: ' . $response->body());
        }
        
        return $this->parseResponse($response->json());
    }
    
    public function getAvailableModels(): Collection
    {
        $response = $this->http
            ->withHeaders(['Authorization' => 'Bearer ' . $this->config['api_key']])
            ->get($this->config['base_url'] . '/models');
        
        return collect($response->json('data'))
            ->map(fn($model) => [
                'id' => $model['id'],
                'name' => $model['name'],
                'type' => $model['type'] ?? 'chat',
                'context_length' => $model['context_length'] ?? 4096,
                'capabilities' => $model['capabilities'] ?? [],
            ]);
    }
    
    public function syncModels(): void
    {
        $models = $this->getAvailableModels();
        
        foreach ($models as $model) {
            \JTD\LaravelAI\Models\AIProviderModel::updateOrCreate([
                'provider_id' => $this->getProviderId(),
                'model_id' => $model['id'],
            ], [
                'name' => $model['name'],
                'type' => $model['type'],
                'capabilities' => $model['capabilities'],
                'context_length' => $model['context_length'],
                'is_active' => true,
                'synced_at' => now(),
            ]);
        }
    }
    
    public function calculateCost(TokenUsage $usage, string $modelId): float
    {
        $pricing = $this->getModelPricing($modelId);
        
        return ($usage->input_tokens * $pricing['input_cost_per_token']) +
               ($usage->output_tokens * $pricing['output_cost_per_token']);
    }
    
    public function validateCredentials(): bool
    {
        try {
            $response = $this->http
                ->withHeaders(['Authorization' => 'Bearer ' . $this->config['api_key']])
                ->get($this->config['base_url'] . '/models');
            
            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }
    
    public function getCapabilities(): array
    {
        return [
            'chat' => true,
            'streaming' => true,
            'functions' => false,
            'vision' => false,
            'audio' => false,
        ];
    }
    
    public function supportsStreaming(): bool
    {
        return true;
    }
    
    public function streamMessage(AIMessage $message, callable $callback, array $options = []): AIResponse
    {
        // Implement streaming logic
        $stream = $this->http
            ->withHeaders([
                'Authorization' => 'Bearer ' . $this->config['api_key'],
                'Content-Type' => 'application/json',
            ])
            ->timeout($this->config['timeout'] ?? 30)
            ->post($this->config['base_url'] . '/chat/completions', [
                'model' => $options['model'] ?? $this->config['default_model'],
                'messages' => $this->formatMessages($message),
                'stream' => true,
            ]);
        
        // Process stream and call callback for each chunk
        // Return final response
    }
    
    public function sendBatch(array $messages, array $options = []): Collection
    {
        // Implement batch processing
        $responses = collect();
        
        foreach ($messages as $message) {
            $responses->push($this->sendMessage($message, $options));
        }
        
        return $responses;
    }
    
    protected function formatMessages(AIMessage $message): array
    {
        // Convert internal message format to provider format
        return [
            [
                'role' => $message->role,
                'content' => $message->content,
            ]
        ];
    }
    
    protected function parseResponse(array $response): AIResponse
    {
        return new AIResponse([
            'content' => $response['choices'][0]['message']['content'],
            'tokens_used' => $response['usage']['total_tokens'],
            'input_tokens' => $response['usage']['prompt_tokens'],
            'output_tokens' => $response['usage']['completion_tokens'],
            'model' => $response['model'],
            'provider' => $this->getName(),
            'response_time' => 0, // Calculate actual response time
            'cost' => $this->calculateCost(
                new TokenUsage(
                    $response['usage']['prompt_tokens'],
                    $response['usage']['completion_tokens']
                ),
                $response['model']
            ),
        ]);
    }
    
    protected function getModelPricing(string $modelId): array
    {
        // Return pricing information for the model
        return [
            'input_cost_per_token' => 0.0001,
            'output_cost_per_token' => 0.0002,
        ];
    }
    
    protected function getProviderId(): int
    {
        return \JTD\LaravelAI\Models\AIProvider::where('slug', $this->getName())->first()->id;
    }
}
```

### Advanced Custom Driver with Streaming

```php
<?php

namespace App\AI\Drivers;

use JTD\LaravelAI\Drivers\BaseDriver;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Models\AIResponse;

class StreamingCustomDriver extends BaseDriver
{
    public function streamMessage(AIMessage $message, callable $callback, array $options = []): AIResponse
    {
        $fullContent = '';
        $totalTokens = 0;
        
        $stream = $this->http
            ->withHeaders($this->getHeaders())
            ->timeout($this->config['timeout'] ?? 60)
            ->post($this->config['base_url'] . '/stream', [
                'model' => $options['model'] ?? $this->config['default_model'],
                'messages' => $this->formatMessages($message),
                'stream' => true,
            ]);
        
        $stream->onChunk(function ($chunk) use ($callback, &$fullContent, &$totalTokens) {
            $data = json_decode($chunk, true);
            
            if (isset($data['choices'][0]['delta']['content'])) {
                $content = $data['choices'][0]['delta']['content'];
                $fullContent .= $content;
                
                // Call the callback with the chunk
                $callback(new AIResponse([
                    'content' => $content,
                    'is_chunk' => true,
                    'full_content' => $fullContent,
                ]));
            }
            
            if (isset($data['usage'])) {
                $totalTokens = $data['usage']['total_tokens'];
            }
        });
        
        return new AIResponse([
            'content' => $fullContent,
            'tokens_used' => $totalTokens,
            'model' => $options['model'] ?? $this->config['default_model'],
            'provider' => $this->getName(),
            'is_streamed' => true,
        ]);
    }
}
```

## Driver Registration

### Registering Custom Drivers

```php
// In a service provider
use JTD\LaravelAI\Facades\AI;
use App\AI\Drivers\CustomAIDriver;

public function boot()
{
    // Register the driver
    AI::extend('custom-ai', function ($config) {
        return new CustomAIDriver($config);
    });
}
```

### Configuration for Custom Drivers

```php
// config/ai.php
'providers' => [
    'custom-ai' => [
        'driver' => 'custom-ai',
        'api_key' => env('CUSTOM_AI_API_KEY'),
        'base_url' => env('CUSTOM_AI_BASE_URL', 'https://api.custom-ai.com/v1'),
        'timeout' => 30,
        'default_model' => 'custom-model-v1',
        'retry_attempts' => 3,
        'retry_delay' => 1000,
    ],
],
```

### Using Custom Drivers

```php
// Use the custom driver
$response = AI::conversation()
    ->provider('custom-ai')
    ->model('custom-model-v1')
    ->message('Hello from custom AI!')
    ->send();
```

## Driver Features

### Error Handling

```php
abstract class BaseDriver implements AIProviderInterface
{
    protected function handleApiError(\Exception $e): void
    {
        if ($e instanceof RateLimitException) {
            throw new \JTD\LaravelAI\Exceptions\RateLimitException(
                'Rate limit exceeded for ' . $this->getName(),
                $e->getRetryAfter()
            );
        }
        
        if ($e instanceof AuthenticationException) {
            throw new \JTD\LaravelAI\Exceptions\InvalidCredentialsException(
                'Invalid credentials for ' . $this->getName()
            );
        }
        
        throw new \JTD\LaravelAI\Exceptions\ProviderException(
            'Provider error: ' . $e->getMessage(),
            $e->getCode(),
            $e
        );
    }
}
```

### Retry Logic

```php
trait HasRetryLogic
{
    protected function withRetry(callable $callback, int $maxAttempts = 3): mixed
    {
        $attempt = 1;
        
        while ($attempt <= $maxAttempts) {
            try {
                return $callback();
            } catch (\Exception $e) {
                if ($attempt === $maxAttempts || !$this->shouldRetry($e)) {
                    throw $e;
                }
                
                $delay = $this->calculateRetryDelay($attempt);
                usleep($delay * 1000); // Convert to microseconds
                
                $attempt++;
            }
        }
    }
    
    protected function shouldRetry(\Exception $e): bool
    {
        return $e instanceof TimeoutException ||
               $e instanceof ConnectionException ||
               ($e instanceof HttpException && $e->getCode() >= 500);
    }
    
    protected function calculateRetryDelay(int $attempt): int
    {
        // Exponential backoff with jitter
        $baseDelay = $this->config['retry_delay'] ?? 1000;
        $exponentialDelay = $baseDelay * (2 ** ($attempt - 1));
        $jitter = rand(0, $exponentialDelay * 0.1);
        
        return $exponentialDelay + $jitter;
    }
}
```

### Caching Support

```php
trait HasCaching
{
    protected function getCacheKey(string $operation, array $params = []): string
    {
        return sprintf(
            'ai:%s:%s:%s',
            $this->getName(),
            $operation,
            md5(serialize($params))
        );
    }
    
    protected function remember(string $key, int $ttl, callable $callback): mixed
    {
        return Cache::remember($key, $ttl, $callback);
    }
    
    public function getAvailableModels(): Collection
    {
        $cacheKey = $this->getCacheKey('models');
        
        return $this->remember($cacheKey, 3600, function () {
            return $this->fetchModelsFromAPI();
        });
    }
}
```

## Driver Testing

### Unit Testing Custom Drivers

```php
<?php

namespace Tests\Unit\AI\Drivers;

use Tests\TestCase;
use App\AI\Drivers\CustomAIDriver;
use JTD\LaravelAI\Models\AIMessage;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Response;

class CustomAIDriverTest extends TestCase
{
    protected CustomAIDriver $driver;
    protected Factory $httpMock;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->httpMock = $this->mock(Factory::class);
        $this->driver = new CustomAIDriver([
            'api_key' => 'test-key',
            'base_url' => 'https://api.test.com/v1',
        ]);
    }
    
    public function test_send_message_returns_response()
    {
        $this->httpMock
            ->shouldReceive('withHeaders')
            ->andReturnSelf()
            ->shouldReceive('timeout')
            ->andReturnSelf()
            ->shouldReceive('post')
            ->andReturn(new Response([
                'choices' => [
                    ['message' => ['content' => 'Hello, world!']]
                ],
                'usage' => [
                    'total_tokens' => 10,
                    'prompt_tokens' => 5,
                    'completion_tokens' => 5,
                ],
                'model' => 'test-model',
            ]));
        
        $message = new AIMessage('Hello');
        $response = $this->driver->sendMessage($message);
        
        $this->assertEquals('Hello, world!', $response->content);
        $this->assertEquals(10, $response->tokens_used);
    }
}
```

### Integration Testing

```php
<?php

namespace Tests\Integration\AI\Drivers;

use Tests\TestCase;
use JTD\LaravelAI\Facades\AI;

class CustomDriverIntegrationTest extends TestCase
{
    public function test_custom_driver_integration()
    {
        // Register custom driver
        AI::extend('test-driver', function ($config) {
            return new TestDriver($config);
        });
        
        // Test usage
        $response = AI::conversation()
            ->provider('test-driver')
            ->message('Test message')
            ->send();
        
        $this->assertNotNull($response->content);
        $this->assertGreaterThan(0, $response->tokens_used);
    }
}
```

## Best Practices

### Driver Development Guidelines

1. **Implement all interface methods**: Ensure complete interface compliance
2. **Handle errors gracefully**: Provide meaningful error messages
3. **Support configuration**: Make drivers configurable through config arrays
4. **Add retry logic**: Implement exponential backoff for transient failures
5. **Cache expensive operations**: Cache model lists and pricing information
6. **Log important events**: Log API calls, errors, and performance metrics
7. **Validate inputs**: Always validate message content and options
8. **Support streaming**: Implement streaming where the provider supports it
9. **Calculate costs accurately**: Ensure precise cost calculations
10. **Test thoroughly**: Write comprehensive unit and integration tests

### Performance Considerations

1. **Connection pooling**: Reuse HTTP connections when possible
2. **Async processing**: Support asynchronous operations for batch requests
3. **Memory management**: Handle large responses efficiently
4. **Timeout handling**: Set appropriate timeouts for different operations
5. **Rate limiting**: Respect provider rate limits and implement backoff
