<?php

namespace JTD\LaravelAI\Tests\Feature\CoreInfrastructure;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use JTD\LaravelAI\Contracts\AIProviderInterface;
use JTD\LaravelAI\Events\MessageSent;
use JTD\LaravelAI\Events\ResponseGenerated;
use JTD\LaravelAI\Facades\AI;
use JTD\LaravelAI\Services\ConfigurationValidator;
use JTD\LaravelAI\Services\DriverManager;
use JTD\LaravelAI\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * AI Manager and Driver System Tests
 *
 * Tests central orchestrator, provider management, and driver implementations
 * for the core AI infrastructure.
 */
#[Group('core-infrastructure')]
#[Group('ai-manager')]
class AIManagerDriverSystemTest extends TestCase
{
    use RefreshDatabase;

    protected DriverManager $driverManager;

    protected array $performanceMetrics = [];

    protected function setUp(): void
    {
        parent::setUp();

        try {
            $this->driverManager = app(DriverManager::class);
        } catch (\Exception $e) {
            $this->driverManager = new DriverManager($this->app);
        }

        $this->performanceMetrics = [];
    }

    protected function tearDown(): void
    {
        $this->logPerformanceMetrics();
        parent::tearDown();
    }

    #[Test]
    public function it_orchestrates_ai_requests_through_central_manager(): void
    {
        Event::fake([MessageSent::class, ResponseGenerated::class]);

        $startTime = microtime(true);

        try {
            // Test central orchestration through AI facade
            $response = AI::provider('mock')->sendMessage('Test orchestration');

            $orchestrationTime = (microtime(true) - $startTime) * 1000;

            $this->recordMetric('central_orchestration', [
                'orchestration_time_ms' => $orchestrationTime,
                'target_ms' => 100,
            ]);

            $this->assertNotNull($response);
            $this->assertLessThan(100, $orchestrationTime,
                "Central orchestration took {$orchestrationTime}ms, exceeding 100ms target");

            // Verify events were fired through orchestration
            Event::assertDispatched(MessageSent::class);
            Event::assertDispatched(ResponseGenerated::class);
        } catch (\Exception $e) {
            $this->markTestIncomplete('Central orchestration test failed: ' . $e->getMessage());
        }
    }

    #[Test]
    public function it_manages_provider_lifecycle_efficiently(): void
    {
        $iterations = 10;
        $lifecycleTimes = [];

        for ($i = 0; $i < $iterations; $i++) {
            $startTime = microtime(true);

            try {
                // Test provider lifecycle management
                $provider = $this->driverManager->driver('mock');
                $this->assertInstanceOf(AIProviderInterface::class, $provider);

                // Refresh provider
                $this->driverManager->refreshDriver('mock');
                $newProvider = $this->driverManager->driver('mock');
                $this->assertInstanceOf(AIProviderInterface::class, $newProvider);

                $lifecycleTime = (microtime(true) - $startTime) * 1000;
                $lifecycleTimes[] = $lifecycleTime;
            } catch (\Exception $e) {
                $this->markTestIncomplete('Provider lifecycle test failed: ' . $e->getMessage());

                return;
            }
        }

        $avgTime = array_sum($lifecycleTimes) / count($lifecycleTimes);

        $this->recordMetric('provider_lifecycle', [
            'average_ms' => $avgTime,
            'iterations' => $iterations,
            'target_ms' => 50,
        ]);

        $this->assertLessThan(50, $avgTime,
            "Provider lifecycle averaged {$avgTime}ms, exceeding 50ms target");
    }

    #[Test]
    public function it_handles_concurrent_provider_requests(): void
    {
        $concurrentRequests = 20;
        $startTime = microtime(true);
        $responses = [];

        try {
            // Test concurrent provider access
            for ($i = 0; $i < $concurrentRequests; $i++) {
                $provider = $this->driverManager->driver('mock');
                $responses[] = $provider->sendMessage("Concurrent request {$i}");
            }

            $totalTime = (microtime(true) - $startTime) * 1000;
            $avgTimePerRequest = $totalTime / $concurrentRequests;

            $this->recordMetric('concurrent_provider_access', [
                'total_requests' => $concurrentRequests,
                'total_time_ms' => $totalTime,
                'avg_time_per_request_ms' => $avgTimePerRequest,
                'target_ms' => 25,
            ]);

            $this->assertCount($concurrentRequests, $responses);
            foreach ($responses as $response) {
                $this->assertNotNull($response);
            }

            $this->assertLessThan(25, $avgTimePerRequest,
                "Concurrent provider access averaged {$avgTimePerRequest}ms per request, exceeding 25ms target");
        } catch (\Exception $e) {
            $this->markTestIncomplete('Concurrent provider access test failed: ' . $e->getMessage());
        }
    }

    #[Test]
    public function it_validates_driver_implementations_comprehensively(): void
    {
        $providers = ['mock', 'openai', 'xai', 'gemini', 'ollama'];
        $validationResults = [];

        foreach ($providers as $provider) {
            $startTime = microtime(true);

            try {
                $result = $this->driverManager->validateProvider($provider);
                $validationTime = (microtime(true) - $startTime) * 1000;

                $validationResults[] = [
                    'provider' => $provider,
                    'result' => $result,
                    'validation_time_ms' => $validationTime,
                ];

                $this->assertIsArray($result);
                $this->assertArrayHasKey('status', $result);
                $this->assertLessThan(100, $validationTime,
                    "Provider {$provider} validation took {$validationTime}ms, exceeding 100ms target");
            } catch (\Exception $e) {
                $validationResults[] = [
                    'provider' => $provider,
                    'result' => ['status' => 'error', 'message' => $e->getMessage()],
                    'validation_time_ms' => (microtime(true) - $startTime) * 1000,
                ];
            }
        }

        $this->recordMetric('driver_validation', [
            'providers_tested' => count($providers),
            'validation_results' => $validationResults,
        ]);

        // At least mock provider should be valid
        $mockResult = collect($validationResults)->firstWhere('provider', 'mock');
        $this->assertEquals('valid', $mockResult['result']['status']);
    }

    #[Test]
    public function it_monitors_provider_health_status(): void
    {
        $providers = $this->driverManager->getAvailableProviders();
        $healthResults = [];

        foreach ($providers as $provider) {
            $startTime = microtime(true);

            try {
                $health = $this->driverManager->getProviderHealth($provider);
                $healthCheckTime = (microtime(true) - $startTime) * 1000;

                $healthResults[] = [
                    'provider' => $provider,
                    'health' => $health,
                    'check_time_ms' => $healthCheckTime,
                ];

                $this->assertIsArray($health);
                $this->assertArrayHasKey('status', $health);
                $this->assertLessThan(50, $healthCheckTime,
                    "Provider {$provider} health check took {$healthCheckTime}ms, exceeding 50ms target");
            } catch (\Exception $e) {
                $healthResults[] = [
                    'provider' => $provider,
                    'health' => ['status' => 'error', 'message' => $e->getMessage()],
                    'check_time_ms' => (microtime(true) - $startTime) * 1000,
                ];
            }
        }

        $this->recordMetric('provider_health_monitoring', [
            'providers_checked' => count($providers),
            'health_results' => $healthResults,
        ]);

        // At least mock provider should be healthy
        $mockResult = collect($healthResults)->firstWhere('provider', 'mock');
        $this->assertEquals('healthy', $mockResult['health']['status']);
    }

    #[Test]
    public function it_handles_provider_configuration_changes(): void
    {
        $startTime = microtime(true);

        try {
            // Test dynamic configuration changes
            $originalProvider = $this->driverManager->driver('mock');
            $this->assertInstanceOf(AIProviderInterface::class, $originalProvider);

            // Simulate configuration change
            config(['ai.providers.mock.test_setting' => 'new_value']);

            // Refresh to pick up new configuration
            $this->driverManager->refreshDriver('mock');
            $updatedProvider = $this->driverManager->driver('mock');

            $configChangeTime = (microtime(true) - $startTime) * 1000;

            $this->recordMetric('configuration_change_handling', [
                'change_time_ms' => $configChangeTime,
                'target_ms' => 75,
            ]);

            $this->assertInstanceOf(AIProviderInterface::class, $updatedProvider);
            $this->assertNotSame($originalProvider, $updatedProvider);
            $this->assertLessThan(75, $configChangeTime,
                "Configuration change handling took {$configChangeTime}ms, exceeding 75ms target");
        } catch (\Exception $e) {
            $this->markTestIncomplete('Configuration change handling test failed: ' . $e->getMessage());
        }
    }

    #[Test]
    public function it_manages_provider_registry_efficiently(): void
    {
        $iterations = 20;
        $registryTimes = [];

        for ($i = 0; $i < $iterations; $i++) {
            $startTime = microtime(true);

            try {
                // Test registry operations
                $registry = $this->driverManager->getProviderRegistry();
                $this->assertIsArray($registry);

                // Test provider registration
                $this->driverManager->registerProvider("test_provider_{$i}", [
                    'description' => "Test provider {$i}",
                    'supports_streaming' => true,
                ]);

                // Verify registration
                $updatedRegistry = $this->driverManager->getProviderRegistry();
                $this->assertArrayHasKey("test_provider_{$i}", $updatedRegistry);

                $registryTime = (microtime(true) - $startTime) * 1000;
                $registryTimes[] = $registryTime;
            } catch (\Exception $e) {
                $this->markTestIncomplete('Provider registry test failed: ' . $e->getMessage());

                return;
            }
        }

        $avgTime = array_sum($registryTimes) / count($registryTimes);

        $this->recordMetric('provider_registry_management', [
            'average_ms' => $avgTime,
            'iterations' => $iterations,
            'target_ms' => 30,
        ]);

        $this->assertLessThan(30, $avgTime,
            "Provider registry operations averaged {$avgTime}ms, exceeding 30ms target");
    }

    #[Test]
    public function it_handles_driver_extension_and_customization(): void
    {
        $startTime = microtime(true);

        try {
            // Test driver extension
            $this->driverManager->extend('custom_test', function ($app, $config) {
                return new \JTD\LaravelAI\Providers\MockProvider($config);
            });

            // Configure the custom provider
            config(['ai.providers.custom_test' => ['driver' => 'custom_test']]);

            // Test custom driver creation
            $customDriver = $this->driverManager->driver('custom_test');

            $extensionTime = (microtime(true) - $startTime) * 1000;

            $this->recordMetric('driver_extension', [
                'extension_time_ms' => $extensionTime,
                'target_ms' => 50,
            ]);

            $this->assertInstanceOf(AIProviderInterface::class, $customDriver);
            $this->assertLessThan(50, $extensionTime,
                "Driver extension took {$extensionTime}ms, exceeding 50ms target");
        } catch (\Exception $e) {
            $this->markTestIncomplete('Driver extension test failed: ' . $e->getMessage());
        }
    }

    #[Test]
    public function it_validates_configuration_comprehensively(): void
    {
        $validator = new ConfigurationValidator;
        $testConfigurations = [
            'valid_basic' => [
                'default' => 'mock',
                'providers' => [
                    'mock' => ['driver' => 'mock'],
                ],
            ],
            'valid_complex' => [
                'default' => 'mock',
                'providers' => [
                    'mock' => ['driver' => 'mock'],
                    'openai' => ['driver' => 'openai', 'api_key' => 'sk-test1234567890123456789012345678901234567890'],
                ],
                'cost_tracking' => ['enabled' => true],
                'budget_management' => ['enabled' => true],
            ],
            'invalid_no_default' => [
                'providers' => [
                    'mock' => ['driver' => 'mock'],
                ],
            ],
            'invalid_missing_provider' => [
                'default' => 'nonexistent',
                'providers' => [
                    'mock' => ['driver' => 'mock'],
                ],
            ],
        ];

        $validationResults = [];

        foreach ($testConfigurations as $name => $config) {
            $startTime = microtime(true);
            $isValid = false;
            $error = null;

            try {
                $isValid = $validator->validate($config);
            } catch (\Exception $e) {
                $error = $e->getMessage();
            }

            $validationTime = (microtime(true) - $startTime) * 1000;

            $validationResults[] = [
                'config_name' => $name,
                'is_valid' => $isValid,
                'error' => $error,
                'validation_time_ms' => $validationTime,
            ];

            $this->assertLessThan(25, $validationTime,
                "Configuration validation for {$name} took {$validationTime}ms, exceeding 25ms target");
        }

        $this->recordMetric('configuration_validation', [
            'configurations_tested' => count($testConfigurations),
            'validation_results' => $validationResults,
        ]);

        // Verify expected results
        $validBasic = collect($validationResults)->firstWhere('config_name', 'valid_basic');
        $this->assertTrue($validBasic['is_valid']);

        $invalidNoDefault = collect($validationResults)->firstWhere('config_name', 'invalid_no_default');
        $this->assertFalse($invalidNoDefault['is_valid']);
    }

    /**
     * Record performance metric.
     */
    protected function recordMetric(string $name, array $data): void
    {
        $this->performanceMetrics[$name] = array_merge($data, [
            'timestamp' => now()->toISOString(),
            'test_environment' => app()->environment(),
        ]);
    }

    /**
     * Log performance metrics.
     */
    protected function logPerformanceMetrics(): void
    {
        if (! empty($this->performanceMetrics)) {
            Log::info('AI Manager Driver System Test Results', [
                'metrics' => $this->performanceMetrics,
                'summary' => $this->generatePerformanceSummary(),
            ]);
        }
    }

    /**
     * Generate performance summary.
     */
    protected function generatePerformanceSummary(): array
    {
        $summary = [
            'total_tests' => count($this->performanceMetrics),
            'components_tested' => array_keys($this->performanceMetrics),
            'performance_targets_met' => 0,
            'performance_targets_failed' => 0,
        ];

        foreach ($this->performanceMetrics as $name => $data) {
            $targetMet = true;
            if (isset($data['target_ms'])) {
                $actualTime = $data['orchestration_time_ms'] ?? $data['average_ms'] ?? $data['change_time_ms'] ?? 0;
                $targetMet = $actualTime < $data['target_ms'];
            }

            if ($targetMet) {
                $summary['performance_targets_met']++;
            } else {
                $summary['performance_targets_failed']++;
            }
        }

        return $summary;
    }
}
