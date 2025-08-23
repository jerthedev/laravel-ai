<?php

namespace JTD\LaravelAI\Tests\Performance;

use JTD\LaravelAI\Drivers\DriverTemplate\DriverTemplateDriver;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * DriverTemplate Driver Performance Benchmarking Tests
 *
 * Measures response times, memory usage, and throughput
 * for DriverTemplate driver operations to ensure performance standards.
 */
#[Group('performance')]
#[Group('drivertemplate')]
class DriverTemplateDriverPerformanceTest extends TestCase
{
    private DriverTemplateDriver $driver;
    private PerformanceBenchmark $benchmark;
    private array $performanceMetrics = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Load credentials from E2E credentials file
        $credentialsPath = __DIR__ . '/../credentials/e2e-credentials.json';

        if (!file_exists($credentialsPath)) {
            $this->markTestSkipped('E2E credentials file not found for performance testing');
        }

        $credentials = json_decode(file_get_contents($credentialsPath), true);

        if (empty($credentials['drivertemplate']['api_key']) || !$credentials['drivertemplate']['enabled']) {
            $this->markTestSkipped('DriverTemplate credentials not configured or disabled for performance testing');
        }

        $this->driver = new DriverTemplateDriver([
            'api_key' => $credentials['drivertemplate']['api_key'],
            'organization' => $credentials['drivertemplate']['organization'] ?? null,
            'project' => $credentials['drivertemplate']['project'] ?? null,
            'timeout' => 60, // Longer timeout for performance tests
        ]);

        $this->benchmark = new PerformanceBenchmark();
    }

    /**
     * Log a test step for debugging.
     */
    private function logTestStep(string $message): void
    {
        if (defined('STDOUT')) {
            fwrite(STDOUT, $message . "\n");
        }
    }

    protected function tearDown(): void
    {
        // Output performance summary
        if (!empty($this->performanceMetrics)) {
            $this->outputPerformanceSummary();
        }

        parent::tearDown();
    }

    #[Test]
    public function it_measures_basic_message_response_time(): void
    {

        // TODO: Implement test
        });

        $this->logTestStep("✅ Response time: {$metrics['response_time']}ms");
        $this->logTestStep("📊 Memory usage: {$metrics['memory_usage']}MB");
        $this->logTestStep("🔤 Response length: " . (isset($metrics['response_length']) ? $metrics['response_length'] : 'N/A') . " chars");

        // Performance assertions
        $this->assertLessThan(5000, $metrics['response_time'], 'Basic message should respond within 5 seconds');
        $this->assertLessThan(10, $metrics['memory_usage'], 'Memory usage should be under 10MB');
        $this->assertGreaterThan(0, $metrics['response_length'], 'Should receive a response');
    }

    #[Test]
    public function it_measures_model_listing_performance(): void
    {

        // TODO: Implement test
        });

        $this->logTestStep("✅ Response time: {$metrics['response_time']}ms");
        $this->logTestStep("📊 Memory usage: {$metrics['memory_usage']}MB");
        $this->logTestStep("🔢 Models count: {$metrics['models_count']}");

        // Performance assertions
        $this->assertLessThan(3000, $metrics['response_time'], 'Model listing should complete within 3 seconds');
        $this->assertLessThan(5, $metrics['memory_usage'], 'Memory usage should be under 5MB');
        $this->assertGreaterThan(0, $metrics['models_count'], 'Should return available models');
    }

    #[Test]
    public function it_measures_cost_calculation_performance(): void
    {

        // TODO: Implement test
        });

        $this->logTestStep("✅ Response time: {$metrics['response_time']}ms");
        $this->logTestStep("📊 Memory usage: {$metrics['memory_usage']}MB");
        $this->logTestStep("💰 Estimated cost: ${$metrics['estimated_cost']}");

        // Performance assertions
        $this->assertLessThan(100, $metrics['response_time'], 'Cost calculation should complete within 100ms');
        $this->assertLessThan(1, $metrics['memory_usage'], 'Memory usage should be under 1MB');
        $this->assertGreaterThan(0, $metrics['estimated_cost'], 'Should calculate a cost');
    }

    #[Test]
    public function it_measures_health_check_performance(): void
    {

        // TODO: Implement test
        });

        $this->logTestStep("✅ Response time: {$metrics['response_time']}ms");
        $this->logTestStep("📊 Memory usage: {$metrics['memory_usage']}MB");
        $this->logTestStep("🏥 Health status: {$metrics['health_status']}");

        // Performance assertions
        $this->assertLessThan(5000, $metrics['response_time'], 'Health check should complete within 5 seconds');
        $this->assertLessThan(5, $metrics['memory_usage'], 'Memory usage should be under 5MB');
        $this->assertNotEmpty($metrics['health_status'], 'Should return health status');
    }

    #[Test]
    public function it_measures_concurrent_request_throughput(): void
    {

        // TODO: Implement test
            };
        }

        // Execute requests sequentially (simulating concurrent behavior)
        $responses = [];
        foreach ($promises as $promise) {
            $responses[] = $promise();
        }

        $totalTime = (microtime(true) - $startTime) * 1000;
        $memoryUsage = (memory_get_usage(true) - $startMemory) / 1024 / 1024;
        $throughput = $concurrentRequests / ($totalTime / 1000);

        $this->logTestStep("✅ Total time: {$totalTime}ms");
        $this->logTestStep("📊 Memory usage: {$memoryUsage}MB");
        $this->logTestStep("⚡ Throughput: {$throughput} requests/second");
        $this->logTestStep("📈 Average per request: " . ($totalTime / $concurrentRequests) . "ms");

        $this->performanceMetrics['concurrent_throughput'] = [
            'total_time' => $totalTime,
            'memory_usage' => $memoryUsage,
            'throughput' => $throughput,
            'requests' => $concurrentRequests,
        ];

        // Performance assertions
        $this->assertLessThan(15000, $totalTime, 'Concurrent requests should complete within 15 seconds');
        $this->assertLessThan(20, $memoryUsage, 'Memory usage should be under 20MB');
        $this->assertGreaterThan(0.1, $throughput, 'Should achieve at least 0.1 requests/second');
        $this->assertCount($concurrentRequests, $responses, 'Should receive all responses');
    }

    #[Test]
    public function it_measures_memory_usage_over_time(): void
    {

        // TODO: Implement test
        }

        $avgMemoryUsage = array_sum(array_column($memorySnapshots, 'diff')) / $iterations;
        $maxMemoryUsage = max(array_column($memorySnapshots, 'diff'));

        $this->logTestStep("✅ Average memory per request: {$avgMemoryUsage}MB");
        $this->logTestStep("📊 Maximum memory per request: {$maxMemoryUsage}MB");

        $this->performanceMetrics['memory_over_time'] = [
            'snapshots' => $memorySnapshots,
            'average_usage' => $avgMemoryUsage,
            'max_usage' => $maxMemoryUsage,
        ];

        // Performance assertions
        $this->assertLessThan(5, $avgMemoryUsage, 'Average memory usage should be under 5MB per request');
        $this->assertLessThan(10, $maxMemoryUsage, 'Maximum memory usage should be under 10MB per request');
    }

    /**
     * Measure performance of a callable operation.
     */
    private function measurePerformance(string $operation, callable $callback): array
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        $result = $callback();

        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);

        $responseTime = ($endTime - $startTime) * 1000; // Convert to milliseconds
        $memoryUsage = ($endMemory - $startMemory) / 1024 / 1024; // Convert to MB

        $metrics = [
            'operation' => $operation,
            'response_time' => round($responseTime, 2),
            'memory_usage' => round($memoryUsage, 2),
            'timestamp' => now()->toISOString(),
        ];

        // Add operation-specific metrics
        if (is_object($result)) {
            if (method_exists($result, 'content')) {
                $metrics['response_length'] = strlen($result->content ?? '');
            }
            if (property_exists($result, 'model')) {
                $metrics['model'] = $result->model ?? null;
            }
        } elseif (is_array($result)) {
            if (isset($result[0]['id'])) {
                $metrics['models_count'] = count($result);
            }
            if (isset($result['estimated_total_cost'])) {
                $metrics['estimated_cost'] = $result['estimated_total_cost'];
            }
            if (isset($result['status'])) {
                $metrics['health_status'] = $result['status'];
            }
        }

        $this->performanceMetrics[$operation] = $metrics;

        return $metrics;
    }

    /**
     * Output performance summary at the end of tests.
     */
    private function outputPerformanceSummary(): void
    {
        $this->logTestStep('');
        $this->logTestStep('📊 PERFORMANCE SUMMARY');
        $this->logTestStep('========================');

        foreach ($this->performanceMetrics as $operation => $metrics) {
            if (is_array($metrics) && isset($metrics['response_time'])) {
                $this->logTestStep("🔹 {$operation}: {$metrics['response_time']}ms, {$metrics['memory_usage']}MB");
            }
        }

        // Calculate overall statistics
        $responseTimes = array_column($this->performanceMetrics, 'response_time');
        $memoryUsages = array_column($this->performanceMetrics, 'memory_usage');

        if (!empty($responseTimes)) {
            $avgResponseTime = array_sum($responseTimes) / count($responseTimes);
            $maxResponseTime = max($responseTimes);
            $avgMemoryUsage = array_sum($memoryUsages) / count($memoryUsages);
            $maxMemoryUsage = max($memoryUsages);

            $this->logTestStep('');
            $this->logTestStep("📈 Average Response Time: {$avgResponseTime}ms");
            $this->logTestStep("⏱️  Maximum Response Time: {$maxResponseTime}ms");
            $this->logTestStep("🧠 Average Memory Usage: {$avgMemoryUsage}MB");
            $this->logTestStep("💾 Maximum Memory Usage: {$maxMemoryUsage}MB");
        }

        $this->logTestStep('========================');
    }

    #[Test]
    public function it_benchmarks_streaming_performance(): void
    {

        // TODO: Implement test
                }
                $chunks[] = $chunk;
            }

            return [
                'chunks' => $chunks,
                'first_chunk_time' => $firstChunkTime,
                'total_chunks' => count($chunks),
            ];
        });

        $this->logTestStep("✅ Total time: {$metrics['response_time']}ms");
        $this->logTestStep("📊 Memory usage: {$metrics['memory_usage']}MB");
        $this->logTestStep("⚡ First chunk: {$metrics['result_summary']['first_chunk_time']}ms");
        $this->logTestStep("📦 Total chunks: {$metrics['result_summary']['total_chunks']}");

        // Performance assertions
        $this->assertLessThan(10000, $metrics['response_time'], 'Streaming should complete within 10 seconds');
        $this->assertLessThan(15, $metrics['memory_usage'], 'Memory usage should be under 15MB');
        $this->assertTrue($metrics['success'], 'Streaming should succeed');
    }

    #[Test]
    public function it_benchmarks_function_calling_performance(): void
    {

        // TODO: Implement test
        });

        $this->logTestStep("✅ Response time: {$metrics['response_time']}ms");
        $this->logTestStep("📊 Memory usage: {$metrics['memory_usage']}MB");

        // Performance assertions
        $this->assertLessThan(10000, $metrics['response_time'], 'Function calling should complete within 10 seconds');
        $this->assertLessThan(15, $metrics['memory_usage'], 'Memory usage should be under 15MB');
        $this->assertTrue($metrics['success'], 'Function calling should succeed');
    }

    #[Test]
    public function it_measures_driver_initialization_performance(): void
    {

        // TODO: Implement test
        }, 10);

        $this->logTestStep("✅ Average initialization: {$metrics['response_time']['avg']}ms");
        $this->logTestStep("📊 Average memory: {$metrics['memory_usage']['avg']}MB");
        $this->logTestStep("📈 Success rate: {$metrics['success_rate']}%");

        // Performance assertions
        $this->assertLessThan(100, $metrics['response_time']['avg'], 'Driver initialization should be under 100ms on average');
        $this->assertLessThan(2, $metrics['memory_usage']['avg'], 'Memory usage should be under 2MB on average');
        $this->assertEquals(100, $metrics['success_rate'], 'All initializations should succeed');
    }

    #[Test]
    public function it_measures_token_estimation_performance(): void
    {

        // TODO: Implement test
            });

            $this->logTestStep("  Message " . ($index + 1) . " ({$metrics['response_time']}ms): {$metrics['result_summary']['value']} tokens");
        }

        // Get the last metrics for assertions
        $lastMetrics = end($this->benchmark->getBenchmarks());

        // Performance assertions
        $this->assertLessThan(50, $lastMetrics['response_time'], 'Token estimation should be under 50ms');
        $this->assertLessThan(1, $lastMetrics['memory_usage'], 'Memory usage should be under 1MB');
        $this->assertTrue($lastMetrics['success'], 'Token estimation should succeed');
    }
}
