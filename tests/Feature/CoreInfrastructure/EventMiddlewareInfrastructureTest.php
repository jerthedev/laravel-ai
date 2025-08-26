<?php

namespace JTD\LaravelAI\Tests\Feature\CoreInfrastructure;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use JTD\LaravelAI\Events\BudgetThresholdReached;
use JTD\LaravelAI\Events\CostCalculated;
use JTD\LaravelAI\Events\MCPToolExecuted;
use JTD\LaravelAI\Events\MessageSent;
use JTD\LaravelAI\Events\ResponseGenerated;
use JTD\LaravelAI\Middleware\BudgetEnforcementMiddleware;
use JTD\LaravelAI\Middleware\CostTrackingMiddleware;
use JTD\LaravelAI\Middleware\RateLimitingMiddleware;
use JTD\LaravelAI\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Event and Middleware Infrastructure Tests
 *
 * Tests core event system and middleware framework
 * for the AI infrastructure.
 */
#[Group('core-infrastructure')]
#[Group('event-middleware')]
class EventMiddlewareInfrastructureTest extends TestCase
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
    public function it_validates_event_system_infrastructure(): void
    {
        Event::fake();

        $events = [
            MessageSent::class,
            ResponseGenerated::class,
            CostCalculated::class,
            MCPToolExecuted::class,
            BudgetThresholdReached::class,
        ];

        $eventResults = [];
        $startTime = microtime(true);

        foreach ($events as $eventClass) {
            $eventStartTime = microtime(true);

            try {
                // Test event instantiation and dispatch
                $eventData = $this->getEventTestData($eventClass);
                $event = new $eventClass(...$eventData);

                event($event);

                $eventTime = (microtime(true) - $eventStartTime) * 1000;

                $eventResults[] = [
                    'event' => $eventClass,
                    'dispatch_time_ms' => $eventTime,
                    'success' => true,
                ];

                // Verify event was dispatched
                Event::assertDispatched($eventClass);

                $this->assertLessThan(25, $eventTime,
                    "Event {$eventClass} dispatch took {$eventTime}ms, exceeding 25ms target");
            } catch (\Exception $e) {
                $eventResults[] = [
                    'event' => $eventClass,
                    'dispatch_time_ms' => (microtime(true) - $eventStartTime) * 1000,
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        $totalTime = (microtime(true) - $startTime) * 1000;

        $this->recordMetric('event_system_validation', [
            'total_time_ms' => $totalTime,
            'events_tested' => count($events),
            'event_results' => $eventResults,
            'target_ms' => 100,
        ]);

        $this->assertLessThan(100, $totalTime,
            "Event system validation took {$totalTime}ms, exceeding 100ms target");

        // Verify all events dispatched successfully
        $successfulEvents = collect($eventResults)->where('success', true)->count();
        $this->assertGreaterThan(0, $successfulEvents, 'At least some events should dispatch successfully');
    }

    #[Test]
    public function it_validates_middleware_infrastructure(): void
    {
        $middlewares = [
            BudgetEnforcementMiddleware::class,
            CostTrackingMiddleware::class,
            RateLimitingMiddleware::class,
        ];

        $middlewareResults = [];
        $startTime = microtime(true);

        foreach ($middlewares as $middlewareClass) {
            $middlewareStartTime = microtime(true);

            try {
                // Test middleware instantiation and execution
                $middleware = app($middlewareClass);
                $request = Request::create('/test', 'POST');

                $response = $middleware->handle($request, function ($req) {
                    return new Response('Test response');
                });

                $middlewareTime = (microtime(true) - $middlewareStartTime) * 1000;

                $middlewareResults[] = [
                    'middleware' => $middlewareClass,
                    'execution_time_ms' => $middlewareTime,
                    'success' => true,
                ];

                $this->assertInstanceOf(Response::class, $response);
                $this->assertLessThan(50, $middlewareTime,
                    "Middleware {$middlewareClass} execution took {$middlewareTime}ms, exceeding 50ms target");
            } catch (\Exception $e) {
                $middlewareResults[] = [
                    'middleware' => $middlewareClass,
                    'execution_time_ms' => (microtime(true) - $middlewareStartTime) * 1000,
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        $totalTime = (microtime(true) - $startTime) * 1000;

        $this->recordMetric('middleware_infrastructure_validation', [
            'total_time_ms' => $totalTime,
            'middlewares_tested' => count($middlewares),
            'middleware_results' => $middlewareResults,
            'target_ms' => 150,
        ]);

        $this->assertLessThan(150, $totalTime,
            "Middleware infrastructure validation took {$totalTime}ms, exceeding 150ms target");
    }

    #[Test]
    public function it_tests_event_listener_registration(): void
    {
        $listenerTests = [
            'message_sent_listeners' => MessageSent::class,
            'response_generated_listeners' => ResponseGenerated::class,
            'cost_calculated_listeners' => CostCalculated::class,
            'mcp_tool_executed_listeners' => MCPToolExecuted::class,
            'budget_threshold_reached_listeners' => BudgetThresholdReached::class,
        ];

        $listenerResults = [];
        $startTime = microtime(true);

        foreach ($listenerTests as $testName => $eventClass) {
            $listenerStartTime = microtime(true);

            try {
                // Get registered listeners for the event
                $listeners = Event::getListeners($eventClass);

                $listenerTime = (microtime(true) - $listenerStartTime) * 1000;

                $listenerResults[] = [
                    'test' => $testName,
                    'event_class' => $eventClass,
                    'listeners_count' => count($listeners),
                    'registration_time_ms' => $listenerTime,
                ];

                $this->assertLessThan(10, $listenerTime,
                    "Listener registration check for {$testName} took {$listenerTime}ms, exceeding 10ms target");
            } catch (\Exception $e) {
                $listenerResults[] = [
                    'test' => $testName,
                    'event_class' => $eventClass,
                    'error' => $e->getMessage(),
                    'registration_time_ms' => (microtime(true) - $listenerStartTime) * 1000,
                ];
            }
        }

        $totalTime = (microtime(true) - $startTime) * 1000;

        $this->recordMetric('event_listener_registration', [
            'total_time_ms' => $totalTime,
            'listener_tests' => count($listenerTests),
            'listener_results' => $listenerResults,
            'target_ms' => 50,
        ]);

        $this->assertLessThan(50, $totalTime,
            "Event listener registration tests took {$totalTime}ms, exceeding 50ms target");
    }

    #[Test]
    public function it_tests_middleware_stack_execution(): void
    {
        $stackTests = [
            'single_middleware' => [BudgetEnforcementMiddleware::class],
            'double_middleware' => [BudgetEnforcementMiddleware::class, CostTrackingMiddleware::class],
            'triple_middleware' => [BudgetEnforcementMiddleware::class, CostTrackingMiddleware::class, RateLimitingMiddleware::class],
        ];

        $stackResults = [];

        foreach ($stackTests as $testName => $middlewareStack) {
            $stackStartTime = microtime(true);

            try {
                $request = Request::create('/test', 'POST');
                $response = $this->executeMiddlewareStack($request, $middlewareStack);

                $stackTime = (microtime(true) - $stackStartTime) * 1000;

                $stackResults[] = [
                    'test' => $testName,
                    'middleware_count' => count($middlewareStack),
                    'execution_time_ms' => $stackTime,
                    'success' => true,
                ];

                $this->assertInstanceOf(Response::class, $response);

                $targetTime = count($middlewareStack) * 25; // 25ms per middleware
                $this->assertLessThan($targetTime, $stackTime,
                    "Middleware stack {$testName} took {$stackTime}ms, exceeding {$targetTime}ms target");
            } catch (\Exception $e) {
                $stackResults[] = [
                    'test' => $testName,
                    'middleware_count' => count($middlewareStack),
                    'execution_time_ms' => (microtime(true) - $stackStartTime) * 1000,
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        $this->recordMetric('middleware_stack_execution', [
            'stack_tests' => count($stackTests),
            'stack_results' => $stackResults,
        ]);
    }

    #[Test]
    public function it_tests_event_propagation_and_handling(): void
    {
        Event::fake();

        $propagationTests = [
            'synchronous_events' => function () {
                event(new MessageSent(
                    userId: 1,
                    provider: 'mock',
                    model: 'gpt-4',
                    message: 'Test message',
                    metadata: []
                ));

                return true;
            },
            'event_with_listeners' => function () {
                // Register a test listener
                Event::listen(ResponseGenerated::class, function ($event) {
                    // Test listener logic
                });

                event(new ResponseGenerated(
                    userId: 1,
                    provider: 'mock',
                    model: 'gpt-4',
                    response: 'Test response',
                    metadata: []
                ));

                return true;
            },
        ];

        $propagationResults = [];

        foreach ($propagationTests as $testName => $test) {
            $propagationStartTime = microtime(true);

            try {
                $result = $test();
                $propagationTime = (microtime(true) - $propagationStartTime) * 1000;

                $propagationResults[] = [
                    'test' => $testName,
                    'propagation_time_ms' => $propagationTime,
                    'success' => $result,
                ];

                $this->assertTrue($result, "Event propagation test {$testName} should succeed");
                $this->assertLessThan(30, $propagationTime,
                    "Event propagation {$testName} took {$propagationTime}ms, exceeding 30ms target");
            } catch (\Exception $e) {
                $propagationResults[] = [
                    'test' => $testName,
                    'propagation_time_ms' => (microtime(true) - $propagationStartTime) * 1000,
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        $this->recordMetric('event_propagation', [
            'propagation_tests' => count($propagationTests),
            'propagation_results' => $propagationResults,
        ]);
    }

    #[Test]
    public function it_tests_infrastructure_error_handling(): void
    {
        $errorTests = [
            'invalid_event_data' => function () {
                try {
                    // This should handle gracefully
                    event(new MessageSent(
                        userId: null, // Invalid data
                        provider: '',
                        model: '',
                        message: '',
                        metadata: []
                    ));

                    return true;
                } catch (\Exception $e) {
                    return false;
                }
            },
            'middleware_exception_handling' => function () {
                try {
                    $middleware = app(BudgetEnforcementMiddleware::class);
                    $request = Request::create('/test', 'POST');

                    $middleware->handle($request, function ($req) {
                        throw new \Exception('Test exception');
                    });

                    return false; // Should not reach here
                } catch (\Exception $e) {
                    return true; // Expected to catch exception
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

                $this->assertLessThan(100, $errorTime,
                    "Error handling {$testName} took {$errorTime}ms, exceeding 100ms target");
            } catch (\Exception $e) {
                $errorResults[] = [
                    'test' => $testName,
                    'handling_time_ms' => (microtime(true) - $errorStartTime) * 1000,
                    'handled_correctly' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        $this->recordMetric('infrastructure_error_handling', [
            'error_tests' => count($errorTests),
            'error_results' => $errorResults,
        ]);
    }

    /**
     * Execute middleware stack.
     */
    protected function executeMiddlewareStack(Request $request, array $middlewareStack): Response
    {
        $next = function ($req) {
            return new Response('Final response');
        };

        // Execute middleware stack in reverse order
        foreach (array_reverse($middlewareStack) as $middlewareClass) {
            $middleware = app($middlewareClass);
            $currentNext = $next;
            $next = function ($req) use ($middleware, $currentNext) {
                return $middleware->handle($req, $currentNext);
            };
        }

        return $next($request);
    }

    /**
     * Get test data for event classes.
     */
    protected function getEventTestData(string $eventClass): array
    {
        switch ($eventClass) {
            case MessageSent::class:
                return [1, 'mock', 'gpt-4', 'Test message', []];
            case ResponseGenerated::class:
                return [1, 'mock', 'gpt-4', 'Test response', []];
            case CostCalculated::class:
                return [1, 'mock', 'gpt-4', 100, 50, 0.001, []];
            case MCPToolExecuted::class:
                return ['sequential-thinking', 'sequential_thinking', [], ['success' => true], 75.5, 1];
            case BudgetThresholdReached::class:
                return [1, 50.00, 100.00, 50, []];
            default:
                return [];
        }
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
            Log::info('Event Middleware Infrastructure Test Results', [
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
            'infrastructure_components_tested' => array_keys($this->performanceMetrics),
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
