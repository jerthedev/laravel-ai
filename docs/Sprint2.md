# Sprint 2: OpenAI Driver Implementation

**Duration**: 2 weeks  
**Epic**: AI Provider Drivers  
**Goal**: Implement complete OpenAI driver with full API integration, model management, and cost calculation

## Sprint Objectives

1. Implement OpenAI driver with complete API integration
2. Add model synchronization from OpenAI API
3. Implement accurate cost calculation for OpenAI models
4. Add streaming response support
5. Implement error handling and retry logic
6. Create comprehensive tests for OpenAI driver

## User Stories

### Story 1: OpenAI Chat Completion with Events
**As a developer, I want to send messages to OpenAI so I can use GPT models**

**Acceptance Criteria:**
- Can send messages to OpenAI Chat Completion API
- Receives properly formatted responses
- Supports all GPT models (3.5-turbo, 4, 4-turbo, etc.)
- Handles message history and context
- Supports temperature, max_tokens, and other parameters
- Fires appropriate events (MessageSent, ResponseGenerated)

**Tasks:**
- [ ] Create OpenAIDriver class implementing AIProviderInterface
- [ ] Implement sendMessage method with Chat Completion API
- [ ] Add support for conversation context
- [ ] Handle API parameters (temperature, max_tokens, etc.)
- [ ] Create response parsing and formatting
- [ ] Integrate event firing for message lifecycle
- [ ] Add comprehensive unit tests

**Estimated Effort:** 3 days

### Story 2: Model Management
**As a developer, I want automatic model syncing so I always have the latest available models**

**Acceptance Criteria:**
- Automatically fetches available models from OpenAI
- Stores model information in database
- Updates model capabilities and pricing
- Handles model deprecation and new releases
- Provides model filtering and selection

**Tasks:**
- [ ] Implement getAvailableModels method
- [ ] Create model synchronization logic
- [ ] Add model capability detection
- [ ] Implement model filtering
- [ ] Create background job for model syncing
- [ ] Add model management tests

**Estimated Effort:** 2 days

### Story 3: Event-Driven Cost Calculation
**As a developer, I want cost calculation so I can track my AI spending**

**Acceptance Criteria:**
- Accurately calculates costs based on token usage
- Supports different pricing for input/output tokens
- Handles pricing changes and model updates
- Provides cost estimation before sending requests
- Tracks costs asynchronously via events
- Fires CostCalculated events for background processing

**Tasks:**
- [ ] Implement calculateCost method
- [ ] Create pricing data structure
- [ ] Add cost estimation functionality
- [ ] Implement event-driven cost tracking
- [ ] Create CostCalculated event and listener
- [ ] Create cost calculation tests
- [ ] Add pricing update mechanism

**Estimated Effort:** 2 days

### Story 4: Streaming Responses
**As a user, I want streaming responses so I can see AI output in real-time**

**Acceptance Criteria:**
- Supports OpenAI streaming API
- Provides real-time response chunks
- Handles streaming errors gracefully
- Maintains conversation context during streaming
- Calculates final costs after streaming completes

**Tasks:**
- [ ] Implement streamMessage method
- [ ] Add streaming response handling
- [ ] Create chunk processing logic
- [ ] Implement streaming error handling
- [ ] Add streaming tests
- [ ] Document streaming usage

**Estimated Effort:** 2 days

### Story 5: Function Calling
**As a developer, I want function calling support so I can extend AI capabilities**

**Acceptance Criteria:**
- Supports OpenAI function calling API
- Handles function definitions and calls
- Processes function results correctly
- Supports parallel function calling
- Provides function call validation

**Tasks:**
- [ ] Implement function calling in sendMessage
- [ ] Add function definition handling
- [ ] Create function call processing
- [ ] Implement parallel function calls
- [ ] Add function calling tests
- [ ] Create function calling examples

**Estimated Effort:** 2 days

### Story 6: Error Handling and Retries
**As a developer, I want robust error handling so my application remains stable**

**Acceptance Criteria:**
- Handles all OpenAI API error types
- Implements exponential backoff for retries
- Provides meaningful error messages
- Logs errors appropriately
- Respects rate limits

**Tasks:**
- [ ] Implement comprehensive error handling
- [ ] Add retry logic with exponential backoff
- [ ] Create custom exception classes
- [ ] Add rate limit handling
- [ ] Implement error logging
- [ ] Create error handling tests

**Estimated Effort:** 1 day

## Technical Implementation

### OpenAI Driver Structure

```php
<?php

namespace JTD\LaravelAI\Drivers;

use JTD\LaravelAI\Contracts\AIProviderInterface;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Models\AIResponse;
use JTD\LaravelAI\Models\TokenUsage;
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
            'functions' => $options['functions'] ?? null,
            'function_call' => $options['function_call'] ?? null,
        ]);
        
        return $this->formatResponse($response);
    }
    
    public function streamMessage(AIMessage $message, callable $callback, array $options = []): AIResponse
    {
        $stream = $this->client->chat()->createStreamed([
            'model' => $options['model'] ?? 'gpt-3.5-turbo',
            'messages' => $this->formatMessages($message),
            'temperature' => $options['temperature'] ?? 0.7,
            'max_tokens' => $options['max_tokens'] ?? null,
        ]);
        
        $fullContent = '';
        $totalTokens = 0;
        
        foreach ($stream as $response) {
            $delta = $response->choices[0]->delta;
            
            if (isset($delta->content)) {
                $fullContent .= $delta->content;
                
                $callback(new AIResponse([
                    'content' => $delta->content,
                    'is_chunk' => true,
                    'full_content' => $fullContent,
                ]));
            }
            
            if (isset($response->usage)) {
                $totalTokens = $response->usage->totalTokens;
            }
        }
        
        return new AIResponse([
            'content' => $fullContent,
            'tokens_used' => $totalTokens,
            'is_streamed' => true,
        ]);
    }
    
    public function getAvailableModels(): Collection
    {
        $models = $this->client->models()->list();
        
        return collect($models->data)
            ->filter(fn($model) => str_starts_with($model->id, 'gpt'))
            ->map(fn($model) => [
                'id' => $model->id,
                'name' => $model->id,
                'type' => $this->getModelType($model->id),
                'context_length' => $this->getContextLength($model->id),
                'capabilities' => $this->getModelCapabilities($model->id),
            ]);
    }
    
    public function calculateCost(TokenUsage $usage, string $modelId): float
    {
        $pricing = $this->getModelPricing($modelId);
        
        return ($usage->input_tokens * $pricing['input_cost_per_token']) +
               ($usage->output_tokens * $pricing['output_cost_per_token']);
    }
    
    // Additional methods...
}
```

### Model Pricing Data

```php
protected function getModelPricing(string $modelId): array
{
    $pricing = [
        'gpt-3.5-turbo' => [
            'input_cost_per_token' => 0.0000015,  // $0.0015 per 1K tokens
            'output_cost_per_token' => 0.000002,  // $0.002 per 1K tokens
        ],
        'gpt-4' => [
            'input_cost_per_token' => 0.00003,    // $0.03 per 1K tokens
            'output_cost_per_token' => 0.00006,   // $0.06 per 1K tokens
        ],
        'gpt-4-turbo' => [
            'input_cost_per_token' => 0.00001,    // $0.01 per 1K tokens
            'output_cost_per_token' => 0.00003,   // $0.03 per 1K tokens
        ],
    ];
    
    return $pricing[$modelId] ?? $pricing['gpt-3.5-turbo'];
}
```

### Error Handling

```php
protected function handleApiError(\Exception $e): void
{
    if ($e instanceof \OpenAI\Exceptions\ErrorException) {
        $errorType = $e->getErrorType();
        
        switch ($errorType) {
            case 'invalid_api_key':
                throw new InvalidCredentialsException('Invalid OpenAI API key');
            case 'insufficient_quota':
                throw new QuotaExceededException('OpenAI quota exceeded');
            case 'rate_limit_exceeded':
                throw new RateLimitException('OpenAI rate limit exceeded', $e->getRetryAfter());
            default:
                throw new ProviderException('OpenAI API error: ' . $e->getMessage());
        }
    }
    
    throw $e;
}
```

## Testing Strategy

### Unit Tests

```php
<?php

namespace Tests\Unit\Drivers;

use Tests\TestCase;
use JTD\LaravelAI\Drivers\OpenAIDriver;
use JTD\LaravelAI\Models\AIMessage;
use OpenAI\Client;
use OpenAI\Resources\Chat;

class OpenAIDriverTest extends TestCase
{
    protected OpenAIDriver $driver;
    protected Client $mockClient;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockClient = $this->mock(Client::class);
        $this->driver = new OpenAIDriver([
            'api_key' => 'test-key',
        ]);
        
        // Inject mock client
        $this->driver->setClient($this->mockClient);
    }
    
    public function test_send_message_returns_valid_response(): void
    {
        $mockChat = $this->mock(Chat::class);
        $this->mockClient->shouldReceive('chat')->andReturn($mockChat);
        
        $mockChat->shouldReceive('create')
            ->with(Mockery::subset([
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    ['role' => 'user', 'content' => 'Hello']
                ],
            ]))
            ->andReturn((object) [
                'choices' => [
                    (object) [
                        'message' => (object) [
                            'content' => 'Hello, world!'
                        ]
                    ]
                ],
                'usage' => (object) [
                    'total_tokens' => 10,
                    'prompt_tokens' => 5,
                    'completion_tokens' => 5,
                ],
                'model' => 'gpt-3.5-turbo',
            ]);
        
        $message = new AIMessage('Hello');
        $response = $this->driver->sendMessage($message);
        
        $this->assertEquals('Hello, world!', $response->content);
        $this->assertEquals(10, $response->tokens_used);
    }
    
    public function test_calculate_cost_for_gpt4(): void
    {
        $usage = new TokenUsage(100, 50);
        $cost = $this->driver->calculateCost($usage, 'gpt-4');
        
        // 100 * 0.00003 + 50 * 0.00006 = 0.006
        $this->assertEquals(0.006, $cost);
    }
    
    public function test_streaming_response(): void
    {
        $chunks = [];
        
        $response = $this->driver->streamMessage(
            new AIMessage('Tell me a story'),
            function ($chunk) use (&$chunks) {
                $chunks[] = $chunk;
            }
        );
        
        $this->assertTrue($response->is_streamed);
        $this->assertNotEmpty($chunks);
    }
}
```

### Integration Tests

```php
<?php

namespace Tests\Integration\Drivers;

use Tests\TestCase;
use JTD\LaravelAI\Facades\AI;

class OpenAIIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        if (!env('AI_OPENAI_API_KEY')) {
            $this->markTestSkipped('OpenAI API key not configured');
        }
    }
    
    public function test_real_openai_api_call(): void
    {
        $response = AI::conversation()
            ->provider('openai')
            ->model('gpt-3.5-turbo')
            ->message('Say "Hello, World!" and nothing else.')
            ->send();
        
        $this->assertStringContainsString('Hello', $response->content);
        $this->assertGreaterThan(0, $response->tokens_used);
        $this->assertGreaterThan(0, $response->cost);
    }
}
```

## Configuration Updates

### Environment Variables
```env
# OpenAI Configuration
AI_OPENAI_API_KEY=sk-your-openai-api-key
AI_OPENAI_ORGANIZATION=org-your-organization-id
AI_OPENAI_PROJECT=proj-your-project-id
```

### Config Updates
```php
'providers' => [
    'openai' => [
        'driver' => 'openai',
        'api_key' => env('AI_OPENAI_API_KEY'),
        'organization' => env('AI_OPENAI_ORGANIZATION'),
        'project' => env('AI_OPENAI_PROJECT'),
        'base_url' => 'https://api.openai.com/v1',
        'timeout' => 30,
        'retry_attempts' => 3,
        'retry_delay' => 1000,
        'default_model' => 'gpt-3.5-turbo',
        'max_tokens' => 4000,
        'temperature' => 0.7,
    ],
],
```

## Documentation Updates

### Usage Examples
```php
// Basic usage
$response = AI::conversation()
    ->provider('openai')
    ->message('Hello, world!')
    ->send();

// With parameters
$response = AI::conversation()
    ->provider('openai')
    ->model('gpt-4')
    ->temperature(0.8)
    ->maxTokens(1000)
    ->message('Write a creative story')
    ->send();

// Streaming
AI::conversation()
    ->provider('openai')
    ->stream(true)
    ->message('Tell me a long story')
    ->onChunk(function ($chunk) {
        echo $chunk->content;
    })
    ->send();

// Function calling
$response = AI::conversation()
    ->provider('openai')
    ->functions([
        [
            'name' => 'get_weather',
            'description' => 'Get weather information',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'location' => ['type' => 'string'],
                ],
            ],
        ],
    ])
    ->message('What\'s the weather in New York?')
    ->send();
```

## Definition of Done

- [ ] OpenAI driver implements all AIProviderInterface methods
- [ ] Chat completion API integration works correctly
- [ ] Model synchronization fetches and stores models
- [ ] Cost calculation is accurate for all OpenAI models
- [ ] Streaming responses work in real-time
- [ ] Function calling is fully supported
- [ ] Error handling covers all API error types
- [ ] Retry logic implements exponential backoff
- [ ] Unit tests achieve 95%+ coverage
- [ ] Integration tests pass with real API
- [ ] Documentation includes usage examples
- [ ] Code review is completed

## Risks and Mitigation

### Risk 1: OpenAI API Changes
**Mitigation**: Monitor OpenAI API updates and version compatibility

### Risk 2: Rate Limiting Issues
**Mitigation**: Implement proper rate limiting and backoff strategies

### Risk 3: Cost Calculation Accuracy
**Mitigation**: Regularly update pricing data and validate calculations

## Sprint Review Criteria

1. Can successfully send messages to OpenAI API
2. Model synchronization works and updates database
3. Cost calculations match OpenAI's billing
4. Streaming responses work smoothly
5. Function calling executes correctly
6. Error handling provides meaningful feedback
7. All tests pass with good coverage

## Next Sprint Preview

Sprint 3 will focus on implementing additional AI provider drivers:
- Gemini driver with safety settings
- xAI driver for Grok models
- Ollama driver for local models
- Provider comparison and fallback mechanisms
