# Sync Commands

## Overview

The `ai:sync-models` command provides a comprehensive interface for synchronizing AI models from all configured providers. It automatically discovers providers with valid credentials and syncs their models to the local cache.

## Command Signature

```bash
php artisan ai:sync-models 
    [--provider=PROVIDER]     # Sync specific provider only
    [--force]                 # Force refresh even if recently synced
    [--dry-run]               # Show what would be synced without syncing
    [--timeout=SECONDS]       # API timeout in seconds (default: 30)
    [-v|--verbose]            # Show detailed output
```

## Usage Examples

### Basic Usage

#### Sync All Providers
```bash
php artisan ai:sync-models
```
Discovers all providers with valid credentials and syncs their models.

#### Sync Specific Provider
```bash
php artisan ai:sync-models --provider=openai
```
Syncs only the specified provider.

#### Force Refresh
```bash
php artisan ai:sync-models --force
```
Forces refresh even if models were recently synced.

### Advanced Usage

#### Dry Run
```bash
php artisan ai:sync-models --dry-run
```
Shows what would be synced without making any changes.

#### Verbose Output
```bash
php artisan ai:sync-models -v
```
Shows detailed statistics and progress information.

#### Custom Timeout
```bash
php artisan ai:sync-models --timeout=60
```
Sets custom API timeout for slow connections.

#### Combined Options
```bash
php artisan ai:sync-models --provider=openai --force --dry-run -v
```
Dry run for OpenAI with force refresh and verbose output.

## Output Examples

### Successful Sync
```
Syncing models from AI providers...

openai:
  ✓ Found 15 models
  ✓ Statistics updated

gemini:
  ✓ Found 8 models
  ✓ Statistics updated

xai:
  ⏭ Skipped (cache_valid)
    Last sync: 2 hours ago

Sync completed successfully!
Total: 23 models synced across 2 providers
```

### Verbose Output
```
Syncing models from AI providers...

openai:
  ✓ Found 15 models
  ✓ Statistics updated
    - Total: 15
    - GPT-4: 5
    - GPT-3.5: 3
    - Function calling: 12
    - Vision: 8

gemini:
  ✓ Found 8 models
  ✓ Statistics updated
    - Total: 8
    - Gemini Pro: 3
    - Gemini Flash: 5
    - Multimodal: 8

Sync completed successfully!
Total: 23 models synced across 2 providers
```

### Dry Run Output
```
DRY RUN - No changes will be made

Syncing models from AI providers...

openai:
  Would sync: 15 models
  Last synced: 6 hours ago
  Models:
    - gpt-4
    - gpt-4-turbo
    - gpt-3.5-turbo
    ... and 12 more

gemini:
  Would sync: 8 models
  Last synced: Never

Dry run completed!
Total: 23 models would be synced across 2 providers
```

### Error Handling
```
Syncing models from AI providers...

openai:
  ✓ Found 15 models

gemini:
  ❌ Synchronization failed: Invalid API key

xai:
  ✓ Found 5 models

Sync completed with errors!
Total: 20 models synced across 2 providers

Errors encountered:
- gemini: Invalid API key
```

## Command Options

### `--provider=PROVIDER`
- **Purpose**: Sync only the specified provider
- **Values**: Any configured provider name (openai, gemini, xai, etc.)
- **Example**: `--provider=openai`

### `--force`
- **Purpose**: Force refresh even if recently synced
- **Use Case**: When you need fresh data regardless of cache status
- **Example**: `--force`

### `--dry-run`
- **Purpose**: Preview what would be synced without making changes
- **Use Case**: Planning, validation, troubleshooting
- **Example**: `--dry-run`

### `--timeout=SECONDS`
- **Purpose**: Set custom API timeout
- **Default**: 30 seconds
- **Use Case**: Slow connections, large model lists
- **Example**: `--timeout=60`

### `-v, --verbose`
- **Purpose**: Show detailed output including statistics
- **Use Case**: Debugging, monitoring, detailed reporting
- **Example**: `-v`

## Exit Codes

- **0**: Success - All providers synced successfully
- **1**: Failure - No providers available or all providers failed
- **2**: Partial success - Some providers failed but others succeeded

## Integration Examples

### Cron Job
```bash
# Sync models every 12 hours
0 */12 * * * cd /path/to/app && php artisan ai:sync-models --quiet
```

### Deployment Script
```bash
#!/bin/bash
# Post-deployment sync
php artisan ai:sync-models --force
```

### Health Check Script
```bash
#!/bin/bash
# Check if sync is needed
php artisan ai:sync-models --dry-run --quiet
if [ $? -eq 0 ]; then
    echo "Models are up to date"
else
    echo "Models need syncing"
    php artisan ai:sync-models
fi
```

### Monitoring Script
```bash
#!/bin/bash
# Detailed sync with logging
php artisan ai:sync-models -v 2>&1 | tee /var/log/ai-sync.log
```

## Provider Discovery Logic

The command automatically discovers providers using this logic:

1. **Get Available Providers**: Query DriverManager for all registered providers
2. **Filter by Credentials**: Check each provider's `hasValidCredentials()` method
3. **Environment Filtering**: Skip mock provider in production environments
4. **Error Handling**: Skip providers that can't be instantiated

### Discovery Example
```php
protected function getAvailableProviders(): array
{
    $providers = [];
    $availableProviders = $this->driverManager->getAvailableProviders();

    foreach ($availableProviders as $providerName) {
        try {
            $driver = $this->driverManager->driver($providerName);
            
            // Skip mock provider in production
            if ($providerName === 'mock' && app()->environment('production')) {
                continue;
            }
            
            // Check if provider has valid credentials
            if ($driver->hasValidCredentials()) {
                $providers[] = $providerName;
            }
        } catch (\Exception $e) {
            // Skip providers that can't be instantiated
            if ($this->getOutput()->isVerbose()) {
                $this->warn("Skipping {$providerName}: " . $e->getMessage());
            }
        }
    }

    return $providers;
}
```

## Error Scenarios

### No Providers Available
```
No providers available for synchronization.
```
**Causes**: No configured providers, all providers have invalid credentials

### Provider Authentication Failed
```
Failed to sync openai: Invalid API key
```
**Causes**: Invalid credentials, expired API keys, network issues

### API Timeout
```
Failed to sync gemini: Request timeout after 30 seconds
```
**Solutions**: Use `--timeout` flag, check network connectivity

### Rate Limiting
```
Failed to sync openai: Rate limit exceeded, retry after 60 seconds
```
**Solutions**: Wait and retry, check rate limit configuration

## Best Practices

### Regular Syncing
- Set up automated syncing every 12-24 hours
- Use `--force` sparingly to avoid unnecessary API calls
- Monitor sync logs for failures

### Error Handling
- Use verbose mode for troubleshooting
- Check provider credentials when sync fails
- Monitor API rate limits and quotas

### Performance
- Use appropriate timeouts for your network
- Consider provider-specific syncing during high-traffic periods
- Monitor sync duration and optimize as needed

### Monitoring
- Log sync results for operational monitoring
- Set up alerts for sync failures
- Track model availability trends

## Related Documentation

- **[Sync System Overview](04-Sync-System.md)**: Understanding the sync architecture
- **[Sync Implementation](05-Sync-Implementation.md)**: How to implement sync in drivers
- **[Configuration System](02-Configuration.md)**: Provider configuration and credentials
- **[Troubleshooting](16-Troubleshooting.md)**: Common sync issues and solutions
