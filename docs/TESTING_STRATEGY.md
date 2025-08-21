# Testing Strategy

## Overview

JTD Laravel AI follows a comprehensive testing strategy with multiple layers of testing to ensure reliability, performance, and correctness. The testing approach includes unit tests, integration tests, feature tests, and end-to-end tests with extensive mocking capabilities.

## Testing Architecture

### Test Structure

```
tests/
├── Unit/
│   ├── Drivers/
│   ├── Services/
│   ├── Models/
│   └── Facades/
├── Feature/
│   ├── Conversations/
│   ├── Providers/
│   ├── CostTracking/
│   └── Analytics/
├── Integration/
│   ├── Providers/
│   ├── Database/
│   └── Queue/
└── Mocks/
    ├── Providers/
    ├── Responses/
    └── Fixtures/
```

### Test Categories

1. **Unit Tests**: Test individual classes and methods in isolation
2. **Feature Tests**: Test complete features and user workflows
3. **Integration Tests**: Test interactions between components
4. **Performance Tests**: Test response times and resource usage
5. **Contract Tests**: Test provider API contracts

## Unit Testing

### Driver Testing

```php
<?php

namespace Tests\Unit\Drivers;

use Tests\TestCase;
use JTD\LaravelAI\Drivers\OpenAIDriver;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Models\TokenUsage;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Response;

class OpenAIDriverTest extends TestCase
{
    protected OpenAIDriver $driver;
    protected Factory $httpMock;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->httpMock = $this->mock(Factory::class);
        $this->driver = new OpenAIDriver([
            'api_key' => 'test-key',
            'base_url' => 'https://api.openai.com/v1',
        ]);
    }
    
    public function test_send_message_returns_valid_response(): void
    {
        $this->httpMock
            ->shouldReceive('withHeaders')
            ->andReturnSelf()
            ->shouldReceive('timeout')
            ->andReturnSelf()
            ->shouldReceive('post')
            ->with('https://api.openai.com/v1/chat/completions', Mockery::any())
            ->andReturn(new Response([
                'choices' => [
                    ['message' => ['content' => 'Hello, world!']]
                ],
                'usage' => [
                    'total_tokens' => 10,
                    'prompt_tokens' => 5,
                    'completion_tokens' => 5,
                ],
                'model' => 'gpt-3.5-turbo',
            ]));
        
        $message = new AIMessage('Hello');
        $response = $this->driver->sendMessage($message);
        
        $this->assertEquals('Hello, world!', $response->content);
        $this->assertEquals(10, $response->tokens_used);
        $this->assertEquals('gpt-3.5-turbo', $response->model);
    }
    
    public function test_calculate_cost_returns_correct_amount(): void
    {
        $usage = new TokenUsage(100, 50);
        $cost = $this->driver->calculateCost($usage, 'gpt-4');
        
        // Assuming GPT-4 pricing: $0.03/1K input, $0.06/1K output
        $expectedCost = (100 * 0.00003) + (50 * 0.00006);
        
        $this->assertEquals($expectedCost, $cost);
    }
    
    public function test_validate_credentials_with_valid_key(): void
    {
        $this->httpMock
            ->shouldReceive('withHeaders')
            ->andReturnSelf()
            ->shouldReceive('get')
            ->andReturn(new Response(['data' => []], 200));
        
        $this->assertTrue($this->driver->validateCredentials());
    }
    
    public function test_validate_credentials_with_invalid_key(): void
    {
        $this->httpMock
            ->shouldReceive('withHeaders')
            ->andReturnSelf()
            ->shouldReceive('get')
            ->andReturn(new Response(['error' => 'Invalid API key'], 401));
        
        $this->assertFalse($this->driver->validateCredentials());
    }
}
```

### Service Testing

```php
<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use JTD\LaravelAI\Services\ConversationService;
use JTD\LaravelAI\Models\AIConversation;
use JTD\LaravelAI\Models\AIMessage;

class ConversationServiceTest extends TestCase
{
    protected ConversationService $service;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ConversationService::class);
    }
    
    public function test_create_conversation(): void
    {
        $conversation = $this->service->create([
            'name' => 'Test Conversation',
            'user_id' => 1,
        ]);
        
        $this->assertInstanceOf(AIConversation::class, $conversation);
        $this->assertEquals('Test Conversation', $conversation->name);
        $this->assertEquals(1, $conversation->user_id);
    }
    
    public function test_add_message_to_conversation(): void
    {
        $conversation = AIConversation::factory()->create();
        
        $message = $this->service->addMessage($conversation, [
            'role' => 'user',
            'content' => 'Hello',
        ]);
        
        $this->assertInstanceOf(AIMessage::class, $message);
        $this->assertEquals('user', $message->role);
        $this->assertEquals('Hello', $message->content);
        $this->assertEquals($conversation->id, $message->conversation_id);
    }
    
    public function test_calculate_conversation_cost(): void
    {
        $conversation = AIConversation::factory()
            ->has(AIMessage::factory()->count(3))
            ->create();
        
        $cost = $this->service->calculateTotalCost($conversation);
        
        $this->assertIsFloat($cost);
        $this->assertGreaterThan(0, $cost);
    }
}
```

## Feature Testing

### Conversation Feature Tests

```php
<?php

namespace Tests\Feature\Conversations;

use Tests\TestCase;
use JTD\LaravelAI\Facades\AI;
use JTD\LaravelAI\Models\AIConversation;
use JTD\LaravelAI\Tests\Mocks\MockAIProvider;

class ConversationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Use mock provider for testing
        AI::extend('mock', function ($config) {
            return new MockAIProvider($config);
        });
        
        config(['ai.default' => 'mock']);
    }
    
    public function test_user_can_create_conversation(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        
        $response = $this->postJson('/api/ai/conversations', [
            'name' => 'My AI Chat',
        ]);
        
        $response->assertStatus(201)
            ->assertJsonStructure([
                'id',
                'name',
                'user_id',
                'created_at',
            ]);
        
        $this->assertDatabaseHas('ai_conversations', [
            'name' => 'My AI Chat',
            'user_id' => $user->id,
        ]);
    }
    
    public function test_user_can_send_message(): void
    {
        $user = User::factory()->create();
        $conversation = AIConversation::factory()->create(['user_id' => $user->id]);
        
        $this->actingAs($user);
        
        $response = $this->postJson("/api/ai/conversations/{$conversation->id}/messages", [
            'content' => 'Hello, AI!',
        ]);
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'message' => ['id', 'content', 'role'],
                'response' => ['id', 'content', 'role', 'tokens_used', 'cost'],
            ]);
        
        $this->assertDatabaseHas('ai_messages', [
            'conversation_id' => $conversation->id,
            'content' => 'Hello, AI!',
            'role' => 'user',
        ]);
    }
    
    public function test_conversation_tracks_costs(): void
    {
        $user = User::factory()->create();
        $conversation = AIConversation::factory()->create(['user_id' => $user->id]);
        
        $this->actingAs($user);
        
        $this->postJson("/api/ai/conversations/{$conversation->id}/messages", [
            'content' => 'Test message',
        ]);
        
        $conversation->refresh();
        
        $this->assertGreaterThan(0, $conversation->total_cost);
        $this->assertGreaterThan(0, $conversation->message_count);
    }
}
```

### Provider Feature Tests

```php
<?php

namespace Tests\Feature\Providers;

use Tests\TestCase;
use JTD\LaravelAI\Facades\AI;

class ProviderTest extends TestCase
{
    public function test_can_switch_providers(): void
    {
        $response1 = AI::conversation()
            ->provider('mock-openai')
            ->message('Hello')
            ->send();
        
        $response2 = AI::conversation()
            ->provider('mock-gemini')
            ->message('Hello')
            ->send();
        
        $this->assertEquals('mock-openai', $response1->provider);
        $this->assertEquals('mock-gemini', $response2->provider);
    }
    
    public function test_provider_fallback_works(): void
    {
        // Configure fallback chain
        config(['ai.providers.primary.fallback' => 'secondary']);
        
        // Mock primary provider to fail
        AI::extend('failing-provider', function ($config) {
            return new FailingMockProvider($config);
        });
        
        $response = AI::conversation()
            ->providers(['failing-provider', 'mock'])
            ->message('Hello')
            ->send();
        
        $this->assertEquals('mock', $response->provider);
    }
}
```

## Integration Testing

### Database Integration Tests

```php
<?php

namespace Tests\Integration\Database;

use Tests\TestCase;
use JTD\LaravelAI\Models\AIConversation;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Models\AIProvider;

class DatabaseIntegrationTest extends TestCase
{
    public function test_conversation_relationships(): void
    {
        $conversation = AIConversation::factory()
            ->has(AIMessage::factory()->count(5))
            ->create();
        
        $this->assertCount(5, $conversation->messages);
        $this->assertInstanceOf(User::class, $conversation->user);
    }
    
    public function test_cost_aggregation(): void
    {
        $conversation = AIConversation::factory()->create();
        
        AIMessage::factory()->create([
            'conversation_id' => $conversation->id,
            'cost' => 0.01,
        ]);
        
        AIMessage::factory()->create([
            'conversation_id' => $conversation->id,
            'cost' => 0.02,
        ]);
        
        $totalCost = $conversation->messages()->sum('cost');
        
        $this->assertEquals(0.03, $totalCost);
    }
    
    public function test_analytics_queries(): void
    {
        // Create test data
        $user = User::factory()->create();
        $conversation = AIConversation::factory()->create(['user_id' => $user->id]);
        
        AIMessage::factory()->count(10)->create([
            'conversation_id' => $conversation->id,
            'cost' => 0.01,
            'tokens_used' => 100,
        ]);
        
        // Test analytics queries
        $userStats = AI::getUserAnalytics($user, [
            'start_date' => now()->subDays(7),
            'end_date' => now(),
        ]);
        
        $this->assertEquals(0.10, $userStats['total_cost']);
        $this->assertEquals(1000, $userStats['total_tokens']);
        $this->assertEquals(10, $userStats['total_requests']);
    }
}
```

### Queue Integration Tests

```php
<?php

namespace Tests\Integration\Queue;

use Tests\TestCase;
use JTD\LaravelAI\Jobs\SyncModelsJob;
use JTD\LaravelAI\Jobs\CalculateConversationCostsJob;
use Illuminate\Support\Facades\Queue;

class QueueIntegrationTest extends TestCase
{
    public function test_model_sync_job_dispatches(): void
    {
        Queue::fake();
        
        dispatch(new SyncModelsJob('openai'));
        
        Queue::assertPushed(SyncModelsJob::class, function ($job) {
            return $job->provider === 'openai';
        });
    }
    
    public function test_cost_calculation_job_processes(): void
    {
        $conversation = AIConversation::factory()
            ->has(AIMessage::factory()->count(3))
            ->create();
        
        $job = new CalculateConversationCostsJob($conversation->id);
        $job->handle();
        
        $conversation->refresh();
        
        $this->assertGreaterThan(0, $conversation->total_cost);
    }
}
```

## Mock System

### Provider Mocks

```php
<?php

namespace JTD\LaravelAI\Tests\Mocks;

use JTD\LaravelAI\Contracts\AIProviderInterface;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Models\AIResponse;
use JTD\LaravelAI\Models\TokenUsage;
use Illuminate\Support\Collection;

class MockAIProvider implements AIProviderInterface
{
    protected array $config;
    protected array $responses;
    
    public function __construct(array $config)
    {
        $this->config = $config;
        $this->responses = $config['mock_responses'] ?? [];
    }
    
    public function sendMessage(AIMessage $message, array $options = []): AIResponse
    {
        $mockResponse = $this->getMockResponse($message->content);
        
        return new AIResponse([
            'content' => $mockResponse['content'],
            'tokens_used' => $mockResponse['tokens_used'],
            'input_tokens' => $mockResponse['input_tokens'],
            'output_tokens' => $mockResponse['output_tokens'],
            'cost' => $mockResponse['cost'],
            'model' => $options['model'] ?? 'mock-model',
            'provider' => $this->getName(),
            'response_time' => 100,
        ]);
    }
    
    public function getAvailableModels(): Collection
    {
        return collect([
            [
                'id' => 'mock-model-1',
                'name' => 'Mock Model 1',
                'type' => 'chat',
                'context_length' => 4096,
            ],
            [
                'id' => 'mock-model-2',
                'name' => 'Mock Model 2',
                'type' => 'chat',
                'context_length' => 8192,
            ],
        ]);
    }
    
    public function calculateCost(TokenUsage $usage, string $modelId): float
    {
        return ($usage->input_tokens * 0.0001) + ($usage->output_tokens * 0.0002);
    }
    
    public function validateCredentials(): bool
    {
        return $this->config['valid_credentials'] ?? true;
    }
    
    public function getName(): string
    {
        return 'mock';
    }
    
    public function getCapabilities(): array
    {
        return [
            'chat' => true,
            'streaming' => false,
            'functions' => false,
            'vision' => false,
        ];
    }
    
    public function supportsStreaming(): bool
    {
        return false;
    }
    
    public function streamMessage(AIMessage $message, callable $callback, array $options = []): AIResponse
    {
        throw new \Exception('Streaming not supported in mock provider');
    }
    
    public function sendBatch(array $messages, array $options = []): Collection
    {
        return collect($messages)->map(function ($message) use ($options) {
            return $this->sendMessage($message, $options);
        });
    }
    
    public function syncModels(): void
    {
        // Mock implementation
    }
    
    private function getMockResponse(string $input): array
    {
        // Return predefined responses or generate based on input
        if (isset($this->responses[$input])) {
            return $this->responses[$input];
        }
        
        return [
            'content' => "Mock response to: {$input}",
            'tokens_used' => 20,
            'input_tokens' => 10,
            'output_tokens' => 10,
            'cost' => 0.002,
        ];
    }
}
```

### Response Fixtures

```php
<?php

namespace JTD\LaravelAI\Tests\Fixtures;

class ResponseFixtures
{
    public static function openAIResponse(): array
    {
        return [
            'id' => 'chatcmpl-123',
            'object' => 'chat.completion',
            'created' => 1677652288,
            'model' => 'gpt-3.5-turbo',
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'Hello! How can I help you today?',
                    ],
                    'finish_reason' => 'stop',
                ],
            ],
            'usage' => [
                'prompt_tokens' => 9,
                'completion_tokens' => 12,
                'total_tokens' => 21,
            ],
        ];
    }
    
    public static function geminiResponse(): array
    {
        return [
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            ['text' => 'Hello! How can I assist you?'],
                        ],
                    ],
                    'finishReason' => 'STOP',
                ],
            ],
            'usageMetadata' => [
                'promptTokenCount' => 8,
                'candidatesTokenCount' => 10,
                'totalTokenCount' => 18,
            ],
        ];
    }
}
```

## Performance Testing

### Load Testing

```php
<?php

namespace Tests\Performance;

use Tests\TestCase;
use JTD\LaravelAI\Facades\AI;

class LoadTest extends TestCase
{
    public function test_concurrent_conversations(): void
    {
        $startTime = microtime(true);
        $conversations = [];
        
        // Create 100 concurrent conversations
        for ($i = 0; $i < 100; $i++) {
            $conversations[] = AI::conversation("Load Test {$i}")
                ->message('Hello')
                ->send();
        }
        
        $endTime = microtime(true);
        $duration = $endTime - $startTime;
        
        $this->assertLessThan(10, $duration); // Should complete within 10 seconds
        $this->assertCount(100, $conversations);
    }
    
    public function test_memory_usage(): void
    {
        $initialMemory = memory_get_usage();
        
        // Process 1000 messages
        for ($i = 0; $i < 1000; $i++) {
            AI::conversation()->message("Message {$i}")->send();
        }
        
        $finalMemory = memory_get_usage();
        $memoryIncrease = $finalMemory - $initialMemory;
        
        // Memory increase should be reasonable (less than 50MB)
        $this->assertLessThan(50 * 1024 * 1024, $memoryIncrease);
    }
}
```

## Test Configuration

### PHPUnit Configuration

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="./vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         colors="true">
    <testsuites>
        <testsuite name="Unit">
            <directory suffix="Test.php">./tests/Unit</directory>
        </testsuite>
        <testsuite name="Feature">
            <directory suffix="Test.php">./tests/Feature</directory>
        </testsuite>
        <testsuite name="Integration">
            <directory suffix="Test.php">./tests/Integration</directory>
        </testsuite>
    </testsuites>
    <coverage>
        <include>
            <directory suffix=".php">./src</directory>
        </include>
        <exclude>
            <directory>./src/Tests</directory>
        </exclude>
    </coverage>
    <php>
        <env name="APP_ENV" value="testing"/>
        <env name="DB_CONNECTION" value="sqlite"/>
        <env name="DB_DATABASE" value=":memory:"/>
        <env name="AI_DEFAULT_PROVIDER" value="mock"/>
    </php>
</phpunit>
```

### Test Environment Setup

```php
<?php

namespace JTD\LaravelAI\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use JTD\LaravelAI\LaravelAIServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->artisan('migrate', ['--database' => 'testing']);
    }
    
    protected function getPackageProviders($app): array
    {
        return [
            LaravelAIServiceProvider::class,
        ];
    }
    
    protected function getEnvironmentSetUp($app): void
    {
        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        
        config()->set('ai.default', 'mock');
        config()->set('ai.providers.mock', [
            'driver' => 'mock',
            'valid_credentials' => true,
        ]);
    }
}
```

## Continuous Integration

### GitHub Actions Workflow

```yaml
name: Tests

on:
  push:
    branches: [ main, develop ]
  pull_request:
    branches: [ main ]

jobs:
  test:
    runs-on: ubuntu-latest
    
    strategy:
      matrix:
        php: [8.1, 8.2, 8.3]
        laravel: [10.0, 11.0]
    
    steps:
    - uses: actions/checkout@v3
    
    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: ${{ matrix.php }}
        extensions: dom, curl, libxml, mbstring, zip, pcntl, pdo, sqlite, pdo_sqlite
    
    - name: Install dependencies
      run: composer install --prefer-dist --no-progress
    
    - name: Run tests
      run: vendor/bin/phpunit --coverage-clover coverage.xml
    
    - name: Upload coverage
      uses: codecov/codecov-action@v3
      with:
        file: ./coverage.xml
```

## Best Practices

### Testing Guidelines

1. **Test Coverage**: Aim for 90%+ code coverage
2. **Mock External APIs**: Always mock AI provider APIs in tests
3. **Test Edge Cases**: Include tests for error conditions and edge cases
4. **Performance Testing**: Include performance benchmarks
5. **Database Testing**: Test database interactions and migrations
6. **Integration Testing**: Test component interactions
7. **Continuous Testing**: Run tests on every commit
8. **Test Documentation**: Document complex test scenarios
9. **Test Data**: Use factories for consistent test data
10. **Cleanup**: Ensure tests clean up after themselves
