# Driver Traits

## Overview

The JTD Laravel AI package uses a trait-based architecture to organize driver functionality into focused, reusable components. This approach promotes code reuse, maintainability, and consistent behavior across different providers.

## Core Trait System

### Trait Architecture Benefits

- **Modularity**: Each trait handles a specific concern
- **Reusability**: Traits can be shared across multiple drivers
- **Testability**: Individual traits can be tested in isolation
- **Maintainability**: Changes to specific functionality are localized
- **Consistency**: Common behavior across all providers

## Available Traits

### HandlesApiCommunication

Manages HTTP client configuration and API communication.

```php
<?php

namespace JTD\LaravelAI\Drivers\OpenAI\Traits;

trait HandlesApiCommunication
{
    protected $client;
    protected array $defaultHeaders = [];

    protected function initializeClient(): void
    {
        $this->client = OpenAI::client($this->config['api_key']);
        
        if (isset($this->config['organization'])) {
            $this->client = $this->client->organization($this->config['organization']);
        }
        
        if (isset($this->config['project'])) {
            $this->client = $this->client->project($this->config['project']);
        }
    }

    protected function getDefaultHeaders(): array
    {
        return array_merge([
            'User-Agent' => 'JTD-Laravel-AI/1.0',
            'Content-Type' => 'application/json',
        ], $this->defaultHeaders);
    }

    protected function executeWithRetry(callable $callback, int $maxAttempts = null)
    {
        $maxAttempts = $maxAttempts ?? $this->config['retry_attempts'] ?? 3;
        $delay = $this->config['retry_delay'] ?? 1000; // milliseconds
        $maxDelay = $this->config['max_retry_delay'] ?? 30000;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                return $callback();
            } catch (\Exception $e) {
                if ($attempt === $maxAttempts || !$this->shouldRetry($e)) {
                    throw $e;
                }

                $waitTime = min($delay * pow(2, $attempt - 1), $maxDelay);
                usleep($waitTime * 1000); // Convert to microseconds
            }
        }
    }

    protected function shouldRetry(\Exception $e): bool
    {
        // Retry on rate limits, server errors, and network issues
        return $e instanceof RateLimitException ||
               $e instanceof ServerException ||
               $e instanceof ConnectException;
    }
}
```

### HandlesErrors

Provides comprehensive error handling and mapping.

```php
<?php

namespace JTD\LaravelAI\Drivers\OpenAI\Traits;

use JTD\LaravelAI\Exceptions\OpenAI\{
    OpenAIException,
    OpenAIRateLimitException,
    OpenAIInvalidCredentialsException,
    OpenAIQuotaExceededException,
    OpenAIServerException
};

trait HandlesErrors
{
    protected function handleApiError(\Exception $e): void
    {
        $message = $e->getMessage();
        $code = $e->getCode();

        // Map OpenAI-specific errors
        if (str_contains($message, 'rate limit')) {
            throw new OpenAIRateLimitException($message, $code, $e);
        }

        if (str_contains($message, 'invalid_api_key') || str_contains($message, 'authentication')) {
            throw new OpenAIInvalidCredentialsException($message, $code, $e);
        }

        if (str_contains($message, 'quota') || str_contains($message, 'billing')) {
            throw new OpenAIQuotaExceededException($message, $code, $e);
        }

        if ($code >= 500) {
            throw new OpenAIServerException($message, $code, $e);
        }

        // Generic OpenAI exception
        throw new OpenAIException($message, $code, $e);
    }

    protected function logError(\Exception $e, array $context = []): void
    {
        \Log::error('AI Provider Error', [
            'provider' => $this->getName(),
            'error' => $e->getMessage(),
            'code' => $e->getCode(),
            'context' => $context,
        ]);
    }

    protected function handleException(\Exception $e): void
    {
        $this->logError($e);
        $this->handleApiError($e);
    }
}
```

### ManagesModels

Handles model discovery, caching, and synchronization.

```php
<?php

namespace JTD\LaravelAI\Drivers\OpenAI\Traits;

use Carbon\Carbon;
use JTD\LaravelAI\Drivers\OpenAI\Support\ModelCapabilities;

trait ManagesModels
{
    public function getAvailableModels(bool $refresh = false): Collection
    {
        $cacheKey = $this->getModelsCacheKey();
        
        if (!$refresh && \Cache::has($cacheKey)) {
            return collect(\Cache::get($cacheKey));
        }

        try {
            $response = $this->executeWithRetry(function () {
                return $this->client->models()->list();
            });

            $models = collect($response->data)
                ->filter(fn($model) => ModelCapabilities::isChatModel($model->id))
                ->map(fn($model) => $this->formatModelData($model))
                ->values();

            \Cache::put($cacheKey, $models->toArray(), now()->addHours(24));

            return $models;

        } catch (\Exception $e) {
            $this->handleApiError($e);
        }
    }

    protected function formatModelData($model): array
    {
        return [
            'id' => $model->id,
            'name' => ModelCapabilities::getDisplayName($model->id),
            'type' => 'chat',
            'capabilities' => ModelCapabilities::getCapabilities($model->id),
            'context_length' => ModelCapabilities::getContextLength($model->id),
            'owned_by' => $model->ownedBy ?? 'openai',
            'created' => $model->created ?? null,
        ];
    }

    public function syncModels(bool $forceRefresh = false): array
    {
        // Implementation from previous sync documentation
        // ... (see 05-Sync-Implementation.md for full implementation)
    }

    protected function getModelsCacheKey(): string
    {
        return "laravel-ai:{$this->getName()}:models";
    }
}
```

### CalculatesCosts

Provides token usage tracking and cost calculation.

```php
<?php

namespace JTD\LaravelAI\Drivers\OpenAI\Traits;

use JTD\LaravelAI\Models\TokenUsage;
use JTD\LaravelAI\Drivers\OpenAI\Support\ModelPricing;

trait CalculatesCosts
{
    public function calculateCost(TokenUsage $usage, string $modelId): float
    {
        $pricing = ModelPricing::getPricing($modelId);
        
        if (!$pricing) {
            \Log::warning("No pricing data available for model: {$modelId}");
            return 0.0;
        }

        $promptCost = ($usage->promptTokens / 1000) * $pricing['prompt'];
        $completionCost = ($usage->completionTokens / 1000) * $pricing['completion'];
        
        $totalCost = $promptCost + $completionCost;

        // Log cost calculation for monitoring
        \Log::info('Cost calculated', [
            'provider' => $this->getName(),
            'model' => $modelId,
            'prompt_tokens' => $usage->promptTokens,
            'completion_tokens' => $usage->completionTokens,
            'prompt_cost' => $promptCost,
            'completion_cost' => $completionCost,
            'total_cost' => $totalCost,
        ]);

        return $totalCost;
    }

    protected function trackUsage(TokenUsage $usage, string $modelId, float $cost): void
    {
        // Fire event for usage tracking
        event(new \JTD\LaravelAI\Events\CostCalculated([
            'provider' => $this->getName(),
            'model' => $modelId,
            'usage' => $usage,
            'cost' => $cost,
            'timestamp' => now(),
        ]));
    }

    protected function estimateTokens(string $text): int
    {
        // Rough estimation: ~4 characters per token for English text
        return (int) ceil(strlen($text) / 4);
    }
}
```

### ValidatesHealth

Handles credential validation and health checks.

```php
<?php

namespace JTD\LaravelAI\Drivers\OpenAI\Traits;

trait ValidatesHealth
{
    public function validateCredentials(): array
    {
        try {
            $response = $this->executeWithRetry(function () {
                return $this->client->models()->list();
            });

            if ($response && count($response->data) > 0) {
                return [
                    'status' => 'valid',
                    'message' => 'Credentials are valid and API is accessible',
                    'models_available' => count($response->data),
                    'checked_at' => now()->toISOString(),
                ];
            }

            return [
                'status' => 'invalid',
                'message' => 'No models available with current credentials',
                'checked_at' => now()->toISOString(),
            ];

        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Failed to validate credentials',
                'error' => $e->getMessage(),
                'checked_at' => now()->toISOString(),
            ];
        }
    }

    public function hasValidCredentials(): bool
    {
        $cacheKey = "laravel-ai:{$this->getName()}:credentials_valid";
        
        // Cache validation result for 5 minutes
        return \Cache::remember($cacheKey, now()->addMinutes(5), function () {
            $result = $this->validateCredentials();
            return $result['status'] === 'valid';
        });
    }

    public function checkHealth(): array
    {
        $health = [
            'provider' => $this->getName(),
            'status' => 'healthy',
            'checks' => [],
            'checked_at' => now()->toISOString(),
        ];

        // Check credentials
        $credentialCheck = $this->validateCredentials();
        $health['checks']['credentials'] = $credentialCheck;
        
        if ($credentialCheck['status'] !== 'valid') {
            $health['status'] = 'unhealthy';
        }

        // Check API response time
        $start = microtime(true);
        try {
            $this->client->models()->list();
            $responseTime = (microtime(true) - $start) * 1000; // Convert to milliseconds
            
            $health['checks']['response_time'] = [
                'status' => $responseTime < 5000 ? 'good' : 'slow',
                'value' => $responseTime,
                'unit' => 'ms',
            ];
            
            if ($responseTime >= 10000) {
                $health['status'] = 'degraded';
            }
            
        } catch (\Exception $e) {
            $health['checks']['response_time'] = [
                'status' => 'failed',
                'error' => $e->getMessage(),
            ];
            $health['status'] = 'unhealthy';
        }

        return $health;
    }
}
```

### HandlesStreaming

Manages real-time response streaming.

```php
<?php

namespace JTD\LaravelAI\Drivers\OpenAI\Traits;

use JTD\LaravelAI\Models\AIResponse;

trait HandlesStreaming
{
    public function sendStreamingMessage(AIMessage $message, array $options = []): \Generator
    {
        return $this->sendStreamingMessages([$message], $options);
    }

    public function sendStreamingMessages(array $messages, array $options = []): \Generator
    {
        try {
            $stream = $this->client->chat()->createStreamed([
                'model' => $options['model'] ?? $this->getDefaultModel(),
                'messages' => $this->formatMessages($messages),
                'temperature' => $options['temperature'] ?? 0.7,
                'max_tokens' => $options['max_tokens'] ?? null,
                'stream' => true,
            ]);

            foreach ($stream as $response) {
                $choice = $response->choices[0] ?? null;
                
                if ($choice && isset($choice->delta->content)) {
                    yield new AIResponse(
                        content: $choice->delta->content,
                        role: 'assistant',
                        model: $response->model,
                        usage: null, // Usage not available in streaming
                        finishReason: $choice->finishReason,
                        rawResponse: $response->toArray()
                    );
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

    protected function handleStreamingError(\Exception $e): void
    {
        \Log::error('Streaming error', [
            'provider' => $this->getName(),
            'error' => $e->getMessage(),
        ]);
        
        throw $e;
    }
}
```

### HandlesFunctionCalling

Manages AI function calling capabilities.

```php
<?php

namespace JTD\LaravelAI\Drivers\OpenAI\Traits;

trait HandlesFunctionCalling
{
    public function sendMessageWithFunctions(
        AIMessage $message, 
        array $functions, 
        array $options = []
    ): AIResponse {
        try {
            $response = $this->client->chat()->create([
                'model' => $options['model'] ?? $this->getDefaultModel(),
                'messages' => $this->formatMessages([$message]),
                'functions' => $functions,
                'function_call' => $options['function_call'] ?? 'auto',
                'temperature' => $options['temperature'] ?? 0.7,
            ]);

            return $this->parseResponse($response);

        } catch (\Exception $e) {
            $this->handleException($e);
        }
    }

    public function supportsFunctionCalling(): bool
    {
        return true;
    }

    protected function parseFunctionCall($choice): ?array
    {
        if (!isset($choice->message->functionCall)) {
            return null;
        }

        $functionCall = $choice->message->functionCall;
        
        return [
            'name' => $functionCall->name,
            'arguments' => json_decode($functionCall->arguments, true),
        ];
    }

    protected function validateFunctionDefinition(array $function): bool
    {
        $required = ['name', 'description', 'parameters'];
        
        foreach ($required as $field) {
            if (!isset($function[$field])) {
                return false;
            }
        }

        return true;
    }
}
```

## Creating Custom Traits

### Trait Template

```php
<?php

namespace App\AI\Traits;

trait CustomTrait
{
    /**
     * Trait-specific properties
     */
    protected array $customConfig = [];

    /**
     * Initialize the trait
     */
    protected function initializeCustomTrait(): void
    {
        $this->customConfig = $this->config['custom'] ?? [];
    }

    /**
     * Public methods that implement specific functionality
     */
    public function customMethod(): mixed
    {
        try {
            // Implementation
            return $this->performCustomOperation();
        } catch (\Exception $e) {
            $this->handleCustomError($e);
        }
    }

    /**
     * Protected helper methods
     */
    protected function performCustomOperation(): mixed
    {
        // Custom logic here
    }

    protected function handleCustomError(\Exception $e): void
    {
        \Log::error('Custom trait error', [
            'trait' => static::class,
            'error' => $e->getMessage(),
        ]);
        
        throw $e;
    }
}
```

### Using Custom Traits

```php
<?php

namespace App\AI\Drivers;

use JTD\LaravelAI\Drivers\AbstractAIProvider;
use App\AI\Traits\CustomTrait;

class CustomDriver extends AbstractAIProvider
{
    use CustomTrait;

    public function __construct(array $config)
    {
        parent::__construct($config);
        
        // Initialize custom traits
        $this->initializeCustomTrait();
    }
}
```

## Testing Traits

### Unit Testing Individual Traits

```php
<?php

namespace Tests\Unit\Traits;

use Tests\TestCase;
use JTD\LaravelAI\Drivers\OpenAI\Traits\CalculatesCosts;
use JTD\LaravelAI\Models\TokenUsage;

class CalculatesCostsTest extends TestCase
{
    use CalculatesCosts;

    protected string $name = 'test';

    public function test_calculates_cost_correctly(): void
    {
        $usage = new TokenUsage(
            promptTokens: 100,
            completionTokens: 50,
            totalTokens: 150
        );

        $cost = $this->calculateCost($usage, 'gpt-4');

        $this->assertGreaterThan(0, $cost);
        $this->assertIsFloat($cost);
    }

    public function getName(): string
    {
        return $this->name;
    }
}
```

### Integration Testing with Traits

```php
<?php

namespace Tests\Integration;

use Tests\TestCase;
use JTD\LaravelAI\Drivers\OpenAIDriver;

class TraitIntegrationTest extends TestCase
{
    public function test_traits_work_together(): void
    {
        $driver = new OpenAIDriver($this->getTestConfig());

        // Test that multiple traits work together
        $this->assertTrue($driver->hasValidCredentials());
        $this->assertTrue($driver->supportsStreaming());
        $this->assertTrue($driver->supportsFunctionCalling());
        
        $models = $driver->getAvailableModels();
        $this->assertNotEmpty($models);
    }
}
```

## Best Practices

### Trait Design
- **Single Responsibility**: Each trait should handle one specific concern
- **Minimal Dependencies**: Avoid tight coupling between traits
- **Clear Interfaces**: Define clear public methods and contracts
- **Error Handling**: Include comprehensive error handling

### Code Organization
- **Consistent Naming**: Use descriptive trait names with clear purposes
- **Proper Namespacing**: Organize traits in logical namespace hierarchies
- **Documentation**: Document all public methods and their purposes
- **Testing**: Create comprehensive tests for each trait

### Performance
- **Lazy Loading**: Initialize expensive resources only when needed
- **Caching**: Cache expensive operations appropriately
- **Memory Management**: Clean up resources when appropriate
- **Efficient Algorithms**: Use efficient algorithms and data structures

## Related Documentation

- **[Driver Interface](03-Interface.md)**: Understanding the complete interface
- **[OpenAI Driver](07-OpenAI-Driver.md)**: Example of trait usage
- **[Creating Custom Drivers](09-Custom-Drivers.md)**: Using traits in custom drivers
- **[Testing Strategy](12-Testing.md)**: Testing trait-based architecture
