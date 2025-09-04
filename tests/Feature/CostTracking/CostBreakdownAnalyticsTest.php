<?php

namespace JTD\LaravelAI\Tests\Feature\CostTracking;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use JTD\LaravelAI\Services\CostAnalyticsService;
use JTD\LaravelAI\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Cost Breakdown Analytics Tests
 *
 * Tests for Sprint4b Story 1: Real-time Cost Tracking with Events
 * Validates cost breakdown by provider, model, and user with historical data preservation.
 */
class CostBreakdownAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected CostAnalyticsService $analyticsService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->analyticsService = app(CostAnalyticsService::class);
        $this->seedCostData();
    }

    #[Test]
    public function it_provides_cost_breakdown_by_provider(): void
    {
        $userId = 1;
        $dateRange = 'month';

        $breakdown = $this->analyticsService->getCostBreakdownByProvider($userId, $dateRange);

        $this->assertIsArray($breakdown);
        $this->assertArrayHasKey('breakdown', $breakdown);
        $this->assertArrayHasKey('totals', $breakdown);
        $this->assertArrayHasKey('metadata', $breakdown);

        // Verify breakdown structure
        $this->assertNotEmpty($breakdown['breakdown']);
        foreach ($breakdown['breakdown'] as $provider) {
            $this->assertArrayHasKey('provider', $provider);
            $this->assertArrayHasKey('total_cost', $provider);
            $this->assertArrayHasKey('request_count', $provider);
            $this->assertArrayHasKey('avg_cost_per_request', $provider);
            $this->assertArrayHasKey('cost_per_1k_tokens', $provider);
        }

        // Verify totals
        $totals = $breakdown['totals'];
        $this->assertArrayHasKey('total_cost', $totals);
        $this->assertArrayHasKey('total_requests', $totals);
        $this->assertArrayHasKey('avg_cost_per_request', $totals);
        $this->assertArrayHasKey('unique_providers', $totals);

        // Verify we have multiple providers
        $this->assertGreaterThan(1, count($breakdown['breakdown']));
        $this->assertGreaterThan(1, $totals['unique_providers']);
    }

    #[Test]
    public function it_provides_cost_breakdown_by_model(): void
    {
        $userId = 1;
        $provider = 'openai';
        $dateRange = 'month';

        $breakdown = $this->analyticsService->getCostBreakdownByModel($userId, $provider, $dateRange);

        $this->assertIsArray($breakdown);
        $this->assertArrayHasKey('breakdown', $breakdown);
        $this->assertArrayHasKey('totals', $breakdown);
        $this->assertArrayHasKey('metadata', $breakdown);

        // Verify model breakdown structure
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

            // Verify provider filter worked
            $this->assertEquals($provider, $model['provider']);
        }
    }

    #[Test]
    public function it_provides_cost_breakdown_by_user(): void
    {
        $userIds = [1, 2, 3];
        $dateRange = 'month';

        $breakdown = $this->analyticsService->getCostBreakdownByUser($userIds, $dateRange);

        $this->assertIsArray($breakdown);
        $this->assertArrayHasKey('breakdown', $breakdown);
        $this->assertArrayHasKey('totals', $breakdown);
        $this->assertArrayHasKey('metadata', $breakdown);

        // Verify user breakdown structure
        foreach ($breakdown['breakdown'] as $user) {
            $this->assertArrayHasKey('user_id', $user);
            $this->assertArrayHasKey('total_cost', $user);
            $this->assertArrayHasKey('request_count', $user);
            $this->assertArrayHasKey('providers_used', $user);
            $this->assertArrayHasKey('models_used', $user);
            $this->assertArrayHasKey('cost_per_1k_tokens', $user);
            $this->assertArrayHasKey('first_request', $user);
            $this->assertArrayHasKey('last_request', $user);

            // Verify user is in requested list
            $this->assertContains($user['user_id'], $userIds);
        }

        // Verify we have multiple users
        $this->assertGreaterThan(1, count($breakdown['breakdown']));
    }

    #[Test]
    public function it_preserves_historical_cost_data(): void
    {
        // Test data spans multiple months
        $currentMonth = $this->analyticsService->getCostBreakdownByProvider(1, 'month');
        $lastMonth = $this->analyticsService->getCostBreakdownByProvider(1, 'last_month');
        $allTime = $this->analyticsService->getCostBreakdownByProvider(1, 'all');

        // Verify historical data is preserved
        $this->assertGreaterThan(0, $currentMonth['totals']['total_cost']);
        $this->assertGreaterThan(0, $lastMonth['totals']['total_cost']);
        $this->assertGreaterThan(0, $allTime['totals']['total_cost']);

        // All-time should be >= current month (since we have data across multiple months)
        $this->assertGreaterThanOrEqual(
            $currentMonth['totals']['total_cost'],
            $allTime['totals']['total_cost']
        );
    }

    #[Test]
    public function it_handles_different_date_ranges(): void
    {
        $userId = 1;
        $dateRanges = ['day', 'week', 'month', 'quarter', 'year', 'all'];

        foreach ($dateRanges as $dateRange) {
            $breakdown = $this->analyticsService->getCostBreakdownByProvider($userId, $dateRange);

            $this->assertIsArray($breakdown);
            $this->assertArrayHasKey('breakdown', $breakdown);
            $this->assertArrayHasKey('totals', $breakdown);

            // Verify metadata includes date range
            $this->assertEquals($dateRange, $breakdown['metadata']['date_range']);
        }
    }

    #[Test]
    public function it_calculates_accurate_cost_aggregations(): void
    {
        $userId = 1;
        $breakdown = $this->analyticsService->getCostBreakdownByProvider($userId, 'all');

        $totalFromBreakdown = array_sum(array_column($breakdown['breakdown'], 'total_cost'));
        $totalFromTotals = $breakdown['totals']['total_cost'];

        // Totals should match breakdown sum
        $this->assertEquals($totalFromBreakdown, $totalFromTotals, '', 0.01); // Allow small floating point differences

        // Request counts should also match
        $requestsFromBreakdown = array_sum(array_column($breakdown['breakdown'], 'request_count'));
        $requestsFromTotals = $breakdown['totals']['total_requests'];

        $this->assertEquals($requestsFromBreakdown, $requestsFromTotals);
    }

    #[Test]
    public function it_handles_empty_cost_data_gracefully(): void
    {
        // Clear all cost data
        DB::table('ai_cost_records')->truncate();

        $breakdown = $this->analyticsService->getCostBreakdownByProvider(999, 'month');

        $this->assertIsArray($breakdown);
        $this->assertEquals(0, $breakdown['totals']['total_cost']);
        $this->assertEquals(0, $breakdown['totals']['total_requests']);
        $this->assertEmpty($breakdown['breakdown']);
    }

    #[Test]
    public function it_caches_cost_breakdown_results(): void
    {
        Cache::flush();

        $userId = 1;
        $dateRange = 'month';

        // First call should hit database
        $startTime = microtime(true);
        $firstResult = $this->analyticsService->getCostBreakdownByProvider($userId, $dateRange);
        $firstCallTime = microtime(true) - $startTime;

        // Second call should hit cache
        $startTime = microtime(true);
        $secondResult = $this->analyticsService->getCostBreakdownByProvider($userId, $dateRange);
        $secondCallTime = microtime(true) - $startTime;

        // Results should be identical
        $this->assertEquals($firstResult, $secondResult);

        // Cached call should be faster
        $this->assertLessThan($firstCallTime, $secondCallTime);
    }

    #[Test]
    public function it_provides_cost_trends_over_time(): void
    {
        $userId = 1;
        $groupBy = 'day';
        $dateRange = 'month';

        $trends = $this->analyticsService->getHistoricalTrends($userId, $groupBy, $dateRange);

        $this->assertIsArray($trends);
        $this->assertArrayHasKey('trends', $trends);
        $this->assertArrayHasKey('summary', $trends);

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

    protected function seedCostData(): void
    {
        $providers = ['openai', 'anthropic', 'google'];
        $models = [
            'openai' => ['gpt-4o-mini', 'gpt-4o'],
            'anthropic' => ['claude-3-haiku', 'claude-3-sonnet'],
            'google' => ['gemini-2.0-flash', 'gemini-1.5-pro'],
        ];
        $users = [1, 2, 3];

        $costData = [];
        $baseDate = Carbon::now()->subDays(60);

        for ($i = 0; $i < 200; $i++) {
            $provider = $providers[array_rand($providers)];
            $model = $models[$provider][array_rand($models[$provider])];
            $userId = $users[array_rand($users)];

            $inputTokens = rand(500, 2000);
            $outputTokens = rand(200, 1000);
            $totalTokens = $inputTokens + $outputTokens;

            // Vary costs by provider
            $baseCost = match ($provider) {
                'openai' => 0.0001,
                'anthropic' => 0.0002,
                'google' => 0.00005,
            };

            $totalCost = ($inputTokens * $baseCost) + ($outputTokens * $baseCost * 2);

            $costData[] = [
                'user_id' => $userId,
                'conversation_id' => 'conv_' . rand(1, 50),
                'message_id' => $i + 1,
                'provider' => $provider,
                'model' => $model,
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'total_tokens' => $totalTokens,
                'input_cost' => $inputTokens * $baseCost,
                'output_cost' => $outputTokens * $baseCost * 2,
                'total_cost' => $totalCost,
                'currency' => 'USD',
                'pricing_source' => 'api',
                'processing_time_ms' => rand(500, 3000),
                'metadata' => json_encode(['test' => true]),
                'created_at' => $baseDate->copy()->addDays(rand(0, 60)),
                'updated_at' => now(),
            ];
        }

        DB::table('ai_cost_records')->insert($costData);
    }
}
