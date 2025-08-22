# Driver System Architecture

## Overview

JTD Laravel AI uses a sophisticated driver-based architecture that provides a unified interface for multiple AI providers while maintaining provider-specific optimizations. The system is built on proven patterns from Laravel's ecosystem and includes comprehensive error handling, retry logic, streaming support, function calling, and performance optimization.

## Driver Architecture

### Core Components

```
AIManager
├── DriverManager
│   ├── OpenAIDriver (Complete Implementation)
│   │   ├── Traits/
│   │   │   ├── HandlesApiCommunication
│   │   │   ├── HandlesErrors
│   │   │   ├── HandlesStreaming
│   │   │   ├── HandlesFunctionCalling
│   │   │   ├── ValidatesHealth
│   │   │   └── CalculatesCosts
│   │   └── Support/
│   │       ├── ErrorMapper
│   │       ├── ModelPricing
│   │       └── ResponseParser
│   ├── MockProvider (Testing)
│   └── CustomDrivers (Extensible)
├── Event System
│   ├── MessageSent
│   ├── ResponseGenerated
│   ├── CostCalculated
│   └── ConversationUpdated
├── Exception Hierarchy
│   ├── OpenAI/
│   │   ├── OpenAIException
│   │   ├── OpenAIRateLimitException
│   │   ├── OpenAIInvalidCredentialsException
│   │   ├── OpenAIQuotaExceededException
│   │   └── OpenAIServerException
│   └── Base Exceptions
└── Models & Data Structures
    ├── AIMessage
    ├── AIResponse
    ├── TokenUsage
    └── Database Models
```

### Driver Interface

All drivers must implement the comprehensive `AIProviderInterface`:

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
     * Send a single message to the AI provider
     */
    public function sendMessage(AIMessage $message, array $options = []): AIResponse;

    /**
     * Send multiple messages in a conversation
     */
    public function sendMessages(array $messages, array $options = []): AIResponse;

    /**
     * Send streaming message with real-time response chunks
     */
    public function sendStreamingMessage(AIMessage $message, array $options = []): \Generator;

    /**
     * Send streaming conversation with real-time response chunks
     */
    public function sendStreamingMessages(array $messages, array $options = []): \Generator;

    /**
     * Get available models from the provider
     */
    public function getAvailableModels(): Collection;

    /**
     * Sync models from the provider API
     */
    public function syncModels(): array;

    /**
     * Calculate cost for token usage
     */
    public function calculateCost(TokenUsage $usage, string $modelId): float;

    /**
     * Validate provider credentials and configuration
     */
    public function validateCredentials(): array;

    /**
     * Get provider capabilities and features
     */
    public function getCapabilities(): array;

    /**
     * Get provider configuration (with sensitive data masked)
     */
    public function getConfig(): array;

    /**
     * Get provider name/identifier
     */
    public function getName(): string;

    /**
     * Check if provider supports streaming responses
     */
    public function supportsStreaming(): bool;

    /**
     * Check if provider supports function calling
     */
    public function supportsFunctionCalling(): bool;

    /**
     * Check if provider supports vision/image inputs
     */
    public function supportsVision(): bool;
}
```

## Built-in Drivers

### OpenAI Driver (Complete Implementation)

The OpenAI driver serves as the reference implementation showcasing all driver system capabilities:

```php
<?php

namespace JTD\LaravelAI\Drivers;

use JTD\LaravelAI\Contracts\AIProviderInterface;
use JTD\LaravelAI\Drivers\AbstractAIProvider;
use JTD\LaravelAI\Drivers\OpenAI\Traits\HandlesApiCommunication;
use JTD\LaravelAI\Drivers\OpenAI\Traits\HandlesErrors;
use JTD\LaravelAI\Drivers\OpenAI\Traits\HandlesStreaming;
use JTD\LaravelAI\Drivers\OpenAI\Traits\HandlesFunctionCalling;
use JTD\LaravelAI\Drivers\OpenAI\Traits\ValidatesHealth;
use JTD\LaravelAI\Drivers\OpenAI\Traits\CalculatesCosts;
use OpenAI\Client as OpenAIClient;

class OpenAIDriver extends AbstractAIProvider implements AIProviderInterface
{
    use HandlesApiCommunication,
        HandlesErrors,
        HandlesStreaming,
        HandlesFunctionCalling,
        ValidatesHealth,
        CalculatesCosts;

    protected OpenAIClient $client;
    protected string $name = 'openai';

    public function __construct(array $config)
    {
        parent::__construct($config);
        $this->validateConfiguration($config);
        $this->initializeClient();
    }

    /**
     * Initialize the OpenAI client with configuration
     */
    protected function initializeClient(): void
    {
        $this->client = \OpenAI::client($this->config['api_key'])
            ->withOrganization($this->config['organization'] ?? null)
            ->withProject($this->config['project'] ?? null)
            ->withHttpClient($this->createHttpClient());
    }

    /**
     * Send a single message with comprehensive error handling and retry logic
     */
    public function sendMessage(AIMessage $message, array $options = []): AIResponse
    {
        return $this->withRetry(function () use ($message, $options) {
            return $this->makeApiCall([$message], $options);
        });
    }

    /**
     * Send streaming message with real-time chunks
     */
    public function sendStreamingMessage(AIMessage $message, array $options = []): \Generator
    {
        return $this->withRetry(function () use ($message, $options) {
            return $this->makeStreamingApiCall([$message], $options);
        });
    }

    /**
     * Get available models with caching and error handling
     */
    public function getAvailableModels(): Collection
    {
        return $this->withRetry(function () {
            $models = $this->client->models()->list();

            return collect($models->data)
                ->filter(fn($model) => $this->isValidModel($model))
                ->map(fn($model) => $this->formatModelInfo($model))
                ->values();
        });
    }

    /**
     * Comprehensive credential validation
     */
    public function validateCredentials(): array
    {
        try {
            $models = $this->client->models()->list();

            return [
                'valid' => true,
                'account_info' => $this->getAccountInfo(),
                'available_models' => count($models->data),
                'organization' => $this->config['organization'] ?? null,
                'project' => $this->config['project'] ?? null,
            ];
        } catch (\Exception $e) {
            return [
                'valid' => false,
                'errors' => [$this->enhanceErrorMessage($e->getMessage())],
                'error_type' => $this->classifyError($e),
            ];
        }
    }

    /**
     * Get comprehensive provider capabilities
     */
    public function getCapabilities(): array
    {
        return [
            'chat' => true,
            'streaming' => true,
            'function_calling' => true,
            'parallel_function_calling' => true,
            'vision' => true,
            'audio' => false,
            'image_generation' => false,
            'embeddings' => true,
            'fine_tuning' => true,
            'max_context_length' => 128000, // GPT-4 Turbo
            'supported_formats' => ['text', 'image', 'json'],
            'rate_limits' => $this->getRateLimits(),
        ];
    }
}
```

### Driver Trait System

The OpenAI driver uses a comprehensive trait system that can be reused for other providers:

#### HandlesApiCommunication Trait
```php
trait HandlesApiCommunication
{
    /**
     * Make API call with comprehensive error handling
     */
    protected function makeApiCall(array $messages, array $options): AIResponse
    {
        $startTime = microtime(true);

        try {
            $response = $this->client->chat()->create(
                $this->buildApiRequest($messages, $options)
            );

            $aiResponse = $this->parseResponse($response, $startTime);
            $this->fireEvents($messages, $aiResponse, $options);

            return $aiResponse;
        } catch (\Exception $e) {
            throw $this->mapException($e);
        }
    }
}
```

#### HandlesErrors Trait
```php
trait HandlesErrors
{
    /**
     * Execute operation with retry logic and exponential backoff
     */
    protected function withRetry(callable $operation, int $maxAttempts = null): mixed
    {
        $maxAttempts = $maxAttempts ?? $this->config['retry_attempts'];
        $attempt = 1;

        while ($attempt <= $maxAttempts) {
            try {
                return $operation();
            } catch (\Exception $e) {
                if ($attempt === $maxAttempts || !$this->isRetryableError($e)) {
                    throw $this->mapException($e);
                }

                $delay = $this->calculateRetryDelay($attempt);
                usleep($delay * 1000);
                $attempt++;
            }
        }
    }
}
```

#### HandlesFunctionCalling Trait
```php
trait HandlesFunctionCalling
{
    /**
     * Process function calls with parallel execution support
     */
    protected function processFunctionCalls(array $toolCalls): array
    {
        $results = [];

        foreach ($toolCalls as $toolCall) {
            $results[] = [
                'tool_call_id' => $toolCall->id,
                'role' => 'tool',
                'content' => $this->executeFunctionCall($toolCall),
            ];
        }

        return $results;
    }
}
```

### Exception System

The driver system includes a comprehensive exception hierarchy:

```php
// Base exceptions
JTD\LaravelAI\Exceptions\AIException
├── ProviderException
├── InvalidCredentialsException
├── RateLimitException
├── QuotaExceededException
└── ServerException

// OpenAI-specific exceptions
JTD\LaravelAI\Exceptions\OpenAI\OpenAIException
├── OpenAIInvalidCredentialsException
├── OpenAIRateLimitException
├── OpenAIQuotaExceededException
└── OpenAIServerException
```

#### Error Mapping System
```php
class ErrorMapper
{
    protected static array $exceptionMap = [
        'invalid_api_key' => OpenAIInvalidCredentialsException::class,
        'rate_limit_exceeded' => OpenAIRateLimitException::class,
        'quota_exceeded' => OpenAIQuotaExceededException::class,
        'server_error' => OpenAIServerException::class,
    ];

    public static function mapException(\Exception $exception): \Exception
    {
        $errorInfo = static::extractErrorInfo($exception);
        $errorType = static::classifyError($errorInfo);

        if (isset(static::$exceptionMap[$errorType])) {
            $exceptionClass = static::$exceptionMap[$errorType];
            return static::createSpecificException($exceptionClass, $errorType, $errorInfo, $exception);
        }

        return new OpenAIException(
            static::enhanceErrorMessage($errorInfo['message'], $errorType),
            $errorType,
            null,
            null,
            $errorInfo,
            static::isRetryableError($exception),
            $exception->getCode(),
            $exception
        );
    }
}
```

## Creating Custom Drivers

### Modern Custom Driver Pattern

```php
<?php

namespace App\AI\Drivers;

use JTD\LaravelAI\Contracts\AIProviderInterface;
use JTD\LaravelAI\Drivers\AbstractAIProvider;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Models\AIResponse;
use JTD\LaravelAI\Models\TokenUsage;
use Illuminate\Support\Collection;
use Illuminate\Http\Client\Factory as HttpClient;

class CustomAIDriver extends AbstractAIProvider implements AIProviderInterface
{
    protected HttpClient $http;
    protected string $name = 'custom-ai';

    public function __construct(array $config)
    {
        parent::__construct($config);
        $this->validateConfiguration($config);
        $this->http = app(HttpClient::class);
    }

    /**
     * Validate driver-specific configuration
     */
    protected function validateConfiguration(array $config): void
    {
        $required = ['api_key', 'base_url'];

        foreach ($required as $key) {
            if (empty($config[$key])) {
                throw new \InvalidArgumentException("Missing required config: {$key}");
            }
        }
    }

    /**
     * Send message with comprehensive error handling
     */
    public function sendMessage(AIMessage $message, array $options = []): AIResponse
    {
        return $this->withRetry(function () use ($message, $options) {
            return $this->makeApiCall([$message], $options);
        });
    }

    /**
     * Make API call with proper error handling
     */
    protected function makeApiCall(array $messages, array $options): AIResponse
    {
        $startTime = microtime(true);

        try {
            $response = $this->http
                ->withHeaders($this->getHeaders())
                ->timeout($this->config['timeout'] ?? 30)
                ->post($this->config['base_url'] . '/chat/completions',
                    $this->buildApiRequest($messages, $options)
                );

            if ($response->failed()) {
                throw new \Exception('API request failed: ' . $response->body());
            }

            $aiResponse = $this->parseResponse($response->json(), $startTime);
            $this->fireEvents($messages, $aiResponse, $options);

            return $aiResponse;
        } catch (\Exception $e) {
            throw $this->mapException($e);
        }
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

Register your custom driver in a service provider with comprehensive configuration:

```php
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use JTD\LaravelAI\AIManager;
use App\AI\Drivers\CustomAIDriver;

class AIServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->app->resolving(AIManager::class, function (AIManager $manager) {
            $manager->extend('custom-ai', function ($app, $config) {
                return new CustomAIDriver($config);
            });
        });
    }
}
```

### Comprehensive Driver Configuration

Add the driver configuration to your `config/ai.php` with all standard options:

```php
'providers' => [
    'openai' => [
        'driver' => 'openai',
        'api_key' => env('OPENAI_API_KEY'),
        'organization' => env('OPENAI_ORGANIZATION'),
        'project' => env('OPENAI_PROJECT'),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        'timeout' => (int) env('OPENAI_TIMEOUT', 30),
        'retry_attempts' => (int) env('OPENAI_RETRY_ATTEMPTS', 3),
        'retry_delay' => (int) env('OPENAI_RETRY_DELAY', 1000),
        'max_retry_delay' => (int) env('OPENAI_MAX_RETRY_DELAY', 30000),
        'logging' => [
            'enabled' => (bool) env('AI_LOGGING_ENABLED', true),
            'channel' => env('AI_LOG_CHANNEL', 'default'),
            'level' => env('AI_LOG_LEVEL', 'info'),
            'include_content' => (bool) env('AI_LOG_INCLUDE_CONTENT', false),
        ],
        'rate_limiting' => [
            'enabled' => (bool) env('AI_RATE_LIMITING_ENABLED', true),
            'requests_per_minute' => (int) env('OPENAI_RPM_LIMIT', 3500),
            'tokens_per_minute' => (int) env('OPENAI_TPM_LIMIT', 90000),
        ],
    ],

    'custom-ai' => [
        'driver' => 'custom-ai',
        'api_key' => env('CUSTOM_AI_API_KEY'),
        'base_url' => env('CUSTOM_AI_BASE_URL'),
        'timeout' => (int) env('CUSTOM_AI_TIMEOUT', 30),
        'retry_attempts' => (int) env('CUSTOM_AI_RETRY_ATTEMPTS', 3),
        'retry_delay' => (int) env('CUSTOM_AI_RETRY_DELAY', 1000),
        'max_retry_delay' => (int) env('CUSTOM_AI_MAX_RETRY_DELAY', 30000),
        'default_model' => env('CUSTOM_AI_DEFAULT_MODEL', 'custom-model-v1'),
        'logging' => [
            'enabled' => (bool) env('AI_LOGGING_ENABLED', true),
            'channel' => env('AI_LOG_CHANNEL', 'default'),
            'level' => env('AI_LOG_LEVEL', 'info'),
            'include_content' => (bool) env('AI_LOG_INCLUDE_CONTENT', false),
        ],
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

### Modern Driver Development Guidelines

1. **Extend AbstractAIProvider**: Use the base class for common functionality
2. **Use Trait System**: Leverage existing traits for error handling, streaming, etc.
3. **Comprehensive Error Handling**: Implement proper exception mapping and retry logic
4. **Event-Driven Architecture**: Fire events for all major operations
5. **Configuration Validation**: Validate all configuration parameters
6. **Security First**: Mask sensitive data in logs and configuration output
7. **Performance Optimization**: Implement caching, connection pooling, and efficient parsing
8. **Comprehensive Testing**: Write unit, integration, and E2E tests
9. **Documentation**: Document all capabilities, limitations, and configuration options
10. **Monitoring & Observability**: Include comprehensive logging and metrics

### Required Implementation Patterns

#### 1. Configuration Validation
```php
protected function validateConfiguration(array $config): void
{
    $required = ['api_key', 'base_url'];

    foreach ($required as $key) {
        if (empty($config[$key])) {
            throw new \InvalidArgumentException("Missing required config: {$key}");
        }
    }

    // Validate API key format
    if (!$this->isValidApiKeyFormat($config['api_key'])) {
        throw new InvalidCredentialsException('Invalid API key format');
    }
}
```

#### 2. Error Mapping
```php
protected function mapException(\Exception $exception): \Exception
{
    $errorInfo = $this->extractErrorInfo($exception);
    $errorType = $this->classifyError($errorInfo);

    return match ($errorType) {
        'invalid_credentials' => new InvalidCredentialsException($errorInfo['message']),
        'rate_limit' => new RateLimitException($errorInfo['message'], $errorInfo['retry_after']),
        'quota_exceeded' => new QuotaExceededException($errorInfo['message']),
        default => new ProviderException($errorInfo['message'], 0, $exception),
    };
}
```

#### 3. Event Integration
```php
protected function fireEvents(array $messages, AIResponse $response, array $options): void
{
    event(new MessageSent($this->getName(), $messages, $options));
    event(new ResponseGenerated($this->getName(), $response));
    event(new CostCalculated($this->getName(), $response->cost, $response->tokenUsage));
}
```

### Comprehensive Testing Strategy

#### 1. Unit Tests
```php
<?php

namespace Tests\Unit\Drivers;

use Tests\TestCase;
use App\AI\Drivers\CustomAIDriver;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Models\AIResponse;
use Illuminate\Support\Facades\Http;

class CustomAIDriverTest extends TestCase
{
    protected CustomAIDriver $driver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->driver = new CustomAIDriver([
            'api_key' => 'test-key-1234567890',
            'base_url' => 'https://api.test.com',
            'timeout' => 30,
            'retry_attempts' => 3,
            'retry_delay' => 1, // Fast for tests
        ]);
    }

    public function test_sends_message_successfully()
    {
        $message = AIMessage::user('Hello, world!');

        Http::fake([
            'api.test.com/*' => Http::response([
                'choices' => [['message' => ['content' => 'Hello back!']]],
                'usage' => ['total_tokens' => 15],
                'model' => 'test-model',
            ]),
        ]);

        $response = $this->driver->sendMessage($message);

        $this->assertInstanceOf(AIResponse::class, $response);
        $this->assertEquals('Hello back!', $response->content);
        $this->assertEquals('custom-ai', $response->provider);
    }

    public function test_handles_rate_limit_errors()
    {
        Http::fake([
            'api.test.com/*' => Http::response([
                'error' => ['message' => 'Rate limit exceeded']
            ], 429),
        ]);

        $this->expectException(RateLimitException::class);
        $this->driver->sendMessage(AIMessage::user('Test'));
    }

    public function test_validates_credentials()
    {
        Http::fake(['api.test.com/*' => Http::response(['models' => []])]);

        $result = $this->driver->validateCredentials();
        $this->assertTrue($result['valid']);
    }
}
```

#### 2. Integration Tests
```php
public function test_full_conversation_flow()
{
    $messages = [
        AIMessage::system('You are a helpful assistant.'),
        AIMessage::user('What is 2+2?'),
    ];

    $response = $this->driver->sendMessages($messages);

    $this->assertNotEmpty($response->content);
    $this->assertGreaterThan(0, $response->tokenUsage->totalTokens);
}
```

#### 3. E2E Tests
```php
public function test_real_api_integration()
{
    if (!$this->hasRealCredentials()) {
        $this->markTestSkipped('Real credentials not available');
    }

    $driver = new CustomAIDriver($this->getRealConfig());
    $response = $driver->sendMessage(AIMessage::user('Hello'));

    $this->assertNotEmpty($response->content);
}
```

### Performance Considerations

1. **Connection pooling**: Reuse HTTP connections when possible
2. **Async processing**: Support asynchronous operations for batch requests
3. **Memory management**: Handle large responses efficiently
4. **Timeout handling**: Set appropriate timeouts for different operations
5. **Rate limiting**: Respect provider rate limits and implement backoff
6. **Caching**: Cache expensive operations like model lists and pricing
7. **Streaming optimization**: Use efficient streaming for real-time responses
8. **Error recovery**: Implement circuit breakers for failing providers

## Conclusion

The JTD Laravel AI driver system provides a robust, extensible architecture for integrating multiple AI providers. The OpenAI driver serves as a comprehensive reference implementation showcasing:

- **Comprehensive error handling** with retry logic and exponential backoff
- **Event-driven architecture** for monitoring and observability
- **Security best practices** with credential masking and validation
- **Performance optimization** with caching and efficient API communication
- **Streaming support** for real-time responses
- **Function calling** capabilities for advanced AI interactions
- **Comprehensive testing** with unit, integration, and E2E tests

When creating custom drivers, follow the established patterns and leverage the trait system to ensure consistency, reliability, and maintainability across all AI provider integrations.
