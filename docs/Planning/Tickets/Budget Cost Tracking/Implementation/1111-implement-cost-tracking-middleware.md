# Implement CostTrackingMiddleware

**Ticket ID**: Implementation/1009-implement-cost-tracking-middleware  
**Date Created**: 2025-01-26  
**Status**: Not Started  

## Title
Implement CostTrackingMiddleware for Real-time Cost Calculation and Event Firing

## Description
Create the missing CostTrackingMiddleware class that intercepts AI requests, calculates costs from responses, and fires CostCalculated events as specified in BUDGET_COST_TRACKING_SPECIFICATION.md.

**Current State**: 
- CostTrackingMiddleware class does not exist
- Cost tracking is handled by event listeners instead of middleware
- No middleware-based cost calculation as specified
- Missing from config/ai.php middleware configuration

**Desired State**:
- CostTrackingMiddleware class exists and follows specification exactly
- Middleware calculates costs from AI responses in real-time
- Fires CostCalculated events with correct data structure
- Integrates with middleware pipeline for all AI requests
- Supports token usage extraction from all providers (OpenAI, XAI, Gemini)

**Critical Issues Addressed**:
- Implements missing cost tracking middleware as specified
- Provides real-time cost calculation for budget enforcement
- Enables proper event-driven cost processing
- Supports multi-provider cost calculation

**Dependencies**:
- Requires ticket 1109 (AIRequest class) to be completed first
- Depends on PricingService for cost calculation
- Requires CostCalculated event to have correct constructor

## Related Documentation
- [ ] docs/BUDGET_COST_TRACKING_SPECIFICATION.md - Defines CostTrackingMiddleware requirements
- [ ] docs/BUDGET_IMPLEMENTATION_ISSUES.md - Documents missing middleware issue
- [ ] Provider documentation - For token usage extraction patterns

## Related Files
- [ ] src/Middleware/CostTrackingMiddleware.php - NEW: Create cost tracking middleware
- [ ] src/Events/CostCalculated.php - VERIFY: Ensure correct constructor signature
- [ ] src/Services/PricingService.php - INTEGRATE: Use for cost calculation
- [ ] config/ai.php - UPDATE: Add to global and available middleware arrays
- [ ] src/Services/MiddlewareManager.php - VERIFY: Can handle new middleware

## Related Tests
- [ ] tests/Unit/Middleware/CostTrackingMiddlewareTest.php - NEW: Unit tests for middleware
- [ ] tests/Integration/CostTrackingIntegrationTest.php - NEW: Integration tests with providers
- [ ] tests/E2E/CostTrackingE2ETest.php - NEW: End-to-end tests with real providers
- [ ] tests/Feature/MiddlewarePipelineTest.php - UPDATE: Include cost tracking tests

## Acceptance Criteria
- [ ] CostTrackingMiddleware class created following specification exactly
- [ ] Middleware implements AIMiddlewareInterface with AIRequest/AIResponse pattern
- [ ] Records request start time before calling next middleware
- [ ] Extracts token usage from AI responses after provider call
- [ ] Calculates costs using PricingService for all supported providers
- [ ] Fires CostCalculated event with correct data structure and all required fields
- [ ] Supports OpenAI, XAI, and Gemini token usage extraction
- [ ] Handles errors gracefully without blocking requests
- [ ] Includes execution time tracking in metadata
- [ ] Added to config/ai.php global middleware array
- [ ] Added to config/ai.php available middleware array
- [ ] Unit tests provide 100% code coverage
- [ ] Integration tests verify cost calculation accuracy
- [ ] E2E tests work with real AI providers
- [ ] Performance meets <10ms overhead requirement
- [ ] Event integration works with existing listeners

## Implementation Details

### Middleware Structure (from Specification)
```php
class CostTrackingMiddleware implements AIMiddlewareInterface
{
    public function handle(AIRequest $request, Closure $next): AIResponse
    {
        // Before: Record request start time
        $startTime = microtime(true);

        $response = $next($request);

        // After: Calculate and record costs
        $this->calculateAndRecordCost($request, $response, $startTime);

        return $response;
    }
    
    private function calculateAndRecordCost(AIRequest $request, AIResponse $response, float $startTime): void
    {
        // Extract token usage from AI response
        $tokenUsage = $this->extractTokenUsage($response);

        // Calculate cost based on provider/model rates
        $cost = $this->calculateCost(
            $request->getProvider(),
            $request->getModel(),
            $tokenUsage
        );

        // Fire cost calculation event
        event(new CostCalculated(
            userId: $request->getUserId(),
            provider: $request->getProvider(),
            model: $request->getModel(),
            inputTokens: $tokenUsage['input'],
            outputTokens: $tokenUsage['output'],
            cost: $cost,
            metadata: [
                'execution_time' => (microtime(true) - $startTime) * 1000,
                'request_id' => $request->getId(),
            ]
        ));
    }
}
```

### Token Usage Extraction
- OpenAI: Extract from response->tokenUsage
- XAI: Extract from response->tokenUsage (OpenAI-compatible)
- Gemini: Extract from response metadata or calculate from content

### Configuration Updates
```php
// config/ai.php
'middleware' => [
    'global' => [
        'cost-tracking',
        'budget-enforcement',
    ],
    'available' => [
        'cost-tracking' => \JTD\LaravelAI\Middleware\CostTrackingMiddleware::class,
        'budget-enforcement' => \JTD\LaravelAI\Middleware\BudgetEnforcementMiddleware::class,
    ],
],
```

## AI Prompt
```
You are a Laravel AI package development expert. Please read this ticket fully: docs/Planning/Tickets/Budget Cost Tracking/Implementation/1009-implement-cost-tracking-middleware.md, including the title, description, related documentation, files, and tests listed above.

This ticket implements the missing CostTrackingMiddleware that is critical for the budget enforcement system. The middleware must extract token usage from AI responses and calculate costs in real-time.

Based on this ticket:
1. Create a comprehensive task list for implementing the CostTrackingMiddleware
2. Plan token usage extraction for all supported providers (OpenAI, XAI, Gemini)
3. Design integration with PricingService for accurate cost calculation
4. Plan event firing with correct CostCalculated event structure
5. Design error handling that doesn't block AI requests
6. Plan comprehensive testing including E2E tests with real providers
7. Ensure performance meets <10ms overhead requirement

Focus on creating middleware that works reliably with all providers and provides accurate cost tracking for budget enforcement.
```

## Notes
This middleware is essential for budget enforcement functionality. It must be implemented before budget enforcement can work properly. The middleware should be added to global middleware to ensure all AI requests are tracked.

## Estimated Effort
Large (1-2 days)

## Dependencies
- [ ] Ticket 1109 - AIRequest class must be completed first
- [ ] CostCalculated event constructor must match usage pattern
- [ ] PricingService must be functional for all providers
