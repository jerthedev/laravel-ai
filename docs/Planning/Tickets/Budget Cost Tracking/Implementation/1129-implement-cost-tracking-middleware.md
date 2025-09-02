# 1015 - Implement CostTrackingMiddleware

**Phase**: Implementation  
**Priority**: P2 - MEDIUM  
**Effort**: Medium (2 days)  
**Status**: Ready for Implementation  

## Title
Implement CostTrackingMiddleware to provide middleware-based cost tracking as specified in the system architecture.

## Description

### Problem Statement
The current system uses event-driven cost tracking via CostTrackingListener, but the specification expects middleware-based cost tracking. This creates a design mismatch where cost tracking happens after the response is generated rather than being integrated into the request/response pipeline.

### Current vs Expected Architecture
**Current (Event-Driven)**:
1. AI request made → Response generated → ResponseGenerated event fired → CostTrackingListener calculates cost

**Expected (Middleware-Based)**:
1. AI request made → CostTrackingMiddleware intercepts → Response generated → Middleware calculates cost → Response returned

### Solution Approach
Implement CostTrackingMiddleware following the specification while maintaining compatibility with the existing event-driven system.

## Related Files

### Files to Create
- `src/Middleware/CostTrackingMiddleware.php` (new middleware)
- `tests/Unit/Middleware/CostTrackingMiddlewareTest.php` (unit tests)

### Files to Review
- `src/Listeners/CostTrackingListener.php` (existing event-driven approach)
- `src/Middleware/BudgetEnforcementMiddleware.php` (middleware pattern reference)
- `docs/BUDGET_COST_TRACKING_SPECIFICATION.md` (specification requirements)

## Implementation Details

### Middleware Class Structure
```php
<?php

namespace JerTheDev\LaravelAI\Middleware;

use JerTheDev\LaravelAI\Contracts\AIMiddlewareInterface;
use JerTheDev\LaravelAI\Models\AIMessage;
use JerTheDev\LaravelAI\Models\AIResponse;
use JerTheDev\LaravelAI\Events\CostCalculated;

class CostTrackingMiddleware implements AIMiddlewareInterface
{
    public function handle(AIMessage $message, \Closure $next): AIResponse
    {
        // Before: Record request start time
        $startTime = microtime(true);

        $response = $next($message);

        // After: Calculate and record costs
        $this->calculateAndRecordCost($message, $response, $startTime);

        return $response;
    }
    
    private function calculateAndRecordCost(AIMessage $message, AIResponse $response, float $startTime): void
    {
        // Extract token usage from AI response
        $tokenUsage = $this->extractTokenUsage($response);

        // Calculate cost based on provider/model rates
        $cost = $this->calculateCost(
            $message->provider,
            $message->model,
            $tokenUsage
        );

        // Fire cost calculation event
        event(new CostCalculated(
            userId: $message->user_id,
            provider: $message->provider,
            model: $message->model,
            inputTokens: $tokenUsage['input'],
            outputTokens: $tokenUsage['output'],
            cost: $cost,
            metadata: [
                'execution_time' => (microtime(true) - $startTime) * 1000,
                'message_id' => $message->id,
            ]
        ));
    }
}
```

### Integration Strategy
1. **Dual System**: Maintain both middleware and event-driven approaches
2. **Configuration**: Allow switching between approaches via config
3. **Migration Path**: Gradual migration from events to middleware
4. **Compatibility**: Ensure both systems can coexist

## Acceptance Criteria

### Functional Requirements
- [ ] Middleware intercepts AI requests correctly
- [ ] Cost calculation happens after response generation
- [ ] CostCalculated events fired with accurate data
- [ ] Token usage extraction works for all providers
- [ ] Execution time tracking included in metadata
- [ ] Compatible with existing event-driven system

### Technical Requirements
- [ ] Implements AIMiddlewareInterface correctly
- [ ] Performance overhead <5ms
- [ ] Proper error handling for cost calculation failures
- [ ] Logging for debugging and audit trail
- [ ] Thread-safe for concurrent requests

### Integration Requirements
- [ ] Works with middleware pipeline
- [ ] Compatible with BudgetEnforcementMiddleware
- [ ] Configurable via Laravel config
- [ ] Can be enabled/disabled independently

## Testing Strategy

### Unit Tests
1. **Test middleware execution**
2. **Test cost calculation accuracy**
3. **Test event firing**
4. **Test error handling**

### Integration Tests
1. **Test with middleware pipeline**
2. **Test with real AI providers**
3. **Test performance impact**

## Implementation Plan

### Day 1: Core Middleware
- Create CostTrackingMiddleware class
- Implement basic middleware structure
- Add cost calculation logic

### Day 2: Testing and Integration
- Write comprehensive tests
- Test integration with pipeline
- Performance optimization

## Definition of Done

### Code Complete
- [ ] CostTrackingMiddleware fully implemented
- [ ] Proper error handling and logging
- [ ] Configuration support added

### Testing Complete
- [ ] Unit tests written and passing
- [ ] Integration tests successful
- [ ] Performance tests show acceptable overhead

---

## AI Prompt

You are implementing ticket 1015-implement-cost-tracking-middleware.md.

**Context**: System needs middleware-based cost tracking to match specification requirements.

**Task**: Create CostTrackingMiddleware following the specification pattern.

**Instructions**:
1. Create comprehensive task list
2. Pause for user review
3. Implement after approval
4. Ensure <5ms performance overhead
5. Test thoroughly

**Critical**: This aligns the system with specification requirements for middleware-based cost tracking.
