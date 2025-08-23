# API Reference

## Overview

This document provides a comprehensive reference for the JTD Laravel AI package API. The package provides a unified interface for multiple AI providers with comprehensive features including streaming, function calling, cost tracking, and event-driven monitoring.

## Quick Navigation

- [Core Classes](#core-classes)
- [Interfaces](#interfaces)
- [Models](#models)
- [Exceptions](#exceptions)
- [Events](#events)
- [Facades](#facades)
- [Configuration](#configuration)

## Core Classes

### AIManager

The main manager class that handles provider registration and resolution.

```php
use JTD\LaravelAI\AIManager;

$manager = new AIManager($app);
$provider = $manager->provider('openai');
```

**Methods:**
- `provider(string $name): AIProviderInterface` - Get a provider instance
- `getDefaultProvider(): string` - Get the default provider name
- `extend(string $driver, Closure $callback): void` - Register a custom driver

### OpenAIDriver

Production-ready OpenAI API integration with comprehensive features.

```php
use JTD\LaravelAI\Drivers\OpenAI\OpenAIDriver;

$driver = new OpenAIDriver([
    'api_key' => 'sk-your-api-key',
    'organization' => 'org-your-org',
    'project' => 'proj_your-project',
]);
```

**Key Methods:**
- `sendMessage(AIMessage $message, array $options = []): AIResponse`
- `sendMessages(array $messages, array $options = []): AIResponse`
- `sendStreamingMessage(AIMessage $message, array $options = []): \Generator`
- `sendStreamingMessages(array $messages, array $options = []): \Generator`
- `getAvailableModels(): Collection`
- `syncModels(): array`
- `calculateCost(TokenUsage $usage, string $modelId): float`
- `validateCredentials(): array`
- `getCapabilities(): array`

## Interfaces

### AIProviderInterface

The main interface that all AI providers must implement.

```php
namespace JTD\LaravelAI\Contracts;

interface AIProviderInterface
{
    public function sendMessage(AIMessage $message, array $options = []): AIResponse;
    public function sendMessages(array $messages, array $options = []): AIResponse;
    public function sendStreamingMessage(AIMessage $message, array $options = []): \Generator;
    public function sendStreamingMessages(array $messages, array $options = []): \Generator;
    public function getAvailableModels(): Collection;
    public function syncModels(): array;
    public function calculateCost(TokenUsage $usage, string $modelId): float;
    public function validateCredentials(): array;
    public function getCapabilities(): array;
    public function getConfig(): array;
    public function getName(): string;
    public function supportsStreaming(): bool;
    public function supportsFunctionCalling(): bool;
    public function supportsVision(): bool;
}
```

## Models

### AIMessage

Represents a message in an AI conversation.

```php
use JTD\LaravelAI\Models\AIMessage;

// Factory methods
$user = AIMessage::user('Hello, world!');
$system = AIMessage::system('You are a helpful assistant.');
$assistant = AIMessage::assistant('Hello! How can I help you?');
$tool = AIMessage::tool('call_123', json_encode(['result' => 'success']));

// Constructor
$message = new AIMessage(
    role: 'user',
    content: 'Hello, world!',
    contentType: AIMessage::CONTENT_TYPE_TEXT
);
```

**Properties:**
- `string $role` - Message role (system, user, assistant, function, tool)
- `string|array $content` - Message content
- `string $contentType` - Content type (text, image, audio, file, multimodal)
- `?array $attachments` - File attachments
- `?array $toolCalls` - Tool calls made by AI
- `?array $metadata` - Additional metadata
- `?string $name` - Function/tool name

**Constants:**
- `ROLE_SYSTEM`, `ROLE_USER`, `ROLE_ASSISTANT`, `ROLE_FUNCTION`, `ROLE_TOOL`
- `CONTENT_TYPE_TEXT`, `CONTENT_TYPE_IMAGE`, `CONTENT_TYPE_AUDIO`, `CONTENT_TYPE_FILE`, `CONTENT_TYPE_MULTIMODAL`

### AIResponse

Represents a response from an AI provider.

```php
use JTD\LaravelAI\Models\AIResponse;

$response = new AIResponse(
    content: 'Hello! How can I help you?',
    role: 'assistant',
    finishReason: AIResponse::FINISH_REASON_STOP,
    tokenUsage: new TokenUsage(10, 8, 18),
    model: 'gpt-4',
    provider: 'openai'
);
```

**Properties:**
- `string $content` - Generated content
- `string $role` - Response role (usually 'assistant')
- `string $finishReason` - Why the response finished
- `TokenUsage $tokenUsage` - Token usage statistics
- `string $model` - Model used for generation
- `string $provider` - Provider name
- `float $cost` - Cost in USD
- `int $responseTimeMs` - Response time in milliseconds
- `?array $toolCalls` - Tool calls made by AI
- `?array $metadata` - Additional metadata

**Constants:**
- `FINISH_REASON_STOP`, `FINISH_REASON_LENGTH`, `FINISH_REASON_CONTENT_FILTER`
- `FINISH_REASON_FUNCTION_CALL`, `FINISH_REASON_TOOL_CALLS`, `FINISH_REASON_ERROR`

### TokenUsage

Represents token usage statistics for cost calculation.

```php
use JTD\LaravelAI\Models\TokenUsage;

$usage = new TokenUsage(
    inputTokens: 100,
    outputTokens: 50,
    totalTokens: 150
);

// Properties
echo $usage->inputTokens;   // 100
echo $usage->outputTokens;  // 50
echo $usage->totalTokens;   // 150
```

## Exceptions

### Base Exceptions

```php
use JTD\LaravelAI\Exceptions\AIException;
use JTD\LaravelAI\Exceptions\ProviderException;
use JTD\LaravelAI\Exceptions\InvalidCredentialsException;
use JTD\LaravelAI\Exceptions\RateLimitException;
use JTD\LaravelAI\Exceptions\QuotaExceededException;
use JTD\LaravelAI\Exceptions\ServerException;
```

### OpenAI-Specific Exceptions

```php
use JTD\LaravelAI\Exceptions\OpenAI\OpenAIException;
use JTD\LaravelAI\Exceptions\OpenAI\OpenAIInvalidCredentialsException;
use JTD\LaravelAI\Exceptions\OpenAI\OpenAIRateLimitException;
use JTD\LaravelAI\Exceptions\OpenAI\OpenAIQuotaExceededException;
use JTD\LaravelAI\Exceptions\OpenAI\OpenAIServerException;

try {
    $response = AI::provider('openai')->sendMessage($message);
} catch (OpenAIRateLimitException $e) {
    $retryAfter = $e->getRetryAfter();
    $limitType = $e->getLimitType();
} catch (OpenAIQuotaExceededException $e) {
    $quotaType = $e->getQuotaType();
    $currentUsage = $e->getCurrentUsage();
}
```

## Events

### Core Events

```php
use JTD\LaravelAI\Events\MessageSent;
use JTD\LaravelAI\Events\ResponseGenerated;
use JTD\LaravelAI\Events\CostCalculated;
use JTD\LaravelAI\Events\ConversationUpdated;
use JTD\LaravelAI\Events\ModelSynced;
use JTD\LaravelAI\Events\ErrorOccurred;

// Listen for events
Event::listen(MessageSent::class, function (MessageSent $event) {
    Log::info('AI message sent', [
        'provider' => $event->provider,
        'model' => $event->options['model'] ?? 'default',
        'message_count' => count($event->messages),
    ]);
});

Event::listen(CostCalculated::class, function (CostCalculated $event) {
    Analytics::track('ai_cost', [
        'provider' => $event->provider,
        'cost' => $event->cost,
        'tokens' => $event->tokenUsage->totalTokens,
    ]);
});
```

## Facades

### AI Facade

The main facade for accessing AI functionality.

```php
use JTD\LaravelAI\Facades\AI;

// Get provider
$provider = AI::provider('openai');

// Send message
$response = AI::provider('openai')->sendMessage(
    AIMessage::user('Hello, world!')
);

// Stream response
$stream = AI::provider('openai')->sendStreamingMessage(
    AIMessage::user('Write a story')
);

foreach ($stream as $chunk) {
    echo $chunk->content;
}
```

## Configuration

### Provider Configuration

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
            'timeout' => (int) env('OPENAI_TIMEOUT', 30),
            'retry_attempts' => (int) env('OPENAI_RETRY_ATTEMPTS', 3),
            'logging' => [
                'enabled' => (bool) env('AI_LOGGING_ENABLED', true),
                'include_content' => (bool) env('AI_LOG_INCLUDE_CONTENT', false),
            ],
            'cost_tracking' => [
                'enabled' => (bool) env('AI_COST_TRACKING_ENABLED', true),
                'daily_limit' => (float) env('AI_DAILY_COST_LIMIT', 100.00),
            ],
        ],
    ],
];
```

## Usage Examples

### Basic Chat

```php
use JTD\LaravelAI\Facades\AI;
use JTD\LaravelAI\Models\AIMessage;

$response = AI::provider('openai')->sendMessage(
    AIMessage::user('Explain Laravel in one sentence'),
    ['model' => 'gpt-4']
);

echo $response->content;
```

### Conversation

```php
$messages = [
    AIMessage::system('You are a helpful Laravel expert.'),
    AIMessage::user('How do I create a middleware?'),
];

$response = AI::provider('openai')->sendMessages($messages, [
    'model' => 'gpt-4-turbo',
    'temperature' => 0.7,
]);
```

### Streaming

```php
$stream = AI::provider('openai')->sendStreamingMessage(
    AIMessage::user('Write a story about Laravel'),
    ['model' => 'gpt-4']
);

foreach ($stream as $chunk) {
    echo $chunk->content;
    
    if ($chunk->finishReason === 'stop') {
        echo "\nFinal cost: $" . number_format($chunk->cost, 4);
        break;
    }
}
```

### Function Calling

```php
$functions = [
    [
        'name' => 'get_weather',
        'description' => 'Get current weather',
        'parameters' => [
            'type' => 'object',
            'properties' => [
                'location' => ['type' => 'string', 'description' => 'City name'],
            ],
            'required' => ['location'],
        ],
    ],
];

$response = AI::provider('openai')->sendMessage(
    AIMessage::user('What\'s the weather in Paris?'),
    [
        'model' => 'gpt-4',
        'tools' => array_map(fn($func) => ['type' => 'function', 'function' => $func], $functions),
    ]
);

if ($response->hasToolCalls()) {
    foreach ($response->toolCalls as $toolCall) {
        // Execute function and send result back
        $result = executeFunction($toolCall->function->name, $toolCall->function->arguments);
        // Continue conversation with function result...
    }
}
```

For more detailed documentation, see:
- [OpenAI Driver Documentation](OPENAI_DRIVER.md)
- [Configuration Guide](CONFIGURATION.md)
- [Function Calling Examples](FUNCTION_CALLING_EXAMPLES.md)
