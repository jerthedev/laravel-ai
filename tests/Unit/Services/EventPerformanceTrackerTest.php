<?php

namespace JTD\LaravelAI\Tests\Unit\Services;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use JTD\LaravelAI\Events\PerformanceThresholdExceeded;
use JTD\LaravelAI\Services\EventPerformanceTracker;
use JTD\LaravelAI\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Group;

/**
 * Event Performance Tracker Tests
 *
 * Tests for event processing performance monitoring and analytics.
 */
#[Group('performance-tracking')]
class EventPerformanceTrackerTest extends TestCase
{
    use RefreshDatabase;

    protected EventPerformanceTracker $performanceTracker;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->performanceTracker = app(EventPerformanceTracker::class);
        $this->createPerformanceMetricsTable();
    }

    #[Test]
    public function it_tracks_event_processing_performance(): void
    {
        Event::fake();

        $eventName = 'TestEvent';
        $duration = 150.5; // Exceeds 100ms threshold
        $context = ['user_id' => 1, 'test' => 'data'];

        $this->performanceTracker->trackEventProcessing($eventName, $duration, $context);

        // Verify data was stored
        $this->assertDatabaseHas('ai_performance_metrics', [
            'component' => 'event_processing',
            'component_name' => $eventName,
            'duration_ms' => $duration,
            'exceeded_threshold' => true,
        ]);

        // Verify threshold exceeded event was fired
        Event::assertDispatched(PerformanceThresholdExceeded::class, function ($event) use ($eventName, $duration) {
            return $event->component === 'event_processing' 
                && $event->performanceData['duration_ms'] === $duration;
        });
    }

    #[Test]
    public function it_tracks_listener_execution_performance(): void
    {
        $listenerName = 'TestListener';
        $eventName = 'TestEvent';
        $duration = 75.2;
        $context = ['memory_usage' => 1024];

        $this->performanceTracker->trackListenerExecution($listenerName, $eventName, $duration, $context);

        // Verify data was stored
        $this->assertDatabaseHas('ai_performance_metrics', [
            'component' => 'listener_execution',
            'component_name' => $listenerName,
            'duration_ms' => $duration,
            'exceeded_threshold' => true, // Exceeds 50ms threshold
        ]);
    }

    #[Test]
    public function it_tracks_queue_job_performance(): void
    {
        $jobName = 'TestJob';
        $duration = 600.0; // Exceeds 500ms threshold
        $context = ['queue' => 'default'];

        $this->performanceTracker->trackQueueJobPerformance($jobName, $duration, $context);

        // Verify data was stored
        $this->assertDatabaseHas('ai_performance_metrics', [
            'component' => 'queue_job',
            'component_name' => $jobName,
            'duration_ms' => $duration,
            'exceeded_threshold' => true,
        ]);
    }

    #[Test]
    public function it_tracks_middleware_performance(): void
    {
        $middlewareName = 'TestMiddleware';
        $duration = 15.5; // Exceeds 10ms threshold
        $context = ['route' => '/api/test'];

        $this->performanceTracker->trackMiddlewarePerformance($middlewareName, $duration, $context);

        // Verify data was stored
        $this->assertDatabaseHas('ai_performance_metrics', [
            'component' => 'middleware_execution',
            'component_name' => $middlewareName,
            'duration_ms' => $duration,
            'exceeded_threshold' => true,
        ]);
    }

    #[Test]
    public function it_updates_real_time_metrics(): void
    {
        Cache::flush();

        $component = 'event_processing';
        $eventName = 'TestEvent';
        $duration = 50.0;

        // Track multiple executions
        for ($i = 0; $i < 3; $i++) {
            $this->performanceTracker->trackEventProcessing($eventName, $duration + $i * 10);
        }

        // Verify real-time metrics were updated
        $count = Cache::get("realtime_metrics_{$component}_count");
        $totalDuration = Cache::get("realtime_metrics_{$component}_total_duration");

        $this->assertEquals(3, $count);
        $this->assertEquals(180.0, $totalDuration); // 50 + 60 + 70
    }

    #[Test]
    public function it_generates_performance_analytics(): void
    {
        // Create test data
        $this->createTestPerformanceData();

        $analytics = $this->performanceTracker->getPerformanceAnalytics('event_processing', 'hour');

        $this->assertIsArray($analytics);
        $this->assertArrayHasKey('component', $analytics);
        $this->assertArrayHasKey('total_executions', $analytics);
        $this->assertArrayHasKey('avg_duration_ms', $analytics);
        $this->assertArrayHasKey('max_duration_ms', $analytics);
        $this->assertArrayHasKey('threshold_violations', $analytics);
        $this->assertArrayHasKey('performance_score', $analytics);

        $this->assertEquals('event_processing', $analytics['component']);
        $this->assertGreaterThan(0, $analytics['total_executions']);
        $this->assertGreaterThan(0, $analytics['avg_duration_ms']);
    }

    #[Test]
    public function it_generates_dashboard_data(): void
    {
        $this->createTestPerformanceData();

        $dashboardData = $this->performanceTracker->getDashboardData();

        $this->assertIsArray($dashboardData);
        $this->assertArrayHasKey('components', $dashboardData);
        $this->assertArrayHasKey('overall_health', $dashboardData);
        $this->assertArrayHasKey('alerts', $dashboardData);
        $this->assertArrayHasKey('last_updated', $dashboardData);

        // Verify component data structure
        foreach ($dashboardData['components'] as $component => $data) {
            $this->assertArrayHasKey('current_metrics', $data);
            $this->assertArrayHasKey('recent_performance', $data);
            $this->assertArrayHasKey('threshold_violations', $data);
            $this->assertArrayHasKey('trends', $data);
        }

        // Verify overall health structure
        $health = $dashboardData['overall_health'];
        $this->assertArrayHasKey('score', $health);
        $this->assertArrayHasKey('status', $health);
        $this->assertArrayHasKey('violation_rate', $health);
    }

    #[Test]
    public function it_identifies_performance_bottlenecks(): void
    {
        $this->createTestPerformanceData();

        $bottlenecks = $this->performanceTracker->getPerformanceBottlenecks(5);

        $this->assertIsArray($bottlenecks);
        $this->assertLessThanOrEqual(5, count($bottlenecks));

        foreach ($bottlenecks as $bottleneck) {
            $this->assertArrayHasKey('component', $bottleneck);
            $this->assertArrayHasKey('component_name', $bottleneck);
            $this->assertArrayHasKey('avg_duration_ms', $bottleneck);
            $this->assertArrayHasKey('threshold_violations', $bottleneck);
            $this->assertArrayHasKey('severity', $bottleneck);
            $this->assertArrayHasKey('violation_rate', $bottleneck);
        }
    }

    #[Test]
    public function it_generates_optimization_recommendations(): void
    {
        $this->createTestPerformanceData();

        $recommendations = $this->performanceTracker->getOptimizationRecommendations();

        $this->assertIsArray($recommendations);
        $this->assertArrayHasKey('recommendations', $recommendations);
        $this->assertArrayHasKey('summary', $recommendations);
        $this->assertArrayHasKey('generated_at', $recommendations);

        foreach ($recommendations['recommendations'] as $recommendation) {
            $this->assertArrayHasKey('component', $recommendation);
            $this->assertArrayHasKey('severity', $recommendation);
            $this->assertArrayHasKey('priority', $recommendation);
            $this->assertArrayHasKey('message', $recommendation);
            $this->assertArrayHasKey('metrics', $recommendation);
        }

        // Verify summary structure
        $summary = $recommendations['summary'];
        $this->assertArrayHasKey('total_recommendations', $summary);
        $this->assertArrayHasKey('by_severity', $summary);
        $this->assertArrayHasKey('by_component', $summary);
    }

    #[Test]
    public function it_handles_empty_analytics_gracefully(): void
    {
        // Clear all data
        DB::table('ai_performance_metrics')->truncate();

        $analytics = $this->performanceTracker->getPerformanceAnalytics('event_processing');

        $this->assertIsArray($analytics);
        $this->assertEquals(0, $analytics['total_executions']);
        $this->assertEquals(0, $analytics['avg_duration_ms']);
        $this->assertEquals(100, $analytics['performance_score']); // Perfect score with no data
    }

    #[Test]
    public function it_calculates_percentiles_correctly(): void
    {
        // Create test data with known values
        $durations = [10, 20, 30, 40, 50, 60, 70, 80, 90, 100];
        
        foreach ($durations as $duration) {
            DB::table('ai_performance_metrics')->insert([
                'component' => 'test_component',
                'component_name' => 'TestComponent',
                'duration_ms' => $duration,
                'threshold_ms' => 100,
                'exceeded_threshold' => false,
                'context_data' => '{}',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $analytics = $this->performanceTracker->getPerformanceAnalytics('test_component');

        // P95 should be around 95 (95th percentile of 10-100)
        $this->assertGreaterThan(90, $analytics['p95_duration_ms']);
        $this->assertLessThan(100, $analytics['p95_duration_ms']);

        // P99 should be around 99
        $this->assertGreaterThan(95, $analytics['p99_duration_ms']);
        $this->assertLessThanOrEqual(100, $analytics['p99_duration_ms']);
    }

    #[Test]
    public function it_performs_analytics_within_performance_targets(): void
    {
        $this->createTestPerformanceData();

        // Test analytics performance
        $startTime = microtime(true);
        $analytics = $this->performanceTracker->getPerformanceAnalytics('event_processing');
        $analyticsTime = (microtime(true) - $startTime) * 1000;

        // Test dashboard performance
        $startTime = microtime(true);
        $dashboard = $this->performanceTracker->getDashboardData();
        $dashboardTime = (microtime(true) - $startTime) * 1000;

        // Test bottlenecks performance
        $startTime = microtime(true);
        $bottlenecks = $this->performanceTracker->getPerformanceBottlenecks();
        $bottlenecksTime = (microtime(true) - $startTime) * 1000;

        // Performance assertions
        $this->assertLessThan(100, $analyticsTime, 
            "Analytics generation took {$analyticsTime}ms, exceeding 100ms target");
        
        $this->assertLessThan(200, $dashboardTime, 
            "Dashboard generation took {$dashboardTime}ms, exceeding 200ms target");
        
        $this->assertLessThan(150, $bottlenecksTime, 
            "Bottlenecks analysis took {$bottlenecksTime}ms, exceeding 150ms target");

        // Verify results are valid
        $this->assertIsArray($analytics);
        $this->assertIsArray($dashboard);
        $this->assertIsArray($bottlenecks);
    }

    /**
     * Create performance metrics table for testing.
     */
    protected function createPerformanceMetricsTable(): void
    {
        if (!DB::getSchemaBuilder()->hasTable('ai_performance_metrics')) {
            DB::getSchemaBuilder()->create('ai_performance_metrics', function ($table) {
                $table->id();
                $table->string('component', 50);
                $table->string('component_name', 100);
                $table->decimal('duration_ms', 8, 2);
                $table->decimal('threshold_ms', 8, 2);
                $table->boolean('exceeded_threshold')->default(false);
                $table->json('context_data')->nullable();
                $table->timestamps();
                
                $table->index(['component', 'created_at']);
                $table->index(['component_name', 'created_at']);
                $table->index(['exceeded_threshold', 'created_at']);
            });
        }
    }

    /**
     * Create test performance data.
     */
    protected function createTestPerformanceData(): void
    {
        $components = [
            'event_processing' => ['TestEvent1', 'TestEvent2', 'TestEvent3'],
            'listener_execution' => ['TestListener1', 'TestListener2'],
            'queue_job' => ['TestJob1', 'TestJob2'],
            'middleware_execution' => ['TestMiddleware1'],
        ];

        $thresholds = [
            'event_processing' => 100,
            'listener_execution' => 50,
            'queue_job' => 500,
            'middleware_execution' => 10,
        ];

        foreach ($components as $component => $names) {
            foreach ($names as $name) {
                for ($i = 0; $i < 10; $i++) {
                    $duration = rand(20, 200);
                    $threshold = $thresholds[$component];
                    
                    DB::table('ai_performance_metrics')->insert([
                        'component' => $component,
                        'component_name' => $name,
                        'duration_ms' => $duration,
                        'threshold_ms' => $threshold,
                        'exceeded_threshold' => $duration > $threshold,
                        'context_data' => json_encode(['test' => 'data']),
                        'created_at' => now()->subMinutes(rand(1, 60)),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }
}
