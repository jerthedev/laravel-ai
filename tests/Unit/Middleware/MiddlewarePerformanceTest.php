<?php

namespace JTD\LaravelAI\Tests\Unit\Middleware;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use JTD\LaravelAI\Events\PerformanceThresholdExceeded;
use JTD\LaravelAI\Http\Middleware\PerformanceMonitoringMiddleware;
use JTD\LaravelAI\Middleware\BudgetEnforcementMiddleware;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Services\BudgetService;
use JTD\LaravelAI\Services\EventPerformanceTracker;
use JTD\LaravelAI\Services\PricingService;
use JTD\LaravelAI\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Group;

/**
 * Middleware Performance Tests
 *
 * Tests for middleware execution time monitoring with <10ms overhead targets.
 */
#[Group('middleware-performance')]
class MiddlewarePerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected PerformanceMonitoringMiddleware $performanceMiddleware;
    protected BudgetEnforcementMiddleware $budgetMiddleware;
    protected EventPerformanceTracker $performanceTracker;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->performanceTracker = app(EventPerformanceTracker::class);
        $this->performanceMiddleware = new PerformanceMonitoringMiddleware($this->performanceTracker);
        
        $budgetService = app(BudgetService::class);
        $pricingService = app(PricingService::class);
        $this->budgetMiddleware = new BudgetEnforcementMiddleware($budgetService, $pricingService, $this->performanceTracker);
        
        $this->createPerformanceMetricsTable();
        $this->seedTestData();
    }

    #[Test]
    public function it_monitors_middleware_performance_with_minimal_overhead(): void
    {
        Event::fake();
        
        $request = Request::create('/api/ai/chat', 'POST', [
            'message' => 'Test message',
            'provider' => 'openai',
        ]);

        $overheadStartTime = microtime(true);
        
        $response = $this->performanceMiddleware->handle($request, function ($req) {
            // Simulate some processing time
            usleep(50000); // 50ms
            return response()->json(['success' => true]);
        });
        
        $totalTime = (microtime(true) - $overheadStartTime) * 1000;

        // Verify response is successful
        $this->assertEquals(200, $response->getStatusCode());
        
        // Verify monitoring overhead is minimal
        $expectedProcessingTime = 50; // 50ms simulated processing
        $monitoringOverhead = $totalTime - $expectedProcessingTime;
        
        $this->assertLessThan(10, $monitoringOverhead, 
            "Monitoring overhead {$monitoringOverhead}ms exceeds 10ms target");
    }

    #[Test]
    public function it_tracks_budget_middleware_performance(): void
    {
        Event::fake();
        
        $message = new AIMessage([
            'user_id' => 1,
            'content' => 'Test message',
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'metadata' => [],
        ]);

        $startTime = microtime(true);
        
        $response = $this->budgetMiddleware->handle($message, function ($msg) {
            return new \JTD\LaravelAI\Models\AIResponse([
                'content' => 'Test response',
                'success' => true,
                'metadata' => [],
            ]);
        });
        
        $executionTime = (microtime(true) - $startTime) * 1000;

        // Verify response is successful
        $this->assertTrue($response->success);
        
        // Verify performance target is met
        $this->assertLessThan(10, $executionTime, 
            "Budget middleware took {$executionTime}ms, exceeding 10ms target");

        // Verify performance was tracked
        $this->assertDatabaseHas('ai_performance_metrics', [
            'component' => 'middleware_execution',
            'component_name' => 'BudgetEnforcementMiddleware',
        ]);
    }

    #[Test]
    public function it_tracks_individual_middleware_performance(): void
    {
        $request = Request::create('/api/test', 'GET');
        
        $middlewareName = 'TestMiddleware';
        $expectedDuration = 25; // ms

        $response = $this->performanceMiddleware->trackMiddleware($middlewareName, $request, function ($req) use ($expectedDuration) {
            usleep($expectedDuration * 1000); // Convert to microseconds
            return response()->json(['test' => 'success']);
        });

        $this->assertEquals(200, $response->getStatusCode());

        // Verify performance was tracked
        $this->assertDatabaseHas('ai_performance_metrics', [
            'component' => 'middleware_execution',
            'component_name' => $middlewareName,
        ]);

        // Get the tracked duration
        $metric = \DB::table('ai_performance_metrics')
            ->where('component', 'middleware_execution')
            ->where('component_name', $middlewareName)
            ->first();

        $this->assertNotNull($metric);
        $this->assertGreaterThan($expectedDuration - 5, $metric->duration_ms); // Allow 5ms variance
        $this->assertLessThan($expectedDuration + 15, $metric->duration_ms); // Allow 15ms variance
    }

    #[Test]
    public function it_handles_middleware_errors_gracefully(): void
    {
        Event::fake();
        
        $request = Request::create('/api/test', 'GET');
        $middlewareName = 'FailingMiddleware';

        $this->expectException(\RuntimeException::class);

        try {
            $this->performanceMiddleware->trackMiddleware($middlewareName, $request, function ($req) {
                throw new \RuntimeException('Middleware failed');
            });
        } catch (\RuntimeException $e) {
            // Verify error was tracked
            $this->assertDatabaseHas('ai_performance_metrics', [
                'component' => 'middleware_execution',
                'component_name' => $middlewareName,
            ]);

            $metric = \DB::table('ai_performance_metrics')
                ->where('component', 'middleware_execution')
                ->where('component_name', $middlewareName)
                ->first();

            $contextData = json_decode($metric->context_data, true);
            $this->assertFalse($contextData['success']);
            $this->assertEquals('Middleware failed', $contextData['error']);

            throw $e;
        }
    }

    #[Test]
    public function it_fires_performance_threshold_events(): void
    {
        Event::fake();
        
        $request = Request::create('/api/test', 'GET');
        $middlewareName = 'SlowMiddleware';

        // Simulate slow middleware (exceeds 10ms threshold)
        $this->performanceMiddleware->trackMiddleware($middlewareName, $request, function ($req) {
            usleep(50000); // 50ms - exceeds threshold
            return response()->json(['test' => 'success']);
        });

        // Verify threshold exceeded event was fired
        Event::assertDispatched(PerformanceThresholdExceeded::class, function ($event) use ($middlewareName) {
            return $event->component === 'middleware_execution' 
                && $event->getComponentName() === $middlewareName
                && $event->getDuration() > 10;
        });
    }

    #[Test]
    public function it_tracks_request_metadata_accurately(): void
    {
        $request = Request::create('/api/ai/chat', 'POST', [
            'message' => 'Test message with metadata',
            'provider' => 'openai',
        ]);
        $request->headers->set('User-Agent', 'TestAgent/1.0');

        $response = $this->performanceMiddleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });

        $this->assertEquals(200, $response->getStatusCode());

        // Verify request metadata was tracked
        $metric = \DB::table('ai_performance_metrics')
            ->where('component', 'middleware_execution')
            ->where('component_name', 'request_total')
            ->first();

        $this->assertNotNull($metric);
        
        $contextData = json_decode($metric->context_data, true);
        $this->assertEquals('POST', $contextData['method']);
        $this->assertEquals('/api/ai/chat', $contextData['path']);
        $this->assertEquals(200, $contextData['status_code']);
        $this->assertTrue($contextData['success']);
        $this->assertArrayHasKey('payload_size', $contextData);
        $this->assertArrayHasKey('memory_peak', $contextData);
    }

    #[Test]
    public function it_measures_monitoring_overhead_accurately(): void
    {
        Cache::flush();
        
        $request = Request::create('/api/test', 'GET');

        // Execute with monitoring
        $startTime = microtime(true);
        $response = $this->performanceMiddleware->handle($request, function ($req) {
            // Minimal processing
            return response()->json(['test' => 'success']);
        });
        $monitoredTime = (microtime(true) - $startTime) * 1000;

        // Execute without monitoring (baseline)
        $startTime = microtime(true);
        $baselineResponse = response()->json(['test' => 'success']);
        $baselineTime = (microtime(true) - $startTime) * 1000;

        $overhead = $monitoredTime - $baselineTime;

        // Verify overhead is within acceptable limits
        $this->assertLessThan(5, $overhead, 
            "Monitoring overhead {$overhead}ms exceeds 5ms acceptable limit");

        // Verify overhead was tracked
        $metric = \DB::table('ai_performance_metrics')
            ->where('component', 'middleware_execution')
            ->where('component_name', 'monitoring_overhead')
            ->first();

        $this->assertNotNull($metric);
        $this->assertLessThan(5, $metric->duration_ms);
    }

    #[Test]
    public function it_performs_middleware_stack_performance_analysis(): void
    {
        $request = Request::create('/api/ai/chat', 'POST', [
            'message' => 'Test middleware stack',
        ]);

        // Simulate middleware stack
        $middlewareStack = [
            'AuthMiddleware' => 5,   // 5ms
            'ThrottleMiddleware' => 3, // 3ms
            'BudgetMiddleware' => 8,   // 8ms
            'ValidationMiddleware' => 4, // 4ms
        ];

        $totalExpectedTime = array_sum($middlewareStack);

        $stackStartTime = microtime(true);

        // Execute middleware stack
        foreach ($middlewareStack as $middlewareName => $expectedDuration) {
            $this->performanceMiddleware->trackMiddleware($middlewareName, $request, function ($req) use ($expectedDuration) {
                usleep($expectedDuration * 1000);
                return response()->json(['middleware' => 'executed']);
            });
        }

        $totalStackTime = (microtime(true) - $stackStartTime) * 1000;

        // Verify total stack time is reasonable
        $this->assertLessThan($totalExpectedTime + 20, $totalStackTime, 
            "Middleware stack took {$totalStackTime}ms, expected around {$totalExpectedTime}ms");

        // Verify all middleware were tracked
        foreach (array_keys($middlewareStack) as $middlewareName) {
            $this->assertDatabaseHas('ai_performance_metrics', [
                'component' => 'middleware_execution',
                'component_name' => $middlewareName,
            ]);
        }

        // Verify performance analytics
        $analytics = $this->performanceTracker->getPerformanceAnalytics('middleware_execution');
        $this->assertEquals(count($middlewareStack), $analytics['total_executions']);
        $this->assertGreaterThan(0, $analytics['avg_duration_ms']);
    }

    /**
     * Create performance metrics table for testing.
     */
    protected function createPerformanceMetricsTable(): void
    {
        if (!\DB::getSchemaBuilder()->hasTable('ai_performance_metrics')) {
            \DB::getSchemaBuilder()->create('ai_performance_metrics', function ($table) {
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
            });
        }
    }

    /**
     * Seed test data.
     */
    protected function seedTestData(): void
    {
        \DB::table('users')->insert([
            'id' => 1,
            'name' => 'Test User',
            'email' => 'test@example.com',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
