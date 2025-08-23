# Troubleshooting Guide

## Overview

This guide helps diagnose and resolve common issues with AI drivers, including authentication problems, API errors, performance issues, and configuration problems.

## Common Issues

### Authentication and Credentials

#### Invalid API Key
**Symptoms:**
- `InvalidCredentialsException` thrown
- "Incorrect API key provided" error messages
- 401 Unauthorized responses

**Diagnosis:**
```bash
# Check if API key is set
php artisan tinker
>>> config('ai.providers.openai.api_key')

# Validate API key format
>>> $key = config('ai.providers.openai.api_key');
>>> strlen($key); // Should be 51 characters for OpenAI
>>> str_starts_with($key, 'sk-'); // Should be true for OpenAI
```

**Solutions:**
```bash
# 1. Verify API key in .env file
OPENAI_API_KEY=sk-your-actual-key-here

# 2. Clear config cache
php artisan config:clear

# 3. Test credentials
php artisan tinker
>>> use JTD\LaravelAI\Facades\AI;
>>> AI::provider('openai')->validateCredentials();
```

#### Organization/Project ID Issues
**Symptoms:**
- "You must be a member of an organization" errors
- Access denied to specific models

**Solutions:**
```bash
# Add organization and project IDs
OPENAI_ORGANIZATION=org-your-org-id
OPENAI_PROJECT=proj-your-project-id

# Clear cache and test
php artisan config:clear
```

### Rate Limiting

#### Rate Limit Exceeded
**Symptoms:**
- `RateLimitException` thrown
- "Rate limit reached" error messages
- 429 Too Many Requests responses

**Diagnosis:**
```php
// Check current rate limit settings
$config = config('ai.providers.openai.rate_limiting');
var_dump($config);

// Monitor request frequency
\Log::info('Request made', ['timestamp' => now()]);
```

**Solutions:**
```php
// 1. Implement exponential backoff
try {
    $response = AI::sendMessage($message);
} catch (\JTD\LaravelAI\Exceptions\RateLimitException $e) {
    $retryAfter = $e->getRetryAfter();
    sleep($retryAfter);
    $response = AI::sendMessage($message); // Retry
}

// 2. Configure rate limiting
// config/ai.php
'rate_limiting' => [
    'enabled' => true,
    'requests_per_minute' => 60, // Reduce if hitting limits
    'tokens_per_minute' => 40000,
],

// 3. Use request queuing
use Illuminate\Support\Facades\Queue;

Queue::push(function () use ($message) {
    AI::sendMessage($message);
});
```

### Connection Issues

#### Timeout Errors
**Symptoms:**
- Connection timeout exceptions
- "Request timeout after X seconds" errors
- Slow response times

**Diagnosis:**
```bash
# Test network connectivity
curl -I https://api.openai.com/v1/models

# Check DNS resolution
nslookup api.openai.com

# Test with increased timeout
php artisan tinker
>>> config(['ai.providers.openai.timeout' => 120]);
>>> AI::provider('openai')->validateCredentials();
```

**Solutions:**
```php
// 1. Increase timeout settings
'timeout' => env('OPENAI_TIMEOUT', 60), // Increase from 30
'connect_timeout' => env('OPENAI_CONNECT_TIMEOUT', 20),

// 2. Configure retry settings
'retry_attempts' => 5,
'retry_delay' => 2000, // 2 seconds
'max_retry_delay' => 60000, // 60 seconds

// 3. Check proxy settings if behind corporate firewall
'proxy' => env('HTTP_PROXY'),
```

#### SSL/TLS Issues
**Symptoms:**
- SSL certificate verification errors
- "cURL error 60: SSL certificate problem"

**Solutions:**
```php
// Temporary fix (not recommended for production)
'verify' => false,

// Better solution: Update CA certificates
// Ubuntu/Debian:
sudo apt-get update && sudo apt-get install ca-certificates

// CentOS/RHEL:
sudo yum update ca-certificates
```

### Model and Sync Issues

#### Model Sync Failures
**Symptoms:**
- `ai:sync-models` command fails
- "No models available" errors
- Outdated model lists

**Diagnosis:**
```bash
# Check sync status
php artisan ai:sync-models --dry-run -v

# Check cache
php artisan tinker
>>> \Cache::get('laravel-ai:openai:models:last_sync');
>>> \Cache::get('laravel-ai:openai:models:last_failure');

# Clear model cache
>>> \Cache::forget('laravel-ai:openai:models');
>>> \Cache::forget('laravel-ai:openai:models:last_sync');
```

**Solutions:**
```bash
# 1. Force refresh models
php artisan ai:sync-models --force

# 2. Sync specific provider
php artisan ai:sync-models --provider=openai --force

# 3. Check provider credentials
php artisan tinker
>>> AI::provider('openai')->hasValidCredentials();

# 4. Clear all AI-related cache
php artisan cache:clear
```

#### Model Not Found
**Symptoms:**
- "Model not found" errors
- Requests fail with specific model IDs

**Solutions:**
```php
// 1. Check available models
$models = AI::provider('openai')->getAvailableModels(true);
dd($models->pluck('id'));

// 2. Use default model
$response = AI::sendMessage($message); // Uses default model

// 3. Update model configuration
// config/ai.php
'default_model' => 'gpt-3.5-turbo', // Use available model
```

### Performance Issues

#### Slow Response Times
**Symptoms:**
- Requests taking longer than expected
- Timeout errors under load
- High memory usage

**Diagnosis:**
```php
// Enable performance monitoring
\Log::info('Request start', ['memory' => memory_get_usage()]);

$start = microtime(true);
$response = AI::sendMessage($message);
$duration = microtime(true) - $start;

\Log::info('Request complete', [
    'duration_ms' => $duration * 1000,
    'memory' => memory_get_usage(),
    'peak_memory' => memory_get_peak_usage(),
]);
```

**Solutions:**
```php
// 1. Enable caching
'cache_models' => true,
'cache_responses' => true,

// 2. Optimize connection settings
'max_connections' => 10,
'connection_pool_size' => 5,

// 3. Use appropriate models
$response = AI::sendMessage($message, [
    'model' => 'gpt-3.5-turbo', // Faster than gpt-4
]);

// 4. Implement request queuing for non-urgent requests
Queue::push(new ProcessAIRequest($message));
```

#### Memory Leaks
**Symptoms:**
- Increasing memory usage over time
- "Allowed memory size exhausted" errors

**Solutions:**
```php
// 1. Implement garbage collection
public function sendMessage(AIMessage $message): AIResponse
{
    $response = $this->performSendMessage($message);
    
    // Clean up large objects
    unset($message);
    gc_collect_cycles();
    
    return $response;
}

// 2. Use streaming for large responses
foreach (AI::sendStreamingMessage($message) as $chunk) {
    echo $chunk->content;
    unset($chunk); // Clean up each chunk
}

// 3. Monitor memory usage
if (memory_get_usage() > 100 * 1024 * 1024) { // 100MB
    \Log::warning('High memory usage detected');
    gc_collect_cycles();
}
```

### Configuration Issues

#### Environment Variables Not Loading
**Symptoms:**
- Default values used instead of environment variables
- Configuration not updating after changes

**Diagnosis:**
```bash
# Check if .env file exists and is readable
ls -la .env

# Verify environment variables
php artisan tinker
>>> env('OPENAI_API_KEY');
>>> $_ENV['OPENAI_API_KEY'] ?? 'not set';

# Check config values
>>> config('ai.providers.openai.api_key');
```

**Solutions:**
```bash
# 1. Clear configuration cache
php artisan config:clear

# 2. Verify .env file format (no spaces around =)
OPENAI_API_KEY=sk-your-key-here

# 3. Restart web server/queue workers
sudo systemctl restart nginx
php artisan queue:restart

# 4. Check file permissions
chmod 644 .env
```

#### Cache Issues
**Symptoms:**
- Old configuration values persist
- Changes not taking effect

**Solutions:**
```bash
# Clear all caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# For production, rebuild optimized caches
php artisan config:cache
php artisan route:cache
```

## Debugging Tools

### Logging Configuration

#### Enable Debug Logging
```php
// config/ai.php
'logging' => [
    'enabled' => true,
    'level' => 'debug',
    'include_content' => true, // Be careful with sensitive data
    'channel' => 'ai-debug',
],

// config/logging.php
'channels' => [
    'ai-debug' => [
        'driver' => 'daily',
        'path' => storage_path('logs/ai-debug.log'),
        'level' => 'debug',
        'days' => 7,
    ],
],
```

#### Custom Debug Commands
```php
// Create debug command
php artisan make:command DebugAIProvider

// app/Console/Commands/DebugAIProvider.php
public function handle()
{
    $provider = $this->argument('provider') ?? 'openai';
    
    $this->info("Debugging AI Provider: {$provider}");
    
    // Test configuration
    $config = config("ai.providers.{$provider}");
    $this->table(['Key', 'Value'], collect($config)->map(function ($value, $key) {
        return [$key, is_array($value) ? json_encode($value) : $value];
    }));
    
    // Test credentials
    try {
        $validation = AI::provider($provider)->validateCredentials();
        $this->info("Credentials: " . $validation['status']);
    } catch (\Exception $e) {
        $this->error("Credential validation failed: " . $e->getMessage());
    }
    
    // Test model sync
    try {
        $result = AI::provider($provider)->syncModels(true);
        $this->info("Models synced: " . $result['models_synced']);
    } catch (\Exception $e) {
        $this->error("Model sync failed: " . $e->getMessage());
    }
    
    // Test basic request
    try {
        $response = AI::provider($provider)->sendMessage(
            \JTD\LaravelAI\Models\AIMessage::user('Test message')
        );
        $this->info("Test message successful");
        $this->info("Response length: " . strlen($response->content));
    } catch (\Exception $e) {
        $this->error("Test message failed: " . $e->getMessage());
    }
}
```

### Health Check Endpoints

#### Create Health Check Route
```php
// routes/web.php or routes/api.php
Route::get('/health/ai', function () {
    $results = [];
    
    foreach (config('ai.providers') as $name => $config) {
        try {
            $driver = AI::provider($name);
            $results[$name] = $driver->checkHealth();
        } catch (\Exception $e) {
            $results[$name] = [
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }
    }
    
    $overallStatus = collect($results)->every(fn($result) => $result['status'] === 'healthy')
        ? 'healthy' : 'unhealthy';
    
    return response()->json([
        'status' => $overallStatus,
        'providers' => $results,
        'timestamp' => now()->toISOString(),
    ], $overallStatus === 'healthy' ? 200 : 503);
});
```

## Error Recovery

### Automatic Recovery Strategies

#### Circuit Breaker Pattern
```php
class CircuitBreaker
{
    protected int $failureThreshold = 5;
    protected int $recoveryTimeout = 60; // seconds
    protected string $state = 'closed'; // closed, open, half-open
    protected int $failureCount = 0;
    protected ?int $lastFailureTime = null;
    
    public function call(callable $callback)
    {
        if ($this->state === 'open') {
            if ($this->shouldAttemptReset()) {
                $this->state = 'half-open';
            } else {
                throw new \Exception('Circuit breaker is open');
            }
        }
        
        try {
            $result = $callback();
            $this->onSuccess();
            return $result;
        } catch (\Exception $e) {
            $this->onFailure();
            throw $e;
        }
    }
    
    protected function onSuccess(): void
    {
        $this->failureCount = 0;
        $this->state = 'closed';
    }
    
    protected function onFailure(): void
    {
        $this->failureCount++;
        $this->lastFailureTime = time();
        
        if ($this->failureCount >= $this->failureThreshold) {
            $this->state = 'open';
        }
    }
    
    protected function shouldAttemptReset(): bool
    {
        return $this->lastFailureTime && 
               (time() - $this->lastFailureTime) >= $this->recoveryTimeout;
    }
}
```

#### Fallback Providers
```php
class FallbackService
{
    protected array $providers = ['openai', 'gemini', 'xai'];
    
    public function sendMessageWithFallback(AIMessage $message): AIResponse
    {
        $lastException = null;
        
        foreach ($this->providers as $provider) {
            try {
                return AI::provider($provider)->sendMessage($message);
            } catch (\Exception $e) {
                $lastException = $e;
                \Log::warning("Provider {$provider} failed, trying next", [
                    'error' => $e->getMessage(),
                ]);
                continue;
            }
        }
        
        throw new \Exception('All providers failed', 0, $lastException);
    }
}
```

## Monitoring and Alerting

### Set Up Monitoring
```php
// Monitor key metrics
class AIMonitor
{
    public function checkSystemHealth(): array
    {
        return [
            'providers' => $this->checkProviders(),
            'cache' => $this->checkCache(),
            'database' => $this->checkDatabase(),
            'memory' => $this->checkMemoryUsage(),
            'performance' => $this->checkPerformance(),
        ];
    }
    
    protected function checkProviders(): array
    {
        $results = [];
        
        foreach (config('ai.providers') as $name => $config) {
            $results[$name] = [
                'credentials_valid' => AI::provider($name)->hasValidCredentials(),
                'last_sync' => AI::provider($name)->getLastSyncTime(),
                'model_count' => AI::provider($name)->getAvailableModels()->count(),
            ];
        }
        
        return $results;
    }
    
    protected function checkCache(): array
    {
        return [
            'redis_connected' => \Cache::store('redis')->getRedis()->ping(),
            'cache_size' => $this->getCacheSize(),
        ];
    }
}
```

### Alert Configuration
```php
// Set up alerts for critical issues
class AIAlerts
{
    public function checkAndAlert(): void
    {
        // Check provider health
        foreach (config('ai.providers') as $name => $config) {
            if (!AI::provider($name)->hasValidCredentials()) {
                $this->sendAlert("Provider {$name} has invalid credentials");
            }
        }
        
        // Check error rates
        $errorRate = $this->getErrorRate();
        if ($errorRate > 0.1) { // 10% error rate
            $this->sendAlert("High error rate detected: {$errorRate}%");
        }
        
        // Check response times
        $avgResponseTime = $this->getAverageResponseTime();
        if ($avgResponseTime > 10000) { // 10 seconds
            $this->sendAlert("Slow response times: {$avgResponseTime}ms");
        }
    }
    
    protected function sendAlert(string $message): void
    {
        // Send to Slack, email, or monitoring service
        \Log::critical('AI System Alert', ['message' => $message]);
        
        // Example: Send to Slack
        // \Notification::route('slack', config('alerts.slack_webhook'))
        //     ->notify(new AIAlert($message));
    }
}
```

## Getting Help

### Support Channels
1. **Documentation**: Check this documentation first
2. **GitHub Issues**: Report bugs and feature requests
3. **Community Forum**: Ask questions and share solutions
4. **Stack Overflow**: Tag questions with `laravel-ai`

### Reporting Issues
When reporting issues, include:
- Laravel version
- PHP version
- Package version
- Provider and model being used
- Complete error message and stack trace
- Minimal code example to reproduce
- Configuration (with sensitive data masked)

### Useful Commands
```bash
# System information
php --version
php artisan --version
composer show jtd/laravel-ai

# Debug information
php artisan ai:debug openai
php artisan config:show ai
php artisan cache:clear

# Test connectivity
curl -I https://api.openai.com/v1/models
ping api.openai.com
```

## Related Documentation

- **[Configuration System](02-Configuration.md)**: Configuration troubleshooting
- **[Error Handling](11-Error-Handling.md)**: Understanding error types
- **[Performance](15-Performance.md)**: Performance optimization
- **[Testing Strategy](12-Testing.md)**: Testing and debugging approaches
