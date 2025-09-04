<?php

namespace JTD\LaravelAI\Tests\Performance;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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
 * Performance Regression Tests
 *
 * Automated tests that track performance metrics over time and detect
 * performance regressions in middleware execution. Maintains baseline
 * performance profiles and alerts when degradation occurs.
 */
#[Group('performance')]
#[Group('regression')]
class PerformanceRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected const PERFORMANCE_BASELINES_FILE = 'performance_baselines.json';

    protected const REGRESSION_THRESHOLD_PERCENT = 20; // 20% performance degradation threshold

    protected MiddlewareManager $middlewareManager;

    protected BudgetEnforcementMiddleware $budgetMiddleware;

    protected EventPerformanceTracker $performanceTracker;

    protected array $performanceBaselines;

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

        $this->loadPerformanceBaselines();
        $this->seedRegressionTestData();
    }

    #[Test]
    public function it_detects_budget_middleware_performance_regressions(): void
    {
        $componentName = 'BudgetEnforcementMiddleware';
        $iterations = 50;
        $executionTimes = [];

        // Clear cache for consistent testing
        Cache::flush();

        for ($i = 0; $i < $iterations; $i++) {
            $message = $this->createRegressionTestMessage($i);

            $startTime = microtime(true);

            $response = $this->budgetMiddleware->handle($message, function ($msg) {
                return $this->createMockResponse();
            });

            $executionTime = (microtime(true) - $startTime) * 1000;
            $executionTimes[] = $executionTime;

            $this->assertTrue($response->success);
        }

        $currentMetrics = $this->calculatePerformanceMetrics($executionTimes);
        $baseline = $this->getBaseline($componentName);

        // Check for performance regressions
        $this->assertNoPerformanceRegression($componentName, $currentMetrics, $baseline);

        // Update baseline if performance improved significantly
        $this->updateBaselineIfImproved($componentName, $currentMetrics, $baseline);
    }

    #[Test]
    public function it_detects_middleware_manager_performance_regressions(): void
    {
        $componentName = 'MiddlewareManager';
        $this->middlewareManager->registerGlobal(BudgetEnforcementMiddleware::class);

        $iterations = 30;
        $executionTimes = [];

        Cache::flush();

        for ($i = 0; $i < $iterations; $i++) {
            $message = $this->createRegressionTestMessage($i);

            $startTime = microtime(true);

            $response = $this->middlewareManager->process($message);

            $executionTime = (microtime(true) - $startTime) * 1000;
            $executionTimes[] = $executionTime;

            $this->assertTrue($response->success);
        }

        $currentMetrics = $this->calculatePerformanceMetrics($executionTimes);
        $baseline = $this->getBaseline($componentName);

        $this->assertNoPerformanceRegression($componentName, $currentMetrics, $baseline);
        $this->updateBaselineIfImproved($componentName, $currentMetrics, $baseline);
    }

    #[Test]
    public function it_detects_database_query_performance_regressions(): void
    {
        $componentName = 'DatabaseQueries';
        $iterations = 100;
        $queryTimes = [];

        for ($i = 0; $i < $iterations; $i++) {
            $userId = ($i % 10) + 1;

            $startTime = microtime(true);

            // Execute the most critical budget queries
            $budgetLimit = DB::table('ai_budgets')
                ->where('user_id', $userId)
                ->where('type', 'daily')
                ->where('is_active', true)
                ->value('limit_amount');

            $dailySpending = DB::table('ai_usage_costs')
                ->where('user_id', $userId)
                ->whereBetween('created_at', [today()->startOfDay(), today()->endOfDay()])
                ->sum('total_cost');

            $queryTime = (microtime(true) - $startTime) * 1000;
            $queryTimes[] = $queryTime;
        }

        $currentMetrics = $this->calculatePerformanceMetrics($queryTimes);
        $baseline = $this->getBaseline($componentName);

        $this->assertNoPerformanceRegression($componentName, $currentMetrics, $baseline);
        $this->updateBaselineIfImproved($componentName, $currentMetrics, $baseline);
    }

    #[Test]
    public function it_detects_memory_usage_regressions(): void
    {
        $componentName = 'MemoryUsage';
        $iterations = 50;
        $memoryUsages = [];

        $initialMemory = memory_get_usage(true);

        for ($i = 0; $i < $iterations; $i++) {
            $message = $this->createRegressionTestMessage($i);

            $beforeMemory = memory_get_usage(true);

            $response = $this->budgetMiddleware->handle($message, function ($msg) {
                return $this->createMockResponse();
            });

            $afterMemory = memory_get_usage(true);
            $memoryUsages[] = $afterMemory - $beforeMemory;

            $this->assertTrue($response->success);
        }

        $currentMetrics = [
            'avg_memory_per_request_bytes' => array_sum($memoryUsages) / count($memoryUsages),
            'max_memory_per_request_bytes' => max($memoryUsages),
            'total_memory_increase_bytes' => memory_get_usage(true) - $initialMemory,
            'peak_memory_bytes' => memory_get_peak_usage(true),
        ];

        $baseline = $this->getBaseline($componentName);

        // Memory regression checks
        if (isset($baseline['avg_memory_per_request_bytes'])) {
            $memoryIncrease = (($currentMetrics['avg_memory_per_request_bytes'] - $baseline['avg_memory_per_request_bytes'])
                / $baseline['avg_memory_per_request_bytes']) * 100;

            $this->assertLessThan(self::REGRESSION_THRESHOLD_PERCENT, $memoryIncrease,
                "Memory usage regression detected: {$memoryIncrease}% increase in average memory per request");
        }

        $this->updateBaselineIfImproved($componentName, $currentMetrics, $baseline);
    }

    #[Test]
    public function it_detects_cache_performance_regressions(): void
    {
        $componentName = 'CachePerformance';

        // Test cache hit performance
        $cacheHitTimes = $this->measureCacheHitPerformance();

        // Test cache miss performance
        Cache::flush();
        $cacheMissTimes = $this->measureCacheMissPerformance();

        $currentMetrics = [
            'avg_cache_hit_time_ms' => array_sum($cacheHitTimes) / count($cacheHitTimes),
            'avg_cache_miss_time_ms' => array_sum($cacheMissTimes) / count($cacheMissTimes),
            'cache_hit_p95_ms' => $this->calculatePercentile($cacheHitTimes, 95),
            'cache_miss_p95_ms' => $this->calculatePercentile($cacheMissTimes, 95),
        ];

        $baseline = $this->getBaseline($componentName);

        $this->assertNoPerformanceRegression($componentName, $currentMetrics, $baseline);
        $this->updateBaselineIfImproved($componentName, $currentMetrics, $baseline);
    }

    #[Test]
    public function it_validates_throughput_regressions(): void
    {
        $componentName = 'Throughput';

        // Measure throughput over fixed time period
        $testDuration = 5; // seconds
        $requestCount = 0;
        $startTime = microtime(true);
        $responseTimes = [];

        while ((microtime(true) - $startTime) < $testDuration) {
            $message = $this->createRegressionTestMessage($requestCount);

            $requestStartTime = microtime(true);

            try {
                $response = $this->budgetMiddleware->handle($message, function ($msg) {
                    return $this->createMockResponse();
                });

                $responseTime = (microtime(true) - $requestStartTime) * 1000;
                $responseTimes[] = $responseTime;
                $requestCount++;
            } catch (\Exception $e) {
                // Count failed requests but don't break the loop
                $requestCount++;
            }
        }

        $actualDuration = microtime(true) - $startTime;
        $throughputRps = $requestCount / $actualDuration;

        $currentMetrics = [
            'requests_per_second' => $throughputRps,
            'total_requests' => $requestCount,
            'test_duration_seconds' => $actualDuration,
            'avg_response_time_ms' => array_sum($responseTimes) / count($responseTimes),
        ];

        $baseline = $this->getBaseline($componentName);

        // Throughput should not decrease significantly
        if (isset($baseline['requests_per_second'])) {
            $throughputDecrease = (($baseline['requests_per_second'] - $currentMetrics['requests_per_second'])
                / $baseline['requests_per_second']) * 100;

            $this->assertLessThan(self::REGRESSION_THRESHOLD_PERCENT, $throughputDecrease,
                "Throughput regression detected: {$throughputDecrease}% decrease in requests per second");
        }

        $this->updateBaselineIfImproved($componentName, $currentMetrics, $baseline);
    }

    /**
     * Measure cache hit performance.
     */
    protected function measureCacheHitPerformance(): array
    {
        $hitTimes = [];

        // Pre-populate cache
        for ($i = 1; $i <= 10; $i++) {
            $message = $this->createRegressionTestMessage($i);
            $this->budgetMiddleware->handle($message, fn ($msg) => $this->createMockResponse());
        }

        // Measure cache hits
        for ($i = 1; $i <= 50; $i++) {
            $userId = ($i % 10) + 1;

            $startTime = microtime(true);

            Cache::get("budget_limit_{$userId}_daily");
            Cache::get("daily_spending_{$userId}_" . now()->format('Y-m-d'));

            $hitTime = (microtime(true) - $startTime) * 1000;
            $hitTimes[] = $hitTime;
        }

        return $hitTimes;
    }

    /**
     * Measure cache miss performance.
     */
    protected function measureCacheMissPerformance(): array
    {
        $missTimes = [];

        for ($i = 1; $i <= 25; $i++) {
            $userId = $i;

            $startTime = microtime(true);

            // These will be cache misses and hit database
            Cache::remember("budget_limit_{$userId}_daily", 300, function () use ($userId) {
                return DB::table('ai_budgets')
                    ->where('user_id', $userId)
                    ->where('type', 'daily')
                    ->where('is_active', true)
                    ->value('limit_amount');
            });

            $missTime = (microtime(true) - $startTime) * 1000;
            $missTimes[] = $missTime;
        }

        return $missTimes;
    }

    /**
     * Calculate performance metrics from execution times.
     */
    protected function calculatePerformanceMetrics(array $times): array
    {
        sort($times);

        return [
            'avg_time_ms' => round(array_sum($times) / count($times), 3),
            'min_time_ms' => round(min($times), 3),
            'max_time_ms' => round(max($times), 3),
            'p50_time_ms' => round($this->calculatePercentile($times, 50), 3),
            'p95_time_ms' => round($this->calculatePercentile($times, 95), 3),
            'p99_time_ms' => round($this->calculatePercentile($times, 99), 3),
            'sample_size' => count($times),
            'measured_at' => now()->toISOString(),
        ];
    }

    /**
     * Assert no performance regression occurred.
     */
    protected function assertNoPerformanceRegression(string $component, array $current, ?array $baseline): void
    {
        if (! $baseline) {
            // No baseline exists, this becomes the new baseline
            $this->markTestSkipped("No baseline exists for {$component}, establishing new baseline");

            return;
        }

        $criticalMetrics = ['avg_time_ms', 'p95_time_ms', 'max_time_ms'];

        foreach ($criticalMetrics as $metric) {
            if (! isset($baseline[$metric]) || ! isset($current[$metric])) {
                continue;
            }

            $increase = (($current[$metric] - $baseline[$metric]) / $baseline[$metric]) * 100;

            $this->assertLessThan(self::REGRESSION_THRESHOLD_PERCENT, $increase,
                "Performance regression detected in {$component}.{$metric}: " .
                "{$increase}% increase from {$baseline[$metric]}ms to {$current[$metric]}ms"
            );
        }
    }

    /**
     * Update baseline if performance improved significantly.
     */
    protected function updateBaselineIfImproved(string $component, array $current, ?array $baseline): void
    {
        if (! $baseline) {
            // Set initial baseline
            $this->setBaseline($component, $current);

            return;
        }

        // Update baseline if average performance improved by more than 10%
        if (isset($baseline['avg_time_ms']) && isset($current['avg_time_ms'])) {
            $improvement = (($baseline['avg_time_ms'] - $current['avg_time_ms']) / $baseline['avg_time_ms']) * 100;

            if ($improvement > 10) {
                echo "\nPerformance improvement detected in {$component}: {$improvement}% faster\n";
                $this->setBaseline($component, $current);
            }
        }
    }

    /**
     * Load performance baselines from storage.
     */
    protected function loadPerformanceBaselines(): void
    {
        $baselinePath = storage_path('app/testing/' . self::PERFORMANCE_BASELINES_FILE);

        if (file_exists($baselinePath)) {
            $this->performanceBaselines = json_decode(file_get_contents($baselinePath), true) ?: [];
        } else {
            $this->performanceBaselines = [];
        }
    }

    /**
     * Get baseline for component.
     */
    protected function getBaseline(string $component): ?array
    {
        return $this->performanceBaselines[$component] ?? null;
    }

    /**
     * Set baseline for component.
     */
    protected function setBaseline(string $component, array $metrics): void
    {
        $this->performanceBaselines[$component] = $metrics;
        $this->savePerformanceBaselines();
    }

    /**
     * Save performance baselines to storage.
     */
    protected function savePerformanceBaselines(): void
    {
        $baselineDir = storage_path('app/testing');

        if (! is_dir($baselineDir)) {
            mkdir($baselineDir, 0755, true);
        }

        $baselinePath = $baselineDir . '/' . self::PERFORMANCE_BASELINES_FILE;
        file_put_contents($baselinePath, json_encode($this->performanceBaselines, JSON_PRETTY_PRINT));
    }

    /**
     * Create regression test message.
     */
    protected function createRegressionTestMessage(int $index): AIMessage
    {
        return new AIMessage([
            'user_id' => ($index % 10) + 1,
            'content' => "Regression test message {$index}",
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'metadata' => [
                'regression_test' => true,
                'index' => $index,
            ],
        ]);
    }

    /**
     * Create mock response.
     */
    protected function createMockResponse(): \JTD\LaravelAI\Models\AIResponse
    {
        return new \JTD\LaravelAI\Models\AIResponse([
            'content' => 'Mock regression test response',
            'success' => true,
            'metadata' => ['test' => 'regression'],
        ]);
    }

    /**
     * Seed regression test data.
     */
    protected function seedRegressionTestData(): void
    {
        // Create consistent test data for regression testing
        for ($i = 1; $i <= 20; $i++) {
            DB::table('users')->insert([
                'id' => $i,
                'name' => "Regression Test User {$i}",
                'email' => "regression{$i}@example.com",
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('ai_budgets')->insert([
                'user_id' => $i,
                'type' => 'daily',
                'limit_amount' => 20.00,
                'current_usage' => 0.00,
                'period_start' => today(),
                'period_end' => today()->addDay(),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Create baseline usage data
        for ($userId = 1; $userId <= 10; $userId++) {
            for ($i = 0; $i < 3; $i++) {
                DB::table('ai_usage_costs')->insert([
                    'user_id' => $userId,
                    'provider' => 'openai',
                    'model' => 'gpt-4o-mini',
                    'input_tokens' => 100,
                    'output_tokens' => 50,
                    'total_tokens' => 150,
                    'input_cost' => 0.001,
                    'output_cost' => 0.002,
                    'total_cost' => 0.003,
                    'created_at' => now()->subMinutes($i * 10),
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
}
