<?php

namespace JTD\LaravelAI\Tests\Unit;

use Illuminate\Validation\ValidationException;
use JTD\LaravelAI\Exceptions\CostCalculationException;
use JTD\LaravelAI\Exceptions\InvalidCredentialsException;
use JTD\LaravelAI\Exceptions\ModelNotFoundException;
use JTD\LaravelAI\Exceptions\ProviderException;
use JTD\LaravelAI\Exceptions\ProviderNotFoundException;
use JTD\LaravelAI\Exceptions\RateLimitException;
use JTD\LaravelAI\Exceptions\StreamingNotSupportedException;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Models\AIResponse;
use JTD\LaravelAI\Models\TokenUsage;
use JTD\LaravelAI\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class InterfacesAndModelsTest extends TestCase
{
    #[Test]
    public function it_can_create_user_message()
    {
        $message = AIMessage::user('Hello, how are you?');

        $this->assertEquals(AIMessage::ROLE_USER, $message->role);
        $this->assertEquals('Hello, how are you?', $message->content);
        $this->assertEquals(AIMessage::CONTENT_TYPE_TEXT, $message->contentType);
        $this->assertInstanceOf(\DateTime::class, $message->timestamp);
    }

    #[Test]
    public function it_can_create_system_message()
    {
        $message = AIMessage::system('You are a helpful assistant.');

        $this->assertEquals(AIMessage::ROLE_SYSTEM, $message->role);
        $this->assertEquals('You are a helpful assistant.', $message->content);
    }

    #[Test]
    public function it_can_create_assistant_message_with_function_calls()
    {
        $functionCalls = [
            ['name' => 'get_weather', 'arguments' => ['location' => 'New York']],
        ];

        $message = AIMessage::assistant('I can help with that.', $functionCalls);

        $this->assertEquals(AIMessage::ROLE_ASSISTANT, $message->role);
        $this->assertEquals('I can help with that.', $message->content);
        $this->assertEquals($functionCalls, $message->functionCalls);
        $this->assertTrue($message->hasFunctionCalls());
    }

    #[Test]
    public function it_validates_message_data()
    {
        $this->expectException(ValidationException::class);

        new AIMessage('invalid_role', 'content');
    }

    #[Test]
    public function it_can_convert_message_to_array()
    {
        $message = AIMessage::user('Hello', AIMessage::CONTENT_TYPE_TEXT, [
            ['type' => 'image', 'url' => 'https://example.com/image.jpg'],
        ]);

        $array = $message->toArray();

        $this->assertArrayHasKey('role', $array);
        $this->assertArrayHasKey('content', $array);
        $this->assertArrayHasKey('content_type', $array);
        $this->assertArrayHasKey('attachments', $array);
        $this->assertArrayHasKey('timestamp', $array);
    }

    #[Test]
    public function it_can_create_message_from_array()
    {
        $data = [
            'role' => 'user',
            'content' => 'Hello',
            'content_type' => 'text',
            'timestamp' => '2025-08-21T10:00:00+00:00',
        ];

        $message = AIMessage::fromArray($data);

        $this->assertEquals('user', $message->role);
        $this->assertEquals('Hello', $message->content);
        $this->assertEquals('text', $message->contentType);
    }

    #[Test]
    public function it_estimates_token_count()
    {
        $message = AIMessage::user('This is a test message for token estimation.');

        $tokenCount = $message->getEstimatedTokenCount();

        $this->assertIsInt($tokenCount);
        $this->assertGreaterThan(0, $tokenCount);
    }

    #[Test]
    public function it_can_create_token_usage()
    {
        $usage = TokenUsage::create(100, 50);

        $this->assertEquals(100, $usage->input_tokens);
        $this->assertEquals(50, $usage->output_tokens);
        $this->assertEquals(150, $usage->totalTokens);
    }

    #[Test]
    public function it_can_create_token_usage_with_costs()
    {
        $usage = TokenUsage::withCosts(100, 50, 0.001, 0.002, 'USD');

        $this->assertEquals(0.001, $usage->inputCost);
        $this->assertEquals(0.002, $usage->outputCost);
        $this->assertEquals(0.003, $usage->totalCost);
        $this->assertEquals('USD', $usage->currency);
    }

    #[Test]
    public function it_can_calculate_costs_from_rates()
    {
        $usage = TokenUsage::create(1000, 500)
            ->calculateCosts(0.01, 0.02); // $0.01 per 1K input, $0.02 per 1K output

        $this->assertEquals(0.01, $usage->inputCost);
        $this->assertEquals(0.01, $usage->outputCost);
        $this->assertEquals(0.02, $usage->totalCost);
        $this->assertTrue($usage->hasCosts());
    }

    #[Test]
    public function it_can_add_token_usage()
    {
        $usage1 = TokenUsage::withCosts(100, 50, 0.001, 0.002);
        $usage2 = TokenUsage::withCosts(200, 100, 0.002, 0.004);

        $combined = $usage1->add($usage2);

        $this->assertEquals(300, $combined->input_tokens);
        $this->assertEquals(150, $combined->output_tokens);
        $this->assertEquals(450, $combined->totalTokens);
        $this->assertEquals(0.009, round($combined->totalCost, 3));
    }

    #[Test]
    public function it_formats_token_usage_cost()
    {
        $usage = TokenUsage::withCosts(100, 50, 0.001, 0.002);

        $formatted = $usage->formatTotalCost(4);

        $this->assertEquals('0.0030 USD', $formatted);
    }

    #[Test]
    public function it_provides_token_usage_summary()
    {
        $usage = TokenUsage::withCosts(100, 50, 0.001, 0.002);

        $summary = $usage->getSummary();

        $this->assertStringContainsString('150 tokens', $summary);
        $this->assertStringContainsString('100 input', $summary);
        $this->assertStringContainsString('50 output', $summary);
        $this->assertStringContainsString('0.0030 USD', $summary);
    }

    #[Test]
    public function it_can_create_successful_ai_response()
    {
        $tokenUsage = TokenUsage::create(100, 50);
        $response = AIResponse::success('Hello there!', $tokenUsage, 'gpt-4', 'openai');

        $this->assertEquals('Hello there!', $response->content);
        $this->assertEquals('gpt-4', $response->model);
        $this->assertEquals('openai', $response->provider);
        $this->assertEquals(AIMessage::ROLE_ASSISTANT, $response->role);
        $this->assertEquals(AIResponse::FINISH_REASON_STOP, $response->finishReason);
        $this->assertTrue($response->isSuccessful());
    }

    #[Test]
    public function it_can_create_streaming_response_chunk()
    {
        $response = AIResponse::streamingChunk('Hello', 'gpt-4', 'openai', false);

        $this->assertEquals('Hello', $response->content);
        $this->assertTrue($response->isStreaming);
        $this->assertFalse($response->metadata['is_complete']);
    }

    #[Test]
    public function it_can_create_error_response()
    {
        $response = AIResponse::error('Something went wrong', 'gpt-4', 'openai');

        $this->assertEquals('Something went wrong', $response->content);
        $this->assertEquals('error', $response->role);
        $this->assertEquals(AIResponse::FINISH_REASON_ERROR, $response->finishReason);
        $this->assertFalse($response->isSuccessful());
    }

    #[Test]
    public function it_can_convert_response_to_message()
    {
        $tokenUsage = TokenUsage::create(100, 50);
        $response = AIResponse::success('Hello!', $tokenUsage, 'gpt-4', 'openai');

        $message = $response->toMessage();

        $this->assertInstanceOf(AIMessage::class, $message);
        $this->assertEquals('Hello!', $message->content);
        $this->assertEquals(AIMessage::ROLE_ASSISTANT, $message->role);
    }

    #[Test]
    public function rate_limit_exception_provides_retry_info()
    {
        $exception = new RateLimitException(
            'Rate limit exceeded',
            60, // rate limit
            0,  // remaining
            30, // reset time
            'requests'
        );

        $this->assertEquals(30, $exception->getWaitTime());
        $this->assertTrue($exception->canRetry());
        $this->assertEquals(60, $exception->rateLimit);
        $this->assertEquals('requests', $exception->limitType);
    }

    #[Test]
    public function invalid_credentials_exception_provides_context()
    {
        $exception = new InvalidCredentialsException(
            'Invalid API key',
            'openai',
            'account-123',
            ['error_code' => 'invalid_api_key']
        );

        $this->assertEquals('openai', $exception->getProvider());
        $this->assertEquals('account-123', $exception->getAccount());
        $this->assertEquals(['error_code' => 'invalid_api_key'], $exception->getDetails());
    }

    #[Test]
    public function provider_exception_indicates_retryability()
    {
        $retryableException = new ProviderException(
            'Temporary error',
            'openai',
            'timeout',
            [],
            true // retryable
        );

        $nonRetryableException = new ProviderException(
            'Invalid request',
            'openai',
            'validation',
            [],
            false // not retryable
        );

        $this->assertTrue($retryableException->isRetryable());
        $this->assertFalse($nonRetryableException->isRetryable());
    }

    #[Test]
    public function model_not_found_exception_provides_alternatives()
    {
        $exception = new ModelNotFoundException(
            'Model not found',
            'gpt-5',
            'openai',
            ['gpt-4', 'gpt-3.5-turbo']
        );

        $this->assertEquals('gpt-5', $exception->getModel());
        $this->assertEquals('openai', $exception->getProvider());
        $this->assertEquals(['gpt-4', 'gpt-3.5-turbo'], $exception->getAvailableModels());
    }

    #[Test]
    public function streaming_not_supported_exception_provides_context()
    {
        $exception = new StreamingNotSupportedException(
            'Streaming not supported',
            'custom-provider',
            'custom-model'
        );

        $this->assertEquals('custom-provider', $exception->getProvider());
        $this->assertEquals('custom-model', $exception->getModel());
    }

    #[Test]
    public function cost_calculation_exception_provides_details()
    {
        $exception = new CostCalculationException(
            'Cost calculation failed',
            'openai',
            'gpt-4',
            ['reason' => 'missing_pricing_data']
        );

        $this->assertEquals('openai', $exception->getProvider());
        $this->assertEquals('gpt-4', $exception->getModel());
        $this->assertEquals(['reason' => 'missing_pricing_data'], $exception->getDetails());
    }

    #[Test]
    public function provider_not_found_exception_lists_alternatives()
    {
        $exception = new ProviderNotFoundException(
            'Provider not found',
            'unknown-provider',
            ['openai', 'xai', 'gemini']
        );

        $this->assertEquals('unknown-provider', $exception->getProvider());
        $this->assertEquals(['openai', 'xai', 'gemini'], $exception->getAvailableProviders());
    }

    #[Test]
    public function interfaces_exist_and_are_properly_defined()
    {
        $this->assertTrue(interface_exists('JTD\LaravelAI\Contracts\AIProviderInterface'));
        $this->assertTrue(interface_exists('JTD\LaravelAI\Contracts\ConversationBuilderInterface'));
    }
}
