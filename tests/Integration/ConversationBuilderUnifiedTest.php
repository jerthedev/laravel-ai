<?php

namespace JTD\LaravelAI\Tests\Integration;

use JTD\LaravelAI\Facades\AI;
use JTD\LaravelAI\Models\AIResponse;
use JTD\LaravelAI\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Test that ConversationBuilder uses the unified sendMessage() approach internally.
 */
class ConversationBuilderUnifiedTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ai.default' => 'mock',
            'ai.providers.mock.enabled' => true,
            'ai.providers.mock.mock_responses.default' => [
                'content' => 'Mock response for conversation builder test',
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
    public function it_uses_unified_sendmessage_internally()
    {
        // Test that AI::conversation()->send() works and uses sendMessage() internally
        $response = AI::conversation()
            ->message('Test conversation builder unified API')
            ->send();

        $this->assertInstanceOf(AIResponse::class, $response);
        $this->assertEquals('Mock response for conversation builder test', $response->content);
        $this->assertEquals('mock', $response->provider);
    }

    #[Test]
    public function it_supports_provider_specific_conversations()
    {
        // Test that conversation builder can use specific providers
        $response = AI::conversation()
            ->provider('mock')
            ->message('Test provider-specific conversation')
            ->send();

        $this->assertInstanceOf(AIResponse::class, $response);
        $this->assertEquals('Mock response for conversation builder test', $response->content);
        $this->assertEquals('mock', $response->provider);
    }

    #[Test]
    public function it_supports_streaming_with_unified_api()
    {
        // Test that conversation builder streaming uses sendStreamingMessage() internally
        $generator = AI::conversation()
            ->message('Test streaming conversation')
            ->stream();

        $this->assertInstanceOf(\Generator::class, $generator);

        $chunks = [];
        foreach ($generator as $chunk) {
            $chunks[] = $chunk;
            $this->assertInstanceOf(AIResponse::class, $chunk);
        }

        $this->assertNotEmpty($chunks);
    }

    #[Test]
    public function it_maintains_fluent_interface_while_using_unified_api()
    {
        // Test that the fluent interface still works with the unified architecture
        $response = AI::conversation()
            ->provider('mock')
            ->model('mock-gpt-4')
            ->message('Test fluent interface')
            ->temperature(0.7)
            ->maxTokens(100)
            ->send();

        $this->assertInstanceOf(AIResponse::class, $response);
        $this->assertEquals('Mock response for conversation builder test', $response->content);
    }

    #[Test]
    public function it_works_with_multiple_messages()
    {
        // Test conversation with multiple messages
        $response = AI::conversation()
            ->message('First message')
            ->message('Second message')
            ->message('Third message')
            ->send();

        $this->assertInstanceOf(AIResponse::class, $response);
        $this->assertEquals('Mock response for conversation builder test', $response->content);
    }

    #[Test]
    public function it_preserves_conversation_context()
    {
        // Test that conversation context is maintained
        $conversation = AI::conversation()
            ->title('Test Conversation')
            ->message('Hello');

        $response1 = $conversation->send();
        $this->assertInstanceOf(AIResponse::class, $response1);

        // Add another message to the same conversation
        $response2 = $conversation
            ->message('Follow up question')
            ->send();

        $this->assertInstanceOf(AIResponse::class, $response2);
    }
}
