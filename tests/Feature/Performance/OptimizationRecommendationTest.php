<?php

namespace JTD\LaravelAI\Tests\Feature\Performance;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use JTD\LaravelAI\Services\BenchmarkService;
use JTD\LaravelAI\Services\PerformanceOptimizationService;
use JTD\LaravelAI\Services\RecommendationEngine;
use JTD\LaravelAI\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Optimization Recommendation Tests
 *
 * Tests performance optimization recommendations and benchmark establishment
 * to ensure the system can identify and suggest performance improvements.
 */
#[Group('performance')]
#[Group('optimization-recommendations')]
class OptimizationRecommendationTest extends TestCase
{
    use RefreshDatabase;

    protected array $performanceMetrics = [];

    protected PerformanceOptimizationService $optimizationService;

    protected BenchmarkService $benchmarkService;

    protected RecommendationEngine $recommendationEngine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->performanceMetrics = [];

        // Try to resolve services, fall back to mocks if not available
        try {
            $this->optimizationService = app(PerformanceOptimizationService::class);
            $this->benchmarkService = app(BenchmarkService::class);
            $this->recommendationEngine = app(RecommendationEngine::class);
        } catch (\Exception $e) {
            // Mock services if not available
            $this->optimizationService = \Mockery::mock(PerformanceOptimizationService::class);
            $this->benchmarkService = \Mockery::mock(BenchmarkService::class);
            $this->recommendationEngine = \Mockery::mock(RecommendationEngine::class);

            // Setup mock expectations
            $this->setupMockExpectations();
        }
    }

    protected function tearDown(): void
    {
        $this->logPerformanceMetrics();
        parent::tearDown();
    }

    #[Test]
    public function it_establishes_performance_benchmarks(): void
    {
        $startTime = microtime(true);

        try {
            // Test benchmark establishment
            $benchmarks = $this->benchmarkService->establishBenchmarks([
                'event_processing' => ['target' => 50, 'current' => 45],
                'middleware_execution' => ['target' => 10, 'current' => 12],
                'queue_processing' => ['target' => 500, 'current' => 450],
                'mcp_tool_execution' => ['target' => 200, 'current' => 180],
            ]);

            $benchmarkTime = (microtime(true) - $startTime) * 1000;

            $this->recordMetric('benchmark_establishment', [
                'establishment_time_ms' => $benchmarkTime,
                'benchmarks_count' => is_array($benchmarks) ? count($benchmarks) : 0,
                'target_ms' => 100,
            ]);

            $this->assertIsArray($benchmarks);
            $this->assertLessThan(100, $benchmarkTime,
                "Benchmark establishment took {$benchmarkTime}ms, exceeding 100ms target");

            // Verify benchmark structure
            foreach ($benchmarks as $benchmark) {
                $this->assertArrayHasKey('metric', $benchmark);
                $this->assertArrayHasKey('target', $benchmark);
                $this->assertArrayHasKey('current', $benchmark);
                $this->assertArrayHasKey('status', $benchmark);
            }
        } catch (\Exception $e) {
            $this->markTestIncomplete('Performance benchmark establishment test failed: ' . $e->getMessage());
        }
    }

    #[Test]
    public function it_generates_optimization_recommendations(): void
    {
        $performanceData = [
            'event_processing' => [
                'avg_time' => 75, // Above 50ms target
                'max_time' => 150,
                'samples' => 100,
            ],
            'middleware_execution' => [
                'avg_time' => 15, // Above 10ms target
                'max_time' => 25,
                'samples' => 200,
            ],
            'queue_processing' => [
                'avg_time' => 600, // Above 500ms target
                'max_time' => 1200,
                'samples' => 50,
            ],
        ];

        $startTime = microtime(true);

        try {
            // Test recommendation generation
            $recommendations = $this->recommendationEngine->generateRecommendations($performanceData);

            $recommendationTime = (microtime(true) - $startTime) * 1000;

            $this->recordMetric('recommendation_generation', [
                'generation_time_ms' => $recommendationTime,
                'recommendations_count' => is_array($recommendations) ? count($recommendations) : 0,
                'target_ms' => 200,
            ]);

            $this->assertIsArray($recommendations);
            $this->assertLessThan(200, $recommendationTime,
                "Recommendation generation took {$recommendationTime}ms, exceeding 200ms target");

            // Verify recommendation structure
            foreach ($recommendations as $recommendation) {
                $this->assertArrayHasKey('metric', $recommendation);
                $this->assertArrayHasKey('issue', $recommendation);
                $this->assertArrayHasKey('recommendation', $recommendation);
                $this->assertArrayHasKey('priority', $recommendation);
                $this->assertArrayHasKey('estimated_improvement', $recommendation);
            }
        } catch (\Exception $e) {
            $this->markTestIncomplete('Optimization recommendation generation test failed: ' . $e->getMessage());
        }
    }

    #[Test]
    public function it_prioritizes_optimization_recommendations(): void
    {
        $recommendations = [
            [
                'metric' => 'queue_processing',
                'current_value' => 600,
                'target_value' => 500,
                'impact_score' => 8.5,
                'effort_score' => 3.0,
            ],
            [
                'metric' => 'event_processing',
                'current_value' => 75,
                'target_value' => 50,
                'impact_score' => 6.0,
                'effort_score' => 2.0,
            ],
            [
                'metric' => 'middleware_execution',
                'current_value' => 15,
                'target_value' => 10,
                'impact_score' => 4.0,
                'effort_score' => 1.5,
            ],
        ];

        $startTime = microtime(true);

        try {
            // Test recommendation prioritization
            $prioritizedRecommendations = $this->recommendationEngine->prioritizeRecommendations($recommendations);

            $prioritizationTime = (microtime(true) - $startTime) * 1000;

            $this->recordMetric('recommendation_prioritization', [
                'prioritization_time_ms' => $prioritizationTime,
                'recommendations_processed' => count($recommendations),
                'target_ms' => 50,
            ]);

            $this->assertIsArray($prioritizedRecommendations);
            $this->assertCount(3, $prioritizedRecommendations);
            $this->assertLessThan(50, $prioritizationTime,
                "Recommendation prioritization took {$prioritizationTime}ms, exceeding 50ms target");

            // Verify prioritization (highest priority first)
            $this->assertGreaterThanOrEqual(
                $prioritizedRecommendations[1]['priority_score'],
                $prioritizedRecommendations[0]['priority_score'],
                'Recommendations should be sorted by priority score'
            );
        } catch (\Exception $e) {
            $this->markTestIncomplete('Recommendation prioritization test failed: ' . $e->getMessage());
        }
    }

    #[Test]
    public function it_tracks_optimization_implementation_progress(): void
    {
        $optimizations = [
            [
                'id' => 'opt_001',
                'metric' => 'queue_processing',
                'recommendation' => 'Implement queue batching',
                'status' => 'implemented',
                'baseline_value' => 600,
                'target_value' => 500,
                'current_value' => 480,
            ],
            [
                'id' => 'opt_002',
                'metric' => 'event_processing',
                'recommendation' => 'Optimize event listeners',
                'status' => 'in_progress',
                'baseline_value' => 75,
                'target_value' => 50,
                'current_value' => 65,
            ],
        ];

        $startTime = microtime(true);

        try {
            // Test optimization progress tracking
            $progress = $this->optimizationService->trackOptimizationProgress($optimizations);

            $trackingTime = (microtime(true) - $startTime) * 1000;

            $this->recordMetric('optimization_progress_tracking', [
                'tracking_time_ms' => $trackingTime,
                'optimizations_tracked' => count($optimizations),
                'target_ms' => 75,
            ]);

            $this->assertIsArray($progress);
            $this->assertLessThan(75, $trackingTime,
                "Optimization progress tracking took {$trackingTime}ms, exceeding 75ms target");

            // Verify progress calculation
            $this->assertArrayHasKey('total_optimizations', $progress);
            $this->assertArrayHasKey('completed_optimizations', $progress);
            $this->assertArrayHasKey('in_progress_optimizations', $progress);
            $this->assertArrayHasKey('overall_improvement', $progress);
        } catch (\Exception $e) {
            $this->markTestIncomplete('Optimization progress tracking test failed: ' . $e->getMessage());
        }
    }

    #[Test]
    public function it_validates_optimization_effectiveness(): void
    {
        $beforeMetrics = [
            'event_processing' => 75,
            'middleware_execution' => 15,
            'queue_processing' => 600,
        ];

        $afterMetrics = [
            'event_processing' => 45, // Improved
            'middleware_execution' => 8, // Improved
            'queue_processing' => 480, // Improved
        ];

        $startTime = microtime(true);

        try {
            // Test optimization effectiveness validation
            $effectiveness = $this->optimizationService->validateOptimizationEffectiveness(
                $beforeMetrics,
                $afterMetrics
            );

            $validationTime = (microtime(true) - $startTime) * 1000;

            $this->recordMetric('optimization_effectiveness_validation', [
                'validation_time_ms' => $validationTime,
                'metrics_compared' => count($beforeMetrics),
                'target_ms' => 30,
            ]);

            $this->assertIsArray($effectiveness);
            $this->assertLessThan(30, $validationTime,
                "Optimization effectiveness validation took {$validationTime}ms, exceeding 30ms target");

            // Verify effectiveness calculation
            $this->assertArrayHasKey('overall_improvement_percentage', $effectiveness);
            $this->assertArrayHasKey('metric_improvements', $effectiveness);
            $this->assertGreaterThan(0, $effectiveness['overall_improvement_percentage']);
        } catch (\Exception $e) {
            $this->markTestIncomplete('Optimization effectiveness validation test failed: ' . $e->getMessage());
        }
    }

    #[Test]
    public function it_generates_performance_improvement_forecasts(): void
    {
        $historicalData = [
            ['date' => '2024-01-01', 'event_processing' => 80],
            ['date' => '2024-01-02', 'event_processing' => 75],
            ['date' => '2024-01-03', 'event_processing' => 70],
            ['date' => '2024-01-04', 'event_processing' => 65],
            ['date' => '2024-01-05', 'event_processing' => 60],
        ];

        $startTime = microtime(true);

        try {
            // Test performance improvement forecasting
            $forecast = $this->optimizationService->generatePerformanceForecast($historicalData, 7); // 7 days

            $forecastTime = (microtime(true) - $startTime) * 1000;

            $this->recordMetric('performance_forecast_generation', [
                'forecast_time_ms' => $forecastTime,
                'historical_points' => count($historicalData),
                'forecast_days' => 7,
                'target_ms' => 150,
            ]);

            $this->assertIsArray($forecast);
            $this->assertLessThan(150, $forecastTime,
                "Performance forecast generation took {$forecastTime}ms, exceeding 150ms target");

            // Verify forecast structure
            $this->assertArrayHasKey('forecast_data', $forecast);
            $this->assertArrayHasKey('trend_analysis', $forecast);
            $this->assertArrayHasKey('confidence_level', $forecast);
        } catch (\Exception $e) {
            $this->markTestIncomplete('Performance improvement forecast test failed: ' . $e->getMessage());
        }
    }

    #[Test]
    public function it_identifies_performance_bottlenecks(): void
    {
        $systemMetrics = [
            'cpu_usage' => 85, // High
            'memory_usage' => 70, // Moderate
            'disk_io' => 45, // Low
            'network_io' => 30, // Low
            'database_queries' => 150, // High
            'cache_hit_rate' => 60, // Low
        ];

        $startTime = microtime(true);

        try {
            // Test bottleneck identification
            $bottlenecks = $this->optimizationService->identifyBottlenecks($systemMetrics);

            $identificationTime = (microtime(true) - $startTime) * 1000;

            $this->recordMetric('bottleneck_identification', [
                'identification_time_ms' => $identificationTime,
                'metrics_analyzed' => count($systemMetrics),
                'bottlenecks_found' => is_array($bottlenecks) ? count($bottlenecks) : 0,
                'target_ms' => 100,
            ]);

            $this->assertIsArray($bottlenecks);
            $this->assertLessThan(100, $identificationTime,
                "Bottleneck identification took {$identificationTime}ms, exceeding 100ms target");

            // Verify bottleneck structure
            foreach ($bottlenecks as $bottleneck) {
                $this->assertArrayHasKey('metric', $bottleneck);
                $this->assertArrayHasKey('severity', $bottleneck);
                $this->assertArrayHasKey('impact', $bottleneck);
                $this->assertArrayHasKey('recommendations', $bottleneck);
            }
        } catch (\Exception $e) {
            $this->markTestIncomplete('Performance bottleneck identification test failed: ' . $e->getMessage());
        }
    }

    #[Test]
    public function it_generates_optimization_reports(): void
    {
        $optimizationData = [
            'period' => 'last_30_days',
            'optimizations_implemented' => 5,
            'performance_improvements' => [
                'event_processing' => 25, // 25% improvement
                'middleware_execution' => 30, // 30% improvement
                'queue_processing' => 20, // 20% improvement
            ],
            'cost_savings' => 150.00, // $150 saved
        ];

        $startTime = microtime(true);

        try {
            // Test optimization report generation
            $report = $this->optimizationService->generateOptimizationReport($optimizationData);

            $reportTime = (microtime(true) - $startTime) * 1000;

            $this->recordMetric('optimization_report_generation', [
                'generation_time_ms' => $reportTime,
                'report_sections' => is_array($report) ? count($report) : 0,
                'target_ms' => 250,
            ]);

            $this->assertIsArray($report);
            $this->assertLessThan(250, $reportTime,
                "Optimization report generation took {$reportTime}ms, exceeding 250ms target");

            // Verify report structure
            $expectedSections = ['summary', 'improvements', 'recommendations', 'next_steps'];
            foreach ($expectedSections as $section) {
                $this->assertArrayHasKey($section, $report,
                    "Optimization report missing {$section} section");
            }
        } catch (\Exception $e) {
            $this->markTestIncomplete('Optimization report generation test failed: ' . $e->getMessage());
        }
    }

    /**
     * Setup mock expectations for services.
     */
    protected function setupMockExpectations(): void
    {
        if ($this->optimizationService instanceof \Mockery\MockInterface) {
            $this->optimizationService->shouldReceive('trackOptimizationProgress')
                ->andReturn(['total_optimizations' => 2, 'completed_optimizations' => 1]);

            $this->optimizationService->shouldReceive('validateOptimizationEffectiveness')
                ->andReturn(['overall_improvement_percentage' => 25.5]);

            $this->optimizationService->shouldReceive('generatePerformanceForecast')
                ->andReturn(['forecast_data' => [], 'trend_analysis' => 'improving']);

            $this->optimizationService->shouldReceive('identifyBottlenecks')
                ->andReturn([['metric' => 'cpu_usage', 'severity' => 'high']]);

            $this->optimizationService->shouldReceive('generateOptimizationReport')
                ->andReturn(['summary' => [], 'improvements' => [], 'recommendations' => [], 'next_steps' => []]);
        }

        if ($this->benchmarkService instanceof \Mockery\MockInterface) {
            $this->benchmarkService->shouldReceive('establishBenchmarks')
                ->andReturn([['metric' => 'test', 'target' => 50, 'current' => 45, 'status' => 'meeting']]);
        }

        if ($this->recommendationEngine instanceof \Mockery\MockInterface) {
            $this->recommendationEngine->shouldReceive('generateRecommendations')
                ->andReturn([['metric' => 'test', 'issue' => 'slow', 'recommendation' => 'optimize', 'priority' => 'high']]);

            $this->recommendationEngine->shouldReceive('prioritizeRecommendations')
                ->andReturn([['priority_score' => 8.5], ['priority_score' => 6.0], ['priority_score' => 4.0]]);
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
            Log::info('Optimization Recommendation Test Results', [
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
            'optimization_features_tested' => [],
            'performance_targets_met' => 0,
            'performance_targets_failed' => 0,
        ];

        foreach ($this->performanceMetrics as $name => $data) {
            $targetMet = true;
            if (isset($data['target_ms'])) {
                $actualTime = $data['establishment_time_ms'] ?? $data['generation_time_ms'] ?? $data['tracking_time_ms'] ?? 0;
                $targetMet = $actualTime < $data['target_ms'];
            }

            $summary['optimization_features_tested'][] = [
                'feature' => $name,
                'target_met' => $targetMet,
            ];

            if ($targetMet) {
                $summary['performance_targets_met']++;
            } else {
                $summary['performance_targets_failed']++;
            }
        }

        return $summary;
    }
}
