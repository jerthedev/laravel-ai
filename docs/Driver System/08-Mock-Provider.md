# Mock Provider

## Overview

The Mock Provider is a testing and development provider that simulates AI responses without making actual API calls. It's essential for unit testing, development environments, and CI/CD pipelines where real API calls would be impractical or expensive.

## Features

- **No API Calls**: Simulates responses without external dependencies
- **Configurable Responses**: Customize responses for different test scenarios
- **Fixture Support**: Load realistic responses from fixture files
- **Error Simulation**: Test error handling scenarios
- **Performance Testing**: Measure performance without API latency
- **Cost Tracking**: Simulate cost calculations for testing

## Configuration

### Basic Configuration

```php
'mock' => [
    'driver' => 'mock',
    'valid_credentials' => true,
    'default_model' => 'mock-gpt-4',
    'response_delay' => 0, // Simulate API delay in milliseconds
    'mock_responses' => [
        'default' => 'This is a mock response from the AI provider.',
    ],
    'error_rate' => 0.0, // Simulate random errors (0.0 = no errors, 1.0 = always error)
],
```

### Advanced Configuration

```php
'mock' => [
    'driver' => 'mock',
    'valid_credentials' => true,
    'default_model' => 'mock-gpt-4',
    'response_delay' => 100,
    'mock_responses' => [
        'default' => 'This is a mock response.',
        'greeting' => 'Hello! How can I help you today?',
        'code_help' => 'Here\'s a code example: ```php\necho "Hello World";\n```',
        'error' => 'SIMULATE_ERROR', // Special keyword to trigger errors
    ],
    'models' => [
        [
            'id' => 'mock-gpt-4',
            'name' => 'Mock GPT-4',
            'type' => 'chat',
            'capabilities' => ['function_calling', 'vision'],
            'context_length' => 8192,
            'owned_by' => 'mock',
        ],
        [
            'id' => 'mock-gpt-3.5-turbo',
            'name' => 'Mock GPT-3.5 Turbo',
            'type' => 'chat',
            'capabilities' => ['function_calling'],
            'context_length' => 4096,
            'owned_by' => 'mock',
        ],
    ],
    'pricing' => [
        'mock-gpt-4' => ['prompt' => 0.03, 'completion' => 0.06],
        'mock-gpt-3.5-turbo' => ['prompt' => 0.001, 'completion' => 0.002],
    ],
],
```

## Usage Examples

### Basic Testing

```php
use JTD\LaravelAI\Facades\AI;
use JTD\LaravelAI\Models\AIMessage;

// Configure mock provider
config(['ai.default' => 'mock']);

// Send a message
$response = AI::sendMessage(
    AIMessage::user('Hello, AI!')
);

echo $response->content; // "This is a mock response from the AI provider."
```

### Custom Response Testing

```php
// Set custom response for testing
config([
    'ai.providers.mock.mock_responses.default' => 'Custom test response'
]);

$response = AI::provider('mock')->sendMessage(
    AIMessage::user('Test message')
);

echo $response->content; // "Custom test response"
```

### Error Simulation

```php
// Configure error simulation
config([
    'ai.providers.mock.error_rate' => 0.5, // 50% error rate
    'ai.providers.mock.mock_responses.default' => 'SIMULATE_ERROR'
]);

try {
    $response = AI::provider('mock')->sendMessage(
        AIMessage::user('This might fail')
    );
} catch (\JTD\LaravelAI\Exceptions\ProviderException $e) {
    echo "Simulated error: " . $e->getMessage();
}
```

### Streaming Simulation

```php
$stream = AI::provider('mock')->sendStreamingMessage(
    AIMessage::user('Stream this response')
);

foreach ($stream as $chunk) {
    echo $chunk->content;
    usleep(50000); // Simulate streaming delay
}
```

## Fixture System

### Loading Provider Fixtures

The mock provider can load realistic responses from other providers:

```php
use JTD\LaravelAI\Providers\MockProvider;

$mockProvider = new MockProvider($config);

// Load OpenAI-like responses
$mockProvider->loadFixtures('openai');

// Load Gemini-like responses
$mockProvider->loadFixtures('gemini');
```

### Custom Fixtures

Create custom fixture files in `tests/fixtures/`:

```php
// tests/fixtures/custom-responses.php
return [
    'chat_completion' => [
        'id' => 'chatcmpl-test123',
        'object' => 'chat.completion',
        'created' => time(),
        'model' => 'mock-gpt-4',
        'choices' => [
            [
                'index' => 0,
                'message' => [
                    'role' => 'assistant',
                    'content' => 'This is a custom fixture response.',
                ],
                'finish_reason' => 'stop',
            ],
        ],
        'usage' => [
            'prompt_tokens' => 10,
            'completion_tokens' => 20,
            'total_tokens' => 30,
        ],
    ],
];
```

## Testing Patterns

### Unit Test Example

```php
<?php

namespace Tests\Unit;

use Tests\TestCase;
use JTD\LaravelAI\Facades\AI;
use JTD\LaravelAI\Models\AIMessage;

class AIServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Use mock provider for testing
        config(['ai.default' => 'mock']);
    }

    public function test_sends_message_successfully(): void
    {
        // Configure expected response
        config([
            'ai.providers.mock.mock_responses.default' => 'Test response'
        ]);

        $response = AI::sendMessage(
            AIMessage::user('Test message')
        );

        $this->assertEquals('Test response', $response->content);
        $this->assertEquals('assistant', $response->role);
        $this->assertEquals('mock-gpt-4', $response->model);
    }

    public function test_handles_errors_gracefully(): void
    {
        // Configure error simulation
        config([
            'ai.providers.mock.mock_responses.default' => 'SIMULATE_ERROR'
        ]);

        $this->expectException(\JTD\LaravelAI\Exceptions\ProviderException::class);

        AI::sendMessage(AIMessage::user('This will fail'));
    }

    public function test_calculates_costs_correctly(): void
    {
        $usage = new \JTD\LaravelAI\Models\TokenUsage(
            promptTokens: 100,
            completionTokens: 50,
            totalTokens: 150
        );

        $cost = AI::provider('mock')->calculateCost($usage, 'mock-gpt-4');

        // Based on mock pricing: (100/1000 * 0.03) + (50/1000 * 0.06) = 0.006
        $this->assertEquals(0.006, $cost);
    }
}
```

### Feature Test Example

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use JTD\LaravelAI\Facades\AI;
use JTD\LaravelAI\Models\AIMessage;

class ConversationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['ai.default' => 'mock']);
    }

    public function test_conversation_flow(): void
    {
        // Configure sequential responses
        config([
            'ai.providers.mock.mock_responses' => [
                'greeting' => 'Hello! How can I help you?',
                'help' => 'I can assist with various tasks.',
                'goodbye' => 'Goodbye! Have a great day!',
            ]
        ]);

        $messages = [
            AIMessage::user('Hello'),
            AIMessage::assistant('Hello! How can I help you?'),
            AIMessage::user('What can you do?'),
        ];

        $response = AI::sendMessages($messages);

        $this->assertNotEmpty($response->content);
        $this->assertEquals('assistant', $response->role);
    }
}
```

## Advanced Features

### Response Patterns

Configure different responses based on message content:

```php
'mock_responses' => [
    'pattern:hello' => 'Hello! How can I help you?',
    'pattern:code' => 'Here\'s a code example: ```php\necho "Hello";\n```',
    'pattern:error' => 'SIMULATE_ERROR',
    'default' => 'I don\'t understand that request.',
],
```

### Function Calling Simulation

```php
'function_responses' => [
    'get_weather' => [
        'location' => 'Paris',
        'temperature' => '22°C',
        'condition' => 'Sunny',
    ],
    'calculate' => [
        'result' => 42,
    ],
],
```

### Streaming Simulation

```php
'streaming' => [
    'enabled' => true,
    'chunk_size' => 10, // Characters per chunk
    'delay' => 50, // Milliseconds between chunks
],
```

## Performance Testing

### Benchmark Without API Latency

```php
public function test_performance_without_api_calls(): void
{
    config(['ai.default' => 'mock']);
    
    $start = microtime(true);
    
    for ($i = 0; $i < 100; $i++) {
        AI::sendMessage(AIMessage::user("Message {$i}"));
    }
    
    $duration = microtime(true) - $start;
    
    // Should be very fast without API calls
    $this->assertLessThan(1.0, $duration);
}
```

### Memory Usage Testing

```php
public function test_memory_usage(): void
{
    config(['ai.default' => 'mock']);
    
    $initialMemory = memory_get_usage();
    
    for ($i = 0; $i < 1000; $i++) {
        AI::sendMessage(AIMessage::user("Message {$i}"));
    }
    
    $finalMemory = memory_get_usage();
    $memoryIncrease = $finalMemory - $initialMemory;
    
    // Memory increase should be reasonable
    $this->assertLessThan(10 * 1024 * 1024, $memoryIncrease); // 10MB
}
```

## CI/CD Integration

### GitHub Actions Example

```yaml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    
    steps:
    - uses: actions/checkout@v2
    
    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: 8.2
    
    - name: Install dependencies
      run: composer install
    
    - name: Run tests with mock provider
      run: |
        export AI_DEFAULT_PROVIDER=mock
        vendor/bin/phpunit
```

### Environment Configuration

```env
# .env.testing
AI_DEFAULT_PROVIDER=mock
AI_LOGGING_ENABLED=false
MOCK_RESPONSE_DELAY=0
MOCK_ERROR_RATE=0.0
```

## Best Practices

### Testing Strategy
- Use mock provider for unit tests
- Use real providers for E2E tests
- Test both success and error scenarios
- Validate response structure and content

### Configuration Management
- Keep mock responses realistic
- Use fixtures for complex scenarios
- Configure appropriate delays for performance tests
- Simulate various error conditions

### Development Workflow
- Start development with mock provider
- Switch to real providers for integration testing
- Use mock provider in CI/CD pipelines
- Keep mock responses updated with real API changes

## Limitations

### What Mock Provider Cannot Test
- Real API authentication issues
- Actual rate limiting behavior
- Network connectivity problems
- Provider-specific error responses
- Real token usage and costs

### When to Use Real Providers
- Final integration testing
- Performance testing with real latency
- Authentication validation
- Rate limiting behavior
- Cost calculation accuracy

## Related Documentation

- **[Configuration System](02-Configuration.md)**: Setting up mock provider
- **[Testing Strategy](12-Testing.md)**: Comprehensive testing approach
- **[E2E Testing Setup](13-E2E-Setup.md)**: Real API testing
- **[OpenAI Driver](07-OpenAI-Driver.md)**: Comparison with real provider
