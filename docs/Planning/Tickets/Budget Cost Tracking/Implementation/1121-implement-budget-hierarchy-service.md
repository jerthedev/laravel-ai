# 1012 - Implement BudgetHierarchyService

**Phase**: Implementation  
**Priority**: P1 - HIGH  
**Effort**: High (3 days)  
**Status**: Ready for Implementation  

## Title
Implement BudgetHierarchyService to provide multi-level budget hierarchy support for user/project/organization budget management.

## Description

### Problem Statement
The budget system currently only supports basic user-level budgets with no hierarchy or inheritance. The system needs multi-level budget support where organization budgets cascade to projects, and project budgets cascade to users, with proper aggregation and utilization tracking across all levels.

### Business Requirements
- **Organization Level**: Set overall spending limits for entire organization
- **Project Level**: Set spending limits for specific projects within organization limits
- **User Level**: Set individual user limits within project/organization limits
- **Budget Inheritance**: Lower levels inherit limits from higher levels
- **Aggregated Spending**: Track spending across all levels with proper aggregation
- **Utilization Tracking**: Monitor budget utilization at each hierarchy level

### Solution Approach
Create a comprehensive BudgetHierarchyService that manages multi-level budget relationships, aggregated spending calculations, and budget utilization tracking with proper caching for performance.

## Related Files

### Files to Create
- `src/Services/BudgetHierarchyService.php` (new service)
- `tests/Unit/Services/BudgetHierarchyServiceTest.php` (unit tests)
- `tests/Feature/BudgetHierarchyTest.php` (feature tests)

### Files to Review
- `src/Services/BudgetService.php` (existing budget logic)
- `database/migrations/*_create_ai_project_budgets_table.php` (may need creation)
- `database/migrations/*_create_ai_organization_budgets_table.php` (may need creation)

### Related Tests
- `tests/E2E/BudgetHierarchyE2ETest.php` (to be created)
- `tests/Feature/BudgetEnforcementTest.php` (integration testing)

## Implementation Details

### Service Class Structure
```php
<?php

namespace JerTheDev\LaravelAI\Services;

class BudgetHierarchyService
{
    public function __construct(
        private BudgetService $budgetService,
        private CostCalculationService $costCalculationService,
        private CacheManager $cache
    ) {}

    // Core hierarchy methods
    public function getAggregatedSpending($id, string $type, string $level = 'user'): float;
    public function getBudgetUtilization($id, string $type, string $level = 'user'): float;
    public function getEffectiveBudgetLimit($id, string $type, string $level = 'user'): float;
    public function getBudgetHierarchy($id, string $level = 'user'): array;
    
    // Validation and enforcement
    public function validateBudgetHierarchy($id, string $level, float $proposedLimit): bool;
    public function checkHierarchyCompliance($id, string $level, float $additionalCost): array;
    
    // Utility methods
    public function getParentBudgets($id, string $level): array;
    public function getChildBudgets($id, string $level): array;
    public function refreshHierarchyCache($id, string $level): void;
}
```

### Core Method Implementations

#### 1. getAggregatedSpending()
**Purpose**: Calculate total spending across hierarchy levels
**Parameters**:
- `$id`: Entity ID (user ID, project ID, or organization ID)
- `$type`: Budget type ('daily', 'monthly', 'per_request')
- `$level`: Hierarchy level ('user', 'project', 'organization')

**Logic**:
- For user level: Return user's direct spending
- For project level: Aggregate spending from all users in project
- For organization level: Aggregate spending from all projects in organization
- Include proper date filtering based on budget type
- Implement caching for performance

#### 2. getBudgetUtilization()
**Purpose**: Calculate budget utilization percentage at specified level
**Returns**: Float (0-100) representing percentage of budget used

**Logic**:
- Get aggregated spending for the level
- Get effective budget limit for the level
- Calculate utilization percentage: (spending / limit) * 100
- Handle edge cases (no limit, zero limit, etc.)

#### 3. getEffectiveBudgetLimit()
**Purpose**: Get the effective budget limit considering hierarchy inheritance
**Logic**:
- Start with entity's direct budget limit
- Check parent level limits and apply inheritance rules
- Return the most restrictive limit in the hierarchy
- Cache results for performance

### Database Integration

#### Required Tables
1. **ai_project_budgets** (if not exists)
   - project_id, organization_id, daily_limit, monthly_limit, per_request_limit
   - is_active, created_at, updated_at

2. **ai_organization_budgets** (if not exists)
   - organization_id, daily_limit, monthly_limit, per_request_limit
   - is_active, created_at, updated_at

#### Query Optimization
- Use proper indexes for hierarchy queries
- Implement query caching for expensive aggregations
- Use database views for complex hierarchy calculations

### Caching Strategy
- **Cache Keys**: `budget_hierarchy:{level}:{id}:{type}`
- **TTL**: 5 minutes for spending data, 1 hour for budget limits
- **Cache Invalidation**: When budgets change or spending occurs
- **Cache Warming**: Pre-calculate common hierarchy queries

## Acceptance Criteria

### Functional Requirements
- [ ] Multi-level budget hierarchy works (organization → project → user)
- [ ] Aggregated spending calculated correctly across all levels
- [ ] Budget utilization tracking accurate for each level
- [ ] Budget inheritance rules enforced properly
- [ ] Effective budget limits consider hierarchy constraints
- [ ] Budget validation prevents hierarchy violations

### Technical Requirements
- [ ] Service follows Laravel service patterns
- [ ] Proper dependency injection and testability
- [ ] Performance optimized with caching (<200ms for hierarchy queries)
- [ ] Error handling for missing budgets or invalid hierarchy
- [ ] Logging for audit trail and debugging
- [ ] Thread-safe for concurrent operations

### Data Integrity Requirements
- [ ] Spending aggregations are mathematically correct
- [ ] Budget utilization percentages accurate
- [ ] Hierarchy relationships maintained consistently
- [ ] Cache invalidation prevents stale data
- [ ] Database transactions ensure consistency

## Testing Strategy

### Unit Tests
1. **Test getAggregatedSpending()**
   - Test user-level spending calculation
   - Test project-level aggregation
   - Test organization-level aggregation
   - Test date filtering for different budget types
   - Test caching behavior

2. **Test getBudgetUtilization()**
   - Test utilization calculation with various spending levels
   - Test edge cases (no budget, zero budget, over budget)
   - Test different hierarchy levels

3. **Test getEffectiveBudgetLimit()**
   - Test budget inheritance from parent levels
   - Test most restrictive limit selection
   - Test missing parent budget handling

### Integration Tests
1. **Test Database Integration**
   - Test with real database queries
   - Test aggregation accuracy with sample data
   - Test performance with large datasets

2. **Test Service Integration**
   - Test integration with BudgetService
   - Test integration with CostCalculationService
   - Test cache integration

### Feature Tests
1. **Test Complete Hierarchy Scenarios**
   - Create organization with projects and users
   - Set budgets at all levels
   - Generate spending and verify aggregations
   - Test budget enforcement across levels

### Performance Tests
1. **Test Query Performance**
   - Test hierarchy queries with large datasets
   - Verify caching improves performance
   - Test concurrent access scenarios

## Implementation Plan

### Phase 1: Core Service Structure (Day 1)
- Create BudgetHierarchyService class
- Implement basic method signatures
- Set up dependency injection
- Create unit test structure

### Phase 2: Core Method Implementation (Day 2)
- Implement getAggregatedSpending() method
- Implement getBudgetUtilization() method
- Implement getEffectiveBudgetLimit() method
- Add basic error handling and logging

### Phase 3: Advanced Features (Day 3)
- Implement budget validation methods
- Add caching layer with proper invalidation
- Implement hierarchy compliance checking
- Add utility methods for parent/child relationships

### Phase 4: Testing and Optimization (Day 3)
- Write comprehensive unit tests
- Create integration tests
- Performance testing and optimization
- Documentation and code cleanup

## Risk Assessment

### High Risk
- **Complex aggregation logic**: Multi-level spending calculations
- **Performance impact**: Hierarchy queries can be expensive
- **Data consistency**: Ensuring accurate aggregations across levels

### Medium Risk
- **Cache invalidation**: Ensuring cache stays synchronized with data
- **Database schema**: May need new tables for project/organization budgets

### Mitigation Strategies
1. **Incremental implementation**: Build and test one method at a time
2. **Performance monitoring**: Track query execution times
3. **Comprehensive testing**: Test with realistic data volumes
4. **Cache warming**: Pre-calculate common queries
5. **Database optimization**: Proper indexes and query optimization

## Dependencies

### Prerequisites
- BudgetService must be functional
- Database tables for project and organization budgets (may need creation)
- Cache system configured and working
- CostCalculationService available for spending calculations

### Potential Blockers
- Missing database tables for project/organization budgets
- Performance issues with large-scale aggregation queries
- Complex business rules for budget inheritance

## Definition of Done

### Code Complete
- [ ] BudgetHierarchyService fully implemented with all required methods
- [ ] Proper error handling and logging throughout
- [ ] Caching implemented for performance optimization
- [ ] Database integration working correctly
- [ ] Service registered in Laravel service container

### Testing Complete
- [ ] Unit tests written and passing (>90% coverage)
- [ ] Integration tests verify database operations
- [ ] Feature tests demonstrate complete hierarchy functionality
- [ ] Performance tests show acceptable query times (<200ms)

### Documentation Complete
- [ ] Service methods documented with PHPDoc
- [ ] Code comments explain complex hierarchy logic
- [ ] Usage examples provided
- [ ] Integration guide for other services

### Deployment Ready
- [ ] Service tested in staging environment
- [ ] Performance validated with realistic data
- [ ] Database migrations ready (if needed)
- [ ] Monitoring configured for hierarchy operations

---

## AI Prompt

You are implementing ticket 1012-implement-budget-hierarchy-service.md located at docs/Planning/Tickets/Budget Cost Tracking/Implementation/1012-implement-budget-hierarchy-service.md.

**Context**: The budget system needs multi-level hierarchy support (organization → project → user) with proper aggregation, inheritance, and utilization tracking.

**Task**: Create a comprehensive BudgetHierarchyService that manages multi-level budget relationships and calculations.

**Instructions**:
1. First, create a comprehensive task list covering all aspects of this ticket
2. Pause for user review and approval of the task list
3. Only proceed with implementation after user confirms the approach
4. Implement all required methods as specified in the ticket
5. Ensure performance targets (<200ms) are met with proper caching
6. Test thoroughly with unit, integration, and feature tests

**Critical**: This is a P1 issue required for complete budget hierarchy functionality. The service must handle complex aggregations and inheritance rules correctly.
