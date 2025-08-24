<?php

namespace JTD\LaravelAI\Tests\E2E;

use JTD\LaravelAI\Facades\AI;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Models\AIResponse;
use JTD\LaravelAI\Providers\MockProvider;
use JTD\LaravelAI\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Comprehensive E2E test for the unified architecture.
 * Tests all API patterns with the unified event system.
 */
class CoreEventSystemUnifiedE2ETest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ai.default' => 'mock',
            'ai.providers.mock.enabled' => true,
            'ai.events.enabled' => true,
            'ai.providers.mock.simulate_errors' => false, // Disable error simulation
            'ai.providers.mock.mock_responses.default' => [
                'content' => 'Unified E2E test response',
                'model' => 'mock-gpt-4',
                'provider' => 'mock',
                'finish_reason' => 'stop',
                'input_tokens' => 10,
                'output_tokens' => 15,
                'cost' => 0.002,
            ],
        ]);
    }

    #[Test]
    public function it_tests_all_unified_api_patterns()
    {
        // Pattern 1: AI::sendMessage() - Default provider
        $response1 = AI::sendMessage(
            AIMessage::user('Test default provider pattern'),
            ['model' => 'mock-gpt-4']
        );

        $this->assertInstanceOf(AIResponse::class, $response1);
        $this->assertEquals('Unified E2E test response', $response1->content);
        $this->assertEquals('mock', $response1->provider);
        $this->assertEquals('mock-model', $response1->model); // MockProvider uses its default model

        // Pattern 2: AI::provider('mock')->sendMessage() - Specific provider
        $response2 = AI::provider('mock')->sendMessage(
            AIMessage::user('Test specific provider pattern'),
            ['model' => 'mock-gpt-4']
        );

        $this->assertInstanceOf(AIResponse::class, $response2);
        $this->assertEquals('Unified E2E test response', $response2->content);
        $this->assertEquals('mock', $response2->provider);

        // Pattern 3: AI::conversation()->send() - Fluent interface
        $response3 = AI::conversation()
            ->message('Test conversation pattern')
            ->send();

        $this->assertInstanceOf(AIResponse::class, $response3);
        $this->assertEquals('mock', $response3->provider);

        // Pattern 4: Direct driver instantiation
        $driver = new MockProvider([
            'mock_responses' => [
                'default' => [
                    'content' => 'Direct driver test response',
                    'model' => 'mock-direct',
                    'provider' => 'mock',
                    'finish_reason' => 'stop',
                    'input_tokens' => 8,
                    'output_tokens' => 12,
                    'cost' => 0.001,
                ],
            ],
        ]);

        $response4 = $driver->sendMessage(
            AIMessage::user('Test direct driver pattern'),
            ['model' => 'mock-direct']
        );

        $this->assertInstanceOf(AIResponse::class, $response4);
        $this->assertEquals('Direct driver test response', $response4->content);
        $this->assertEquals('mock', $response4->provider);
    }

    #[Test]
    public function it_tests_streaming_across_all_patterns()
    {
        // Pattern 1: AI::sendStreamingMessage() - Default provider streaming
        $generator1 = AI::sendStreamingMessage(
            AIMessage::user('Test default streaming')
        );

        $this->assertInstanceOf(\Generator::class, $generator1);

        $chunks1 = [];
        foreach ($generator1 as $chunk) {
            $chunks1[] = $chunk;
            $this->assertInstanceOf(AIResponse::class, $chunk);
        }
        $this->assertNotEmpty($chunks1);

        // Pattern 2: AI::provider('mock')->sendStreamingMessage() - Specific provider streaming
        $generator2 = AI::provider('mock')->sendStreamingMessage(
            AIMessage::user('Test specific provider streaming')
        );

        $chunks2 = [];
        foreach ($generator2 as $chunk) {
            $chunks2[] = $chunk;
            $this->assertInstanceOf(AIResponse::class, $chunk);
        }
        $this->assertNotEmpty($chunks2);

        // Pattern 3: AI::conversation()->stream() - Fluent interface streaming
        $generator3 = AI::conversation()
            ->message('Test conversation streaming')
            ->stream();

        $chunks3 = [];
        foreach ($generator3 as $chunk) {
            $chunks3[] = $chunk;
            $this->assertInstanceOf(AIResponse::class, $chunk);
        }
        $this->assertNotEmpty($chunks3);

        // Pattern 4: Direct driver streaming
        $driver = new MockProvider([
            'mock_responses' => [
                'default' => [
                    'content' => 'Direct streaming response',
                    'model' => 'mock-stream',
                    'provider' => 'mock',
                    'finish_reason' => 'stop',
                    'input_tokens' => 6,
                    'output_tokens' => 10,
                    'cost' => 0.001,
                ],
            ],
        ]);

        $generator4 = $driver->sendStreamingMessage(
            AIMessage::user('Test direct streaming')
        );

        $chunks4 = [];
        foreach ($generator4 as $chunk) {
            $chunks4[] = $chunk;
            $this->assertInstanceOf(AIResponse::class, $chunk);
        }
        $this->assertNotEmpty($chunks4);
    }

    #[Test]
    public function it_tests_conversation_builder_with_all_options()
    {
        // Test conversation builder with all optional parameters
        $response1 = AI::conversation()
            ->message('Test minimal conversation')
            ->send();

        $this->assertInstanceOf(AIResponse::class, $response1);
        $this->assertEquals('mock', $response1->provider);

        // Test conversation builder with provider specified
        $response2 = AI::conversation()
            ->provider('mock')
            ->message('Test with provider')
            ->send();

        $this->assertInstanceOf(AIResponse::class, $response2);
        $this->assertEquals('mock', $response2->provider);

        // Test conversation builder with model and temperature
        $response3 = AI::conversation()
            ->model('mock-gpt-4')
            ->temperature(0.7)
            ->message('Test with options')
            ->send();

        $this->assertInstanceOf(AIResponse::class, $response3);
        $this->assertEquals('mock', $response3->provider);

        // Test conversation builder with all options
        $response4 = AI::conversation()
            ->provider('mock')
            ->model('mock-gpt-4')
            ->temperature(0.8)
            ->maxTokens(100)
            ->message('System message')
            ->message('User message')
            ->send();

        $this->assertInstanceOf(AIResponse::class, $response4);
        $this->assertEquals('mock', $response4->provider);
    }

    #[Test]
    public function it_tests_multi_message_conversations()
    {
        // Test single message with context (the unified API uses single AIMessage)
        $message = AIMessage::user('What can you help me with?');
        $message->metadata = [
            'context' => 'Previous conversation about being helpful',
        ];

        $response = AI::sendMessage($message, ['model' => 'mock-gpt-4']);

        $this->assertInstanceOf(AIResponse::class, $response);
        $this->assertEquals('mock', $response->provider);

        // Test with specific provider
        $response2 = AI::provider('mock')->sendMessage($message, ['model' => 'mock-gpt-4']);

        $this->assertInstanceOf(AIResponse::class, $response2);
        $this->assertEquals('mock', $response2->provider);
    }

    #[Test]
    public function it_tests_error_handling_across_patterns()
    {
        // Test that all patterns handle errors gracefully
        // This test verifies the unified architecture handles errors consistently

        try {
            // Pattern 1: Default provider
            $response1 = AI::sendMessage(AIMessage::user('Test error handling'));
            $this->assertInstanceOf(AIResponse::class, $response1);

            // Pattern 2: Specific provider
            $response2 = AI::provider('mock')->sendMessage(AIMessage::user('Test error handling'));
            $this->assertInstanceOf(AIResponse::class, $response2);

            // Pattern 3: Conversation builder
            $response3 = AI::conversation()->message('Test error handling')->send();
            $this->assertInstanceOf(AIResponse::class, $response3);

            // Pattern 4: Direct driver
            $driver = new MockProvider();
            $response4 = $driver->sendMessage(AIMessage::user('Test error handling'));
            $this->assertInstanceOf(AIResponse::class, $response4);

        } catch (\Exception $e) {
            // If any errors occur, they should be handled consistently
            $this->fail('Unified architecture should handle errors gracefully: ' . $e->getMessage());
        }
    }

    #[Test]
    public function it_verifies_unified_response_structure()
    {
        // Test that all patterns return consistent response structure
        $message = AIMessage::user('Test response structure');

        // Get responses from all patterns
        $responses = [
            'default' => AI::sendMessage($message),
            'specific' => AI::provider('mock')->sendMessage($message),
            'conversation' => AI::conversation()->message('Test response structure')->send(),
            'direct' => (new MockProvider())->sendMessage($message),
        ];

        // Verify all responses have consistent structure
        foreach ($responses as $pattern => $response) {
            $this->assertInstanceOf(AIResponse::class, $response, "Pattern {$pattern} should return AIResponse");
            $this->assertNotEmpty($response->content, "Pattern {$pattern} should have content");
            $this->assertEquals('mock', $response->provider, "Pattern {$pattern} should have correct provider");
            $this->assertNotNull($response->model, "Pattern {$pattern} should have model");
            $this->assertNotNull($response->finishReason, "Pattern {$pattern} should have finish reason");
        }
    }
}
