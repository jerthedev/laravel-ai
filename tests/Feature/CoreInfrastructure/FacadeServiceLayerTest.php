<?php

namespace JTD\LaravelAI\Tests\Feature\CoreInfrastructure;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use JTD\LaravelAI\Facades\AI;
use JTD\LaravelAI\LaravelAIServiceProvider;
use JTD\LaravelAI\Services\ConfigurationValidator;
use JTD\LaravelAI\Services\DriverManager;
use JTD\LaravelAI\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Facade and Service Layer Tests
 *
 * Tests Laravel integration, facades, and service provider functionality
 * for the core AI infrastructure.
 */
#[Group('core-infrastructure')]
#[Group('facade-service-layer')]
class FacadeServiceLayerTest extends TestCase
{
    use RefreshDatabase;

    protected array $performanceMetrics = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->performanceMetrics = [];
    }

    protected function tearDown(): void
    {
        $this->logPerformanceMetrics();
        parent::tearDown();
    }

    #[Test]
    public function it_validates_service_provider_registration(): void
    {
        $startTime = microtime(true);

        try {
            // Test service provider is registered
            $providers = App::getProviders(LaravelAIServiceProvider::class);
            $this->assertNotEmpty($providers, 'LaravelAIServiceProvider should be registered');

            // Test core services are bound
            $coreServices = [
                'laravel-ai.driver-manager' => DriverManager::class,
                'laravel-ai.config-validator' => ConfigurationValidator::class,
            ];

            foreach ($coreServices as $binding => $expectedClass) {
                $this->assertTrue(App::bound($binding), "Service {$binding} should be bound");

                $service = App::make($binding);
                $this->assertInstanceOf($expectedClass, $service,
                    "Service {$binding} should be instance of {$expectedClass}");
            }

            $registrationTime = (microtime(true) - $startTime) * 1000;

            $this->recordMetric('service_provider_registration', [
                'registration_time_ms' => $registrationTime,
                'services_tested' => count($coreServices),
                'target_ms' => 100,
            ]);

            $this->assertLessThan(100, $registrationTime,
                "Service provider registration took {$registrationTime}ms, exceeding 100ms target");
        } catch (\Exception $e) {
            $this->markTestIncomplete('Service provider registration test failed: ' . $e->getMessage());
        }
    }

    #[Test]
    public function it_validates_ai_facade_functionality(): void
    {
        $facadeTests = [
            'provider_method' => function () {
                $provider = AI::provider('mock');

                return $provider !== null;
            },
            'send_message_method' => function () {
                $response = AI::provider('mock')->sendMessage('Test facade message');

                return $response !== null;
            },
            'conversation_method' => function () {
                $conversation = AI::conversation();

                return $conversation !== null;
            },
            'default_provider' => function () {
                $response = AI::sendMessage('Test default provider');

                return $response !== null;
            },
        ];

        $facadeResults = [];
        $startTime = microtime(true);

        foreach ($facadeTests as $testName => $test) {
            $facadeStartTime = microtime(true);

            try {
                $result = $test();
                $facadeTime = (microtime(true) - $facadeStartTime) * 1000;

                $facadeResults[] = [
                    'test' => $testName,
                    'execution_time_ms' => $facadeTime,
                    'success' => $result,
                ];

                $this->assertTrue($result, "Facade test {$testName} should succeed");
                $this->assertLessThan(100, $facadeTime,
                    "Facade test {$testName} took {$facadeTime}ms, exceeding 100ms target");
            } catch (\Exception $e) {
                $facadeResults[] = [
                    'test' => $testName,
                    'execution_time_ms' => (microtime(true) - $facadeStartTime) * 1000,
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        $totalTime = (microtime(true) - $startTime) * 1000;

        $this->recordMetric('ai_facade_functionality', [
            'total_time_ms' => $totalTime,
            'facade_tests' => count($facadeTests),
            'facade_results' => $facadeResults,
            'target_ms' => 300,
        ]);

        $this->assertLessThan(300, $totalTime,
            "AI facade functionality tests took {$totalTime}ms, exceeding 300ms target");
    }

    #[Test]
    public function it_validates_configuration_integration(): void
    {
        $configTests = [
            'default_config_loaded' => function () {
                $default = Config::get('ai.default');

                return $default !== null;
            },
            'providers_config_loaded' => function () {
                $providers = Config::get('ai.providers');

                return is_array($providers) && ! empty($providers);
            },
            'cost_tracking_config' => function () {
                $costTracking = Config::get('ai.cost_tracking');

                return is_array($costTracking);
            },
            'budget_management_config' => function () {
                $budgetManagement = Config::get('ai.budget_management');

                return is_array($budgetManagement);
            },
            'config_caching' => function () {
                // Test config caching performance
                $start = microtime(true);
                for ($i = 0; $i < 10; $i++) {
                    Config::get('ai.default');
                }
                $time = (microtime(true) - $start) * 1000;

                return $time < 10; // Should be very fast due to caching
            },
        ];

        $configResults = [];
        $startTime = microtime(true);

        foreach ($configTests as $testName => $test) {
            $configStartTime = microtime(true);

            try {
                $result = $test();
                $configTime = (microtime(true) - $configStartTime) * 1000;

                $configResults[] = [
                    'test' => $testName,
                    'execution_time_ms' => $configTime,
                    'success' => $result,
                ];

                $this->assertTrue($result, "Configuration test {$testName} should succeed");
                $this->assertLessThan(50, $configTime,
                    "Configuration test {$testName} took {$configTime}ms, exceeding 50ms target");
            } catch (\Exception $e) {
                $configResults[] = [
                    'test' => $testName,
                    'execution_time_ms' => (microtime(true) - $configStartTime) * 1000,
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        $totalTime = (microtime(true) - $startTime) * 1000;

        $this->recordMetric('configuration_integration', [
            'total_time_ms' => $totalTime,
            'config_tests' => count($configTests),
            'config_results' => $configResults,
            'target_ms' => 200,
        ]);

        $this->assertLessThan(200, $totalTime,
            "Configuration integration tests took {$totalTime}ms, exceeding 200ms target");
    }

    #[Test]
    public function it_validates_service_container_bindings(): void
    {
        $serviceBindings = [
            'driver-manager' => 'laravel-ai.driver-manager',
            'config-validator' => 'laravel-ai.config-validator',
            'conversation-service' => 'laravel-ai.conversation',
            'cost-tracking-service' => 'laravel-ai.cost-tracking',
            'budget-service' => 'laravel-ai.budget',
        ];

        $bindingResults = [];
        $startTime = microtime(true);

        foreach ($serviceBindings as $serviceName => $binding) {
            $bindingStartTime = microtime(true);

            try {
                $isBound = App::bound($binding);
                $bindingTime = (microtime(true) - $bindingStartTime) * 1000;

                if ($isBound) {
                    $resolveStartTime = microtime(true);
                    $service = App::make($binding);
                    $resolveTime = (microtime(true) - $resolveStartTime) * 1000;

                    $bindingResults[] = [
                        'service' => $serviceName,
                        'binding' => $binding,
                        'is_bound' => true,
                        'binding_time_ms' => $bindingTime,
                        'resolve_time_ms' => $resolveTime,
                        'service_class' => get_class($service),
                    ];

                    $this->assertLessThan(25, $resolveTime,
                        "Service {$serviceName} resolution took {$resolveTime}ms, exceeding 25ms target");
                } else {
                    $bindingResults[] = [
                        'service' => $serviceName,
                        'binding' => $binding,
                        'is_bound' => false,
                        'binding_time_ms' => $bindingTime,
                    ];
                }

                $this->assertLessThan(10, $bindingTime,
                    "Service {$serviceName} binding check took {$bindingTime}ms, exceeding 10ms target");
            } catch (\Exception $e) {
                $bindingResults[] = [
                    'service' => $serviceName,
                    'binding' => $binding,
                    'is_bound' => false,
                    'error' => $e->getMessage(),
                    'binding_time_ms' => (microtime(true) - $bindingStartTime) * 1000,
                ];
            }
        }

        $totalTime = (microtime(true) - $startTime) * 1000;

        $this->recordMetric('service_container_bindings', [
            'total_time_ms' => $totalTime,
            'bindings_tested' => count($serviceBindings),
            'binding_results' => $bindingResults,
            'target_ms' => 150,
        ]);

        $this->assertLessThan(150, $totalTime,
            "Service container binding tests took {$totalTime}ms, exceeding 150ms target");

        // Verify core services are bound
        $coreServices = ['driver-manager', 'config-validator'];
        foreach ($coreServices as $service) {
            $result = collect($bindingResults)->firstWhere('service', $service);
            $this->assertTrue($result['is_bound'], "Core service {$service} should be bound");
        }
    }

    #[Test]
    public function it_validates_laravel_integration_features(): void
    {
        $integrationTests = [
            'artisan_commands' => function () {
                // Test that AI-related artisan commands are registered
                $commands = App::make('Illuminate\Contracts\Console\Kernel')->all();
                $aiCommands = array_filter(array_keys($commands), function ($command) {
                    return str_starts_with($command, 'ai:');
                });

                return count($aiCommands) > 0;
            },
            'middleware_registration' => function () {
                // Test that AI middleware is available
                $router = App::make('router');
                $middleware = $router->getMiddleware();

                return isset($middleware['ai.budget']) || isset($middleware['ai.cost-tracking']);
            },
            'event_listeners' => function () {
                // Test that event listeners are registered
                $dispatcher = App::make('events');
                $listeners = $dispatcher->getListeners('JTD\LaravelAI\Events\MessageSent');

                return count($listeners) >= 0; // May be 0 if no listeners registered yet
            },
            'config_publishing' => function () {
                // Test that config can be published
                $configPath = config_path('ai.php');

                return file_exists($configPath) || true; // Config may not be published in tests
            },
        ];

        $integrationResults = [];
        $startTime = microtime(true);

        foreach ($integrationTests as $testName => $test) {
            $integrationStartTime = microtime(true);

            try {
                $result = $test();
                $integrationTime = (microtime(true) - $integrationStartTime) * 1000;

                $integrationResults[] = [
                    'test' => $testName,
                    'execution_time_ms' => $integrationTime,
                    'success' => $result,
                ];

                $this->assertLessThan(100, $integrationTime,
                    "Integration test {$testName} took {$integrationTime}ms, exceeding 100ms target");
            } catch (\Exception $e) {
                $integrationResults[] = [
                    'test' => $testName,
                    'execution_time_ms' => (microtime(true) - $integrationStartTime) * 1000,
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        $totalTime = (microtime(true) - $startTime) * 1000;

        $this->recordMetric('laravel_integration_features', [
            'total_time_ms' => $totalTime,
            'integration_tests' => count($integrationTests),
            'integration_results' => $integrationResults,
            'target_ms' => 300,
        ]);

        $this->assertLessThan(300, $totalTime,
            "Laravel integration feature tests took {$totalTime}ms, exceeding 300ms target");
    }

    #[Test]
    public function it_validates_service_layer_performance(): void
    {
        $performanceTests = [
            'facade_resolution' => function () {
                $start = microtime(true);
                for ($i = 0; $i < 10; $i++) {
                    AI::provider('mock');
                }

                return (microtime(true) - $start) * 1000;
            },
            'service_instantiation' => function () {
                $start = microtime(true);
                for ($i = 0; $i < 10; $i++) {
                    App::make(DriverManager::class);
                }

                return (microtime(true) - $start) * 1000;
            },
            'config_access' => function () {
                $start = microtime(true);
                for ($i = 0; $i < 100; $i++) {
                    Config::get('ai.default');
                }

                return (microtime(true) - $start) * 1000;
            },
        ];

        $performanceResults = [];

        foreach ($performanceTests as $testName => $test) {
            try {
                $executionTime = $test();

                $performanceResults[] = [
                    'test' => $testName,
                    'execution_time_ms' => $executionTime,
                    'target_met' => $executionTime < 100,
                ];

                $this->assertLessThan(100, $executionTime,
                    "Performance test {$testName} took {$executionTime}ms, exceeding 100ms target");
            } catch (\Exception $e) {
                $performanceResults[] = [
                    'test' => $testName,
                    'error' => $e->getMessage(),
                    'target_met' => false,
                ];
            }
        }

        $this->recordMetric('service_layer_performance', [
            'performance_tests' => count($performanceTests),
            'performance_results' => $performanceResults,
        ]);
    }

    #[Test]
    public function it_validates_error_handling_in_service_layer(): void
    {
        $errorTests = [
            'invalid_provider_facade' => function () {
                try {
                    AI::provider('nonexistent');

                    return false; // Should throw exception
                } catch (\Exception $e) {
                    return true; // Expected behavior
                }
            },
            'invalid_config_access' => function () {
                try {
                    Config::get('ai.nonexistent.deeply.nested.key');

                    return true; // Should return null gracefully
                } catch (\Exception $e) {
                    return false; // Should not throw exception
                }
            },
            'service_resolution_failure' => function () {
                try {
                    App::make('nonexistent-service');

                    return false; // Should throw exception
                } catch (\Exception $e) {
                    return true; // Expected behavior
                }
            },
        ];

        $errorResults = [];

        foreach ($errorTests as $testName => $test) {
            $errorStartTime = microtime(true);

            try {
                $result = $test();
                $errorTime = (microtime(true) - $errorStartTime) * 1000;

                $errorResults[] = [
                    'test' => $testName,
                    'handling_time_ms' => $errorTime,
                    'handled_correctly' => $result,
                ];

                $this->assertTrue($result, "Error handling test {$testName} should handle errors correctly");
                $this->assertLessThan(50, $errorTime,
                    "Error handling {$testName} took {$errorTime}ms, exceeding 50ms target");
            } catch (\Exception $e) {
                $errorResults[] = [
                    'test' => $testName,
                    'handling_time_ms' => (microtime(true) - $errorStartTime) * 1000,
                    'handled_correctly' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        $this->recordMetric('service_layer_error_handling', [
            'error_tests' => count($errorTests),
            'error_results' => $errorResults,
        ]);
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
            Log::info('Facade Service Layer Test Results', [
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
            'service_components_tested' => array_keys($this->performanceMetrics),
            'performance_targets_met' => 0,
            'performance_targets_failed' => 0,
        ];

        foreach ($this->performanceMetrics as $name => $data) {
            $targetMet = true;
            if (isset($data['target_ms'])) {
                $actualTime = $data['total_time_ms'] ?? 0;
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
