<?php

namespace JTD\LaravelAI\Tests\Performance;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use JTD\LaravelAI\Middleware\BudgetEnforcementMiddleware;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Services\BudgetCacheService;
use JTD\LaravelAI\Services\BudgetService;
use JTD\LaravelAI\Services\EventPerformanceTracker;
use JTD\LaravelAI\Services\MiddlewareManager;
use JTD\LaravelAI\Services\PerformanceAlertService;
use JTD\LaravelAI\Services\PricingService;
use JTD\LaravelAI\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Middleware Overhead Validation Test
 *
 * Comprehensive validation suite that ensures the <10ms middleware execution
 * overhead requirement is consistently met across all scenarios and conditions.
 * This test serves as the final quality gate for middleware performance.
 */
#[Group('performance')]
#[Group('validation')]
#[Group('critical')]
class MiddlewareOverheadValidationTest extends TestCase
{
    use RefreshDatabase;

    protected const PERFORMANCE_TARGET_MS = 10;

    protected const ACCEPTABLE_VARIANCE_MS = 2; // Allow 2ms variance

    protected const CRITICAL_THRESHOLD_MS = 15; // Absolute maximum allowed

    protected MiddlewareManager $middlewareManager;

    protected BudgetEnforcementMiddleware $budgetMiddleware;

    protected BudgetCacheService $budgetCacheService;

    protected PerformanceAlertService $alertService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->budgetCacheService = app(BudgetCacheService::class);
        $this->alertService = app(PerformanceAlertService::class);
        $this->middlewareManager = app(MiddlewareManager::class);

        $budgetService = app(BudgetService::class);
        $pricingService = app(PricingService::class);
        $performanceTracker = app(EventPerformanceTracker::class);

        $this->budgetMiddleware = new BudgetEnforcementMiddleware(
            $budgetService,
            $pricingService,
            $performanceTracker,
            $this->budgetCacheService
        );

        $this->seedValidationTestData();
    }

    #[Test]
    public function it_meets_10ms_overhead_requirement_for_budget_middleware(): void
    {
        $iterations = 100;
        $executionTimes = [];
        $failedRequests = 0;

        // Test with cold cache (worst case scenario)
        Cache::flush();

        for ($i = 0; $i < $iterations; $i++) {
            $message = $this->createValidationMessage($i);

            $startTime = microtime(true);

            try {
                $response = $this->budgetMiddleware->handle($message, function ($msg) {
                    return $this->createMockResponse();
                });

                $executionTime = (microtime(true) - $startTime) * 1000;
                $executionTimes[] = $executionTime;

                // Validate response
                $this->assertTrue($response->success, "Request {$i} failed unexpectedly");
            } catch (\Exception $e) {
                $failedRequests++;
                $this->fail("Request {$i} threw exception: " . $e->getMessage());
            }
        }

        // Performance validation
        $avgTime = array_sum($executionTimes) / count($executionTimes);
        $maxTime = max($executionTimes);
        $p95Time = $this->calculatePercentile($executionTimes, 95);
        $p99Time = $this->calculatePercentile($executionTimes, 99);

        // Critical assertions - these must pass
        $this->assertLessThan(self::PERFORMANCE_TARGET_MS, $avgTime,
            "CRITICAL: Average execution time {$avgTime}ms exceeds 10ms target");

        $this->assertLessThan(self::CRITICAL_THRESHOLD_MS, $maxTime,
            "CRITICAL: Maximum execution time {$maxTime}ms exceeds 15ms absolute limit");

        $this->assertLessThan(self::PERFORMANCE_TARGET_MS + self::ACCEPTABLE_VARIANCE_MS, $p95Time,
            "CRITICAL: 95th percentile {$p95Time}ms exceeds target + variance ({self::PERFORMANCE_TARGET_MS}ms + {self::ACCEPTABLE_VARIANCE_MS}ms)");

        // Performance quality assertions
        $this->assertLessThan(self::PERFORMANCE_TARGET_MS + 5, $p99Time,
            "Quality: 99th percentile {$p99Time}ms should be under 15ms");

        $this->assertEquals(0, $failedRequests,
            "All requests should succeed: {$failedRequests} failures out of {$iterations}");

        // Log results for monitoring
        $this->logValidationResults('BudgetMiddleware_ColdCache', [
            'iterations' => $iterations,
            'avg_time_ms' => round($avgTime, 3),
            'max_time_ms' => round($maxTime, 3),
            'p95_time_ms' => round($p95Time, 3),
            'p99_time_ms' => round($p99Time, 3),
            'failed_requests' => $failedRequests,
            'target_met' => $avgTime < self::PERFORMANCE_TARGET_MS,
            'critical_threshold_respected' => $maxTime < self::CRITICAL_THRESHOLD_MS,
        ]);
    }

    #[Test]
    public function it_maintains_performance_with_warm_cache(): void
    {
        // Warm up cache
        $this->warmUpCache();

        $iterations = 150;
        $executionTimes = [];

        for ($i = 0; $i < $iterations; $i++) {
            $message = $this->createValidationMessage($i % 10); // Reuse user IDs for cache hits

            $startTime = microtime(true);

            $response = $this->budgetMiddleware->handle($message, function ($msg) {
                return $this->createMockResponse();
            });

            $executionTime = (microtime(true) - $startTime) * 1000;
            $executionTimes[] = $executionTime;

            $this->assertTrue($response->success);
        }

        $avgTime = array_sum($executionTimes) / count($executionTimes);
        $maxTime = max($executionTimes);
        $p95Time = $this->calculatePercentile($executionTimes, 95);

        // With warm cache, performance should be excellent
        $this->assertLessThan(5, $avgTime,
            "Warm cache average time {$avgTime}ms should be under 5ms");

        $this->assertLessThan(10, $maxTime,
            "Warm cache maximum time {$maxTime}ms should be under 10ms");

        $this->logValidationResults('BudgetMiddleware_WarmCache', [
            'iterations' => $iterations,
            'avg_time_ms' => round($avgTime, 3),
            'max_time_ms' => round($maxTime, 3),
            'p95_time_ms' => round($p95Time, 3),
            'performance_improvement' => true,
        ]);
    }

    #[Test]
    public function it_meets_performance_target_under_concurrent_load(): void
    {
        $concurrentUsers = 25;
        $requestsPerUser = 10;
        $totalRequests = $concurrentUsers * $requestsPerUser;
        $executionTimes = [];

        $overallStartTime = microtime(true);

        // Simulate concurrent users
        for ($user = 0; $user < $concurrentUsers; $user++) {
            for ($request = 0; $request < $requestsPerUser; $request++) {
                $message = $this->createValidationMessage($user);

                $startTime = microtime(true);

                $response = $this->budgetMiddleware->handle($message, function ($msg) {
                    return $this->createMockResponse();
                });

                $executionTime = (microtime(true) - $startTime) * 1000;
                $executionTimes[] = $executionTime;

                $this->assertTrue($response->success);
            }
        }

        $totalTime = (microtime(true) - $overallStartTime) * 1000;
        $avgTime = array_sum($executionTimes) / count($executionTimes);
        $maxTime = max($executionTimes);
        $throughput = $totalRequests / ($totalTime / 1000);

        // Performance must not degrade significantly under load
        $this->assertLessThan(self::PERFORMANCE_TARGET_MS + 5, $avgTime,
            "Concurrent load average time {$avgTime}ms exceeds acceptable threshold");

        $this->assertLessThan(25, $maxTime,
            "Concurrent load maximum time {$maxTime}ms exceeds 25ms limit");

        $this->assertGreaterThan(50, $throughput,
            "Throughput {$throughput} req/sec should be above 50 req/sec");

        $this->logValidationResults('ConcurrentLoad', [
            'concurrent_users' => $concurrentUsers,
            'requests_per_user' => $requestsPerUser,
            'total_requests' => $totalRequests,
            'avg_time_ms' => round($avgTime, 3),
            'max_time_ms' => round($maxTime, 3),
            'throughput_rps' => round($throughput, 2),
            'total_time_ms' => round($totalTime, 2),
        ]);
    }

    #[Test]
    public function it_handles_edge_cases_within_performance_limits(): void
    {
        $edgeCases = [
            'large_message' => $this->createLargeMessage(),
            'complex_metadata' => $this->createComplexMetadataMessage(),
            'missing_optional_fields' => $this->createMinimalMessage(),
            'unicode_content' => $this->createUnicodeMessage(),
        ];

        foreach ($edgeCases as $caseName => $message) {
            $startTime = microtime(true);

            $response = $this->budgetMiddleware->handle($message, function ($msg) {
                return $this->createMockResponse();
            });

            $executionTime = (microtime(true) - $startTime) * 1000;

            $this->assertTrue($response->success, "Edge case {$caseName} failed");
            $this->assertLessThan(self::CRITICAL_THRESHOLD_MS, $executionTime,
                "Edge case {$caseName} execution time {$executionTime}ms exceeds critical threshold");

            echo "Edge case {$caseName}: {$executionTime}ms\n";
        }
    }

    #[Test]
    public function it_maintains_performance_across_different_providers_and_models(): void
    {
        $providerModelCombos = [
            ['openai', 'gpt-4o-mini'],
            ['openai', 'gpt-4'],
            ['gemini', 'gemini-2.0-flash'],
            ['xai', 'grok-2-1212'],
        ];

        foreach ($providerModelCombos as [$provider, $model]) {
            $iterations = 20;
            $executionTimes = [];

            for ($i = 0; $i < $iterations; $i++) {
                $message = new AIMessage([
                    'user_id' => ($i % 5) + 1,
                    'content' => "Test message for {$provider} {$model}",
                    'provider' => $provider,
                    'model' => $model,
                    'metadata' => ['validation_test' => true],
                ]);

                $startTime = microtime(true);

                $response = $this->budgetMiddleware->handle($message, function ($msg) {
                    return $this->createMockResponse();
                });

                $executionTime = (microtime(true) - $startTime) * 1000;
                $executionTimes[] = $executionTime;

                $this->assertTrue($response->success);
            }

            $avgTime = array_sum($executionTimes) / count($executionTimes);

            $this->assertLessThan(self::PERFORMANCE_TARGET_MS + 3, $avgTime,
                "Provider {$provider} model {$model} average time {$avgTime}ms exceeds threshold");

            echo "Provider {$provider} model {$model}: avg {$avgTime}ms\n";
        }
    }

    #[Test]
    public function it_validates_memory_usage_stays_within_limits(): void
    {
        $iterations = 100;
        $memoryReadings = [];
        $initialMemory = memory_get_usage(true);

        for ($i = 0; $i < $iterations; $i++) {
            $message = $this->createValidationMessage($i);

            $beforeMemory = memory_get_usage(true);

            $response = $this->budgetMiddleware->handle($message, function ($msg) {
                return $this->createMockResponse();
            });

            $afterMemory = memory_get_usage(true);
            $memoryIncrease = $afterMemory - $beforeMemory;
            $memoryReadings[] = $memoryIncrease;

            $this->assertTrue($response->success);

            // Memory should not increase significantly per request
            $this->assertLessThan(1024 * 50, $memoryIncrease, // 50KB limit per request
                "Request {$i} memory increase {$memoryIncrease} bytes exceeds 50KB limit");
        }

        $totalMemoryIncrease = memory_get_usage(true) - $initialMemory;
        $avgMemoryPerRequest = array_sum($memoryReadings) / count($memoryReadings);

        // Total memory increase should be reasonable
        $this->assertLessThan(1024 * 1024 * 5, $totalMemoryIncrease, // 5MB total limit
            "Total memory increase {$totalMemoryIncrease} bytes exceeds 5MB limit");

        $this->logValidationResults('MemoryValidation', [
            'iterations' => $iterations,
            'total_memory_increase_mb' => round($totalMemoryIncrease / 1024 / 1024, 3),
            'avg_memory_per_request_bytes' => round($avgMemoryPerRequest),
            'peak_memory_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
        ]);
    }

    #[Test]
    public function it_validates_end_to_end_middleware_stack_performance(): void
    {
        // Register full middleware stack
        $this->middlewareManager->registerGlobal(BudgetEnforcementMiddleware::class);

        $iterations = 50;
        $executionTimes = [];

        for ($i = 0; $i < $iterations; $i++) {
            $message = $this->createValidationMessage($i);

            $startTime = microtime(true);

            $response = $this->middlewareManager->process($message);

            $executionTime = (microtime(true) - $startTime) * 1000;
            $executionTimes[] = $executionTime;

            $this->assertTrue($response->success);
        }

        $avgTime = array_sum($executionTimes) / count($executionTimes);
        $maxTime = max($executionTimes);
        $p95Time = $this->calculatePercentile($executionTimes, 95);

        // End-to-end stack must still meet performance requirements
        $this->assertLessThan(self::PERFORMANCE_TARGET_MS + 5, $avgTime,
            "E2E middleware stack average time {$avgTime}ms exceeds target + 5ms");

        $this->assertLessThan(25, $maxTime,
            "E2E middleware stack maximum time {$maxTime}ms exceeds 25ms");

        // Validate middleware manager performance stats
        $managerStats = $this->middlewareManager->getPerformanceStats();
        $this->assertGreaterThan(0, $managerStats['cache_size'],
            'Middleware manager cache should be populated');

        $this->logValidationResults('E2EMiddlewareStack', [
            'iterations' => $iterations,
            'avg_time_ms' => round($avgTime, 3),
            'max_time_ms' => round($maxTime, 3),
            'p95_time_ms' => round($p95Time, 3),
            'middleware_cache_size' => $managerStats['cache_size'],
        ]);
    }

    /**
     * Warm up cache for optimal performance testing.
     */
    protected function warmUpCache(): void
    {
        // Warm up budget cache service
        $userIds = range(1, 10);
        $this->budgetCacheService->warmUpCache($userIds, ['daily', 'monthly']);

        // Warm up middleware manager
        $this->middlewareManager->warmUpCache();
    }

    /**
     * Create validation test message.
     */
    protected function createValidationMessage(int $index): AIMessage
    {
        return new AIMessage([
            'user_id' => ($index % 10) + 1,
            'content' => "Validation test message #{$index}",
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'metadata' => [
                'validation_test' => true,
                'index' => $index,
                'project_id' => 'validation-project-' . ($index % 3),
                'organization_id' => 'validation-org-' . ($index % 2),
            ],
        ]);
    }

    /**
     * Create large message for edge case testing.
     */
    protected function createLargeMessage(): AIMessage
    {
        $largeContent = str_repeat('This is a test message with substantial content. ', 100);

        return new AIMessage([
            'user_id' => 1,
            'content' => $largeContent,
            'provider' => 'openai',
            'model' => 'gpt-4',
            'metadata' => ['edge_case' => 'large_message'],
        ]);
    }

    /**
     * Create message with complex metadata.
     */
    protected function createComplexMetadataMessage(): AIMessage
    {
        return new AIMessage([
            'user_id' => 2,
            'content' => 'Message with complex metadata',
            'provider' => 'gemini',
            'model' => 'gemini-2.0-flash',
            'metadata' => [
                'edge_case' => 'complex_metadata',
                'nested' => [
                    'level1' => [
                        'level2' => [
                            'data' => range(1, 50),
                        ],
                    ],
                ],
                'arrays' => [
                    'tags' => ['validation', 'test', 'performance', 'complex'],
                    'metrics' => ['cpu', 'memory', 'disk', 'network'],
                ],
                'timestamps' => [
                    'created' => now()->toISOString(),
                    'modified' => now()->subHours(2)->toISOString(),
                ],
            ],
        ]);
    }

    /**
     * Create minimal message for edge case testing.
     */
    protected function createMinimalMessage(): AIMessage
    {
        return new AIMessage([
            'user_id' => 3,
            'content' => 'Min',
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
        ]);
    }

    /**
     * Create unicode message for edge case testing.
     */
    protected function createUnicodeMessage(): AIMessage
    {
        return new AIMessage([
            'user_id' => 4,
            'content' => 'Unicode test: 你好世界 🌍 Здравствуй мир Γεια σας κόσμε مرحبا بالعالم',
            'provider' => 'xai',
            'model' => 'grok-2-1212',
            'metadata' => ['edge_case' => 'unicode_content'],
        ]);
    }

    /**
     * Create mock AI response.
     */
    protected function createMockResponse(): \JTD\LaravelAI\Models\AIResponse
    {
        return new \JTD\LaravelAI\Models\AIResponse([
            'content' => 'Mock validation response',
            'success' => true,
            'metadata' => ['validation' => true],
        ]);
    }

    /**
     * Seed validation test data.
     */
    protected function seedValidationTestData(): void
    {
        // Create test users
        for ($i = 1; $i <= 20; $i++) {
            DB::table('users')->insert([
                'id' => $i,
                'name' => "Validation User {$i}",
                'email' => "validation{$i}@example.com",
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Create budgets
            DB::table('ai_user_budgets')->insert([
                'user_id' => $i,
                'type' => 'daily',
                'limit_amount' => 25.00,
                'current_usage' => 0.00,
                'period_start' => today(),
                'period_end' => today()->addDay(),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('ai_user_budgets')->insert([
                'user_id' => $i,
                'type' => 'monthly',
                'limit_amount' => 500.00,
                'current_usage' => 0.00,
                'period_start' => now()->startOfMonth(),
                'period_end' => now()->endOfMonth(),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Create some usage history
        for ($userId = 1; $userId <= 10; $userId++) {
            for ($i = 0; $i < 5; $i++) {
                DB::table('ai_cost_records')->insert([
                    'user_id' => $userId,
                    'provider' => 'openai',
                    'model' => 'gpt-4o-mini',
                    'input_tokens' => rand(100, 500),
                    'output_tokens' => rand(50, 250),
                    'total_tokens' => rand(150, 750),
                    'input_cost' => rand(1, 25) / 100000,
                    'output_cost' => rand(1, 50) / 100000,
                    'total_cost' => rand(2, 75) / 100000,
                    'created_at' => now()->subMinutes(rand(1, 120)),
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
     * Log validation results.
     */
    protected function logValidationResults(string $testName, array $results): void
    {
        $passed = isset($results['target_met']) ? $results['target_met'] : true;
        $status = $passed ? '✅ PASSED' : '❌ FAILED';

        echo "\n=== {$status} - Performance Validation: {$testName} ===\n";

        foreach ($results as $key => $value) {
            echo "{$key}: {$value}\n";
        }

        echo "=== End Validation ===\n\n";
    }
}
