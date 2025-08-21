<?php

namespace JTD\LaravelAI\Tests\Unit;

use JTD\LaravelAI\Exceptions\InvalidCredentialsException;
use JTD\LaravelAI\Exceptions\ProviderException;
use JTD\LaravelAI\Exceptions\RateLimitException;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Models\AIResponse;
use JTD\LaravelAI\Models\TokenUsage;
use JTD\LaravelAI\Providers\MockProvider;
use JTD\LaravelAI\Testing\ResponseFixtures;
use JTD\LaravelAI\Tests\TestCase;

class MockProviderTest extends TestCase
{
    protected MockProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provider = new MockProvider;
    }

    /** @test */
    public function it_can_create_mock_provider()
    {
        $this->assertInstanceOf(MockProvider::class, $this->provider);
        $this->assertEquals('mock', $this->provider->getName());
        $this->assertEquals('1.0.0', $this->provider->getVersion());
    }

    /** @test */
    public function it_can_send_basic_message()
    {
        $message = AIMessage::user('Hello, world!');
        $response = $this->provider->sendMessage($message);

        $this->assertInstanceOf(AIResponse::class, $response);
        $this->assertIsString($response->content);
        $this->assertInstanceOf(TokenUsage::class, $response->tokenUsage);
        $this->assertEquals('mock', $response->provider);
    }

    /** @test */
    public function it_can_send_streaming_message()
    {
        $message = AIMessage::user('Hello, world!');
        $chunks = [];

        foreach ($this->provider->sendStreamingMessage($message) as $chunk) {
            $chunks[] = $chunk;
            $this->assertInstanceOf(AIResponse::class, $chunk);
        }

        $this->assertNotEmpty($chunks);
        $lastChunk = end($chunks);
        $this->assertTrue($lastChunk->metadata['is_complete'] ?? false);
    }

    /** @test */
    public function it_returns_available_models()
    {
        $models = $this->provider->getAvailableModels();

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

    /** @test */
    public function it_can_get_model_info()
    {
        $modelInfo = $this->provider->getModelInfo('mock-model');

        $this->assertIsArray($modelInfo);
        $this->assertEquals('mock-model', $modelInfo['id']);
        $this->assertArrayHasKey('name', $modelInfo);
        $this->assertArrayHasKey('description', $modelInfo);
    }

    /** @test */
    public function it_throws_exception_for_unknown_model()
    {
        $this->expectException(\JTD\LaravelAI\Exceptions\ModelNotFoundException::class);

        $this->provider->getModelInfo('unknown-model');
    }

    /** @test */
    public function it_can_calculate_cost()
    {
        $cost = $this->provider->calculateCost('Hello, world!');

        $this->assertIsArray($cost);
        $this->assertArrayHasKey('total', $cost);
        $this->assertArrayHasKey('input_cost', $cost);
        $this->assertArrayHasKey('output_cost', $cost);
        $this->assertArrayHasKey('currency', $cost);
        $this->assertArrayHasKey('tokens', $cost);
    }

    /** @test */
    public function it_can_estimate_tokens()
    {
        $tokens = $this->provider->estimateTokens('Hello, world!');

        $this->assertIsInt($tokens);
        $this->assertGreaterThan(0, $tokens);
    }

    /** @test */
    public function it_validates_credentials()
    {
        $result = $this->provider->validateCredentials();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('status', $result);
        $this->assertEquals('valid', $result['status']);
    }

    /** @test */
    public function it_can_invalidate_credentials()
    {
        $provider = new MockProvider(['valid_credentials' => false]);
        $result = $provider->validateCredentials();

        $this->assertEquals('invalid', $result['status']);
    }

    /** @test */
    public function it_returns_health_status()
    {
        $health = $this->provider->getHealthStatus();

        $this->assertIsArray($health);
        $this->assertArrayHasKey('status', $health);
        $this->assertArrayHasKey('response_time', $health);
        $this->assertEquals('healthy', $health['status']);
    }

    /** @test */
    public function it_returns_capabilities()
    {
        $capabilities = $this->provider->getCapabilities();

        $this->assertIsArray($capabilities);
        $this->assertArrayHasKey('streaming', $capabilities);
        $this->assertArrayHasKey('function_calling', $capabilities);
        $this->assertTrue($capabilities['streaming']);
        $this->assertTrue($capabilities['function_calling']);
    }

    /** @test */
    public function it_supports_feature_checking()
    {
        $this->assertTrue($this->provider->supportsFeature('streaming'));
        $this->assertTrue($this->provider->supportsFeature('function_calling'));
        $this->assertFalse($this->provider->supportsFeature('nonexistent_feature'));
    }

    /** @test */
    public function it_returns_rate_limits()
    {
        $rateLimits = $this->provider->getRateLimits();

        $this->assertIsArray($rateLimits);
        $this->assertArrayHasKey('requests_per_minute', $rateLimits);
        $this->assertArrayHasKey('tokens_per_minute', $rateLimits);
    }

    /** @test */
    public function it_returns_usage_stats()
    {
        $stats = $this->provider->getUsageStats();

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('period', $stats);
        $this->assertArrayHasKey('requests', $stats);
        $this->assertArrayHasKey('tokens', $stats);
        $this->assertArrayHasKey('cost', $stats);
    }

    /** @test */
    public function it_can_set_model()
    {
        $this->provider->setModel('mock-advanced');

        $this->assertEquals('mock-advanced', $this->provider->getCurrentModel());
    }

    /** @test */
    public function it_can_set_options()
    {
        $options = ['temperature' => 0.7, 'max_tokens' => 1000];
        $this->provider->setOptions($options);

        $currentOptions = $this->provider->getOptions();
        $this->assertEquals(0.7, $currentOptions['temperature']);
        $this->assertEquals(1000, $currentOptions['max_tokens']);
    }

    /** @test */
    public function it_can_configure_error_simulation()
    {
        // Create provider with no retries to avoid infinite loops
        $provider = new MockProvider([
            'retry' => ['max_attempts' => 1],
            'simulate_errors' => true,
            'error_scenarios' => [
                'rate_limit' => ['probability' => 1.0],
            ],
        ]);

        $this->expectException(RateLimitException::class);

        $message = AIMessage::user('Test message');
        $provider->sendMessage($message);
    }

    /** @test */
    public function it_can_simulate_timeout_error()
    {
        $provider = new MockProvider([
            'retry' => ['max_attempts' => 1],
            'simulate_errors' => true,
            'error_scenarios' => [
                'timeout' => ['probability' => 1.0],
            ],
        ]);

        $this->expectException(ProviderException::class);

        $message = AIMessage::user('Test message');
        $provider->sendMessage($message);
    }

    /** @test */
    public function it_can_simulate_invalid_credentials_error()
    {
        $provider = new MockProvider([
            'retry' => ['max_attempts' => 1],
            'simulate_errors' => true,
            'error_scenarios' => [
                'invalid_credentials' => ['probability' => 1.0],
            ],
        ]);

        $this->expectException(InvalidCredentialsException::class);

        $message = AIMessage::user('Test message');
        $provider->sendMessage($message);
    }

    /** @test */
    public function it_can_set_response_delay()
    {
        $provider = new MockProvider(['response_delay' => 50]); // 50ms

        $startTime = microtime(true);
        $message = AIMessage::user('Test message');
        $provider->sendMessage($message);
        $endTime = microtime(true);

        $duration = ($endTime - $startTime) * 1000; // Convert to milliseconds
        $this->assertGreaterThanOrEqual(40, $duration); // Allow some tolerance
    }

    /** @test */
    public function it_can_add_custom_mock_responses()
    {
        $this->provider->addMockResponse('custom_trigger', [
            'content' => 'Custom response content',
            'input_tokens' => 5,
            'output_tokens' => 10,
            'cost' => 0.001,
        ]);

        $message = AIMessage::user('This contains custom_trigger in the message');
        $response = $this->provider->sendMessage($message);

        $this->assertEquals('Custom response content', $response->content);
    }

    /** @test */
    public function it_can_load_provider_fixtures()
    {
        $this->provider->loadFixtures('openai');

        $message = AIMessage::user('Hello');
        $response = $this->provider->sendMessage($message);

        $this->assertStringContainsString('ChatGPT', $response->content);
    }

    /** @test */
    public function it_uses_appropriate_fixtures_for_content()
    {
        $this->provider->loadFixtures('openai');

        // Test hello response
        $helloMessage = AIMessage::user('Hello there!');
        $helloResponse = $this->provider->sendMessage($helloMessage);
        $this->assertStringContainsString('ChatGPT', $helloResponse->content);

        // Test code help response
        $codeMessage = AIMessage::user('Can you help me with code?');
        $codeResponse = $this->provider->sendMessage($codeMessage);
        $this->assertStringContainsString('php', $codeResponse->content);
    }

    /** @test */
    public function it_handles_error_responses_from_fixtures()
    {
        $this->provider->addMockResponse('error_test', [
            'error' => 'rate_limit',
            'message' => 'Rate limit exceeded',
            'retry_after' => 60,
        ]);

        $this->expectException(RateLimitException::class);

        $message = AIMessage::user('error_test');
        $this->provider->sendMessage($message);
    }

    /** @test */
    public function response_fixtures_contain_all_providers()
    {
        $fixtures = ResponseFixtures::all();

        $this->assertArrayHasKey('openai', $fixtures);
        $this->assertArrayHasKey('xai', $fixtures);
        $this->assertArrayHasKey('gemini', $fixtures);
        $this->assertArrayHasKey('ollama', $fixtures);
        $this->assertArrayHasKey('generic', $fixtures);
        $this->assertArrayHasKey('errors', $fixtures);
    }

    /** @test */
    public function response_fixtures_can_get_specific_fixture()
    {
        $openaiHello = ResponseFixtures::get('openai', 'hello');

        $this->assertIsArray($openaiHello);
        $this->assertArrayHasKey('content', $openaiHello);
        $this->assertStringContainsString('ChatGPT', $openaiHello['content']);
    }

    /** @test */
    public function it_can_handle_streaming_with_custom_chunk_size()
    {
        $provider = new MockProvider([
            'streaming_chunk_size' => 5,
            'streaming_delay' => 10,
        ]);

        $message = AIMessage::user('This is a longer message for testing streaming');
        $chunks = [];

        foreach ($provider->sendStreamingMessage($message) as $chunk) {
            $chunks[] = $chunk->content;
        }

        $this->assertGreaterThan(5, count($chunks)); // Should be split into multiple chunks
        $fullContent = implode('', $chunks);
        $this->assertNotEmpty($fullContent);
    }
}
