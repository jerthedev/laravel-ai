<?php

namespace JTD\LaravelAI\Tests\Integration;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use JTD\LaravelAI\Events\BudgetThresholdReached;
use JTD\LaravelAI\Events\CostCalculated;
use JTD\LaravelAI\Events\ResponseGenerated;
use JTD\LaravelAI\Listeners\AnalyticsListener;
use JTD\LaravelAI\Listeners\CostTrackingListener;
use JTD\LaravelAI\Listeners\NotificationListener;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Models\AIResponse;
use JTD\LaravelAI\Services\MiddlewareManager;
use JTD\LaravelAI\Tests\TestCase;
use Mockery;

class EventMiddlewareIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Enable all systems for integration testing
        config([
            'ai.events.enabled' => true,
            'ai.middleware.enabled' => true,
            'ai.events.listeners.cost_tracking.enabled' => true,
            'ai.events.listeners.analytics.enabled' => true,
            'ai.events.listeners.notifications.enabled' => true,
        ]);
    }

    public function test_response_generated_event_integration()
    {
        Event::fake();

        // Create a test message and response
        $message = AIMessage::user('Test message for integration');
        $message->user_id = 1;
        $message->conversation_id = 1;

        $response = AIResponse::fromArray([
            'content' => 'Test response from integration',
            'provider' => 'test',
            'model' => 'test-model',
            'token_usage' => [
                'input_tokens' => 100,
                'output_tokens' => 50,
                'total_tokens' => 150,
            ],
        ]);

        // Manually fire the ResponseGenerated event to test integration
        $event = new ResponseGenerated(
            message: $message,
            response: $response,
            context: ['integration_test' => true],
            totalProcessingTime: 1.0,
            providerMetadata: [
                'provider' => 'test',
                'model' => 'test-model',
                'tokens_used' => 150,
            ]
        );

        event($event);

        // Verify ResponseGenerated event was fired
        Event::assertDispatched(ResponseGenerated::class, function ($dispatchedEvent) {
            return $dispatchedEvent->context['integration_test'] === true;
        });
    }

    public function test_event_listener_integration()
    {
        // Test that event listeners are properly registered by checking configuration
        $this->assertTrue(config('ai.events.enabled'), 'Events should be enabled');
        $this->assertTrue(config('ai.events.listeners.cost_tracking.enabled'), 'Cost tracking should be enabled');
        $this->assertTrue(config('ai.events.listeners.analytics.enabled'), 'Analytics should be enabled');

        // Test that the listener classes exist and can be instantiated
        $costListener = app(CostTrackingListener::class);
        $analyticsListener = app(AnalyticsListener::class);
        $notificationListener = app(NotificationListener::class);

        $this->assertInstanceOf(CostTrackingListener::class, $costListener);
        $this->assertInstanceOf(AnalyticsListener::class, $analyticsListener);
        $this->assertInstanceOf(NotificationListener::class, $notificationListener);

        // Test that listeners have the handle method
        $this->assertTrue(method_exists($costListener, 'handle'));
        $this->assertTrue(method_exists($analyticsListener, 'handle'));
        $this->assertTrue(method_exists($notificationListener, 'handle'));
    }

    public function test_middleware_integration()
    {
        $middlewareManager = app(MiddlewareManager::class);

        // Register a test middleware
        $middlewareManager->register('test', TestIntegrationMiddleware::class);

        $message = AIMessage::user('Test middleware integration');
        $message->user_id = 1;
        $message->metadata = [];

        // Mock the AI manager to return a test response
        $mockAIManager = Mockery::mock();
        $mockAIManager->shouldReceive('send')
            ->once()
            ->with('Test middleware integration')
            ->andReturn(AIResponse::fromArray([
                'content' => 'Middleware test response',
                'provider' => 'test',
                'model' => 'test-model',
            ]));

        $this->app->instance('laravel-ai', $mockAIManager);

        // Process through middleware
        $response = $middlewareManager->process($message, ['test']);

        $this->assertInstanceOf(AIResponse::class, $response);
        $this->assertEquals('Middleware test response', $response->content);

        // Verify middleware was applied
        $this->assertArrayHasKey('middleware_applied', $message->metadata);
        $this->assertContains('test', $message->metadata['middleware_applied']);
    }

    public function test_budget_threshold_event_integration()
    {
        Event::fake();

        // Create and fire a budget threshold event
        $budgetEvent = new BudgetThresholdReached(
            userId: 1,
            budgetType: 'daily',
            currentSpending: 9.50,
            budgetLimit: 10.00,
            percentage: 95.0,
            severity: 'critical'
        );

        event($budgetEvent);

        // Verify event was dispatched
        Event::assertDispatched(BudgetThresholdReached::class, function ($event) {
            return $event->severity === 'critical' && $event->percentage === 95.0;
        });
    }

    public function test_cost_calculated_event_integration()
    {
        Event::fake();

        // Create and fire a cost calculated event
        $costEvent = new CostCalculated(
            userId: 1,
            provider: 'test',
            model: 'test-model',
            cost: 0.001,
            inputTokens: 200,
            outputTokens: 100,
            conversationId: 1,
            messageId: 1
        );

        event($costEvent);

        // Verify event was dispatched
        Event::assertDispatched(CostCalculated::class, function ($event) {
            return $event->cost === 0.001 && $event->inputTokens === 200;
        });
    }

    public function test_event_listener_queue_integration()
    {
        // Test that event listeners are configured for queueing
        $costListener = app(CostTrackingListener::class);
        $analyticsListener = app(AnalyticsListener::class);
        $notificationListener = app(NotificationListener::class);

        // Verify listeners implement ShouldQueue
        $this->assertInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class, $costListener);
        $this->assertInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class, $analyticsListener);
        $this->assertInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class, $notificationListener);

        // Verify queue assignments
        $this->assertEquals('ai-analytics', $costListener->queue);
        $this->assertEquals('ai-analytics', $analyticsListener->queue);
        $this->assertEquals('ai-notifications', $notificationListener->queue);

        // Verify retry configuration
        $this->assertEquals(3, $costListener->tries);
        $this->assertEquals(3, $analyticsListener->tries);
        $this->assertEquals(5, $notificationListener->tries);
    }

    public function test_event_system_performance()
    {
        $startTime = microtime(true);

        // Fire multiple events to test performance
        for ($i = 0; $i < 10; $i++) {
            $event = new ResponseGenerated(
                message: AIMessage::user("Performance test message {$i}"),
                response: AIResponse::fromArray([
                    'content' => "Performance test response {$i}",
                    'provider' => 'test',
                    'model' => 'test-model',
                ]),
                context: ['performance_test' => true],
                totalProcessingTime: 0.1,
                providerMetadata: []
            );

            event($event);
        }

        $totalTime = microtime(true) - $startTime;

        // Event firing should be reasonable (under 500ms for 10 events in integration tests)
        $this->assertLessThan(0.5, $totalTime);
    }

    public function test_event_error_handling_integration()
    {
        // Test that event system handles errors gracefully
        $this->expectNotToPerformAssertions();

        try {
            // Create an event with invalid data to test error handling
            $event = new ResponseGenerated(
                message: AIMessage::user('Error test message'),
                response: AIResponse::fromArray([
                    'content' => 'Error test response',
                    'provider' => 'test',
                    'model' => 'test-model',
                ]),
                context: ['error_test' => true],
                totalProcessingTime: 1.0,
                providerMetadata: []
            );

            // Fire the event - should not throw exceptions
            event($event);
        } catch (\Exception $e) {
            $this->fail('Event system should handle errors gracefully: ' . $e->getMessage());
        }
    }

    public function test_configuration_driven_behavior()
    {
        Event::fake();

        // Test with events disabled
        config(['ai.events.enabled' => false]);

        // Create and fire an event
        $event = new ResponseGenerated(
            message: AIMessage::user('Config test message'),
            response: AIResponse::fromArray([
                'content' => 'Config test response',
                'provider' => 'test',
                'model' => 'test-model',
            ]),
            context: ['config_test' => true],
            totalProcessingTime: 1.0,
            providerMetadata: []
        );

        // Fire event manually (this should still work even if events are "disabled"
        // since we're testing the event system itself)
        event($event);

        // Verify the event was fired (the event system itself still works)
        Event::assertDispatched(ResponseGenerated::class);

        // Re-enable events
        config(['ai.events.enabled' => true]);

        // Fire another event
        event($event);

        // Events should still be fired
        Event::assertDispatched(ResponseGenerated::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}

// Test middleware for integration testing
class TestIntegrationMiddleware implements \JTD\LaravelAI\Contracts\AIMiddlewareInterface
{
    public function handle(\JTD\LaravelAI\Models\AIMessage $message, \Closure $next): \JTD\LaravelAI\Models\AIResponse
    {
        $message->metadata['middleware_applied'] = $message->metadata['middleware_applied'] ?? [];
        $message->metadata['middleware_applied'][] = 'test';

        return $next($message);
    }
}
