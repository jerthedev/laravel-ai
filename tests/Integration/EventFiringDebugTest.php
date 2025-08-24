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
 * Debug test to understand why events are not firing.
 */
class EventFiringDebugTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        config([
            'ai.default_provider' => 'mock',
            'ai.providers.mock.enabled' => true,
            'ai.events.enabled' => true,
            'ai.providers.mock.mock_responses.default' => [
                'content' => 'Debug response',
                'model' => 'mock-gpt-4',
                'provider' => 'mock',
                'finish_reason' => 'stop',
                'input_tokens' => 5,
                'output_tokens' => 10,
                'cost' => 0.001,
            ],
        ]);
    }

    #[Test]
    public function it_can_call_provider_directly()
    {
        $provider = app('laravel-ai')->driver('mock');
        $message = AIMessage::user('Debug test');
        
        $response = $provider->sendMessage($message, ['model' => 'mock-gpt-4']);
        
        $this->assertNotNull($response);
        $this->assertEquals('Debug response', $response->content);
        $this->assertEquals('mock', $response->provider);
    }

    #[Test]
    public function it_shows_what_events_are_fired()
    {
        // Don't fake events, let them fire naturally and capture them
        $firedEvents = [];
        
        Event::listen('*', function ($eventName, $data) use (&$firedEvents) {
            $firedEvents[] = $eventName;
        });
        
        $provider = app('laravel-ai')->driver('mock');
        $message = AIMessage::user('Debug test');
        $message->user_id = 123;
        
        $response = $provider->sendMessage($message, ['model' => 'mock-gpt-4']);
        
        // Debug output
        dump('Events fired:', $firedEvents);
        dump('Config ai.events.enabled:', config('ai.events.enabled'));
        dump('Response has token usage:', $response->tokenUsage !== null);
        
        $this->assertNotNull($response);
    }

    #[Test]
    public function it_checks_event_configuration()
    {
        $this->assertTrue(config('ai.events.enabled'));
        $this->assertEquals('mock', config('ai.default_provider'));
        $this->assertTrue(config('ai.providers.mock.enabled'));
    }
}
