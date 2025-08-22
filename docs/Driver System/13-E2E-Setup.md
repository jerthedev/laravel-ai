# E2E Testing Setup

## Overview

End-to-End (E2E) testing validates the complete functionality of AI drivers using real API endpoints. This guide explains how to set up E2E testing with proper credential management, test organization, and best practices.

## Credential Management

### E2E Credentials File

Create a git-excluded credentials file for real API keys:

```bash
# Create the credentials directory
mkdir -p tests/credentials

# Create the credentials file
touch tests/credentials/e2e-credentials.json

# Ensure it's git-excluded
echo "tests/credentials/e2e-credentials.json" >> .gitignore
```

### Credentials File Structure

```json
{
  "openai": {
    "api_key": "sk-your-real-openai-key-here",
    "organization": "org-your-organization-id",
    "project": "proj-your-project-id"
  },
  "gemini": {
    "api_key": "your-real-gemini-key-here"
  },
  "xai": {
    "api_key": "your-real-xai-key-here"
  },
  "anthropic": {
    "api_key": "your-real-anthropic-key-here"
  }
}
```

### Security Considerations

- **Never commit credentials**: Ensure the credentials file is in `.gitignore`
- **Use test accounts**: Use separate API accounts for testing when possible
- **Limit permissions**: Use API keys with minimal required permissions
- **Monitor usage**: Monitor API usage to detect unexpected costs
- **Rotate keys**: Regularly rotate API keys used for testing

## E2E Test Base Classes

### Base E2E Test Case

```php
<?php

namespace Tests\Support;

use Tests\TestCase;
use Tests\Support\Traits\HasE2ECredentials;

abstract class E2ETestCase extends TestCase
{
    use HasE2ECredentials;

    protected string $provider;
    protected array $originalConfig = [];

    protected function setUp(): void
    {
        parent::setUp();
        
        if (!$this->hasE2ECredentials($this->provider)) {
            $this->markTestSkipped("E2E credentials not available for {$this->provider}");
        }
        
        $this->backupOriginalConfig();
        $this->overrideConfigWithE2ECredentials($this->provider);
        $this->setupE2EEnvironment();
    }

    protected function tearDown(): void
    {
        $this->cleanupE2EResources();
        $this->restoreOriginalConfig();
        
        parent::tearDown();
    }

    protected function backupOriginalConfig(): void
    {
        $this->originalConfig = config("ai.providers.{$this->provider}", []);
    }

    protected function restoreOriginalConfig(): void
    {
        config(["ai.providers.{$this->provider}" => $this->originalConfig]);
    }

    protected function setupE2EEnvironment(): void
    {
        // Enable detailed logging for E2E tests
        config([
            'ai.logging.enabled' => true,
            'ai.logging.level' => 'debug',
            'ai.logging.include_content' => false, // Don't log sensitive content
        ]);
    }

    protected function cleanupE2EResources(): void
    {
        // Override in specific test classes if needed
        // Clean up any resources created during tests
    }

    protected function getTestMessage(): string
    {
        return 'This is a test message for E2E testing. Please respond with "E2E test successful".';
    }

    protected function assertValidE2EResponse($response): void
    {
        $this->assertNotEmpty($response->content);
        $this->assertEquals('assistant', $response->role);
        $this->assertNotNull($response->usage);
        $this->assertGreaterThan(0, $response->usage->totalTokens);
        $this->assertNotEmpty($response->model);
    }
}
```

### Provider-Specific Base Classes

```php
<?php

namespace Tests\Support;

abstract class OpenAIE2ETestCase extends E2ETestCase
{
    protected string $provider = 'openai';

    protected function getTestModel(): string
    {
        return 'gpt-3.5-turbo'; // Use cheaper model for testing
    }

    protected function assertOpenAIResponse($response): void
    {
        $this->assertValidE2EResponse($response);
        $this->assertStringStartsWith('gpt-', $response->model);
    }
}

abstract class GeminiE2ETestCase extends E2ETestCase
{
    protected string $provider = 'gemini';

    protected function getTestModel(): string
    {
        return 'gemini-pro';
    }

    protected function assertGeminiResponse($response): void
    {
        $this->assertValidE2EResponse($response);
        $this->assertStringContains('gemini', strtolower($response->model));
    }
}
```

## E2E Credentials Trait

```php
<?php

namespace Tests\Support\Traits;

trait HasE2ECredentials
{
    protected function hasE2ECredentials(string $provider = null): bool
    {
        $credentialsFile = $this->getCredentialsFilePath();
        
        if (!file_exists($credentialsFile)) {
            return false;
        }
        
        $credentials = $this->loadCredentials();
        
        if ($provider) {
            return isset($credentials[$provider]['api_key']) && 
                   !empty($credentials[$provider]['api_key']);
        }
        
        // Check if any provider has credentials
        foreach ($credentials as $providerCredentials) {
            if (isset($providerCredentials['api_key']) && 
                !empty($providerCredentials['api_key'])) {
                return true;
            }
        }
        
        return false;
    }

    protected function overrideConfigWithE2ECredentials(string $provider = null): void
    {
        $credentials = $this->loadCredentials();
        
        if ($provider) {
            $this->overrideProviderConfig($provider, $credentials[$provider] ?? []);
        } else {
            foreach ($credentials as $providerName => $providerCredentials) {
                $this->overrideProviderConfig($providerName, $providerCredentials);
            }
        }
    }

    protected function overrideProviderConfig(string $provider, array $credentials): void
    {
        if (empty($credentials) || empty($credentials['api_key'])) {
            return;
        }

        // Merge with existing config, prioritizing E2E credentials
        config([
            "ai.providers.{$provider}" => array_merge(
                config("ai.providers.{$provider}", []),
                $credentials,
                [
                    // Override specific settings for E2E testing
                    'timeout' => 60, // Longer timeout for E2E tests
                    'retry_attempts' => 1, // Fewer retries to fail fast
                ]
            )
        ]);
    }

    protected function loadCredentials(): array
    {
        $credentialsFile = $this->getCredentialsFilePath();
        
        if (!file_exists($credentialsFile)) {
            return [];
        }
        
        $content = file_get_contents($credentialsFile);
        $credentials = json_decode($content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Invalid JSON in E2E credentials file: ' . json_last_error_msg());
        }
        
        return $credentials ?? [];
    }

    protected function getCredentialsFilePath(): string
    {
        return base_path('tests/credentials/e2e-credentials.json');
    }

    protected function getAvailableE2EProviders(): array
    {
        $credentials = $this->loadCredentials();
        $providers = [];
        
        foreach ($credentials as $provider => $config) {
            if (isset($config['api_key']) && !empty($config['api_key'])) {
                $providers[] = $provider;
            }
        }
        
        return $providers;
    }
}
```

## E2E Test Examples

### OpenAI E2E Tests

```php
<?php

namespace Tests\E2E\Drivers;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\OpenAIE2ETestCase;
use JTD\LaravelAI\Facades\AI;
use JTD\LaravelAI\Models\AIMessage;

#[Group('e2e')]
#[Group('openai')]
class OpenAIDriverE2ETest extends OpenAIE2ETestCase
{
    #[Test]
    public function it_sends_message_to_real_openai_api(): void
    {
        $response = AI::provider('openai')->sendMessage(
            AIMessage::user($this->getTestMessage()),
            ['model' => $this->getTestModel()]
        );

        $this->assertOpenAIResponse($response);
        $this->assertStringContainsString('E2E test successful', $response->content);
    }

    #[Test]
    public function it_handles_conversation_context(): void
    {
        $messages = [
            AIMessage::system('You are a helpful assistant. Always end responses with "Context maintained."'),
            AIMessage::user('What is 2+2?'),
            AIMessage::assistant('2+2 equals 4. Context maintained.'),
            AIMessage::user('What about 3+3?'),
        ];

        $response = AI::provider('openai')->sendMessages(
            $messages,
            ['model' => $this->getTestModel()]
        );

        $this->assertOpenAIResponse($response);
        $this->assertStringContainsString('6', $response->content);
        $this->assertStringContainsString('Context maintained', $response->content);
    }

    #[Test]
    public function it_streams_responses_from_real_api(): void
    {
        $chunks = [];
        $stream = AI::provider('openai')->sendStreamingMessage(
            AIMessage::user('Count from 1 to 3, one number per line.'),
            ['model' => $this->getTestModel()]
        );

        foreach ($stream as $chunk) {
            $chunks[] = $chunk->content;
            
            // Safety limit to prevent runaway tests
            if (count($chunks) > 100) {
                break;
            }
        }

        $this->assertNotEmpty($chunks);
        $this->assertGreaterThan(3, count($chunks)); // Should have multiple chunks
        
        $fullResponse = implode('', $chunks);
        $this->assertStringContainsString('1', $fullResponse);
        $this->assertStringContainsString('2', $fullResponse);
        $this->assertStringContainsString('3', $fullResponse);
    }

    #[Test]
    public function it_syncs_models_from_real_api(): void
    {
        $result = AI::provider('openai')->syncModels(true);

        $this->assertEquals('success', $result['status']);
        $this->assertGreaterThan(0, $result['models_synced']);
        $this->assertArrayHasKey('statistics', $result);
        
        $stats = $result['statistics'];
        $this->assertArrayHasKey('total_models', $stats);
        $this->assertGreaterThan(0, $stats['total_models']);
    }

    #[Test]
    public function it_validates_credentials_with_real_api(): void
    {
        $validation = AI::provider('openai')->validateCredentials();

        $this->assertEquals('valid', $validation['status']);
        $this->assertArrayHasKey('models_available', $validation);
        $this->assertGreaterThan(0, $validation['models_available']);
    }

    #[Test]
    public function it_calculates_real_costs(): void
    {
        $response = AI::provider('openai')->sendMessage(
            AIMessage::user('Short test message for cost calculation.'),
            ['model' => $this->getTestModel()]
        );

        $cost = AI::provider('openai')->calculateCost($response->usage, $response->model);

        $this->assertGreaterThan(0, $cost);
        $this->assertLessThan(0.01, $cost); // Should be very small for short message
    }

    protected function cleanupE2EResources(): void
    {
        // OpenAI doesn't require cleanup for basic message sending
        // If you create any persistent resources, clean them up here
    }
}
```

### Multi-Provider E2E Tests

```php
<?php

namespace Tests\E2E\Workflows;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\E2ETestCase;
use JTD\LaravelAI\Facades\AI;
use JTD\LaravelAI\Models\AIMessage;

#[Group('e2e')]
#[Group('multi-provider')]
class MultiProviderE2ETest extends E2ETestCase
{
    protected string $provider = 'openai'; // Default provider

    #[Test]
    public function it_works_with_all_available_providers(): void
    {
        $availableProviders = $this->getAvailableE2EProviders();
        
        if (empty($availableProviders)) {
            $this->markTestSkipped('No E2E providers available');
        }

        foreach ($availableProviders as $provider) {
            $this->overrideConfigWithE2ECredentials($provider);
            
            $response = AI::provider($provider)->sendMessage(
                AIMessage::user("Test message for {$provider}")
            );

            $this->assertValidE2EResponse($response);
            $this->assertStringContainsString($provider, strtolower($response->model));
        }
    }

    #[Test]
    public function it_handles_provider_fallback(): void
    {
        $availableProviders = $this->getAvailableE2EProviders();
        
        if (count($availableProviders) < 2) {
            $this->markTestSkipped('Need at least 2 providers for fallback testing');
        }

        // Configure multiple providers
        foreach ($availableProviders as $provider) {
            $this->overrideConfigWithE2ECredentials($provider);
        }

        // Test fallback logic (implementation depends on your fallback strategy)
        $response = $this->sendMessageWithFallback('Test fallback message');
        
        $this->assertNotEmpty($response);
    }

    protected function sendMessageWithFallback(string $message): string
    {
        $providers = $this->getAvailableE2EProviders();
        
        foreach ($providers as $provider) {
            try {
                $response = AI::provider($provider)->sendMessage(
                    AIMessage::user($message)
                );
                
                return $response->content;
            } catch (\Exception $e) {
                // Log and continue to next provider
                \Log::warning("Provider {$provider} failed: " . $e->getMessage());
                continue;
            }
        }
        
        throw new \Exception('All providers failed');
    }
}
```

## CI/CD Integration

### GitHub Actions E2E Workflow

```yaml
name: E2E Tests

on:
  schedule:
    - cron: '0 2 * * *' # Run daily at 2 AM
  workflow_dispatch: # Allow manual triggering

jobs:
  e2e-tests:
    runs-on: ubuntu-latest
    
    steps:
    - uses: actions/checkout@v3
    
    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: 8.2
        extensions: mbstring, xml, ctype, iconv, intl, pdo_sqlite
    
    - name: Install dependencies
      run: composer install --no-progress --prefer-dist --optimize-autoloader
    
    - name: Create E2E credentials file
      run: |
        mkdir -p tests/credentials
        echo '${{ secrets.E2E_CREDENTIALS }}' > tests/credentials/e2e-credentials.json
    
    - name: Run E2E tests
      run: vendor/bin/phpunit --testsuite=E2E --group=e2e
      env:
        APP_ENV: testing
    
    - name: Clean up credentials
      if: always()
      run: rm -f tests/credentials/e2e-credentials.json
    
    - name: Upload test results
      if: failure()
      uses: actions/upload-artifact@v3
      with:
        name: e2e-test-results
        path: tests/results/
```

### Environment Variables for CI

```bash
# GitHub Secrets (JSON format)
E2E_CREDENTIALS='{
  "openai": {
    "api_key": "sk-real-key-here",
    "organization": "org-id"
  },
  "gemini": {
    "api_key": "real-gemini-key"
  }
}'
```

## Best Practices

### Test Design
- **Use cheap models**: Use the least expensive models for E2E testing
- **Keep messages short**: Minimize token usage and costs
- **Test core functionality**: Focus on essential features, not edge cases
- **Set timeouts**: Use longer timeouts for real API calls
- **Limit test scope**: Don't test every possible scenario in E2E tests

### Credential Management
- **Separate test accounts**: Use dedicated API accounts for testing
- **Monitor costs**: Track API usage and costs from E2E tests
- **Rotate keys regularly**: Change API keys periodically
- **Use minimal permissions**: Grant only necessary permissions to test keys
- **Document requirements**: Document what credentials are needed

### Error Handling
- **Expect failures**: E2E tests may fail due to external factors
- **Retry transient failures**: Implement retry logic for network issues
- **Skip when unavailable**: Skip tests when credentials are missing
- **Log detailed errors**: Capture detailed error information for debugging
- **Clean up on failure**: Ensure resources are cleaned up even when tests fail

### Performance
- **Run selectively**: Don't run E2E tests on every commit
- **Parallel execution**: Run E2E tests for different providers in parallel
- **Cache when possible**: Cache expensive setup operations
- **Monitor execution time**: Track and optimize test execution time
- **Rate limit awareness**: Respect API rate limits

## Troubleshooting

### Common Issues

#### Credentials Not Found
```bash
# Check if credentials file exists
ls -la tests/credentials/

# Validate JSON format
cat tests/credentials/e2e-credentials.json | jq .
```

#### API Rate Limits
```php
// Add delays between tests if needed
protected function setUp(): void
{
    parent::setUp();
    
    // Add delay to avoid rate limits
    if (isset($GLOBALS['last_api_call'])) {
        $timeSinceLastCall = time() - $GLOBALS['last_api_call'];
        if ($timeSinceLastCall < 1) {
            sleep(1 - $timeSinceLastCall);
        }
    }
    
    $GLOBALS['last_api_call'] = time();
}
```

#### Network Timeouts
```php
// Increase timeouts for E2E tests
protected function setupE2EEnvironment(): void
{
    parent::setupE2EEnvironment();
    
    config([
        "ai.providers.{$this->provider}.timeout" => 120,
        "ai.providers.{$this->provider}.retry_attempts" => 3,
    ]);
}
```

## Related Documentation

- **[Configuration System](02-Configuration.md)**: Understanding configuration override patterns
- **[Testing Strategy](12-Testing.md)**: Overall testing approach
- **[Mock Provider](08-Mock-Provider.md)**: Alternative to E2E testing
- **[OpenAI Driver](07-OpenAI-Driver.md)**: Example E2E test implementation
