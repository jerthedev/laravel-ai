# Fix Middleware Configuration Structure

**Ticket ID**: Implementation/1012-fix-middleware-configuration-structure  
**Date Created**: 2025-01-26  
**Status**: Not Started  

## Title
Fix Middleware Configuration Structure to Match Specification

## Description
Update the middleware configuration structure in config/ai.php to match the BUDGET_COST_TRACKING_SPECIFICATION.md requirements, fixing the current mismatch between configuration objects and middleware name arrays.

**Current State**: 
- Global middleware array contains configuration objects instead of middleware names
- Missing 'cost-tracking' middleware from both global and available arrays
- Available array only contains 'budget_enforcement', missing other required middleware
- Configuration structure doesn't match specification requirements

**Desired State**:
- Global middleware array contains middleware names as strings
- Available middleware array maps names to class references
- All required middleware included (cost-tracking, budget-enforcement, rate-limiting, audit-logging)
- Configuration structure matches specification exactly
- Backward compatibility maintained where possible

**Critical Issues Addressed**:
- Fixes configuration structure mismatch preventing proper middleware loading
- Adds missing middleware to configuration arrays
- Enables proper middleware resolution and execution
- Provides foundation for middleware system functionality

**Dependencies**:
- Requires CostTrackingMiddleware to be implemented (ticket 1111)
- Requires other middleware classes to exist or be stubbed
- May require MiddlewareManager updates to handle new configuration

## Related Documentation
- [ ] docs/BUDGET_COST_TRACKING_SPECIFICATION.md - Defines correct configuration structure
- [ ] Laravel Configuration documentation - For config file patterns
- [ ] Laravel Middleware documentation - For middleware registration patterns

## Related Files
- [ ] config/ai.php - UPDATE: Fix middleware configuration structure
- [ ] src/Services/MiddlewareManager.php - VERIFY: Can handle new configuration format
- [ ] src/Providers/LaravelAIServiceProvider.php - VERIFY: Middleware registration works

## Related Tests
- [ ] tests/Unit/Config/MiddlewareConfigTest.php - NEW: Test configuration structure
- [ ] tests/Integration/MiddlewareResolutionTest.php - NEW: Test middleware resolution
- [ ] tests/Feature/MiddlewareConfigurationTest.php - UPDATE: Test new configuration

## Acceptance Criteria
- [ ] Global middleware array contains middleware names as strings
- [ ] Available middleware array maps names to fully qualified class names
- [ ] 'cost-tracking' middleware added to both global and available arrays
- [ ] 'budget-enforcement' middleware properly configured in both arrays
- [ ] Additional middleware (rate-limiting, audit-logging) added to available array
- [ ] Configuration structure matches specification exactly
- [ ] MiddlewareManager can resolve middleware from new configuration
- [ ] Backward compatibility maintained for existing middleware usage
- [ ] Configuration validation prevents invalid middleware names
- [ ] Unit tests verify configuration structure is correct
- [ ] Integration tests verify middleware resolution works
- [ ] Feature tests verify middleware execution with new configuration
- [ ] Documentation updated with correct configuration examples

## Implementation Details

### Current Configuration (Broken)
```php
'middleware' => [
    'enabled' => env('AI_MIDDLEWARE_ENABLED', true),
    'global' => [
        'budget_enforcement' => [
            'enabled' => true,
            'daily_limit' => 100.00,
            'monthly_limit' => 1000.00,
            'per_request_limit' => 10.00,
        ],
    ],
    'available' => [
        'budget_enforcement' => \JTD\LaravelAI\Middleware\BudgetEnforcementMiddleware::class,
    ],
],
```

### Fixed Configuration (Specification Compliant)
```php
'middleware' => [
    'enabled' => env('AI_MIDDLEWARE_ENABLED', true),
    
    // Global middleware - executed for all requests
    'global' => [
        'cost-tracking',
        'budget-enforcement',
    ],
    
    // Available middleware - can be used optionally
    'available' => [
        'cost-tracking' => \JTD\LaravelAI\Middleware\CostTrackingMiddleware::class,
        'budget-enforcement' => \JTD\LaravelAI\Middleware\BudgetEnforcementMiddleware::class,
        'rate-limiting' => \JTD\LaravelAI\Middleware\RateLimitingMiddleware::class,
        'audit-logging' => \JTD\LaravelAI\Middleware\AuditLoggingMiddleware::class,
    ],
    
    // Middleware-specific configuration moved to separate section
    'budget_enforcement' => [
        'daily_limit' => env('AI_DAILY_BUDGET_LIMIT', 100.00),
        'monthly_limit' => env('AI_MONTHLY_BUDGET_LIMIT', 1000.00),
        'per_request_limit' => env('AI_PER_REQUEST_LIMIT', 10.00),
    ],
    
    'rate_limiting' => [
        'requests_per_minute' => env('AI_RATE_LIMIT_RPM', 60),
        'requests_per_hour' => env('AI_RATE_LIMIT_RPH', 1000),
    ],
    
    'audit_logging' => [
        'enabled' => env('AI_AUDIT_LOGGING_ENABLED', true),
        'log_requests' => true,
        'log_responses' => true,
    ],
],
```

### MiddlewareManager Updates (if needed)
```php
protected function resolveMiddleware(string $middleware): AIMiddlewareInterface
{
    $available = config('ai.middleware.available', []);
    
    if (!isset($available[$middleware])) {
        throw new InvalidArgumentException("Middleware '{$middleware}' not found in available middleware.");
    }
    
    $class = $available[$middleware];
    
    if (!class_exists($class)) {
        throw new InvalidArgumentException("Middleware class '{$class}' does not exist.");
    }
    
    return app($class);
}
```

### Migration Strategy
1. Update configuration structure to match specification
2. Move middleware-specific configuration to separate sections
3. Add missing middleware to available array (stub classes if needed)
4. Update MiddlewareManager to handle new configuration format
5. Add configuration validation
6. Update tests to use new configuration structure

## AI Prompt
```
You are a Laravel AI package development expert. Please read this ticket fully: docs/Planning/Tickets/Budget Cost Tracking/Implementation/1012-fix-middleware-configuration-structure.md, including the title, description, related documentation, files, and tests listed above.

This ticket fixes the middleware configuration structure to match the specification requirements and enable proper middleware functionality.

Based on this ticket:
1. Create a comprehensive task list for updating the configuration structure
2. Plan the migration from configuration objects to middleware name arrays
3. Design the separation of middleware-specific configuration into dedicated sections
4. Plan the addition of missing middleware to the configuration
5. Design configuration validation to prevent invalid middleware names
6. Plan comprehensive testing of the new configuration structure
7. Ensure backward compatibility where possible

Focus on creating a clean, specification-compliant configuration that enables all middleware functionality.
```

## Notes
This ticket addresses a fundamental configuration issue that prevents proper middleware loading and execution. The configuration structure must match the specification exactly to enable the middleware system to function correctly.

## Estimated Effort
Small (0.5 days)

## Dependencies
- [ ] CostTrackingMiddleware class must exist (ticket 1111) or be stubbed
- [ ] Other middleware classes may need to be stubbed if they don't exist
- [ ] MiddlewareManager may need updates to handle new configuration format
