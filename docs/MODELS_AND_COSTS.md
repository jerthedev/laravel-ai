# Models and Cost Management

## Overview

JTD Laravel AI features an **Enhanced Pricing System** with database-first architecture, AI-powered pricing discovery, and comprehensive cost management. The system automatically syncs available models from AI providers, tracks costs in real-time, and provides advanced analytics and budget controls for enterprise deployments.

### Enhanced Pricing System Features

- **Database-First Architecture**: Centralized pricing storage with intelligent fallback chains
- **AI-Powered Discovery**: Automatic pricing discovery using web search and NLP extraction
- **Multi-Unit Support**: Handle different pricing models (per token, per request, per image, etc.)
- **Real-Time Validation**: Comprehensive pricing validation with consistency checks
- **Cost Optimization**: Smart caching and performance optimization
- **Migration Tools**: Seamless migration from legacy pricing systems

## Model Management

### Automatic Model Syncing

The package automatically syncs available models from all configured providers:

```bash
# Sync all models
php artisan ai:sync-models

# Sync specific provider
php artisan ai:sync-models --provider=openai

# Force sync (ignore cache)
php artisan ai:sync-models --force

# Sync with verbose output
php artisan ai:sync-models --verbose
```

### Model Information

```php
use JTD\LaravelAI\Facades\AI;

// Get all available models
$models = AI::getModels();

// Get models for specific provider
$openaiModels = AI::getModels('openai');

// Get models by type
$chatModels = AI::getModels(null, 'chat');
$imageModels = AI::getModels(null, 'image');
$audioModels = AI::getModels(null, 'audio');

// Get model details
$model = AI::getModel('gpt-4', 'openai');
echo $model->name;              // "gpt-4"
echo $model->type;              // "chat"
echo $model->context_length;    // 8192
echo $model->max_output_tokens; // 4096
echo $model->supports_functions; // true
echo $model->supports_vision;   // true
```

### Model Capabilities

```php
// Check model capabilities
$capabilities = AI::getModelCapabilities('gpt-4', 'openai');

if ($capabilities->supports_functions) {
    // Model supports function calling
}

if ($capabilities->supports_vision) {
    // Model can process images
}

if ($capabilities->supports_streaming) {
    // Model supports streaming responses
}

// Get models with specific capabilities
$visionModels = AI::getModelsWithCapability('vision');
$functionModels = AI::getModelsWithCapability('functions');
```

### Model Selection

```php
// Automatic model selection based on task
$bestModel = AI::selectBestModel([
    'task_type' => 'chat',
    'max_tokens' => 2000,
    'budget' => 0.10, // $0.10 maximum cost
    'capabilities' => ['functions'],
]);

// Use selected model
$response = AI::conversation()
    ->model($bestModel->name, $bestModel->provider)
    ->message('Hello')
    ->send();
```

## Enhanced Pricing System

### Database-First Architecture

The Enhanced Pricing System uses a three-tier fallback architecture for maximum reliability:

1. **Database Pricing** (Primary): Current pricing from `ai_provider_model_costs` table
2. **Driver Static Defaults** (Fallback): Hardcoded pricing in driver classes
3. **Universal Fallback** (Last Resort): Basic fallback pricing for unknown models

```php
use JTD\LaravelAI\Services\PricingService;

$pricingService = app(PricingService::class);

// Get pricing with automatic fallback
$pricing = $pricingService->getModelPricing('openai', 'gpt-4o');
echo $pricing['source']; // 'database', 'driver_static', or 'universal_fallback'
echo $pricing['input'];  // Input cost per unit
echo $pricing['output']; // Output cost per unit
echo $pricing['unit']->value; // Pricing unit (1k_tokens, 1m_tokens, etc.)
```

### Pricing Synchronization

Sync pricing data from static files to database:

```bash
# Sync models and pricing
php artisan ai:sync-models --show-pricing

# Enable AI-powered pricing discovery
php artisan ai:sync-models --enable-ai-discovery --show-pricing

# Validate pricing without storing
php artisan ai:sync-models --validate-only

# Force pricing update
php artisan ai:sync-models --force-pricing
```

### AI-Powered Pricing Discovery

Automatically discover current pricing using web search and NLP extraction:

```bash
# Configure AI discovery (disabled by default for safety)
AI_PRICING_DISCOVERY_ENABLED=true
AI_PRICING_DISCOVERY_MAX_COST=0.01
AI_PRICING_DISCOVERY_CONFIDENCE=0.8
AI_PRICING_DISCOVERY_CONFIRM=true
```

```php
use JTD\LaravelAI\Services\IntelligentPricingDiscovery;

$discovery = app(IntelligentPricingDiscovery::class);

// Discover pricing for a model
$result = $discovery->discoverPricing('openai', 'gpt-4o', [
    'confirmed' => true, // Skip confirmation prompt
]);

if ($result['status'] === 'success') {
    echo "Discovered pricing: " . json_encode($result['pricing']);
    echo "Confidence: " . $result['confidence_score'];
}
```

### Pricing Validation

Comprehensive validation ensures pricing data quality:

```php
use JTD\LaravelAI\Services\PricingValidator;

$validator = app(PricingValidator::class);

// Validate pricing array
$pricing = [
    'gpt-4o' => [
        'input' => 0.0025,
        'output' => 0.01,
        'unit' => \JTD\LaravelAI\Enums\PricingUnit::PER_1K_TOKENS,
        'currency' => 'USD',
        'billing_model' => \JTD\LaravelAI\Enums\BillingModel::PAY_PER_USE,
    ],
];

$errors = $validator->validatePricingArray($pricing);
if (empty($errors)) {
    echo "Pricing is valid!";
} else {
    foreach ($errors as $error) {
        echo "Error: $error\n";
    }
}

// Get validation summary
$summary = $validator->getValidationSummary($pricing);
echo "Valid: " . ($summary['valid'] ? 'Yes' : 'No');
echo "Errors: " . $summary['error_count'];
echo "Warnings: " . $summary['warning_count'];
```

### Migration from Legacy System

Migrate from the legacy pricing system to the enhanced database-first system:

```bash
# Validate current pricing before migration
php artisan ai:migrate-pricing-system --validate-only

# Perform dry run to see what would be migrated
php artisan ai:migrate-pricing-system --dry-run

# Migrate with backup
php artisan ai:migrate-pricing-system --backup

# Rollback if needed
php artisan ai:migrate-pricing-system --rollback
```

### Advanced Pricing Features

```php
// Compare pricing across providers
$comparisons = [
    ['provider' => 'openai', 'model' => 'gpt-4o'],
    ['provider' => 'gemini', 'model' => 'gemini-2.0-flash'],
    ['provider' => 'xai', 'model' => 'grok-2-1212'],
];

$results = $pricingService->comparePricing($comparisons, 1000, 500);
foreach ($results as $result) {
    echo "{$result['provider']} {$result['model']}: \${$result['total_cost']}\n";
}

// Normalize pricing units for comparison
$pricing = $pricingService->getModelPricing('openai', 'gpt-4o');
$normalized = $pricingService->normalizePricing($pricing, PricingUnit::PER_1M_TOKENS);

// Store custom pricing to database
$customPricing = [
    'input' => 0.005,
    'output' => 0.015,
    'unit' => PricingUnit::PER_1K_TOKENS,
    'currency' => 'USD',
    'billing_model' => BillingModel::PAY_PER_USE,
    'effective_date' => '2024-01-01',
];

$stored = $pricingService->storePricingToDatabase('custom', 'my-model', $customPricing);
```

### Configuration

Configure the Enhanced Pricing System in `config/ai.php`:

```php
'model_sync' => [
    'pricing' => [
        'enabled' => env('AI_PRICING_SYNC_ENABLED', true),
        'sync_with_models' => env('AI_PRICING_SYNC_WITH_MODELS', true),
        'store_to_database' => env('AI_PRICING_STORE_DATABASE', true),
        'validate_pricing' => env('AI_PRICING_VALIDATE', true),
    ],

    'ai_discovery' => [
        'enabled' => env('AI_PRICING_DISCOVERY_ENABLED', false),
        'max_cost_per_discovery' => env('AI_PRICING_DISCOVERY_MAX_COST', 0.01),
        'confidence_threshold' => env('AI_PRICING_DISCOVERY_CONFIDENCE', 0.8),
        'require_confirmation' => env('AI_PRICING_DISCOVERY_CONFIRM', true),
    ],

    'validation' => [
        'strict_mode' => env('AI_PRICING_VALIDATION_STRICT', false),
        'max_cost_per_token' => env('AI_PRICING_MAX_COST_TOKEN', 0.001),
        'warn_on_inconsistencies' => env('AI_PRICING_WARN_INCONSISTENT', true),
    ],
],
```

## Cost Tracking

### Real-time Cost Calculation

```php
// Costs are automatically calculated for each message
$response = AI::conversation()
    ->message('Hello world')
    ->send();

echo $response->cost;           // $0.000123
echo $response->input_tokens;   // 2
echo $response->output_tokens;  // 3
echo $response->total_tokens;   // 5
```

### Cost Breakdown

```php
// Get detailed cost breakdown
$conversation = AI::conversation('My Chat');
$costs = $conversation->getCostBreakdown();

echo $costs['total'];                    // Total cost
echo $costs['input_cost'];              // Input token costs
echo $costs['output_cost'];             // Output token costs
echo $costs['by_provider']['openai'];   // Costs by provider
echo $costs['by_model']['gpt-4'];       // Costs by model
echo $costs['by_date']['2024-01-15'];   // Daily costs
```

### Cost Estimation

```php
// Estimate cost before sending
$estimate = AI::estimateCost([
    'provider' => 'openai',
    'model' => 'gpt-4',
    'input_tokens' => 100,
    'output_tokens' => 50,
]);

echo $estimate['total'];        // $0.0045
echo $estimate['input_cost'];   // $0.003
echo $estimate['output_cost'];  // $0.0015

// Estimate for conversation
$estimate = $conversation->estimateNextMessageCost('Hello, how are you?');
```

## Cost Analytics

### Usage Statistics

```php
use JTD\LaravelAI\Facades\AI;

// Get user usage statistics
$stats = AI::getUserUsage(auth()->user(), [
    'start_date' => '2024-01-01',
    'end_date' => '2024-01-31',
]);

echo $stats['total_cost'];          // $45.67
echo $stats['total_tokens'];        // 123456
echo $stats['total_requests'];      // 234
echo $stats['avg_cost_per_request']; // $0.195

// Get provider usage
$providerStats = AI::getProviderUsage('openai', '2024-01-01', '2024-01-31');

// Get model usage
$modelStats = AI::getModelUsage('gpt-4', '2024-01-01', '2024-01-31');
```

### Cost Trends

```php
// Get cost trends
$trends = AI::getCostTrends([
    'period' => 'daily', // daily, weekly, monthly
    'start_date' => '2024-01-01',
    'end_date' => '2024-01-31',
    'user_id' => auth()->id(),
]);

foreach ($trends as $date => $cost) {
    echo "$date: $cost\n";
}

// Get usage growth
$growth = AI::getUsageGrowth('monthly');
echo "Growth rate: {$growth['percentage']}%";
```

### Cost Comparison

```php
// Compare providers
$comparison = AI::compareProviderCosts([
    'providers' => ['openai', 'gemini', 'xai'],
    'task_type' => 'chat',
    'input_tokens' => 1000,
    'output_tokens' => 500,
]);

foreach ($comparison as $provider => $cost) {
    echo "$provider: $cost\n";
}

// Find cheapest option
$cheapest = AI::findCheapestProvider([
    'task_type' => 'chat',
    'input_tokens' => 1000,
    'output_tokens' => 500,
    'capabilities' => ['functions'],
]);
```

## Budget Management

### Setting Budgets

```php
// Set user budget
AI::setUserBudget(auth()->user(), [
    'monthly_limit' => 100.00,  // $100 per month
    'daily_limit' => 5.00,      // $5 per day
    'per_request_limit' => 1.00, // $1 per request
]);

// Set conversation budget
$conversation->setBudget([
    'total_limit' => 10.00,     // $10 total
    'per_message_limit' => 0.50, // $0.50 per message
]);

// Set project budget
AI::setProjectBudget('project-123', [
    'monthly_limit' => 500.00,
    'alert_threshold' => 0.8, // Alert at 80%
]);
```

### Budget Monitoring

```php
// Check budget status
$budget = AI::getUserBudget(auth()->user());

echo $budget['monthly_spent'];      // $45.67
echo $budget['monthly_remaining'];  // $54.33
echo $budget['daily_spent'];        // $2.34
echo $budget['daily_remaining'];    // $2.66

// Check if over budget
if ($budget['is_over_monthly_limit']) {
    // User is over monthly budget
}

// Get budget alerts
$alerts = AI::getBudgetAlerts(auth()->user());
```

### Budget Enforcement

```php
// Enable budget enforcement
AI::enableBudgetEnforcement(auth()->user());

// Try to send message (will fail if over budget)
try {
    $response = AI::conversation()
        ->message('Hello')
        ->send();
} catch (BudgetExceededException $e) {
    echo "Budget exceeded: " . $e->getMessage();
}

// Check before sending
if (AI::canAfford(auth()->user(), $estimatedCost)) {
    $response = AI::conversation()->message('Hello')->send();
}
```

## Cost Optimization

### Automatic Optimization

```php
// Enable cost optimization
$response = AI::conversation()
    ->costOptimized()           // Use cheapest suitable provider
    ->maxCost(0.10)            // Maximum cost per message
    ->message('Summarize this text...')
    ->send();

// Optimize for specific criteria
$response = AI::conversation()
    ->optimize([
        'cost' => 0.7,          // 70% weight on cost
        'speed' => 0.2,         // 20% weight on speed
        'quality' => 0.1,       // 10% weight on quality
    ])
    ->message('Hello')
    ->send();
```

### Token Optimization

```php
// Optimize token usage
$response = AI::conversation()
    ->optimizeTokens()          // Automatically optimize prompt
    ->maxTokens(500)           // Limit output tokens
    ->message('Very long prompt that will be optimized...')
    ->send();

// Token counting
$tokenCount = AI::countTokens('Hello world', 'gpt-4');
echo "Tokens: $tokenCount";

// Optimize prompt
$optimized = AI::optimizePrompt('Very long prompt...', [
    'target_tokens' => 100,
    'preserve_meaning' => true,
]);
```

### Batch Processing for Cost Savings

```php
// Batch multiple requests
$responses = AI::batch()
    ->provider('openai')
    ->model('gpt-3.5-turbo')    // Use cheaper model for batch
    ->messages([
        'Summarize text 1...',
        'Summarize text 2...',
        'Summarize text 3...',
    ])
    ->process();

// Get batch cost savings
$savings = AI::getBatchSavings($responses);
echo "Saved: $" . $savings['amount'];
```

## Cost Reporting

### Generate Reports

```bash
# Generate cost report
php artisan ai:report:costs --start=2024-01-01 --end=2024-01-31

# Generate user report
php artisan ai:report:user-costs --user=123

# Generate provider comparison report
php artisan ai:report:provider-comparison

# Export to CSV
php artisan ai:report:costs --format=csv --output=costs.csv
```

### Custom Reports

```php
// Create custom cost report
$report = AI::createCostReport([
    'start_date' => '2024-01-01',
    'end_date' => '2024-01-31',
    'group_by' => ['provider', 'model', 'user'],
    'filters' => [
        'min_cost' => 0.01,
        'providers' => ['openai', 'gemini'],
    ],
]);

// Export report
$report->exportToCsv('monthly_costs.csv');
$report->exportToPdf('monthly_costs.pdf');
$report->exportToExcel('monthly_costs.xlsx');
```

### Scheduled Reports

```php
// Schedule daily cost reports
AI::scheduleCostReport([
    'frequency' => 'daily',
    'recipients' => ['admin@example.com'],
    'format' => 'pdf',
    'filters' => ['min_cost' => 1.00],
]);

// Schedule budget alerts
AI::scheduleBudgetAlerts([
    'frequency' => 'hourly',
    'threshold' => 0.8, // 80% of budget
    'recipients' => ['finance@example.com'],
]);
```

## Model Pricing Management

### Pricing Updates

```bash
# Sync latest pricing from providers
php artisan ai:sync-pricing

# Update specific provider pricing
php artisan ai:sync-pricing --provider=openai

# Set custom pricing
php artisan ai:set-pricing gpt-4 --input=0.03 --output=0.06
```

### Pricing History

```php
// Get pricing history
$history = AI::getPricingHistory('gpt-4', 'openai');

foreach ($history as $price) {
    echo "{$price->effective_date}: Input: {$price->input_cost}, Output: {$price->output_cost}\n";
}

// Get price changes
$changes = AI::getPriceChanges('2024-01-01', '2024-01-31');
```

### Custom Pricing

```php
// Set custom pricing for organization
AI::setCustomPricing('gpt-4', [
    'input_cost_per_token' => 0.025,  // Custom negotiated rate
    'output_cost_per_token' => 0.05,
    'effective_date' => '2024-01-01',
]);

// Apply volume discounts
AI::setVolumeDiscount(auth()->user(), [
    'threshold' => 1000.00,  // $1000 monthly spend
    'discount' => 0.1,       // 10% discount
]);
```

## Performance Monitoring

### Model Performance

```php
// Get model performance metrics
$performance = AI::getModelPerformance('gpt-4', [
    'start_date' => '2024-01-01',
    'end_date' => '2024-01-31',
]);

echo $performance['avg_response_time'];  // 1.23 seconds
echo $performance['success_rate'];       // 99.5%
echo $performance['error_rate'];         // 0.5%
echo $performance['cost_efficiency'];    // Cost per successful request

// Compare model performance
$comparison = AI::compareModelPerformance(['gpt-4', 'gpt-3.5-turbo']);
```

### Cost Efficiency Analysis

```php
// Analyze cost efficiency
$efficiency = AI::analyzeCostEfficiency([
    'user_id' => auth()->id(),
    'start_date' => '2024-01-01',
    'end_date' => '2024-01-31',
]);

echo $efficiency['cost_per_token'];      // $0.000045
echo $efficiency['cost_per_request'];    // $0.123
echo $efficiency['tokens_per_dollar'];   // 22222

// Get efficiency recommendations
$recommendations = AI::getCostOptimizationRecommendations(auth()->user());
```

## Integration with Laravel Features

### Queue Integration

```php
// Queue cost calculations
dispatch(new CalculateCostsJob($conversation));

// Queue model syncing
dispatch(new SyncModelsJob('openai'));

// Queue budget alerts
dispatch(new CheckBudgetAlertsJob());
```

### Cache Integration

```php
// Cache model information
Cache::remember('ai:models:openai', 3600, function () {
    return AI::getModels('openai');
});

// Cache cost calculations
Cache::remember("ai:cost:{$messageId}", 86400, function () use ($messageId) {
    return AI::calculateMessageCost($messageId);
});
```

### Event Integration

```php
// Listen for cost events
Event::listen(CostCalculated::class, function ($event) {
    if ($event->cost > 1.00) {
        // Alert for high-cost requests
        Mail::to('admin@example.com')->send(new HighCostAlert($event));
    }
});

// Listen for budget events
Event::listen(BudgetThresholdReached::class, function ($event) {
    // Send budget warning
    Notification::send($event->user, new BudgetWarning($event->budget));
});
```
