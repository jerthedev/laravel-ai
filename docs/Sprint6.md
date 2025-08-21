# Sprint 6: Advanced Middleware and Context Systems

**Duration**: 2 weeks  
**Epic**: Advanced Features and Optimization  
**Goal**: Implement advanced middleware components including context injection, pre-processing, and memory systems

## Sprint Objectives

1. Build Context Injection middleware with memory integration
2. Implement Pre-processing middleware for request enhancement
3. Create Memory/Learning system for context accumulation
4. Add advanced middleware features (chaining, conditional execution)
5. Implement caching and performance optimizations
6. Build middleware management and debugging tools

## User Stories

### Story 1: Context Injection Middleware
**As a user, I want AI to remember previous conversations and relevant context so responses are more personalized and accurate**

**Acceptance Criteria:**
- Automatically injects relevant conversation history
- Includes user preferences and profile information
- Adds project-specific context when available
- Retrieves domain-specific knowledge
- Optimizes context length for token efficiency
- Improves response relevance by 40%+ in testing

**Tasks:**
- [ ] Implement context retrieval system
- [ ] Create user preference integration
- [ ] Add project context management
- [ ] Build domain knowledge system
- [ ] Implement context optimization
- [ ] Write context injection tests
- [ ] Create context relevance benchmarks

**Estimated Effort:** 4 days

### Story 2: Pre-processing Middleware
**As a developer, I want request pre-processing so AI receives optimized and enhanced prompts**

**Acceptance Criteria:**
- Detects and clarifies vague requests
- Enhances code-related queries with context
- Adds specificity prompts for better responses
- Handles multi-language requests appropriately
- Optimizes prompts for token efficiency
- Maintains original intent while improving clarity

**Tasks:**
- [ ] Implement vagueness detection
- [ ] Create code context enhancement
- [ ] Add specificity prompt generation
- [ ] Build language detection and handling
- [ ] Implement prompt optimization
- [ ] Write pre-processing tests
- [ ] Create prompt quality metrics

**Estimated Effort:** 3 days

### Story 3: Memory and Learning System
**As a system, I want to learn from interactions so I can provide increasingly relevant context over time**

**Acceptance Criteria:**
- Stores conversation summaries and key insights
- Learns user preferences from interactions
- Builds domain-specific knowledge bases
- Tracks successful context patterns
- Provides memory management and cleanup
- Respects privacy and data retention policies

**Tasks:**
- [ ] Design memory storage architecture
- [ ] Implement conversation summarization
- [ ] Create preference learning algorithms
- [ ] Build knowledge base system
- [ ] Add pattern recognition
- [ ] Implement memory management
- [ ] Write memory system tests
- [ ] Create privacy compliance features

**Estimated Effort:** 3 days

### Story 4: Advanced Middleware Features
**As a developer, I want advanced middleware capabilities so I can build sophisticated AI workflows**

**Acceptance Criteria:**
- Supports conditional middleware execution
- Enables middleware chaining and composition
- Provides middleware configuration per request
- Allows dynamic middleware registration
- Supports middleware dependencies
- Includes middleware performance profiling

**Tasks:**
- [ ] Implement conditional middleware execution
- [ ] Create middleware chaining system
- [ ] Add per-request configuration
- [ ] Build dynamic registration
- [ ] Implement dependency management
- [ ] Add performance profiling
- [ ] Write advanced feature tests

**Estimated Effort:** 2 days

### Story 5: Caching and Performance Optimization
**As a system administrator, I want optimized middleware performance so the system remains responsive**

**Acceptance Criteria:**
- Caches expensive context lookups
- Implements intelligent cache invalidation
- Optimizes database queries
- Reduces memory usage
- Provides performance monitoring
- Achieves <50ms average middleware overhead

**Tasks:**
- [ ] Implement context caching system
- [ ] Add cache invalidation logic
- [ ] Optimize database queries
- [ ] Reduce memory footprint
- [ ] Create performance monitoring
- [ ] Write performance tests
- [ ] Create optimization documentation

**Estimated Effort:** 2 days

### Story 6: Middleware Management Tools
**As a developer, I want debugging and management tools so I can troubleshoot and optimize middleware**

**Acceptance Criteria:**
- Provides middleware execution visualization
- Includes performance profiling tools
- Offers debugging and logging features
- Supports middleware testing utilities
- Includes configuration validation
- Provides middleware documentation generation

**Tasks:**
- [ ] Create middleware execution tracer
- [ ] Build performance profiling dashboard
- [ ] Implement debugging tools
- [ ] Add testing utilities
- [ ] Create configuration validator
- [ ] Build documentation generator
- [ ] Write management tool tests

**Estimated Effort:** 1 day

## Technical Implementation

### Context Injection Middleware

```php
<?php

namespace JTD\LaravelAI\Middleware;

use JTD\LaravelAI\Services\MemoryService;
use JTD\LaravelAI\Services\ContextOptimizer;

class ContextInjectionMiddleware implements AIMiddlewareInterface
{
    protected MemoryService $memory;
    protected ContextOptimizer $optimizer;
    
    public function __construct(MemoryService $memory, ContextOptimizer $optimizer)
    {
        $this->memory = $memory;
        $this->optimizer = $optimizer;
    }
    
    public function handle(AIMessage $message, \Closure $next): AIResponse
    {
        $context = $this->gatherContext($message);
        
        if (!empty($context)) {
            $optimizedContext = $this->optimizer->optimize($context, $message);
            $message->content = $this->injectContext($message->content, $optimizedContext);
            
            $message->metadata['context_injected'] = true;
            $message->metadata['context_items'] = count($optimizedContext);
            $message->metadata['context_tokens'] = $this->estimateTokens($optimizedContext);
        }
        
        $response = $next($message);
        
        // Learn from the interaction
        $this->memory->recordInteraction($message, $response);
        
        return $response;
    }
    
    protected function gatherContext(AIMessage $message): array
    {
        $context = [];
        
        // User context from memory
        $userMemory = $this->memory->getUserMemory($message->user_id);
        if ($userMemory) {
            $context['user_memory'] = $userMemory;
        }
        
        // Conversation history
        $relevantHistory = $this->memory->getRelevantHistory(
            $message->conversation_id,
            $message->content,
            5 // Last 5 relevant exchanges
        );
        if ($relevantHistory) {
            $context['conversation_history'] = $relevantHistory;
        }
        
        // Project context
        if ($projectId = $message->metadata['project_id'] ?? null) {
            $projectContext = $this->memory->getProjectContext($projectId);
            if ($projectContext) {
                $context['project'] = $projectContext;
            }
        }
        
        // Domain knowledge
        $domain = $this->detectDomain($message->content);
        if ($domain) {
            $domainKnowledge = $this->memory->getDomainKnowledge($domain, $message->user_id);
            if ($domainKnowledge) {
                $context['domain_knowledge'] = $domainKnowledge;
            }
        }
        
        // User preferences
        $preferences = $this->memory->getUserPreferences($message->user_id);
        if ($preferences) {
            $context['preferences'] = $preferences;
        }
        
        return $context;
    }
    
    protected function injectContext(string $originalContent, array $context): string
    {
        $contextString = "Relevant Context:\n";
        
        foreach ($context as $type => $data) {
            $contextString .= "- " . ucfirst(str_replace('_', ' ', $type)) . ": " . $data . "\n";
        }
        
        return $contextString . "\n---\n\nUser Request: " . $originalContent;
    }
}
```

### Memory Service

```php
<?php

namespace JTD\LaravelAI\Services;

use JTD\LaravelAI\Models\AIMemory;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Models\AIResponse;

class MemoryService
{
    public function recordInteraction(AIMessage $message, AIResponse $response): void
    {
        // Extract key insights from the interaction
        $insights = $this->extractInsights($message, $response);
        
        if (!empty($insights)) {
            AIMemory::create([
                'user_id' => $message->user_id,
                'conversation_id' => $message->conversation_id,
                'type' => 'interaction',
                'content' => $insights,
                'relevance_score' => $this->calculateRelevance($insights),
                'created_at' => now(),
            ]);
        }
        
        // Update user preferences based on interaction
        $this->updateUserPreferences($message, $response);
    }
    
    public function getUserMemory(int $userId, int $limit = 10): ?string
    {
        $memories = AIMemory::where('user_id', $userId)
            ->where('type', 'user_preference')
            ->orderBy('relevance_score', 'desc')
            ->limit($limit)
            ->get();
        
        if ($memories->isEmpty()) {
            return null;
        }
        
        return $memories->pluck('content')->implode('; ');
    }
    
    public function getRelevantHistory(int $conversationId, string $currentMessage, int $limit = 5): ?string
    {
        // Use semantic similarity to find relevant past exchanges
        $relevantMessages = $this->findSimilarMessages($conversationId, $currentMessage, $limit);
        
        if ($relevantMessages->isEmpty()) {
            return null;
        }
        
        return $relevantMessages->map(function ($msg) {
            return "Previous: {$msg->content} -> {$msg->response_content}";
        })->implode("\n");
    }
    
    public function getProjectContext(int $projectId): ?string
    {
        $projectMemory = AIMemory::where('project_id', $projectId)
            ->where('type', 'project_context')
            ->orderBy('relevance_score', 'desc')
            ->first();
        
        return $projectMemory?->content;
    }
    
    public function getDomainKnowledge(string $domain, int $userId): ?string
    {
        $knowledge = AIMemory::where('user_id', $userId)
            ->where('type', 'domain_knowledge')
            ->where('metadata->domain', $domain)
            ->orderBy('relevance_score', 'desc')
            ->limit(3)
            ->get();
        
        if ($knowledge->isEmpty()) {
            return null;
        }
        
        return $knowledge->pluck('content')->implode('; ');
    }
    
    protected function extractInsights(AIMessage $message, AIResponse $response): array
    {
        $insights = [];
        
        // Extract user preferences
        if ($this->indicatesPreference($message, $response)) {
            $insights['preference'] = $this->extractPreference($message, $response);
        }
        
        // Extract domain knowledge
        if ($this->containsDomainKnowledge($response)) {
            $insights['domain_knowledge'] = $this->extractDomainKnowledge($response);
        }
        
        // Extract successful patterns
        if ($this->isSuccessfulInteraction($response)) {
            $insights['successful_pattern'] = [
                'message_type' => $this->classifyMessage($message),
                'response_quality' => $this->assessResponseQuality($response),
            ];
        }
        
        return $insights;
    }
    
    protected function updateUserPreferences(AIMessage $message, AIResponse $response): void
    {
        // Learn from user behavior patterns
        $preferences = [];
        
        // Provider preference (if user seems satisfied with specific provider)
        if ($this->isPositiveResponse($response)) {
            $preferences['preferred_provider'] = $message->provider;
        }
        
        // Communication style preference
        $style = $this->detectPreferredStyle($message, $response);
        if ($style) {
            $preferences['communication_style'] = $style;
        }
        
        // Topic interests
        $topics = $this->extractTopics($message);
        if (!empty($topics)) {
            $preferences['interested_topics'] = $topics;
        }
        
        if (!empty($preferences)) {
            AIMemory::updateOrCreate([
                'user_id' => $message->user_id,
                'type' => 'user_preference',
            ], [
                'content' => json_encode($preferences),
                'relevance_score' => 0.8,
                'updated_at' => now(),
            ]);
        }
    }
}
```

### Pre-processing Middleware

```php
<?php

namespace JTD\LaravelAI\Middleware;

class PreProcessingMiddleware implements AIMiddlewareInterface
{
    public function handle(AIMessage $message, \Closure $next): AIResponse
    {
        $originalContent = $message->content;
        $enhancements = [];
        
        // Detect and handle vague requests
        if ($this->isVague($originalContent)) {
            $message->content = $this->addClarityPrompts($originalContent);
            $enhancements[] = 'clarity_enhancement';
        }
        
        // Enhance code-related queries
        if ($this->containsCode($originalContent)) {
            $message->content = $this->enhanceCodeContext($message->content);
            $enhancements[] = 'code_enhancement';
        }
        
        // Add specificity for general questions
        if ($this->needsSpecificity($originalContent)) {
            $message->content = $this->addSpecificityPrompts($message->content);
            $enhancements[] = 'specificity_enhancement';
        }
        
        // Handle multi-language content
        if ($this->isMultiLanguage($originalContent)) {
            $message->content = $this->handleMultiLanguage($message->content);
            $enhancements[] = 'language_enhancement';
        }
        
        // Optimize for token efficiency
        if ($this->isTokenInefficient($message->content)) {
            $message->content = $this->optimizeTokens($message->content);
            $enhancements[] = 'token_optimization';
        }
        
        $message->metadata['preprocessing_applied'] = $enhancements;
        $message->metadata['original_length'] = strlen($originalContent);
        $message->metadata['enhanced_length'] = strlen($message->content);
        
        return $next($message);
    }
    
    protected function isVague(string $content): bool
    {
        $vagueIndicators = [
            'it', 'this', 'that', 'the thing', 'stuff', 'something',
            'anything', 'everything', 'nothing', 'whatever'
        ];
        
        $content = strtolower($content);
        $vagueCount = 0;
        
        foreach ($vagueIndicators as $indicator) {
            $vagueCount += substr_count($content, $indicator);
        }
        
        // Consider vague if more than 2 vague indicators or very short
        return $vagueCount > 2 || (strlen($content) < 50 && $vagueCount > 0);
    }
    
    protected function addClarityPrompts(string $content): string
    {
        return "Please provide a detailed and specific response. If any part of this question is unclear, " .
               "make reasonable assumptions and state them clearly.\n\n" .
               "Question: " . $content;
    }
    
    protected function enhanceCodeContext(string $content): string
    {
        $language = $this->detectProgrammingLanguage($content);
        
        $enhancement = "You are an expert programmer";
        if ($language) {
            $enhancement .= " specializing in {$language}";
        }
        
        $enhancement .= ". When providing code examples:\n" .
                       "- Include clear comments explaining the logic\n" .
                       "- Consider edge cases and error handling\n" .
                       "- Follow best practices and conventions\n" .
                       "- Explain any complex algorithms or patterns used\n\n";
        
        return $enhancement . $content;
    }
    
    protected function addSpecificityPrompts(string $content): string
    {
        if ($this->isHowToQuestion($content)) {
            return "Please provide a step-by-step guide with specific examples. " .
                   "Include any prerequisites, potential pitfalls, and best practices.\n\n" . $content;
        }
        
        if ($this->isWhatIsQuestion($content)) {
            return "Please provide a comprehensive explanation including definition, " .
                   "key characteristics, examples, and practical applications.\n\n" . $content;
        }
        
        return "Please provide a detailed and comprehensive response with specific examples " .
               "and practical applications where relevant.\n\n" . $content;
    }
}
```

## Advanced Middleware Features

### Conditional Middleware Execution

```php
<?php

namespace JTD\LaravelAI\Services;

class ConditionalMiddleware
{
    public static function when(callable $condition, string $middleware): array
    {
        return [
            'middleware' => $middleware,
            'condition' => $condition,
            'type' => 'conditional',
        ];
    }
    
    public static function unless(callable $condition, string $middleware): array
    {
        return [
            'middleware' => $middleware,
            'condition' => fn($message) => !$condition($message),
            'type' => 'conditional',
        ];
    }
}

// Usage
$response = AI::conversation()
    ->middleware([
        ConditionalMiddleware::when(
            fn($msg) => $msg->user->isPremium(),
            ContextInjectionMiddleware::class
        ),
        ConditionalMiddleware::unless(
            fn($msg) => app()->environment('testing'),
            BudgetEnforcementMiddleware::class
        ),
    ])
    ->message('Hello')
    ->send();
```

### Middleware Chaining and Composition

```php
<?php

namespace JTD\LaravelAI\Services;

class MiddlewareChain
{
    protected array $chain = [];
    
    public function add(string $middleware, array $config = []): self
    {
        $this->chain[] = ['middleware' => $middleware, 'config' => $config];
        return $this;
    }
    
    public function when(callable $condition): self
    {
        if (!empty($this->chain)) {
            $this->chain[count($this->chain) - 1]['condition'] = $condition;
        }
        return $this;
    }
    
    public function toArray(): array
    {
        return $this->chain;
    }
    
    public static function create(): self
    {
        return new static();
    }
}

// Usage
$middlewareChain = MiddlewareChain::create()
    ->add(SmartRouterMiddleware::class, ['prefer_cost_optimization' => true])
    ->add(ContextInjectionMiddleware::class)
    ->when(fn($msg) => strlen($msg->content) > 100)
    ->add(PreProcessingMiddleware::class);

$response = AI::conversation()
    ->middleware($middlewareChain->toArray())
    ->message('Complex question')
    ->send();
```

## Performance Optimization

### Context Caching System

```php
<?php

namespace JTD\LaravelAI\Services;

class ContextCache
{
    protected int $defaultTtl = 300; // 5 minutes
    
    public function remember(string $key, callable $callback, ?int $ttl = null): mixed
    {
        $ttl = $ttl ?? $this->defaultTtl;
        
        return Cache::remember("ai_context:{$key}", $ttl, $callback);
    }
    
    public function getUserContext(int $userId): mixed
    {
        return $this->remember("user:{$userId}", function () use ($userId) {
            return app(MemoryService::class)->getUserMemory($userId);
        });
    }
    
    public function getProjectContext(int $projectId): mixed
    {
        return $this->remember("project:{$projectId}", function () use ($projectId) {
            return app(MemoryService::class)->getProjectContext($projectId);
        }, 1800); // 30 minutes for project context
    }
    
    public function invalidateUser(int $userId): void
    {
        Cache::forget("ai_context:user:{$userId}");
    }
    
    public function invalidateProject(int $projectId): void
    {
        Cache::forget("ai_context:project:{$projectId}");
    }
}
```

## Testing Strategy

### Context Injection Tests

```php
<?php

namespace Tests\Unit\Middleware;

class ContextInjectionMiddlewareTest extends TestCase
{
    public function test_injects_user_memory(): void
    {
        $user = User::factory()->create();
        
        // Create some user memory
        AIMemory::create([
            'user_id' => $user->id,
            'type' => 'user_preference',
            'content' => 'Prefers detailed technical explanations',
            'relevance_score' => 0.9,
        ]);
        
        $middleware = new ContextInjectionMiddleware(
            app(MemoryService::class),
            app(ContextOptimizer::class)
        );
        
        $message = new AIMessage('Explain APIs');
        $message->user_id = $user->id;
        
        $middleware->handle($message, function ($msg) {
            $this->assertStringContainsString('Prefers detailed technical explanations', $msg->content);
            $this->assertTrue($msg->metadata['context_injected']);
            return new AIResponse(['content' => 'API explanation...']);
        });
    }
    
    public function test_optimizes_context_for_token_efficiency(): void
    {
        $middleware = new ContextInjectionMiddleware(
            app(MemoryService::class),
            app(ContextOptimizer::class)
        );
        
        // Test with very long context that should be optimized
        $longContext = str_repeat('This is a very long context string. ', 100);
        
        $message = new AIMessage('Short question');
        
        $middleware->handle($message, function ($msg) use ($longContext) {
            // Context should be present but optimized (shorter than original)
            $this->assertLessThan(strlen($longContext), strlen($msg->content));
            $this->assertGreaterThan(strlen('Short question'), strlen($msg->content));
            return new AIResponse(['content' => 'Response']);
        });
    }
}
```

## Configuration Updates

```php
'middleware' => [
    'context_injection' => [
        'enabled' => env('AI_CONTEXT_INJECTION_ENABLED', true),
        'max_context_tokens' => env('AI_MAX_CONTEXT_TOKENS', 2000),
        'cache_ttl' => env('AI_CONTEXT_CACHE_TTL', 300),
        'relevance_threshold' => 0.7,
        'include_conversation_history' => true,
        'include_user_preferences' => true,
        'include_domain_knowledge' => true,
    ],
    
    'preprocessing' => [
        'enabled' => env('AI_PREPROCESSING_ENABLED', true),
        'enhance_vague_requests' => true,
        'enhance_code_queries' => true,
        'add_specificity_prompts' => true,
        'optimize_tokens' => true,
    ],
    
    'memory' => [
        'enabled' => env('AI_MEMORY_ENABLED', true),
        'retention_days' => env('AI_MEMORY_RETENTION_DAYS', 90),
        'max_memories_per_user' => 1000,
        'learning_enabled' => true,
        'privacy_mode' => env('AI_MEMORY_PRIVACY_MODE', false),
    ],
],
```

## Definition of Done

- [ ] Context Injection middleware improves response relevance by 40%+
- [ ] Pre-processing middleware enhances request clarity
- [ ] Memory system learns and applies user preferences
- [ ] Advanced middleware features work correctly
- [ ] Performance optimizations achieve <50ms overhead
- [ ] Management tools provide useful debugging information
- [ ] All tests pass with 90%+ coverage
- [ ] Documentation covers all new features

## Next Sprint Preview

Sprint 7 will focus on:
- Documentation and developer experience improvements
- Advanced testing and quality assurance
- Performance benchmarking and optimization
- Community features and extensibility
- Production readiness and deployment guides
