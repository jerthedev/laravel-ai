<?php

namespace JTD\LaravelAI\Tests\Performance;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use JTD\LaravelAI\Events\ResponseGenerated;
use JTD\LaravelAI\Listeners\AnalyticsListener;
use JTD\LaravelAI\Listeners\CostTrackingListener;
use JTD\LaravelAI\Listeners\NotificationListener;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Models\AIResponse;
use JTD\LaravelAI\Services\AIManager;
use JTD\LaravelAI\Services\ConversationBuilder;
use JTD\LaravelAI\Services\MiddlewareManager;
use JTD\LaravelAI\Services\PricingService;
use JTD\LaravelAI\Tests\TestCase;

class PerformanceBenchmarkTest extends TestCase
{
    protected array $performanceResults = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Enable all systems for performance testing
        config([
            'ai.events.enabled' => true,
            'ai.middleware.enabled' => true,
            'ai.events.listeners.cost_tracking.enabled' => true,
            'ai.events.listeners.analytics.enabled' => true,
            'ai.events.listeners.notifications.enabled' => true,
        ]);

        $this->performanceResults = [];
    }

    public function test_middleware_performance_overhead()
    {
        $iterations = 100;
        $results = [];

        // Test without middleware
        config(['ai.middleware.enabled' => false]);
        $timeWithoutMiddleware = $this->benchmarkConversationFlow($iterations);
        $results['without_middleware'] = $timeWithoutMiddleware;

        // Test with middleware
        config(['ai.middleware.enabled' => true]);
        $timeWithMiddleware = $this->benchmarkConversationFlow($iterations);
        $results['with_middleware'] = $timeWithMiddleware;

        // Calculate overhead
        $overhead = $timeWithMiddleware - $timeWithoutMiddleware;
        $overheadPercentage = ($overhead / $timeWithoutMiddleware) * 100;

        $this->performanceResults['middleware_overhead'] = [
            'without_middleware_ms' => $timeWithoutMiddleware * 1000,
            'with_middleware_ms' => $timeWithMiddleware * 1000,
            'overhead_ms' => $overhead * 1000,
            'overhead_percentage' => $overheadPercentage,
        ];

        // Middleware overhead should be minimal (less than 50% increase)
        $this->assertLessThan(50, $overheadPercentage,
            "Middleware overhead is too high: {$overheadPercentage}%");

        $this->logPerformanceResult('Middleware Overhead', $this->performanceResults['middleware_overhead']);
    }

    public function test_event_system_performance_overhead()
    {
        $iterations = 100;

        // Test without events
        config(['ai.events.enabled' => false]);
        $timeWithoutEvents = $this->benchmarkConversationFlow($iterations);

        // Test with events
        config(['ai.events.enabled' => true]);
        $timeWithEvents = $this->benchmarkConversationFlow($iterations);

        // Calculate overhead
        $overhead = $timeWithEvents - $timeWithoutEvents;
        $overheadPercentage = ($overhead / $timeWithoutEvents) * 100;

        $this->performanceResults['event_overhead'] = [
            'without_events_ms' => $timeWithoutEvents * 1000,
            'with_events_ms' => $timeWithEvents * 1000,
            'overhead_ms' => $overhead * 1000,
            'overhead_percentage' => $overheadPercentage,
        ];

        // Event overhead should be minimal (less than 30% increase)
        $this->assertLessThan(30, $overheadPercentage,
            "Event system overhead is too high: {$overheadPercentage}%");

        $this->logPerformanceResult('Event System Overhead', $this->performanceResults['event_overhead']);
    }

    public function test_listener_execution_performance()
    {
        Queue::fake(); // Prevent actual queue processing

        $message = AIMessage::user('Performance test message');
        $message->user_id = 1;
        $message->conversation_id = 1;

        $response = AIResponse::fromArray([
            'content' => 'Performance test response',
            'provider' => 'mock',
            'model' => 'mock-model',
            'token_usage' => [
                'input_tokens' => 100,
                'output_tokens' => 50,
                'total_tokens' => 150,
            ],
        ]);

        $event = new ResponseGenerated(
            message: $message,
            response: $response,
            context: [],
            totalProcessingTime: 1.0,
            providerMetadata: [
                'provider' => 'mock',
                'model' => 'mock-model',
                'tokens_used' => 150,
            ]
        );

        $iterations = 1000;
        $listenerResults = [];

        // Benchmark CostTrackingListener
        $costListener = app(CostTrackingListener::class);
        $startTime = microtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            $costListener->handle($event);
        }
        $costListenerTime = microtime(true) - $startTime;
        $listenerResults['cost_tracking'] = $costListenerTime / $iterations;

        // Benchmark AnalyticsListener
        $analyticsListener = app(AnalyticsListener::class);
        $startTime = microtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            $analyticsListener->handle($event);
        }
        $analyticsListenerTime = microtime(true) - $startTime;
        $listenerResults['analytics'] = $analyticsListenerTime / $iterations;

        // Benchmark NotificationListener
        $notificationListener = app(NotificationListener::class);
        $startTime = microtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            $notificationListener->handleResponseGenerated($event);
        }
        $notificationListenerTime = microtime(true) - $startTime;
        $listenerResults['notification'] = $notificationListenerTime / $iterations;

        $this->performanceResults['listener_performance'] = [
            'cost_tracking_ms' => $listenerResults['cost_tracking'] * 1000,
            'analytics_ms' => $listenerResults['analytics'] * 1000,
            'notification_ms' => $listenerResults['notification'] * 1000,
            'iterations' => $iterations,
        ];

        // Each listener should execute quickly (under 10ms per execution)
        $this->assertLessThan(0.01, $listenerResults['cost_tracking'],
            'CostTrackingListener is too slow');
        $this->assertLessThan(0.01, $listenerResults['analytics'],
            'AnalyticsListener is too slow');
        $this->assertLessThan(0.01, $listenerResults['notification'],
            'NotificationListener is too slow');

        $this->logPerformanceResult('Listener Performance', $this->performanceResults['listener_performance']);
    }

    public function test_pricing_service_performance()
    {
        $pricingService = app(PricingService::class);
        $iterations = 1000;

        // Benchmark cost calculations
        $startTime = microtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            $pricingService->calculateCost('openai', 'gpt-4o-mini', 100, 50);
        }
        $calculationTime = microtime(true) - $startTime;

        // Benchmark pricing lookups
        $startTime = microtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            $pricingService->getModelPricing('openai', 'gpt-4o-mini');
        }
        $lookupTime = microtime(true) - $startTime;

        $this->performanceResults['pricing_service'] = [
            'calculation_time_ms' => ($calculationTime / $iterations) * 1000,
            'lookup_time_ms' => ($lookupTime / $iterations) * 1000,
            'iterations' => $iterations,
        ];

        // Pricing operations should be fast (under 1ms per operation)
        $this->assertLessThan(0.001, $calculationTime / $iterations,
            'Pricing calculations are too slow');
        $this->assertLessThan(0.001, $lookupTime / $iterations,
            'Pricing lookups are too slow');

        $this->logPerformanceResult('Pricing Service Performance', $this->performanceResults['pricing_service']);
    }

    public function test_memory_usage_during_processing()
    {
        $initialMemory = memory_get_usage(true);
        $peakMemory = memory_get_peak_usage(true);

        // Process multiple conversations to test memory usage
        $iterations = 50;
        for ($i = 0; $i < $iterations; $i++) {
            $this->processTestConversation();
        }

        $finalMemory = memory_get_usage(true);
        $finalPeakMemory = memory_get_peak_usage(true);

        $memoryIncrease = $finalMemory - $initialMemory;
        $peakMemoryIncrease = $finalPeakMemory - $peakMemory;

        $this->performanceResults['memory_usage'] = [
            'initial_memory_mb' => $initialMemory / 1024 / 1024,
            'final_memory_mb' => $finalMemory / 1024 / 1024,
            'memory_increase_mb' => $memoryIncrease / 1024 / 1024,
            'peak_memory_increase_mb' => $peakMemoryIncrease / 1024 / 1024,
            'iterations' => $iterations,
        ];

        // Memory increase should be reasonable (less than 50MB for 50 iterations)
        $this->assertLessThan(50 * 1024 * 1024, $memoryIncrease,
            'Memory usage increase is too high');

        $this->logPerformanceResult('Memory Usage', $this->performanceResults['memory_usage']);
    }

    public function test_concurrent_processing_performance()
    {
        $concurrentRequests = 10;
        $startTime = microtime(true);

        // Simulate concurrent processing
        $promises = [];
        for ($i = 0; $i < $concurrentRequests; $i++) {
            $promises[] = $this->processTestConversation();
        }

        $totalTime = microtime(true) - $startTime;
        $averageTime = $totalTime / $concurrentRequests;

        $this->performanceResults['concurrent_processing'] = [
            'concurrent_requests' => $concurrentRequests,
            'total_time_ms' => $totalTime * 1000,
            'average_time_ms' => $averageTime * 1000,
            'requests_per_second' => $concurrentRequests / $totalTime,
        ];

        // Should handle concurrent requests efficiently
        $this->assertGreaterThan(5, $concurrentRequests / $totalTime,
            'Concurrent processing is too slow');

        $this->logPerformanceResult('Concurrent Processing', $this->performanceResults['concurrent_processing']);
    }

    protected function benchmarkConversationFlow(int $iterations): float
    {
        $this->mockProvider('benchmark', function ($messages, $options) {
            return AIResponse::fromArray([
                'content' => 'Benchmark response',
                'provider' => 'benchmark',
                'model' => 'benchmark-model',
                'token_usage' => [
                    'input_tokens' => 50,
                    'output_tokens' => 25,
                    'total_tokens' => 75,
                ],
            ]);
        });

        $startTime = microtime(true);

        for ($i = 0; $i < $iterations; $i++) {
            $conversation = app(ConversationBuilder::class);
            $response = $conversation
                ->provider('benchmark')
                ->model('benchmark-model')
                ->message("Benchmark message {$i}")
                ->send();
        }

        return (microtime(true) - $startTime) / $iterations;
    }

    protected function processTestConversation()
    {
        $this->mockProvider('memory', function ($messages, $options) {
            return AIResponse::fromArray([
                'content' => 'Memory test response',
                'provider' => 'memory',
                'model' => 'memory-model',
                'token_usage' => [
                    'input_tokens' => 100,
                    'output_tokens' => 50,
                    'total_tokens' => 150,
                ],
            ]);
        });

        $conversation = app(ConversationBuilder::class);
        return $conversation
            ->provider('memory')
            ->model('memory-model')
            ->message('Memory test message')
            ->send();
    }

    protected function mockProvider(string $name, callable $handler)
    {
        $this->app['config']->set("ai.providers.{$name}", [
            'driver' => 'mock',
            'enabled' => true,
        ]);

        $mockProvider = new class($handler) {
            private $handler;

            public function __construct(callable $handler) {
                $this->handler = $handler;
            }

            public function sendMessage($messages, $options = []) {
                return ($this->handler)($messages, $options);
            }

            public function getName() { return 'mock'; }
            public function getModel() { return 'mock-model'; }
            public function setModel($model) { return $this; }
            public function setOptions($options) { return $this; }
        };

        $this->app['laravel-ai.driver']->extend($name, function () use ($mockProvider) {
            return $mockProvider;
        });
    }

    protected function logPerformanceResult(string $testName, array $results)
    {
        $output = "\n" . str_repeat('=', 60) . "\n";
        $output .= "PERFORMANCE BENCHMARK: {$testName}\n";
        $output .= str_repeat('=', 60) . "\n";

        foreach ($results as $key => $value) {
            if (is_numeric($value)) {
                $output .= sprintf("%-30s: %s\n", ucwords(str_replace('_', ' ', $key)),
                    is_float($value) ? number_format($value, 4) : number_format($value));
            } else {
                $output .= sprintf("%-30s: %s\n", ucwords(str_replace('_', ' ', $key)), $value);
            }
        }

        $output .= str_repeat('=', 60) . "\n";

        // Output to console during testing
        fwrite(STDERR, $output);
    }

    protected function tearDown(): void
    {
        // Output final performance summary
        if (!empty($this->performanceResults)) {
            $this->outputPerformanceSummary();
        }

        parent::tearDown();
    }

    protected function outputPerformanceSummary()
    {
        $summary = "\n" . str_repeat('=', 80) . "\n";
        $summary .= "PERFORMANCE BENCHMARK SUMMARY\n";
        $summary .= str_repeat('=', 80) . "\n";

        foreach ($this->performanceResults as $testName => $results) {
            $summary .= ucwords(str_replace('_', ' ', $testName)) . ":\n";
            foreach ($results as $key => $value) {
                if (is_numeric($value)) {
                    $summary .= sprintf("  %-25s: %s\n", ucwords(str_replace('_', ' ', $key)),
                        is_float($value) ? number_format($value, 4) : number_format($value));
                }
            }
            $summary .= "\n";
        }

        $summary .= str_repeat('=', 80) . "\n";

        fwrite(STDERR, $summary);
    }
}
