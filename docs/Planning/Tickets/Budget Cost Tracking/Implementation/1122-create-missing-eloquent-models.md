# Create Missing Eloquent Models

**Ticket ID**: Implementation/1012-create-missing-eloquent-models  
**Date Created**: 2025-01-27  
**Status**: Not Started  

## Title
Create Missing Eloquent Models for Cost Tracking and Budget Management

## Description
The model analysis revealed that 5 critical Eloquent models are missing for core cost tracking and budget management functionality. Services are currently using raw database queries instead of proper ORM relationships, which reduces code maintainability, eliminates validation benefits, and prevents proper relationship management.

**Current State:**
- Services use raw DB queries: `DB::table('ai_usage_costs')`, `DB::table('ai_budgets')`
- No Eloquent models for core cost tracking and budget tables
- Missing relationships between users, budgets, costs, and alerts
- No model-level validation or business logic encapsulation
- Inefficient manual test data creation in test files

**Desired State:**
- Complete Eloquent models for all cost tracking and budget tables
- Proper model relationships (belongsTo, hasMany, etc.)
- Model-level validation and business logic methods
- Consistent ORM usage throughout the application
- Efficient model factories for testing

**Missing Models Identified:**
1. `Budget` - For `ai_budgets` table
2. `BudgetAlert` - For `ai_budget_alerts` table  
3. `CostRecord` - For `ai_usage_costs` table
4. `BudgetAlertConfig` - For `ai_budget_alert_configs` table
5. `CostAnalytics` - For `ai_cost_analytics` table

## Related Documentation
- [ ] docs/BUDGET_COST_TRACKING_SPECIFICATION.md - Model requirements and relationships
- [ ] docs/Audit/DATABASE_SCHEMA_INVENTORY_REPORT.md - Missing models analysis
- [ ] docs/Audit/GAP_ANALYSIS_REPORT.md - GAP-004: Missing Eloquent Models
- [ ] docs/Audit/IMPLEMENTATION_ROADMAP.md - Phase 2 implementation plan
- [ ] docs/project-guidelines.txt - Laravel model conventions
- [ ] Laravel Eloquent Documentation - Model best practices

## Related Files
- [ ] src/Models/Budget.php - Create Budget model with relationships and methods
- [ ] src/Models/BudgetAlert.php - Create BudgetAlert model with relationships
- [ ] src/Models/CostRecord.php - Create CostRecord model with relationships
- [ ] src/Models/BudgetAlertConfig.php - Create BudgetAlertConfig model
- [ ] src/Models/CostAnalytics.php - Create CostAnalytics model
- [ ] src/Services/BudgetService.php - Refactor to use Eloquent models
- [ ] src/Services/CostAnalyticsService.php - Refactor to use Eloquent models
- [ ] src/Services/BudgetAlertService.php - Refactor to use Eloquent models

## Related Tests
- [ ] tests/Unit/Models/BudgetTest.php - Test Budget model functionality
- [ ] tests/Unit/Models/BudgetAlertTest.php - Test BudgetAlert model functionality
- [ ] tests/Unit/Models/CostRecordTest.php - Test CostRecord model functionality
- [ ] tests/Unit/Models/BudgetAlertConfigTest.php - Test BudgetAlertConfig model
- [ ] tests/Unit/Models/CostAnalyticsTest.php - Test CostAnalytics model
- [ ] tests/Feature/Models/ModelRelationshipsTest.php - Test model relationships

## Acceptance Criteria
- [ ] All 5 missing Eloquent models are created with proper structure
- [ ] Models have correct table names, fillable attributes, and casts
- [ ] All model relationships are properly defined (belongsTo, hasMany, etc.)
- [ ] Models include business logic methods (budget utilization, cost calculations, etc.)
- [ ] Models follow Laravel conventions and project coding standards
- [ ] Model factories are created for efficient test data generation
- [ ] Services are refactored to use Eloquent models instead of raw queries
- [ ] All existing functionality continues to work after refactoring
- [ ] Model validation rules are implemented where appropriate
- [ ] Models include proper PHPDoc documentation

## AI Prompt
```
You are a Laravel AI package development expert. Please read this ticket fully: docs/Planning/Tickets/Budget Cost Tracking/Implementation/1012-create-missing-eloquent-models.md, including the title, description, related documentation, files, and tests listed above.

TICKET DIRECTORY STRUCTURE:
- Template: docs/Planning/Tickets/template.md
- Major Features: Budget Cost Tracking, Unified Tool System
- Phases: Audit, Implementation, Cleanup, Test Implementation, Test Cleanup
- Format: docs/Planning/Tickets/<Major Feature>/<Phase>/####-short-description.md
- Numbering: 1000s for Budget Cost Tracking, 2000s for Unified Tool System

Based on this ticket:
1. Create a comprehensive task list breaking down all work needed to complete this ticket
2. Identify any dependencies or prerequisites
3. Suggest the order of execution for maximum efficiency
4. Highlight any potential risks or challenges
5. If this is an AUDIT ticket, plan the creation of subsequent phase tickets using the template
6. Pause and wait for my review before proceeding with implementation

Please be thorough and consider all aspects of Laravel development including code implementation, testing, documentation, and integration.
```

## Notes
- Use existing AIProviderModelCost and TokenUsage models as examples for structure and quality
- Models should include accessor/mutator methods for calculated fields
- Consider implementing model events for cache invalidation
- Ensure models support the hierarchical budget structure

## Estimated Effort
XL (2+ days)

## Dependencies
- [ ] 1010-add-missing-foreign-key-constraints - Foreign keys must exist for relationships
- [ ] 1011-create-missing-database-tables - All tables must exist before creating models
