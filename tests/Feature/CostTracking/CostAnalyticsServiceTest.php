<?php

namespace JTD\LaravelAI\Tests\Unit\Services;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use JTD\LaravelAI\Services\CostAnalyticsService;
use JTD\LaravelAI\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Cost Analytics Service Tests
 *
 * Tests the CostAnalyticsService which provides analytics on cost data
 * stored by the event-driven cost tracking system. The service queries
 * the ai_usage_costs table that is populated by CostTrackingListener
 * when ResponseGenerated events are processed.
 *
 * Integration with event system:
 * - CostTrackingListener stores cost data in ai_usage_costs table
 * - CostAnalyticsService queries this table for analytics
 * - Tests use the same data structure as the event system
 */
#[Group('cost-analytics')]
class CostAnalyticsServiceTest extends TestCase
{
    use RefreshDatabase;

    protected CostAnalyticsService $costAnalyticsService;

    protected function setUp(): void
    {
        parent::setUp();

        // Initialize the CostAnalyticsService
        $this->costAnalyticsService = new CostAnalyticsService;

        // Seed test data for analytics calculations
        $this->seedTestData();
    }

    #[Test]
    public function it_calculates_cost_breakdown_by_provider(): void
    {
        $userId = 1;
        $dateRange = 'month';

        $breakdown = $this->costAnalyticsService->getCostBreakdownByProvider($userId, $dateRange);

        $this->assertIsArray($breakdown);
        $this->assertArrayHasKey('breakdown', $breakdown);
        $this->assertArrayHasKey('totals', $breakdown);
        $this->assertArrayHasKey('metadata', $breakdown);

        // Verify totals structure
        $totals = $breakdown['totals'];
        $this->assertArrayHasKey('total_cost', $totals);
        $this->assertArrayHasKey('total_requests', $totals);
        $this->assertArrayHasKey('avg_cost_per_request', $totals);
        $this->assertArrayHasKey('unique_providers', $totals);

        // Verify provider data structure
        foreach ($breakdown['breakdown'] as $provider) {
            $this->assertArrayHasKey('provider', $provider);
            $this->assertArrayHasKey('total_cost', $provider);
            $this->assertArrayHasKey('request_count', $provider);
            $this->assertArrayHasKey('avg_cost_per_request', $provider);
            $this->assertArrayHasKey('cost_per_1k_tokens', $provider);
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
        $this->assertArrayHasKey('breakdown', $breakdown);
        $this->assertArrayHasKey('totals', $breakdown);
        $this->assertArrayHasKey('metadata', $breakdown);

        // Verify model data structure
        foreach ($breakdown['breakdown'] as $model) {
            $this->assertArrayHasKey('model', $model);
            $this->assertArrayHasKey('provider', $model);
            $this->assertArrayHasKey('total_cost', $model);
            $this->assertArrayHasKey('request_count', $model);
            $this->assertArrayHasKey('total_tokens', $model);
            $this->assertArrayHasKey('cost_per_1k_tokens', $model);
            $this->assertArrayHasKey('avg_tokens_per_request', $model);
            $this->assertArrayHasKey('first_used', $model);
            $this->assertArrayHasKey('last_used', $model);
        }
    }

    #[Test]
    public function it_calculates_cost_breakdown_by_user(): void
    {
        $userIds = [1, 2, 3];
        $dateRange = 'month';

        $breakdown = $this->costAnalyticsService->getCostBreakdownByUser($userIds, $dateRange);

        $this->assertIsArray($breakdown);
        $this->assertArrayHasKey('breakdown', $breakdown);
        $this->assertArrayHasKey('totals', $breakdown);
        $this->assertArrayHasKey('metadata', $breakdown);

        // Verify user data structure
        foreach ($breakdown['breakdown'] as $user) {
            $this->assertArrayHasKey('user_id', $user);
            $this->assertArrayHasKey('total_cost', $user);
            $this->assertArrayHasKey('request_count', $user);
            $this->assertArrayHasKey('providers_used', $user);
            $this->assertArrayHasKey('models_used', $user);
            $this->assertArrayHasKey('cost_per_1k_tokens', $user);
            $this->assertArrayHasKey('first_request', $user);
            $this->assertArrayHasKey('last_request', $user);
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
            $this->assertArrayHasKey('avg_cost', $trend);
            $this->assertArrayHasKey('total_tokens', $trend);
            $this->assertArrayHasKey('unique_users', $trend);
            $this->assertArrayHasKey('providers_used', $trend);
            $this->assertArrayHasKey('cost_per_1k_tokens', $trend);
        }

        // Verify summary structure
        $summary = $trends['summary'];
        $this->assertArrayHasKey('total_periods', $summary);
        $this->assertArrayHasKey('avg_cost_per_period', $summary);
        $this->assertArrayHasKey('trend', $summary);
        $this->assertArrayHasKey('cost_change', $summary);
        $this->assertArrayHasKey('cost_change_percent', $summary);
        $this->assertArrayHasKey('peak_cost_period', $summary);
        $this->assertArrayHasKey('peak_cost_amount', $summary);
    }

    #[Test]
    public function it_calculates_cost_efficiency_metrics(): void
    {
        $userId = 1;
        $dateRange = 'month';

        $efficiency = $this->costAnalyticsService->getCostEfficiencyMetrics($userId, $dateRange);

        $this->assertIsArray($efficiency);
        $this->assertArrayHasKey('efficiency_metrics', $efficiency);
        $this->assertArrayHasKey('recommendations', $efficiency);
        $this->assertArrayHasKey('metadata', $efficiency);
        $this->assertIsArray($efficiency['efficiency_metrics']);

        // Verify recommendations structure
        $this->assertIsArray($efficiency['recommendations']);

        // Verify metadata structure
        $this->assertIsArray($efficiency['metadata']);
        $this->assertArrayHasKey('user_id', $efficiency['metadata']);
        $this->assertArrayHasKey('date_range', $efficiency['metadata']);
        $this->assertArrayHasKey('generated_at', $efficiency['metadata']);
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

        // Cache hit should be faster (allow for some variance in timing)
        $this->assertLessThan($firstCallTime, $secondCallTime,
            'Cached call should be faster than first call');

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
        $this->assertEmpty($breakdown['breakdown']);
    }

    // Removed validateCostAccuracy test - this functionality is handled by CostTrackingListener.trackCostAccuracy()

    // Removed generateOptimizationRecommendations test - this functionality is included in getCostEfficiencyMetrics()

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
        $results = array_map(fn ($promise) => $promise(), $promises);

        // All results should be identical
        $firstResult = $results[0];
        foreach ($results as $result) {
            $this->assertEquals($firstResult, $result);
        }
    }

    // Removed getCostProjections test - this method was never implemented and is not part of the current architecture

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
        // Clear existing data
        DB::table('ai_usage_costs')->truncate();

        // Create test cost data in ai_usage_costs table (the table CostAnalyticsService actually queries)
        $costData = [];
        $providers = ['openai', 'anthropic', 'google'];
        $models = ['gpt-4o-mini', 'claude-3-haiku', 'gemini-2.0-flash'];

        for ($i = 0; $i < 100; $i++) {
            $provider = $providers[array_rand($providers)];
            $model = $models[array_rand($models)];
            $userId = rand(1, 3);
            $inputTokens = rand(100, 2000);
            $outputTokens = rand(50, 1000);
            $totalTokens = $inputTokens + $outputTokens;
            $inputCost = $inputTokens * 0.00001; // $0.01 per 1k tokens
            $outputCost = $outputTokens * 0.00003; // $0.03 per 1k tokens
            $totalCost = $inputCost + $outputCost;

            $costData[] = [
                'user_id' => $userId,
                'conversation_id' => 'conv_' . rand(1, 20),
                'message_id' => rand(1, 1000),
                'provider' => $provider,
                'model' => $model,
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'total_tokens' => $totalTokens,
                'input_cost' => $inputCost,
                'output_cost' => $outputCost,
                'total_cost' => $totalCost,
                'currency' => 'USD',
                'pricing_source' => 'api',
                'processing_time_ms' => rand(500, 3000),
                'metadata' => json_encode(['test' => true]),
                'created_at' => now()->subDays(rand(0, 30)),
                'updated_at' => now(),
            ];
        }

        DB::table('ai_usage_costs')->insert($costData);
    }
}
