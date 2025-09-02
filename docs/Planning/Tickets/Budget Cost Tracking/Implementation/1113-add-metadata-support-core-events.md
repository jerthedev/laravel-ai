# Add Metadata Support to Core Events

**Ticket ID**: Implementation/1010-add-metadata-support-core-events  
**Date Created**: 2025-01-26  
**Status**: Not Started  

## Title
Add Metadata Parameter Support to Core Cost Tracking Events

## Description
The BUDGET_COST_TRACKING_SPECIFICATION.md requires all core events to support a metadata parameter for passing additional context data, but current event implementations are missing this capability. This limits extensibility, debugging capabilities, and compliance with the specification.

**Current State**: Core events (CostCalculated, MessageSent, ResponseGenerated) lack metadata parameter support, reducing flexibility for passing execution context, debugging information, and custom data.

**Desired State**: All core events support an optional metadata parameter that allows passing arbitrary context data while maintaining backward compatibility.

**Root Cause**: Events were implemented before the metadata requirement was fully specified, and the specification compliance was not enforced during development.

**Impact**: 
- Reduced debugging capabilities (can't pass execution context)
- Limited extensibility for custom implementations
- Non-compliance with specification requirements
- Difficulty tracking request context across event chain

**Dependencies**: This ticket should be completed after the critical fixes (1007-1009) to avoid conflicts with constructor changes.

## Related Documentation
- [ ] docs/audit-event-system-gap-analysis.md - High Priority Issue #1 details
- [ ] docs/BUDGET_COST_TRACKING_SPECIFICATION.md - Metadata requirements for core events
- [ ] docs/EVENT_SYSTEM.md - Event system architecture documentation

## Related Files
- [ ] src/Events/CostCalculated.php - Missing metadata parameter
- [ ] src/Events/MessageSent.php - Has options but not metadata
- [ ] src/Events/ResponseGenerated.php - Has providerMetadata but not general metadata
- [ ] src/Middleware/CostTrackingMiddleware.php - Should pass metadata when dispatching events
- [ ] src/Listeners/CostTrackingListener.php - Should pass metadata when creating events
- [ ] src/Listeners/AnalyticsListener.php - Should utilize metadata for enhanced analytics

## Related Tests
- [ ] tests/Unit/Events/CostCalculatedTest.php - Test metadata parameter functionality
- [ ] tests/Unit/Events/MessageSentTest.php - Test metadata parameter functionality
- [ ] tests/Unit/Events/ResponseGeneratedTest.php - Test metadata parameter functionality
- [ ] tests/Feature/CostTracking/MetadataTrackingTest.php - Feature tests for metadata flow
- [ ] tests/Integration/EventMiddlewareIntegrationTest.php - Integration tests with metadata

## Acceptance Criteria
- [ ] CostCalculated event supports metadata parameter:
  - [ ] Add optional metadata parameter with default empty array
  - [ ] Maintain backward compatibility with existing constructor calls
  - [ ] Update all CostCalculated instantiations to pass relevant metadata
- [ ] MessageSent event supports metadata parameter:
  - [ ] Add optional metadata parameter (in addition to existing options)
  - [ ] Distinguish between options (request configuration) and metadata (context data)
  - [ ] Update all MessageSent instantiations to pass relevant metadata
- [ ] ResponseGenerated event supports metadata parameter:
  - [ ] Add optional metadata parameter (in addition to existing providerMetadata)
  - [ ] Distinguish between providerMetadata (provider-specific) and metadata (general context)
  - [ ] Update all ResponseGenerated instantiations to pass relevant metadata
- [ ] Metadata usage patterns are established:
  - [ ] Include execution_time in metadata where applicable
  - [ ] Include request_id for tracing across events
  - [ ] Include user context (user_id, session_id) where available
  - [ ] Include performance metrics where applicable
- [ ] All event dispatching locations updated:
  - [ ] CostTrackingMiddleware passes execution context in metadata
  - [ ] CostTrackingListener passes calculation context in metadata
  - [ ] Other middleware and services pass relevant context
- [ ] Backward compatibility maintained:
  - [ ] All existing event instantiations continue to work
  - [ ] Optional parameters have sensible defaults
  - [ ] No breaking changes to existing event interfaces
- [ ] Documentation updated:
  - [ ] Event class docblocks include metadata parameter documentation
  - [ ] Usage examples show metadata best practices
  - [ ] Specification compliance noted in comments
- [ ] All unit tests pass with metadata support
- [ ] All integration tests pass with metadata flow
- [ ] Performance impact is minimal (< 0.5ms additional overhead)

## AI Prompt
```
You are a Laravel AI package development expert specializing in event systems and API design. Please read this ticket fully: docs/Planning/Tickets/Budget Cost Tracking/Implementation/1010-add-metadata-support-core-events.md, including the title, description, related documentation, files, and tests listed above.

TICKET DIRECTORY STRUCTURE:
- Template: docs/Planning/Tickets/template.md
- Major Features: Budget Cost Tracking, Unified Tool System
- Phases: Audit, Implementation, Cleanup, Test Implementation, Test Cleanup
- Format: docs/Planning/Tickets/<Major Feature>/<Phase>/####-short-description.md
- Numbering: 1000s for Budget Cost Tracking, 2000s for Unified Tool System

Based on this ticket:
1. Create a comprehensive task list breaking down all work needed to complete this ticket
2. Plan backward-compatible constructor changes for each event
3. Design metadata usage patterns and best practices
4. Identify all locations where events are instantiated that need updates
5. Consider the relationship between existing parameters (options, providerMetadata) and new metadata
6. Pause and wait for my review before proceeding with implementation

Focus on:
- Maintaining strict backward compatibility
- Establishing consistent metadata usage patterns
- Planning efficient metadata collection and passing
- Ensuring specification compliance
- Designing extensible metadata structure for future enhancements

This ticket enhances the event system's flexibility and specification compliance.
```

## Notes
This ticket focuses on enhancing the event system's capabilities while maintaining backward compatibility. The metadata support will improve debugging, tracing, and extensibility.

**Metadata Design Principles**:
1. Always optional with sensible defaults
2. Structured data (arrays) for flexibility
3. Consistent naming conventions across events
4. Performance-conscious (avoid heavy objects)

**Common Metadata Fields**:
- execution_time: Processing time in milliseconds
- request_id: Unique identifier for request tracing
- user_context: User ID, session ID, etc.
- performance_metrics: Timing, memory usage, etc.
- debug_info: Additional debugging context

## Estimated Effort
Medium (4-5 hours)

## Dependencies
- [ ] Ticket 1007 - Fix BudgetThresholdReached Constructor (should be completed first to avoid conflicts)
- [ ] Ticket 1008 - Fix BudgetAlertListener Compatibility (should be completed first)
- [ ] Ticket 1009 - Register Missing Event Listeners (should be completed first)
