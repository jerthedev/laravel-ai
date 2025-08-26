<?php

namespace JTD\LaravelAI\Tests\Performance;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use JTD\LaravelAI\Services\MCPManager;
use JTD\LaravelAI\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * MCP Performance and Load Tests
 *
 * Comprehensive performance testing ensuring MCP processing meets
 * <100ms built-in and <500ms external server benchmarks with load testing.
 */
#[Group('performance')]
#[Group('mcp-performance')]
class MCPPerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected MCPManager $mcpManager;

    protected array $performanceMetrics = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->mcpManager = app(MCPManager::class);
        $this->setupPerformanceConfiguration();
        $this->performanceMetrics = [];
    }

    protected function tearDown(): void
    {
        $this->logPerformanceMetrics();
        parent::tearDown();
    }

    #[Test]
    public function it_meets_built_in_tool_performance_targets(): void
    {
        $builtInTools = ['sequential_thinking'];
        $targetTime = 100; // 100ms target

        foreach ($builtInTools as $tool) {
            $executionTimes = [];

            // Run multiple iterations for statistical accuracy
            for ($i = 0; $i < 10; $i++) {
                $startTime = microtime(true);

                $result = $this->mcpManager->executeTool($tool, [
                    'thought' => "Performance test iteration {$i}",
                    'nextThoughtNeeded' => false,
                    'thoughtNumber' => 1,
                    'totalThoughts' => 1,
                ]);

                $executionTime = (microtime(true) - $startTime) * 1000;
                $executionTimes[] = $executionTime;

                $this->assertTrue($result['success'], "Built-in tool {$tool} failed on iteration {$i}");
            }

            $avgTime = array_sum($executionTimes) / count($executionTimes);
            $maxTime = max($executionTimes);
            $minTime = min($executionTimes);

            $this->recordMetric("built_in_{$tool}", [
                'average_ms' => $avgTime,
                'max_ms' => $maxTime,
                'min_ms' => $minTime,
                'target_ms' => $targetTime,
                'iterations' => count($executionTimes),
            ]);

            // Performance assertions
            $this->assertLessThan($targetTime, $avgTime,
                "Built-in tool {$tool} average time {$avgTime}ms exceeds {$targetTime}ms target");

            $this->assertLessThan($targetTime * 1.5, $maxTime,
                "Built-in tool {$tool} max time {$maxTime}ms exceeds acceptable variance");
        }
    }

    #[Test]
    public function it_meets_external_server_performance_targets(): void
    {
        $externalTools = $this->getAvailableExternalTools();
        $targetTime = 500; // 500ms target

        if (empty($externalTools)) {
            $this->markTestSkipped('No external MCP servers configured for performance testing');
        }

        foreach ($externalTools as $tool) {
            $executionTimes = [];

            // Run fewer iterations for external servers due to network latency
            for ($i = 0; $i < 5; $i++) {
                $startTime = microtime(true);

                $result = $this->mcpManager->executeTool($tool, $this->getToolParameters($tool));

                $executionTime = (microtime(true) - $startTime) * 1000;
                $executionTimes[] = $executionTime;

                if (! $result['success']) {
                    Log::warning("External tool {$tool} failed on iteration {$i}", [
                        'error' => $result['error'] ?? 'Unknown error',
                        'execution_time_ms' => $executionTime,
                    ]);

                    continue; // Don't fail test for external server issues
                }
            }

            if (empty($executionTimes)) {
                $this->markTestSkipped("External tool {$tool} failed all iterations");

                continue;
            }

            $avgTime = array_sum($executionTimes) / count($executionTimes);
            $maxTime = max($executionTimes);
            $minTime = min($executionTimes);

            $this->recordMetric("external_{$tool}", [
                'average_ms' => $avgTime,
                'max_ms' => $maxTime,
                'min_ms' => $minTime,
                'target_ms' => $targetTime,
                'iterations' => count($executionTimes),
                'success_rate' => (count($executionTimes) / 5) * 100,
            ]);

            // Performance assertions with more lenient targets for external servers
            $this->assertLessThan($targetTime, $avgTime,
                "External tool {$tool} average time {$avgTime}ms exceeds {$targetTime}ms target");
        }
    }

    #[Test]
    public function it_handles_concurrent_load_testing(): void
    {
        $concurrentRequests = 10;
        $tool = 'sequential_thinking';
        $targetTotalTime = 1000; // 1 second for all concurrent requests

        $startTime = microtime(true);
        $promises = [];

        // Simulate concurrent requests
        for ($i = 0; $i < $concurrentRequests; $i++) {
            $promises[] = function () use ($tool, $i) {
                $requestStart = microtime(true);

                $result = $this->mcpManager->executeTool($tool, [
                    'thought' => "Concurrent load test request {$i}",
                    'nextThoughtNeeded' => false,
                    'thoughtNumber' => 1,
                    'totalThoughts' => 1,
                ]);

                $requestTime = (microtime(true) - $requestStart) * 1000;

                return [
                    'success' => $result['success'],
                    'execution_time' => $requestTime,
                    'request_id' => $i,
                ];
            };
        }

        // Execute all concurrent requests
        $results = array_map(fn ($promise) => $promise(), $promises);
        $totalTime = (microtime(true) - $startTime) * 1000;

        // Analyze results
        $successfulRequests = array_filter($results, fn ($r) => $r['success']);
        $executionTimes = array_column($successfulRequests, 'execution_time');

        $avgTime = count($executionTimes) > 0 ? array_sum($executionTimes) / count($executionTimes) : 0;
        $maxTime = count($executionTimes) > 0 ? max($executionTimes) : 0;
        $successRate = (count($successfulRequests) / $concurrentRequests) * 100;

        $this->recordMetric('concurrent_load_test', [
            'concurrent_requests' => $concurrentRequests,
            'total_time_ms' => $totalTime,
            'average_request_time_ms' => $avgTime,
            'max_request_time_ms' => $maxTime,
            'success_rate_percent' => $successRate,
            'target_total_time_ms' => $targetTotalTime,
        ]);

        // Performance assertions
        $this->assertLessThan($targetTotalTime, $totalTime,
            "Concurrent load test took {$totalTime}ms, exceeding {$targetTotalTime}ms target");

        $this->assertGreaterThanOrEqual(90, $successRate,
            "Success rate {$successRate}% is below 90% threshold");

        $this->assertLessThan(200, $avgTime,
            "Average concurrent request time {$avgTime}ms exceeds 200ms threshold");
    }

    #[Test]
    public function it_performs_stress_testing_with_increasing_load(): void
    {
        $loadLevels = [1, 5, 10, 20];
        $tool = 'sequential_thinking';
        $results = [];

        foreach ($loadLevels as $load) {
            $startTime = microtime(true);
            $successCount = 0;
            $executionTimes = [];

            for ($i = 0; $i < $load; $i++) {
                $requestStart = microtime(true);

                $result = $this->mcpManager->executeTool($tool, [
                    'thought' => "Stress test load {$load} request {$i}",
                    'nextThoughtNeeded' => false,
                    'thoughtNumber' => 1,
                    'totalThoughts' => 1,
                ]);

                $requestTime = (microtime(true) - $requestStart) * 1000;
                $executionTimes[] = $requestTime;

                if ($result['success']) {
                    $successCount++;
                }
            }

            $totalTime = (microtime(true) - $startTime) * 1000;
            $avgTime = array_sum($executionTimes) / count($executionTimes);
            $successRate = ($successCount / $load) * 100;

            $results[$load] = [
                'load_level' => $load,
                'total_time_ms' => $totalTime,
                'average_request_time_ms' => $avgTime,
                'success_rate_percent' => $successRate,
                'throughput_requests_per_second' => $load / ($totalTime / 1000),
            ];

            $this->recordMetric("stress_test_load_{$load}", $results[$load]);
        }

        // Analyze stress test results
        $this->analyzeStressTestResults($results);
    }

    #[Test]
    #[DataProvider('toolParameterProvider')]
    public function it_tests_performance_with_different_parameters(string $tool, array $parameters, int $expectedMaxTime): void
    {
        $iterations = 5;
        $executionTimes = [];

        for ($i = 0; $i < $iterations; $i++) {
            $startTime = microtime(true);

            $result = $this->mcpManager->executeTool($tool, $parameters);

            $executionTime = (microtime(true) - $startTime) * 1000;
            $executionTimes[] = $executionTime;

            if ($result['success']) {
                $this->assertIsArray($result['result'], "Tool {$tool} should return valid result");
            }
        }

        $avgTime = array_sum($executionTimes) / count($executionTimes);
        $maxTime = max($executionTimes);

        $this->recordMetric("parameter_test_{$tool}", [
            'parameters' => $parameters,
            'average_ms' => $avgTime,
            'max_ms' => $maxTime,
            'expected_max_ms' => $expectedMaxTime,
            'iterations' => $iterations,
        ]);

        $this->assertLessThan($expectedMaxTime, $avgTime,
            "Tool {$tool} with specific parameters took {$avgTime}ms, exceeding {$expectedMaxTime}ms target");
    }

    #[Test]
    public function it_measures_memory_usage_during_execution(): void
    {
        $tool = 'sequential_thinking';
        $iterations = 10;
        $memoryUsages = [];

        for ($i = 0; $i < $iterations; $i++) {
            $memoryBefore = memory_get_usage(true);

            $result = $this->mcpManager->executeTool($tool, [
                'thought' => "Memory test iteration {$i}",
                'nextThoughtNeeded' => false,
                'thoughtNumber' => 1,
                'totalThoughts' => 1,
            ]);

            $memoryAfter = memory_get_usage(true);
            $memoryUsed = $memoryAfter - $memoryBefore;
            $memoryUsages[] = $memoryUsed;

            $this->assertTrue($result['success'], "Memory test iteration {$i} failed");
        }

        $avgMemory = array_sum($memoryUsages) / count($memoryUsages);
        $maxMemory = max($memoryUsages);
        $totalMemory = memory_get_peak_usage(true);

        $this->recordMetric('memory_usage', [
            'average_bytes' => $avgMemory,
            'max_bytes' => $maxMemory,
            'peak_total_bytes' => $totalMemory,
            'average_mb' => round($avgMemory / 1024 / 1024, 2),
            'max_mb' => round($maxMemory / 1024 / 1024, 2),
            'peak_total_mb' => round($totalMemory / 1024 / 1024, 2),
        ]);

        // Memory usage assertions
        $this->assertLessThan(10 * 1024 * 1024, $avgMemory,
            "Average memory usage {$avgMemory} bytes exceeds 10MB threshold");

        $this->assertLessThan(50 * 1024 * 1024, $maxMemory,
            "Max memory usage {$maxMemory} bytes exceeds 50MB threshold");
    }

    /**
     * Data provider for tool parameter testing.
     */
    public static function toolParameterProvider(): array
    {
        return [
            'sequential_thinking_simple' => [
                'sequential_thinking',
                [
                    'thought' => 'Simple thought',
                    'nextThoughtNeeded' => false,
                    'thoughtNumber' => 1,
                    'totalThoughts' => 1,
                ],
                100, // 100ms expected max
            ],
            'sequential_thinking_complex' => [
                'sequential_thinking',
                [
                    'thought' => 'Complex multi-step analysis with detailed reasoning and comprehensive evaluation',
                    'nextThoughtNeeded' => true,
                    'thoughtNumber' => 1,
                    'totalThoughts' => 5,
                ],
                150, // 150ms expected max for complex
            ],
        ];
    }

    /**
     * Get available external tools for testing.
     */
    protected function getAvailableExternalTools(): array
    {
        $configuredTools = config('ai.mcp.servers', []);
        $externalTools = [];

        foreach ($configuredTools as $tool => $config) {
            if (($config['type'] ?? '') === 'external' && ($config['enabled'] ?? false)) {
                $externalTools[] = $tool;
            }
        }

        return $externalTools;
    }

    /**
     * Get tool-specific parameters.
     */
    protected function getToolParameters(string $tool): array
    {
        return match ($tool) {
            'brave_search' => [
                'query' => 'Laravel performance testing',
                'count' => 3,
            ],
            'github_mcp' => [
                'action' => 'search_repositories',
                'query' => 'laravel',
                'limit' => 5,
            ],
            default => [
                'test' => 'performance_parameter',
            ],
        };
    }

    /**
     * Analyze stress test results.
     */
    protected function analyzeStressTestResults(array $results): void
    {
        $loadLevels = array_keys($results);
        $throughputs = array_column($results, 'throughput_requests_per_second');
        $avgTimes = array_column($results, 'average_request_time_ms');

        // Check for performance degradation
        for ($i = 1; $i < count($loadLevels); $i++) {
            $currentThroughput = $throughputs[$i];
            $previousThroughput = $throughputs[$i - 1];

            // Allow some degradation but not more than 50%
            $degradationThreshold = $previousThroughput * 0.5;

            $this->assertGreaterThan($degradationThreshold, $currentThroughput,
                "Throughput degraded too much at load level {$loadLevels[$i]}: {$currentThroughput} vs {$previousThroughput}");
        }

        // Check that response times don't increase exponentially
        $maxAcceptableTime = 300; // 300ms max even under stress
        foreach ($avgTimes as $i => $avgTime) {
            $this->assertLessThan($maxAcceptableTime, $avgTime,
                "Average response time {$avgTime}ms at load {$loadLevels[$i]} exceeds {$maxAcceptableTime}ms threshold");
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
            Log::info('MCP Performance Test Results', [
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
            'built_in_tools' => [],
            'external_tools' => [],
            'load_tests' => [],
        ];

        foreach ($this->performanceMetrics as $name => $data) {
            if (str_starts_with($name, 'built_in_')) {
                $summary['built_in_tools'][] = [
                    'tool' => str_replace('built_in_', '', $name),
                    'avg_ms' => $data['average_ms'] ?? 0,
                    'target_met' => ($data['average_ms'] ?? 0) < ($data['target_ms'] ?? 100),
                ];
            } elseif (str_starts_with($name, 'external_')) {
                $summary['external_tools'][] = [
                    'tool' => str_replace('external_', '', $name),
                    'avg_ms' => $data['average_ms'] ?? 0,
                    'target_met' => ($data['average_ms'] ?? 0) < ($data['target_ms'] ?? 500),
                ];
            } elseif (str_contains($name, 'load_test') || str_contains($name, 'stress_test')) {
                $summary['load_tests'][] = [
                    'test' => $name,
                    'success_rate' => $data['success_rate_percent'] ?? 0,
                    'avg_ms' => $data['average_request_time_ms'] ?? 0,
                ];
            }
        }

        return $summary;
    }

    /**
     * Setup performance testing configuration.
     */
    protected function setupPerformanceConfiguration(): void
    {
        config([
            'ai.mcp.enabled' => true,
            'ai.mcp.timeout' => 30,
            'ai.mcp.max_concurrent' => 20,
            'ai.mcp.performance_monitoring' => true,
        ]);

        // Clear any existing cache
        Cache::flush();
    }
}
