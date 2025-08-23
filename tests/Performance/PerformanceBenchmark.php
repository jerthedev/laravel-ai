<?php

namespace JTD\LaravelAI\Tests\Performance;

/**
 * Performance Benchmarking Utility
 *
 * Provides utilities for measuring and analyzing performance
 * metrics across different operations and scenarios.
 */
class PerformanceBenchmark
{
    private array $benchmarks = [];

    private array $thresholds = [];

    public function __construct()
    {
        $this->setDefaultThresholds();
    }

    /**
     * Set default performance thresholds.
     */
    private function setDefaultThresholds(): void
    {
        $this->thresholds = [
            'basic_message' => [
                'response_time' => 5000, // 5 seconds
                'memory_usage' => 10,    // 10 MB
            ],
            'model_listing' => [
                'response_time' => 3000, // 3 seconds
                'memory_usage' => 5,     // 5 MB
            ],
            'cost_calculation' => [
                'response_time' => 100,  // 100 ms
                'memory_usage' => 1,     // 1 MB
            ],
            'health_check' => [
                'response_time' => 5000, // 5 seconds
                'memory_usage' => 5,     // 5 MB
            ],
            'streaming' => [
                'first_chunk_time' => 2000, // 2 seconds
                'memory_usage' => 15,        // 15 MB
            ],
            'function_calling' => [
                'response_time' => 10000, // 10 seconds
                'memory_usage' => 15,     // 15 MB
            ],
        ];
    }

    /**
     * Measure performance of a callable operation.
     */
    public function measure(string $operation, callable $callback, array $context = []): array
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);
        $startPeakMemory = memory_get_peak_usage(true);

        try {
            $result = $callback();
            $success = true;
            $error = null;
        } catch (\Exception $e) {
            $result = null;
            $success = false;
            $error = $e->getMessage();
        }

        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);
        $endPeakMemory = memory_get_peak_usage(true);

        $metrics = [
            'operation' => $operation,
            'success' => $success,
            'error' => $error,
            'response_time' => round(($endTime - $startTime) * 1000, 2), // ms
            'memory_usage' => round(($endMemory - $startMemory) / 1024 / 1024, 2), // MB
            'peak_memory_usage' => round(($endPeakMemory - $startPeakMemory) / 1024 / 1024, 2), // MB
            'timestamp' => now()->toISOString(),
            'context' => $context,
            'result_summary' => $this->summarizeResult($result),
        ];

        $this->benchmarks[] = $metrics;

        return $metrics;
    }

    /**
     * Measure multiple iterations of an operation.
     */
    public function measureIterations(string $operation, callable $callback, int $iterations = 5, array $context = []): array
    {
        $results = [];
        $totalStartTime = microtime(true);

        for ($i = 0; $i < $iterations; $i++) {
            $iterationContext = array_merge($context, ['iteration' => $i + 1]);
            $result = $this->measure("{$operation}_iteration_{$i}", $callback, $iterationContext);
            $results[] = $result;

            // Small delay to avoid overwhelming the API
            if ($i < $iterations - 1) {
                usleep(200000); // 0.2 seconds
            }
        }

        $totalTime = (microtime(true) - $totalStartTime) * 1000;

        // Calculate statistics
        $responseTimes = array_column($results, 'response_time');
        $memoryUsages = array_column($results, 'memory_usage');
        $successCount = count(array_filter($results, fn ($r) => $r['success']));

        $summary = [
            'operation' => $operation,
            'iterations' => $iterations,
            'total_time' => round($totalTime, 2),
            'success_rate' => round(($successCount / $iterations) * 100, 2),
            'response_time' => [
                'min' => min($responseTimes),
                'max' => max($responseTimes),
                'avg' => round(array_sum($responseTimes) / count($responseTimes), 2),
                'median' => $this->calculateMedian($responseTimes),
            ],
            'memory_usage' => [
                'min' => min($memoryUsages),
                'max' => max($memoryUsages),
                'avg' => round(array_sum($memoryUsages) / count($memoryUsages), 2),
                'median' => $this->calculateMedian($memoryUsages),
            ],
            'throughput' => round($iterations / ($totalTime / 1000), 2), // requests per second
            'results' => $results,
        ];

        $this->benchmarks[] = $summary;

        return $summary;
    }

    /**
     * Measure concurrent operations (simulated).
     */
    public function measureConcurrent(string $operation, callable $callback, int $concurrency = 3, array $context = []): array
    {
        $results = [];
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        // Simulate concurrent execution (sequential for API rate limits)
        for ($i = 0; $i < $concurrency; $i++) {
            $concurrentContext = array_merge($context, ['concurrent_id' => $i + 1]);
            $result = $this->measure("{$operation}_concurrent_{$i}", $callback, $concurrentContext);
            $results[] = $result;
        }

        $totalTime = (microtime(true) - $startTime) * 1000;
        $totalMemory = (memory_get_usage(true) - $startMemory) / 1024 / 1024;

        $responseTimes = array_column($results, 'response_time');
        $memoryUsages = array_column($results, 'memory_usage');
        $successCount = count(array_filter($results, fn ($r) => $r['success']));

        $summary = [
            'operation' => $operation,
            'concurrency' => $concurrency,
            'total_time' => round($totalTime, 2),
            'total_memory' => round($totalMemory, 2),
            'success_rate' => round(($successCount / $concurrency) * 100, 2),
            'throughput' => round($concurrency / ($totalTime / 1000), 2),
            'avg_response_time' => round(array_sum($responseTimes) / count($responseTimes), 2),
            'max_response_time' => max($responseTimes),
            'avg_memory_usage' => round(array_sum($memoryUsages) / count($memoryUsages), 2),
            'max_memory_usage' => max($memoryUsages),
            'results' => $results,
        ];

        $this->benchmarks[] = $summary;

        return $summary;
    }

    /**
     * Check if metrics meet performance thresholds.
     */
    public function checkThresholds(array $metrics): array
    {
        $operation = $metrics['operation'];
        $issues = [];

        if (! isset($this->thresholds[$operation])) {
            return ['passed' => true, 'issues' => []];
        }

        $thresholds = $this->thresholds[$operation];

        foreach ($thresholds as $metric => $threshold) {
            if (isset($metrics[$metric]) && $metrics[$metric] > $threshold) {
                $issues[] = [
                    'metric' => $metric,
                    'actual' => $metrics[$metric],
                    'threshold' => $threshold,
                    'message' => "{$metric} ({$metrics[$metric]}) exceeds threshold ({$threshold})",
                ];
            }
        }

        return [
            'passed' => empty($issues),
            'issues' => $issues,
        ];
    }

    /**
     * Generate performance report.
     */
    public function generateReport(): array
    {
        $report = [
            'summary' => [
                'total_benchmarks' => count($this->benchmarks),
                'timestamp' => now()->toISOString(),
            ],
            'benchmarks' => $this->benchmarks,
            'analysis' => $this->analyzePerformance(),
        ];

        return $report;
    }

    /**
     * Analyze performance trends and issues.
     */
    private function analyzePerformance(): array
    {
        $analysis = [
            'performance_issues' => [],
            'recommendations' => [],
            'trends' => [],
        ];

        foreach ($this->benchmarks as $benchmark) {
            if (isset($benchmark['operation'])) {
                $thresholdCheck = $this->checkThresholds($benchmark);
                if (! $thresholdCheck['passed']) {
                    $analysis['performance_issues'][] = [
                        'operation' => $benchmark['operation'],
                        'issues' => $thresholdCheck['issues'],
                    ];
                }
            }
        }

        // Generate recommendations based on issues
        if (! empty($analysis['performance_issues'])) {
            $analysis['recommendations'][] = 'Consider optimizing operations that exceed performance thresholds';
            $analysis['recommendations'][] = 'Review memory usage patterns for potential memory leaks';
            $analysis['recommendations'][] = 'Consider implementing caching for frequently accessed data';
        }

        return $analysis;
    }

    /**
     * Summarize operation result.
     */
    private function summarizeResult($result): array
    {
        if ($result === null) {
            return ['type' => 'null'];
        }

        if (is_object($result)) {
            $summary = ['type' => get_class($result)];

            if (method_exists($result, 'content')) {
                $summary['content_length'] = strlen($result->content ?? '');
            }

            if (property_exists($result, 'model')) {
                $summary['model'] = $result->model ?? null;
            }

            return $summary;
        }

        if (is_array($result)) {
            return [
                'type' => 'array',
                'count' => count($result),
                'keys' => array_keys($result),
            ];
        }

        return [
            'type' => gettype($result),
            'value' => is_scalar($result) ? $result : 'complex',
        ];
    }

    /**
     * Calculate median of an array.
     */
    private function calculateMedian(array $values): float
    {
        sort($values);
        $count = count($values);

        if ($count % 2 === 0) {
            return ($values[$count / 2 - 1] + $values[$count / 2]) / 2;
        }

        return $values[intval($count / 2)];
    }

    /**
     * Get all benchmarks.
     */
    public function getBenchmarks(): array
    {
        return $this->benchmarks;
    }

    /**
     * Clear all benchmarks.
     */
    public function clearBenchmarks(): void
    {
        $this->benchmarks = [];
    }

    /**
     * Set custom thresholds.
     */
    public function setThresholds(array $thresholds): void
    {
        $this->thresholds = array_merge($this->thresholds, $thresholds);
    }
}
