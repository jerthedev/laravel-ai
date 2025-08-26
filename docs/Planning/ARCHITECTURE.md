# Architecture

Package: jerthedev/laravel-ai
Namespace: JTD\\LaravelAI
Local path: packages/jerthedev/laravel-ai

## High-level Overview
The package provides a unified, Laravel-native interface for multiple AI providers through a driver-based architecture. It manages conversations, tracks costs, syncs models, and integrates with Model Context Protocol servers while maintaining familiar Laravel patterns and conventions.

## Core Components

### AI Manager (Central Orchestrator)
- **AIManager**: Central service that coordinates all AI operations
- **ProviderManager**: Handles provider registration, configuration, and switching
- **ConversationManager**: Manages conversation lifecycle and persistence
- **CostTracker**: Monitors token usage and calculates costs in real-time

### Driver System
```
AIManager
├── OpenAIDriver
├── XAIDriver  
├── GeminiDriver
├── OllamaDriver
└── CustomDriver (extensible)
```

Each driver implements the `AIProviderInterface`:
- `sendMessage(Message $message): Response`
- `getAvailableModels(): Collection`
- `calculateCost(Usage $usage): float`
- `validateCredentials(): bool`

### Facades & Service Layer
- **AI Facade**: Primary entry point (`AI::conversation()`, `AI::model()`, etc.)
- **ConversationBuilder**: Fluent interface for building AI requests
- **ResponseProcessor**: Handles AI responses and triggers callbacks
- **BatchProcessor**: Manages bulk operations and queue integration

### Database Layer

#### Provider & Model Management
```sql
ai_providers
├── id, name, slug, is_active, config
├── created_at, updated_at

ai_accounts  
├── id, provider_id, name, credentials_encrypted
├── is_default, is_active, created_at, updated_at

ai_provider_models
├── id, provider_id, model_id, name, type
├── capabilities, pricing, is_active, synced_at

ai_provider_model_costs
├── id, model_id, input_cost_per_token, output_cost_per_token
├── effective_date, currency, created_at
```

#### Conversation System
```sql
ai_conversations
├── id, name, user_id, provider_id, model_id
├── context, metadata, total_cost, message_count
├── created_at, updated_at

ai_messages
├── id, conversation_id, role, content, tokens_used
├── input_tokens, output_tokens, cost, response_time
├── provider_id, model_id, created_at

ai_conversation_participants
├── id, conversation_id, user_id, role, joined_at
```

#### Analytics & Tracking
```sql
ai_usage_analytics
├── id, provider_id, model_id, date, total_requests
├── total_tokens, total_cost, avg_response_time

ai_cost_tracking
├── id, user_id, provider_id, model_id, date
├── requests_count, tokens_used, cost, created_at

ai_model_performance
├── id, model_id, avg_response_time, success_rate
├── error_rate, last_updated
```

## Request Flow Architecture

### Event-Driven Request Flow
```
1. AI::conversation() → AIManager
2. AIManager → ConversationBuilder
3. ConversationBuilder → Middleware Stack
4. Middleware Stack → ProviderManager
5. ProviderManager → Specific Driver (OpenAI/xAI/Gemini)
6. Driver → MCP Servers (if configured)
7. MCP Enhanced Request → External API
8. Response → ResponseProcessor
9. ResponseProcessor → Fire Events → Return Response (immediately)
                           ↓
                    Background Event Handlers:
                    - Cost tracking & analytics
                    - Memory & learning updates
                    - Agent actions & integrations
                    - Notifications & webhooks
```

### Performance Comparison
**Traditional Synchronous Flow:**
```
Request → Processing → Response → Cost Calc → Analytics → Return
~2000ms + 200ms + 100ms = ~2300ms total
```

**Event-Driven Asynchronous Flow:**
```
Request → Processing → Response → Fire Events → Return
~2000ms + 5ms = ~2005ms (85% faster!)

Background Processing (parallel):
- Cost calculation, analytics, agent actions, notifications
```

### Conversation Persistence Flow
```
1. ConversationBuilder.send() → ConversationManager
2. ConversationManager → Database (save message)
3. ConversationManager → Middleware → Driver → MCP → AI Provider
4. AI Response → ConversationManager
5. ConversationManager → Database (save response)
6. ConversationManager → Fire Events (ResponseGenerated, etc.)
7. Background Event Handlers → Process costs, analytics, actions
8. Immediate Response → User
```

## Provider Driver Interface

### Core Interface
```php
interface AIProviderInterface
{
    public function sendMessage(AIMessage $message, array $options = []): AIResponse;
    public function sendBatch(array $messages, array $options = []): Collection;
    public function getAvailableModels(): Collection;
    public function syncModels(): void;
    public function calculateCost(TokenUsage $usage, string $modelId): float;
    public function validateCredentials(): bool;
    public function getCapabilities(): array;
}
```

### Driver Configuration
```php
// config/ai.php
'providers' => [
    'openai' => [
        'driver' => OpenAIDriver::class,
        'api_key' => env('AI_OPENAI_API_KEY'),
        'organization' => env('AI_OPENAI_ORG'),
        'base_url' => 'https://api.openai.com/v1',
        'timeout' => 30,
        'retry_attempts' => 3,
    ],
    'xai' => [
        'driver' => XAIDriver::class,
        'api_key' => env('AI_XAI_API_KEY'),
        'base_url' => 'https://api.x.ai/v1',
    ],
]
```

## Model Context Protocol (MCP) Integration

### MCP Architecture
```
ConversationBuilder
├── MCPManager
    ├── SequentialThinkingServer
    ├── CustomMCPServer
    └── ExtensibleMCPInterface
```

### MCP Flow
```
1. AI::conversation().mcp('sequential-thinking')
2. MCPManager → SequentialThinkingServer
3. SequentialThinkingServer → Process thinking steps
4. Enhanced prompt → AI Provider
5. AI Response → MCP post-processing
6. Final enhanced response → User
```

## Event System

### Core Events
- `MessageSent` - User message sent to AI provider
- `ResponseGenerated` - AI response generated and ready to return
- `ConversationUpdated` - Conversation modified (messages, context, metadata)
- `CostCalculated` - Cost calculation completed
- `BudgetThresholdReached` - User approaching/exceeding budget limits
- `AgentActionRequested` - AI response indicates agent action needed
- `AgentActionCompleted` - Agent action executed (success/failure)
- `ExternalIntegrationTriggered` - External systems need notification
- `ModelSynced` - Models synchronized from providers
- `ProviderSwitched` - Conversation switched providers

### Event Listeners (Queued)
- `CostTrackingListener` - Background cost calculation and budget monitoring
- `AnalyticsListener` - Usage analytics and reporting
- `AgentActionListener` - Execute agent actions (meetings, emails, CRM updates)
- `WebhookListener` - Send notifications to external systems
- `NotificationListener` - User notifications and alerts
- `MemoryListener` - Update learning and context systems

## Caching Strategy

### Multi-Level Caching
1. **Request Cache**: In-memory caching for single request lifecycle
2. **Model Cache**: Cache available models (TTL: 1 hour)
3. **Cost Cache**: Cache pricing information (TTL: 24 hours)
4. **Response Cache**: Optional response caching for identical requests

### Cache Keys
```
ai:models:{provider_id}
ai:costs:{provider_id}:{model_id}
ai:response:{hash}
ai:conversation:{conversation_id}:context
```

## Security Architecture

### Credential Management
- Encrypted storage of API keys using Laravel's encryption
- Environment-based configuration with validation
- Credential rotation support
- Audit logging for credential access

### Request Security
- Rate limiting per provider and user
- Request validation and sanitization
- Response filtering for sensitive data
- Audit trails for all AI interactions

## Background Processing

### Queue Jobs
- `SyncModelsJob`: Periodic model synchronization
- `CalculateCostsJob`: Batch cost calculations
- `GenerateAnalyticsJob`: Usage analytics generation
- `CleanupConversationsJob`: Archive old conversations

### Scheduling
```php
// In service provider
$schedule->job(SyncModelsJob::class)->hourly();
$schedule->job(CalculateCostsJob::class)->everyFifteenMinutes();
$schedule->job(GenerateAnalyticsJob::class)->daily();
```

## Error Handling & Resilience

### Retry Logic
- Exponential backoff for API failures
- Circuit breaker pattern for provider outages
- Graceful degradation to alternative providers
- Comprehensive error logging and monitoring

### Fallback Strategies
- Provider failover for high availability
- Cached responses for repeated requests
- Offline mode with local models (future)
- User notification for service disruptions

## Performance Considerations

### Optimization Strategies
- Connection pooling for HTTP clients
- Async processing for non-critical operations
- Database query optimization with proper indexing
- Memory-efficient conversation handling

### Monitoring & Metrics
- Response time tracking per provider
- Cost efficiency analysis
- Error rate monitoring
- Resource usage optimization

## Implementation Status

### Budget Management System
**Status**: 🔴 **Critical Implementation Issues**
**Test Coverage**: 53/53 tests passing (100%) with workarounds
**Production Ready**: ❌ No - Critical gaps exist

**⚠️ See [Budget Implementation Issues](../BUDGET_IMPLEMENTATION_ISSUES.md) for detailed breakdown of:**
- Missing middleware methods (checkProjectBudgetOptimized, checkOrganizationBudgetOptimized)
- Event constructor mismatches (BudgetThresholdReached)
- Missing services (BudgetHierarchyService, BudgetStatusService, BudgetDashboardService)
- Database schema gaps (missing tables and columns)
- Interface mismatches between components

**Priority**: P0 - Required for Sprint4b Story 2 completion
