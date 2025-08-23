# AI Event System

## Overview

The JTD Laravel AI event system provides a powerful, Laravel-native way to handle post-response actions and enable sophisticated AI workflows. By leveraging Laravel's event system, the package achieves significant performance improvements, enables agent-like capabilities, and provides clean extension points for custom functionality.

## Architecture

### Event-Driven Request Flow

```
Request → Middleware → Driver → MCP → AI Provider → Response → Fire Events → Return (immediately)
                                                           ↓
                                                    Background Event Handlers:
                                                    - Cost tracking & analytics
                                                    - Memory & learning updates  
                                                    - Agent actions & integrations
                                                    - Notifications & webhooks
                                                    - Audit logging & monitoring
```

### Performance Benefits

**Traditional Synchronous Flow:**
```
Request → AI Processing → Response → Cost Calc → Analytics → Budget Check → Return
~2000ms + 200ms + 100ms + 50ms = ~2350ms total response time
```

**Event-Driven Asynchronous Flow:**
```
Request → AI Processing → Response → Fire Events → Return
~2000ms + 5ms = ~2005ms (85% faster response!)

Background Processing (parallel):
- Cost calculation: 200ms
- Analytics update: 100ms
- Budget checks: 50ms
- Memory updates: 150ms
- Agent actions: 300ms
- Webhook notifications: 200ms
```

## Core Events

### Response Lifecycle Events

#### `MessageSent`
Fired when a user message is sent to an AI provider.

```php
<?php

namespace JTD\LaravelAI\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use JTD\LaravelAI\Models\AIMessage;

class MessageSent
{
    use Dispatchable, SerializesModels;
    
    public function __construct(
        public AIMessage $message,
        public array $middlewareApplied = [],
        public array $mcpServersUsed = [],
        public float $processingTime = 0
    ) {}
}
```

#### `ResponseGenerated`
Fired when an AI response is generated and ready to return to the user.

```php
<?php

namespace JTD\LaravelAI\Events;

class ResponseGenerated
{
    use Dispatchable, SerializesModels;
    
    public function __construct(
        public AIMessage $message,
        public AIResponse $response,
        public array $context = [],
        public float $totalProcessingTime = 0,
        public array $providerMetadata = []
    ) {}
}
```

#### `ConversationUpdated`
Fired when a conversation is modified (new messages, context changes, etc.).

```php
<?php

namespace JTD\LaravelAI\Events;

class ConversationUpdated
{
    use Dispatchable, SerializesModels;
    
    public function __construct(
        public AIConversation $conversation,
        public string $updateType, // 'message_added', 'context_updated', 'metadata_changed'
        public array $changes = []
    ) {}
}
```

### Cost and Analytics Events

#### `CostCalculated`
Fired when cost calculation is completed for a message or conversation.

```php
<?php

namespace JTD\LaravelAI\Events;

class CostCalculated
{
    use Dispatchable, SerializesModels;
    
    public function __construct(
        public int $userId,
        public string $provider,
        public string $model,
        public float $cost,
        public int $inputTokens,
        public int $outputTokens,
        public ?int $conversationId = null,
        public ?int $messageId = null
    ) {}
}
```

#### `BudgetThresholdReached`
Fired when a user approaches or exceeds their budget limits.

```php
<?php

namespace JTD\LaravelAI\Events;

class BudgetThresholdReached
{
    use Dispatchable, SerializesModels;
    
    public function __construct(
        public int $userId,
        public string $budgetType, // 'daily', 'monthly', 'per_request'
        public float $currentSpending,
        public float $budgetLimit,
        public float $percentage, // Percentage of budget used
        public string $severity // 'warning', 'critical', 'exceeded'
    ) {}
}
```

#### `UsageAnalyticsRecorded`
Fired when usage analytics are recorded for reporting and optimization.

```php
<?php

namespace JTD\LaravelAI\Events;

class UsageAnalyticsRecorded
{
    use Dispatchable, SerializesModels;
    
    public function __construct(
        public array $analyticsData,
        public string $period, // 'hourly', 'daily', 'weekly', 'monthly'
        public string $aggregationType // 'user', 'provider', 'model', 'organization'
    ) {}
}
```

### Agent and Integration Events

#### `AgentActionRequested`
Fired when an AI response indicates that an agent action should be taken.

```php
<?php

namespace JTD\LaravelAI\Events;

class AgentActionRequested
{
    use Dispatchable, SerializesModels;
    
    public function __construct(
        public string $action, // 'schedule_meeting', 'send_email', 'update_crm'
        public array $parameters,
        public AIMessage $originalMessage,
        public AIResponse $response,
        public array $context = [],
        public int $priority = 5 // 1-10, higher = more urgent
    ) {}
}
```

#### `AgentActionCompleted`
Fired when an agent action has been completed (successfully or with errors).

```php
<?php

namespace JTD\LaravelAI\Events;

class AgentActionCompleted
{
    use Dispatchable, SerializesModels;
    
    public function __construct(
        public string $action,
        public array $parameters,
        public bool $success,
        public array $result = [],
        public ?string $error = null,
        public float $executionTime = 0
    ) {}
}
```

#### `ExternalIntegrationTriggered`
Fired when external systems need to be notified or updated.

```php
<?php

namespace JTD\LaravelAI\Events;

class ExternalIntegrationTriggered
{
    use Dispatchable, SerializesModels;
    
    public function __construct(
        public string $integrationType, // 'webhook', 'api_call', 'database_update'
        public string $endpoint,
        public array $payload,
        public array $headers = [],
        public int $retryAttempts = 3
    ) {}
}
```

### System Events

#### `ProviderSwitched`
Fired when a conversation switches from one AI provider to another.

```php
<?php

namespace JTD\LaravelAI\Events;

class ProviderSwitched
{
    use Dispatchable, SerializesModels;
    
    public function __construct(
        public int $conversationId,
        public string $fromProvider,
        public string $toProvider,
        public string $reason, // 'user_request', 'fallback', 'cost_optimization'
        public array $context = []
    ) {}
}
```

#### `ModelSynced`
Fired when models are synchronized from AI providers.

```php
<?php

namespace JTD\LaravelAI\Events;

class ModelSynced
{
    use Dispatchable, SerializesModels;
    
    public function __construct(
        public string $provider,
        public int $modelsAdded,
        public int $modelsUpdated,
        public int $modelsRemoved,
        public array $syncDetails = []
    ) {}
}
```

### AI Function Events

#### `AIFunctionCalled`
Fired when an AI function is called for execution.

```php
<?php

namespace JTD\LaravelAI\Events;

class AIFunctionCalled
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $functionName,
        public array $parameters,
        public int $userId,
        public ?int $conversationId = null,
        public ?int $messageId = null,
        public array $context = []
    ) {}
}
```

#### `AIFunctionCompleted`
Fired when an AI function completes successfully.

```php
<?php

namespace JTD\LaravelAI\Events;

class AIFunctionCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $functionName,
        public array $parameters,
        public mixed $result,
        public float $executionTime,
        public int $userId,
        public ?int $conversationId = null,
        public ?int $messageId = null,
        public array $context = []
    ) {}
}
```

#### `AIFunctionFailed`
Fired when an AI function fails during execution.

```php
<?php

namespace JTD\LaravelAI\Events;

class AIFunctionFailed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $functionName,
        public array $parameters,
        public \Throwable $error,
        public float $executionTime,
        public int $userId,
        public ?int $conversationId = null,
        public ?int $messageId = null,
        public array $context = []
    ) {}
}
```

## Event Listeners

### Cost and Analytics Listeners

#### `CostTrackingListener`
Handles cost calculation and budget monitoring in the background.

```php
<?php

namespace JTD\LaravelAI\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use JTD\LaravelAI\Events\ResponseGenerated;
use JTD\LaravelAI\Services\CostTrackingService;

class CostTrackingListener implements ShouldQueue
{
    public $queue = 'ai-analytics';
    
    public function __construct(
        protected CostTrackingService $costTracker
    ) {}
    
    public function handle(ResponseGenerated $event): void
    {
        // Calculate and record costs
        $cost = $this->costTracker->calculateMessageCost($event->message, $event->response);
        
        // Fire cost calculated event
        event(new CostCalculated(
            userId: $event->message->user_id,
            provider: $event->response->provider,
            model: $event->response->model,
            cost: $cost,
            inputTokens: $event->response->input_tokens,
            outputTokens: $event->response->output_tokens,
            conversationId: $event->message->conversation_id,
            messageId: $event->message->id
        ));
        
        // Check budget thresholds
        $this->costTracker->checkBudgetThresholds($event->message->user_id, $cost);
    }
}
```

#### `AnalyticsListener`
Records usage analytics for reporting and optimization.

```php
<?php

namespace JTD\LaravelAI\Listeners;

class AnalyticsListener implements ShouldQueue
{
    public $queue = 'ai-analytics';
    
    public function handle(ResponseGenerated $event): void
    {
        $analyticsData = [
            'user_id' => $event->message->user_id,
            'provider' => $event->response->provider,
            'model' => $event->response->model,
            'tokens_used' => $event->response->tokens_used,
            'response_time' => $event->totalProcessingTime,
            'middleware_count' => count($event->context['middleware_applied'] ?? []),
            'mcp_servers_used' => count($event->context['mcp_servers_used'] ?? []),
            'timestamp' => now(),
        ];
        
        // Store analytics data
        AIUsageAnalytics::create($analyticsData);
        
        // Fire analytics recorded event
        event(new UsageAnalyticsRecorded($analyticsData, 'real_time', 'user'));
    }
}
```

### Agent Action Listeners

#### `AgentActionListener`
Processes agent action requests and executes them.

```php
<?php

namespace JTD\LaravelAI\Listeners;

class AgentActionListener implements ShouldQueue
{
    public $queue = 'ai-agents';
    public $timeout = 300; // 5 minutes for complex actions
    
    public function handle(AgentActionRequested $event): void
    {
        $startTime = microtime(true);
        
        try {
            $result = $this->executeAction($event->action, $event->parameters, $event->context);
            
            event(new AgentActionCompleted(
                action: $event->action,
                parameters: $event->parameters,
                success: true,
                result: $result,
                executionTime: microtime(true) - $startTime
            ));
            
        } catch (\Exception $e) {
            event(new AgentActionCompleted(
                action: $event->action,
                parameters: $event->parameters,
                success: false,
                error: $e->getMessage(),
                executionTime: microtime(true) - $startTime
            ));
            
            Log::error('Agent action failed', [
                'action' => $event->action,
                'error' => $e->getMessage(),
                'parameters' => $event->parameters,
            ]);
        }
    }
    
    protected function executeAction(string $action, array $parameters, array $context): array
    {
        return match ($action) {
            'schedule_meeting' => $this->scheduleMeeting($parameters, $context),
            'send_email' => $this->sendEmail($parameters, $context),
            'update_crm' => $this->updateCRM($parameters, $context),
            'create_task' => $this->createTask($parameters, $context),
            default => throw new \InvalidArgumentException("Unknown action: {$action}"),
        };
    }
    
    protected function scheduleMeeting(array $parameters, array $context): array
    {
        // Integration with calendar systems (Google Calendar, Outlook, etc.)
        $calendarService = app(CalendarService::class);
        
        return $calendarService->createEvent([
            'title' => $parameters['title'] ?? 'AI Scheduled Meeting',
            'start_time' => $parameters['start_time'],
            'end_time' => $parameters['end_time'],
            'attendees' => $parameters['attendees'] ?? [],
            'description' => $parameters['description'] ?? '',
        ]);
    }
}
```

### Integration Listeners

#### `WebhookListener`
Handles webhook notifications to external systems.

```php
<?php

namespace JTD\LaravelAI\Listeners;

class WebhookListener implements ShouldQueue
{
    public $queue = 'ai-integrations';
    public $tries = 3;
    
    public function handle(ExternalIntegrationTriggered $event): void
    {
        if ($event->integrationType !== 'webhook') {
            return;
        }
        
        $response = Http::withHeaders($event->headers)
            ->timeout(30)
            ->retry(3, 1000)
            ->post($event->endpoint, $event->payload);
        
        if ($response->failed()) {
            throw new \Exception("Webhook failed: {$response->status()} - {$response->body()}");
        }
        
        Log::info('Webhook sent successfully', [
            'endpoint' => $event->endpoint,
            'status' => $response->status(),
        ]);
    }
}
```

#### `NotificationListener`
Sends notifications to users about AI interactions, budget alerts, etc.

```php
<?php

namespace JTD\LaravelAI\Listeners;

class NotificationListener implements ShouldQueue
{
    public function handle(BudgetThresholdReached $event): void
    {
        $user = User::find($event->userId);
        
        if (!$user) {
            return;
        }
        
        $notification = match ($event->severity) {
            'warning' => new BudgetWarningNotification($event),
            'critical' => new BudgetCriticalNotification($event),
            'exceeded' => new BudgetExceededNotification($event),
        };
        
        $user->notify($notification);
        
        // Also send to administrators if critical
        if (in_array($event->severity, ['critical', 'exceeded'])) {
            $admins = User::where('role', 'admin')->get();
            Notification::send($admins, new AdminBudgetAlertNotification($event));
        }
    }
}
```

## Event Registration and Configuration

### Service Provider Registration

```php
<?php

namespace JTD\LaravelAI;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;

class LaravelAIServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerEventListeners();
    }
    
    protected function registerEventListeners(): void
    {
        // Core system events
        Event::listen(ResponseGenerated::class, CostTrackingListener::class);
        Event::listen(ResponseGenerated::class, AnalyticsListener::class);
        Event::listen(ConversationUpdated::class, ConversationCacheListener::class);
        
        // Cost and budget events
        Event::listen(CostCalculated::class, BudgetMonitoringListener::class);
        Event::listen(BudgetThresholdReached::class, NotificationListener::class);
        
        // Agent and integration events
        Event::listen(AgentActionRequested::class, AgentActionListener::class);
        Event::listen(ExternalIntegrationTriggered::class, WebhookListener::class);
        
        // System events
        Event::listen(ModelSynced::class, ModelCacheListener::class);
        Event::listen(ProviderSwitched::class, ProviderAnalyticsListener::class);
    }
}
```

### Configuration

Configure event behavior in `config/ai.php`:

```php
'events' => [
    'enabled' => env('AI_EVENTS_ENABLED', true),
    
    'queues' => [
        'analytics' => env('AI_ANALYTICS_QUEUE', 'ai-analytics'),
        'agents' => env('AI_AGENTS_QUEUE', 'ai-agents'),
        'integrations' => env('AI_INTEGRATIONS_QUEUE', 'ai-integrations'),
        'notifications' => env('AI_NOTIFICATIONS_QUEUE', 'ai-notifications'),
    ],
    
    'listeners' => [
        'cost_tracking' => [
            'enabled' => env('AI_COST_TRACKING_EVENTS', true),
            'queue' => 'ai-analytics',
            'timeout' => 60,
        ],
        'analytics' => [
            'enabled' => env('AI_ANALYTICS_EVENTS', true),
            'queue' => 'ai-analytics',
            'batch_size' => 100,
        ],
        'agent_actions' => [
            'enabled' => env('AI_AGENT_ACTIONS_ENABLED', false),
            'queue' => 'ai-agents',
            'timeout' => 300,
            'max_retries' => 3,
        ],
        'webhooks' => [
            'enabled' => env('AI_WEBHOOKS_ENABLED', false),
            'timeout' => 30,
            'max_retries' => 3,
        ],
    ],
    
    'agent_actions' => [
        'allowed_actions' => [
            'schedule_meeting',
            'send_email',
            'update_crm',
            'create_task',
            'post_to_slack',
        ],
        'security_checks' => true,
        'user_confirmation_required' => env('AI_AGENT_CONFIRMATION_REQUIRED', true),
    ],
],
```

## Usage Examples

### Basic Event Usage

```php
// Events are fired automatically during AI interactions
$response = AI::conversation()
    ->message('Schedule a meeting with John tomorrow at 2pm')
    ->send();

// This automatically fires:
// - MessageSent event
// - ResponseGenerated event  
// - CostCalculated event
// - AgentActionRequested event (if agent actions are detected)
```

### Custom Event Listeners

```php
// Create custom listener
class CustomAnalyticsListener implements ShouldQueue
{
    public function handle(ResponseGenerated $event): void
    {
        // Custom analytics logic
        $this->sendToCustomAnalytics($event);
    }
}

// Register in service provider
Event::listen(ResponseGenerated::class, CustomAnalyticsListener::class);
```

### Manual Event Firing

```php
// Fire custom events manually
event(new AgentActionRequested(
    action: 'custom_action',
    parameters: ['param1' => 'value1'],
    originalMessage: $message,
    response: $response
));
```

### Event-Based Workflows

```php
// Complex workflow using events
class WorkflowListener
{
    public function handle(AgentActionCompleted $event): void
    {
        if ($event->action === 'schedule_meeting' && $event->success) {
            // Trigger follow-up actions
            event(new AgentActionRequested(
                action: 'send_email',
                parameters: [
                    'to' => $event->result['attendees'],
                    'subject' => 'Meeting Scheduled',
                    'body' => 'Your meeting has been scheduled successfully.',
                ]
            ));
        }
    }
}
```

## Performance and Monitoring

### Event Performance Tracking

```php
class EventPerformanceListener
{
    public function handle($event): void
    {
        $startTime = microtime(true);
        
        // Process event
        $this->processEvent($event);
        
        $executionTime = microtime(true) - $startTime;
        
        // Track performance
        Cache::increment("event_performance:" . get_class($event));
        Cache::put("event_time:" . get_class($event), $executionTime, 3600);
    }
}
```

### Event Monitoring

```php
// Monitor event processing
$eventMetrics = [
    'events_fired_today' => Cache::get('events_fired:' . today()),
    'failed_events_today' => Cache::get('failed_events:' . today()),
    'average_processing_time' => Cache::get('avg_event_time'),
    'queue_sizes' => [
        'analytics' => Queue::size('ai-analytics'),
        'agents' => Queue::size('ai-agents'),
        'integrations' => Queue::size('ai-integrations'),
    ],
];
```

## Best Practices

### Event Design Guidelines

1. **Keep Events Focused**: Each event should represent a single, well-defined occurrence
2. **Include Rich Context**: Provide enough information for listeners to make decisions
3. **Use Queues Appropriately**: Queue heavy processing, keep light operations synchronous
4. **Handle Failures Gracefully**: Implement retry logic and dead letter queues
5. **Monitor Performance**: Track event processing times and queue sizes
6. **Maintain Backward Compatibility**: Be careful when changing event structures

### Security Considerations

1. **Validate Event Data**: Always validate event payloads before processing
2. **Secure Agent Actions**: Implement proper authorization for agent actions
3. **Audit Event Processing**: Log important events for security auditing
4. **Rate Limiting**: Implement rate limiting for event-triggered actions
5. **Sanitize Webhook Payloads**: Clean data before sending to external systems

### Testing Events

```php
<?php

namespace Tests\Feature\Events;

use Tests\TestCase;
use Illuminate\Support\Facades\Event;
use JTD\LaravelAI\Events\ResponseGenerated;

class EventSystemTest extends TestCase
{
    public function test_response_generated_event_is_fired(): void
    {
        Event::fake();
        
        $response = AI::conversation()
            ->message('Hello')
            ->send();
        
        Event::assertDispatched(ResponseGenerated::class, function ($event) {
            return $event->response->content !== null;
        });
    }
    
    public function test_cost_tracking_listener_processes_event(): void
    {
        $listener = new CostTrackingListener(app(CostTrackingService::class));
        $event = new ResponseGenerated($message, $response);
        
        $listener->handle($event);
        
        $this->assertDatabaseHas('ai_cost_tracking', [
            'user_id' => $message->user_id,
            'provider' => $response->provider,
        ]);
    }
}
```
