# Installation Guide

## Requirements

- PHP 8.1 or higher
- Laravel 10.0 or higher
- MySQL 8.0+ / PostgreSQL 13+ / SQLite 3.35+
- Composer 2.0+

## Installation Steps

### 1. Install via Composer

```bash
composer require jerthedev/laravel-ai
```

### 2. Publish Configuration and Migrations

```bash
# Publish configuration file
php artisan vendor:publish --provider="JTD\LaravelAI\LaravelAIServiceProvider" --tag="config"

# Publish migrations
php artisan vendor:publish --provider="JTD\LaravelAI\LaravelAIServiceProvider" --tag="migrations"

# Optional: Publish views (if you plan to customize)
php artisan vendor:publish --provider="JTD\LaravelAI\LaravelAIServiceProvider" --tag="views"
```

### 3. Run Migrations

```bash
php artisan migrate
```

This will create the following tables:
- `ai_providers`
- `ai_accounts`
- `ai_provider_models`
- `ai_provider_model_costs`
- `ai_conversations`
- `ai_messages`
- `ai_conversation_participants`
- `ai_usage_analytics`
- `ai_cost_tracking`
- `ai_model_performance`

### 4. Environment Configuration

Add the following to your `.env` file:

```env
# Default AI Provider
AI_DEFAULT_PROVIDER=openai

# OpenAI Configuration
AI_OPENAI_API_KEY=your-openai-api-key
AI_OPENAI_ORGANIZATION=your-org-id-optional

# xAI Configuration
AI_XAI_API_KEY=your-xai-api-key

# Google Gemini Configuration
AI_GEMINI_API_KEY=your-gemini-api-key

# Ollama Configuration (for local models)
AI_OLLAMA_BASE_URL=http://localhost:11434
AI_OLLAMA_TIMEOUT=120

# Cost Tracking
AI_COST_TRACKING_ENABLED=true
AI_COST_CURRENCY=USD

# Model Syncing
AI_MODEL_SYNC_ENABLED=true
AI_MODEL_SYNC_FREQUENCY=hourly

# Caching
AI_CACHE_ENABLED=true
AI_CACHE_TTL=3600
```

### 5. Initialize Providers and Models

```bash
# Sync available models from all configured providers
php artisan ai:sync-models

# Initialize cost data
php artisan ai:sync-costs

# Verify installation
php artisan ai:status
```

## Configuration

### Basic Configuration

The main configuration file is located at `config/ai.php`. Here's a basic setup:

```php
<?php

return [
    'default' => env('AI_DEFAULT_PROVIDER', 'openai'),
    
    'providers' => [
        'openai' => [
            'driver' => 'openai',
            'api_key' => env('AI_OPENAI_API_KEY'),
            'organization' => env('AI_OPENAI_ORGANIZATION'),
            'timeout' => 30,
            'retry_attempts' => 3,
        ],
        
        'xai' => [
            'driver' => 'xai',
            'api_key' => env('AI_XAI_API_KEY'),
            'timeout' => 30,
        ],
        
        'gemini' => [
            'driver' => 'gemini',
            'api_key' => env('AI_GEMINI_API_KEY'),
            'timeout' => 30,
        ],
    ],
    
    'cost_tracking' => [
        'enabled' => env('AI_COST_TRACKING_ENABLED', true),
        'currency' => env('AI_COST_CURRENCY', 'USD'),
        'precision' => 6,
    ],
    
    'model_sync' => [
        'enabled' => env('AI_MODEL_SYNC_ENABLED', true),
        'frequency' => env('AI_MODEL_SYNC_FREQUENCY', 'hourly'),
    ],
];
```

### Advanced Configuration

For advanced setups, you can configure multiple accounts per provider:

```php
'providers' => [
    'openai' => [
        'accounts' => [
            'default' => [
                'api_key' => env('AI_OPENAI_API_KEY'),
                'organization' => env('AI_OPENAI_ORGANIZATION'),
            ],
            'premium' => [
                'api_key' => env('AI_OPENAI_PREMIUM_API_KEY'),
                'organization' => env('AI_OPENAI_PREMIUM_ORG'),
            ],
        ],
    ],
],
```

## Verification

### Test Your Installation

Create a simple test to verify everything is working:

```php
<?php

use JTD\LaravelAI\Facades\AI;

// Test basic functionality
$response = AI::conversation('Installation Test')
    ->message('Hello, this is a test message.')
    ->send();

echo $response->content;
```

### Check Provider Status

```bash
php artisan ai:status
```

This command will show:
- Configured providers and their status
- Available models per provider
- Recent usage statistics
- Cost tracking status

### Run Tests

```bash
# Run package tests
php artisan test packages/jerthedev/laravel-ai/tests

# Or if using PHPUnit directly
./vendor/bin/phpunit packages/jerthedev/laravel-ai/tests
```

## Troubleshooting

### Common Issues

#### 1. API Key Issues
```bash
# Verify your API keys
php artisan ai:verify-credentials
```

#### 2. Migration Issues
```bash
# Reset and re-run migrations
php artisan migrate:rollback --path=packages/jerthedev/laravel-ai/database/migrations
php artisan migrate --path=packages/jerthedev/laravel-ai/database/migrations
```

#### 3. Model Sync Issues
```bash
# Force model sync
php artisan ai:sync-models --force

# Check sync logs
php artisan ai:sync-models --verbose
```

#### 4. Permission Issues
```bash
# Ensure proper file permissions
chmod -R 755 storage/
chmod -R 755 bootstrap/cache/
```

### Debug Mode

Enable debug mode for detailed logging:

```env
AI_DEBUG=true
LOG_LEVEL=debug
```

### Getting Help

If you encounter issues:

1. Check the [troubleshooting guide](TROUBLESHOOTING.md)
2. Review the [FAQ](FAQ.md)
3. Search existing [GitHub issues](https://github.com/jerthedev/laravel-ai/issues)
4. Create a new issue with detailed information

## Next Steps

After successful installation:

1. [Configure your providers](PROVIDERS.md)
2. [Set up conversations](CONVERSATIONS.md)
3. [Enable cost tracking](COST_ANALYTICS.md)
4. [Explore MCP integration](MCP_INTEGRATION.md)

## Upgrading

When upgrading to a new version:

```bash
# Update the package
composer update jerthedev/laravel-ai

# Publish new migrations (if any)
php artisan vendor:publish --provider="JTD\LaravelAI\LaravelAIServiceProvider" --tag="migrations" --force

# Run new migrations
php artisan migrate

# Sync models to get latest updates
php artisan ai:sync-models
```

## Uninstallation

To completely remove the package:

```bash
# Remove package
composer remove jerthedev/laravel-ai

# Drop tables (optional - this will delete all data)
php artisan migrate:rollback --path=packages/jerthedev/laravel-ai/database/migrations

# Remove configuration file
rm config/ai.php
```
