# Best Practices

## Overview

This guide outlines best practices for developing, maintaining, and using AI drivers in the JTD Laravel AI package. Following these practices ensures reliability, performance, security, and maintainability.

## Driver Development

### Architecture Principles

#### Single Responsibility
Each driver should focus on one AI provider and implement the complete interface consistently.

```php
// Good: Focused on OpenAI functionality
class OpenAIDriver extends AbstractAIProvider
{
    use HandlesApiCommunication,
        ManagesModels,
        CalculatesCosts;
}

// Avoid: Mixing multiple providers in one class
class MultiProviderDriver // Don't do this
{
    public function sendOpenAIMessage() { }
    public function sendGeminiMessage() { }
}
```

#### Trait-Based Organization
Use traits to organize functionality into focused, reusable components.

```php
// Good: Focused trait with single responsibility
trait CalculatesCosts
{
    public function calculateCost(TokenUsage $usage, string $modelId): float
    {
        // Cost calculation logic
    }
}

// Avoid: Monolithic driver class
class OpenAIDriver
{
    public function sendMessage() { }
    public function calculateCost() { }
    public function validateCredentials() { }
    public function syncModels() { }
    // ... hundreds of lines
}
```

#### Configuration-Driven Behavior
Make all behavior configurable through Laravel's configuration system.

```php
// Good: Configuration-driven
public function getTimeout(): int
{
    return $this->config['timeout'] ?? 30;
}

public function getRetryAttempts(): int
{
    return $this->config['retry_attempts'] ?? 3;
}

// Avoid: Hard-coded values
public function getTimeout(): int
{
    return 30; // Hard-coded
}
```

### Error Handling

#### Provider-Specific Exception Mapping
Map provider errors to specific exception types for better error handling.

```php
// Good: Specific exception mapping
protected function handleApiError(\Exception $e): void
{
    if (str_contains($e->getMessage(), 'rate_limit_exceeded')) {
        throw new OpenAIRateLimitException($e->getMessage(), $e->getCode(), $e);
    }
    
    if (str_contains($e->getMessage(), 'invalid_api_key')) {
        throw new OpenAIInvalidCredentialsException($e->getMessage(), $e->getCode(), $e);
    }
}

// Avoid: Generic exceptions
protected function handleApiError(\Exception $e): void
{
    throw new \Exception($e->getMessage()); // Too generic
}
```

#### Comprehensive Logging
Log errors with appropriate detail levels and context.

```php
// Good: Contextual logging
protected function logError(\Exception $e, array $context = []): void
{
    \Log::error('AI Provider Error', [
        'provider' => $this->getName(),
        'exception' => get_class($e),
        'message' => $e->getMessage(),
        'code' => $e->getCode(),
        'context' => $context,
        'trace' => $e->getTraceAsString(),
    ]);
}

// Avoid: Minimal logging
protected function logError(\Exception $e): void
{
    \Log::error($e->getMessage()); // Not enough context
}
```

#### Retry Logic with Exponential Backoff
Implement intelligent retry logic for transient failures.

```php
// Good: Exponential backoff
protected function executeWithRetry(callable $callback, int $maxAttempts = 3)
{
    for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
        try {
            return $callback();
        } catch (\Exception $e) {
            if ($attempt === $maxAttempts || !$this->shouldRetry($e)) {
                throw $e;
            }
            
            $delay = min(1000 * pow(2, $attempt - 1), 30000);
            usleep($delay * 1000);
        }
    }
}

// Avoid: Fixed delay retry
protected function executeWithRetry(callable $callback)
{
    for ($i = 0; $i < 3; $i++) {
        try {
            return $callback();
        } catch (\Exception $e) {
            sleep(1); // Fixed delay
        }
    }
}
```

## Security

### Credential Management

#### Environment Variables
Always use environment variables for sensitive configuration.

```php
// Good: Environment-based configuration
'api_key' => env('OPENAI_API_KEY'),
'organization' => env('OPENAI_ORGANIZATION'),

// Avoid: Hard-coded credentials
'api_key' => 'sk-hardcoded-key-here', // Never do this
```

#### Credential Masking
Mask sensitive data in logs and debug output.

```php
// Good: Masked credentials
public function getConfig(): array
{
    $config = $this->config;
    
    if (isset($config['api_key'])) {
        $config['api_key'] = 'sk-***' . substr($config['api_key'], -4);
    }
    
    return $config;
}

// Avoid: Exposing credentials
public function getConfig(): array
{
    return $this->config; // Exposes API keys
}
```

#### Validation
Validate credentials format and permissions.

```php
// Good: Comprehensive validation
protected function validateConfiguration(array $config): void
{
    if (empty($config['api_key'])) {
        throw new \InvalidArgumentException('API key is required');
    }
    
    if (!$this->isValidApiKeyFormat($config['api_key'])) {
        throw new InvalidCredentialsException('Invalid API key format');
    }
    
    // Test credentials with a lightweight API call
    $this->validateCredentials();
}

// Avoid: No validation
protected function validateConfiguration(array $config): void
{
    // No validation
}
```

### Data Protection

#### Content Filtering
Be careful about logging sensitive content.

```php
// Good: Configurable content logging
protected function logRequest(array $data): void
{
    $logData = [
        'provider' => $this->getName(),
        'model' => $data['model'] ?? 'unknown',
        'message_count' => count($data['messages'] ?? []),
    ];
    
    if ($this->config['logging']['include_content'] ?? false) {
        $logData['messages'] = $data['messages'];
    }
    
    \Log::info('AI Request', $logData);
}

// Avoid: Always logging content
protected function logRequest(array $data): void
{
    \Log::info('AI Request', $data); // May contain sensitive data
}
```

## Performance

### Caching Strategy

#### Intelligent Caching
Cache expensive operations with appropriate TTL.

```php
// Good: Intelligent caching
public function getAvailableModels(bool $refresh = false): Collection
{
    $cacheKey = "laravel-ai:{$this->getName()}:models";
    
    if (!$refresh && \Cache::has($cacheKey)) {
        return collect(\Cache::get($cacheKey));
    }
    
    $models = $this->fetchModelsFromApi();
    \Cache::put($cacheKey, $models->toArray(), now()->addHours(24));
    
    return $models;
}

// Avoid: No caching
public function getAvailableModels(): Collection
{
    return $this->fetchModelsFromApi(); // Always hits API
}
```

#### Cache Invalidation
Implement proper cache invalidation strategies.

```php
// Good: Explicit cache invalidation
public function syncModels(bool $forceRefresh = false): array
{
    if ($forceRefresh) {
        $this->clearModelsCache();
    }
    
    // Sync logic...
}

protected function clearModelsCache(): void
{
    $cacheKey = "laravel-ai:{$this->getName()}:models";
    \Cache::forget($cacheKey);
    \Cache::forget($cacheKey . ':last_sync');
    \Cache::forget($cacheKey . ':stats');
}
```

### Resource Management

#### Connection Pooling
Reuse HTTP connections when possible.

```php
// Good: Reuse HTTP client
protected function getHttpClient(): HttpClient
{
    if (!isset($this->httpClient)) {
        $this->httpClient = Http::withOptions([
            'timeout' => $this->getTimeout(),
            'connect_timeout' => 10,
        ]);
    }
    
    return $this->httpClient;
}

// Avoid: Creating new connections
protected function makeRequest(): Response
{
    return Http::timeout(30)->post(...); // New connection each time
}
```

#### Memory Management
Be mindful of memory usage, especially with streaming.

```php
// Good: Memory-efficient streaming
public function sendStreamingMessage(AIMessage $message): \Generator
{
    $stream = $this->client->chat()->createStreamed([...]);
    
    foreach ($stream as $response) {
        yield $this->parseStreamChunk($response);
        
        // Don't accumulate chunks in memory
        unset($response);
    }
}

// Avoid: Accumulating in memory
public function sendStreamingMessage(AIMessage $message): array
{
    $chunks = [];
    $stream = $this->client->chat()->createStreamed([...]);
    
    foreach ($stream as $response) {
        $chunks[] = $response; // Accumulates in memory
    }
    
    return $chunks;
}
```

## Testing

### Test Organization

#### Comprehensive Test Coverage
Test all aspects of driver functionality.

```php
// Good: Comprehensive test structure
class OpenAIDriverTest extends TestCase
{
    public function test_sends_message_successfully() { }
    public function test_handles_rate_limit_errors() { }
    public function test_validates_credentials() { }
    public function test_calculates_costs_correctly() { }
    public function test_syncs_models() { }
    public function test_streams_responses() { }
}
```

#### Proper Mocking
Mock external dependencies appropriately.

```php
// Good: Mock external APIs
public function test_sends_message_successfully(): void
{
    $mockClient = Mockery::mock(OpenAI::class);
    $mockClient->shouldReceive('chat->create')
        ->once()
        ->andReturn($this->createMockResponse());
    
    $driver = new OpenAIDriver($this->config);
    $driver->setClient($mockClient);
    
    $response = $driver->sendMessage(AIMessage::user('Test'));
    
    $this->assertInstanceOf(AIResponse::class, $response);
}

// Avoid: Testing external APIs in unit tests
public function test_sends_message_successfully(): void
{
    $driver = new OpenAIDriver($this->realConfig);
    $response = $driver->sendMessage(AIMessage::user('Test')); // Hits real API
}
```

### E2E Testing

#### Credential Management
Manage E2E credentials securely.

```php
// Good: Secure credential management
protected function setUp(): void
{
    parent::setUp();
    
    if (!$this->hasE2ECredentials('openai')) {
        $this->markTestSkipped('OpenAI E2E credentials not available');
    }
    
    $this->overrideConfigWithE2ECredentials('openai');
}

// Avoid: Hard-coded credentials
protected function setUp(): void
{
    config(['ai.providers.openai.api_key' => 'sk-real-key']); // Don't do this
}
```

## Documentation

### Code Documentation

#### Comprehensive PHPDoc
Document all public methods with complete PHPDoc blocks.

```php
// Good: Complete documentation
/**
 * Send a message to the AI provider.
 *
 * @param  AIMessage  $message  The message to send
 * @param  array  $options  Additional options (model, temperature, etc.)
 * @return AIResponse The AI response
 *
 * @throws \JTD\LaravelAI\Exceptions\ProviderException
 * @throws \JTD\LaravelAI\Exceptions\InvalidCredentialsException
 * @throws \JTD\LaravelAI\Exceptions\RateLimitException
 */
public function sendMessage(AIMessage $message, array $options = []): AIResponse
{
    // Implementation
}

// Avoid: Minimal documentation
/**
 * Send message
 */
public function sendMessage($message, $options = [])
{
    // Implementation
}
```

#### Usage Examples
Include practical usage examples in documentation.

```php
/**
 * Calculate cost for token usage.
 *
 * @example
 * ```php
 * $usage = new TokenUsage(100, 50, 150);
 * $cost = $driver->calculateCost($usage, 'gpt-4');
 * echo "Cost: $" . number_format($cost, 4);
 * ```
 */
public function calculateCost(TokenUsage $usage, string $modelId): float
```

## Monitoring and Observability

### Logging Strategy

#### Structured Logging
Use structured logging for better observability.

```php
// Good: Structured logging
\Log::info('AI message sent', [
    'provider' => $this->getName(),
    'model' => $options['model'] ?? $this->getDefaultModel(),
    'message_length' => strlen($message->content),
    'response_time_ms' => $responseTime,
    'tokens_used' => $response->usage->totalTokens,
    'cost_usd' => $cost,
]);

// Avoid: Unstructured logging
\Log::info("Sent message to {$this->getName()} using {$model}"); // Hard to parse
```

#### Performance Metrics
Track performance metrics for monitoring.

```php
// Good: Performance tracking
protected function trackPerformance(string $operation, callable $callback)
{
    $start = microtime(true);
    
    try {
        $result = $callback();
        
        $duration = (microtime(true) - $start) * 1000;
        
        \Log::info('AI operation completed', [
            'provider' => $this->getName(),
            'operation' => $operation,
            'duration_ms' => $duration,
            'status' => 'success',
        ]);
        
        return $result;
        
    } catch (\Exception $e) {
        $duration = (microtime(true) - $start) * 1000;
        
        \Log::error('AI operation failed', [
            'provider' => $this->getName(),
            'operation' => $operation,
            'duration_ms' => $duration,
            'status' => 'error',
            'error' => $e->getMessage(),
        ]);
        
        throw $e;
    }
}
```

### Event System

#### Comprehensive Events
Fire events for all major operations.

```php
// Good: Event-driven architecture
public function sendMessage(AIMessage $message, array $options = []): AIResponse
{
    event(new MessageSending([
        'provider' => $this->getName(),
        'message' => $message,
        'options' => $options,
    ]));
    
    $response = $this->performSendMessage($message, $options);
    
    event(new MessageSent([
        'provider' => $this->getName(),
        'message' => $message,
        'response' => $response,
        'cost' => $this->calculateCost($response->usage, $response->model),
    ]));
    
    return $response;
}
```

## Deployment

### Configuration Management

#### Environment-Specific Settings
Use different settings for different environments.

```php
// config/ai.php
'providers' => [
    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'timeout' => env('OPENAI_TIMEOUT', app()->environment('production') ? 30 : 60),
        'retry_attempts' => env('OPENAI_RETRY_ATTEMPTS', app()->environment('production') ? 3 : 1),
        'logging' => [
            'enabled' => env('AI_LOGGING_ENABLED', !app()->environment('production')),
            'level' => env('AI_LOG_LEVEL', app()->environment('production') ? 'warning' : 'debug'),
        ],
    ],
],
```

#### Health Checks
Implement health checks for monitoring.

```php
// Good: Comprehensive health check
public function checkHealth(): array
{
    return [
        'provider' => $this->getName(),
        'status' => $this->isHealthy() ? 'healthy' : 'unhealthy',
        'checks' => [
            'credentials' => $this->validateCredentials(),
            'api_connectivity' => $this->checkApiConnectivity(),
            'model_availability' => $this->checkModelAvailability(),
        ],
        'last_checked' => now()->toISOString(),
    ];
}
```

## Common Anti-Patterns to Avoid

### Don't Mix Concerns
```php
// Avoid: Mixed concerns
class OpenAIDriver
{
    public function sendMessage() { }
    public function saveToDatabase() { } // Database concern
    public function sendEmail() { } // Email concern
}
```

### Don't Hard-Code Values
```php
// Avoid: Hard-coded values
public function getModels()
{
    return ['gpt-4', 'gpt-3.5-turbo']; // Hard-coded
}
```

### Don't Ignore Errors
```php
// Avoid: Ignoring errors
try {
    $response = $this->client->chat()->create($data);
} catch (\Exception $e) {
    // Silently ignore errors
}
```

### Don't Skip Validation
```php
// Avoid: No validation
public function sendMessage($message)
{
    return $this->client->chat()->create([
        'messages' => [$message], // No validation
    ]);
}
```

## Related Documentation

- **[Driver Interface](03-Interface.md)**: Complete interface specification
- **[Error Handling](11-Error-Handling.md)**: Error handling patterns
- **[Testing Strategy](12-Testing.md)**: Testing best practices
- **[Performance](15-Performance.md)**: Performance optimization
