# Core Interfaces and Contracts

This document provides comprehensive documentation for all core interfaces and contracts in the JTD Laravel AI package.

## AIProviderInterface

The `AIProviderInterface` is the core contract that all AI provider drivers must implement. It provides a unified API for interacting with different AI providers while allowing for provider-specific implementations.

### Implementation Requirements

All AI provider drivers must implement the following methods:

#### Core Methods

```php
public function sendMessage($message, array $options = []): AIResponse;
public function sendStreamingMessage($message, array $options = []): \Generator;
public function getAvailableModels(bool $forceRefresh = false): array;
public function calculateCost($input, ?string $modelId = null): array;
public function validateCredentials(): array;
```

#### Configuration Methods

```php
public function setModel(string $modelId): self;
public function getCurrentModel(): string;
public function getDefaultModel(): string;
public function setOptions(array $options): self;
public function getOptions(): array;
```

#### Capability Methods

```php
public function getCapabilities(): array;
public function supportsFeature(string $feature): bool;
public function getHealthStatus(): array;
```

### Usage Examples

#### Basic Message Sending

```php
use JTD\LaravelAI\Models\AIMessage;

$provider = app('ai.provider.openai');
$message = AIMessage::user('Hello, how are you?');

$response = $provider->sendMessage($message, [
    'temperature' => 0.7,
    'max_tokens' => 1000,
]);

echo $response->content;
```

#### Streaming Responses

```php
$generator = $provider->sendStreamingMessage($message);

foreach ($generator as $chunk) {
    echo $chunk->content;
    flush();
}
```

#### Cost Calculation

```php
$cost = $provider->calculateCost($message, 'gpt-4');
// Returns: ['total' => 0.002, 'input_cost' => 0.001, 'output_cost' => 0.001, 'currency' => 'USD']
```

## ConversationBuilderInterface

The `ConversationBuilderInterface` provides a fluent, Laravel-style interface for building AI conversations with method chaining support.

### Core Features

- **Fluent Interface**: Method chaining for readable conversation building
- **Conditional Logic**: `when()` and `unless()` methods for conditional execution
- **Callback Support**: Event handlers for success, error, and progress
- **Configuration**: Comprehensive options for AI parameters

### Usage Examples

#### Basic Conversation

```php
use JTD\LaravelAI\Facades\AI;

$response = AI::conversation('My Chat')
    ->provider('openai')
    ->model('gpt-4')
    ->temperature(0.7)
    ->message('Hello, how are you?')
    ->send();
```

#### Advanced Conversation with Callbacks

```php
$response = AI::conversation('Support Chat')
    ->provider('openai')
    ->model('gpt-4')
    ->systemPrompt('You are a helpful customer support agent.')
    ->message('I need help with my account')
    ->onSuccess(function ($response) {
        Log::info('AI response received', ['content' => $response->content]);
    })
    ->onError(function ($exception) {
        Log::error('AI request failed', ['error' => $exception->getMessage()]);
    })
    ->send();
```

#### Conditional Logic

```php
$isVip = $user->isVip();

$response = AI::conversation('Customer Service')
    ->provider('openai')
    ->when($isVip, function ($builder) {
        return $builder->model('gpt-4')->temperature(0.3);
    }, function ($builder) {
        return $builder->model('gpt-3.5-turbo')->temperature(0.7);
    })
    ->message($userMessage)
    ->send();
```

#### Streaming with Progress Callbacks

```php
$stream = AI::conversation('Live Chat')
    ->provider('openai')
    ->model('gpt-4')
    ->streaming()
    ->message('Tell me a story')
    ->onProgress(function ($chunk) {
        broadcast(new MessageChunk($chunk->content));
    })
    ->stream();

foreach ($stream as $chunk) {
    // Handle each chunk
}
```

## Data Transfer Objects

### AIMessage

Represents a message in an AI conversation with support for various content types and metadata.

#### Creating Messages

```php
use JTD\LaravelAI\Models\AIMessage;

// User message
$userMessage = AIMessage::user('Hello, how are you?');

// System message
$systemMessage = AIMessage::system('You are a helpful assistant.');

// Assistant message with function calls
$assistantMessage = AIMessage::assistant('I can help you with that.', [
    ['name' => 'get_weather', 'arguments' => ['location' => 'New York']]
]);

// Multimodal message with attachments
$multimodalMessage = AIMessage::user([
    ['type' => 'text', 'text' => 'What do you see in this image?'],
    ['type' => 'image_url', 'image_url' => ['url' => 'https://example.com/image.jpg']]
], AIMessage::CONTENT_TYPE_MULTIMODAL);
```

#### Message Properties

```php
$message = AIMessage::user('Hello');

echo $message->role; // 'user'
echo $message->content; // 'Hello'
echo $message->contentType; // 'text'
echo $message->getEstimatedTokenCount(); // Rough token estimate
```

### AIResponse

Represents a response from an AI provider with comprehensive metadata.

#### Response Properties

```php
$response = $provider->sendMessage($message);

echo $response->content; // Response content
echo $response->model; // Model used
echo $response->provider; // Provider name
echo $response->finishReason; // Why response ended
echo $response->tokenUsage->totalTokens; // Token count
echo $response->getTotalCost(); // Total cost
```

#### Response Methods

```php
if ($response->isSuccessful()) {
    echo "Response successful";
}

if ($response->hasFunctionCalls()) {
    foreach ($response->functionCalls as $call) {
        // Handle function call
    }
}

$message = $response->toMessage(); // Convert to AIMessage
```

### TokenUsage

Tracks token usage and cost information for AI requests.

#### Creating TokenUsage

```php
use JTD\LaravelAI\Models\TokenUsage;

// Basic token usage
$usage = TokenUsage::create(100, 50); // 100 input, 50 output tokens

// With costs
$usage = TokenUsage::withCosts(100, 50, 0.001, 0.002, 'USD');

// Calculate costs from rates
$usage = TokenUsage::create(100, 50)
    ->calculateCosts(0.01, 0.02); // $0.01 per 1K input, $0.02 per 1K output
```

#### Usage Methods

```php
echo $usage->totalTokens; // 150
echo $usage->getTotalCost(); // 0.003
echo $usage->formatTotalCost(); // "0.0030 USD"
echo $usage->getSummary(); // "150 tokens (100 input, 50 output) - 0.0030 USD"
echo $usage->getInputTokenPercentage(); // 66.67
```

## Exception Handling

The package provides specific exceptions for different error scenarios:

### RateLimitException

```php
try {
    $response = $provider->sendMessage($message);
} catch (RateLimitException $e) {
    if ($e->canRetry()) {
        sleep($e->getWaitTime());
        // Retry the request
    }
}
```

### InvalidCredentialsException

```php
try {
    $response = $provider->sendMessage($message);
} catch (InvalidCredentialsException $e) {
    Log::error('Invalid credentials for provider', [
        'provider' => $e->getProvider(),
        'account' => $e->getAccount(),
        'details' => $e->getDetails(),
    ]);
}
```

### ProviderException

```php
try {
    $response = $provider->sendMessage($message);
} catch (ProviderException $e) {
    if ($e->isRetryable()) {
        // Implement retry logic
    } else {
        // Handle non-retryable error
    }
}
```

## Implementation Guidelines

### Creating Custom Providers

To create a custom AI provider:

1. Implement the `AIProviderInterface`
2. Handle all required methods
3. Provide proper error handling with specific exceptions
4. Support the package's event system
5. Follow the retry and rate limiting patterns

```php
class CustomAIProvider implements AIProviderInterface
{
    public function sendMessage($message, array $options = []): AIResponse
    {
        try {
            // Implementation
        } catch (Exception $e) {
            throw new ProviderException(
                'Custom provider error: ' . $e->getMessage(),
                'custom',
                'api_error',
                ['original_error' => $e->getMessage()],
                true // retryable
            );
        }
    }
    
    // Implement other required methods...
}
```

### Extending ConversationBuilder

Custom conversation builders should implement `ConversationBuilderInterface` and support:

- Method chaining
- Conditional execution
- Callback handling
- Configuration management
- Error handling

## Best Practices

1. **Error Handling**: Always use specific exceptions with detailed context
2. **Validation**: Validate inputs early and provide clear error messages
3. **Documentation**: Document all public methods with examples
4. **Testing**: Provide comprehensive test coverage for all implementations
5. **Performance**: Implement caching and rate limiting appropriately
6. **Security**: Handle credentials securely and validate all inputs
