<?php

namespace JTD\LaravelAI\Tests\Unit\Events;

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
use JTD\LaravelAI\Services\PricingService;
use JTD\LaravelAI\Tests\TestCase;
use Mockery;

class EventListenersTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Enable events for testing
        config(['ai.events.enabled' => true]);
        config(['ai.events.listeners.cost_tracking.enabled' => true]);
        config(['ai.events.listeners.analytics.enabled' => true]);
        config(['ai.events.listeners.notifications.enabled' => true]);
    }

    public function test_cost_tracking_listener_handles_response_generated_event()
    {
        Queue::fake();

        $message = AIMessage::user('Test message');
        $message->user_id = 1;
        $message->conversation_id = 1;
        $message->provider = 'openai';
        $message->model = 'gpt-4o-mini';
        $message->id = 1; // Add message ID

        $response = AIResponse::fromArray([
            'content' => 'Test response',
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'token_usage' => [
                'input_tokens' => 100,
                'output_tokens' => 50,
                'total_tokens' => 150,
            ],
        ]);

        $event = new ResponseGenerated(
            message: $message,
            response: $response,
            context: [],
            total_processing_time: 1.0,
            provider_metadata: [
                'provider' => 'openai',
                'model' => 'gpt-4o-mini',
                'tokens_used' => 150,
            ]
        );

        // Mock the PricingService
        $pricingMock = Mockery::mock(PricingService::class);
        $pricingMock->shouldReceive('calculateCost')
            ->with('openai', 'gpt-4o-mini', 100, 50)
            ->andReturn([
                'total_cost' => 0.001,
                'input_cost' => 0.0006,
                'output_cost' => 0.0004,
            ]);
        $this->app->instance(PricingService::class, $pricingMock);

        // Fake events before calling the listener
        Event::fake();

        $listener = new CostTrackingListener($pricingMock);
        $listener->handle($event);

        // Verify that a CostCalculated event was fired
        Event::assertDispatched(CostCalculated::class);
    }

    public function test_analytics_listener_handles_response_generated_event()
    {
        Queue::fake();

        $message = AIMessage::user('Test message');
        $message->user_id = 1;

        $response = AIResponse::fromArray([
            'content' => 'Test response',
            'provider' => 'test',
            'model' => 'test-model',
            'token_usage' => [
                'input_tokens' => 100,
                'output_tokens' => 50,
                'total_tokens' => 150,
            ],
        ]);

        $event = new ResponseGenerated(
            message: $message,
            response: $response,
            context: [],
            total_processing_time: 1.0,
            provider_metadata: [
                'provider' => 'openai',
                'model' => 'gpt-4o-mini',
                'tokens_used' => 150,
            ]
        );

        $listener = new AnalyticsListener;

        // Should not throw any exceptions
        $this->assertNull($listener->handle($event));
    }

    public function test_notification_listener_handles_budget_threshold_event()
    {
        Queue::fake();

        $event = new BudgetThresholdReached(
            userId: 1,
            budgetType: 'daily',
            current_spending: 8.50,
            budget_limit: 10.00,
            threshold_percentage: 85.0,
            severity: 'warning'
        );

        $listener = new NotificationListener;

        // Should not throw any exceptions
        $this->assertNull($listener->handle($event));
    }

    public function test_listeners_are_queued()
    {
        // Test that listeners implement ShouldQueue
        $costListener = new CostTrackingListener($this->app->make(PricingService::class));
        $analyticsListener = new AnalyticsListener;
        $notificationListener = new NotificationListener;

        $this->assertInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class, $costListener);
        $this->assertInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class, $analyticsListener);
        $this->assertInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class, $notificationListener);
    }

    public function test_listeners_have_correct_queue_assignments()
    {
        $costListener = new CostTrackingListener($this->app->make(PricingService::class));
        $analyticsListener = new AnalyticsListener;
        $notificationListener = new NotificationListener;

        $this->assertEquals('ai-analytics', $costListener->queue);
        $this->assertEquals('ai-analytics', $analyticsListener->queue);
        $this->assertEquals('ai-notifications', $notificationListener->queue);
    }

    public function test_listeners_have_retry_configuration()
    {
        $costListener = new CostTrackingListener($this->app->make(PricingService::class));
        $analyticsListener = new AnalyticsListener;
        $notificationListener = new NotificationListener;

        $this->assertEquals(3, $costListener->tries);
        $this->assertEquals(3, $analyticsListener->tries);
        $this->assertEquals(5, $notificationListener->tries); // More retries for notifications

        $this->assertEquals(60, $costListener->backoff);
        $this->assertEquals(60, $analyticsListener->backoff);
        $this->assertEquals(30, $notificationListener->backoff);
    }

    public function test_cost_tracking_listener_error_handling()
    {
        // Mock PricingService to throw an exception
        $this->app->bind(PricingService::class, function () {
            $mock = Mockery::mock(PricingService::class);
            $mock->shouldReceive('calculateCost')
                ->andThrow(new \Exception('Pricing service error'));

            return $mock;
        });

        $message = AIMessage::user('Test message');
        $message->user_id = 1;

        $response = AIResponse::fromArray([
            'content' => 'Test response',
            'provider' => 'test',
            'model' => 'test-model',
            'token_usage' => [
                'input_tokens' => 100,
                'output_tokens' => 50,
                'total_tokens' => 150,
            ],
        ]);

        $event = new ResponseGenerated(
            message: $message,
            response: $response,
            context: [],
            total_processing_time: 1.0,
            provider_metadata: []
        );

        $listener = new CostTrackingListener($this->app->make(PricingService::class));

        // Should not throw exception - should handle gracefully
        $this->assertNull($listener->handle($event));
    }

    public function test_listeners_performance_tracking()
    {
        $message = AIMessage::user('Test message');
        $message->user_id = 1;

        $response = AIResponse::fromArray([
            'content' => 'Test response',
            'provider' => 'test',
            'model' => 'test-model',
            'token_usage' => [
                'input_tokens' => 100,
                'output_tokens' => 50,
                'total_tokens' => 150,
            ],
        ]);

        $event = new ResponseGenerated(
            message: $message,
            response: $response,
            context: [],
            total_processing_time: 1.0,
            provider_metadata: []
        );

        // Test that listeners complete within reasonable time
        $startTime = microtime(true);

        $analyticsListener = new AnalyticsListener;
        $analyticsListener->handle($event);

        $executionTime = microtime(true) - $startTime;

        // Should complete quickly (under 1 second for unit test)
        $this->assertLessThan(1.0, $executionTime);
    }

    public function test_notification_listener_severity_handling()
    {
        $severities = ['warning', 'critical', 'exceeded'];

        foreach ($severities as $severity) {
            $event = new BudgetThresholdReached(
                userId: 1,
                budgetType: 'daily',
                current_spending: 8.50,
                budget_limit: 10.00,
                threshold_percentage: 85.0,
                severity: $severity
            );

            $listener = new NotificationListener;

            // Should handle all severity levels without error
            $this->assertNull($listener->handle($event));
        }
    }

    public function test_cost_calculated_event_listener_integration()
    {
        $event = new CostCalculated(
            userId: 1,
            provider: 'openai',
            model: 'gpt-4o-mini',
            cost: 0.001,
            input_tokens: 100,
            output_tokens: 50,
            conversationId: 1,
            messageId: 1
        );

        $analyticsListener = new AnalyticsListener;

        // Should handle CostCalculated events
        $this->assertNull($analyticsListener->handleCostCalculated($event));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
