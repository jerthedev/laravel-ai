# Create AIRequest Class and Update Middleware Interface

**Ticket ID**: Implementation/1008-create-airequest-class-and-update-interface  
**Date Created**: 2025-01-26  
**Status**: Not Started  

## Title
Create AIRequest Class and Update AIMiddlewareInterface to Match Specification

## Description
Create the missing AIRequest class and update the AIMiddlewareInterface to use AIRequest instead of AIMessage, bringing the middleware system into compliance with the BUDGET_COST_TRACKING_SPECIFICATION.md requirements.

**Current State**: 
- AIMiddlewareInterface uses `AIMessage` instead of `AIRequest`
- AIRequest class does not exist
- All middleware implementations use wrong interface pattern
- Specification expects AIRequest/AIResponse pattern for middleware operations

**Desired State**:
- AIRequest class exists with required methods (getProvider(), getModel(), getUserId(), getId())
- AIMiddlewareInterface updated to use AIRequest/AIResponse pattern
- All existing middleware updated to use new interface
- Backward compatibility maintained where possible

**Critical Issues Addressed**:
- Fixes fundamental architecture mismatch between specification and implementation
- Enables proper middleware functionality as specified
- Provides foundation for cost tracking and budget enforcement middleware

**Dependencies**:
- Must be completed before any other middleware implementation tickets
- Affects all existing middleware classes (BudgetEnforcementMiddleware, etc.)

## Related Documentation
- [ ] docs/BUDGET_COST_TRACKING_SPECIFICATION.md - Defines AIRequest/AIResponse pattern requirements
- [ ] docs/BUDGET_IMPLEMENTATION_ISSUES.md - Documents current interface mismatch issues
- [ ] Laravel Middleware documentation - For pattern reference

## Related Files
- [ ] src/Models/AIRequest.php - NEW: Create AIRequest class with required methods
- [ ] src/Contracts/AIMiddlewareInterface.php - UPDATE: Change method signature to use AIRequest
- [ ] src/Middleware/BudgetEnforcementMiddleware.php - UPDATE: Use new interface
- [ ] src/Services/MiddlewareManager.php - UPDATE: Handle AIRequest objects
- [ ] src/Services/ConversationBuilder.php - UPDATE: Create AIRequest from AIMessage

## Related Tests
- [ ] tests/Unit/Models/AIRequestTest.php - NEW: Unit tests for AIRequest class
- [ ] tests/Unit/Contracts/AIMiddlewareInterfaceTest.php - UPDATE: Test new interface
- [ ] tests/Unit/Middleware/BudgetEnforcementMiddlewareTest.php - UPDATE: Use AIRequest in tests
- [ ] tests/Integration/MiddlewarePipelineTest.php - UPDATE: Test AIRequest flow

## Acceptance Criteria
- [ ] AIRequest class created with all required methods from specification
- [ ] AIRequest provides getProvider(), getModel(), getUserId(), getId() methods
- [ ] AIRequest can be created from AIMessage for backward compatibility
- [ ] AIMiddlewareInterface updated to use AIRequest instead of AIMessage
- [ ] BudgetEnforcementMiddleware updated to use new interface without breaking functionality
- [ ] MiddlewareManager updated to handle AIRequest objects
- [ ] ConversationBuilder creates AIRequest objects for middleware processing
- [ ] All existing tests pass with updated interface
- [ ] New unit tests provide 100% coverage for AIRequest class
- [ ] Integration tests verify AIRequest flows through middleware pipeline correctly
- [ ] Documentation updated to reflect new interface pattern
- [ ] No breaking changes to public API (ConversationBuilder, AI Facade)

## Implementation Details

### AIRequest Class Structure
```php
class AIRequest
{
    protected AIMessage $message;
    protected string $provider;
    protected string $model;
    protected int $userId;
    protected string $id;
    protected array $metadata;
    
    public function getProvider(): string;
    public function getModel(): string;
    public function getUserId(): int;
    public function getId(): string;
    public function getMessage(): AIMessage;
    public function getMetadata(): array;
    
    public static function fromMessage(AIMessage $message, array $context = []): self;
}
```

### Interface Update
```php
interface AIMiddlewareInterface
{
    public function handle(AIRequest $request, Closure $next): AIResponse;
}
```

### Migration Strategy
1. Create AIRequest class with backward compatibility
2. Update interface but maintain AIMessage support temporarily
3. Update all middleware to use AIRequest
4. Update MiddlewareManager to convert AIMessage to AIRequest
5. Remove AIMessage support from interface once all middleware updated

## AI Prompt
```
You are a Laravel AI package development expert. Please read this ticket fully: docs/Planning/Tickets/Budget Cost Tracking/Implementation/1008-create-airequest-class-and-update-interface.md, including the title, description, related documentation, files, and tests listed above.

This ticket addresses the fundamental architecture mismatch between the specification and current implementation. The specification requires AIRequest/AIResponse pattern but the current implementation uses AIMessage/AIResponse.

Based on this ticket:
1. Create a comprehensive task list for implementing the AIRequest class and updating the interface
2. Plan the migration strategy to avoid breaking existing functionality
3. Design the AIRequest class to provide all methods required by the specification
4. Plan how to maintain backward compatibility during the transition
5. Identify integration points that need updates (MiddlewareManager, ConversationBuilder)
6. Plan comprehensive testing strategy for the new class and interface

Focus on creating a robust foundation that enables all other middleware functionality while maintaining system stability.
```

## Notes
This is the highest priority ticket as it addresses the fundamental architecture issue that prevents proper middleware functionality. All other middleware tickets depend on this being completed first.

## Estimated Effort
Large (1-2 days)

## Dependencies
- [ ] None - this is the foundation ticket that other middleware tickets depend on
