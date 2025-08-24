<?php

namespace JTD\LaravelAI\Tests\Unit\Services;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use JTD\LaravelAI\Services\CostAnalyticsService;
use JTD\LaravelAI\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Cost Analytics Service Tests
 *
 * Comprehensive tests for cost analytics functionality with performance
 * benchmarks and accuracy validation.
 */
#[Group('cost-analytics')]
class CostAnalyticsServiceTest extends TestCase
{
    use RefreshDatabase;

    protected CostAnalyticsService $costAnalyticsService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->costAnalyticsService = app(CostAnalyticsService::class);
        $this->seedTestData();
    }

    #[Test]
    public function it_calculates_cost_breakdown_by_provider(): void
    {
        $userId = 1;
        $dateRange = 'month';

        $breakdown = $this->costAnalyticsService->getCostBreakdownByProvider($userId, $dateRange);

        $this->assertIsArray($breakdown);
        $this->assertArrayHasKey('by_provider', $breakdown);
        $this->assertArrayHasKey('totals', $breakdown);
        $this->assertArrayHasKey('metadata', $breakdown);

        // Verify totals structure
        $totals = $breakdown['totals'];
        $this->assertArrayHasKey('total_cost', $totals);
        $this->assertArrayHasKey('total_requests', $totals);
        $this->assertArrayHasKey('avg_cost_per_request', $totals);
        $this->assertArrayHasKey('unique_providers', $totals);

        // Verify provider data structure
        foreach ($breakdown['by_provider'] as $provider) {
            $this->assertArrayHasKey('provider', $provider);
            $this->assertArrayHasKey('total_cost', $provider);
            $this->assertArrayHasKey('request_count', $provider);
            $this->assertArrayHasKey('avg_cost_per_request', $provider);
            $this->assertArrayHasKey('percentage', $provider);
        }
    }

    #[Test]
    public function it_calculates_cost_breakdown_by_model(): void
    {
        $userId = 1;
        $provider = 'openai';
        $dateRange = 'month';

        $breakdown = $this->costAnalyticsService->getCostBreakdownByModel($userId, $provider, $dateRange);

        $this->assertIsArray($breakdown);
        $this->assertArrayHasKey('by_model', $breakdown);
        $this->assertArrayHasKey('totals', $breakdown);

        // Verify model data structure
        foreach ($breakdown['by_model'] as $model) {
            $this->assertArrayHasKey('model', $model);
            $this->assertArrayHasKey('provider', $model);
            $this->assertArrayHasKey('total_cost', $model);
            $this->assertArrayHasKey('request_count', $model);
            $this->assertArrayHasKey('avg_tokens_per_request', $model);
        }
    }

    #[Test]
    public function it_calculates_cost_breakdown_by_user(): void
    {
        $userIds = [1, 2, 3];
        $dateRange = 'month';

        $breakdown = $this->costAnalyticsService->getCostBreakdownByUser($userIds, $dateRange);

        $this->assertIsArray($breakdown);
        $this->assertArrayHasKey('by_user', $breakdown);
        $this->assertArrayHasKey('totals', $breakdown);

        // Verify user data structure
        foreach ($breakdown['by_user'] as $user) {
            $this->assertArrayHasKey('user_id', $user);
            $this->assertArrayHasKey('total_cost', $user);
            $this->assertArrayHasKey('request_count', $user);
            $this->assertArrayHasKey('providers_used', $user);
        }
    }

    #[Test]
    public function it_gets_historical_trends(): void
    {
        $userId = 1;
        $groupBy = 'day';
        $dateRange = 'month';

        $trends = $this->costAnalyticsService->getHistoricalTrends($userId, $groupBy, $dateRange);

        $this->assertIsArray($trends);
        $this->assertArrayHasKey('trends', $trends);
        $this->assertArrayHasKey('summary', $trends);
        $this->assertArrayHasKey('metadata', $trends);

        // Verify trend data structure
        foreach ($trends['trends'] as $trend) {
            $this->assertArrayHasKey('period', $trend);
            $this->assertArrayHasKey('total_cost', $trend);
            $this->assertArrayHasKey('request_count', $trend);
            $this->assertArrayHasKey('avg_cost_per_request', $trend);
        }

        // Verify summary structure
        $summary = $trends['summary'];
        $this->assertArrayHasKey('total_periods', $summary);
        $this->assertArrayHasKey('avg_daily_cost', $summary);
        $this->assertArrayHasKey('trend_direction', $summary);
        $this->assertArrayHasKey('growth_rate', $summary);
    }

    #[Test]
    public function it_calculates_cost_efficiency_metrics(): void
    {
        $userId = 1;
        $dateRange = 'month';

        $efficiency = $this->costAnalyticsService->getCostEfficiencyMetrics($userId, $dateRange);

        $this->assertIsArray($efficiency);
        $this->assertArrayHasKey('efficiency_score', $efficiency);
        $this->assertArrayHasKey('cost_per_token', $efficiency);
        $this->assertArrayHasKey('provider_efficiency', $efficiency);
        $this->assertArrayHasKey('model_efficiency', $efficiency);
        $this->assertArrayHasKey('recommendations', $efficiency);

        // Verify efficiency score is between 0 and 100
        $this->assertGreaterThanOrEqual(0, $efficiency['efficiency_score']);
        $this->assertLessThanOrEqual(100, $efficiency['efficiency_score']);

        // Verify recommendations structure
        $this->assertIsArray($efficiency['recommendations']);
        foreach ($efficiency['recommendations'] as $recommendation) {
            $this->assertArrayHasKey('type', $recommendation);
            $this->assertArrayHasKey('message', $recommendation);
            $this->assertArrayHasKey('priority', $recommendation);
        }
    }

    #[Test]
    public function it_performs_cost_breakdown_within_performance_target(): void
    {
        $userId = 1;
        $dateRange = 'month';

        $startTime = microtime(true);
        $breakdown = $this->costAnalyticsService->getCostBreakdownByProvider($userId, $dateRange);
        $executionTime = (microtime(true) - $startTime) * 1000; // Convert to milliseconds

        // Performance target: <100ms for cost breakdown
        $this->assertLessThan(100, $executionTime, 
            "Cost breakdown took {$executionTime}ms, exceeding 100ms target");

        $this->assertIsArray($breakdown);
        $this->assertNotEmpty($breakdown);
    }

    #[Test]
    public function it_caches_cost_analytics_results(): void
    {
        $userId = 1;
        $dateRange = 'month';

        // Clear cache
        Cache::flush();

        // First call should hit database
        $startTime = microtime(true);
        $firstResult = $this->costAnalyticsService->getCostBreakdownByProvider($userId, $dateRange);
        $firstCallTime = (microtime(true) - $startTime) * 1000;

        // Second call should hit cache
        $startTime = microtime(true);
        $secondResult = $this->costAnalyticsService->getCostBreakdownByProvider($userId, $dateRange);
        $secondCallTime = (microtime(true) - $startTime) * 1000;

        // Cache hit should be significantly faster
        $this->assertLessThan($firstCallTime / 2, $secondCallTime, 
            "Cached call should be at least 50% faster");

        // Results should be identical
        $this->assertEquals($firstResult, $secondResult);
    }

    #[Test]
    #[DataProvider('dateRangeProvider')]
    public function it_handles_different_date_ranges(string $dateRange): void
    {
        $userId = 1;

        $breakdown = $this->costAnalyticsService->getCostBreakdownByProvider($userId, $dateRange);

        $this->assertIsArray($breakdown);
        $this->assertArrayHasKey('metadata', $breakdown);
        $this->assertEquals($dateRange, $breakdown['metadata']['date_range']);
    }

    #[Test]
    public function it_handles_empty_cost_data_gracefully(): void
    {
        // Clear all cost data
        DB::table('ai_usage_costs')->truncate();

        $userId = 999; // Non-existent user
        $breakdown = $this->costAnalyticsService->getCostBreakdownByProvider($userId, 'month');

        $this->assertIsArray($breakdown);
        $this->assertEquals(0, $breakdown['totals']['total_cost']);
        $this->assertEquals(0, $breakdown['totals']['total_requests']);
        $this->assertEmpty($breakdown['by_provider']);
    }

    #[Test]
    public function it_validates_cost_accuracy(): void
    {
        $userId = 1;
        $provider = 'openai';
        $model = 'gpt-4o-mini';
        $inputTokens = 1000;
        $outputTokens = 500;

        $accuracy = $this->costAnalyticsService->validateCostAccuracy(
            $userId, $provider, $model, $inputTokens, $outputTokens
        );

        $this->assertIsArray($accuracy);
        $this->assertArrayHasKey('is_accurate', $accuracy);
        $this->assertArrayHasKey('calculated_cost', $accuracy);
        $this->assertArrayHasKey('expected_cost', $accuracy);
        $this->assertArrayHasKey('variance_percentage', $accuracy);
        $this->assertArrayHasKey('accuracy_score', $accuracy);

        // Accuracy score should be between 0 and 100
        $this->assertGreaterThanOrEqual(0, $accuracy['accuracy_score']);
        $this->assertLessThanOrEqual(100, $accuracy['accuracy_score']);
    }

    #[Test]
    public function it_generates_cost_optimization_recommendations(): void
    {
        $userId = 1;
        $dateRange = 'month';

        $recommendations = $this->costAnalyticsService->generateOptimizationRecommendations($userId, $dateRange);

        $this->assertIsArray($recommendations);
        $this->assertArrayHasKey('recommendations', $recommendations);
        $this->assertArrayHasKey('potential_savings', $recommendations);
        $this->assertArrayHasKey('priority_actions', $recommendations);

        foreach ($recommendations['recommendations'] as $recommendation) {
            $this->assertArrayHasKey('type', $recommendation);
            $this->assertArrayHasKey('description', $recommendation);
            $this->assertArrayHasKey('impact', $recommendation);
            $this->assertArrayHasKey('effort', $recommendation);
        }
    }

    #[Test]
    public function it_handles_concurrent_cost_calculations(): void
    {
        $userId = 1;
        $dateRange = 'month';

        // Simulate concurrent requests
        $promises = [];
        for ($i = 0; $i < 5; $i++) {
            $promises[] = function () use ($userId, $dateRange) {
                return $this->costAnalyticsService->getCostBreakdownByProvider($userId, $dateRange);
            };
        }

        // Execute all promises
        $results = array_map(fn($promise) => $promise(), $promises);

        // All results should be identical
        $firstResult = $results[0];
        foreach ($results as $result) {
            $this->assertEquals($firstResult, $result);
        }
    }

    #[Test]
    public function it_calculates_cost_projections(): void
    {
        $userId = 1;
        $days = 30;

        $projections = $this->costAnalyticsService->getCostProjections($userId, $days);

        $this->assertIsArray($projections);
        $this->assertArrayHasKey('daily_projection', $projections);
        $this->assertArrayHasKey('monthly_projection', $projections);
        $this->assertArrayHasKey('confidence_level', $projections);
        $this->assertArrayHasKey('projection_method', $projections);

        // Projections should be positive numbers
        $this->assertGreaterThanOrEqual(0, $projections['daily_projection']);
        $this->assertGreaterThanOrEqual(0, $projections['monthly_projection']);
    }

    /**
     * Data provider for date range testing.
     */
    public static function dateRangeProvider(): array
    {
        return [
            ['week'],
            ['month'],
            ['quarter'],
            ['year'],
        ];
    }

    /**
     * Seed test data for cost analytics tests.
     */
    protected function seedTestData(): void
    {
        // Create test users
        DB::table('users')->insert([
            ['id' => 1, 'name' => 'Test User 1', 'email' => 'test1@example.com', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'Test User 2', 'email' => 'test2@example.com', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'name' => 'Test User 3', 'email' => 'test3@example.com', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Create test cost data
        $costData = [];
        $providers = ['openai', 'anthropic', 'google'];
        $models = ['gpt-4o-mini', 'claude-3-haiku', 'gemini-2.0-flash'];

        for ($i = 0; $i < 100; $i++) {
            $provider = $providers[array_rand($providers)];
            $model = $models[array_rand($models)];
            $userId = rand(1, 3);
            
            $costData[] = [
                'user_id' => $userId,
                'provider' => $provider,
                'model' => $model,
                'input_tokens' => rand(100, 2000),
                'output_tokens' => rand(50, 1000),
                'total_tokens' => rand(150, 3000),
                'input_cost' => rand(1, 50) / 1000, // $0.001 to $0.050
                'output_cost' => rand(1, 100) / 1000, // $0.001 to $0.100
                'total_cost' => rand(2, 150) / 1000, // $0.002 to $0.150
                'currency' => 'USD',
                'created_at' => now()->subDays(rand(0, 30)),
                'updated_at' => now(),
            ];
        }

        DB::table('ai_usage_costs')->insert($costData);
    }
}
