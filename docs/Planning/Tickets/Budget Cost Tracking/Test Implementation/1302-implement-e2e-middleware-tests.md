# Implement E2E Middleware Tests with Real AI Providers

**Ticket ID**: Test Implementation/1032-implement-e2e-middleware-tests  
**Date Created**: 2025-01-26  
**Status**: Not Started  

## Title
Implement End-to-End Middleware Tests with Real AI Providers

## Description
Create comprehensive end-to-end tests that verify middleware functionality works correctly with real AI providers (OpenAI, XAI, Gemini) to ensure the complete middleware pipeline functions properly in production scenarios.

**Current State**: 
- No end-to-end testing of middleware with real AI providers
- Missing validation of complete cost tracking and budget enforcement workflows
- No testing of middleware performance with actual API calls
- Limited confidence in production middleware behavior

**Desired State**:
- Comprehensive E2E tests with all supported AI providers
- Complete workflow testing from request to cost calculation and budget enforcement
- Real API call testing with actual token usage and cost calculation
- Performance validation under real-world conditions
- Confidence in production middleware behavior

**Critical Issues Addressed**:
- Validates middleware works correctly with real AI provider responses
- Ensures cost tracking accurately calculates costs from real token usage
- Verifies budget enforcement works with actual cost data
- Provides confidence in production deployment

**Dependencies**:
- Requires all Implementation phase tickets to be completed
- Requires E2E test credentials for AI providers
- Requires functional middleware system with real provider integration

## Related Documentation
- [ ] docs/BUDGET_COST_TRACKING_SPECIFICATION.md - For E2E testing requirements
- [ ] tests/credentials/e2e-credentials.json - For provider credentials
- [ ] Laravel Testing documentation - For E2E testing patterns

## Related Files
- [ ] tests/E2E/Middleware/MiddlewareE2ETest.php - NEW: Main E2E middleware tests
- [ ] tests/E2E/Middleware/CostTrackingE2ETest.php - NEW: Cost tracking E2E tests
- [ ] tests/E2E/Middleware/BudgetEnforcementE2ETest.php - NEW: Budget enforcement E2E tests
- [ ] tests/E2E/Middleware/MiddlewarePerformanceE2ETest.php - NEW: Performance E2E tests

## Related Tests
- [ ] All E2E test files listed above - NEW: Create comprehensive E2E test suites
- [ ] tests/E2E/E2ETestCase.php - UPDATE: Add middleware E2E testing utilities

## Acceptance Criteria
- [ ] E2E tests work with OpenAI, XAI, and Gemini providers
- [ ] Cost tracking middleware accurately calculates costs from real responses
- [ ] Budget enforcement middleware properly enforces limits with real cost data
- [ ] Complete workflow testing from AI request to event firing
- [ ] Performance testing validates <10ms middleware overhead with real API calls
- [ ] Tests handle real API errors and edge cases gracefully
- [ ] Tests use actual E2E credentials and skip when credentials unavailable
- [ ] Tests verify event firing with real cost and budget data
- [ ] Tests validate middleware pipeline execution order with real providers
- [ ] Tests confirm middleware works with both ConversationBuilder and Direct SendMessage patterns

## Implementation Details

### Main E2E Middleware Test
```php
class MiddlewareE2ETest extends E2ETestCase
{
    use RefreshDatabase;
    
    public function test_complete_middleware_workflow_with_openai()
    {
        $this->skipIfCredentialsMissing('openai');
        
        Event::fake();
        
        // Create user with budget
        $user = User::factory()->create();
        $this->actingAs($user);
        
        // Set budget limits
        $this->setBudgetLimits($user, [
            'daily_limit' => 10.00,
            'monthly_limit' => 100.00,
        ]);
        
        // Make AI request with middleware
        $response = AI::conversation()
            ->middleware(['cost-tracking', 'budget-enforcement'])
            ->send('Hello, this is a test message');
        
        // Verify response received
        $this->assertInstanceOf(AIResponse::class, $response);
        $this->assertNotEmpty($response->getContent());
        
        // Verify cost tracking event fired
        Event::assertDispatched(CostCalculated::class, function ($event) use ($user) {
            return $event->userId === $user->id
                && $event->provider === 'openai'
                && $event->cost > 0
                && $event->inputTokens > 0
                && $event->outputTokens > 0;
        });
        
        // Verify budget was updated
        $this->assertDatabaseHas('ai_usage_logs', [
            'user_id' => $user->id,
            'provider' => 'openai',
        ]);
    }
    
    public function test_budget_enforcement_prevents_overspend_with_real_costs()
    {
        $this->skipIfCredentialsMissing('openai');
        
        $user = User::factory()->create();
        $this->actingAs($user);
        
        // Set very low budget limit
        $this->setBudgetLimits($user, [
            'daily_limit' => 0.01, // Very low limit
        ]);
        
        // Expect budget exception
        $this->expectException(BudgetExceededException::class);
        
        // Make AI request that should exceed budget
        AI::conversation()
            ->middleware(['cost-tracking', 'budget-enforcement'])
            ->send('Generate a very long detailed response that will cost more than 1 cent');
    }
    
    public function test_middleware_performance_with_real_api_calls()
    {
        $this->skipIfCredentialsMissing('openai');
        
        $user = User::factory()->create();
        $this->actingAs($user);
        
        $startTime = microtime(true);
        
        $response = AI::conversation()
            ->middleware(['cost-tracking', 'budget-enforcement'])
            ->send('Hello');
        
        $totalTime = (microtime(true) - $startTime) * 1000;
        
        // Verify response received
        $this->assertInstanceOf(AIResponse::class, $response);
        
        // Note: Total time includes API call, so we can't test <10ms total
        // But we can verify the request completed successfully
        $this->assertLessThan(30000, $totalTime); // 30 second timeout
    }
}
```

### Cost Tracking E2E Tests
```php
class CostTrackingE2ETest extends E2ETestCase
{
    public function test_cost_tracking_with_openai_gpt4()
    {
        $this->skipIfCredentialsMissing('openai');
        
        Event::fake();
        
        $user = User::factory()->create();
        $this->actingAs($user);
        
        $response = AI::provider('openai')
            ->model('gpt-4')
            ->sendMessage('Count to 10', [
                'middleware' => ['cost-tracking']
            ]);
        
        Event::assertDispatched(CostCalculated::class, function ($event) use ($user) {
            return $event->userId === $user->id
                && $event->provider === 'openai'
                && $event->model === 'gpt-4'
                && $event->cost > 0
                && $event->inputTokens > 0
                && $event->outputTokens > 0
                && isset($event->metadata['execution_time']);
        });
    }
    
    public function test_cost_tracking_with_gemini()
    {
        $this->skipIfCredentialsMissing('gemini');
        
        Event::fake();
        
        $user = User::factory()->create();
        $this->actingAs($user);
        
        $response = AI::provider('gemini')
            ->sendMessage('Hello world', [
                'middleware' => ['cost-tracking']
            ]);
        
        Event::assertDispatched(CostCalculated::class, function ($event) use ($user) {
            return $event->provider === 'gemini'
                && $event->cost >= 0; // Gemini might be free for small requests
        });
    }
    
    public function test_cost_accuracy_with_known_token_counts()
    {
        $this->skipIfCredentialsMissing('openai');
        
        Event::fake();
        
        $user = User::factory()->create();
        $this->actingAs($user);
        
        // Use a predictable message for consistent token count
        $message = 'Hello'; // Should be ~1 token
        
        AI::provider('openai')
            ->model('gpt-3.5-turbo')
            ->sendMessage($message, [
                'middleware' => ['cost-tracking']
            ]);
        
        Event::assertDispatched(CostCalculated::class, function ($event) {
            // Verify reasonable token counts for simple message
            return $event->inputTokens >= 1 && $event->inputTokens <= 10
                && $event->outputTokens >= 1 && $event->outputTokens <= 50
                && $event->cost > 0 && $event->cost < 0.01; // Should be very cheap
        });
    }
}
```

### Budget Enforcement E2E Tests
```php
class BudgetEnforcementE2ETest extends E2ETestCase
{
    public function test_budget_threshold_events_with_real_costs()
    {
        $this->skipIfCredentialsMissing('openai');
        
        Event::fake();
        
        $user = User::factory()->create();
        $this->actingAs($user);
        
        // Set budget to trigger 80% threshold
        $this->setBudgetLimits($user, [
            'daily_limit' => 0.10, // 10 cents
        ]);
        
        // Make multiple requests to approach threshold
        for ($i = 0; $i < 3; $i++) {
            AI::conversation()
                ->middleware(['cost-tracking', 'budget-enforcement'])
                ->send('Short message ' . $i);
        }
        
        // Should eventually fire threshold event
        Event::assertDispatched(BudgetThresholdReached::class);
    }
    
    public function test_organization_budget_enforcement()
    {
        $this->skipIfCredentialsMissing('openai');
        
        $organization = Organization::factory()->create([
            'daily_budget_limit' => 0.05, // 5 cents
        ]);
        
        $user = User::factory()->create([
            'organization_id' => $organization->id,
        ]);
        
        $this->actingAs($user);
        
        $this->expectException(BudgetExceededException::class);
        
        // Request that should exceed org budget
        AI::conversation()
            ->middleware(['cost-tracking', 'budget-enforcement'])
            ->send('Generate a detailed analysis of artificial intelligence trends');
    }
}
```

## AI Prompt
```
You are a Laravel AI package development expert. Please read this ticket fully: docs/Planning/Tickets/Budget Cost Tracking/Test Implementation/1032-implement-e2e-middleware-tests.md, including the title, description, related documentation, files, and tests listed above.

This ticket implements comprehensive E2E tests for middleware functionality with real AI providers to ensure production reliability.

Based on this ticket:
1. Create a comprehensive task list for implementing E2E middleware tests
2. Plan testing strategy for all supported AI providers (OpenAI, XAI, Gemini)
3. Design complete workflow testing from request to cost calculation and budget enforcement
4. Plan real API call testing with actual token usage and cost validation
5. Design performance testing under real-world conditions
6. Plan error handling and edge case testing with real providers
7. Ensure tests provide confidence in production middleware behavior

Focus on creating thorough E2E tests that validate the complete middleware system works correctly in production scenarios.
```

## Notes
This ticket is critical for ensuring the middleware system works correctly with real AI providers in production. The E2E tests must validate the complete workflow including cost tracking accuracy and budget enforcement effectiveness.

## Estimated Effort
Large (1-2 days)

## Dependencies
- [ ] All Implementation phase tickets must be completed
- [ ] E2E test credentials must be available for all providers
- [ ] Functional middleware system with real provider integration required
