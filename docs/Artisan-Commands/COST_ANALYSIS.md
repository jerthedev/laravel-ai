# Cost Analysis Commands

## Overview

Cost analysis commands provide comprehensive tools for tracking, analyzing, and optimizing AI usage costs. These commands help you understand spending patterns, generate reports, and make data-driven decisions about AI usage.

## `ai:costs:report`

Generate detailed cost reports for AI usage.

### Basic Usage

```bash
# Generate report for current month
php artisan ai:costs:report

# Generate report for specific date range
php artisan ai:costs:report --start=2024-01-01 --end=2024-01-31

# Generate user-specific report
php artisan ai:costs:report --user=123

# Generate report in CSV format
php artisan ai:costs:report --format=csv --output=costs.csv
```

### Options

| Option | Description | Default |
|--------|-------------|---------|
| `--start=DATE` | Start date (YYYY-MM-DD) | First day of current month |
| `--end=DATE` | End date (YYYY-MM-DD) | Current date |
| `--user=ID` | Filter by specific user ID | All users |
| `--provider=NAME` | Filter by provider | All providers |
| `--model=NAME` | Filter by model | All models |
| `--format=FORMAT` | Output format (table, csv, json, pdf) | table |
| `--output=FILE` | Output file path | stdout |
| `--group-by=FIELD` | Group results by field | date |
| `--sort=FIELD` | Sort by field | date |
| `--limit=N` | Limit number of results | No limit |

### Examples

#### Monthly Cost Report
```bash
php artisan ai:costs:report --start=2024-01-01 --end=2024-01-31
```

**Output:**
```
AI Cost Report: January 2024
============================

Summary:
  Total Cost: $1,247.83
  Total Requests: 15,432
  Total Tokens: 8,947,291
  Average Cost per Request: $0.081

By Provider:
+----------+----------+----------+----------+
| Provider | Requests | Tokens   | Cost     |
+----------+----------+----------+----------+
| openai   | 8,234    | 4,892,341| $892.45  |
| gemini   | 4,891    | 2,847,392| $234.67  |
| xai      | 2,307    | 1,207,558| $120.71  |
+----------+----------+----------+----------+

By Model:
+------------------+----------+----------+----------+
| Model            | Requests | Tokens   | Cost     |
+------------------+----------+----------+----------+
| gpt-4            | 3,421    | 2,847,392| $567.89  |
| gpt-3.5-turbo    | 4,813    | 2,044,949| $324.56  |
| gemini-pro       | 4,891    | 2,847,392| $234.67  |
| grok-beta        | 2,307    | 1,207,558| $120.71  |
+------------------+----------+----------+----------+

Daily Breakdown:
+------------+----------+----------+
| Date       | Requests | Cost     |
+------------+----------+----------+
| 2024-01-01 | 487      | $39.23   |
| 2024-01-02 | 523      | $42.17   |
| ...        | ...      | ...      |
| 2024-01-31 | 612      | $49.87   |
+------------+----------+----------+
```

#### User-Specific Report in CSV Format
```bash
php artisan ai:costs:report --user=123 --format=csv --output=user_123_costs.csv
```

**Generated CSV:**
```csv
Date,Provider,Model,Requests,Tokens,Input_Tokens,Output_Tokens,Cost
2024-01-01,openai,gpt-4,15,8432,6234,2198,0.89
2024-01-01,gemini,gemini-pro,8,4521,3421,1100,0.23
2024-01-02,openai,gpt-3.5-turbo,23,12847,9234,3613,0.45
...
```

#### Provider Comparison Report
```bash
php artisan ai:costs:report --group-by=provider --format=json
```

**Output:**
```json
{
  "summary": {
    "total_cost": 1247.83,
    "total_requests": 15432,
    "total_tokens": 8947291,
    "period": "2024-01-01 to 2024-01-31"
  },
  "by_provider": {
    "openai": {
      "requests": 8234,
      "tokens": 4892341,
      "cost": 892.45,
      "percentage": 71.5,
      "avg_cost_per_request": 0.108
    },
    "gemini": {
      "requests": 4891,
      "tokens": 2847392,
      "cost": 234.67,
      "percentage": 18.8,
      "avg_cost_per_request": 0.048
    },
    "xai": {
      "requests": 2307,
      "tokens": 1207558,
      "cost": 120.71,
      "percentage": 9.7,
      "avg_cost_per_request": 0.052
    }
  }
}
```

## `ai:costs:analyze`

Perform advanced cost analysis and optimization recommendations.

### Basic Usage

```bash
# Analyze costs for current month
php artisan ai:costs:analyze

# Analyze specific user's costs
php artisan ai:costs:analyze --user=123

# Analyze with optimization recommendations
php artisan ai:costs:analyze --optimize

# Analyze trends over time
php artisan ai:costs:analyze --trends --period=6months
```

### Options

| Option | Description | Default |
|--------|-------------|---------|
| `--user=ID` | Analyze specific user | All users |
| `--period=PERIOD` | Analysis period (1month, 3months, 6months, 1year) | 1month |
| `--optimize` | Include optimization recommendations | false |
| `--trends` | Include trend analysis | false |
| `--forecast` | Include cost forecasting | false |
| `--format=FORMAT` | Output format (table, json) | table |

### Examples

#### Cost Analysis with Optimization
```bash
php artisan ai:costs:analyze --optimize --trends
```

**Output:**
```
AI Cost Analysis
================

Current Period: Last 30 days
Total Cost: $1,247.83
Growth Rate: +15.3% vs previous period

Cost Efficiency Analysis:
+------------------+----------+----------+----------+
| Model            | Requests | Cost/Req | Efficiency|
+------------------+----------+----------+----------+
| gpt-3.5-turbo    | 4,813    | $0.067   | Excellent |
| gemini-pro       | 4,891    | $0.048   | Excellent |
| grok-beta        | 2,307    | $0.052   | Good      |
| gpt-4            | 3,421    | $0.166   | Expensive |
+------------------+----------+----------+----------+

Optimization Recommendations:
1. Consider using gpt-3.5-turbo instead of gpt-4 for simple tasks
   Potential savings: $234.56/month (41% reduction for affected requests)

2. Batch similar requests to reduce API overhead
   Potential savings: $45.23/month (3.6% reduction)

3. Implement response caching for repeated queries
   Potential savings: $89.12/month (7.1% reduction)

Trend Analysis:
- Usage increasing 15.3% month-over-month
- Cost per request decreasing 2.1% (efficiency improving)
- Peak usage: Weekdays 9-11 AM, 2-4 PM

Forecast (Next 30 days):
- Estimated cost: $1,438.21 (±$127.45)
- Recommended budget: $1,600.00
```

#### User Cost Analysis
```bash
php artisan ai:costs:analyze --user=123 --forecast
```

**Output:**
```
User Cost Analysis: John Doe (ID: 123)
=====================================

Current Month:
  Total Cost: $89.45
  Requests: 1,247
  Average per Request: $0.072

Usage Patterns:
  Most Active: Weekdays 10 AM - 12 PM
  Preferred Provider: OpenAI (67%)
  Most Used Model: gpt-3.5-turbo (54%)

Cost Breakdown:
  OpenAI: $59.87 (67%)
  Gemini: $21.34 (24%)
  xAI: $8.24 (9%)

Forecast (Next 30 days):
  Estimated Cost: $94.23 (±$12.45)
  Trend: Stable usage pattern
  Budget Recommendation: $110.00

Efficiency Score: 8.2/10 (Above Average)
```

## `ai:costs:budget`

Manage and monitor budget limits and alerts.

### Basic Usage

```bash
# Check budget status for all users
php artisan ai:costs:budget

# Set budget for specific user
php artisan ai:costs:budget --set --user=123 --amount=100 --type=monthly

# Check budget alerts
php artisan ai:costs:budget --alerts

# Send budget notifications
php artisan ai:costs:budget --notify
```

### Options

| Option | Description | Default |
|--------|-------------|---------|
| `--user=ID` | Specific user ID | All users |
| `--set` | Set budget limit | false |
| `--amount=AMOUNT` | Budget amount (when setting) | Required with --set |
| `--type=TYPE` | Budget type (daily, monthly, per_request) | monthly |
| `--alerts` | Show budget alerts | false |
| `--notify` | Send budget notifications | false |
| `--threshold=PCT` | Alert threshold percentage | 80 |

### Examples

#### Check Budget Status
```bash
php artisan ai:costs:budget
```

**Output:**
```
Budget Status Report
===================

Users Approaching Budget Limits:
+------+----------+----------+----------+----------+
| User | Name     | Budget   | Spent    | Status   |
+------+----------+----------+----------+----------+
| 123  | John Doe | $100.00  | $89.45   | Warning  |
| 456  | Jane S.  | $50.00   | $47.23   | Warning  |
| 789  | Bob J.   | $200.00  | $198.67  | Critical |
+------+----------+----------+----------+----------+

Budget Alerts:
- 3 users over 80% of budget
- 1 user over 95% of budget
- 0 users over budget limit

Recommendations:
- Send warning notifications to users over 80%
- Consider increasing budgets for consistent high users
```

#### Set User Budget
```bash
php artisan ai:costs:budget --set --user=123 --amount=150 --type=monthly
```

**Output:**
```
Budget Updated Successfully
==========================

User: John Doe (ID: 123)
Previous Budget: $100.00/month
New Budget: $150.00/month
Current Spending: $89.45 (59.6% of new budget)
Status: Within limits
```

## `ai:costs:optimize`

Analyze and implement cost optimization strategies.

### Basic Usage

```bash
# Get optimization recommendations
php artisan ai:costs:optimize

# Implement automatic optimizations
php artisan ai:costs:optimize --implement

# Analyze specific optimization area
php artisan ai:costs:optimize --focus=models

# Generate optimization report
php artisan ai:costs:optimize --report --output=optimization_report.pdf
```

### Options

| Option | Description | Default |
|--------|-------------|---------|
| `--implement` | Implement safe optimizations automatically | false |
| `--focus=AREA` | Focus on specific area (models, providers, caching) | all |
| `--report` | Generate detailed optimization report | false |
| `--output=FILE` | Output file for report | stdout |
| `--savings-threshold=AMOUNT` | Minimum savings to recommend | $10.00 |

### Example

```bash
php artisan ai:costs:optimize --report
```

**Output:**
```
AI Cost Optimization Analysis
=============================

Total Potential Monthly Savings: $387.45 (31.1%)

High-Impact Optimizations:
1. Model Selection Optimization - $234.56 savings
   - Replace gpt-4 with gpt-3.5-turbo for 67% of simple queries
   - Confidence: High
   - Implementation: Automatic routing based on query complexity

2. Response Caching - $89.12 savings
   - Cache responses for repeated queries (24% of requests)
   - Confidence: High
   - Implementation: Redis-based caching with 1-hour TTL

3. Request Batching - $45.23 savings
   - Batch similar requests to reduce API overhead
   - Confidence: Medium
   - Implementation: Queue-based batching system

Medium-Impact Optimizations:
4. Provider Load Balancing - $18.54 savings
   - Route requests to most cost-effective provider
   - Confidence: Medium
   - Implementation: Dynamic provider selection

Low-Impact Optimizations:
5. Token Optimization - $12.34 savings
   - Optimize prompts to reduce token usage
   - Confidence: Low
   - Implementation: Prompt engineering guidelines

Implementation Priority:
1. Enable response caching (Quick win, high impact)
2. Implement model selection optimization
3. Set up request batching
4. Configure provider load balancing
5. Establish token optimization guidelines

Risk Assessment:
- Low risk: Caching, batching
- Medium risk: Model selection (may affect quality)
- High risk: Provider switching (may affect reliability)
```

## `ai:costs:forecast`

Generate cost forecasts and budget planning recommendations.

### Basic Usage

```bash
# Generate 3-month forecast
php artisan ai:costs:forecast

# Forecast for specific user
php artisan ai:costs:forecast --user=123

# Generate annual budget planning
php artisan ai:costs:forecast --period=12months --budget-planning
```

### Options

| Option | Description | Default |
|--------|-------------|---------|
| `--period=PERIOD` | Forecast period (1month, 3months, 6months, 12months) | 3months |
| `--user=ID` | Forecast for specific user | All users |
| `--budget-planning` | Include budget planning recommendations | false |
| `--confidence=LEVEL` | Confidence level (80, 90, 95) | 90 |
| `--format=FORMAT` | Output format (table, json, csv) | table |

### Example

```bash
php artisan ai:costs:forecast --period=6months --budget-planning
```

**Output:**
```
AI Cost Forecast: Next 6 Months
===============================

Historical Analysis:
- Average monthly cost: $1,247.83
- Growth rate: +15.3% month-over-month
- Seasonal patterns: 20% higher in Q4

Forecast (90% confidence):
+----------+----------+----------+----------+
| Month    | Low Est. | Expected | High Est.|
+----------+----------+----------+----------+
| Feb 2024 | $1,298   | $1,438   | $1,578   |
| Mar 2024 | $1,356   | $1,502   | $1,648   |
| Apr 2024 | $1,417   | $1,569   | $1,721   |
| May 2024 | $1,481   | $1,639   | $1,797   |
| Jun 2024 | $1,548   | $1,713   | $1,878   |
| Jul 2024 | $1,618   | $1,791   | $1,964   |
+----------+----------+----------+----------+

6-Month Total: $9,652 (±$1,247)

Budget Planning Recommendations:
- Conservative Budget: $11,000 (covers 95% confidence)
- Recommended Budget: $10,200 (covers 90% confidence)
- Optimistic Budget: $9,400 (covers 80% confidence)

Key Assumptions:
- Current growth rate continues
- No major usage pattern changes
- Provider pricing remains stable
- Optimization efforts reduce costs by 5%

Risk Factors:
- New team members joining (+15% cost impact)
- Increased AI model usage in production (+25% impact)
- Provider price increases (+10% impact)
```

## Scheduling Cost Analysis

Schedule regular cost analysis in `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule)
{
    // Daily cost calculation
    $schedule->command('ai:costs:calculate')
        ->daily()
        ->at('01:00');
    
    // Weekly cost report
    $schedule->command('ai:costs:report --format=pdf --output=weekly_report.pdf')
        ->weekly()
        ->mondays()
        ->at('09:00');
    
    // Monthly optimization analysis
    $schedule->command('ai:costs:optimize --report')
        ->monthly()
        ->at('08:00');
    
    // Budget alerts every 4 hours
    $schedule->command('ai:costs:budget --alerts --notify')
        ->everyFourHours();
}
```

## Configuration

Cost analysis settings in `config/ai.php`:

```php
'cost_analysis' => [
    'enabled' => env('AI_COST_ANALYSIS_ENABLED', true),
    'currency' => env('AI_COST_CURRENCY', 'USD'),
    'precision' => 6,
    'retention_days' => 365,
    'budget_alerts' => [
        'enabled' => true,
        'thresholds' => [80, 90, 95, 100],
        'channels' => ['mail', 'slack'],
    ],
    'optimization' => [
        'auto_implement' => false,
        'min_savings' => 10.00,
        'confidence_threshold' => 0.8,
    ],
],
```
