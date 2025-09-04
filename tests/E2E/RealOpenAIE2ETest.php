<?php

namespace JTD\LaravelAI\Tests\E2E;

use Illuminate\Support\Facades\Event;
use JTD\LaravelAI\Events\CostCalculated;
use JTD\LaravelAI\Events\MessageSent;
use JTD\LaravelAI\Events\ResponseGenerated;
use JTD\LaravelAI\Facades\AI;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * E2E test with real OpenAI API calls.
 * Tests the unified architecture with actual OpenAI responses.
 */
#[Group('e2e')]
class RealOpenAIE2ETest extends TestCase
{
    protected array $firedEvents = [];

    protected bool $hasCredentials = false;

    protected function setUp(): void
    {
        parent::setUp();

        // Check if E2E credentials are available
        $credentialsPath = __DIR__ . '/../credentials/e2e-credentials.json';

        if (! file_exists($credentialsPath)) {
            $this->markTestSkipped('E2E credentials file not found. Run: php artisan ai:setup-e2e');
        }

        $credentials = json_decode(file_get_contents($credentialsPath), true);

        if (empty($credentials['openai']['api_key'])) {
            $this->markTestSkipped('OpenAI API key not configured in E2E credentials');
        }

        $this->hasCredentials = true;

        // Configure for real OpenAI testing
        config([
            'ai.default' => 'openai',
            'ai.providers.openai.enabled' => true,
            'ai.providers.openai.api_key' => $credentials['openai']['api_key'],
            'ai.providers.openai.organization' => $credentials['openai']['organization'] ?? null,
            'ai.providers.openai.project' => $credentials['openai']['project'] ?? null,
            'ai.events.enabled' => true,
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
    public function it_works_with_real_openai_default_provider()
    {
        if (! $this->hasCredentials) {
            $this->markTestSkipped('No OpenAI credentials available');
        }

        $message = AIMessage::user('Say "Hello from unified architecture" in exactly those words.');
        $message->user_id = 123;
        $message->conversation_id = 456;

        $response = AI::sendMessage($message, [
            'model' => 'gpt-3.5-turbo',
            'max_tokens' => 50,
            'temperature' => 0.1,
        ]);

        // Verify response from real OpenAI
        $this->assertNotNull($response);
        $this->assertNotEmpty($response->content);
        $this->assertEquals('openai', $response->provider);
        $this->assertStringStartsWith('gpt-3.5-turbo', $response->model); // OpenAI may return specific version like gpt-3.5-turbo-0125
        $this->assertNotNull($response->tokenUsage);
        $this->assertGreaterThan(0, $response->tokenUsage->totalTokens);
        $this->assertIsFloat($response->getTotalCost());
        $this->assertGreaterThan(0, $response->getTotalCost());

        // Verify events were fired with real data
        $this->assertArrayHasKey('MessageSent', $this->firedEvents);
        $this->assertArrayHasKey('ResponseGenerated', $this->firedEvents);
        $this->assertArrayHasKey('CostCalculated', $this->firedEvents);

        // Verify MessageSent event
        $messageSentEvent = $this->firedEvents['MessageSent'][0];
        $this->assertEquals('openai', $messageSentEvent->provider);
        $this->assertStringStartsWith('gpt-3.5-turbo', $messageSentEvent->model);
        $this->assertEquals(123, $messageSentEvent->userId);
        $this->assertEquals(456, $messageSentEvent->conversationId);

        // Verify ResponseGenerated event
        $responseEvent = $this->firedEvents['ResponseGenerated'][0];
        $this->assertNotEmpty($responseEvent->response->content);
        $this->assertTrue($responseEvent->context['provider_level_event']);
        $this->assertGreaterThan(0, $responseEvent->total_processing_time);

        // Verify CostCalculated event with real cost data
        $costEvent = $this->firedEvents['CostCalculated'][0];
        $this->assertEquals(123, $costEvent->userId);
        $this->assertEquals('openai', $costEvent->provider);
        $this->assertStringStartsWith('gpt-3.5-turbo', $costEvent->model);
        $this->assertGreaterThan(0, $costEvent->input_tokens);
        $this->assertGreaterThan(0, $costEvent->output_tokens);
        $this->assertGreaterThan(0, $costEvent->cost);
        $this->assertEquals(456, $costEvent->conversationId);

        echo "\nReal OpenAI Response: " . substr($response->content, 0, 100) . '...';
        echo "\nTokens Used: " . $response->tokenUsage->totalTokens;
        echo "\nCost: $" . number_format($response->getTotalCost(), 6);
    }

    #[Test]
    public function it_works_with_real_openai_specific_provider()
    {
        if (! $this->hasCredentials) {
            $this->markTestSkipped('No OpenAI credentials available');
        }

        $this->firedEvents = []; // Reset

        $message = AIMessage::user('Respond with exactly "Specific provider test successful"');
        $message->user_id = 789;

        $response = AI::provider('openai')->sendMessage($message, [
            'model' => 'gpt-3.5-turbo',
            'max_tokens' => 20,
            'temperature' => 0.0,
        ]);

        // Verify response
        $this->assertNotNull($response);
        $this->assertEquals('openai', $response->provider);
        $this->assertEquals('gpt-3.5-turbo', $response->model);
        $this->assertGreaterThan(0, $response->tokenUsage->totalTokens);
        $this->assertGreaterThan(0, $response->getTotalCost());

        // Verify events were fired
        $this->assertArrayHasKey('MessageSent', $this->firedEvents);
        $this->assertArrayHasKey('ResponseGenerated', $this->firedEvents);
        $this->assertArrayHasKey('CostCalculated', $this->firedEvents);

        // Verify event data
        $costEvent = $this->firedEvents['CostCalculated'][0];
        $this->assertEquals(789, $costEvent->userId);
        $this->assertEquals('openai', $costEvent->provider);
        $this->assertGreaterThan(0, $costEvent->cost);
    }

    #[Test]
    public function it_works_with_real_openai_conversation_builder()
    {
        if (! $this->hasCredentials) {
            $this->markTestSkipped('No OpenAI credentials available');
        }

        $this->firedEvents = []; // Reset

        $response = AI::conversation()
            ->provider('openai')
            ->model('gpt-3.5-turbo')
            ->temperature(0.1)
            ->maxTokens(30)
            ->message('You are a helpful assistant.')
            ->message('Say "Conversation test passed" exactly.')
            ->send();

        // Verify response
        $this->assertNotNull($response);
        $this->assertEquals('openai', $response->provider);
        $this->assertGreaterThan(0, $response->tokenUsage->totalTokens);
        $this->assertGreaterThan(0, $response->getTotalCost());

        // Verify events were fired
        $this->assertArrayHasKey('MessageSent', $this->firedEvents);
        $this->assertArrayHasKey('ResponseGenerated', $this->firedEvents);
        $this->assertArrayHasKey('CostCalculated', $this->firedEvents);
    }

    #[Test]
    public function it_works_with_real_openai_streaming()
    {
        if (! $this->hasCredentials) {
            $this->markTestSkipped('No OpenAI credentials available');
        }

        $this->firedEvents = []; // Reset

        $message = AIMessage::user('Count from 1 to 5, one number per line.');
        $message->user_id = 555;

        $chunks = [];
        $fullContent = '';

        foreach (AI::sendStreamingMessage($message, [
            'model' => 'gpt-3.5-turbo',
            'max_tokens' => 50,
            'temperature' => 0.1,
        ]) as $chunk) {
            $chunks[] = $chunk;
            $fullContent .= $chunk->content;

            // Verify each chunk
            $this->assertNotNull($chunk);
            $this->assertEquals('openai', $chunk->provider);
            $this->assertEquals('gpt-3.5-turbo', $chunk->model);
        }

        // Verify streaming worked
        $this->assertNotEmpty($chunks);
        $this->assertNotEmpty($fullContent);

        // Verify final chunk has token usage and cost
        $finalChunk = end($chunks);
        if ($finalChunk->tokenUsage) {
            $this->assertGreaterThan(0, $finalChunk->tokenUsage->totalTokens);
            $this->assertGreaterThan(0, $finalChunk->getTotalCost());
        }

        // Verify events were fired for streaming
        $this->assertArrayHasKey('MessageSent', $this->firedEvents);
        $this->assertArrayHasKey('ResponseGenerated', $this->firedEvents);
        $this->assertArrayHasKey('CostCalculated', $this->firedEvents);

        // Verify streaming-specific event data
        $responseEvent = $this->firedEvents['ResponseGenerated'][0];
        $this->assertTrue($responseEvent->context['streaming_response']);
        $this->assertGreaterThan(0, $responseEvent->context['total_chunks']);

        echo "\nStreaming Response: " . substr($fullContent, 0, 100) . '...';
        echo "\nChunks Received: " . count($chunks);
    }

    #[Test]
    public function it_calculates_real_costs_accurately()
    {
        if (! $this->hasCredentials) {
            $this->markTestSkipped('No OpenAI credentials available');
        }

        $this->firedEvents = []; // Reset

        $message = AIMessage::user('This is a test message for cost calculation.');
        $message->user_id = 999;

        $response = AI::sendMessage($message, [
            'model' => 'gpt-3.5-turbo',
            'max_tokens' => 100,
        ]);

        // Verify cost calculation
        $this->assertNotNull($response->tokenUsage);
        $this->assertGreaterThan(0, $response->tokenUsage->input_tokens);
        $this->assertGreaterThan(0, $response->tokenUsage->output_tokens);
        $this->assertGreaterThan(0, $response->tokenUsage->totalTokens);
        $this->assertGreaterThan(0, $response->getTotalCost());

        // Verify cost event has accurate data
        $costEvent = $this->firedEvents['CostCalculated'][0];
        $this->assertEquals($response->tokenUsage->input_tokens, $costEvent->input_tokens);
        $this->assertEquals($response->tokenUsage->output_tokens, $costEvent->output_tokens);
        $this->assertEquals($response->getTotalCost(), $costEvent->cost);
        $this->assertEquals(999, $costEvent->userId);

        // Cost should be reasonable for gpt-3.5-turbo (typically < $0.01 for short messages)
        $this->assertLessThan(0.01, $response->getTotalCost());

        echo "\nCost Analysis:";
        echo "\nInput Tokens: " . $response->tokenUsage->input_tokens;
        echo "\nOutput Tokens: " . $response->tokenUsage->output_tokens;
        echo "\nTotal Cost: $" . number_format($response->getTotalCost(), 6);
    }

    #[Test]
    public function it_handles_real_openai_errors_gracefully()
    {
        if (! $this->hasCredentials) {
            $this->markTestSkipped('No OpenAI credentials available');
        }

        // Test with invalid model to trigger an error
        try {
            $message = AIMessage::user('Test error handling');

            $response = AI::sendMessage($message, [
                'model' => 'invalid-model-name-that-does-not-exist',
            ]);

            $this->fail('Should have thrown an exception for invalid model');
        } catch (\Exception $e) {
            // Verify error handling works
            $this->assertNotEmpty($e->getMessage());
            $this->assertTrue(true, 'Error handling works correctly');
        }
    }
}
