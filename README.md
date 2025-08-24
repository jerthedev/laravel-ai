# JTD Laravel AI

[![PHP Version](https://img.shields.io/badge/php-%5E8.1-blue.svg?style=flat-square)](https://php.net)
[![Laravel Version](https://img.shields.io/badge/laravel-%5E10.0%7C%5E11.0%7C%5E12.0-red.svg?style=flat-square)](https://laravel.com)
[![License](https://img.shields.io/badge/license-MIT-green.svg?style=flat-square)](LICENSE.md)
[![Tests](https://github.com/jerthedev/laravel-ai/actions/workflows/tests.yml/badge.svg)](https://github.com/jerthedev/laravel-ai/actions/workflows/tests.yml)
[![codecov](https://codecov.io/gh/jerthedev/laravel-ai/branch/main/graph/badge.svg)](https://codecov.io/gh/jerthedev/laravel-ai)
[![PHPUnit](https://img.shields.io/badge/phpunit-12.3-blue.svg?style=flat-square)](https://phpunit.de)
[![PHPStan Level](https://img.shields.io/badge/phpstan-level%206-brightgreen.svg?style=flat-square)](https://phpstan.org)
[![Code Style](https://img.shields.io/badge/code%20style-laravel%20pint-orange.svg?style=flat-square)](https://laravel.com/docs/pint)

A comprehensive Laravel package that provides a unified, driver-based interface for multiple AI providers with conversation management, cost tracking, and enterprise-level features.

> **Status**: Sprint 2 (OpenAI Driver) is complete! Full OpenAI integration with streaming, function calling, comprehensive error handling, and 95%+ test coverage.

## Features

### ✅ Sprint 1 (Foundation) - Complete
- **Package Foundation**: Complete Laravel package structure with auto-discovery
- **Database Schema**: Full migration system for providers, conversations, messages, and analytics
- **Configuration System**: Environment-driven configuration with validation
- **Driver Architecture**: Extensible driver system with provider registry
- **Core Interfaces**: AIProviderInterface and data transfer objects (AIMessage, AIResponse, TokenUsage)
- **Service Provider & Facade**: Laravel service provider with AI facade
- **Mock Provider**: Comprehensive testing provider with configurable responses
- **Testing Framework**: 260+ tests with 81%+ code coverage using PHPUnit and Orchestra Testbench
- **CI/CD Pipeline**: GitHub Actions with multi-version testing, static analysis, and code coverage

### ✅ Sprint 2 (OpenAI Driver) - Complete
- **OpenAI Integration**: Full OpenAI API integration with Chat Completions, Models, and Streaming
- **Streaming Support**: Real-time streaming responses with chunk processing
- **Function Calling**: Complete function calling support with parallel execution
- **Error Handling**: Comprehensive error handling with retry logic and exponential backoff
- **Cost Calculation**: Accurate cost tracking for all OpenAI models with real-time pricing
- **Model Management**: Automatic model synchronization and capability detection
- **Event System**: Event-driven architecture for monitoring and observability
- **Security**: Credential masking, validation, and comprehensive security review
- **Testing**: 95%+ test coverage with unit, integration, and E2E tests
- **Performance**: Optimized for speed with <0.1s individual test performance

### 🚧 Upcoming Features
- **Additional Providers**: xAI, Gemini, Ollama implementations
- **Conversation Management**: Enhanced persistent conversation threads
- **Advanced Analytics**: Detailed usage analytics and cost reporting
- **MCP Integration**: Model Context Protocol server support with Sequential Thinking
- **Admin Dashboard**: Web interface for monitoring and management

## Quick Start

```bash
composer require jerthedev/laravel-ai
php artisan vendor:publish --provider="JTD\LaravelAI\LaravelAIServiceProvider"
php artisan migrate
```

Configure your AI providers in `.env`:

```env
# Default provider (use 'openai' for production)
AI_DEFAULT_PROVIDER=openai
AI_COST_TRACKING_ENABLED=true
AI_MODEL_SYNC_ENABLED=true

# OpenAI Configuration (Sprint 2 - Complete)
OPENAI_API_KEY=your-openai-api-key
OPENAI_ORGANIZATION=your-org-id  # Optional
OPENAI_PROJECT=your-project-id   # Optional

# Future provider configurations
# AI_XAI_API_KEY=your-xai-key
# AI_GEMINI_API_KEY=your-gemini-key
```

## Route Configuration

The package provides configurable API and web routes for monitoring and management. **Routes are production-safe by default** with sensitive endpoints disabled.

### Production Configuration (Recommended)

```env
# Enable basic monitoring with secure defaults
AI_ROUTES_ENABLED=true
AI_API_PREFIX=ai-admin
AI_PERFORMANCE_DASHBOARD_ENABLED=false  # Disabled for security
AI_COST_ANALYTICS_ENABLED=false         # Disabled for security
AI_WEB_ROUTES_ENABLED=false             # Disabled for security
```

### Available Endpoints

With default configuration, these endpoints are available:

- `GET /ai-admin/system/health` - System health check
- `GET /ai-admin/performance/alerts` - Performance alerts
- `POST /ai-admin/performance/alerts/{id}/acknowledge` - Acknowledge alerts
- `GET /ai-admin/costs/current` - Current usage summary
- `GET /ai-admin/mcp/status` - MCP server status

### Development Configuration

For development environments, enable additional features:

```env
AI_PERFORMANCE_DASHBOARD_ENABLED=true
AI_COST_ANALYTICS_ENABLED=true
AI_WEB_ROUTES_ENABLED=true
```

See [Route Configuration Documentation](docs/ROUTES_CONFIGURATION.md) for complete details.

## OpenAI Usage Examples

### Basic Chat Completion

```php
use JTD\LaravelAI\Facades\AI;
use JTD\LaravelAI\Models\AIMessage;

// Simple message
$response = AI::provider('openai')
    ->sendMessage(
        AIMessage::user('Explain Laravel in one sentence'),
        ['model' => 'gpt-4']
    );

echo $response->content; // AI response
echo $response->tokenUsage->totalTokens; // Token count
echo $response->cost; // Cost in USD
```

### Conversation Management

```php
// Create a conversation with context
$messages = [
    AIMessage::system('You are a helpful Laravel expert.'),
    AIMessage::user('How do I create a middleware?'),
];

$response = AI::provider('openai')->sendMessages($messages, [
    'model' => 'gpt-4-turbo',
    'temperature' => 0.7,
    'max_tokens' => 1000,
]);

// Continue the conversation
$messages[] = AIMessage::assistant($response->content);
$messages[] = AIMessage::user('Can you show me an example?');

$followUp = AI::provider('openai')->sendMessages($messages);
```

### Streaming Responses

```php
// Stream responses in real-time
$stream = AI::provider('openai')->sendStreamingMessage(
    AIMessage::user('Write a story about Laravel'),
    ['model' => 'gpt-4']
);

foreach ($stream as $chunk) {
    echo $chunk->content; // Print each chunk as it arrives

    if ($chunk->finishReason === 'stop') {
        echo "\n\nFinal cost: $" . $chunk->cost;
        break;
    }
}
```

### Function Calling

```php
// Define functions the AI can call
$functions = [
    [
        'name' => 'get_weather',
        'description' => 'Get current weather for a location',
        'parameters' => [
            'type' => 'object',
            'properties' => [
                'location' => [
                    'type' => 'string',
                    'description' => 'City name',
                ],
                'unit' => [
                    'type' => 'string',
                    'enum' => ['celsius', 'fahrenheit'],
                    'description' => 'Temperature unit',
                ],
            ],
            'required' => ['location'],
        ],
    ],
];

// Send message with function definitions
$response = AI::provider('openai')->sendMessage(
    AIMessage::user('What\'s the weather in Paris?'),
    [
        'model' => 'gpt-4',
        'tools' => array_map(fn($func) => ['type' => 'function', 'function' => $func], $functions),
        'tool_choice' => 'auto',
    ]
);

// Handle function calls
if ($response->hasToolCalls()) {
    foreach ($response->toolCalls as $toolCall) {
        if ($toolCall->function->name === 'get_weather') {
            $args = json_decode($toolCall->function->arguments, true);
            $weather = getWeatherData($args['location'], $args['unit'] ?? 'celsius');

            // Send function result back to AI
            $messages = [
                AIMessage::user('What\'s the weather in Paris?'),
                AIMessage::assistant('', null, null, null, null, null, $response->toolCalls),
                AIMessage::tool($toolCall->id, json_encode($weather)),
            ];

            $finalResponse = AI::provider('openai')->sendMessages($messages);
            echo $finalResponse->content; // AI's response using weather data
        }
    }
}
```

### Advanced Configuration

```php
// Configure OpenAI driver with all options
$response = AI::provider('openai')->sendMessage(
    AIMessage::user('Explain quantum computing'),
    [
        'model' => 'gpt-4-turbo',
        'temperature' => 0.3,           // Lower for more focused responses
        'max_tokens' => 2000,           // Limit response length
        'top_p' => 0.9,                 // Nucleus sampling
        'frequency_penalty' => 0.1,     // Reduce repetition
        'presence_penalty' => 0.1,      // Encourage topic diversity
        'stop' => ["\n\n", "---"],      // Stop sequences
        'user' => 'user-123',           // User identifier for abuse monitoring
    ]
);
```

### Error Handling

```php
use JTD\LaravelAI\Exceptions\OpenAI\OpenAIRateLimitException;
use JTD\LaravelAI\Exceptions\OpenAI\OpenAIQuotaExceededException;
use JTD\LaravelAI\Exceptions\OpenAI\OpenAIInvalidCredentialsException;

try {
    $response = AI::provider('openai')->sendMessage(
        AIMessage::user('Hello, world!')
    );
} catch (OpenAIRateLimitException $e) {
    // Handle rate limiting - includes retry-after information
    $retryAfter = $e->getRetryAfter();
    Log::warning("Rate limited, retry after {$retryAfter} seconds");
} catch (OpenAIQuotaExceededException $e) {
    // Handle quota exceeded
    Log::error('OpenAI quota exceeded', ['details' => $e->getDetails()]);
} catch (OpenAIInvalidCredentialsException $e) {
    // Handle authentication errors
    Log::error('Invalid OpenAI credentials');
} catch (\Exception $e) {
    // Handle other errors
    Log::error('AI request failed', ['error' => $e->getMessage()]);
}
```

### Model Management

```php
// Get available models
$models = AI::provider('openai')->getAvailableModels();

foreach ($models as $model) {
    echo "Model: {$model['id']}\n";
    echo "Context Length: {$model['context_length']}\n";
    echo "Capabilities: " . implode(', ', $model['capabilities']) . "\n\n";
}

// Sync models from OpenAI API
$syncResult = AI::provider('openai')->syncModels();
echo "Synced {$syncResult['synced']} models\n";
```

### Cost Tracking and Analytics

```php
use JTD\LaravelAI\Models\TokenUsage;

// Calculate cost before sending
$estimatedTokens = AI::provider('openai')->estimateTokens(
    [AIMessage::user('Long message here...')],
    'gpt-4'
);
$estimatedCost = AI::provider('openai')->calculateCost($estimatedTokens, 'gpt-4');

echo "Estimated cost: $" . number_format($estimatedCost, 4);

// Track actual usage
$response = AI::provider('openai')->sendMessage(AIMessage::user('Hello'));

echo "Actual tokens: {$response->tokenUsage->totalTokens}\n";
echo "Actual cost: $" . number_format($response->cost, 4) . "\n";
echo "Input tokens: {$response->tokenUsage->inputTokens}\n";
echo "Output tokens: {$response->tokenUsage->outputTokens}\n";
```

### Event-Driven Monitoring

```php
use JTD\LaravelAI\Events\MessageSent;
use JTD\LaravelAI\Events\ResponseGenerated;
use JTD\LaravelAI\Events\CostCalculated;

// Listen for AI events
Event::listen(MessageSent::class, function (MessageSent $event) {
    Log::info('AI message sent', [
        'provider' => $event->provider,
        'model' => $event->options['model'] ?? 'default',
        'message_count' => count($event->messages),
    ]);
});

Event::listen(CostCalculated::class, function (CostCalculated $event) {
    // Track costs in your analytics system
    Analytics::track('ai_cost', [
        'provider' => $event->provider,
        'cost' => $event->cost,
        'tokens' => $event->tokenUsage->totalTokens,
    ]);
});
```

## Architecture Overview

### Driver System
The package uses a driver-based architecture similar to Laravel's database connections:

```php
// Register custom drivers
AI::extend('custom-provider', function ($app, $config) {
    return new CustomAIProvider($config);
});

// Use different providers
$response = AI::conversation()
    ->provider('custom-provider')
    ->message('Hello')
    ->send();
```

### Configuration Structure
```php
// config/ai.php
return [
    'default' => env('AI_DEFAULT_PROVIDER', 'openai'),

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

        'mock' => [
            'driver' => 'mock',
            'responses' => [
                'default' => 'Mock response to: {message}',
                'error' => 'Mock error response',
            ],
        ],
    ],
];
```

### Database Schema
The package includes comprehensive migrations for:
- `ai_providers` - Provider configurations
- `ai_accounts` - Provider account credentials
- `ai_provider_models` - Available models per provider
- `ai_provider_model_costs` - Pricing information
- `ai_conversations` - Conversation threads
- `ai_messages` - Individual messages
- `ai_usage_analytics` - Usage statistics

## Development Status

### Sprint 1: Foundation ✅ Complete
- [x] Package structure and auto-discovery
- [x] Database migrations and schema
- [x] Configuration system with validation
- [x] Core interfaces and contracts
- [x] Service provider and facade
- [x] Driver system foundation
- [x] Mock provider for testing
- [x] Comprehensive test suite (260+ tests, 81%+ coverage)
- [x] CI/CD pipeline with GitHub Actions
- [x] Static analysis with PHPStan
- [x] Code style enforcement with Pint

### Sprint 2: OpenAI Driver ✅ Complete
- [x] OpenAI driver implementation with full API integration
- [x] Streaming response support with real-time chunks
- [x] Function calling with parallel execution
- [x] Comprehensive error handling and retry logic
- [x] Cost calculation with real-time pricing
- [x] Model synchronization and capability detection
- [x] Event-driven architecture for monitoring
- [x] Security review and credential protection
- [x] 95%+ test coverage with unit, integration, and E2E tests
- [x] Performance optimization (<0.1s per test)

### Sprint 3: Additional Providers (Planned)
- [ ] xAI (Grok) driver implementation
- [ ] Google Gemini driver implementation
- [ ] Ollama driver implementation
- [ ] Provider comparison and benchmarking
- [ ] Multi-provider conversation continuity

### Sprint 4: Advanced Features (Planned)
- [ ] Enhanced conversation persistence and management
- [ ] Advanced cost tracking and analytics dashboard
- [ ] Model Context Protocol (MCP) integration
- [ ] Background job processing optimization
- [ ] Webhook integrations and real-time notifications
- [ ] Admin dashboard with monitoring and controls

## Requirements

- PHP 8.1+
- Laravel 10.0+

## Installation

You can install the package via composer:

```bash
composer require jerthedev/laravel-ai
```

Publish the configuration file and migrations:

```bash
php artisan vendor:publish --provider="JTD\LaravelAI\LaravelAIServiceProvider"
```

Run the migrations:

```bash
php artisan migrate
```

## Configuration

The package uses a driver-based configuration system similar to Laravel's database connections. Configure your providers in `config/ai.php` and set your API keys in `.env`.

## Testing

### E2E Testing Setup

Set up E2E testing with real OpenAI API credentials:

```bash
# Interactive setup command
php artisan ai:setup-e2e

# Or manually create credentials file
cp tests/credentials/e2e-credentials.example.json tests/credentials/e2e-credentials.json
# Edit with your real API keys
```

The credentials file is automatically excluded from Git for security.

### Running Tests

The package includes a comprehensive test suite with 400+ tests covering:

```bash
# Run all tests (unit + integration)
composer test

# Run only unit tests (fast)
composer test -- --group=unit

# Run E2E tests (requires credentials)
composer test -- --group=e2e

# Run tests with coverage
composer test-coverage

# Run static analysis
composer analyse

# Fix code style
composer format

# Performance testing
php scripts/test-performance.php
```

### Test Categories
- **Unit Tests**: Individual class and method testing (400+ tests)
- **Integration Tests**: Component interaction testing
- **E2E Tests**: Real API testing with OpenAI (conditional)
- **Performance Tests**: Speed and memory optimization
- **Security Tests**: Credential handling and validation

### CI/CD Pipeline
- Automated testing on PHP 8.1-8.4 and Laravel 10.x-11.x
- Code coverage reporting with Codecov
- Static analysis with PHPStan (level 6)
- Code style enforcement with Laravel Pint
- Security auditing with Composer audit

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Jeremy Fall](https://github.com/jerthedev)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
