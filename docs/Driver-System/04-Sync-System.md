# Model Synchronization System

## Overview

The driver system includes a comprehensive model synchronization architecture that automatically discovers providers with valid credentials and syncs their available models to the local cache/database.

## Key Features

### Auto-Discovery
- Automatically finds all configured providers with valid credentials
- Skips providers with invalid or missing credentials
- Filters out mock providers in production environments

### Generic Command
- Single `ai:sync-models` command works with all providers
- Provider-specific sync with `--provider` flag
- Supports dry-run mode for planning and validation

### Intelligent Caching
- Avoids unnecessary API calls with smart cache validation
- Configurable cache TTL (default 24 hours)
- Automatic refresh based on last sync time

### Comprehensive Logging
- Detailed logs for monitoring and troubleshooting
- Success and failure tracking
- Performance metrics and statistics

### Statistics Tracking
- Tracks model counts and capabilities for operational insights
- Provider-specific statistics (e.g., GPT-4 models, function calling support)
- Historical sync data for trend analysis

### Error Resilience
- Graceful error handling with detailed error reporting
- Individual provider failures don't affect others
- Retry logic with exponential backoff

## Architecture Components

### Sync Interface Methods

All drivers implement these sync-related methods:

```php
// Perform actual synchronization
public function syncModels(bool $forceRefresh = false): array;

// Check credential validity
public function hasValidCredentials(): bool;

// Get last sync timestamp
public function getLastSyncTime(): ?\Carbon\Carbon;

// Preview syncable models (dry-run)
public function getSyncableModels(): array;
```

### DriverManager Integration

The `DriverManager` provides multi-provider sync operations:

```php
// Get providers with valid credentials
public function getProvidersWithValidCredentials(): array;

// Sync all providers
public function syncAllProviderModels(bool $forceRefresh = false): array;

// Get all last sync times
public function getAllLastSyncTimes(): array;
```

### Sync Command

The `SyncModelsCommand` provides user-friendly CLI interface:

```bash
# Sync all providers
php artisan ai:sync-models

# Sync specific provider
php artisan ai:sync-models --provider=openai

# Force refresh
php artisan ai:sync-models --force

# Dry run
php artisan ai:sync-models --dry-run

# Verbose output
php artisan ai:sync-models -v
```

## Sync Flow

### 1. Provider Discovery
```
SyncModelsCommand
├── Get available providers from DriverManager
├── Filter by valid credentials
├── Skip mock provider in production
└── Return list of syncable providers
```

### 2. Individual Provider Sync
```
Driver.syncModels()
├── Check if refresh needed (unless forced)
├── Fetch models from provider API
├── Store in cache with TTL
├── Update last sync timestamp
├── Generate statistics
└── Return sync results
```

### 3. Result Aggregation
```
Command Output
├── Display per-provider results
├── Show statistics (if verbose)
├── Report any errors
└── Provide summary totals
```

## Sync Results Format

### Success Response
```php
[
    'status' => 'success',
    'models_synced' => 15,
    'statistics' => [
        'total_models' => 15,
        'gpt_4_models' => 5,
        'gpt_3_5_models' => 3,
        'function_calling_models' => 12,
        'vision_models' => 8,
        'updated_at' => '2024-01-15T10:30:00Z',
    ],
    'cached_until' => Carbon::parse('2024-01-16T10:30:00Z'),
    'last_sync' => Carbon::parse('2024-01-15T10:30:00Z'),
]
```

### Skipped Response
```php
[
    'status' => 'skipped',
    'reason' => 'cache_valid',
    'last_sync' => Carbon::parse('2024-01-15T04:30:00Z'),
]
```

### Error Response
```php
[
    'status' => 'error',
    'error' => 'API authentication failed',
    'error_type' => 'invalid_credentials',
]
```

## Cache Strategy

### Cache Keys
- Models: `laravel-ai:{provider}:models`
- Last sync: `laravel-ai:{provider}:models:last_sync`
- Statistics: `laravel-ai:{provider}:models:stats`
- Failures: `laravel-ai:{provider}:models:last_failure`

### Cache TTL
- Models: 24 hours (configurable)
- Last sync: 7 days
- Statistics: 7 days
- Failures: 24 hours

### Refresh Logic
Models are refreshed when:
- Force refresh is requested (`--force`)
- Last sync is older than 12 hours
- No previous sync exists
- Cache is manually cleared

## Statistics Tracking

### Common Statistics
- `total_models`: Total number of models
- `updated_at`: Timestamp of statistics generation

### Provider-Specific Statistics

#### OpenAI
- `gpt_4_models`: GPT-4 family models
- `gpt_3_5_models`: GPT-3.5 family models
- `gpt_4o_models`: GPT-4o family models
- `function_calling_models`: Models supporting function calling
- `vision_models`: Models supporting vision/images

#### Gemini
- `gemini_pro_models`: Gemini Pro models
- `gemini_flash_models`: Gemini Flash models
- `multimodal_models`: Models supporting text + images
- `safety_filtered_models`: Models with safety filtering

#### xAI
- `grok_models`: Grok family models
- `reasoning_models`: Models optimized for reasoning

## Monitoring & Observability

### Logging
All sync operations are logged with:
- Provider name
- Operation type (sync, skip, error)
- Model counts
- Performance metrics
- Error details

### Events
Sync operations fire events for:
- Sync started
- Sync completed
- Sync failed
- Models updated

### Health Checks
The sync system provides health check endpoints:
- Last sync times per provider
- Sync failure rates
- Model availability status

## Configuration

### Sync Settings
```php
'sync' => [
    'auto_sync' => (bool) env('AI_AUTO_SYNC_MODELS', true),
    'sync_interval' => (int) env('AI_SYNC_INTERVAL_HOURS', 12),
    'cache_ttl' => (int) env('AI_MODEL_CACHE_TTL_HOURS', 24),
    'timeout' => (int) env('AI_SYNC_TIMEOUT', 30),
    'retry_attempts' => (int) env('AI_SYNC_RETRY_ATTEMPTS', 3),
],
```

### Environment Variables
```env
AI_AUTO_SYNC_MODELS=true
AI_SYNC_INTERVAL_HOURS=12
AI_MODEL_CACHE_TTL_HOURS=24
AI_SYNC_TIMEOUT=30
AI_SYNC_RETRY_ATTEMPTS=3
```

## Benefits

### Operational
- **Automated Discovery**: No manual provider configuration needed
- **Consistent Interface**: Same command works for all providers
- **Efficient Caching**: Reduces API calls and improves performance
- **Comprehensive Monitoring**: Detailed insights into model availability

### Development
- **Easy Testing**: Dry-run mode for safe testing
- **Flexible Options**: Provider-specific and global sync options
- **Error Handling**: Graceful failure handling and reporting
- **Statistics**: Rich data for operational insights

### Production
- **Reliability**: Robust error handling and retry logic
- **Performance**: Intelligent caching and efficient API usage
- **Monitoring**: Comprehensive logging and health checks
- **Scalability**: Handles multiple providers efficiently

## Related Documentation

- **[Sync Implementation](05-Sync-Implementation.md)**: How to implement sync in drivers
- **[Sync Commands](06-Sync-Commands.md)**: Using the ai:sync-models command
- **[Driver Interface](03-Interface.md)**: Complete interface specification
- **[OpenAI Driver](07-OpenAI-Driver.md)**: Reference sync implementation
