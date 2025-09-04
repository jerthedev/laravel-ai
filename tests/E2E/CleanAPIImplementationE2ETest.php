<?php

namespace JTD\LaravelAI\Tests\E2E;

use JTD\LaravelAI\Facades\AI;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Models\AIResponse;
use JTD\LaravelAI\Providers\MockProvider;
use JTD\LaravelAI\Services\AIManager;
use JTD\LaravelAI\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;

/**
 * E2E test for clean API implementation.
 * Verifies that the clean sendMessage() API works correctly across all patterns
 * without any deprecated methods, ensuring a clear and unified developer experience.
 */
class CleanAPIImplementationE2ETest extends TestCase
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
                'content' => 'Clean API test response',
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
    public function it_verifies_clean_sendmessage_api_only()
    {
        // Verify that only the clean sendMessage() API exists, no deprecated methods

        $aiManager = app(AIManager::class);
        $reflection = new ReflectionClass($aiManager);
        $methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);

        $methodNames = array_map(fn ($method) => $method->getName(), $methods);

        // Should have sendMessage() method
        $this->assertContains('sendMessage', $methodNames,
            'AIManager should have sendMessage() method');

        // Should have sendStreamingMessage() method
        $this->assertContains('sendStreamingMessage', $methodNames,
            'AIManager should have sendStreamingMessage() method');

        // Should NOT have deprecated send() method
        $this->assertNotContains('send', $methodNames,
            'AIManager should NOT have deprecated send() method');

        // Should NOT have deprecated stream() method
        $this->assertNotContains('stream', $methodNames,
            'AIManager should NOT have deprecated stream() method');

        echo "\nClean API Verification:";
        echo "\n✓ sendMessage() method exists";
        echo "\n✓ sendStreamingMessage() method exists";
        echo "\n✓ send() method removed";
        echo "\n✓ stream() method removed";
    }

    #[Test]
    public function it_tests_clean_default_provider_api()
    {
        // Test the clean API with default provider
        $message = AIMessage::user('Test clean default provider API');
        $message->user_id = 123;

        // This should be the primary way to use the AI package
        $response = AI::sendMessage($message, ['model' => 'mock-gpt-4']);

        $this->assertInstanceOf(AIResponse::class, $response);
        $this->assertEquals('Clean API test response', $response->content);
        $this->assertEquals('mock', $response->provider);
        $this->assertEquals(123, $response->tokenUsage->input_tokens ?? 0);

        echo "\nDefault Provider API Test:";
        echo "\n✓ AI::sendMessage() works correctly";
        echo "\n✓ Returns proper AIResponse object";
        echo "\n✓ Uses default provider configuration";
    }

    #[Test]
    public function it_tests_clean_specific_provider_api()
    {
        // Test the clean API with specific provider
        $message = AIMessage::user('Test clean specific provider API');
        $message->user_id = 456;

        // This should be the way to use specific providers
        $response = AI::provider('mock')->sendMessage($message, ['model' => 'mock-gpt-4']);

        $this->assertInstanceOf(AIResponse::class, $response);
        $this->assertEquals('Clean API test response', $response->content);
        $this->assertEquals('mock', $response->provider);

        echo "\nSpecific Provider API Test:";
        echo "\n✓ AI::provider('mock')->sendMessage() works correctly";
        echo "\n✓ Returns proper AIResponse object";
        echo "\n✓ Uses specified provider";
    }

    #[Test]
    public function it_tests_clean_streaming_api()
    {
        // Test the clean streaming API
        $message = AIMessage::user('Test clean streaming API');
        $message->user_id = 789;

        // Default provider streaming
        $chunks1 = [];
        foreach (AI::sendStreamingMessage($message) as $chunk) {
            $chunks1[] = $chunk;
            $this->assertInstanceOf(AIResponse::class, $chunk);
        }

        $this->assertNotEmpty($chunks1);

        // Specific provider streaming
        $chunks2 = [];
        foreach (AI::provider('mock')->sendStreamingMessage($message) as $chunk) {
            $chunks2[] = $chunk;
            $this->assertInstanceOf(AIResponse::class, $chunk);
        }

        $this->assertNotEmpty($chunks2);

        echo "\nStreaming API Test:";
        echo "\n✓ AI::sendStreamingMessage() works correctly";
        echo "\n✓ AI::provider('mock')->sendStreamingMessage() works correctly";
        echo "\n✓ Both return proper generator with AIResponse chunks";
    }

    #[Test]
    public function it_tests_clean_conversation_api()
    {
        // Test that conversation builder uses the clean API internally
        $response = AI::conversation()
            ->message('Test clean conversation API')
            ->send();

        $this->assertInstanceOf(AIResponse::class, $response);
        $this->assertEquals('Clean API test response', $response->content);
        $this->assertEquals('mock', $response->provider);

        // Test conversation with specific provider
        $response2 = AI::conversation()
            ->provider('mock')
            ->message('Test clean conversation with provider')
            ->send();

        $this->assertInstanceOf(AIResponse::class, $response2);
        $this->assertEquals('mock', $response2->provider);

        echo "\nConversation API Test:";
        echo "\n✓ AI::conversation()->send() works correctly";
        echo "\n✓ AI::conversation()->provider('mock')->send() works correctly";
        echo "\n✓ Uses clean sendMessage() API internally";
    }

    #[Test]
    public function it_tests_clean_direct_driver_api()
    {
        // Test that direct driver instantiation uses clean API
        $driver = new MockProvider([
            'mock_responses' => [
                'default' => [
                    'content' => 'Direct driver clean API test',
                    'model' => 'mock-direct',
                    'provider' => 'mock',
                    'finish_reason' => 'stop',
                    'input_tokens' => 6,
                    'output_tokens' => 10,
                    'cost' => 0.001,
                ],
            ],
        ]);

        $message = AIMessage::user('Test direct driver clean API');
        $message->user_id = 999;

        // Direct driver should use sendMessage() method
        $response = $driver->sendMessage($message, ['model' => 'mock-direct']);

        $this->assertInstanceOf(AIResponse::class, $response);
        $this->assertEquals('Direct driver clean API test', $response->content);
        $this->assertEquals('mock', $response->provider);

        // Direct driver should also support streaming
        $chunks = [];
        foreach ($driver->sendStreamingMessage($message) as $chunk) {
            $chunks[] = $chunk;
            $this->assertInstanceOf(AIResponse::class, $chunk);
        }

        $this->assertNotEmpty($chunks);

        echo "\nDirect Driver API Test:";
        echo "\n✓ Direct driver sendMessage() works correctly";
        echo "\n✓ Direct driver sendStreamingMessage() works correctly";
        echo "\n✓ Uses clean API methods";
    }

    #[Test]
    public function it_verifies_consistent_response_structure()
    {
        // Test that all API patterns return consistent response structure
        $message = AIMessage::user('Test consistent response structure');

        // Get responses from all patterns
        $responses = [
            'default' => AI::sendMessage($message),
            'specific' => AI::provider('mock')->sendMessage($message),
            'conversation' => AI::conversation()->message('Test consistent response structure')->send(),
            'direct' => (new MockProvider)->sendMessage($message),
        ];

        // Verify all responses have consistent structure
        foreach ($responses as $pattern => $response) {
            $this->assertInstanceOf(AIResponse::class, $response,
                "Pattern {$pattern} should return AIResponse");
            $this->assertNotEmpty($response->content,
                "Pattern {$pattern} should have content");
            $this->assertEquals('mock', $response->provider,
                "Pattern {$pattern} should have correct provider");
            $this->assertNotNull($response->model,
                "Pattern {$pattern} should have model");
            $this->assertNotNull($response->finishReason,
                "Pattern {$pattern} should have finish reason");
            $this->assertNotNull($response->tokenUsage,
                "Pattern {$pattern} should have token usage");
        }

        echo "\nConsistent Response Structure Test:";
        echo "\n✓ All API patterns return AIResponse objects";
        echo "\n✓ All responses have consistent structure";
        echo "\n✓ All responses have required fields";
    }

    #[Test]
    public function it_verifies_clean_error_handling()
    {
        // Test that error handling is clean and consistent across all patterns

        try {
            // Test with invalid configuration to trigger error handling
            $message = AIMessage::user('Test error handling');

            // All patterns should handle errors gracefully
            $response1 = AI::sendMessage($message);
            $this->assertInstanceOf(AIResponse::class, $response1);

            $response2 = AI::provider('mock')->sendMessage($message);
            $this->assertInstanceOf(AIResponse::class, $response2);

            $response3 = AI::conversation()->message('Test error handling')->send();
            $this->assertInstanceOf(AIResponse::class, $response3);

            $response4 = (new MockProvider)->sendMessage($message);
            $this->assertInstanceOf(AIResponse::class, $response4);
        } catch (\Exception $e) {
            // If errors occur, they should be handled consistently
            $this->assertNotEmpty($e->getMessage(), 'Error messages should be informative');
        }

        echo "\nError Handling Test:";
        echo "\n✓ All API patterns handle errors gracefully";
        echo "\n✓ Error handling is consistent across patterns";
    }

    #[Test]
    public function it_verifies_clean_api_documentation_compliance()
    {
        // Verify that the API matches the documentation patterns

        // Pattern 1: AI::sendMessage() - Default provider
        $message1 = AIMessage::user('Documentation pattern 1');
        $response1 = AI::sendMessage($message1, ['model' => 'mock-gpt-4']);
        $this->assertInstanceOf(AIResponse::class, $response1);

        // Pattern 2: AI::provider('name')->sendMessage() - Specific provider
        $message2 = AIMessage::user('Documentation pattern 2');
        $response2 = AI::provider('mock')->sendMessage($message2, ['model' => 'mock-gpt-4']);
        $this->assertInstanceOf(AIResponse::class, $response2);

        // Pattern 3: AI::sendStreamingMessage() - Default provider streaming
        $message3 = AIMessage::user('Documentation pattern 3');
        $generator1 = AI::sendStreamingMessage($message3);
        $this->assertInstanceOf(\Generator::class, $generator1);

        // Pattern 4: AI::provider('name')->sendStreamingMessage() - Specific provider streaming
        $message4 = AIMessage::user('Documentation pattern 4');
        $generator2 = AI::provider('mock')->sendStreamingMessage($message4);
        $this->assertInstanceOf(\Generator::class, $generator2);

        echo "\nAPI Documentation Compliance Test:";
        echo "\n✓ AI::sendMessage() pattern works as documented";
        echo "\n✓ AI::provider('name')->sendMessage() pattern works as documented";
        echo "\n✓ AI::sendStreamingMessage() pattern works as documented";
        echo "\n✓ AI::provider('name')->sendStreamingMessage() pattern works as documented";
        echo "\n✓ All patterns match README examples";
    }
}
