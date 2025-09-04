<?php

namespace JTD\LaravelAI\Tests\Integration;

use JTD\LaravelAI\Facades\AI;
use JTD\LaravelAI\Models\AIResponse;
use JTD\LaravelAI\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Test that conversation builder uses default provider when none is specified.
 */
class ConversationDefaultProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ai.default' => 'mock',
            'ai.providers.mock.enabled' => true,
            'ai.providers.mock.mock_responses.default' => [
                'content' => 'Default provider response from conversation',
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
    public function it_uses_default_provider_when_none_specified()
    {
        // This should use the default provider (mock) without specifying it
        $response = AI::conversation()
            ->message('Hello, world!')
            ->send();

        $this->assertInstanceOf(AIResponse::class, $response);
        $this->assertEquals('Default provider response from conversation', $response->content);
        $this->assertEquals('mock', $response->provider);
    }

    #[Test]
    public function it_uses_default_provider_with_model_and_temperature()
    {
        // This should use default provider with custom model and temperature
        $response = AI::conversation()
            ->model('gpt-4')
            ->temperature(0.7)
            ->message('Hello with options!')
            ->send();

        $this->assertInstanceOf(AIResponse::class, $response);
        $this->assertEquals('Default provider response from conversation', $response->content);
        $this->assertEquals('mock', $response->provider);
    }

    #[Test]
    public function it_can_override_default_provider()
    {
        // This should use the specified provider instead of default
        $response = AI::conversation()
            ->provider('mock')  // Explicitly specify provider
            ->model('gpt-4')
            ->temperature(0.7)
            ->message('Hello with explicit provider!')
            ->send();

        $this->assertInstanceOf(AIResponse::class, $response);
        $this->assertEquals('Default provider response from conversation', $response->content);
        $this->assertEquals('mock', $response->provider);
    }

    #[Test]
    public function it_supports_minimal_conversation_syntax()
    {
        // The most minimal syntax - just message and send
        $response = AI::conversation()
            ->message('Minimal conversation')
            ->send();

        $this->assertInstanceOf(AIResponse::class, $response);
        $this->assertEquals('Default provider response from conversation', $response->content);
        $this->assertEquals('mock', $response->provider);
    }

    #[Test]
    public function it_supports_streaming_with_default_provider()
    {
        // Streaming should also work with default provider
        $generator = AI::conversation()
            ->message('Stream with default provider')
            ->stream();

        $this->assertInstanceOf(\Generator::class, $generator);

        $chunks = [];
        foreach ($generator as $chunk) {
            $chunks[] = $chunk;
            $this->assertInstanceOf(AIResponse::class, $chunk);
        }

        $this->assertNotEmpty($chunks);
    }
}
