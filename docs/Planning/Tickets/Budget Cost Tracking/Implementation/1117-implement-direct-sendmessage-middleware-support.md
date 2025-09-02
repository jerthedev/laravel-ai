# Implement Direct SendMessage Middleware Support

**Ticket ID**: Implementation/1011-implement-direct-sendmessage-middleware-support  
**Date Created**: 2025-01-26  
**Status**: Not Started  

## Title
Implement Middleware Support for Direct SendMessage Pattern

## Description
Add middleware support to the Direct SendMessage pattern (`AI::provider()->sendMessage()`) by implementing 'middleware' option processing in the options array as specified in BUDGET_COST_TRACKING_SPECIFICATION.md.

**Current State**: 
- `AI::provider()->sendMessage()` does not process 'middleware' option in options array
- Specification expects middleware support: `['middleware' => ['rate-limiting', 'audit-logging']]`
- Neither AIManager nor AbstractAIProvider handle middleware option
- Direct SendMessage pattern bypasses middleware entirely

**Desired State**:
- Direct SendMessage pattern supports 'middleware' option in options array
- Middleware executes for direct calls just like ConversationBuilder pattern
- Both global and optional middleware work with direct pattern
- Consistent middleware behavior across all API patterns

**Critical Issues Addressed**:
- Enables middleware for direct AI provider calls
- Provides consistent middleware behavior across API patterns
- Supports budget enforcement and cost tracking for direct calls
- Maintains backward compatibility with existing direct calls

**Dependencies**:
- Requires ticket 1109 (AIRequest class) to be completed first
- Requires MiddlewareManager to be updated for AIRequest support
- Requires middleware configuration system to be functional

## Related Documentation
- [ ] docs/BUDGET_COST_TRACKING_SPECIFICATION.md - Defines Direct SendMessage middleware requirements
- [ ] docs/UNIFIED_TOOL_SYSTEM_INTEGRATION_REPORT.md - Shows API examples with middleware option
- [ ] Laravel Service Container documentation - For middleware resolution

## Related Files
- [ ] src/Services/AIManager.php - UPDATE: Process middleware option in sendMessage()
- [ ] src/Providers/AbstractAIProvider.php - UPDATE: Handle middleware option processing
- [ ] src/Services/MiddlewareManager.php - VERIFY: Can handle direct sendMessage calls
- [ ] src/Facades/AI.php - VERIFY: Facade supports middleware option passthrough

## Related Tests
- [ ] tests/Unit/Services/AIManagerTest.php - UPDATE: Test middleware option processing
- [ ] tests/Unit/Providers/AbstractAIProviderTest.php - UPDATE: Test middleware handling
- [ ] tests/Integration/DirectSendMessageMiddlewareTest.php - NEW: Integration tests
- [ ] tests/E2E/DirectSendMessageE2ETest.php - NEW: End-to-end tests with real providers

## Acceptance Criteria
- [ ] AIManager.sendMessage() processes 'middleware' option from options array
- [ ] AbstractAIProvider.sendMessage() supports middleware option processing
- [ ] Direct SendMessage calls execute middleware pipeline when middleware option provided
- [ ] Global middleware always executes for direct calls (when middleware enabled)
- [ ] Optional middleware executes when specified in middleware option
- [ ] Middleware option supports both string and array formats
- [ ] Backward compatibility maintained - existing direct calls work unchanged
- [ ] AI Facade properly passes middleware option through to providers
- [ ] Error handling works correctly when middleware fails
- [ ] Performance overhead is minimal (<10ms) for direct calls with middleware
- [ ] Unit tests cover all middleware option processing scenarios
- [ ] Integration tests verify middleware execution with direct calls
- [ ] E2E tests work with real AI providers and middleware
- [ ] Documentation updated with Direct SendMessage middleware examples

## Implementation Details

### AIManager Update
```php
public function sendMessage(AIMessage $message, array $options = []): AIResponse
{
    $provider = $this->driver();
    
    // Process tool options if present
    $options = $this->processToolOptions($options);
    
    // NEW: Process middleware option
    if (isset($options['middleware']) || config('ai.middleware.enabled', false)) {
        return $this->processWithMiddleware($message, $options);
    }
    
    return $provider->sendMessage($message, $options);
}

protected function processWithMiddleware(AIMessage $message, array $options): AIResponse
{
    // Create AIRequest from AIMessage
    $request = AIRequest::fromMessage($message, [
        'provider' => $this->getDefaultDriver(),
        'model' => $options['model'] ?? $this->getDefaultModel(),
        'user_id' => $options['user_id'] ?? auth()->id(),
        'options' => $options,
    ]);
    
    // Get middleware from options or use empty array
    $middleware = $options['middleware'] ?? [];
    if (is_string($middleware)) {
        $middleware = [$middleware];
    }
    
    // Process through middleware pipeline
    return app(MiddlewareManager::class)->process($request, $middleware);
}
```

### AbstractAIProvider Update
```php
public function sendMessage($message, array $options = []): AIResponse
{
    // If middleware option present, delegate to AIManager for processing
    if (isset($options['middleware'])) {
        return app('laravel-ai')->sendMessage(
            is_array($message) ? new AIMessage($message) : $message,
            $options
        );
    }
    
    // Existing implementation for direct provider calls
    $messages = is_array($message) ? $message : [$message];
    $mergedOptions = array_merge($this->options, $options);
    
    return $this->makeRequest($messages, $mergedOptions);
}
```

### Usage Examples (from Specification)
```php
// Direct SendMessage with middleware
$response = AI::provider('openai')
    ->model('gpt-4')
    ->sendMessage('Generate a comprehensive report', [
        'budget_limit' => 5.00,
        'user_id' => auth()->id(),
        'middleware' => ['rate-limiting', 'audit-logging'],
    ]);

// Single middleware
$response = AI::provider('openai')->sendMessage('Hello', [
    'middleware' => 'cost-tracking'
]);

// Array of middleware
$response = AI::provider('openai')->sendMessage('Hello', [
    'middleware' => ['cost-tracking', 'budget-enforcement']
]);
```

## AI Prompt
```
You are a Laravel AI package development expert. Please read this ticket fully: docs/Planning/Tickets/Budget Cost Tracking/Implementation/1011-implement-direct-sendmessage-middleware-support.md, including the title, description, related documentation, files, and tests listed above.

This ticket implements middleware support for the Direct SendMessage pattern to provide consistent middleware behavior across all API patterns.

Based on this ticket:
1. Create a comprehensive task list for implementing middleware support in Direct SendMessage
2. Plan the integration between AIManager and MiddlewareManager for direct calls
3. Design the middleware option processing logic in both AIManager and AbstractAIProvider
4. Plan AIRequest creation from AIMessage for direct calls
5. Design backward compatibility to ensure existing direct calls continue working
6. Plan comprehensive testing including edge cases and error conditions
7. Ensure performance overhead is minimal for direct calls

Focus on creating seamless middleware integration that works consistently across ConversationBuilder and Direct SendMessage patterns.
```

## Notes
This ticket is essential for providing consistent middleware behavior across all API patterns. The implementation must maintain backward compatibility while adding powerful middleware capabilities to direct provider calls.

## Estimated Effort
Medium (1 day)

## Dependencies
- [ ] Ticket 1109 - AIRequest class must be completed first
- [ ] MiddlewareManager must support AIRequest processing
- [ ] Middleware configuration system must be functional
