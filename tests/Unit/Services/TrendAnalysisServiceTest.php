<?php

namespace JTD\LaravelAI\Tests\Unit\Services;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use JTD\LaravelAI\Services\TrendAnalysisService;
use JTD\LaravelAI\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Trend Analysis Service Tests
 *
 * Comprehensive tests for trend analysis and forecasting functionality
 * with performance benchmarks and accuracy validation.
 */
#[Group('trend-analysis')]
class TrendAnalysisServiceTest extends TestCase
{
    use RefreshDatabase;

    protected TrendAnalysisService $trendAnalysisService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->trendAnalysisService = app(TrendAnalysisService::class);
        $this->seedTestData();
    }

    #[Test]
    public function it_analyzes_usage_trends_successfully(): void
    {
        $userId = 1;
        $period = 'daily';
        $days = 30;

        $trends = $this->trendAnalysisService->analyzeUsageTrends($userId, $period, $days);

        $this->assertIsArray($trends);
        $this->assertEquals('success', $trends['status']);
        $this->assertArrayHasKey('trend_analysis', $trends);
        $this->assertArrayHasKey('forecasting', $trends);
        $this->assertArrayHasKey('patterns', $trends);
        $this->assertArrayHasKey('anomalies', $trends);
        $this->assertArrayHasKey('recommendations', $trends);
        $this->assertArrayHasKey('metadata', $trends);

        // Verify trend analysis structure
        $trendAnalysis = $trends['trend_analysis'];
        $this->assertArrayHasKey('trend_direction', $trendAnalysis);
        $this->assertArrayHasKey('trend_strength', $trendAnalysis);
        $this->assertArrayHasKey('slope', $trendAnalysis);
        $this->assertArrayHasKey('r_squared', $trendAnalysis);
        $this->assertArrayHasKey('volatility', $trendAnalysis);
        $this->assertArrayHasKey('growth_rate', $trendAnalysis);

        // Verify metadata
        $this->assertEquals($userId, $trends['metadata']['user_id']);
        $this->assertEquals($period, $trends['metadata']['period']);
        $this->assertEquals($days, $trends['metadata']['days_analyzed']);
    }

    #[Test]
    public function it_analyzes_cost_trends_successfully(): void
    {
        $userId = 1;
        $period = 'daily';
        $days = 30;

        $trends = $this->trendAnalysisService->analyzeCostTrends($userId, $period, $days);

        $this->assertIsArray($trends);
        $this->assertEquals('success', $trends['status']);
        $this->assertArrayHasKey('cost_trends', $trends);
        $this->assertArrayHasKey('cost_forecast', $trends);
        $this->assertArrayHasKey('spending_patterns', $trends);
        $this->assertArrayHasKey('cost_efficiency', $trends);
        $this->assertArrayHasKey('budget_projections', $trends);
        $this->assertArrayHasKey('cost_optimization', $trends);

        // Verify cost forecast structure
        $forecast = $trends['cost_forecast'];
        $this->assertArrayHasKey('daily_forecast', $forecast);
        $this->assertArrayHasKey('total_forecast_cost', $forecast);
        $this->assertArrayHasKey('monthly_projection', $forecast);
        $this->assertArrayHasKey('model_accuracy', $forecast);
        $this->assertArrayHasKey('forecast_confidence', $forecast);

        // Verify forecast values are reasonable
        $this->assertGreaterThanOrEqual(0, $forecast['total_forecast_cost']);
        $this->assertGreaterThanOrEqual(0, $forecast['monthly_projection']);
        $this->assertGreaterThanOrEqual(0, $forecast['model_accuracy']);
        $this->assertLessThanOrEqual(1, $forecast['model_accuracy']);
    }

    #[Test]
    public function it_compares_provider_performance(): void
    {
        $userId = 1;
        $days = 30;

        $comparison = $this->trendAnalysisService->compareProviderPerformance($userId, $days);

        $this->assertIsArray($comparison);
        $this->assertArrayHasKey('provider_rankings', $comparison);
        $this->assertArrayHasKey('performance_metrics', $comparison);
        $this->assertArrayHasKey('cost_comparison', $comparison);
        $this->assertArrayHasKey('efficiency_analysis', $comparison);
        $this->assertArrayHasKey('recommendations', $comparison);
        $this->assertArrayHasKey('trends', $comparison);
        $this->assertArrayHasKey('metadata', $comparison);

        // Verify metadata
        $this->assertEquals($userId, $comparison['metadata']['user_id']);
        $this->assertEquals($days, $comparison['metadata']['days_analyzed']);
        $this->assertGreaterThan(0, $comparison['metadata']['providers_analyzed']);
    }

    #[Test]
    public function it_compares_model_performance(): void
    {
        $userId = 1;
        $provider = 'openai';
        $days = 30;

        $comparison = $this->trendAnalysisService->compareModelPerformance($userId, $provider, $days);

        $this->assertIsArray($comparison);
        $this->assertArrayHasKey('model_rankings', $comparison);
        $this->assertArrayHasKey('performance_metrics', $comparison);
        $this->assertArrayHasKey('cost_analysis', $comparison);
        $this->assertArrayHasKey('efficiency_scores', $comparison);
        $this->assertArrayHasKey('usage_patterns', $comparison);
        $this->assertArrayHasKey('recommendations', $comparison);
        $this->assertArrayHasKey('metadata', $comparison);

        // Verify metadata
        $this->assertEquals($userId, $comparison['metadata']['user_id']);
        $this->assertEquals($provider, $comparison['metadata']['provider_filter']);
        $this->assertEquals($days, $comparison['metadata']['days_analyzed']);
    }

    #[Test]
    public function it_generates_optimization_recommendations(): void
    {
        $userId = 1;
        $days = 30;

        $recommendations = $this->trendAnalysisService->generateOptimizationRecommendations($userId, $days);

        $this->assertIsArray($recommendations);
        $this->assertArrayHasKey('priority_recommendations', $recommendations);
        $this->assertArrayHasKey('cost_optimization', $recommendations);
        $this->assertArrayHasKey('performance_optimization', $recommendations);
        $this->assertArrayHasKey('usage_optimization', $recommendations);
        $this->assertArrayHasKey('budget_optimization', $recommendations);
        $this->assertArrayHasKey('implementation_plan', $recommendations);
        $this->assertArrayHasKey('expected_savings', $recommendations);
        $this->assertArrayHasKey('metadata', $recommendations);

        // Verify metadata
        $this->assertEquals($userId, $recommendations['metadata']['user_id']);
        $this->assertEquals($days, $recommendations['metadata']['analysis_period']);
        $this->assertArrayHasKey('confidence_score', $recommendations['metadata']);
    }

    #[Test]
    public function it_handles_insufficient_data_gracefully(): void
    {
        // Clear all data
        DB::table('ai_usage_analytics')->truncate();
        DB::table('ai_usage_costs')->truncate();

        $userId = 999; // Non-existent user
        $trends = $this->trendAnalysisService->analyzeUsageTrends($userId, 'daily', 30);

        $this->assertIsArray($trends);
        $this->assertEquals('insufficient_data', $trends['status']);
        $this->assertArrayHasKey('message', $trends);
        $this->assertArrayHasKey('data_points', $trends);
        $this->assertArrayHasKey('required_points', $trends);
    }

    #[Test]
    public function it_performs_trend_analysis_within_performance_target(): void
    {
        $userId = 1;
        $period = 'daily';
        $days = 30;

        $startTime = microtime(true);
        $trends = $this->trendAnalysisService->analyzeUsageTrends($userId, $period, $days);
        $executionTime = (microtime(true) - $startTime) * 1000; // Convert to milliseconds

        // Performance target: <500ms for trend analysis
        $this->assertLessThan(500, $executionTime, 
            "Trend analysis took {$executionTime}ms, exceeding 500ms target");

        $this->assertIsArray($trends);
        $this->assertEquals('success', $trends['status']);
    }

    #[Test]
    public function it_caches_trend_analysis_results(): void
    {
        $userId = 1;
        $period = 'daily';
        $days = 30;

        // Clear cache
        Cache::flush();

        // First call should hit database
        $startTime = microtime(true);
        $firstResult = $this->trendAnalysisService->analyzeUsageTrends($userId, $period, $days);
        $firstCallTime = (microtime(true) - $startTime) * 1000;

        // Second call should hit cache
        $startTime = microtime(true);
        $secondResult = $this->trendAnalysisService->analyzeUsageTrends($userId, $period, $days);
        $secondCallTime = (microtime(true) - $startTime) * 1000;

        // Cache hit should be significantly faster
        $this->assertLessThan($firstCallTime / 2, $secondCallTime, 
            "Cached call should be at least 50% faster");

        // Results should be identical
        $this->assertEquals($firstResult, $secondResult);
    }

    #[Test]
    #[DataProvider('periodProvider')]
    public function it_handles_different_analysis_periods(string $period): void
    {
        $userId = 1;
        $days = 30;

        $trends = $this->trendAnalysisService->analyzeUsageTrends($userId, $period, $days);

        $this->assertIsArray($trends);
        $this->assertEquals('success', $trends['status']);
        $this->assertEquals($period, $trends['metadata']['period']);
    }

    #[Test]
    public function it_calculates_accurate_linear_regression(): void
    {
        // Test with known data points
        $values = [1, 2, 3, 4, 5];
        
        // Use reflection to test protected method
        $reflection = new \ReflectionClass($this->trendAnalysisService);
        $method = $reflection->getMethod('calculateLinearRegression');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->trendAnalysisService, $values);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('slope', $result);
        $this->assertArrayHasKey('intercept', $result);
        $this->assertArrayHasKey('r_squared', $result);

        // For perfect linear data, slope should be 1 and R² should be 1
        $this->assertEquals(1.0, $result['slope'], '', 0.01);
        $this->assertEquals(1.0, $result['r_squared'], '', 0.01);
    }

    #[Test]
    public function it_determines_trend_direction_correctly(): void
    {
        $reflection = new \ReflectionClass($this->trendAnalysisService);
        $method = $reflection->getMethod('determineTrendDirection');
        $method->setAccessible(true);

        // Test increasing trend
        $this->assertEquals('increasing', $method->invoke($this->trendAnalysisService, 0.5));
        
        // Test decreasing trend
        $this->assertEquals('decreasing', $method->invoke($this->trendAnalysisService, -0.5));
        
        // Test stable trend
        $this->assertEquals('stable', $method->invoke($this->trendAnalysisService, 0.005));
    }

    #[Test]
    public function it_calculates_volatility_correctly(): void
    {
        $reflection = new \ReflectionClass($this->trendAnalysisService);
        $method = $reflection->getMethod('calculateVolatility');
        $method->setAccessible(true);

        // Test with stable data
        $stableData = [10, 10, 10, 10, 10];
        $stableVolatility = $method->invoke($this->trendAnalysisService, $stableData);
        $this->assertEquals(0, $stableVolatility);

        // Test with volatile data
        $volatileData = [1, 10, 1, 10, 1];
        $volatileVolatility = $method->invoke($this->trendAnalysisService, $volatileData);
        $this->assertGreaterThan(50, $volatileVolatility); // High volatility
    }

    #[Test]
    public function it_generates_reasonable_forecasts(): void
    {
        $userId = 1;
        $period = 'daily';
        $days = 30;

        $trends = $this->trendAnalysisService->analyzeUsageTrends($userId, $period, $days);
        
        $this->assertEquals('success', $trends['status']);
        
        $forecast = $trends['forecasting'];
        $this->assertArrayHasKey('predictions', $forecast);
        $this->assertArrayHasKey('forecast_periods', $forecast);
        $this->assertArrayHasKey('model_accuracy', $forecast);

        // Verify forecast predictions
        foreach ($forecast['predictions'] as $prediction) {
            $this->assertArrayHasKey('period', $prediction);
            $this->assertArrayHasKey('predicted_value', $prediction);
            $this->assertArrayHasKey('confidence_interval', $prediction);
            
            // Predicted values should be non-negative
            $this->assertGreaterThanOrEqual(0, $prediction['predicted_value']);
            
            // Confidence interval should have lower and upper bounds
            $this->assertArrayHasKey('lower', $prediction['confidence_interval']);
            $this->assertArrayHasKey('upper', $prediction['confidence_interval']);
            $this->assertLessThanOrEqual(
                $prediction['confidence_interval']['upper'],
                $prediction['confidence_interval']['lower']
            );
        }
    }

    /**
     * Data provider for analysis periods.
     */
    public static function periodProvider(): array
    {
        return [
            ['daily'],
            ['weekly'],
            ['monthly'],
        ];
    }

    /**
     * Seed test data for trend analysis.
     */
    protected function seedTestData(): void
    {
        // Create test users
        DB::table('users')->insert([
            ['id' => 1, 'name' => 'Test User', 'email' => 'test@example.com', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Create usage analytics data with trends
        $analyticsData = [];
        $costData = [];
        $providers = ['openai', 'anthropic', 'google'];
        $models = ['gpt-4o-mini', 'claude-3-haiku', 'gemini-2.0-flash'];

        for ($i = 0; $i < 60; $i++) {
            $date = now()->subDays($i);
            $provider = $providers[array_rand($providers)];
            $model = $models[array_rand($models)];
            
            // Create trending data (increasing usage over time)
            $baseUsage = 100 + ($i * 2); // Increasing trend
            $tokens = $baseUsage + rand(-20, 20); // Add some variance
            $cost = $tokens * 0.001; // Simple cost calculation

            $analyticsData[] = [
                'user_id' => 1,
                'conversation_id' => 'conv_' . rand(1, 10),
                'provider' => $provider,
                'model' => $model,
                'input_tokens' => $tokens * 0.6,
                'output_tokens' => $tokens * 0.4,
                'total_tokens' => $tokens,
                'processing_time_ms' => rand(500, 2000),
                'response_time_ms' => rand(1000, 3000),
                'success' => true,
                'content_length' => rand(100, 1000),
                'response_length' => rand(200, 2000),
                'created_at' => $date,
                'updated_at' => $date,
            ];

            $costData[] = [
                'user_id' => 1,
                'provider' => $provider,
                'model' => $model,
                'input_tokens' => $tokens * 0.6,
                'output_tokens' => $tokens * 0.4,
                'total_tokens' => $tokens,
                'input_cost' => $cost * 0.6,
                'output_cost' => $cost * 0.4,
                'total_cost' => $cost,
                'currency' => 'USD',
                'created_at' => $date,
                'updated_at' => $date,
            ];
        }

        DB::table('ai_usage_analytics')->insert($analyticsData);
        DB::table('ai_usage_costs')->insert($costData);
    }
}
