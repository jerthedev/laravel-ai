# JTD Laravel AI

[![PHP Version](https://img.shields.io/badge/php-%5E8.1-blue.svg?style=flat-square)](https://php.net)
[![Laravel Version](https://img.shields.io/badge/laravel-%5E10.0%7C%5E11.0%7C%5E12.0-red.svg?style=flat-square)](https://laravel.com)
[![License](https://img.shields.io/badge/license-MIT-green.svg?style=flat-square)](LICENSE.md)
[![Tests](https://github.com/jerthedev/laravel-ai/actions/workflows/tests.yml/badge.svg)](https://github.com/jerthedev/laravel-ai/actions/workflows/tests.yml)
[![codecov](https://codecov.io/gh/jerthedev/laravel-ai/branch/main/graph/badge.svg)](https://codecov.io/gh/jerthedev/laravel-ai)

A unified Laravel package for multiple AI providers with a clean, Laravel-native API. Send messages, stream responses, and manage conversations with OpenAI, xAI, Gemini, and more.



## Quick Start

### Installation

```bash
composer require jerthedev/laravel-ai
php artisan vendor:publish --provider="JTD\LaravelAI\LaravelAIServiceProvider"
php artisan migrate
```

### Configuration

Add your AI provider credentials to `.env`:

```env
# Default provider
AI_DEFAULT_PROVIDER=openai

# OpenAI
OPENAI_API_KEY=your-openai-api-key
OPENAI_ORGANIZATION=your-org-id  # Optional
OPENAI_PROJECT=your-project-id   # Optional

# xAI (Grok)
AI_XAI_API_KEY=your-xai-key

# Google Gemini
AI_GEMINI_API_KEY=your-gemini-key
```

## Usage

### Basic Message Sending

```php
use JTD\LaravelAI\Facades\AI;
use JTD\LaravelAI\Models\AIMessage;

// Using default provider
$response = AI::sendMessage(
    AIMessage::user('Explain Laravel in one sentence'),
    ['model' => 'gpt-4']
);

echo $response->content; // AI response
echo $response->tokenUsage->totalTokens; // Token count

// Using specific provider
$response = AI::provider('openai')->sendMessage(
    AIMessage::user('Hello, world!'),
    ['model' => 'gpt-4']
);
```

### Streaming Responses

```php
// Stream with default provider
foreach (AI::sendStreamingMessage(AIMessage::user('Write a story')) as $chunk) {
    echo $chunk->content; // Print each chunk as it arrives
}

// Stream with specific provider
foreach (AI::provider('openai')->sendStreamingMessage(AIMessage::user('Write a story')) as $chunk) {
    echo $chunk->content;

    if ($chunk->finishReason === 'stop') {
        echo "\nFinal cost: $" . $chunk->cost;
        break;
    }
}
```

### Multiple Providers

```php
// Switch between providers easily
$openaiResponse = AI::provider('openai')->sendMessage(
    AIMessage::user('Explain quantum computing'),
    ['model' => 'gpt-4']
);

$xaiResponse = AI::provider('xai')->sendMessage(
    AIMessage::user('Explain quantum computing'),
    ['model' => 'grok-beta']
);

$geminiResponse = AI::provider('gemini')->sendMessage(
    AIMessage::user('Explain quantum computing'),
    ['model' => 'gemini-pro']
);
```

### Conversation Builder

```php
// Fluent conversation interface
$response = AI::conversation()
    ->provider('openai')
    ->model('gpt-4')
    ->temperature(0.7)
    ->message('You are a helpful Laravel expert.')
    ->message('How do I create a middleware?')
    ->send();

// Continue the conversation
$followUp = AI::conversation()
    ->provider('openai')
    ->message('Can you show me an example?')
    ->send();

// Streaming conversations
foreach (AI::conversation()->message('Write a story')->stream() as $chunk) {
    echo $chunk->content;
}
```

### Advanced Options

```php
// Configure providers with advanced options
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

// Multi-message conversations
$messages = [
    AIMessage::system('You are a helpful Laravel expert.'),
    AIMessage::user('How do I create a middleware?'),
];

$response = AI::provider('openai')->sendMessage($messages, [
    'model' => 'gpt-4-turbo',
    'temperature' => 0.7,
    'max_tokens' => 1000,
]);
```

### Error Handling

```php
use JTD\LaravelAI\Exceptions\RateLimitException;
use JTD\LaravelAI\Exceptions\InvalidCredentialsException;
use JTD\LaravelAI\Exceptions\ProviderException;

try {
    $response = AI::sendMessage(AIMessage::user('Hello, world!'));
} catch (RateLimitException $e) {
    // Handle rate limiting - includes retry-after information
    $retryAfter = $e->getRetryAfter();
    Log::warning("Rate limited, retry after {$retryAfter} seconds");
} catch (InvalidCredentialsException $e) {
    // Handle authentication errors
    Log::error('Invalid AI provider credentials');
} catch (ProviderException $e) {
    // Handle provider-specific errors
    Log::error('AI provider error', ['details' => $e->getDetails()]);
} catch (\Exception $e) {
    // Handle other errors
    Log::error('AI request failed', ['error' => $e->getMessage()]);
}
```

## Middleware System

The Laravel AI package includes a powerful middleware system for intercepting and transforming AI requests. Middleware operates in a Laravel-familiar pipeline pattern, enabling budget enforcement, cost tracking, performance monitoring, and custom request processing.

### Available Middleware

#### Budget Enforcement Middleware
Prevents AI spending from exceeding configured limits with real-time budget checking:

```php
use JTD\LaravelAI\Services\BudgetService;

// Set budget limits
$budgetService = app(BudgetService::class);
$budgetService->setBudgetLimit($userId, 'daily', 50.00);
$budgetService->setBudgetLimit($userId, 'monthly', 500.00);
$budgetService->setProjectBudgetLimit($projectId, 1000.00);

// Budget enforcement runs automatically as global middleware
$response = AI::conversation()->send('Generate a report');
```

#### Cost Tracking Middleware
Automatically calculates and tracks costs for all AI requests:

```php
// Cost tracking runs automatically and fires events
$response = AI::sendMessage(AIMessage::user('Hello'));

// Access cost information
echo $response->cost; // e.g., 0.0025
echo $response->tokenUsage->totalTokens; // e.g., 150
```

#### Performance Monitoring Middleware
Tracks request performance and logs slow operations:

```php
// Available for selective use
$response = AI::conversation()
    ->middleware(['performance-monitoring'])
    ->send('Analyze this data');
```

### Usage Patterns

#### ConversationBuilder Pattern
Apply middleware to specific conversations:

```php
$response = AI::conversation()
    ->middleware(['budget-enforcement', 'performance-monitoring'])
    ->message('Generate marketing content')
    ->send();
```

#### Direct SendMessage Pattern
Specify middleware for individual requests:

```php
$response = AI::provider('openai')->sendMessage('Hello', [
    'middleware' => ['budget-enforcement'],
    'user_id' => $userId,
    'metadata' => [
        'project_id' => $projectId,
        'organization_id' => $organizationId,
    ]
]);
```

#### Global Middleware
Automatically applied to all requests (configured in `config/ai.php`):

```php
// Global middleware runs for every AI request
'global' => [
    'budget-enforcement' => [...],
    'cost-tracking' => [...],
],
```

### Configuration

Configure middleware behavior in `config/ai.php`:

```php
'middleware' => [
    'enabled' => true,
    
    // Global middleware (runs for all requests)
    'global' => [
        'budget-enforcement' => [
            'class' => BudgetEnforcementMiddleware::class,
            'strict_mode' => false,
            'cache_ttl' => 300,
            'fail_open' => true,
        ],
    ],
    
    // Available middleware (selective use)
    'available' => [
        'budget-enforcement' => BudgetEnforcementMiddleware::class,
        'cost-tracking' => CostTrackingMiddleware::class,
        'performance-monitoring' => PerformanceMonitoringMiddleware::class,
    ],
    
    // Performance settings
    'performance' => [
        'track_execution_time' => true,
        'slow_threshold_ms' => 100,
        'stack_target_ms' => 10,
    ],
];
```

### Budget Management

Set and manage budget limits:

```php
use JTD\LaravelAI\Services\BudgetService;

$budgetService = app(BudgetService::class);

// Set user budget limits
$budgetService->setBudgetLimit($userId, 'per_request', 5.00);
$budgetService->setBudgetLimit($userId, 'daily', 100.00);
$budgetService->setBudgetLimit($userId, 'monthly', 1000.00);

// Set project budget limits
$budgetService->setProjectBudgetLimit($projectId, 2000.00);

// Set organization budget limits
$budgetService->setOrganizationBudgetLimit($orgId, 10000.00);

// Check current spending
$dailySpent = $budgetService->getDailySpending($userId);
$monthlySpent = $budgetService->getMonthlySpending($userId);
```

### Error Handling

Handle budget exceeded exceptions:

```php
use JTD\LaravelAI\Exceptions\BudgetExceededException;

try {
    $response = AI::conversation()->send('Expensive operation');
} catch (BudgetExceededException $e) {
    echo "Budget limit exceeded: " . $e->getMessage();
    echo "Budget type: " . $e->getBudgetType(); // 'daily', 'monthly', etc.
    echo "Limit: $" . $e->getBudgetLimit();
    echo "Projected spending: $" . $e->getProjectedSpending();
}
```

### Performance Features

The middleware system is optimized for enterprise use:

- **<10ms execution overhead** for the entire middleware stack
- **Intelligent caching** with 5-minute budget cache and 1-minute spending cache
- **Object pooling** for high-traffic scenarios
- **Performance monitoring** with automatic slow operation detection
- **Fail-open approach** to prevent blocking requests on system errors

## Requirements

- PHP 8.1+
- Laravel 10.0+

## Documentation

For detailed documentation, see the `docs/` directory:

- [Driver System](docs/DRIVER_SYSTEM.md) - How to create custom AI providers
- [Configuration](docs/CONFIGURATION.md) - Advanced configuration options
- [Events](docs/EVENTS.md) - Event-driven monitoring and analytics
- [Testing](docs/TESTING.md) - Testing strategies and E2E setup
- [Contributing](CONTRIBUTING.md) - Development guidelines

## Testing

```bash
# Run all tests
composer test

# Run with coverage
composer test-coverage

# Run static analysis
composer analyse

# Fix code style
composer format
```

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
