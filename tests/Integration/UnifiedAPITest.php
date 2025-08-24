<?php

namespace JTD\LaravelAI\Tests\Integration;

use JTD\LaravelAI\Contracts\AIProviderInterface;
use JTD\LaravelAI\Facades\AI;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Models\AIResponse;
use JTD\LaravelAI\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Test the unified API implementation following the DB facade pattern.
 */
class UnifiedAPITest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ai.default' => 'mock',
            'ai.providers.mock.enabled' => true,
            'ai.providers.mock.mock_responses.default' => [
                'content' => 'Mock response for unified API test',
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
    public function it_supports_default_provider_sendmessage_pattern()
    {
        // Test AI::sendMessage() - like DB::table()
        $message = AIMessage::user('Test default provider sendMessage');
        $response = AI::sendMessage($message, ['model' => 'mock-gpt-4']);

        $this->assertInstanceOf(AIResponse::class, $response);
        $this->assertEquals('Mock response for unified API test', $response->content);
        $this->assertEquals('mock', $response->provider);
    }

    #[Test]
    public function it_supports_specific_provider_sendmessage_pattern()
    {
        // Test AI::provider('mock')->sendMessage() - like DB::connection('mysql')->table()
        $message = AIMessage::user('Test specific provider sendMessage');
        $provider = AI::provider('mock');

        $this->assertInstanceOf(AIProviderInterface::class, $provider);

        $response = $provider->sendMessage($message, ['model' => 'mock-gpt-4']);

        $this->assertInstanceOf(AIResponse::class, $response);
        $this->assertEquals('Mock response for unified API test', $response->content);
        $this->assertEquals('mock', $response->provider);
    }

    #[Test]
    public function it_supports_streaming_with_default_provider()
    {
        // Test AI::sendStreamingMessage()
        $message = AIMessage::user('Test streaming with default provider');
        $generator = AI::sendStreamingMessage($message, ['model' => 'mock-gpt-4']);

        $this->assertInstanceOf(\Generator::class, $generator);

        $chunks = [];
        foreach ($generator as $chunk) {
            $chunks[] = $chunk;
            $this->assertInstanceOf(AIResponse::class, $chunk);
        }

        $this->assertNotEmpty($chunks);
    }

    #[Test]
    public function it_supports_streaming_with_specific_provider()
    {
        // Test AI::provider('mock')->sendStreamingMessage()
        $message = AIMessage::user('Test streaming with specific provider');
        $provider = AI::provider('mock');
        $generator = $provider->sendStreamingMessage($message, ['model' => 'mock-gpt-4']);

        $this->assertInstanceOf(\Generator::class, $generator);

        $chunks = [];
        foreach ($generator as $chunk) {
            $chunks[] = $chunk;
            $this->assertInstanceOf(AIResponse::class, $chunk);
        }

        $this->assertNotEmpty($chunks);
    }



    #[Test]
    public function it_uses_correct_default_provider_from_config()
    {
        // Verify that AI::sendMessage() uses the configured default provider
        $message = AIMessage::user('Test default provider config');
        $response = AI::sendMessage($message);

        // Should use 'mock' as configured in setUp()
        $this->assertEquals('mock', $response->provider);
    }

    #[Test]
    public function it_allows_switching_providers_dynamically()
    {
        // Test that we can switch between providers
        $message = AIMessage::user('Test provider switching');

        // Use mock provider
        $mockResponse = AI::provider('mock')->sendMessage($message);
        $this->assertEquals('mock', $mockResponse->provider);

        // Default should also be mock
        $defaultResponse = AI::sendMessage($message);
        $this->assertEquals('mock', $defaultResponse->provider);
    }

    #[Test]
    public function it_preserves_message_metadata_across_calls()
    {
        $message = AIMessage::user('Test metadata preservation');
        $message->user_id = 999;
        $message->conversation_id = 123;
        $message->metadata = ['custom_key' => 'custom_value'];

        $response = AI::sendMessage($message);

        $this->assertInstanceOf(AIResponse::class, $response);
        // The message object should retain its metadata
        $this->assertEquals(999, $message->user_id);
        $this->assertEquals(123, $message->conversation_id);
        $this->assertEquals('custom_value', $message->metadata['custom_key']);
    }

    #[Test]
    public function it_handles_options_correctly_across_patterns()
    {
        $message = AIMessage::user('Test options handling');
        $options = [
            'model' => 'mock-gpt-4',
            'temperature' => 0.7,
            'max_tokens' => 100,
        ];

        // Test with default provider
        $response1 = AI::sendMessage($message, $options);
        $this->assertInstanceOf(AIResponse::class, $response1);

        // Test with specific provider
        $response2 = AI::provider('mock')->sendMessage($message, $options);
        $this->assertInstanceOf(AIResponse::class, $response2);

        // Both should work the same way
        $this->assertEquals($response1->provider, $response2->provider);
    }
}
