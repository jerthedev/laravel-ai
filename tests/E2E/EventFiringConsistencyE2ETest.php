<?php

namespace JTD\LaravelAI\Tests\E2E;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use JTD\LaravelAI\Events\CostCalculated;
use JTD\LaravelAI\Events\MessageSent;
use JTD\LaravelAI\Events\ResponseGenerated;
use JTD\LaravelAI\Facades\AI;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Providers\MockProvider;
use JTD\LaravelAI\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * E2E test for event firing consistency across all call paths.
 * Verifies that MessageSent, ResponseGenerated, and CostCalculated events
 * fire consistently regardless of how the AI provider is called.
 */
class EventFiringConsistencyE2ETest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        config([
            'ai.default' => 'mock',
            'ai.providers.mock.enabled' => true,
            'ai.events.enabled' => true,
            'ai.providers.mock.simulate_errors' => false,
            'ai.providers.mock.mock_responses.default' => [
                'content' => 'Event consistency test response',
                'model' => 'mock-gpt-4',
                'provider' => 'mock',
                'finish_reason' => 'stop',
                'input_tokens' => 8,
                'output_tokens' => 12,
                'cost' => 0.001,
            ],
        ]);
    }

    #[Test]
    public function it_fires_events_consistently_across_all_call_paths()
    {
        Event::fake();
        Queue::fake();
        
        $message = AIMessage::user('Test event consistency');
        $message->user_id = 123;
        $message->conversation_id = 456;
        
        // Test Pattern 1: AI::sendMessage() - Default provider
        $response1 = AI::sendMessage($message, ['model' => 'mock-gpt-4']);
        
        // Verify events were fired for default provider pattern
        Event::assertDispatched(MessageSent::class, function ($event) use ($message) {
            return $event->message->content === $message->content
                && $event->provider === 'mock'
                && $event->model === 'mock-gpt-4'
                && $event->userId === 123
                && $event->conversationId === 456;
        });
        
        Event::assertDispatched(ResponseGenerated::class, function ($event) use ($message) {
            return $event->message->content === $message->content
                && $event->response->content === 'Event consistency test response'
                && $event->context['provider_level_event'] === true
                && $event->providerMetadata['provider'] === 'mock'
                && $event->providerMetadata['tokens_used'] === 20; // 8 + 12
        });
        
        Event::assertDispatched(CostCalculated::class, function ($event) {
            return $event->userId === 123
                && $event->provider === 'mock'
                && $event->inputTokens === 8
                && $event->outputTokens === 12
                && $event->conversationId === 456
                && $event->cost > 0;
        });
        
        // Reset event fake for next test
        Event::fake();
        
        // Test Pattern 2: AI::provider('mock')->sendMessage() - Specific provider
        $response2 = AI::provider('mock')->sendMessage($message, ['model' => 'mock-gpt-4']);
        
        // Verify same events were fired for specific provider pattern
        Event::assertDispatched(MessageSent::class, function ($event) use ($message) {
            return $event->message->content === $message->content
                && $event->provider === 'mock'
                && $event->userId === 123
                && $event->conversationId === 456;
        });
        
        Event::assertDispatched(ResponseGenerated::class, function ($event) use ($message) {
            return $event->message->content === $message->content
                && $event->response->content === 'Event consistency test response'
                && $event->context['provider_level_event'] === true;
        });
        
        Event::assertDispatched(CostCalculated::class, function ($event) {
            return $event->userId === 123
                && $event->provider === 'mock'
                && $event->inputTokens === 8
                && $event->outputTokens === 12;
        });
        
        // Reset event fake for next test
        Event::fake();
        
        // Test Pattern 3: AI::conversation()->send() - Fluent interface
        $response3 = AI::conversation()
            ->message('Test event consistency')
            ->send();
        
        // Verify same events were fired for conversation pattern
        Event::assertDispatched(MessageSent::class, function ($event) {
            return $event->provider === 'mock';
        });
        
        Event::assertDispatched(ResponseGenerated::class, function ($event) {
            return $event->context['provider_level_event'] === true;
        });
        
        Event::assertDispatched(CostCalculated::class, function ($event) {
            return $event->provider === 'mock';
        });
        
        // Reset event fake for next test
        Event::fake();
        
        // Test Pattern 4: Direct driver instantiation
        $driver = new MockProvider([
            'mock_responses' => [
                'default' => [
                    'content' => 'Direct driver event test',
                    'model' => 'mock-direct',
                    'provider' => 'mock',
                    'finish_reason' => 'stop',
                    'input_tokens' => 6,
                    'output_tokens' => 10,
                    'cost' => 0.001,
                ],
            ],
        ]);
        
        $directMessage = AIMessage::user('Test direct driver events');
        $directMessage->user_id = 789;
        $response4 = $driver->sendMessage($directMessage, ['model' => 'mock-direct']);
        
        // Verify same events were fired for direct driver pattern
        Event::assertDispatched(MessageSent::class, function ($event) use ($directMessage) {
            return $event->message->content === $directMessage->content
                && $event->provider === 'mock'
                && $event->userId === 789;
        });
        
        Event::assertDispatched(ResponseGenerated::class, function ($event) use ($directMessage) {
            return $event->message->content === $directMessage->content
                && $event->response->content === 'Direct driver event test'
                && $event->context['provider_level_event'] === true;
        });
        
        Event::assertDispatched(CostCalculated::class, function ($event) {
            return $event->userId === 789
                && $event->provider === 'mock'
                && $event->inputTokens === 6
                && $event->outputTokens === 10;
        });
    }

    #[Test]
    public function it_fires_events_consistently_for_streaming()
    {
        Event::fake();
        Queue::fake();
        
        $message = AIMessage::user('Test streaming event consistency');
        $message->user_id = 555;
        $message->conversation_id = 777;
        
        // Test streaming with default provider
        $chunks = [];
        foreach (AI::sendStreamingMessage($message) as $chunk) {
            $chunks[] = $chunk;
        }
        
        // Verify streaming events were fired
        Event::assertDispatched(MessageSent::class, function ($event) use ($message) {
            return $event->message->content === $message->content
                && $event->provider === 'mock'
                && $event->userId === 555
                && $event->conversationId === 777;
        });
        
        Event::assertDispatched(ResponseGenerated::class, function ($event) {
            return $event->context['provider_level_event'] === true
                && $event->context['streaming_response'] === true
                && isset($event->context['total_chunks']);
        });
        
        Event::assertDispatched(CostCalculated::class, function ($event) {
            return $event->userId === 555
                && $event->provider === 'mock';
        });
        
        $this->assertNotEmpty($chunks);
    }

    #[Test]
    public function it_respects_event_configuration()
    {
        Event::fake();
        Queue::fake();
        
        // Disable events
        config(['ai.events.enabled' => false]);
        
        $message = AIMessage::user('Test with events disabled');
        $response = AI::sendMessage($message);
        
        // Verify no events were fired when disabled
        Event::assertNotDispatched(MessageSent::class);
        Event::assertNotDispatched(ResponseGenerated::class);
        Event::assertNotDispatched(CostCalculated::class);
        
        // But response should still work
        $this->assertNotNull($response);
        $this->assertNotEmpty($response->content);
        
        // Re-enable events for cleanup
        config(['ai.events.enabled' => true]);
    }

    #[Test]
    public function it_handles_missing_user_data_gracefully()
    {
        Event::fake();
        Queue::fake();
        
        // Create message without user_id or conversation_id
        $message = AIMessage::user('Test without user data');
        // Don't set user_id or conversation_id
        
        $response = AI::sendMessage($message);
        
        // Events should still fire with null values
        Event::assertDispatched(MessageSent::class, function ($event) {
            return $event->userId === null
                && $event->conversationId === null
                && $event->provider === 'mock';
        });
        
        Event::assertDispatched(ResponseGenerated::class);
        Event::assertDispatched(CostCalculated::class, function ($event) {
            return $event->userId === 0; // Default value for missing user_id
        });
        
        $this->assertNotNull($response);
    }

    #[Test]
    public function it_fires_events_with_correct_timing_data()
    {
        Event::fake();
        Queue::fake();
        
        $message = AIMessage::user('Test timing data');
        
        $startTime = microtime(true);
        $response = AI::sendMessage($message);
        $endTime = microtime(true);
        
        $actualDuration = $endTime - $startTime;
        
        Event::assertDispatched(ResponseGenerated::class, function ($event) use ($actualDuration) {
            // Processing time should be reasonable (within 10% margin)
            return $event->totalProcessingTime > 0
                && $event->totalProcessingTime <= ($actualDuration * 1.1)
                && isset($event->context['processing_start_time']);
        });
        
        $this->assertNotNull($response);
    }
}
