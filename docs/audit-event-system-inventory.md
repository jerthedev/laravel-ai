# Event System Foundation Audit - Inventory Report

**Date**: 2025-01-26  
**Audit Phase**: Phase 1 - Current Event System Inventory  
**Status**: COMPLETE  

## Executive Summary

This report documents the complete inventory of existing events compared against the BUDGET_COST_TRACKING_SPECIFICATION.md requirements. Critical mismatches have been identified that prevent the event system from functioning correctly.

## Existing Events Inventory

### Core Cost Tracking Events (CRITICAL)

#### ✅ MessageSent
- **File**: `src/Events/MessageSent.php`
- **Constructor**: `(AIMessage $message, string $provider, string $model, array $options = [], ?string $conversationId = null, $userId = null)`
- **Properties**: `message`, `provider`, `model`, `options`, `conversationId`, `userId`
- **Status**: ✅ EXISTS - Compatible with specification

#### ✅ ResponseGenerated  
- **File**: `src/Events/ResponseGenerated.php`
- **Constructor**: `(AIMessage $message, AIResponse $response, array $context = [], float $totalProcessingTime = 0, array $providerMetadata = [])`
- **Properties**: `message`, `response`, `context`, `totalProcessingTime`, `providerMetadata`
- **Status**: ⚠️ EXISTS - Missing required properties from specification

#### ✅ CostCalculated
- **File**: `src/Events/CostCalculated.php`
- **Constructor**: `(int $userId, string $provider, string $model, float $cost, int $inputTokens, int $outputTokens, ?int $conversationId = null, ?int $messageId = null)`
- **Properties**: `userId`, `provider`, `model`, `cost`, `inputTokens`, `outputTokens`, `conversationId`, `messageId`
- **Status**: ⚠️ EXISTS - Missing metadata property from specification

#### 🔴 BudgetThresholdReached
- **File**: `src/Events/BudgetThresholdReached.php`
- **Constructor**: `(int $userId, string $budgetType, float $currentSpending, float $budgetLimit, float $percentage, string $severity)`
- **Properties**: `userId`, `budgetType`, `currentSpending`, `budgetLimit`, `percentage`, `severity`
- **Status**: 🔴 CRITICAL MISMATCH - Constructor incompatible with middleware usage

### Additional Events (Beyond Specification)

#### ✅ CostAnomalyDetected
- **File**: `src/Events/CostAnomalyDetected.php`
- **Constructor**: `(int $userId, float $currentCost, float $averageCost, array $costData, array $metadata = [])`
- **Status**: ✅ ENHANCEMENT - Valuable addition for monitoring

#### ✅ UsageAnalyticsRecorded
- **File**: `src/Events/UsageAnalyticsRecorded.php`
- **Constructor**: `(array $analyticsData)`
- **Status**: ✅ ENHANCEMENT - Supports analytics requirements

#### ✅ CostTrackingFailed
- **File**: `src/Events/CostTrackingFailed.php`
- **Constructor**: `(array $context)`
- **Status**: ✅ ENHANCEMENT - Important for error handling

### Other Events (Not Cost Tracking Related)

- `ConversationCreated.php` - Conversation management
- `ConversationUpdated.php` - Conversation management
- `MessageAdded.php` - Message management
- `PerformanceThresholdExceeded.php` - Performance monitoring
- `ProviderFallbackTriggered.php` - Provider management
- `ProviderSwitched.php` - Provider management
- `AIFunctionCalled.php` - Function calling
- `AIFunctionCompleted.php` - Function calling
- `AIFunctionFailed.php` - Function calling
- `FunctionCallRequested.php` - Function calling
- `MCPToolExecuted.php` - MCP integration

## Specification Requirements vs Current Implementation

### Required Events from Specification

| Event | Required Properties | Current Properties | Status |
|-------|-------------------|-------------------|---------|
| **MessageSent** | user_id, provider, model, message, metadata | message, provider, model, options, conversationId, userId | ⚠️ PARTIAL |
| **ResponseGenerated** | user_id, provider, model, response, metadata | message, response, context, totalProcessingTime, providerMetadata | ⚠️ PARTIAL |
| **CostCalculated** | user_id, provider, model, input_tokens, output_tokens, cost, metadata | userId, provider, model, cost, inputTokens, outputTokens, conversationId, messageId | ⚠️ PARTIAL |
| **BudgetThresholdReached** | user_id, current_spend, budget_limit, threshold_percentage, metadata | userId, budgetType, currentSpending, budgetLimit, percentage, severity | 🔴 MISMATCH |

## Critical Issues Identified

### 1. BudgetThresholdReached Constructor Mismatch (CRITICAL)

**Issue**: Middleware attempts to create event with parameters that don't exist in constructor.

**Middleware Usage** (from BUDGET_IMPLEMENTATION_ISSUES.md):
```php
new BudgetThresholdReached(
    userId: $userId,
    budgetType: $budgetType,
    currentSpending: $currentSpending,
    budgetLimit: $budgetLimit,
    additionalCost: $additionalCost,        // ❌ Doesn't exist
    thresholdPercentage: $thresholdPercentage, // ❌ Should be 'percentage'
    projectId: $projectId,                  // ❌ Doesn't exist
    organizationId: $organizationId,        // ❌ Doesn't exist
    metadata: $metadata                     // ❌ Doesn't exist
)
```

**Actual Constructor**:
```php
public function __construct(int $userId, string $budgetType, float $currentSpending, 
                          float $budgetLimit, float $percentage, string $severity)
```

**Impact**: Events cannot be dispatched, breaking the entire budget alert system.

### 2. Missing Metadata Properties

**Issue**: Specification requires `metadata` property on core events, but current implementation lacks this.

**Missing From**:
- `CostCalculated` - No metadata property
- `ResponseGenerated` - Has `providerMetadata` but not general `metadata`
- `MessageSent` - Has `options` but not `metadata`

**Impact**: Reduces flexibility for passing additional context data.

### 3. Property Name Inconsistencies

**Issue**: Property names don't match specification expectations.

**Examples**:
- Spec: `current_spend` vs Actual: `currentSpending`
- Spec: `threshold_percentage` vs Actual: `percentage`
- Spec: `input_tokens` vs Actual: `inputTokens`
- Spec: `output_tokens` vs Actual: `outputTokens`

## Event Dependencies and Flow

### Cost Tracking Event Flow
1. `MessageSent` → Fired when message sent to AI provider
2. `ResponseGenerated` → Fired when response received
3. `CostCalculated` → Fired after cost calculation (CRITICAL)
4. `BudgetThresholdReached` → Fired if budget thresholds exceeded
5. `CostAnomalyDetected` → Fired if unusual cost patterns detected
6. `UsageAnalyticsRecorded` → Fired for analytics processing

### Dependencies
- `CostCalculated` depends on successful `ResponseGenerated`
- `BudgetThresholdReached` depends on `CostCalculated`
- `CostAnomalyDetected` depends on cost history data
- `UsageAnalyticsRecorded` can be triggered by multiple events

## Recommendations

### Immediate Actions Required (Critical)
1. **Fix BudgetThresholdReached Constructor** - Align with middleware usage
2. **Add Missing Metadata Properties** - Add to core events for specification compliance
3. **Standardize Property Names** - Use consistent naming convention

### Enhancement Opportunities
1. **Keep Additional Events** - `CostAnomalyDetected`, `UsageAnalyticsRecorded`, `CostTrackingFailed` provide valuable functionality
2. **Add Event Interfaces** - Create interfaces for consistent event structure
3. **Add Event Validation** - Ensure events contain required data

## Next Steps

This inventory will be used to:
1. Assess constructor compatibility (Phase 2)
2. Analyze listener registration (Phase 3)
3. Test integration functionality (Phase 4)
4. Create comprehensive gap analysis (Phase 5)
5. Generate implementation tickets (Phase 6)

## Files Audited

- ✅ `src/Events/MessageSent.php`
- ✅ `src/Events/ResponseGenerated.php`
- ✅ `src/Events/CostCalculated.php`
- ✅ `src/Events/BudgetThresholdReached.php`
- ✅ `src/Events/CostAnomalyDetected.php`
- ✅ `src/Events/UsageAnalyticsRecorded.php`
- ✅ `src/Events/CostTrackingFailed.php`
- ✅ All other events in `src/Events/` directory

**Total Events**: 18 existing events
**Core Cost Tracking Events**: 4 required, 4 exist (1 critical mismatch)
**Enhancement Events**: 3 valuable additions
**Other Events**: 11 supporting various features

---

# Phase 2: Event Constructor Compatibility Assessment

## Critical Constructor Mismatches Identified

### 🔴 BudgetThresholdReached - CRITICAL FAILURE

**Actual Constructor**:
```php
public function __construct(
    public int $userId,
    public string $budgetType,
    public float $currentSpending,
    public float $budgetLimit,
    public float $percentage,
    public string $severity
)
```

**Middleware Usage Attempts** (BudgetEnforcementMiddleware.php:460):
```php
event(new BudgetThresholdReached(
    userId: $userId,
    budgetType: $budgetType,
    currentSpending: $currentSpending,
    budgetLimit: $budgetLimit,
    additionalCost: $additionalCost,        // ❌ FATAL: Parameter doesn't exist
    thresholdPercentage: $thresholdPercentage, // ❌ FATAL: Should be 'percentage'
    projectId: $projectId,                  // ❌ FATAL: Parameter doesn't exist
    organizationId: $organizationId         // ❌ FATAL: Parameter doesn't exist
));
```

**Other Usage Attempts**:
- CostTrackingListener.php:552 - Uses `additionalCost` and `thresholdPercentage` (FATAL)
- BudgetAlertService.php:421 - Uses `additionalCost`, `thresholdPercentage`, `metadata` (FATAL)

**Working Usage Examples**:
- BudgetService.php:190 - Uses correct constructor ✅
- Tests - Use correct constructor ✅

**Impact**: Event dispatching fails with fatal errors, breaking budget alert system.

### ⚠️ CostCalculated - SPECIFICATION MISMATCH

**Actual Constructor**:
```php
public function __construct(
    public int $userId,
    public string $provider,
    public string $model,
    public float $cost,
    public int $inputTokens,
    public int $outputTokens,
    public ?int $conversationId = null,
    public ?int $messageId = null
)
```

**Specification Expected**:
```php
CostCalculated::class => [
    'user_id' => int,
    'provider' => string,
    'model' => string,
    'input_tokens' => int,
    'output_tokens' => int,
    'cost' => float,
    'metadata' => array,  // ❌ MISSING
]
```

**Current Usage** (All working):
- CostTrackingListener.php:67 - Uses correct constructor ✅
- Tests - Use correct constructor ✅
- Documentation examples - Use correct constructor ✅

**Issues**:
- Missing `metadata` parameter for additional context
- Property names use camelCase vs specification snake_case

### ✅ MessageSent - COMPATIBLE

**Actual Constructor**:
```php
public function __construct(
    AIMessage $message,
    string $provider,
    string $model,
    array $options = [],
    ?string $conversationId = null,
    $userId = null
)
```

**Usage**: All current usage is compatible with constructor.

### ⚠️ ResponseGenerated - SPECIFICATION MISMATCH

**Actual Constructor**:
```php
public function __construct(
    public AIMessage $message,
    public AIResponse $response,
    public array $context = [],
    public float $totalProcessingTime = 0,
    public array $providerMetadata = []
)
```

**Specification Expected**:
```php
ResponseGenerated::class => [
    'user_id' => int,      // ❌ MISSING - No direct user_id property
    'provider' => string,  // ❌ MISSING - Must extract from response
    'model' => string,     // ❌ MISSING - Must extract from response
    'response' => AIMessage,
    'metadata' => array,   // ⚠️ Has providerMetadata but not metadata
]
```

**Current Usage**: All working with current constructor.

## Constructor Compatibility Test Results

### Test 1: BudgetThresholdReached Instantiation

**❌ FAILED - Middleware Pattern**:
```php
// This FAILS with fatal error
try {
    $event = new BudgetThresholdReached(
        userId: 1,
        budgetType: 'daily',
        currentSpending: 50.0,
        budgetLimit: 100.0,
        additionalCost: 10.0,        // Fatal: Unknown parameter
        thresholdPercentage: 60.0    // Fatal: Unknown parameter
    );
} catch (Error $e) {
    // Fatal error: Unknown named parameter $additionalCost
}
```

**✅ PASSED - Correct Pattern**:
```php
$event = new BudgetThresholdReached(
    userId: 1,
    budgetType: 'daily',
    currentSpending: 50.0,
    budgetLimit: 100.0,
    percentage: 60.0,
    severity: 'warning'
);
```

### Test 2: CostCalculated Instantiation

**✅ PASSED - Current Pattern**:
```php
$event = new CostCalculated(
    userId: 1,
    provider: 'openai',
    model: 'gpt-4',
    cost: 0.003,
    inputTokens: 100,
    outputTokens: 50
);
```

**⚠️ SPECIFICATION PATTERN** (Would require constructor change):
```php
// This would require adding metadata parameter
$event = new CostCalculated(
    userId: 1,
    provider: 'openai',
    model: 'gpt-4',
    inputTokens: 100,
    outputTokens: 50,
    cost: 0.003,
    metadata: ['execution_time' => 1500]
);
```
