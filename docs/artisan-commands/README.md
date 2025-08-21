# Artisan Commands

JTD Laravel AI provides a comprehensive set of Artisan commands for managing AI providers, models, costs, and system maintenance. These commands help with installation, configuration, monitoring, and optimization of your AI system.

## Installation and Setup Commands

### `ai:install`
Install and configure the JTD Laravel AI package.

```bash
php artisan ai:install
```

**Options:**
- `--force` - Overwrite existing configuration files
- `--providers=openai,gemini` - Install specific providers only
- `--skip-migrations` - Skip running migrations

**What it does:**
- Publishes configuration files
- Runs database migrations
- Sets up default providers
- Creates sample environment configuration

### `ai:publish`
Publish package assets and configuration.

```bash
php artisan ai:publish
```

**Options:**
- `--config` - Publish configuration files only
- `--migrations` - Publish migration files only
- `--views` - Publish view files only
- `--all` - Publish all assets

## Provider Management Commands

### `ai:providers:list`
List all configured AI providers and their status.

```bash
php artisan ai:providers:list
```

**Options:**
- `--active` - Show only active providers
- `--format=table` - Output format (table, json, csv)

**Example Output:**
```
+----------+--------+--------+------------------+
| Provider | Driver | Status | Models Available |
+----------+--------+--------+------------------+
| openai   | openai | Active | 15               |
| gemini   | gemini | Active | 8                |
| xai      | xai    | Active | 3                |
+----------+--------+--------+------------------+
```

### `ai:providers:test`
Test connectivity and authentication for AI providers.

```bash
php artisan ai:providers:test [provider]
```

**Arguments:**
- `provider` - Specific provider to test (optional)

**Options:**
- `--timeout=30` - Connection timeout in seconds
- `--verbose` - Show detailed test results

### `ai:providers:verify`
Verify API credentials for all providers.

```bash
php artisan ai:providers:verify
```

**Options:**
- `--provider=openai` - Verify specific provider only
- `--fix` - Attempt to fix common credential issues

## Model Management Commands

### `ai:sync-models`
Synchronize available models from AI providers.

```bash
php artisan ai:sync-models
```

**Options:**
- `--provider=openai` - Sync specific provider only
- `--force` - Force sync even if recently updated
- `--verbose` - Show detailed sync progress

**Example:**
```bash
# Sync all providers
php artisan ai:sync-models

# Sync only OpenAI models
php artisan ai:sync-models --provider=openai

# Force sync with verbose output
php artisan ai:sync-models --force --verbose
```

### `ai:models:list`
List all available AI models.

```bash
php artisan ai:models:list
```

**Options:**
- `--provider=openai` - Filter by provider
- `--type=chat` - Filter by model type (chat, image, audio)
- `--active` - Show only active models
- `--format=table` - Output format

### `ai:models:info`
Get detailed information about a specific model.

```bash
php artisan ai:models:info {model} {provider}
```

**Arguments:**
- `model` - Model identifier
- `provider` - Provider name

**Example:**
```bash
php artisan ai:models:info gpt-4 openai
```

## Cost Management Commands

### `ai:costs:calculate`
Calculate costs for messages that don't have cost data.

```bash
php artisan ai:costs:calculate
```

**Options:**
- `--batch-size=1000` - Number of messages to process at once
- `--provider=openai` - Calculate costs for specific provider only
- `--from-date=2024-01-01` - Calculate costs from specific date

### `ai:costs:report`
Generate cost reports.

```bash
php artisan ai:costs:report
```

**Options:**
- `--start=2024-01-01` - Start date for report
- `--end=2024-01-31` - End date for report
- `--user=123` - Generate report for specific user
- `--provider=openai` - Filter by provider
- `--format=pdf` - Output format (pdf, csv, json)
- `--output=report.pdf` - Output file path

**Examples:**
```bash
# Monthly cost report
php artisan ai:costs:report --start=2024-01-01 --end=2024-01-31

# User-specific report in CSV format
php artisan ai:costs:report --user=123 --format=csv --output=user_costs.csv

# Provider comparison report
php artisan ai:costs:report --format=json --output=provider_comparison.json
```

### `ai:budgets:check`
Check budget status for users.

```bash
php artisan ai:budgets:check
```

**Options:**
- `--user=123` - Check specific user's budget
- `--alert` - Send alerts for users approaching limits
- `--enforce` - Enforce budget limits

### `ai:budgets:set`
Set budget limits for users.

```bash
php artisan ai:budgets:set {user} {amount} {type}
```

**Arguments:**
- `user` - User ID
- `amount` - Budget amount
- `type` - Budget type (daily, monthly, per_request)

**Example:**
```bash
php artisan ai:budgets:set 123 100.00 monthly
```

## Analytics Commands

### `ai:analytics:generate`
Generate usage analytics.

```bash
php artisan ai:analytics:generate
```

**Options:**
- `--period=daily` - Analytics period (daily, weekly, monthly)
- `--date=2024-01-15` - Specific date to generate analytics for
- `--force` - Regenerate existing analytics

### `ai:analytics:export`
Export analytics data.

```bash
php artisan ai:analytics:export
```

**Options:**
- `--start=2024-01-01` - Start date
- `--end=2024-01-31` - End date
- `--format=csv` - Export format (csv, json, xlsx)
- `--output=analytics.csv` - Output file

### `ai:usage:summary`
Display usage summary.

```bash
php artisan ai:usage:summary
```

**Options:**
- `--period=30` - Number of days to include
- `--user=123` - Show summary for specific user
- `--provider=openai` - Filter by provider

## Maintenance Commands

### `ai:cleanup`
Clean up old data and optimize database.

```bash
php artisan ai:cleanup
```

**Options:**
- `--days=90` - Keep data for specified number of days
- `--conversations` - Clean up old conversations
- `--analytics` - Clean up old analytics data
- `--dry-run` - Show what would be cleaned without actually doing it

### `ai:optimize`
Optimize the AI system for better performance.

```bash
php artisan ai:optimize
```

**Options:**
- `--cache` - Optimize caching
- `--database` - Optimize database queries
- `--models` - Optimize model data

### `ai:health`
Check system health and configuration.

```bash
php artisan ai:health
```

**Options:**
- `--fix` - Attempt to fix detected issues
- `--verbose` - Show detailed health information

**Example Output:**
```
AI System Health Check
======================

✓ Configuration is valid
✓ Database connection is working
✓ All providers are accessible
✓ Model data is up to date
⚠ Cache is not configured (recommended for production)
✗ OpenAI API key is invalid

Overall Status: Warning (1 issue found)
```

## Configuration Commands

### `ai:config:validate`
Validate AI configuration.

```bash
php artisan ai:config:validate
```

**Options:**
- `--fix` - Attempt to fix configuration issues
- `--strict` - Use strict validation rules

### `ai:config:show`
Display current AI configuration.

```bash
php artisan ai:config:show
```

**Options:**
- `--provider=openai` - Show configuration for specific provider
- `--hide-secrets` - Hide sensitive information like API keys

## MCP Commands

### `ai:mcp:list`
List available MCP servers.

```bash
php artisan ai:mcp:list
```

**Options:**
- `--active` - Show only active MCP servers
- `--capabilities` - Show server capabilities

### `ai:mcp:test`
Test MCP server functionality.

```bash
php artisan ai:mcp:test {server}
```

**Arguments:**
- `server` - MCP server name to test

**Example:**
```bash
php artisan ai:mcp:test sequential-thinking
```

### `ai:mcp:benchmark`
Benchmark MCP server performance.

```bash
php artisan ai:mcp:benchmark
```

**Options:**
- `--server=sequential-thinking` - Benchmark specific server
- `--iterations=100` - Number of test iterations

## Queue and Job Commands

### `ai:queue:status`
Show status of AI-related queue jobs.

```bash
php artisan ai:queue:status
```

**Options:**
- `--queue=ai-high` - Show status for specific queue
- `--failed` - Show only failed jobs

### `ai:jobs:retry`
Retry failed AI jobs.

```bash
php artisan ai:jobs:retry
```

**Options:**
- `--job-type=SyncModelsJob` - Retry specific job type only
- `--max-age=24` - Retry jobs failed within specified hours

## Monitoring Commands

### `ai:monitor`
Monitor AI system in real-time.

```bash
php artisan ai:monitor
```

**Options:**
- `--interval=5` - Refresh interval in seconds
- `--providers` - Monitor provider status
- `--costs` - Monitor cost accumulation
- `--usage` - Monitor usage statistics

### `ai:alerts:send`
Send pending alerts.

```bash
php artisan ai:alerts:send
```

**Options:**
- `--type=budget` - Send specific alert type only
- `--user=123` - Send alerts for specific user

## Development Commands

### `ai:make:driver`
Generate a new AI provider driver.

```bash
php artisan ai:make:driver {name}
```

**Arguments:**
- `name` - Driver name

**Options:**
- `--provider=custom` - Provider identifier
- `--force` - Overwrite existing driver

### `ai:make:mcp`
Generate a new MCP server.

```bash
php artisan ai:make:mcp {name}
```

**Arguments:**
- `name` - MCP server name

**Options:**
- `--tools` - Include tool support
- `--force` - Overwrite existing server

## Scheduling Commands

Most maintenance commands can be scheduled in your `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule)
{
    // Sync models hourly
    $schedule->command('ai:sync-models')->hourly();
    
    // Generate daily analytics
    $schedule->command('ai:analytics:generate daily')->daily();
    
    // Check budgets every 15 minutes
    $schedule->command('ai:budgets:check --alert')->everyFifteenMinutes();
    
    // Weekly cleanup
    $schedule->command('ai:cleanup')->weekly();
    
    // Health check every 5 minutes
    $schedule->command('ai:health')->everyFiveMinutes();
}
```

## Command Aliases

For convenience, you can create aliases for frequently used commands:

```bash
# Add to your shell profile (.bashrc, .zshrc, etc.)
alias ai-sync='php artisan ai:sync-models'
alias ai-costs='php artisan ai:costs:report'
alias ai-health='php artisan ai:health'
alias ai-test='php artisan ai:providers:test'
```

## Exit Codes

All commands follow standard exit code conventions:
- `0` - Success
- `1` - General error
- `2` - Configuration error
- `3` - Authentication error
- `4` - Network error
- `5` - Data validation error

## Getting Help

Use the `--help` option with any command to get detailed usage information:

```bash
php artisan ai:sync-models --help
php artisan ai:costs:report --help
php artisan ai:health --help
```
