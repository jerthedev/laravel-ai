# 1022 - Create Missing Eloquent Models

**Phase**: Cleanup  
**Priority**: P2 - MEDIUM  
**Effort**: Medium (2 days)  
**Status**: Ready for Implementation  

## Title
Create missing Eloquent models for budget and cost tracking tables to replace raw database queries with proper ORM relationships.

## Description

### Problem Statement
The system currently uses raw database queries for budget and cost data operations instead of Eloquent models. This leads to:
- No data validation or relationships
- Difficult maintenance and extension
- No proper model events or observers
- Inconsistent data access patterns

### Missing Models Identified
1. **Budget** - For `ai_budgets` table
2. **BudgetAlert** - For `ai_budget_alerts` table  
3. **CostRecord** - For `ai_usage_costs` table
4. **ProjectBudget** - For `ai_project_budgets` table (when created)
5. **OrganizationBudget** - For `ai_organization_budgets` table (when created)

### Solution Approach
Create comprehensive Eloquent models with proper relationships, validation, accessors/mutators, and integrate them into existing services.

## Related Files

### Files to Create
- `src/Models/Budget.php`
- `src/Models/BudgetAlert.php`
- `src/Models/CostRecord.php`
- `src/Models/ProjectBudget.php`
- `src/Models/OrganizationBudget.php`

### Files to Update
- `src/Services/BudgetService.php` (replace raw queries)
- `src/Services/CostAnalyticsService.php` (use models)
- `src/Listeners/CostTrackingListener.php` (use CostRecord model)

## Implementation Details

### Budget Model
```php
<?php

namespace JerTheDev\LaravelAI\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Budget extends Model
{
    protected $table = 'ai_budgets';
    
    protected $fillable = [
        'user_id', 'type', 'limit_amount', 'current_usage', 'is_active'
    ];
    
    protected $casts = [
        'limit_amount' => 'decimal:2',
        'current_usage' => 'decimal:2',
        'is_active' => 'boolean',
    ];
    
    public function alerts(): HasMany
    {
        return $this->hasMany(BudgetAlert::class, 'user_id', 'user_id');
    }
    
    public function costRecords(): HasMany
    {
        return $this->hasMany(CostRecord::class, 'user_id', 'user_id');
    }
    
    public function getUtilizationPercentageAttribute(): float
    {
        return $this->limit_amount > 0 
            ? ($this->current_usage / $this->limit_amount) * 100 
            : 0;
    }
}
```

### CostRecord Model
```php
<?php

namespace JerTheDev\LaravelAI\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CostRecord extends Model
{
    protected $table = 'ai_usage_costs';
    
    protected $fillable = [
        'user_id', 'provider', 'model', 'input_tokens', 'output_tokens', 
        'total_tokens', 'input_cost', 'output_cost', 'total_cost', 
        'currency', 'metadata'
    ];
    
    protected $casts = [
        'input_cost' => 'decimal:6',
        'output_cost' => 'decimal:6', 
        'total_cost' => 'decimal:6',
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];
    
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

## Acceptance Criteria

### Functional Requirements
- [ ] All models created with proper table mappings
- [ ] Relationships defined between models
- [ ] Validation rules implemented
- [ ] Accessors/mutators for calculated fields
- [ ] Services updated to use models instead of raw queries

### Technical Requirements
- [ ] Models follow Laravel conventions
- [ ] Proper fillable/guarded properties
- [ ] Type casting for decimal/date fields
- [ ] Model events and observers where needed
- [ ] Performance optimized with eager loading

## Testing Strategy

### Unit Tests
1. **Test model creation and validation**
2. **Test relationships work correctly**
3. **Test accessors/mutators**
4. **Test model events**

### Integration Tests
1. **Test service integration with models**
2. **Test query performance with models**
3. **Test data consistency**

## Implementation Plan

### Day 1: Core Models
- Create Budget, BudgetAlert, CostRecord models
- Add relationships and validation
- Write unit tests

### Day 2: Integration and Optimization
- Update services to use models
- Add ProjectBudget and OrganizationBudget models
- Performance testing and optimization

## Definition of Done

### Code Complete
- [ ] All models created and tested
- [ ] Services updated to use models
- [ ] Proper relationships implemented
- [ ] Validation rules added

### Testing Complete
- [ ] Unit tests for all models
- [ ] Integration tests with services
- [ ] Performance tests show no degradation

---

## AI Prompt
```
You are implementing ticket 1203-create-missing-eloquent-models.md.

**Context**: System uses raw database queries instead of Eloquent models, making maintenance difficult.

**Task**: Create comprehensive Eloquent models for budget and cost tracking with proper relationships.

**Instructions**:
1. Create comprehensive task list
2. Pause for user review  
3. Implement after approval
4. Ensure proper relationships and validation
5. Update services to use models
6. Test thoroughly

Important: Backward compatibility is not necessary since this package has not yet been released. We want consistent patterns throughout the project.

**Critical**: This improves code maintainability and enables proper ORM relationships for the budget system.
```