<?php

namespace JTD\LaravelAI\Tests\Performance;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use JTD\LaravelAI\Middleware\BudgetEnforcementMiddleware;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Services\BudgetService;
use JTD\LaravelAI\Services\EventPerformanceTracker;
use JTD\LaravelAI\Services\MiddlewareManager;
use JTD\LaravelAI\Services\PricingService;
use JTD\LaravelAI\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Middleware Performance Benchmark Tests
 *
 * Comprehensive performance testing suite to validate <10ms middleware execution
 * overhead requirement and identify performance bottlenecks.
 */
#[Group('performance')]
#[Group('middleware')]
class MiddlewarePerformanceBenchmarkTest extends TestCase
{
    use RefreshDatabase;

    protected BudgetEnforcementMiddleware $budgetMiddleware;

    protected MiddlewareManager $middlewareManager;

    protected EventPerformanceTracker $performanceTracker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->performanceTracker = app(EventPerformanceTracker::class);
        $this->middlewareManager = app(MiddlewareManager::class);

        $budgetService = app(BudgetService::class);
        $pricingService = app(PricingService::class);
        $this->budgetMiddleware = new BudgetEnforcementMiddleware(
            $budgetService,
            $pricingService,
            $this->performanceTracker
        );

        $this->seedPerformanceTestData();
    }

    #[Test]
    public function it_executes_budget_middleware_within_10ms_target(): void
    {
        $iterations = 100;
        $executionTimes = [];

        // Clear cache to ensure realistic performance testing
        Cache::flush();

        for ($i = 0; $i < $iterations; $i++) {
            $message = $this->createTestMessage($i);

            $startTime = microtime(true);

            $response = $this->budgetMiddleware->handle($message, function ($msg) {
                return $this->createMockResponse();
            });

            $executionTime = (microtime(true) - $startTime) * 1000;
            $executionTimes[] = $executionTime;

            $this->assertTrue($response->success);
        }

        // Performance assertions
        $avgExecutionTime = array_sum($executionTimes) / count($executionTimes);
        $maxExecutionTime = max($executionTimes);
        $p95ExecutionTime = $this->calculatePercentile($executionTimes, 95);
        $p99ExecutionTime = $this->calculatePercentile($executionTimes, 99);

        $this->assertLessThan(10, $avgExecutionTime,
            "Average execution time {$avgExecutionTime}ms exceeds 10ms target");
        $this->assertLessThan(15, $maxExecutionTime,
            "Maximum execution time {$maxExecutionTime}ms exceeds 15ms acceptable limit");
        $this->assertLessThan(12, $p95ExecutionTime,
            "95th percentile execution time {$p95ExecutionTime}ms exceeds 12ms threshold");
        $this->assertLessThan(15, $p99ExecutionTime,
            "99th percentile execution time {$p99ExecutionTime}ms exceeds 15ms threshold");

        $this->logPerformanceBenchmark('BudgetEnforcementMiddleware', [
            'iterations' => $iterations,
            'avg_execution_time_ms' => round($avgExecutionTime, 3),
            'max_execution_time_ms' => round($maxExecutionTime, 3),
            'p95_execution_time_ms' => round($p95ExecutionTime, 3),
            'p99_execution_time_ms' => round($p99ExecutionTime, 3),
            'memory_usage_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
        ]);
    }

    #[Test]
    public function it_executes_middleware_manager_stack_within_performance_target(): void
    {
        $this->middlewareManager->registerGlobal(BudgetEnforcementMiddleware::class);

        $iterations = 50;
        $executionTimes = [];

        Cache::flush();

        for ($i = 0; $i < $iterations; $i++) {
            $message = $this->createTestMessage($i);

            $startTime = microtime(true);

            $response = $this->middlewareManager->process($message);

            $executionTime = (microtime(true) - $startTime) * 1000;
            $executionTimes[] = $executionTime;

            $this->assertTrue($response->success);
        }

        $avgExecutionTime = array_sum($executionTimes) / count($executionTimes);
        $maxExecutionTime = max($executionTimes);
        $p95ExecutionTime = $this->calculatePercentile($executionTimes, 95);

        $this->assertLessThan(15, $avgExecutionTime,
            "Middleware stack average execution time {$avgExecutionTime}ms exceeds 15ms target");
        $this->assertLessThan(25, $maxExecutionTime,
            "Middleware stack maximum execution time {$maxExecutionTime}ms exceeds 25ms limit");

        $this->logPerformanceBenchmark('MiddlewareManager', [
            'iterations' => $iterations,
            'avg_execution_time_ms' => round($avgExecutionTime, 3),
            'max_execution_time_ms' => round($maxExecutionTime, 3),
            'p95_execution_time_ms' => round($p95ExecutionTime, 3),
            'cache_utilization' => $this->middlewareManager->getPerformanceStats(),
        ]);
    }

    #[Test]
    public function it_maintains_performance_with_cached_data(): void
    {
        // Warm up cache with initial requests
        for ($i = 0; $i < 10; $i++) {
            $message = $this->createTestMessage($i);
            $this->budgetMiddleware->handle($message, fn ($msg) => $this->createMockResponse());
        }

        // Test performance with warm cache
        $iterations = 100;
        $cachedExecutionTimes = [];

        for ($i = 0; $i < $iterations; $i++) {
            $message = $this->createTestMessage($i % 5); // Reuse user IDs for cache hits

            $startTime = microtime(true);

            $response = $this->budgetMiddleware->handle($message, function ($msg) {
                return $this->createMockResponse();
            });

            $cachedExecutionTime = (microtime(true) - $startTime) * 1000;
            $cachedExecutionTimes[] = $cachedExecutionTime;

            $this->assertTrue($response->success);
        }

        $avgCachedTime = array_sum($cachedExecutionTimes) / count($cachedExecutionTimes);
        $maxCachedTime = max($cachedExecutionTimes);

        // Cached performance should be significantly faster
        $this->assertLessThan(5, $avgCachedTime,
            "Cached execution time {$avgCachedTime}ms should be under 5ms with warm cache");
        $this->assertLessThan(10, $maxCachedTime,
            "Maximum cached execution time {$maxCachedTime}ms exceeds 10ms with warm cache");

        $this->logPerformanceBenchmark('BudgetMiddleware_Cached', [
            'iterations' => $iterations,
            'avg_execution_time_ms' => round($avgCachedTime, 3),
            'max_execution_time_ms' => round($maxCachedTime, 3),
            'cache_performance_improvement' => true,
        ]);
    }

    #[Test]
    public function it_handles_concurrent_requests_efficiently(): void
    {
        $concurrentRequests = 20;
        $executionTimes = [];

        // Simulate concurrent requests by creating multiple middleware instances
        $middlewareInstances = [];
        for ($i = 0; $i < $concurrentRequests; $i++) {
            $budgetService = app(BudgetService::class);
            $pricingService = app(PricingService::class);
            $middlewareInstances[] = new BudgetEnforcementMiddleware(
                $budgetService,
                $pricingService,
                $this->performanceTracker
            );
        }

        $startTime = microtime(true);

        // Execute concurrent requests
        foreach ($middlewareInstances as $index => $middleware) {
            $message = $this->createTestMessage($index);
            $instanceStartTime = microtime(true);

            $response = $middleware->handle($message, fn ($msg) => $this->createMockResponse());

            $instanceExecutionTime = (microtime(true) - $instanceStartTime) * 1000;
            $executionTimes[] = $instanceExecutionTime;

            $this->assertTrue($response->success);
        }

        $totalTime = (microtime(true) - $startTime) * 1000;
        $avgExecutionTime = array_sum($executionTimes) / count($executionTimes);
        $maxExecutionTime = max($executionTimes);

        // Performance should not degrade significantly under concurrent load
        $this->assertLessThan(15, $avgExecutionTime,
            "Concurrent average execution time {$avgExecutionTime}ms exceeds 15ms");
        $this->assertLessThan(200, $totalTime,
            "Total concurrent processing time {$totalTime}ms exceeds 200ms");

        $this->logPerformanceBenchmark('ConcurrentRequests', [
            'concurrent_requests' => $concurrentRequests,
            'total_time_ms' => round($totalTime, 3),
            'avg_execution_time_ms' => round($avgExecutionTime, 3),
            'max_execution_time_ms' => round($maxExecutionTime, 3),
            'throughput_requests_per_second' => round($concurrentRequests / ($totalTime / 1000), 2),
        ]);
    }

    #[Test]
    public function it_validates_database_query_performance(): void
    {
        $userId = 1;
        $iterations = 50;
        $queryTimes = [];

        // Test direct database queries that middleware depends on
        for ($i = 0; $i < $iterations; $i++) {
            $startTime = microtime(true);

            // Simulate the most common budget query
            $budgetLimit = DB::table('ai_user_budgets')
                ->where('user_id', $userId)
                ->where('type', 'daily')
                ->where('is_active', true)
                ->value('limit_amount');

            $dailySpending = DB::table('ai_cost_records')
                ->where('user_id', $userId)
                ->whereBetween('created_at', [today()->startOfDay(), today()->endOfDay()])
                ->sum('total_cost');

            $queryTime = (microtime(true) - $startTime) * 1000;
            $queryTimes[] = $queryTime;
        }

        $avgQueryTime = array_sum($queryTimes) / count($queryTimes);
        $maxQueryTime = max($queryTimes);

        // Database queries should be extremely fast with proper indexing
        $this->assertLessThan(2, $avgQueryTime,
            "Database query average time {$avgQueryTime}ms exceeds 2ms target");
        $this->assertLessThan(5, $maxQueryTime,
            "Database query max time {$maxQueryTime}ms exceeds 5ms limit");

        $this->logPerformanceBenchmark('DatabaseQueries', [
            'iterations' => $iterations,
            'avg_query_time_ms' => round($avgQueryTime, 3),
            'max_query_time_ms' => round($maxQueryTime, 3),
        ]);
    }

    #[Test]
    public function it_benchmarks_memory_usage_efficiency(): void
    {
        $iterations = 100;
        $memoryUsages = [];

        $initialMemory = memory_get_usage(true);

        for ($i = 0; $i < $iterations; $i++) {
            $message = $this->createTestMessage($i);

            $beforeMemory = memory_get_usage(true);

            $response = $this->budgetMiddleware->handle($message, function ($msg) {
                return $this->createMockResponse();
            });

            $afterMemory = memory_get_usage(true);
            $memoryUsages[] = $afterMemory - $beforeMemory;

            $this->assertTrue($response->success);
        }

        $finalMemory = memory_get_usage(true);
        $totalMemoryIncrease = $finalMemory - $initialMemory;
        $avgMemoryPerRequest = array_sum($memoryUsages) / count($memoryUsages);
        $maxMemoryPerRequest = max($memoryUsages);

        // Memory usage should remain reasonable
        $this->assertLessThan(1024 * 1024, $totalMemoryIncrease,
            'Total memory increase exceeds 1MB after 100 requests');
        $this->assertLessThan(50 * 1024, $avgMemoryPerRequest,
            'Average memory per request exceeds 50KB');

        $this->logPerformanceBenchmark('MemoryUsage', [
            'iterations' => $iterations,
            'total_memory_increase_mb' => round($totalMemoryIncrease / 1024 / 1024, 3),
            'avg_memory_per_request_kb' => round($avgMemoryPerRequest / 1024, 2),
            'max_memory_per_request_kb' => round($maxMemoryPerRequest / 1024, 2),
            'peak_memory_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
        ]);
    }

    /**
     * Seed test data for performance benchmarking.
     */
    protected function seedPerformanceTestData(): void
    {
        // Create test users
        for ($i = 1; $i <= 10; $i++) {
            DB::table('users')->insert([
                'id' => $i,
                'name' => "Test User {$i}",
                'email' => "test{$i}@example.com",
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Create budgets for users
        for ($userId = 1; $userId <= 10; $userId++) {
            DB::table('ai_user_budgets')->insert([
                'user_id' => $userId,
                'type' => 'daily',
                'limit_amount' => 10.00,
                'current_usage' => 0.00,
                'period_start' => today(),
                'period_end' => today()->addDay(),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('ai_user_budgets')->insert([
                'user_id' => $userId,
                'type' => 'monthly',
                'limit_amount' => 100.00,
                'current_usage' => 0.00,
                'period_start' => now()->startOfMonth(),
                'period_end' => now()->endOfMonth(),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Create some usage cost records for realistic testing
        for ($userId = 1; $userId <= 5; $userId++) {
            for ($i = 0; $i < 10; $i++) {
                DB::table('ai_cost_records')->insert([
                    'user_id' => $userId,
                    'provider' => 'openai',
                    'model' => 'gpt-4o-mini',
                    'input_tokens' => rand(100, 1000),
                    'output_tokens' => rand(50, 500),
                    'total_tokens' => rand(150, 1500),
                    'input_cost' => rand(1, 50) / 10000,
                    'output_cost' => rand(1, 100) / 10000,
                    'total_cost' => rand(2, 150) / 10000,
                    'created_at' => now()->subMinutes(rand(1, 1440)),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Create a test AI message.
     */
    protected function createTestMessage(int $userId): AIMessage
    {
        return new AIMessage([
            'user_id' => ($userId % 10) + 1,
            'content' => "Test message for performance benchmarking iteration {$userId}",
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'metadata' => [
                'project_id' => 'test-project-' . ($userId % 3),
                'organization_id' => 'test-org-' . ($userId % 2),
            ],
        ]);
    }

    /**
     * Create a mock AI response.
     */
    protected function createMockResponse(): \JTD\LaravelAI\Models\AIResponse
    {
        return new \JTD\LaravelAI\Models\AIResponse([
            'content' => 'Mock response for performance testing',
            'success' => true,
            'metadata' => ['performance_test' => true],
        ]);
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
     * Log performance benchmark results.
     */
    protected function logPerformanceBenchmark(string $component, array $metrics): void
    {
        echo "\n=== Performance Benchmark: {$component} ===\n";
        foreach ($metrics as $key => $value) {
            echo "{$key}: {$value}\n";
        }
        echo "=== End Benchmark ===\n\n";
    }
}
