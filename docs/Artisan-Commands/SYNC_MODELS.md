# Model Synchronization Commands

## Overview

Model synchronization commands help you keep your local model database up-to-date with the latest models available from AI providers. This ensures you always have access to the newest models and their current pricing information.

## `ai:sync-models`

Synchronizes available models from AI providers to your local database.

### Basic Usage

```bash
# Sync models from all configured providers
php artisan ai:sync-models

# Sync models from a specific provider
php artisan ai:sync-models --provider=openai

# Force sync even if recently updated
php artisan ai:sync-models --force

# Verbose output showing detailed progress
php artisan ai:sync-models --verbose
```

### Options

| Option | Description | Default |
|--------|-------------|---------|
| `--provider=NAME` | Sync models from specific provider only | All providers |
| `--force` | Force sync even if recently updated | false |
| `--verbose` | Show detailed sync progress | false |
| `--dry-run` | Show what would be synced without actually syncing | false |
| `--timeout=SECONDS` | API timeout in seconds | 30 |

### Examples

#### Sync All Providers
```bash
php artisan ai:sync-models
```

**Output:**
```
Syncing models from AI providers...

OpenAI:
  ✓ Found 15 models
  ✓ Updated 2 models
  ✓ Added 1 new model

Gemini:
  ✓ Found 8 models
  ✓ All models up to date

xAI:
  ✓ Found 3 models
  ✓ Updated 1 model

Sync completed successfully!
Total: 26 models synced across 3 providers
```

#### Sync Specific Provider with Verbose Output
```bash
php artisan ai:sync-models --provider=openai --verbose
```

**Output:**
```
Syncing models from OpenAI...

Fetching models from OpenAI API...
✓ API request successful (1.2s)

Processing models:
  gpt-4-turbo-preview
    ✓ Model exists, checking for updates
    ✓ Updated context_length: 128000 → 128000
    ✓ Updated pricing: input $0.01/1K → $0.01/1K, output $0.03/1K → $0.03/1K
    
  gpt-4-vision-preview
    ✓ Model exists, no changes needed
    
  gpt-3.5-turbo-0125
    ✓ New model detected
    ✓ Added with capabilities: chat, functions
    ✓ Set pricing: input $0.0005/1K, output $0.0015/1K

OpenAI sync completed:
- 15 models processed
- 1 model added
- 1 model updated
- 13 models unchanged
```

#### Dry Run to Preview Changes
```bash
php artisan ai:sync-models --dry-run
```

**Output:**
```
DRY RUN - No changes will be made

OpenAI:
  Would add: gpt-4-turbo (new model)
  Would update: gpt-4 (pricing changed)
  Would skip: 13 models (no changes)

Gemini:
  Would skip: 8 models (no changes)

xAI:
  Would update: grok-beta (capabilities updated)
  Would skip: 2 models (no changes)

Summary:
- 1 model would be added
- 2 models would be updated
- 23 models would remain unchanged
```

## `ai:sync-pricing`

Synchronizes pricing information for AI models.

### Basic Usage

```bash
# Sync pricing for all providers
php artisan ai:sync-pricing

# Sync pricing for specific provider
php artisan ai:sync-pricing --provider=openai

# Force pricing update
php artisan ai:sync-pricing --force
```

### Options

| Option | Description | Default |
|--------|-------------|---------|
| `--provider=NAME` | Sync pricing for specific provider only | All providers |
| `--force` | Force pricing update even if recently updated | false |
| `--verbose` | Show detailed pricing information | false |

### Example

```bash
php artisan ai:sync-pricing --provider=openai --verbose
```

**Output:**
```
Syncing pricing for OpenAI models...

gpt-4:
  Input: $0.03 per 1K tokens
  Output: $0.06 per 1K tokens
  ✓ Pricing up to date

gpt-4-turbo:
  Input: $0.01 per 1K tokens (was $0.015)
  Output: $0.03 per 1K tokens
  ✓ Updated pricing

gpt-3.5-turbo:
  Input: $0.0005 per 1K tokens
  Output: $0.0015 per 1K tokens
  ✓ Pricing up to date

Pricing sync completed for OpenAI
Updated: 1 model
Unchanged: 14 models
```

## `ai:models:list`

Lists all available AI models in your database.

### Basic Usage

```bash
# List all models
php artisan ai:models:list

# List models for specific provider
php artisan ai:models:list --provider=openai

# List only chat models
php artisan ai:models:list --type=chat

# List only active models
php artisan ai:models:list --active
```

### Options

| Option | Description | Default |
|--------|-------------|---------|
| `--provider=NAME` | Filter by provider | All providers |
| `--type=TYPE` | Filter by model type (chat, image, audio) | All types |
| `--active` | Show only active models | All models |
| `--format=FORMAT` | Output format (table, json, csv) | table |
| `--sort=FIELD` | Sort by field (name, provider, type, created_at) | name |

### Examples

#### List All Models
```bash
php artisan ai:models:list
```

**Output:**
```
+------------------+----------+-------+--------+---------------+--------+
| Model            | Provider | Type  | Status | Context Length| Cost   |
+------------------+----------+-------+--------+---------------+--------+
| gpt-4            | openai   | chat  | active | 8192          | High   |
| gpt-4-turbo      | openai   | chat  | active | 128000        | Medium |
| gpt-3.5-turbo    | openai   | chat  | active | 4096          | Low    |
| gemini-pro       | gemini   | chat  | active | 32768         | Low    |
| gemini-pro-vision| gemini   | chat  | active | 16384         | Medium |
| grok-beta        | xai      | chat  | active | 8192          | Medium |
+------------------+----------+-------+--------+---------------+--------+
```

#### List Models in JSON Format
```bash
php artisan ai:models:list --format=json --provider=openai
```

**Output:**
```json
[
  {
    "id": "gpt-4",
    "name": "GPT-4",
    "provider": "openai",
    "type": "chat",
    "status": "active",
    "context_length": 8192,
    "capabilities": ["chat", "functions"],
    "pricing": {
      "input_cost_per_token": 0.00003,
      "output_cost_per_token": 0.00006
    },
    "last_synced": "2024-01-15T10:30:00Z"
  }
]
```

## `ai:models:info`

Get detailed information about a specific model.

### Basic Usage

```bash
# Get info for specific model
php artisan ai:models:info gpt-4 openai

# Get info with pricing history
php artisan ai:models:info gpt-4 openai --history

# Get info in JSON format
php artisan ai:models:info gpt-4 openai --format=json
```

### Options

| Option | Description | Default |
|--------|-------------|---------|
| `--history` | Include pricing history | false |
| `--format=FORMAT` | Output format (table, json) | table |

### Example

```bash
php artisan ai:models:info gpt-4 openai --history
```

**Output:**
```
Model Information: gpt-4 (OpenAI)
================================

Basic Information:
  Name: GPT-4
  Provider: OpenAI
  Type: Chat
  Status: Active
  Context Length: 8,192 tokens
  Max Output: 4,096 tokens

Capabilities:
  ✓ Chat completion
  ✓ Function calling
  ✓ JSON mode
  ✗ Vision
  ✗ Audio

Current Pricing:
  Input: $0.03 per 1,000 tokens
  Output: $0.06 per 1,000 tokens
  
Pricing History:
  2024-01-01: Input $0.03, Output $0.06
  2023-11-01: Input $0.03, Output $0.06
  2023-07-01: Input $0.03, Output $0.06

Usage Statistics (Last 30 days):
  Total Requests: 1,247
  Total Tokens: 2,847,392
  Total Cost: $156.23
  Average Cost per Request: $0.125

Last Synced: 2024-01-15 10:30:00 UTC
```

## `ai:models:cleanup`

Clean up outdated or deprecated models.

### Basic Usage

```bash
# Clean up deprecated models
php artisan ai:models:cleanup

# Dry run to see what would be cleaned
php artisan ai:models:cleanup --dry-run

# Clean up models older than specific date
php artisan ai:models:cleanup --older-than=2023-01-01
```

### Options

| Option | Description | Default |
|--------|-------------|---------|
| `--dry-run` | Show what would be cleaned without actually cleaning | false |
| `--older-than=DATE` | Clean models last synced before this date | 90 days ago |
| `--force` | Force cleanup without confirmation | false |

## Scheduling Model Sync

You can schedule model synchronization in your `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule)
{
    // Sync models every hour
    $schedule->command('ai:sync-models')
        ->hourly()
        ->withoutOverlapping()
        ->onOneServer();
    
    // Sync pricing daily at 2 AM
    $schedule->command('ai:sync-pricing')
        ->dailyAt('02:00')
        ->onOneServer();
    
    // Clean up old models weekly
    $schedule->command('ai:models:cleanup')
        ->weekly()
        ->sundays()
        ->at('03:00');
}
```

## Configuration

Model sync behavior can be configured in `config/ai.php`:

```php
'model_sync' => [
    'enabled' => env('AI_MODEL_SYNC_ENABLED', true),
    'frequency' => env('AI_MODEL_SYNC_FREQUENCY', 'hourly'),
    'timeout' => 30,
    'retry_attempts' => 3,
    'batch_size' => 50,
    'cache_ttl' => 3600, // 1 hour
],
```

## Troubleshooting

### Common Issues

#### API Rate Limits
```bash
# Use longer timeout and fewer retries
php artisan ai:sync-models --timeout=60 --provider=openai
```

#### Network Timeouts
```bash
# Sync providers one at a time
php artisan ai:sync-models --provider=openai
php artisan ai:sync-models --provider=gemini
php artisan ai:sync-models --provider=xai
```

#### Stale Data
```bash
# Force refresh all model data
php artisan ai:sync-models --force
```

### Error Messages

| Error | Solution |
|-------|----------|
| "Provider not configured" | Check your `.env` file for API keys |
| "API timeout" | Increase timeout with `--timeout=60` |
| "Rate limit exceeded" | Wait and retry, or sync providers individually |
| "Invalid API key" | Verify your API key in configuration |

## Monitoring

You can monitor model sync status:

```bash
# Check when models were last synced
php artisan ai:models:list --sort=synced_at

# View sync logs
tail -f storage/logs/laravel.log | grep "model sync"

# Check for failed sync jobs
php artisan queue:failed | grep SyncModels
```
