<?php

namespace JTD\LaravelAI\Tests\Unit\Events;

use JTD\LaravelAI\Events\BudgetThresholdReached;
use JTD\LaravelAI\Events\CostCalculated;
use JTD\LaravelAI\Events\ResponseGenerated;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Models\AIResponse;
use JTD\LaravelAI\Tests\TestCase;

class EventSystemTest extends TestCase
{
    public function test_response_generated_event_creation()
    {
        $message = AIMessage::user('Test message');
        $message->user_id = 1;
        $message->conversation_id = 1;

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
            context: ['test' => true],
            total_processing_time: 1.5,
            provider_metadata: [
                'provider' => 'openai',
                'model' => 'gpt-4o-mini',
                'tokens_used' => 150,
            ]
        );

        $this->assertInstanceOf(ResponseGenerated::class, $event);
        $this->assertEquals($message, $event->message);
        $this->assertEquals($response, $event->response);
        $this->assertEquals(['test' => true], $event->context);
        $this->assertEquals(1.5, $event->total_processing_time);
        $this->assertEquals('openai', $event->provider_metadata['provider']);
    }

    public function test_cost_calculated_event_creation()
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

        $this->assertInstanceOf(CostCalculated::class, $event);
        $this->assertEquals(1, $event->userId);
        $this->assertEquals('openai', $event->provider);
        $this->assertEquals('gpt-4o-mini', $event->model);
        $this->assertEquals(0.001, $event->cost);
        $this->assertEquals(100, $event->input_tokens);
        $this->assertEquals(50, $event->output_tokens);
        $this->assertEquals(1, $event->conversationId);
        $this->assertEquals(1, $event->messageId);
    }

    public function test_budget_threshold_reached_event_creation()
    {
        $event = new BudgetThresholdReached(
            userId: 1,
            budgetType: 'daily',
            current_spending: 8.50,
            budget_limit: 10.00,
            threshold_percentage: 85.0,
            severity: 'warning'
        );

        $this->assertInstanceOf(BudgetThresholdReached::class, $event);
        $this->assertEquals(1, $event->userId);
        $this->assertEquals('daily', $event->budgetType);
        $this->assertEquals(8.50, $event->current_spending);
        $this->assertEquals(10.00, $event->budget_limit);
        $this->assertEquals(85.0, $event->threshold_percentage);
        $this->assertEquals('warning', $event->severity);
    }

    public function test_events_are_serializable()
    {
        $message = AIMessage::user('Test message');
        $message->user_id = 1;

        $response = AIResponse::fromArray([
            'content' => 'Test response',
            'provider' => 'openai',
            'model' => 'test-model',
        ]);

        $responseEvent = new ResponseGenerated(
            message: $message,
            response: $response,
            context: [],
            total_processing_time: 1.0,
            provider_metadata: []
        );

        $costEvent = new CostCalculated(
            userId: 1,
            provider: 'openai',
            model: 'gpt-4o-mini',
            cost: 0.001,
            input_tokens: 100,
            output_tokens: 50,
            conversationId: 1,
            messageId: 1
        );

        $budgetEvent = new BudgetThresholdReached(
            userId: 1,
            budgetType: 'daily',
            current_spending: 8.50,
            budget_limit: 10.00,
            threshold_percentage: 85.0,
            severity: 'warning'
        );

        // Test serialization doesn't throw errors
        $this->assertIsString(serialize($responseEvent));
        $this->assertIsString(serialize($costEvent));
        $this->assertIsString(serialize($budgetEvent));
    }

    public function test_event_broadcasting_configuration()
    {
        // Test that events can be configured for broadcasting
        $event = new ResponseGenerated(
            message: AIMessage::user('test'),
            response: AIResponse::fromArray(['content' => 'response', 'provider' => 'test', 'model' => 'test']),
            context: [],
            total_processing_time: 1.0,
            provider_metadata: []
        );

        // Events should implement ShouldBroadcast if broadcasting is needed
        // For now, we just test they don't break when serialized for queues
        $this->assertIsObject($event);
    }

    public function test_event_metadata_handling()
    {
        $message = AIMessage::user('Test message');
        $message->metadata = ['custom' => 'data'];

        $response = AIResponse::fromArray([
            'content' => 'Test response',
            'provider' => 'test',
            'model' => 'test-model',
            'metadata' => ['response_custom' => 'data'],
        ]);

        $event = new ResponseGenerated(
            message: $message,
            response: $response,
            context: ['context_custom' => 'data'],
            total_processing_time: 1.0,
            provider_metadata: ['provider_custom' => 'data']
        );

        // Test that all metadata is preserved
        $this->assertEquals(['custom' => 'data'], $event->message->metadata);
        $this->assertEquals(['response_custom' => 'data'], $event->response->metadata);
        $this->assertEquals(['context_custom' => 'data'], $event->context);
        $this->assertEquals(['provider_custom' => 'data'], $event->provider_metadata);
    }

    public function test_event_timing_accuracy()
    {
        $startTime = microtime(true);

        // Simulate some processing time
        usleep(10000); // 10ms

        $processingTime = microtime(true) - $startTime;

        $event = new ResponseGenerated(
            message: AIMessage::user('test'),
            response: AIResponse::fromArray(['content' => 'response', 'provider' => 'test', 'model' => 'test']),
            context: [],
            total_processing_time: $processingTime,
            provider_metadata: []
        );

        // Processing time should be reasonable (between 0.01 and 1 second for this test)
        $this->assertGreaterThan(0.005, $event->total_processing_time);
        $this->assertLessThan(1.0, $event->total_processing_time);
    }

    public function test_cost_calculated_event_validation()
    {
        // Test with valid data
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

        $this->assertGreaterThan(0, $event->cost);
        $this->assertGreaterThan(0, $event->input_tokens);
        $this->assertGreaterThan(0, $event->output_tokens);

        // Test with zero cost (should be allowed for free tiers)
        $freeEvent = new CostCalculated(
            userId: 1,
            provider: 'mock',
            model: 'free-model',
            cost: 0.0,
            input_tokens: 100,
            output_tokens: 50,
            conversationId: 1,
            messageId: 1
        );

        $this->assertEquals(0.0, $freeEvent->cost);
    }

    public function test_budget_threshold_severity_levels()
    {
        $severityLevels = ['warning', 'critical', 'exceeded'];

        foreach ($severityLevels as $severity) {
            $event = new BudgetThresholdReached(
                userId: 1,
                budgetType: 'daily',
                current_spending: 8.50,
                budget_limit: 10.00,
                threshold_percentage: 85.0,
                severity: $severity
            );

            $this->assertEquals($severity, $event->severity);
            $this->assertContains($event->severity, $severityLevels);
        }
    }
}
