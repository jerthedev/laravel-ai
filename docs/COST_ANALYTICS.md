# Cost Analytics and Reporting

## Overview

JTD Laravel AI provides comprehensive cost analytics and reporting capabilities to help organizations monitor, analyze, and optimize their AI spending. The system tracks costs in real-time, provides detailed breakdowns, and offers predictive analytics for budget planning.

## Real-time Cost Tracking

### Automatic Cost Calculation

```php
use JTD\LaravelAI\Facades\AI;

// Costs are automatically calculated for every request
$response = AI::conversation()
    ->message('Hello world')
    ->send();

echo "Cost: $" . $response->cost;           // $0.000123
echo "Input tokens: " . $response->input_tokens;   // 2
echo "Output tokens: " . $response->output_tokens; // 3
echo "Total tokens: " . $response->total_tokens;   // 5
```

### Cost Breakdown

```php
// Get detailed cost breakdown for a conversation
$conversation = AI::conversation('My Chat');
$costs = $conversation->getCostBreakdown();

echo "Total cost: $" . $costs['total'];
echo "Input cost: $" . $costs['input_cost'];
echo "Output cost: $" . $costs['output_cost'];

// Breakdown by provider
foreach ($costs['by_provider'] as $provider => $cost) {
    echo "{$provider}: ${cost}\n";
}

// Breakdown by model
foreach ($costs['by_model'] as $model => $cost) {
    echo "{$model}: ${cost}\n";
}

// Daily breakdown
foreach ($costs['by_date'] as $date => $cost) {
    echo "{$date}: ${cost}\n";
}
```

## Analytics Dashboard

### User Analytics

```php
// Get comprehensive user analytics
$analytics = AI::getUserAnalytics(auth()->user(), [
    'start_date' => '2024-01-01',
    'end_date' => '2024-01-31',
    'granularity' => 'daily', // daily, weekly, monthly
]);

echo "Total spent: $" . $analytics['total_cost'];
echo "Total requests: " . $analytics['total_requests'];
echo "Average cost per request: $" . $analytics['avg_cost_per_request'];
echo "Total tokens: " . $analytics['total_tokens'];
echo "Most used provider: " . $analytics['top_provider'];
echo "Most used model: " . $analytics['top_model'];

// Daily usage trends
foreach ($analytics['daily_usage'] as $date => $usage) {
    echo "{$date}: {$usage['requests']} requests, ${usage['cost']}\n";
}
```

### Organization Analytics

```php
// Get organization-wide analytics
$orgAnalytics = AI::getOrganizationAnalytics([
    'start_date' => '2024-01-01',
    'end_date' => '2024-01-31',
]);

echo "Organization total: $" . $orgAnalytics['total_cost'];
echo "Active users: " . $orgAnalytics['active_users'];
echo "Top spending user: " . $orgAnalytics['top_user']['name'];
echo "Top spending department: " . $orgAnalytics['top_department'];

// Department breakdown
foreach ($orgAnalytics['by_department'] as $dept => $cost) {
    echo "{$dept}: ${cost}\n";
}
```

### Provider Comparison

```php
// Compare costs across providers
$comparison = AI::compareProviderCosts([
    'providers' => ['openai', 'gemini', 'xai'],
    'start_date' => '2024-01-01',
    'end_date' => '2024-01-31',
    'task_type' => 'chat',
]);

foreach ($comparison as $provider => $data) {
    echo "{$provider}:\n";
    echo "  Total cost: ${data['total_cost']}\n";
    echo "  Requests: {$data['requests']}\n";
    echo "  Avg cost per request: ${data['avg_cost']}\n";
    echo "  Cost efficiency: {$data['efficiency_score']}\n";
}

// Find most cost-effective provider
$mostEfficient = AI::getMostCostEffectiveProvider([
    'task_type' => 'chat',
    'quality_threshold' => 0.8,
]);
```

## Cost Forecasting

### Usage Prediction

```php
// Predict future costs based on historical data
$forecast = AI::forecastCosts([
    'user_id' => auth()->id(),
    'forecast_period' => 30, // days
    'confidence_interval' => 0.95,
]);

echo "Predicted cost (next 30 days): $" . $forecast['predicted_cost'];
echo "Lower bound: $" . $forecast['lower_bound'];
echo "Upper bound: $" . $forecast['upper_bound'];
echo "Confidence: " . ($forecast['confidence'] * 100) . "%";

// Daily predictions
foreach ($forecast['daily_predictions'] as $date => $prediction) {
    echo "{$date}: ${prediction['cost']} (±{$prediction['margin']})\n";
}
```

### Trend Analysis

```php
// Analyze usage trends
$trends = AI::analyzeTrends([
    'user_id' => auth()->id(),
    'period' => 'monthly',
    'lookback_months' => 6,
]);

echo "Growth rate: " . $trends['growth_rate'] . "%\n";
echo "Trend direction: " . $trends['direction']; // increasing, decreasing, stable
echo "Seasonality detected: " . ($trends['seasonal'] ? 'Yes' : 'No');

// Monthly trend data
foreach ($trends['monthly_data'] as $month => $data) {
    echo "{$month}: ${data['cost']} ({$data['change']}% change)\n";
}
```

### Budget Projections

```php
// Project when budget will be exhausted
$projection = AI::projectBudgetExhaustion([
    'user_id' => auth()->id(),
    'current_budget' => 100.00,
    'budget_period' => 'monthly',
]);

if ($projection['will_exceed']) {
    echo "Budget will be exceeded on: " . $projection['exhaustion_date'];
    echo "Days remaining: " . $projection['days_remaining'];
    echo "Recommended daily limit: $" . $projection['recommended_daily_limit'];
}
```

## Advanced Analytics

### Cost Optimization Analysis

```php
// Analyze potential cost savings
$optimization = AI::analyzeCostOptimization([
    'user_id' => auth()->id(),
    'analysis_period' => 30,
]);

echo "Potential monthly savings: $" . $optimization['potential_savings'];
echo "Current efficiency score: " . $optimization['efficiency_score'];

// Optimization recommendations
foreach ($optimization['recommendations'] as $rec) {
    echo "Recommendation: " . $rec['title'] . "\n";
    echo "Potential savings: $" . $rec['savings'] . "\n";
    echo "Implementation: " . $rec['description'] . "\n\n";
}

// Provider switching recommendations
foreach ($optimization['provider_switches'] as $switch) {
    echo "Switch from {$switch['from']} to {$switch['to']}\n";
    echo "Estimated savings: ${switch['savings']} per month\n";
    echo "Quality impact: {$switch['quality_impact']}\n";
}
```

### Usage Pattern Analysis

```php
// Analyze usage patterns
$patterns = AI::analyzeUsagePatterns([
    'user_id' => auth()->id(),
    'analysis_period' => 90,
]);

echo "Peak usage hours: " . implode(', ', $patterns['peak_hours']);
echo "Peak usage days: " . implode(', ', $patterns['peak_days']);
echo "Average session length: " . $patterns['avg_session_length'] . " minutes";

// Usage by time of day
foreach ($patterns['hourly_distribution'] as $hour => $usage) {
    echo "Hour {$hour}: {$usage['requests']} requests, ${usage['cost']}\n";
}

// Usage by day of week
foreach ($patterns['daily_distribution'] as $day => $usage) {
    echo "{$day}: {$usage['requests']} requests, ${usage['cost']}\n";
}
```

### Model Performance Analysis

```php
// Analyze model performance vs cost
$performance = AI::analyzeModelPerformance([
    'start_date' => '2024-01-01',
    'end_date' => '2024-01-31',
    'metrics' => ['cost', 'response_time', 'quality_score'],
]);

foreach ($performance['models'] as $model => $data) {
    echo "{$model}:\n";
    echo "  Average cost: ${data['avg_cost']}\n";
    echo "  Average response time: {$data['avg_response_time']}ms\n";
    echo "  Quality score: {$data['quality_score']}/10\n";
    echo "  Cost efficiency: {$data['cost_efficiency']}\n";
    echo "  Recommendation: {$data['recommendation']}\n\n";
}
```

## Reporting System

### Automated Reports

```bash
# Generate cost reports via Artisan commands
php artisan ai:report:costs --start=2024-01-01 --end=2024-01-31
php artisan ai:report:user-costs --user=123
php artisan ai:report:department-costs --department=engineering
php artisan ai:report:provider-comparison
php artisan ai:report:optimization-opportunities
```

### Custom Reports

```php
// Create custom cost report
$report = AI::createCostReport([
    'title' => 'Monthly AI Usage Report',
    'start_date' => '2024-01-01',
    'end_date' => '2024-01-31',
    'filters' => [
        'users' => [123, 456, 789],
        'providers' => ['openai', 'gemini'],
        'min_cost' => 0.01,
    ],
    'group_by' => ['user', 'provider', 'model'],
    'metrics' => [
        'total_cost',
        'total_requests',
        'avg_cost_per_request',
        'total_tokens',
        'cost_trend',
    ],
    'visualizations' => [
        'cost_over_time',
        'provider_breakdown',
        'user_ranking',
        'model_comparison',
    ],
]);

// Export report in various formats
$report->exportToPdf('monthly_report.pdf');
$report->exportToCsv('monthly_data.csv');
$report->exportToExcel('monthly_report.xlsx');
$report->exportToJson('monthly_data.json');

// Email report
$report->emailTo(['admin@company.com', 'finance@company.com']);
```

### Scheduled Reports

```php
// Schedule recurring reports
AI::scheduleReport([
    'name' => 'Weekly Cost Summary',
    'type' => 'cost_summary',
    'frequency' => 'weekly',
    'day_of_week' => 'monday',
    'time' => '09:00',
    'recipients' => ['finance@company.com'],
    'filters' => [
        'departments' => ['engineering', 'marketing'],
    ],
    'format' => 'pdf',
]);

// Schedule budget alerts
AI::scheduleBudgetAlert([
    'name' => 'Budget Threshold Alert',
    'threshold' => 0.8, // 80% of budget
    'frequency' => 'daily',
    'recipients' => ['admin@company.com'],
    'escalation' => [
        0.9 => ['manager@company.com'],
        1.0 => ['ceo@company.com'],
    ],
]);
```

## Cost Control and Alerts

### Budget Monitoring

```php
// Set up budget monitoring
AI::setBudgetMonitoring([
    'user_id' => auth()->id(),
    'monthly_budget' => 100.00,
    'alert_thresholds' => [0.5, 0.8, 0.9, 1.0],
    'alert_methods' => ['email', 'slack', 'webhook'],
]);

// Check budget status
$budgetStatus = AI::getBudgetStatus(auth()->user());

if ($budgetStatus['is_over_budget']) {
    echo "Over budget by: $" . $budgetStatus['overage'];
}

if ($budgetStatus['approaching_limit']) {
    echo "Approaching budget limit: " . $budgetStatus['percentage_used'] . "% used";
}
```

### Cost Alerts

```php
// Set up cost alerts
AI::setCostAlerts([
    'user_id' => auth()->id(),
    'daily_limit' => 10.00,
    'per_request_limit' => 1.00,
    'unusual_spending_threshold' => 2.0, // 2x normal spending
    'alert_channels' => [
        'email' => 'user@company.com',
        'slack' => '#ai-alerts',
        'webhook' => 'https://company.com/webhooks/ai-alerts',
    ],
]);

// Manual cost check
$costCheck = AI::checkCostLimits(auth()->user());

if (!$costCheck['within_limits']) {
    foreach ($costCheck['violations'] as $violation) {
        echo "Limit exceeded: {$violation['type']} - {$violation['message']}\n";
    }
}
```

## Integration with Business Intelligence

### Data Export

```php
// Export data for BI tools
$export = AI::exportAnalyticsData([
    'start_date' => '2024-01-01',
    'end_date' => '2024-01-31',
    'granularity' => 'daily',
    'dimensions' => ['user', 'provider', 'model', 'department'],
    'metrics' => ['cost', 'tokens', 'requests', 'response_time'],
    'format' => 'csv', // csv, json, parquet
]);

// Save to data warehouse
$export->saveToDataWarehouse('ai_usage_analytics');

// Send to BI platform
$export->sendToTableau();
$export->sendToPowerBI();
$export->sendToLooker();
```

### API Endpoints

```php
// RESTful API for analytics data
Route::get('/api/ai/analytics/costs', [AnalyticsController::class, 'costs']);
Route::get('/api/ai/analytics/usage', [AnalyticsController::class, 'usage']);
Route::get('/api/ai/analytics/trends', [AnalyticsController::class, 'trends']);
Route::get('/api/ai/analytics/forecasts', [AnalyticsController::class, 'forecasts']);

// GraphQL endpoint
Route::post('/graphql/ai-analytics', [GraphQLController::class, 'analytics']);
```

### Webhook Integration

```php
// Set up webhooks for real-time analytics
AI::setWebhook('cost_threshold_reached', [
    'url' => 'https://company.com/webhooks/ai-cost-alert',
    'headers' => [
        'Authorization' => 'Bearer ' . config('webhooks.ai_token'),
    ],
    'payload' => [
        'user_id' => '{{user_id}}',
        'cost' => '{{cost}}',
        'threshold' => '{{threshold}}',
        'timestamp' => '{{timestamp}}',
    ],
]);
```

## Performance Optimization

### Analytics Caching

```php
// Cache expensive analytics queries
$analytics = Cache::remember('user_analytics_' . auth()->id(), 3600, function () {
    return AI::getUserAnalytics(auth()->user(), [
        'start_date' => now()->subDays(30),
        'end_date' => now(),
    ]);
});

// Warm up analytics cache
Artisan::command('ai:warm-analytics-cache', function () {
    $users = User::active()->get();
    
    foreach ($users as $user) {
        AI::getUserAnalytics($user, [
            'start_date' => now()->subDays(30),
            'end_date' => now(),
        ]);
    }
});
```

### Background Processing

```php
// Process analytics in background
dispatch(new GenerateAnalyticsJob([
    'user_id' => auth()->id(),
    'period' => 'monthly',
    'callback_url' => route('analytics.callback'),
]));

// Batch analytics processing
dispatch(new BatchAnalyticsJob([
    'users' => User::active()->pluck('id'),
    'start_date' => now()->subMonth(),
    'end_date' => now(),
]));
```

## Compliance and Auditing

### Audit Trail

```php
// Track all cost-related events
AI::auditCostEvent([
    'event_type' => 'budget_exceeded',
    'user_id' => auth()->id(),
    'amount' => 150.00,
    'budget_limit' => 100.00,
    'metadata' => [
        'provider' => 'openai',
        'model' => 'gpt-4',
        'conversation_id' => 123,
    ],
]);

// Get audit trail
$auditTrail = AI::getAuditTrail([
    'user_id' => auth()->id(),
    'start_date' => '2024-01-01',
    'event_types' => ['budget_exceeded', 'cost_alert', 'unusual_spending'],
]);
```

### Compliance Reports

```php
// Generate compliance reports
$complianceReport = AI::generateComplianceReport([
    'type' => 'financial_audit',
    'period' => 'quarterly',
    'year' => 2024,
    'quarter' => 1,
    'include_user_data' => false, // GDPR compliance
]);

// Export for external auditors
$complianceReport->exportForAudit('Q1_2024_AI_Usage_Audit.pdf');
```
