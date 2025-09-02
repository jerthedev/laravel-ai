# Update MiddlewareManager for AIRequest Support

**Ticket ID**: Implementation/1013-update-middleware-manager-airequest-support  
**Date Created**: 2025-01-26  
**Status**: Not Started  

## Title
Update MiddlewareManager to Support AIRequest Objects and Conversion

## Description
Update the MiddlewareManager to handle AIRequest objects instead of AIMessage objects, including conversion logic to maintain backward compatibility during the transition period.

**Current State**: 
- MiddlewareManager processes AIMessage objects through middleware pipeline
- Pipeline execution works correctly but uses wrong object type
- No conversion between AIMessage and AIRequest
- Method signatures expect AIMessage instead of AIRequest

**Desired State**:
- MiddlewareManager processes AIRequest objects through middleware pipeline
- Automatic conversion from AIMessage to AIRequest when needed
- Backward compatibility maintained during transition
- Updated method signatures to use AIRequest
- Integration with ConversationBuilder and Direct SendMessage patterns

**Critical Issues Addressed**:
- Enables middleware to use AIRequest pattern as specified
- Provides foundation for proper middleware functionality
- Maintains backward compatibility during transition
- Supports both API patterns (ConversationBuilder and Direct SendMessage)

**Dependencies**:
- Requires ticket 1109 (AIRequest class) to be completed first
- Requires updated AIMiddlewareInterface to be in place

## Related Documentation
- [ ] docs/BUDGET_COST_TRACKING_SPECIFICATION.md - For AIRequest requirements
- [ ] Laravel Service Container documentation - For dependency injection patterns

## Related Files
- [ ] src/Services/MiddlewareManager.php - UPDATE: Support AIRequest processing
- [ ] src/Services/ConversationBuilder.php - UPDATE: Create AIRequest for middleware
- [ ] src/Services/AIManager.php - UPDATE: Handle middleware with AIRequest

## Related Tests
- [ ] tests/Unit/Services/MiddlewareManagerTest.php - UPDATE: Test AIRequest processing
- [ ] tests/Integration/MiddlewarePipelineTest.php - UPDATE: Test AIRequest flow

## Acceptance Criteria
- [ ] MiddlewareManager.process() method accepts AIRequest objects
- [ ] Automatic conversion from AIMessage to AIRequest when needed
- [ ] Pipeline execution works correctly with AIRequest objects
- [ ] ConversationBuilder creates AIRequest objects for middleware processing
- [ ] Direct SendMessage pattern creates AIRequest objects when middleware specified
- [ ] Backward compatibility maintained for existing AIMessage usage
- [ ] Error handling works correctly with AIRequest objects
- [ ] Performance overhead minimal for conversion operations
- [ ] Unit tests verify AIRequest processing functionality
- [ ] Integration tests verify complete pipeline works with AIRequest

## Implementation Details

### Updated MiddlewareManager Methods
```php
/**
 * Process an AI request through the middleware stack.
 */
public function process(AIRequest $request, array $middleware = []): AIResponse
{
    $stack = $this->buildStack(array_merge($this->globalMiddleware, $middleware));
    return $stack($request);
}

/**
 * Process AIMessage through middleware (backward compatibility).
 */
public function processMessage(AIMessage $message, array $middleware = [], array $context = []): AIResponse
{
    $request = $this->convertMessageToRequest($message, $context);
    return $this->process($request, $middleware);
}

/**
 * Convert AIMessage to AIRequest with context.
 */
protected function convertMessageToRequest(AIMessage $message, array $context): AIRequest
{
    $defaultContext = [
        'provider' => config('ai.default_provider', 'openai'),
        'model' => config('ai.default_model', 'gpt-4'),
        'user_id' => auth()->id() ?? 1,
    ];
    
    return AIRequest::fromMessage($message, array_merge($defaultContext, $context));
}
```

### Updated Pipeline Building
```php
protected function buildStack(array $middleware): Closure
{
    return array_reduce(
        array_reverse($middleware),
        function ($next, $middleware) {
            return function (AIRequest $request) use ($next, $middleware) {
                $instance = $this->resolveMiddleware($middleware);
                
                // Track applied middleware
                $request->setMetadata('middleware_applied', 
                    array_merge($request->getMetadataValue('middleware_applied', []), [$middleware])
                );
                
                $startTime = microtime(true);
                
                try {
                    $response = $instance->handle($request, $next);
                    $this->logPerformance($middleware, microtime(true) - $startTime);
                    return $response;
                } catch (\Exception $e) {
                    Log::error('Middleware failed', [
                        'middleware' => $middleware,
                        'error' => $e->getMessage(),
                        'request_id' => $request->getId(),
                    ]);
                    
                    return $next($request);
                }
            };
        },
        function (AIRequest $request) {
            return $this->finalHandler($request);
        }
    );
}
```

### ConversationBuilder Integration
```php
// In ConversationBuilder
public function send(): AIResponse
{
    if ($this->middlewareEnabled && !empty($this->middleware)) {
        return $this->sendWithMiddleware();
    }
    
    return $this->sendDirect();
}

protected function sendWithMiddleware(): AIResponse
{
    $message = $this->buildMessage();
    $context = [
        'provider' => $this->provider,
        'model' => $this->model,
        'user_id' => $this->userId,
        'options' => $this->options,
    ];
    
    return app(MiddlewareManager::class)->processMessage($message, $this->middleware, $context);
}
```

## AI Prompt
```
You are a Laravel AI package development expert. Please read this ticket fully: docs/Planning/Tickets/Budget Cost Tracking/Implementation/1013-update-middleware-manager-airequest-support.md, including the title, description, related documentation, files, and tests listed above.

This ticket updates the MiddlewareManager to support AIRequest objects while maintaining backward compatibility during the transition.

Based on this ticket:
1. Create a comprehensive task list for updating MiddlewareManager to support AIRequest
2. Plan the conversion logic between AIMessage and AIRequest objects
3. Design backward compatibility strategy during the transition period
4. Plan integration with ConversationBuilder and Direct SendMessage patterns
5. Design error handling for AIRequest processing
6. Plan comprehensive testing of the updated pipeline
7. Ensure performance is maintained during the transition

Focus on creating a smooth transition that maintains system stability while enabling AIRequest functionality.
```

## Notes
This ticket is essential for enabling the middleware system to work with AIRequest objects as specified. The implementation must maintain backward compatibility to avoid breaking existing functionality during the transition.

## Estimated Effort
Medium (1 day)

## Dependencies
- [ ] Ticket 1109 - AIRequest class must be completed first
- [ ] Updated AIMiddlewareInterface must be in place
