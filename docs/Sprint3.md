# Sprint 3: Multi-Provider Support and Conversation System

**Duration**: 2 weeks  
**Epic**: AI Provider Drivers + Conversation Management System  
**Goal**: Implement Gemini and xAI drivers, and build the core conversation management system

## Sprint Objectives

1. Implement Gemini driver with safety settings and multimodal support
2. Implement xAI driver for Grok models
3. Create conversation management system with persistence
4. Add provider switching and fallback mechanisms
5. Implement conversation context and history management
6. Add conversation branching and templates

## User Stories

### Story 1: Gemini Driver Implementation
**As a developer, I want to use Google Gemini so I can access Google's AI models**

**Acceptance Criteria:**
- Can send messages to Gemini API
- Supports safety settings configuration
- Handles multimodal inputs (text + images)
- Implements proper cost calculation
- Provides model synchronization

**Tasks:**
- [ ] Create GeminiDriver class
- [ ] Implement Gemini API integration
- [ ] Add safety settings support
- [ ] Implement multimodal capabilities
- [ ] Add cost calculation for Gemini models
- [ ] Create comprehensive tests

**Estimated Effort:** 3 days

### Story 2: xAI Driver Implementation
**As a developer, I want to use xAI Grok so I can access Grok models**

**Acceptance Criteria:**
- Can send messages to xAI API
- Supports Grok model variants
- Implements proper authentication
- Provides accurate cost calculation
- Handles xAI-specific features

**Tasks:**
- [ ] Create XAIDriver class
- [ ] Implement xAI API integration
- [ ] Add Grok model support
- [ ] Implement cost calculation
- [ ] Add error handling
- [ ] Create driver tests

**Estimated Effort:** 2 days

### Story 3: Conversation Management
**As a user, I want to create and manage conversations so I can organize my AI interactions**

**Acceptance Criteria:**
- Can create named conversations
- Conversations persist across sessions
- Support conversation metadata
- Enable conversation search and filtering
- Provide conversation statistics

**Tasks:**
- [ ] Create Conversation model and service
- [ ] Implement conversation CRUD operations
- [ ] Add conversation metadata support
- [ ] Create conversation search functionality
- [ ] Add conversation statistics
- [ ] Write conversation tests

**Estimated Effort:** 3 days

### Story 4: Provider Switching
**As a user, I want to switch AI providers mid-conversation so I can compare responses**

**Acceptance Criteria:**
- Can switch providers within same conversation
- Context is maintained across provider switches
- Provider history is tracked
- Fallback providers work automatically
- Cost tracking works across providers

**Tasks:**
- [ ] Implement provider switching logic
- [ ] Add context preservation
- [ ] Create provider fallback system
- [ ] Add provider history tracking
- [ ] Implement cross-provider cost tracking
- [ ] Test provider switching scenarios

**Estimated Effort:** 2 days

### Story 5: Conversation Context Management
**As a user, I want conversation history so I can reference previous exchanges**

**Acceptance Criteria:**
- Maintains conversation context automatically
- Supports configurable context window
- Handles context truncation intelligently
- Preserves important context markers
- Optimizes context for token efficiency

**Tasks:**
- [ ] Implement context management system
- [ ] Add configurable context windows
- [ ] Create intelligent context truncation
- [ ] Add context optimization
- [ ] Implement context preservation
- [ ] Test context management

**Estimated Effort:** 2 days

### Story 6: Conversation Templates
**As a developer, I want conversation templates so I can standardize common use cases**

**Acceptance Criteria:**
- Can create reusable conversation templates
- Templates include system messages and context
- Support template parameters and variables
- Enable template sharing and management
- Provide template validation

**Tasks:**
- [ ] Create template system architecture
- [ ] Implement template creation and storage
- [ ] Add template parameter support
- [ ] Create template management interface
- [ ] Add template validation
- [ ] Write template tests

**Estimated Effort:** 2 days

## Technical Implementation

### Gemini Driver

```php
<?php

namespace JTD\LaravelAI\Drivers;

use JTD\LaravelAI\Contracts\AIProviderInterface;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Models\AIResponse;
use Illuminate\Http\Client\Factory as HttpClient;

class GeminiDriver implements AIProviderInterface
{
    protected HttpClient $http;
    protected array $config;
    
    public function __construct(array $config)
    {
        $this->config = $config;
        $this->http = app(HttpClient::class);
    }
    
    public function sendMessage(AIMessage $message, array $options = []): AIResponse
    {
        $response = $this->http
            ->withHeaders([
                'Content-Type' => 'application/json',
            ])
            ->timeout($this->config['timeout'] ?? 30)
            ->post($this->getEndpoint('/generateContent'), [
                'contents' => $this->formatContents($message),
                'generationConfig' => [
                    'temperature' => $options['temperature'] ?? 0.7,
                    'maxOutputTokens' => $options['max_tokens'] ?? 1000,
                ],
                'safetySettings' => $this->getSafetySettings($options),
            ]);
        
        return $this->parseResponse($response->json());
    }
    
    protected function getSafetySettings(array $options): array
    {
        $defaultSettings = [
            'HARM_CATEGORY_HARASSMENT' => 'BLOCK_MEDIUM_AND_ABOVE',
            'HARM_CATEGORY_HATE_SPEECH' => 'BLOCK_MEDIUM_AND_ABOVE',
            'HARM_CATEGORY_SEXUALLY_EXPLICIT' => 'BLOCK_MEDIUM_AND_ABOVE',
            'HARM_CATEGORY_DANGEROUS_CONTENT' => 'BLOCK_MEDIUM_AND_ABOVE',
        ];
        
        $customSettings = $options['safety_settings'] ?? [];
        
        return collect(array_merge($defaultSettings, $customSettings))
            ->map(fn($threshold, $category) => [
                'category' => $category,
                'threshold' => $threshold,
            ])
            ->values()
            ->toArray();
    }
    
    protected function formatContents(AIMessage $message): array
    {
        $contents = [];
        
        // Handle text content
        if ($message->content) {
            $contents[] = [
                'role' => $message->role === 'assistant' ? 'model' : 'user',
                'parts' => [['text' => $message->content]],
            ];
        }
        
        // Handle image attachments
        if ($message->attachments) {
            foreach ($message->attachments as $attachment) {
                if ($attachment['type'] === 'image') {
                    $contents[0]['parts'][] = [
                        'inlineData' => [
                            'mimeType' => $attachment['mime_type'],
                            'data' => base64_encode($attachment['data']),
                        ],
                    ];
                }
            }
        }
        
        return $contents;
    }
    
    protected function getEndpoint(string $path): string
    {
        $baseUrl = $this->config['base_url'] ?? 'https://generativelanguage.googleapis.com/v1';
        $model = $this->config['default_model'] ?? 'gemini-pro';
        
        return "{$baseUrl}/models/{$model}:generateContent?key={$this->config['api_key']}";
    }
}
```

### xAI Driver

```php
<?php

namespace JTD\LaravelAI\Drivers;

use JTD\LaravelAI\Contracts\AIProviderInterface;

class XAIDriver implements AIProviderInterface
{
    protected HttpClient $http;
    protected array $config;
    
    public function sendMessage(AIMessage $message, array $options = []): AIResponse
    {
        $response = $this->http
            ->withHeaders([
                'Authorization' => 'Bearer ' . $this->config['api_key'],
                'Content-Type' => 'application/json',
            ])
            ->timeout($this->config['timeout'] ?? 30)
            ->post($this->config['base_url'] . '/chat/completions', [
                'model' => $options['model'] ?? 'grok-beta',
                'messages' => $this->formatMessages($message),
                'temperature' => $options['temperature'] ?? 0.7,
                'max_tokens' => $options['max_tokens'] ?? 1000,
            ]);
        
        return $this->parseResponse($response->json());
    }
    
    public function getAvailableModels(): Collection
    {
        $response = $this->http
            ->withHeaders(['Authorization' => 'Bearer ' . $this->config['api_key']])
            ->get($this->config['base_url'] . '/models');
        
        return collect($response->json('data'))
            ->filter(fn($model) => str_starts_with($model['id'], 'grok'))
            ->map(fn($model) => [
                'id' => $model['id'],
                'name' => $model['id'],
                'type' => 'chat',
                'context_length' => $model['context_length'] ?? 8192,
                'capabilities' => ['chat', 'reasoning'],
            ]);
    }
}
```

### Conversation Management

```php
<?php

namespace JTD\LaravelAI\Services;

use JTD\LaravelAI\Models\AIConversation;
use JTD\LaravelAI\Models\AIMessage;

class ConversationService
{
    public function create(array $attributes): AIConversation
    {
        return AIConversation::create([
            'name' => $attributes['name'] ?? 'New Conversation',
            'user_id' => $attributes['user_id'] ?? auth()->id(),
            'provider_id' => $attributes['provider_id'] ?? null,
            'model_id' => $attributes['model_id'] ?? null,
            'context' => $attributes['context'] ?? [],
            'metadata' => $attributes['metadata'] ?? [],
        ]);
    }
    
    public function addMessage(AIConversation $conversation, array $messageData): AIMessage
    {
        $message = $conversation->messages()->create([
            'role' => $messageData['role'],
            'content' => $messageData['content'],
            'provider_id' => $messageData['provider_id'] ?? null,
            'model_id' => $messageData['model_id'] ?? null,
            'tokens_used' => $messageData['tokens_used'] ?? 0,
            'cost' => $messageData['cost'] ?? 0,
            'metadata' => $messageData['metadata'] ?? [],
        ]);
        
        $this->updateConversationStats($conversation);
        
        return $message;
    }
    
    public function switchProvider(AIConversation $conversation, string $provider): void
    {
        $conversation->update([
            'provider_id' => $provider,
            'metadata' => array_merge($conversation->metadata ?? [], [
                'provider_history' => array_merge(
                    $conversation->metadata['provider_history'] ?? [],
                    [$provider]
                ),
            ]),
        ]);
    }
    
    public function getContext(AIConversation $conversation, int $limit = 10): array
    {
        return $conversation->messages()
            ->latest()
            ->limit($limit)
            ->get()
            ->reverse()
            ->map(fn($message) => [
                'role' => $message->role,
                'content' => $message->content,
            ])
            ->toArray();
    }
    
    protected function updateConversationStats(AIConversation $conversation): void
    {
        $stats = $conversation->messages()
            ->selectRaw('COUNT(*) as message_count, SUM(cost) as total_cost')
            ->first();
        
        $conversation->update([
            'message_count' => $stats->message_count,
            'total_cost' => $stats->total_cost,
        ]);
    }
}
```

### Provider Fallback System

```php
<?php

namespace JTD\LaravelAI\Services;

class ProviderFallbackService
{
    public function sendWithFallback(AIMessage $message, array $providers, array $options = []): AIResponse
    {
        $lastException = null;
        
        foreach ($providers as $providerName) {
            try {
                $provider = AI::provider($providerName);
                return $provider->sendMessage($message, $options);
            } catch (\Exception $e) {
                $lastException = $e;
                
                Log::warning("Provider {$providerName} failed, trying next", [
                    'error' => $e->getMessage(),
                    'provider' => $providerName,
                ]);
                
                // Don't retry on authentication errors
                if ($e instanceof InvalidCredentialsException) {
                    continue;
                }
                
                // Don't retry on quota exceeded
                if ($e instanceof QuotaExceededException) {
                    continue;
                }
                
                // Retry on rate limits after delay
                if ($e instanceof RateLimitException) {
                    sleep($e->getRetryAfter() ?? 1);
                    continue;
                }
            }
        }
        
        throw new AllProvidersFailedException(
            'All providers failed to process the request',
            0,
            $lastException
        );
    }
}
```

### Conversation Templates

```php
<?php

namespace JTD\LaravelAI\Models;

use Illuminate\Database\Eloquent\Model;

class ConversationTemplate extends Model
{
    protected $fillable = [
        'name',
        'description',
        'system_message',
        'initial_context',
        'parameters',
        'metadata',
        'is_public',
        'created_by',
    ];
    
    protected $casts = [
        'parameters' => 'array',
        'metadata' => 'array',
        'is_public' => 'boolean',
    ];
    
    public function applyToConversation(AIConversation $conversation, array $variables = []): void
    {
        $systemMessage = $this->interpolateVariables($this->system_message, $variables);
        $context = $this->interpolateVariables($this->initial_context, $variables);
        
        if ($systemMessage) {
            $conversation->messages()->create([
                'role' => 'system',
                'content' => $systemMessage,
            ]);
        }
        
        $conversation->update([
            'context' => array_merge($conversation->context ?? [], $context ?? []),
            'metadata' => array_merge($conversation->metadata ?? [], [
                'template_id' => $this->id,
                'template_variables' => $variables,
            ]),
        ]);
    }
    
    protected function interpolateVariables(string $text, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $text = str_replace("{{$key}}", $value, $text);
        }
        
        return $text;
    }
}
```

## Testing Strategy

### Multi-Provider Tests

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use JTD\LaravelAI\Facades\AI;

class MultiProviderTest extends TestCase
{
    public function test_provider_switching_in_conversation(): void
    {
        $conversation = AI::conversation('Multi-Provider Test');
        
        // Start with OpenAI
        $response1 = $conversation
            ->provider('openai')
            ->message('Hello')
            ->send();
        
        $this->assertEquals('openai', $response1->provider);
        
        // Switch to Gemini
        $response2 = $conversation
            ->provider('gemini')
            ->message('How are you?')
            ->send();
        
        $this->assertEquals('gemini', $response2->provider);
        
        // Verify conversation history is maintained
        $history = $conversation->getHistory();
        $this->assertCount(4, $history); // 2 user + 2 assistant messages
    }
    
    public function test_provider_fallback(): void
    {
        // Mock first provider to fail
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

### Conversation Tests

```php
<?php

namespace Tests\Feature;

class ConversationTest extends TestCase
{
    public function test_conversation_context_management(): void
    {
        $conversation = AI::conversation('Context Test');
        
        $conversation->message('My name is John')->send();
        $response = $conversation->message('What is my name?')->send();
        
        $this->assertStringContainsString('John', $response->content);
    }
    
    public function test_conversation_templates(): void
    {
        $template = ConversationTemplate::create([
            'name' => 'Customer Support',
            'system_message' => 'You are a helpful customer support agent for {{company}}.',
            'parameters' => ['company'],
        ]);
        
        $conversation = AI::conversation()
            ->fromTemplate($template, ['company' => 'Acme Corp'])
            ->message('I need help')
            ->send();
        
        $systemMessage = $conversation->messages()->where('role', 'system')->first();
        $this->assertStringContainsString('Acme Corp', $systemMessage->content);
    }
}
```

## Configuration Updates

```php
'providers' => [
    'openai' => [
        'driver' => 'openai',
        'api_key' => env('AI_OPENAI_API_KEY'),
        'fallback' => 'gemini',
    ],
    
    'gemini' => [
        'driver' => 'gemini',
        'api_key' => env('AI_GEMINI_API_KEY'),
        'base_url' => 'https://generativelanguage.googleapis.com/v1',
        'default_model' => 'gemini-pro',
        'safety_settings' => [
            'HARM_CATEGORY_HARASSMENT' => 'BLOCK_MEDIUM_AND_ABOVE',
        ],
        'fallback' => 'xai',
    ],
    
    'xai' => [
        'driver' => 'xai',
        'api_key' => env('AI_XAI_API_KEY'),
        'base_url' => 'https://api.x.ai/v1',
        'default_model' => 'grok-beta',
    ],
],

'conversation' => [
    'default_context_window' => 10,
    'max_context_tokens' => 4000,
    'auto_save' => true,
    'templates_enabled' => true,
],
```

## Definition of Done

- [ ] Gemini driver works with safety settings and multimodal support
- [ ] xAI driver successfully communicates with Grok models
- [ ] Conversation system persists and manages conversations
- [ ] Provider switching maintains context correctly
- [ ] Fallback system works when providers fail
- [ ] Conversation templates can be created and applied
- [ ] Context management optimizes token usage
- [ ] All tests pass with 90%+ coverage
- [ ] Documentation includes multi-provider examples

## Next Sprint Preview

Sprint 4 will focus on:
- Cost tracking and analytics system
- Budget management and alerts
- Usage reporting and optimization
- Performance monitoring and caching
