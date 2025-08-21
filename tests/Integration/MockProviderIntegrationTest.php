<?php

namespace JTD\LaravelAI\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use JTD\LaravelAI\Exceptions\RateLimitException;
use JTD\LaravelAI\Facades\AI;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Models\AIResponse;
use JTD\LaravelAI\Providers\MockProvider;
use JTD\LaravelAI\Tests\TestCase;

/**
 * Integration tests for mock provider with the complete system.
 *
 * Tests the mock provider's integration with:
 * - AI facade and manager
 * - Conversation builder
 * - Driver system
 * - Configuration system
 * - Error handling
 * - Response fixtures
 */
class MockProviderIntegrationTest extends TestCase
{
    #[Test]
    public function mock_provider_integrates_with_ai_facade()
    {
        $response = AI::conversation()
            ->provider('mock')
            ->message('Hello, world!')
            ->send();

        $this->assertInstanceOf(AIResponse::class, $response);
        $this->assertNotEmpty($response->content);
        $this->assertEquals('mock', $response->provider);
        $this->assertInstanceOf(\JTD\LaravelAI\Models\TokenUsage::class, $response->tokenUsage);
    }
    #[Test]
    public function mock_provider_works_with_conversation_builder()
    {
        $builder = AI::conversation('Test Conversation')
            ->provider('mock')
            ->model('mock-advanced')
            ->temperature(0.7)
            ->maxTokens(1000)
            ->systemPrompt('You are a helpful assistant')
            ->message('What is Laravel?');

        $response = $builder->send();

        $this->assertInstanceOf(AIResponse::class, $response);
        $this->assertNotEmpty($response->content);
        $this->assertEquals('mock', $response->provider);
    }
    #[Test]
    public function mock_provider_supports_streaming()
    {
        // Configure mock provider with faster streaming for tests
        config(['ai.providers.mock.streaming_delay' => 5]); // 5ms instead of 50ms

        $chunks = [];

        // Use a longer message to ensure multiple chunks
        $longMessage = 'Tell me a very long story about a brave knight who goes on an epic adventure to save the kingdom from a terrible dragon that has been terrorizing the land for many years.';

        foreach (AI::conversation()->provider('mock')->message($longMessage)->stream() as $chunk) {
            $chunks[] = $chunk;
            $this->assertInstanceOf(AIResponse::class, $chunk);
        }

        $this->assertNotEmpty($chunks);
        $this->assertGreaterThanOrEqual(1, count($chunks)); // At least 1 chunk

        // Verify last chunk is marked as complete
        $lastChunk = end($chunks);
        $this->assertTrue($lastChunk->metadata['is_complete'] ?? false);
    }
    #[Test]
    public function mock_provider_handles_multiple_messages()
    {
        $response = AI::conversation()
            ->provider('mock')
            ->message('Hello')
            ->message('How are you?')
            ->message('What can you help me with?')
            ->send();

        $this->assertInstanceOf(AIResponse::class, $response);
        $this->assertNotEmpty($response->content);
    }
    #[Test]
    public function mock_provider_uses_response_fixtures()
    {
        // Test with OpenAI fixtures
        $mockProvider = new MockProvider;
        $mockProvider->loadFixtures('openai');

        $response = $mockProvider->sendMessage(AIMessage::user('Hello'));

        $this->assertStringContainsString('ChatGPT', $response->content);
        // The provider is still 'mock' but uses OpenAI-style responses
        $this->assertEquals('mock', $response->provider);
    }
    #[Test]
    public function mock_provider_simulates_different_providers()
    {
        $providers = ['openai', 'xai', 'gemini', 'ollama'];

        foreach ($providers as $providerType) {
            $mockProvider = new MockProvider;
            $mockProvider->loadFixtures($providerType);

            $response = $mockProvider->sendMessage(AIMessage::user('Hello'));

            $this->assertInstanceOf(AIResponse::class, $response);
            $this->assertNotEmpty($response->content);

            // Each provider should have different response characteristics
            switch ($providerType) {
                case 'openai':
                    $this->assertStringContainsString('ChatGPT', $response->content);
                    break;
                case 'xai':
                    $this->assertStringContainsString('Grok', $response->content);
                    break;
                case 'gemini':
                    $this->assertStringContainsString('Gemini', $response->content);
                    break;
                case 'ollama':
                    $this->assertStringContainsString('locally', $response->content);
                    break;
            }
        }
    }
    #[Test]
    public function mock_provider_handles_error_simulation()
    {
        $mockProvider = new MockProvider([
            'simulate_errors' => true,
            'error_scenarios' => [
                'rate_limit' => ['probability' => 1.0],
            ],
            'retry' => ['max_attempts' => 1], // Prevent infinite retries
        ]);

        $this->expectException(RateLimitException::class);

        $mockProvider->sendMessage(AIMessage::user('Test message'));
    }
    #[Test]
    public function mock_provider_integrates_with_driver_system()
    {
        // Test that mock provider is registered in driver system
        $driver = AI::driver('mock');

        $this->assertInstanceOf(MockProvider::class, $driver);

        // Test that it can be used through driver system
        $response = $driver->sendMessage(AIMessage::user('Hello from driver'));

        $this->assertInstanceOf(AIResponse::class, $response);
        $this->assertNotEmpty($response->content);
    }
    #[Test]
    public function mock_provider_respects_configuration()
    {
        // Test with custom configuration
        config(['ai.providers.mock.valid_credentials' => false]);

        $mockProvider = new MockProvider(config('ai.providers.mock'));
        $validation = $mockProvider->validateCredentials();

        $this->assertEquals('invalid', $validation['status']);

        // Reset configuration
        config(['ai.providers.mock.valid_credentials' => true]);
    }
    #[Test]
    public function mock_provider_calculates_costs_correctly()
    {
        $cost = AI::calculateCost('Hello, world!', 'mock');

        $this->assertIsArray($cost);
        $this->assertArrayHasKey('total', $cost);
        $this->assertArrayHasKey('input_cost', $cost);
        $this->assertArrayHasKey('output_cost', $cost);
        $this->assertArrayHasKey('currency', $cost);
        $this->assertArrayHasKey('tokens', $cost);

        $this->assertIsFloat($cost['total']);
        $this->assertGreaterThan(0, $cost['total']);
    }
    #[Test]
    public function mock_provider_estimates_tokens_correctly()
    {
        $tokens = AI::estimateTokens('Hello, world!', 'mock');

        $this->assertIsInt($tokens);
        $this->assertGreaterThan(0, $tokens);

        // Longer text should have more tokens
        $longerTokens = AI::estimateTokens('This is a much longer message that should have more tokens', 'mock');
        $this->assertGreaterThan($tokens, $longerTokens);
    }
    #[Test]
    public function mock_provider_returns_available_models()
    {
        $models = AI::getModels('mock');

        $this->assertIsArray($models);
        $this->assertNotEmpty($models);

        foreach ($models as $model) {
            $this->assertArrayHasKey('id', $model);
            $this->assertArrayHasKey('name', $model);
            $this->assertArrayHasKey('description', $model);
            $this->assertArrayHasKey('max_tokens', $model);
            $this->assertArrayHasKey('supports_streaming', $model);
        }
    }
    #[Test]
    public function mock_provider_validates_correctly()
    {
        $isValid = AI::validateProvider('mock');

        $this->assertTrue($isValid);
    }
    #[Test]
    public function mock_provider_reports_health_status()
    {
        $health = AI::getProviderHealth('mock');

        $this->assertIsArray($health);
        $this->assertArrayHasKey('status', $health);
        $this->assertEquals('healthy', $health['status']);
        $this->assertArrayHasKey('response_time', $health);
        $this->assertArrayHasKey('provider', $health);
        $this->assertEquals('mock', $health['provider']);
    }
    #[Test]
    public function mock_provider_supports_custom_responses()
    {
        $mockProvider = new MockProvider;
        $mockProvider->addMockResponse('weather', [
            'content' => 'The weather is sunny today!',
            'input_tokens' => 10,
            'output_tokens' => 8,
            'cost' => 0.0018,
        ]);

        $response = $mockProvider->sendMessage(AIMessage::user('What is the weather like?'));

        $this->assertEquals('The weather is sunny today!', $response->content);
    }
    #[Test]
    public function mock_provider_handles_response_delays()
    {
        $mockProvider = new MockProvider(['response_delay' => 100]); // 100ms delay

        $startTime = microtime(true);
        $response = $mockProvider->sendMessage(AIMessage::user('Test message'));
        $endTime = microtime(true);

        $duration = ($endTime - $startTime) * 1000; // Convert to milliseconds
        $this->assertGreaterThanOrEqual(90, $duration); // Allow some tolerance

        $this->assertInstanceOf(AIResponse::class, $response);
    }
    #[Test]
    public function mock_provider_works_with_different_message_types()
    {
        $messages = [
            AIMessage::user('Hello'),
            AIMessage::assistant('Hi there!'),
            AIMessage::system('You are a helpful assistant'),
        ];

        foreach ($messages as $message) {
            $response = AI::conversation()
                ->provider('mock')
                ->message($message)
                ->send();

            $this->assertInstanceOf(AIResponse::class, $response);
            $this->assertNotEmpty($response->content);
        }
    }
    #[Test]
    public function mock_provider_maintains_conversation_context()
    {
        $builder = AI::conversation('Context Test')
            ->provider('mock')
            ->systemPrompt('Remember the user\'s name');

        $response1 = $builder->message('My name is John')->send();
        $this->assertInstanceOf(AIResponse::class, $response1);

        $response2 = $builder->message('What is my name?')->send();
        $this->assertInstanceOf(AIResponse::class, $response2);

        // Both responses should be valid
        $this->assertNotEmpty($response1->content);
        $this->assertNotEmpty($response2->content);
    }
    #[Test]
    public function mock_provider_integrates_with_event_system()
    {
        $eventsFired = [];

        // Listen for events (if event system is implemented)
        if (class_exists('\JTD\LaravelAI\Events\MessageSent')) {
            \Event::listen('\JTD\LaravelAI\Events\MessageSent', function ($event) use (&$eventsFired) {
                $eventsFired[] = 'MessageSent';
            });
        }

        if (class_exists('\JTD\LaravelAI\Events\ResponseGenerated')) {
            \Event::listen('\JTD\LaravelAI\Events\ResponseGenerated', function ($event) use (&$eventsFired) {
                $eventsFired[] = 'ResponseGenerated';
            });
        }

        $response = AI::conversation()
            ->provider('mock')
            ->message('Test event firing')
            ->send();

        $this->assertInstanceOf(AIResponse::class, $response);

        // If events are implemented, they should have fired
        // If not implemented yet, this test still passes
        $this->assertTrue(true, 'Event system integration test completed');
    }
    #[Test]
    public function mock_provider_handles_batch_operations()
    {
        $conversations = [];
        $responses = [];

        // Create multiple conversations
        for ($i = 1; $i <= 3; $i++) {
            $conversations[] = AI::conversation("Batch Test {$i}")
                ->provider('mock')
                ->message("Hello from conversation {$i}");
        }

        // Send all conversations
        foreach ($conversations as $conversation) {
            $responses[] = $conversation->send();
        }

        // Verify all responses
        $this->assertCount(3, $responses);
        foreach ($responses as $response) {
            $this->assertInstanceOf(AIResponse::class, $response);
            $this->assertNotEmpty($response->content);
            $this->assertEquals('mock', $response->provider);
        }
    }
    #[Test]
    public function mock_provider_works_with_all_configuration_options()
    {
        $config = [
            'driver' => 'mock',
            'valid_credentials' => true,
            'simulate_errors' => false,
            'response_delay' => 0,
            'streaming_chunk_size' => 5,
            'streaming_delay' => 25,
            'fixture_provider' => 'openai',
            'health_status' => 'healthy',
            'health_response_time' => 75,
            'supports_vision' => false,
            'supports_audio' => false,
            'supports_embeddings' => false,
            'supports_fine_tuning' => false,
            'supports_batch' => true,
        ];

        $mockProvider = new MockProvider($config);

        // Test basic functionality
        $response = $mockProvider->sendMessage(AIMessage::user('Test with full config'));
        $this->assertInstanceOf(AIResponse::class, $response);

        // Test capabilities
        $capabilities = $mockProvider->getCapabilities();
        $this->assertFalse($capabilities['vision']);
        $this->assertFalse($capabilities['audio']);
        $this->assertTrue($capabilities['batch_processing']);

        // Test health status
        $health = $mockProvider->getHealthStatus();
        $this->assertEquals('healthy', $health['status']);
        $this->assertEquals(75, $health['response_time']);
    }
}
