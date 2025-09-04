<?php

namespace JTD\LaravelAI\Tests\Performance;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use JTD\LaravelAI\Middleware\BudgetEnforcementMiddleware;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Services\EventPerformanceTracker;
use JTD\LaravelAI\Services\MiddlewareManager;
use JTD\LaravelAI\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Middleware Load Testing Framework
 *
 * Tests middleware performance under various load conditions to validate
 * system behavior under production-like concurrent request loads.
 */
#[Group('performance')]
#[Group('load-testing')]
class MiddlewareLoadTest extends TestCase
{
    use RefreshDatabase;

    protected MiddlewareManager $middlewareManager;

    protected EventPerformanceTracker $performanceTracker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->performanceTracker = app(EventPerformanceTracker::class);
        $this->middlewareManager = app(MiddlewareManager::class);

        // Register middleware for load testing
        $this->middlewareManager->registerGlobal(BudgetEnforcementMiddleware::class);

        $this->seedLoadTestData();
    }

    #[Test]
    public function it_handles_low_concurrency_load_efficiently(): void
    {
        $this->runLoadTest([
            'concurrent_users' => 5,
            'requests_per_user' => 20,
            'max_avg_response_time_ms' => 12,
            'max_p95_response_time_ms' => 20,
            'min_success_rate' => 100.0,
        ]);
    }

    #[Test]
    public function it_handles_medium_concurrency_load_efficiently(): void
    {
        $this->runLoadTest([
            'concurrent_users' => 15,
            'requests_per_user' => 15,
            'max_avg_response_time_ms' => 18,
            'max_p95_response_time_ms' => 30,
            'min_success_rate' => 99.0,
        ]);
    }

    #[Test]
    public function it_handles_high_concurrency_load_gracefully(): void
    {
        $this->runLoadTest([
            'concurrent_users' => 30,
            'requests_per_user' => 10,
            'max_avg_response_time_ms' => 25,
            'max_p95_response_time_ms' => 50,
            'min_success_rate' => 95.0,
        ]);
    }

    #[Test]
    public function it_maintains_performance_with_cache_warming(): void
    {
        // Warm up cache with initial requests
        $this->warmUpMiddlewareCache();

        $this->runLoadTest([
            'concurrent_users' => 20,
            'requests_per_user' => 25,
            'max_avg_response_time_ms' => 8,
            'max_p95_response_time_ms' => 15,
            'min_success_rate' => 100.0,
            'cache_warmed' => true,
        ]);
    }

    #[Test]
    public function it_handles_burst_load_patterns(): void
    {
        $burstResults = [];

        // Simulate burst patterns: low -> high -> low
        $burstPatterns = [
            ['users' => 2, 'requests' => 5],   // Low
            ['users' => 25, 'requests' => 8],  // Burst
            ['users' => 3, 'requests' => 5],   // Low
        ];

        foreach ($burstPatterns as $index => $pattern) {
            $results = $this->executeLoadTestPattern($pattern['users'], $pattern['requests']);
            $burstResults["burst_phase_{$index}"] = $results;

            // Short pause between bursts
            usleep(100000); // 100ms
        }

        // Verify burst handling
        foreach ($burstResults as $phase => $results) {
            $this->assertLessThan(95, $results['error_rate'],
                "Phase {$phase} error rate {$results['error_rate']}% too high");
            $this->assertLessThan(100, $results['avg_response_time_ms'],
                "Phase {$phase} average response time {$results['avg_response_time_ms']}ms too slow");
        }

        $this->logLoadTestResults('BurstLoadPattern', [
            'phases' => count($burstPatterns),
            'results' => $burstResults,
        ]);
    }

    #[Test]
    public function it_validates_memory_usage_under_load(): void
    {
        $initialMemory = memory_get_usage(true);
        $peakMemoryUsages = [];

        $loadPatterns = [
            ['users' => 10, 'requests' => 10],
            ['users' => 20, 'requests' => 10],
            ['users' => 30, 'requests' => 10],
        ];

        foreach ($loadPatterns as $pattern) {
            $beforeMemory = memory_get_usage(true);

            $results = $this->executeLoadTestPattern($pattern['users'], $pattern['requests']);

            $afterMemory = memory_get_usage(true);
            $peakMemory = memory_get_peak_usage(true);

            $peakMemoryUsages[] = [
                'pattern' => $pattern,
                'memory_increase_mb' => round(($afterMemory - $beforeMemory) / 1024 / 1024, 3),
                'peak_memory_mb' => round($peakMemory / 1024 / 1024, 2),
                'avg_response_time' => $results['avg_response_time_ms'],
            ];

            // Reset memory baseline
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
        }

        // Memory usage assertions
        foreach ($peakMemoryUsages as $usage) {
            $this->assertLessThan(10, $usage['memory_increase_mb'],
                'Memory increase per load pattern should be under 10MB');
            $this->assertLessThan(50, $usage['peak_memory_mb'],
                'Peak memory usage should be under 50MB');
        }

        $this->logLoadTestResults('MemoryUsageUnderLoad', [
            'patterns_tested' => count($loadPatterns),
            'memory_usage_data' => $peakMemoryUsages,
        ]);
    }

    #[Test]
    public function it_stress_tests_database_connection_handling(): void
    {
        // Test with many concurrent database operations
        $stressResults = $this->executeLoadTestPattern(50, 5);

        // Verify database connections are handled properly
        $this->assertLessThan(10, $stressResults['error_rate'],
            'Database stress test error rate too high');
        $this->assertGreaterThan(90, $stressResults['success_rate'],
            'Database stress test success rate too low');

        // Check for database connection leaks
        $connectionCount = DB::connection()->getPdo()->query('SHOW STATUS LIKE "Threads_connected"')->fetchColumn(1);
        $this->assertLessThan(20, (int) $connectionCount,
            'Database connection count suggests connection leaks');

        $this->logLoadTestResults('DatabaseStressTest', [
            'concurrent_db_operations' => 250, // 50 users * 5 requests
            'final_connection_count' => $connectionCount,
            'results' => $stressResults,
        ]);
    }

    /**
     * Run a comprehensive load test with specified parameters.
     */
    protected function runLoadTest(array $config): array
    {
        $results = $this->executeLoadTestPattern(
            $config['concurrent_users'],
            $config['requests_per_user']
        );

        // Performance assertions
        $this->assertLessThan($config['max_avg_response_time_ms'], $results['avg_response_time_ms'],
            "Average response time {$results['avg_response_time_ms']}ms exceeds limit {$config['max_avg_response_time_ms']}ms");

        $this->assertLessThan($config['max_p95_response_time_ms'], $results['p95_response_time_ms'],
            "P95 response time {$results['p95_response_time_ms']}ms exceeds limit {$config['max_p95_response_time_ms']}ms");

        $this->assertGreaterThanOrEqual($config['min_success_rate'], $results['success_rate'],
            "Success rate {$results['success_rate']}% below minimum {$config['min_success_rate']}%");

        $testName = 'LoadTest_' . $config['concurrent_users'] . 'users_' . $config['requests_per_user'] . 'requests';
        if (isset($config['cache_warmed'])) {
            $testName .= '_CacheWarmed';
        }

        $this->logLoadTestResults($testName, array_merge($config, $results));

        return $results;
    }

    /**
     * Execute a specific load test pattern.
     */
    protected function executeLoadTestPattern(int $concurrentUsers, int $requestsPerUser): array
    {
        $allResults = [];
        $startTime = microtime(true);

        // Simulate concurrent users making requests
        for ($user = 0; $user < $concurrentUsers; $user++) {
            $userResults = [];

            for ($request = 0; $request < $requestsPerUser; $request++) {
                $message = $this->createLoadTestMessage($user, $request);

                $requestStartTime = microtime(true);
                $success = false;
                $error = null;

                try {
                    $response = $this->middlewareManager->process($message);
                    $success = $response->success ?? true;
                } catch (\Exception $e) {
                    $error = $e->getMessage();
                    $success = false;
                }

                $responseTime = (microtime(true) - $requestStartTime) * 1000;

                $userResults[] = [
                    'response_time_ms' => $responseTime,
                    'success' => $success,
                    'error' => $error,
                ];

                $allResults[] = $userResults[count($userResults) - 1];
            }
        }

        $totalTime = (microtime(true) - $startTime) * 1000;

        // Calculate metrics
        $responseTimes = array_column($allResults, 'response_time_ms');
        $successCount = count(array_filter($allResults, fn ($r) => $r['success']));
        $totalRequests = count($allResults);

        return [
            'total_requests' => $totalRequests,
            'successful_requests' => $successCount,
            'failed_requests' => $totalRequests - $successCount,
            'success_rate' => round(($successCount / $totalRequests) * 100, 2),
            'error_rate' => round((($totalRequests - $successCount) / $totalRequests) * 100, 2),
            'total_time_ms' => round($totalTime, 2),
            'avg_response_time_ms' => round(array_sum($responseTimes) / count($responseTimes), 3),
            'min_response_time_ms' => round(min($responseTimes), 3),
            'max_response_time_ms' => round(max($responseTimes), 3),
            'p95_response_time_ms' => round($this->calculatePercentile($responseTimes, 95), 3),
            'p99_response_time_ms' => round($this->calculatePercentile($responseTimes, 99), 3),
            'throughput_rps' => round($totalRequests / ($totalTime / 1000), 2),
            'concurrent_users' => $concurrentUsers,
            'requests_per_user' => $requestsPerUser,
        ];
    }

    /**
     * Warm up middleware cache for optimal performance testing.
     */
    protected function warmUpMiddlewareCache(): void
    {
        // Warm up with various user patterns
        for ($userId = 1; $userId <= 10; $userId++) {
            $message = new AIMessage([
                'user_id' => $userId,
                'content' => 'Cache warming request',
                'provider' => 'openai',
                'model' => 'gpt-4o-mini',
                'metadata' => [],
            ]);

            try {
                $this->middlewareManager->process($message);
            } catch (\Exception $e) {
                // Ignore errors during cache warming
            }
        }

        // Warm up middleware resolution cache
        $this->middlewareManager->warmUpCache();
    }

    /**
     * Create a load test message.
     */
    protected function createLoadTestMessage(int $userIndex, int $requestIndex): AIMessage
    {
        $userId = ($userIndex % 20) + 1; // Distribute across 20 test users

        return new AIMessage([
            'user_id' => $userId,
            'content' => "Load test message from user {$userIndex}, request {$requestIndex}",
            'provider' => ['openai', 'gemini', 'xai'][array_rand(['openai', 'gemini', 'xai'])],
            'model' => 'gpt-4o-mini',
            'metadata' => [
                'load_test' => true,
                'user_index' => $userIndex,
                'request_index' => $requestIndex,
                'project_id' => 'load-test-project-' . ($userIndex % 5),
                'organization_id' => 'load-test-org-' . ($userIndex % 3),
            ],
        ]);
    }

    /**
     * Seed data for load testing.
     */
    protected function seedLoadTestData(): void
    {
        // Create test users for load testing
        for ($i = 1; $i <= 50; $i++) {
            DB::table('users')->insert([
                'id' => $i,
                'name' => "Load Test User {$i}",
                'email' => "loadtest{$i}@example.com",
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Create budgets for each user
            DB::table('ai_budgets')->insert([
                'user_id' => $i,
                'type' => 'daily',
                'limit_amount' => 50.00,
                'current_usage' => 0.00,
                'period_start' => today(),
                'period_end' => today()->addDay(),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Create some baseline usage data
        for ($userId = 1; $userId <= 10; $userId++) {
            for ($i = 0; $i < 5; $i++) {
                DB::table('ai_usage_costs')->insert([
                    'user_id' => $userId,
                    'provider' => 'openai',
                    'model' => 'gpt-4o-mini',
                    'input_tokens' => rand(50, 500),
                    'output_tokens' => rand(25, 250),
                    'total_tokens' => rand(75, 750),
                    'input_cost' => rand(1, 25) / 10000,
                    'output_cost' => rand(1, 50) / 10000,
                    'total_cost' => rand(2, 75) / 10000,
                    'created_at' => now()->subMinutes(rand(1, 60)),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Calculate percentile from array of values.
     */
    protected function calculatePercentile(array $values, int $percentile): float
    {
        sort($values);
        $index = ($percentile / 100) * (count($values) - 1);

        if (floor($index) == $index) {
            return $values[$index];
        }

        $lower = $values[floor($index)];
        $upper = $values[ceil($index)];

        return $lower + ($upper - $lower) * ($index - floor($index));
    }

    /**
     * Log load test results.
     */
    protected function logLoadTestResults(string $testName, array $results): void
    {
        echo "\n=== Load Test Results: {$testName} ===\n";

        $keyMetrics = [
            'total_requests', 'success_rate', 'avg_response_time_ms',
            'p95_response_time_ms', 'max_response_time_ms', 'throughput_rps',
        ];

        foreach ($results as $key => $value) {
            if (in_array($key, $keyMetrics) || ! is_array($value)) {
                echo "{$key}: {$value}\n";
            }
        }

        echo "=== End Load Test ===\n\n";
    }
}
