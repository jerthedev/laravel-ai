<?php

namespace JTD\LaravelAI\Tests\Integration;

use Illuminate\Support\Facades\Event;
use JTD\LaravelAI\Events\CostCalculated;
use JTD\LaravelAI\Events\MessageSent;
use JTD\LaravelAI\Events\ResponseGenerated;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Integration test for event firing consistency across the unified architecture.
 *
 * Tests that events fire properly when using the actual mock provider
 * through different call paths.
 */
class EventFiringIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ai.default_provider' => 'mock',
            'ai.providers.mock.enabled' => true,
            'ai.events.enabled' => true,
            'ai.providers.mock.mock_responses.default' => [
                'content' => 'This is a mock response for testing events.',
                'model' => 'mock-gpt-4',
                'provider' => 'mock',
                'finish_reason' => 'stop',
                'input_tokens' => 10,
                'output_tokens' => 15,
                'cost' => 0.001,
            ],
        ]);
    }

    #[Test]
    public function it_fires_all_events_from_direct_provider_sendmessage()
    {
        Event::fake();

        // Get mock provider directly
        $provider = app('laravel-ai')->driver('mock');

        // Create test message
        $message = AIMessage::user('Test direct provider call');
        $message->user_id = 123;
        $message->conversation_id = 456;

        // Send message directly to provider
        $response = $provider->sendMessage($message, ['model' => 'mock-gpt-4']);

        // Verify MessageSent event was fired
        Event::assertDispatched(MessageSent::class, function ($event) use ($message) {
            return $event->message->content === $message->content
                && $event->provider === 'mock'
                && $event->model === 'mock-gpt-4'
                && $event->userId === 123
                && $event->conversationId === 456;
        });

        // Verify ResponseGenerated event was fired
        Event::assertDispatched(ResponseGenerated::class, function ($event) use ($message) {
            return $event->message->content === $message->content
                && $event->context['provider_level_event'] === true
                && isset($event->total_processing_time)
                && $event->total_processing_time >= 0;
        });

        // Verify response is valid
        $this->assertNotNull($response);
        $this->assertNotEmpty($response->content);
        $this->assertEquals('mock', $response->provider);
    }

    #[Test]
    public function it_fires_events_when_token_usage_is_available()
    {
        Event::fake();

        // Configure mock to return token usage
        config([
            'ai.providers.mock.include_token_usage' => true,
            'ai.providers.mock.mock_token_usage' => [
                'input_tokens' => 10,
                'output_tokens' => 15,
                'total_tokens' => 25,
            ],
        ]);

        $provider = app('laravel-ai')->driver('mock');
        $message = AIMessage::user('Test with token usage');
        $message->user_id = 789;

        $response = $provider->sendMessage($message, ['model' => 'mock-gpt-4']);

        // All three events should fire
        Event::assertDispatched(MessageSent::class);
        Event::assertDispatched(ResponseGenerated::class);

        // CostCalculated should fire if token usage is present
        if ($response->tokenUsage) {
            Event::assertDispatched(CostCalculated::class, function ($event) {
                return $event->userId === 789
                    && $event->provider === 'mock'
                    && $event->model === 'mock-gpt-4'
                    && $event->input_tokens >= 0
                    && $event->output_tokens >= 0
                    && $event->cost >= 0;
            });
        }
    }

    #[Test]
    public function it_respects_event_configuration()
    {
        Event::fake();

        // Disable events
        config(['ai.events.enabled' => false]);

        $provider = app('laravel-ai')->driver('mock');
        $message = AIMessage::user('Test with events disabled');

        $response = $provider->sendMessage($message, ['model' => 'mock-gpt-4']);

        // No events should be fired
        Event::assertNotDispatched(MessageSent::class);
        Event::assertNotDispatched(ResponseGenerated::class);
        Event::assertNotDispatched(CostCalculated::class);

        // But response should still work
        $this->assertNotNull($response);
        $this->assertNotEmpty($response->content);
    }

    #[Test]
    public function it_handles_missing_user_data_gracefully()
    {
        Event::fake();

        $provider = app('laravel-ai')->driver('mock');

        // Create message without user_id or conversation_id
        $message = AIMessage::user('Test without user data');
        // Don't set user_id or conversation_id

        $response = $provider->sendMessage($message, ['model' => 'mock-gpt-4']);

        // Events should still fire with default values
        Event::assertDispatched(MessageSent::class, function ($event) {
            return $event->userId === null
                && $event->conversationId === null
                && $event->provider === 'mock';
        });

        Event::assertDispatched(ResponseGenerated::class);

        // Response should still work
        $this->assertNotNull($response);
    }

    #[Test]
    public function it_includes_correct_metadata_in_events()
    {
        Event::fake();

        $provider = app('laravel-ai')->driver('mock');
        $message = AIMessage::user('Test metadata');
        $message->user_id = 555;

        $response = $provider->sendMessage($message, ['model' => 'mock-gpt-4', 'temperature' => 0.7]);

        // Check MessageSent metadata
        Event::assertDispatched(MessageSent::class, function ($event) {
            return $event->provider === 'mock'
                && $event->model === 'mock-gpt-4'
                && isset($event->options['temperature'])
                && $event->options['temperature'] === 0.7;
        });

        // Check ResponseGenerated metadata
        Event::assertDispatched(ResponseGenerated::class, function ($event) {
            return $event->context['provider_level_event'] === true
                && isset($event->provider_metadata['provider'])
                && $event->provider_metadata['provider'] === 'mock'
                && isset($event->provider_metadata['model'])
                && $event->provider_metadata['model'] === 'mock-gpt-4';
        });
    }

    #[Test]
    public function it_measures_processing_time_accurately()
    {
        Event::fake();

        $provider = app('laravel-ai')->driver('mock');
        $message = AIMessage::user('Test processing time');

        $startTime = microtime(true);
        $response = $provider->sendMessage($message, ['model' => 'mock-gpt-4']);
        $endTime = microtime(true);

        $actualDuration = $endTime - $startTime;

        Event::assertDispatched(ResponseGenerated::class, function ($event) use ($actualDuration) {
            // Processing time should be reasonable (within 10% margin)
            return $event->total_processing_time > 0
                && $event->total_processing_time <= ($actualDuration * 1.1)
                && isset($event->context['processing_start_time']);
        });
    }
}
