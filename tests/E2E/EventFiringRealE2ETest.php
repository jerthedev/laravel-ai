<?php

namespace JTD\LaravelAI\Tests\E2E;

use Illuminate\Support\Facades\Event;
use JTD\LaravelAI\Events\CostCalculated;
use JTD\LaravelAI\Events\MessageSent;
use JTD\LaravelAI\Events\ResponseGenerated;
use JTD\LaravelAI\Facades\AI;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Providers\MockProvider;
use JTD\LaravelAI\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * E2E test for real event firing consistency.
 * Tests that events actually fire (without Event::fake()) across all call paths.
 */
class EventFiringRealE2ETest extends TestCase
{
    protected array $firedEvents = [];

    protected function setUp(): void
    {
        parent::setUp();
        
        config([
            'ai.default' => 'mock',
            'ai.providers.mock.enabled' => true,
            'ai.events.enabled' => true,
            'ai.providers.mock.simulate_errors' => false,
            'ai.providers.mock.mock_responses.default' => [
                'content' => 'Real event test response',
                'model' => 'mock-gpt-4',
                'provider' => 'mock',
                'finish_reason' => 'stop',
                'input_tokens' => 8,
                'output_tokens' => 12,
                'cost' => 0.001,
            ],
        ]);
        
        // Set up real event listeners to capture fired events
        $this->firedEvents = [];
        
        Event::listen(MessageSent::class, function ($event) {
            $this->firedEvents['MessageSent'][] = $event;
        });
        
        Event::listen(ResponseGenerated::class, function ($event) {
            $this->firedEvents['ResponseGenerated'][] = $event;
        });
        
        Event::listen(CostCalculated::class, function ($event) {
            $this->firedEvents['CostCalculated'][] = $event;
        });
    }

    #[Test]
    public function it_fires_events_for_default_provider_pattern()
    {
        $message = AIMessage::user('Test default provider events');
        $message->user_id = 123;
        $message->conversation_id = 456;
        
        $response = AI::sendMessage($message, ['model' => 'mock-gpt-4']);
        
        // Verify response works
        $this->assertNotNull($response);
        $this->assertEquals('Real event test response', $response->content);
        $this->assertEquals('mock', $response->provider);
        
        // Verify events were fired
        $this->assertArrayHasKey('MessageSent', $this->firedEvents);
        $this->assertArrayHasKey('ResponseGenerated', $this->firedEvents);
        $this->assertArrayHasKey('CostCalculated', $this->firedEvents);
        
        // Verify MessageSent event data
        $messageSentEvent = $this->firedEvents['MessageSent'][0];
        $this->assertEquals('Test default provider events', $messageSentEvent->message->content);
        $this->assertEquals('mock', $messageSentEvent->provider);
        $this->assertEquals(123, $messageSentEvent->userId);
        $this->assertEquals(456, $messageSentEvent->conversationId);
        
        // Verify ResponseGenerated event data
        $responseEvent = $this->firedEvents['ResponseGenerated'][0];
        $this->assertEquals('Test default provider events', $responseEvent->message->content);
        $this->assertEquals('Real event test response', $responseEvent->response->content);
        $this->assertTrue($responseEvent->context['provider_level_event']);
        
        // Verify CostCalculated event data
        $costEvent = $this->firedEvents['CostCalculated'][0];
        $this->assertEquals(123, $costEvent->userId);
        $this->assertEquals('mock', $costEvent->provider);
        $this->assertEquals(8, $costEvent->inputTokens);
        $this->assertEquals(12, $costEvent->outputTokens);
        $this->assertEquals(456, $costEvent->conversationId);
    }

    #[Test]
    public function it_fires_events_for_specific_provider_pattern()
    {
        $this->firedEvents = []; // Reset
        
        $message = AIMessage::user('Test specific provider events');
        $message->user_id = 789;
        
        $response = AI::provider('mock')->sendMessage($message, ['model' => 'mock-gpt-4']);
        
        // Verify response works
        $this->assertNotNull($response);
        $this->assertEquals('mock', $response->provider);
        
        // Verify events were fired
        $this->assertArrayHasKey('MessageSent', $this->firedEvents);
        $this->assertArrayHasKey('ResponseGenerated', $this->firedEvents);
        $this->assertArrayHasKey('CostCalculated', $this->firedEvents);
        
        // Verify event data
        $messageSentEvent = $this->firedEvents['MessageSent'][0];
        $this->assertEquals('Test specific provider events', $messageSentEvent->message->content);
        $this->assertEquals('mock', $messageSentEvent->provider);
        $this->assertEquals(789, $messageSentEvent->userId);
    }

    #[Test]
    public function it_fires_events_for_conversation_pattern()
    {
        $this->firedEvents = []; // Reset
        
        $response = AI::conversation()
            ->message('Test conversation events')
            ->send();
        
        // Verify response works
        $this->assertNotNull($response);
        $this->assertEquals('mock', $response->provider);
        
        // Verify events were fired
        $this->assertArrayHasKey('MessageSent', $this->firedEvents);
        $this->assertArrayHasKey('ResponseGenerated', $this->firedEvents);
        $this->assertArrayHasKey('CostCalculated', $this->firedEvents);
        
        // Verify event data
        $messageSentEvent = $this->firedEvents['MessageSent'][0];
        $this->assertEquals('mock', $messageSentEvent->provider);
        
        $responseEvent = $this->firedEvents['ResponseGenerated'][0];
        $this->assertTrue($responseEvent->context['provider_level_event']);
    }

    #[Test]
    public function it_fires_events_for_direct_driver_pattern()
    {
        $this->firedEvents = []; // Reset
        
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
        
        $message = AIMessage::user('Test direct driver events');
        $message->user_id = 999;
        
        $response = $driver->sendMessage($message, ['model' => 'mock-direct']);
        
        // Verify response works
        $this->assertNotNull($response);
        $this->assertEquals('Direct driver event test', $response->content);
        $this->assertEquals('mock', $response->provider);
        
        // Verify events were fired
        $this->assertArrayHasKey('MessageSent', $this->firedEvents);
        $this->assertArrayHasKey('ResponseGenerated', $this->firedEvents);
        $this->assertArrayHasKey('CostCalculated', $this->firedEvents);
        
        // Verify event data
        $messageSentEvent = $this->firedEvents['MessageSent'][0];
        $this->assertEquals('Test direct driver events', $messageSentEvent->message->content);
        $this->assertEquals('mock', $messageSentEvent->provider);
        $this->assertEquals(999, $messageSentEvent->userId);
        
        $costEvent = $this->firedEvents['CostCalculated'][0];
        $this->assertEquals(999, $costEvent->userId);
        $this->assertEquals(6, $costEvent->inputTokens);
        $this->assertEquals(10, $costEvent->outputTokens);
    }

    #[Test]
    public function it_fires_events_for_streaming()
    {
        $this->firedEvents = []; // Reset
        
        $message = AIMessage::user('Test streaming events');
        $message->user_id = 555;
        
        $chunks = [];
        foreach (AI::sendStreamingMessage($message) as $chunk) {
            $chunks[] = $chunk;
        }
        
        // Verify streaming worked
        $this->assertNotEmpty($chunks);
        
        // Verify events were fired
        $this->assertArrayHasKey('MessageSent', $this->firedEvents);
        $this->assertArrayHasKey('ResponseGenerated', $this->firedEvents);
        $this->assertArrayHasKey('CostCalculated', $this->firedEvents);
        
        // Verify streaming-specific event data
        $responseEvent = $this->firedEvents['ResponseGenerated'][0];
        $this->assertTrue($responseEvent->context['provider_level_event']);
        $this->assertTrue($responseEvent->context['streaming_response']);
        $this->assertArrayHasKey('total_chunks', $responseEvent->context);
    }

    #[Test]
    public function it_respects_event_configuration()
    {
        $this->firedEvents = []; // Reset
        
        // Disable events
        config(['ai.events.enabled' => false]);
        
        $message = AIMessage::user('Test with events disabled');
        $response = AI::sendMessage($message);
        
        // Verify response still works
        $this->assertNotNull($response);
        $this->assertNotEmpty($response->content);
        
        // Verify no events were fired
        $this->assertEmpty($this->firedEvents);
        
        // Re-enable events for cleanup
        config(['ai.events.enabled' => true]);
    }
}
