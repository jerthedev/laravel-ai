# Testing Strategy

## Overview

The JTD Laravel AI package implements a comprehensive testing strategy that covers unit tests, integration tests, and end-to-end tests. This multi-layered approach ensures reliability, performance, and compatibility across different AI providers.

## Testing Pyramid

### Unit Tests (70%)
- **Scope**: Individual classes, methods, and traits
- **Speed**: Very fast (< 1ms per test)
- **Dependencies**: Mocked or stubbed
- **Purpose**: Verify individual components work correctly

### Integration Tests (20%)
- **Scope**: Multiple components working together
- **Speed**: Fast (< 100ms per test)
- **Dependencies**: Real Laravel services, mocked external APIs
- **Purpose**: Verify components integrate correctly

### End-to-End Tests (10%)
- **Scope**: Complete workflows with real APIs
- **Speed**: Slow (1-10 seconds per test)
- **Dependencies**: Real AI provider APIs
- **Purpose**: Verify real-world functionality

## Test Organization

### Directory Structure

```
tests/
├── Unit/
│   ├── Drivers/
│   │   ├── OpenAI/
│   │   │   ├── Traits/
│   │   │   │   ├── HandlesApiCommunicationTest.php
│   │   │   │   ├── ManagesModelsTest.php
│   │   │   │   └── CalculatesCostsTest.php
│   │   │   └── OpenAIDriverTest.php
│   │   └── MockProviderTest.php
│   ├── Services/
│   │   ├── DriverManagerTest.php
│   │   └── AIManagerTest.php
│   ├── Models/
│   │   ├── AIMessageTest.php
│   │   └── AIResponseTest.php
│   └── Console/
│       └── Commands/
│           └── SyncModelsCommandTest.php
├── Integration/
│   ├── DriverIntegrationTest.php
│   ├── SyncSystemIntegrationTest.php
│   └── EventSystemIntegrationTest.php
├── E2E/
│   ├── Drivers/
│   │   ├── OpenAIDriverE2ETest.php
│   │   ├── GeminiDriverE2ETest.php
│   │   └── XAIDriverE2ETest.php
│   └── Workflows/
│       ├── ConversationWorkflowE2ETest.php
│       └── StreamingWorkflowE2ETest.php
└── Support/
    ├── TestCase.php
    ├── E2ETestCase.php
    └── Traits/
        ├── HasE2ECredentials.php
        └── MocksAIResponses.php
```

## Unit Testing

### Testing Individual Traits

```php
<?php

namespace Tests\Unit\Drivers\OpenAI\Traits;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;
use JTD\LaravelAI\Drivers\OpenAI\Traits\CalculatesCosts;
use JTD\LaravelAI\Models\TokenUsage;
use Mockery;

#[Group('unit')]
#[Group('openai')]
#[Group('costs')]
class CalculatesCostsTest extends TestCase
{
    use CalculatesCosts;

    protected string $name = 'test-provider';

    #[Test]
    public function it_calculates_cost_for_gpt_4(): void
    {
        $usage = new TokenUsage(
            promptTokens: 1000,
            completionTokens: 500,
            totalTokens: 1500
        );

        $cost = $this->calculateCost($usage, 'gpt-4');

        // GPT-4 pricing: $0.03/1K prompt, $0.06/1K completion
        // Expected: (1000/1000 * 0.03) + (500/1000 * 0.06) = 0.03 + 0.03 = 0.06
        $this->assertEquals(0.06, $cost);
    }

    #[Test]
    public function it_handles_unknown_model_gracefully(): void
    {
        $usage = new TokenUsage(100, 50, 150);

        $cost = $this->calculateCost($usage, 'unknown-model');

        $this->assertEquals(0.0, $cost);
    }

    public function getName(): string
    {
        return $this->name;
    }
}
```

### Testing Driver Classes

```php
<?php

namespace Tests\Unit\Drivers\OpenAI;

use JTD\LaravelAI\Drivers\OpenAI\OpenAIDriver;use PHPUnit\Framework\Attributes\Group;use PHPUnit\Framework\Attributes\Test;use Tests\TestCase;

#[Group('unit')]
#[Group('openai')]
class OpenAIDriverTest extends TestCase
{
    protected OpenAIDriver $driver;
    protected array $config;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = [
            'api_key' => 'sk-test-key-1234567890abcdef1234567890abcdef',
            'organization' => 'org-test',
            'project' => 'proj-test',
        ];
    }

    #[Test]
    public function it_initializes_with_valid_config(): void
    {
        $driver = new OpenAIDriver($this->config);

        $this->assertEquals('openai', $driver->getName());
        $this->assertTrue($driver->supportsStreaming());
        $this->assertTrue($driver->supportsFunctionCalling());
    }

    #[Test]
    public function it_validates_required_config(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required config: api_key');

        new OpenAIDriver(['organization' => 'org-test']);
    }

    #[Test]
    public function it_masks_sensitive_config(): void
    {
        $driver = new OpenAIDriver($this->config);

        $maskedConfig = $driver->getConfig();

        $this->assertStringStartsWith('sk-***', $maskedConfig['api_key']);
        $this->assertStringEndsWith('cdef', $maskedConfig['api_key']);
    }
}
```

### Testing Services

```php
<?php

namespace Tests\Unit\Services;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;
use JTD\LaravelAI\Services\DriverManager;
use JTD\LaravelAI\Contracts\AIProviderInterface;
use Mockery;

#[Group('unit')]
#[Group('services')]
class DriverManagerTest extends TestCase
{
    protected DriverManager $driverManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->driverManager = new DriverManager($this->app);
    }

    #[Test]
    public function it_creates_openai_driver(): void
    {
        $config = [
            'api_key' => 'sk-test-key-1234567890abcdef1234567890abcdef',
        ];

        $driver = $this->driverManager->driver('openai', $config);

        $this->assertInstanceOf(AIProviderInterface::class, $driver);
        $this->assertEquals('openai', $driver->getName());
    }

    #[Test]
    public function it_throws_exception_for_unknown_driver(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Driver [unknown] not supported');

        $this->driverManager->driver('unknown');
    }

    #[Test]
    public function it_gets_available_providers(): void
    {
        $providers = $this->driverManager->getAvailableProviders();

        $this->assertContains('openai', $providers);
        $this->assertContains('mock', $providers);
    }
}
```

## Integration Testing

### Testing Component Integration

```php
<?php

namespace Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;
use JTD\LaravelAI\Facades\AI;
use JTD\LaravelAI\Models\AIMessage;
use Illuminate\Support\Facades\Event;
use JTD\LaravelAI\Events\MessageSent;

#[Group('integration')]
class DriverIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Use mock provider for integration tests
        config(['ai.default' => 'mock']);
    }

    #[Test]
    public function it_sends_message_and_fires_events(): void
    {
        Event::fake();

        $response = AI::sendMessage(
            AIMessage::user('Hello, AI!')
        );

        $this->assertNotEmpty($response->content);
        $this->assertEquals('assistant', $response->role);

        Event::assertDispatched(MessageSent::class);
    }

    #[Test]
    public function it_calculates_costs_correctly(): void
    {
        $response = AI::sendMessage(
            AIMessage::user('Calculate cost for this message')
        );

        $this->assertNotNull($response->usage);
        $this->assertGreaterThan(0, $response->usage->totalTokens);
        
        $cost = AI::provider()->calculateCost($response->usage, $response->model);
        $this->assertGreaterThan(0, $cost);
    }

    #[Test]
    public function it_handles_streaming_responses(): void
    {
        $chunks = [];
        
        $stream = AI::sendStreamingMessage(
            AIMessage::user('Stream this response')
        );

        foreach ($stream as $chunk) {
            $chunks[] = $chunk->content;
        }

        $this->assertNotEmpty($chunks);
        $this->assertIsString($chunks[0]);
    }
}
```

### Testing Sync System Integration

```php
<?php

namespace Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

#[Group('integration')]
#[Group('sync')]
class SyncSystemIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    #[Test]
    public function it_syncs_models_via_command(): void
    {
        $exitCode = Artisan::call('ai:sync-models', ['--provider' => 'mock']);

        $this->assertEquals(0, $exitCode);
        
        $output = Artisan::output();
        $this->assertStringContainsString('Sync completed successfully', $output);
        $this->assertStringContainsString('mock:', $output);
    }

    #[Test]
    public function it_performs_dry_run_sync(): void
    {
        $exitCode = Artisan::call('ai:sync-models', [
            '--provider' => 'mock',
            '--dry-run' => true
        ]);

        $this->assertEquals(0, $exitCode);
        
        $output = Artisan::output();
        $this->assertStringContainsString('DRY RUN', $output);
        $this->assertStringContainsString('Would sync:', $output);
    }
}
```

## End-to-End Testing

### E2E Test Base Class

```php
<?php

namespace Tests\Support;

use Tests\TestCase;
use Tests\Support\Traits\HasE2ECredentials;

abstract class E2ETestCase extends TestCase
{
    use HasE2ECredentials;

    protected function setUp(): void
    {
        parent::setUp();
        
        if (!$this->hasE2ECredentials()) {
            $this->markTestSkipped('E2E credentials not available');
        }
        
        $this->overrideConfigWithE2ECredentials();
    }

    protected function tearDown(): void
    {
        // Clean up any resources created during E2E tests
        $this->cleanupE2EResources();
        
        parent::tearDown();
    }

    protected function cleanupE2EResources(): void
    {
        // Override in specific test classes if needed
    }
}
```

### E2E Credentials Trait

```php
<?php

namespace Tests\Support\Traits;

trait HasE2ECredentials
{
    protected function hasE2ECredentials(string $provider = null): bool
    {
        $credentialsFile = base_path('tests/credentials/e2e-credentials.json');
        
        if (!file_exists($credentialsFile)) {
            return false;
        }
        
        $credentials = json_decode(file_get_contents($credentialsFile), true);
        
        if ($provider) {
            return isset($credentials[$provider]['api_key']);
        }
        
        // Check if any provider has credentials
        foreach ($credentials as $providerCredentials) {
            if (isset($providerCredentials['api_key'])) {
                return true;
            }
        }
        
        return false;
    }

    protected function overrideConfigWithE2ECredentials(string $provider = null): void
    {
        $credentialsFile = base_path('tests/credentials/e2e-credentials.json');
        $credentials = json_decode(file_get_contents($credentialsFile), true);
        
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
        if (empty($credentials)) {
            return;
        }

        config([
            "ai.providers.{$provider}" => array_merge(
                config("ai.providers.{$provider}", []),
                $credentials
            )
        ]);
    }
}
```

### OpenAI E2E Tests

```php
<?php

namespace Tests\E2E\Drivers;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\E2ETestCase;
use JTD\LaravelAI\Facades\AI;
use JTD\LaravelAI\Models\AIMessage;

#[Group('e2e')]
#[Group('openai')]
class OpenAIDriverE2ETest extends E2ETestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        if (!$this->hasE2ECredentials('openai')) {
            $this->markTestSkipped('OpenAI E2E credentials not available');
        }
        
        $this->overrideConfigWithE2ECredentials('openai');
    }

    #[Test]
    public function it_sends_real_message_to_openai(): void
    {
        $response = AI::provider('openai')->sendMessage(
            AIMessage::user('Say "Hello, E2E test!" and nothing else.')
        );

        $this->assertNotEmpty($response->content);
        $this->assertStringContainsString('Hello, E2E test!', $response->content);
        $this->assertEquals('assistant', $response->role);
        $this->assertNotNull($response->usage);
        $this->assertGreaterThan(0, $response->usage->totalTokens);
    }

    #[Test]
    public function it_syncs_real_models_from_openai(): void
    {
        $result = AI::provider('openai')->syncModels(true);

        $this->assertEquals('success', $result['status']);
        $this->assertGreaterThan(0, $result['models_synced']);
        $this->assertArrayHasKey('statistics', $result);
        $this->assertArrayHasKey('gpt_4_models', $result['statistics']);
    }

    #[Test]
    public function it_validates_real_credentials(): void
    {
        $validation = AI::provider('openai')->validateCredentials();

        $this->assertEquals('valid', $validation['status']);
        $this->assertArrayHasKey('models_available', $validation);
        $this->assertGreaterThan(0, $validation['models_available']);
    }

    #[Test]
    public function it_streams_real_responses(): void
    {
        $chunks = [];
        $stream = AI::provider('openai')->sendStreamingMessage(
            AIMessage::user('Count from 1 to 5, one number per line.')
        );

        foreach ($stream as $chunk) {
            $chunks[] = $chunk->content;
            
            // Limit chunks to prevent runaway tests
            if (count($chunks) > 50) {
                break;
            }
        }

        $this->assertNotEmpty($chunks);
        $this->assertGreaterThan(5, count($chunks)); // Should have multiple chunks
    }
}
```

## Test Configuration

### PHPUnit Configuration

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         colors="true">
    <testsuites>
        <testsuite name="Unit">
            <directory suffix="Test.php">./tests/Unit</directory>
        </testsuite>
        <testsuite name="Integration">
            <directory suffix="Test.php">./tests/Integration</directory>
        </testsuite>
        <testsuite name="E2E">
            <directory suffix="Test.php">./tests/E2E</directory>
        </testsuite>
    </testsuites>
    
    <groups>
        <include>
            <group>unit</group>
            <group>integration</group>
        </include>
        <exclude>
            <group>e2e</group>
        </exclude>
    </groups>
    
    <coverage>
        <include>
            <directory suffix=".php">./src</directory>
        </include>
        <exclude>
            <directory>./src/Testing</directory>
        </exclude>
    </coverage>
    
    <php>
        <env name="APP_ENV" value="testing"/>
        <env name="AI_DEFAULT_PROVIDER" value="mock"/>
        <env name="AI_LOGGING_ENABLED" value="false"/>
    </php>
</phpunit>
```

### Test Commands

```bash
# Run all tests except E2E
vendor/bin/phpunit

# Run only unit tests
vendor/bin/phpunit --testsuite=Unit

# Run only integration tests
vendor/bin/phpunit --testsuite=Integration

# Run E2E tests (requires credentials)
vendor/bin/phpunit --testsuite=E2E --group=e2e

# Run specific provider E2E tests
vendor/bin/phpunit --group=openai --group=e2e

# Run with coverage
vendor/bin/phpunit --coverage-html coverage

# Run performance tests
vendor/bin/phpunit --group=performance
```

## Performance Testing

### Performance Test Example

```php
<?php

namespace Tests\Performance;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;
use JTD\LaravelAI\Facades\AI;
use JTD\LaravelAI\Models\AIMessage;

#[Group('performance')]
class PerformanceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['ai.default' => 'mock']);
    }

    #[Test]
    public function it_handles_concurrent_requests_efficiently(): void
    {
        $startTime = microtime(true);
        $requests = 100;
        
        for ($i = 0; $i < $requests; $i++) {
            AI::sendMessage(AIMessage::user("Message {$i}"));
        }
        
        $duration = microtime(true) - $startTime;
        $requestsPerSecond = $requests / $duration;
        
        $this->assertGreaterThan(1000, $requestsPerSecond); // Should handle 1000+ req/sec
    }

    #[Test]
    public function it_maintains_reasonable_memory_usage(): void
    {
        $initialMemory = memory_get_usage();
        
        for ($i = 0; $i < 1000; $i++) {
            AI::sendMessage(AIMessage::user("Memory test {$i}"));
        }
        
        $finalMemory = memory_get_usage();
        $memoryIncrease = $finalMemory - $initialMemory;
        
        // Memory increase should be less than 10MB for 1000 requests
        $this->assertLessThan(10 * 1024 * 1024, $memoryIncrease);
    }
}
```

## Best Practices

### Test Organization
- **Group by functionality**: Organize tests by the component they test
- **Use descriptive names**: Test method names should clearly describe what they test
- **Follow AAA pattern**: Arrange, Act, Assert in each test
- **One assertion per concept**: Focus each test on a single behavior

### Test Data Management
- **Use factories**: Create test data using factories for consistency
- **Isolate tests**: Each test should be independent and not rely on others
- **Clean up**: Clean up any resources created during tests
- **Use realistic data**: Test data should be realistic but not sensitive

### Mocking and Stubbing
- **Mock external dependencies**: Mock API calls and external services
- **Stub complex objects**: Use stubs for complex objects that are hard to create
- **Verify interactions**: Verify that mocks are called with expected parameters
- **Don't over-mock**: Only mock what's necessary for the test

### E2E Testing
- **Use real credentials**: Store real API credentials securely for E2E tests
- **Skip when unavailable**: Skip E2E tests when credentials are not available
- **Clean up resources**: Clean up any resources created during E2E tests
- **Limit API calls**: Be mindful of API rate limits and costs

## Related Documentation

- **[Configuration System](02-Configuration.md)**: Test configuration setup
- **[Mock Provider](08-Mock-Provider.md)**: Using mock provider for testing
- **[E2E Testing Setup](13-E2E-Setup.md)**: Detailed E2E testing configuration
- **[Performance](15-Performance.md)**: Performance testing and optimization
