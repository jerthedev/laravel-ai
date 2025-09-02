# Standardize Event Property Naming Convention

**Ticket ID**: Cleanup/1020-standardize-event-property-naming  
**Date Created**: 2025-01-26  
**Status**: Not Started  

## Title
Standardize Property Naming Convention Across All Events

## Description
Event properties use inconsistent naming conventions (camelCase vs snake_case) which creates confusion and potential integration issues. The BUDGET_COST_TRACKING_SPECIFICATION.md uses snake_case naming, but current implementation uses camelCase, creating a mismatch that affects API consistency and developer experience.

**Current State**: Mixed naming conventions across events - some use camelCase (inputTokens, outputTokens, currentSpending) while specification expects snake_case (input_tokens, output_tokens, current_spend).

**Desired State**: Consistent naming convention across all events that aligns with the specification and Laravel conventions.

**Root Cause**: Events were developed at different times without a unified naming standard, and specification compliance was not enforced.

**Impact**: 
- Developer confusion when working with events
- Potential integration issues with external systems
- Non-compliance with specification requirements
- Inconsistent API experience

**Dependencies**: This ticket should be completed after all critical implementation fixes (1107-1116) to avoid conflicts during the transition.

## Related Documentation
- [ ] docs/audit-event-system-gap-analysis.md - High Priority Issue #2 details
- [ ] docs/BUDGET_COST_TRACKING_SPECIFICATION.md - Property naming requirements
- [ ] Laravel coding standards documentation

## Related Files
- [ ] src/Events/CostCalculated.php - Properties: inputTokens, outputTokens (should be input_tokens, output_tokens)
- [ ] src/Events/BudgetThresholdReached.php - Properties: currentSpending, budgetLimit (should be current_spending, budget_limit)
- [ ] src/Events/ResponseGenerated.php - Properties: totalProcessingTime, providerMetadata (should be total_processing_time, provider_metadata)
- [ ] All listeners that access these properties - Need updates for new property names
- [ ] All middleware and services that create events - Need updates for new property names
- [ ] Documentation and examples - Need updates for new property names

## Related Tests
- [ ] All event-related unit tests - Need updates for new property names
- [ ] All integration tests that access event properties - Need updates
- [ ] All E2E tests that verify event data - Need updates
- [ ] Property access tests to ensure backward compatibility during transition

## Acceptance Criteria
- [ ] Consistent naming convention chosen and documented:
  - [ ] Decision made: camelCase vs snake_case (recommend snake_case for spec compliance)
  - [ ] Naming convention documented in project guidelines
  - [ ] Rationale for choice documented
- [ ] All event properties use consistent naming:
  - [ ] CostCalculated: input_tokens, output_tokens (from inputTokens, outputTokens)
  - [ ] BudgetThresholdReached: current_spending, budget_limit, threshold_percentage (from currentSpending, budgetLimit, percentage)
  - [ ] ResponseGenerated: total_processing_time, provider_metadata (from totalProcessingTime, providerMetadata)
  - [ ] All other events reviewed and updated for consistency
- [ ] Backward compatibility maintained during transition:
  - [ ] Consider using property accessors/mutators for old names
  - [ ] Or implement transition period with deprecation warnings
  - [ ] Or coordinate breaking change with major version bump
- [ ] All property access updated:
  - [ ] All listeners updated to use new property names
  - [ ] All middleware updated to use new property names
  - [ ] All services updated to use new property names
  - [ ] All tests updated to use new property names
- [ ] Documentation updated:
  - [ ] All code examples use new property names
  - [ ] API documentation reflects new naming
  - [ ] Migration guide created if breaking changes
- [ ] Code quality improvements:
  - [ ] Property names are descriptive and clear
  - [ ] Naming follows Laravel/PHP conventions
  - [ ] Consistency maintained across all events
- [ ] All tests pass with new property names
- [ ] No performance impact from naming changes
- [ ] Specification compliance achieved

## AI Prompt
```
You are a Laravel AI package development expert specializing in API design and code organization. Please read this ticket fully: docs/Planning/Tickets/Budget Cost Tracking/Cleanup/1020-standardize-event-property-naming.md, including the title, description, related documentation, files, and tests listed above.

TICKET DIRECTORY STRUCTURE:
- Template: docs/Planning/Tickets/template.md
- Major Features: Budget Cost Tracking, Unified Tool System
- Phases: Audit, Implementation, Cleanup, Test Implementation, Test Cleanup
- Format: docs/Planning/Tickets/<Major Feature>/<Phase>/####-short-description.md
- Numbering: 1000s for Budget Cost Tracking, 2000s for Unified Tool System

Based on this ticket:
1. Create a comprehensive task list breaking down all work needed to complete this ticket
2. Recommend a naming convention (camelCase vs snake_case) with rationale
3. Plan the transition strategy to minimize breaking changes
4. Identify all locations that need updates for new property names
5. Design backward compatibility approach if needed
6. Pause and wait for my review before proceeding with implementation

Focus on:
- Analyzing current property naming inconsistencies
- Choosing the best naming convention for the project
- Planning a smooth transition with minimal disruption
- Ensuring specification compliance
- Maintaining code quality and developer experience

This is a cleanup ticket that improves code consistency and specification compliance.
```

## Notes
This ticket focuses on improving code consistency and developer experience by standardizing property naming. The choice of naming convention should consider:

1. **Specification Compliance**: BUDGET_COST_TRACKING_SPECIFICATION.md uses snake_case
2. **Laravel Conventions**: Laravel typically uses snake_case for database columns, camelCase for properties
3. **Developer Experience**: Consistency is more important than the specific choice
4. **Backward Compatibility**: Minimize breaking changes

**Recommended Approach**: Use snake_case to match specification, implement transition strategy for backward compatibility.

## Estimated Effort
Medium (4-6 hours)

## Dependencies
- [ ] Ticket 1107 - Fix BudgetThresholdReached Constructor (should be completed first)
- [ ] Ticket 1108 - Fix BudgetAlertListener Compatibility (should be completed first)
- [ ] Ticket 1110 - Register Missing Event Listeners (should be completed first)
- [ ] Ticket 1113 - Add Metadata Support to Core Events (should be completed first)
