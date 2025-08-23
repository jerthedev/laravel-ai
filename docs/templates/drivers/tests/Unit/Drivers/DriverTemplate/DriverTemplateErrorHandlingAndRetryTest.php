<?php

namespace JTD\LaravelAI\Tests\Unit;

use JTD\LaravelAI\Drivers\DriverTemplate\DriverTemplateDriver;
use JTD\LaravelAI\Exceptions\DriverTemplate\DriverTemplateException;
use JTD\LaravelAI\Exceptions\DriverTemplate\DriverTemplateInvalidCredentialsException;
use JTD\LaravelAI\Exceptions\DriverTemplate\DriverTemplateQuotaExceededException;
use JTD\LaravelAI\Exceptions\DriverTemplate\DriverTemplateRateLimitException;
use JTD\LaravelAI\Exceptions\DriverTemplate\DriverTemplateServerException;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Tests\TestCase;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Comprehensive Error Handling and Retry Logic Tests
 *
 * Tests all error scenarios, retry logic, exponential backoff,
 * rate limit handling, and exception mapping for DriverTemplate driver.
 */
#[Group('unit')]
#[Group('drivertemplate')]
#[Group('error-handling')]
#[Group('retry')]
class DriverTemplateErrorHandlingAndRetryTest extends TestCase
{
    private DriverTemplateDriver $driver;

    private $mockClient;

    protected function setUp(): void
    {
        parent::setUp();

        // Create fresh mocks for each test to avoid interference
        $this->createFreshDriver();
    }

    private function createFreshDriver(): void
    {
        $this->mockClient = Mockery::mock();
        $this->driver = new DriverTemplateDriver([
            'api_key' => 'api-key-test-key-for-unit-tests',
            'timeout' => 30,
            'retry_attempts' => 3,
            'retry_delay' => 1, // 1 millisecond for fast tests
            'max_retry_delay' => 10, // 10 milliseconds max for fast tests
        ]);
        $this->driver->setClient($this->mockClient);
    }

    #[Test]
    #[Group('exception-mapping')]
    public function it_maps_invalid_api_key_error_to_credentials_exception(): void
    {
        $mockChatResource = Mockery::mock();
        $mockChatResource->shouldReceive('create')
            ->once()
            ->andThrow(new \Exception('Invalid API key provided'));

        $this->mockClient->shouldReceive('chat')
            ->once()
            ->andReturn($mockChatResource);

        $this->expectException(DriverTemplateInvalidCredentialsException::class);
        $this->expectExceptionMessage('Invalid DriverTemplate API key');

        $message = AIMessage::user('Test message');
        $this->driver->sendMessage($message, ['model' => 'default-model-3.5-turbo']);
    }

    #[Test]
    #[Group('exception-mapping')]
    public function it_maps_rate_limit_error_to_rate_limit_exception(): void
    {
        $mockChatResource = Mockery::mock();
        $mockChatResource->shouldReceive('create')
            ->once()
            ->andThrow(new \Exception('Rate limit exceeded'));

        $this->mockClient->shouldReceive('chat')
            ->once()
            ->andReturn($mockChatResource);

        $this->expectException(DriverTemplateRateLimitException::class);
        $this->expectExceptionMessage('rate limit exceeded');

        $message = AIMessage::user('Test message');
        $this->driver->sendMessage($message, ['model' => 'default-model-3.5-turbo']);
    }

    #[Test]
    #[Group('exception-mapping')]
    public function it_maps_quota_exceeded_error_to_quota_exception(): void
    {
        $mockChatResource = Mockery::mock();
        $mockChatResource->shouldReceive('create')
            ->once()
            ->andThrow(new \Exception('You exceeded your current quota'));

        $this->mockClient->shouldReceive('chat')
            ->once()
            ->andReturn($mockChatResource);

        $this->expectException(DriverTemplateQuotaExceededException::class);
        $this->expectExceptionMessage('quota exceeded');

        $message = AIMessage::user('Test message');
        $this->driver->sendMessage($message, ['model' => 'default-model-3.5-turbo']);
    }

    #[Test]
    #[Group('exception-mapping')]
    public function it_maps_server_error_to_server_exception(): void
    {
        // Create driver with no retries to test just the exception mapping
        $noRetryDriver = new DriverTemplateDriver([
            'api_key' => 'api-key-test-key-for-unit-tests',
            'retry_attempts' => 1, // No retries
        ]);
        $mockClient = Mockery::mock();
        $noRetryDriver->setClient($mockClient);

        $mockChatResource = Mockery::mock();
        $mockChatResource->shouldReceive('create')
            ->once()
            ->andThrow(new \Exception('Internal server error'));

        $mockClient->shouldReceive('chat')
            ->once()
            ->andReturn($mockChatResource);

        $this->expectException(DriverTemplateServerException::class);
        $this->expectExceptionMessage('server error');

        $message = AIMessage::user('Test message');
        $noRetryDriver->sendMessage($message, ['model' => 'default-model-3.5-turbo']);
    }

    #[Test]
    #[Group('retry-logic')]
    public function it_retries_on_retryable_errors(): void
    {
        $mockChatResource = Mockery::mock();

        // First two attempts fail with retryable error
        $mockChatResource->shouldReceive('create')
            ->twice()
            ->andThrow(new \Exception('Service unavailable'));

        // Third attempt succeeds
        $mockChatResource->shouldReceive('create')
            ->once()
            ->andReturn($this->createMockResponse());

        $this->mockClient->shouldReceive('chat')
            ->times(3)
            ->andReturn($mockChatResource);

        $message = AIMessage::user('Test message');
        $response = $this->driver->sendMessage($message, ['model' => 'default-model-3.5-turbo']);

        $this->assertNotNull($response);
        $this->assertEquals('Test response', $response->content);
    }

    #[Test]
    #[Group('retry-logic')]
    public function it_does_not_retry_non_retryable_errors(): void
    {
        $mockChatResource = Mockery::mock();
        $mockChatResource->shouldReceive('create')
            ->once() // Should only be called once, no retries
            ->andThrow(new \Exception('Invalid API key provided'));

        $this->mockClient->shouldReceive('chat')
            ->once()
            ->andReturn($mockChatResource);

        $this->expectException(DriverTemplateInvalidCredentialsException::class);

        $message = AIMessage::user('Test message');
        $this->driver->sendMessage($message, ['model' => 'default-model-3.5-turbo']);
    }

    #[Test]
    #[Group('retry-logic')]
    public function it_respects_maximum_retry_attempts(): void
    {
        $mockChatResource = Mockery::mock();

        // All attempts fail with retryable error
        $mockChatResource->shouldReceive('create')
            ->times(3) // Should try exactly 3 times (max attempts)
            ->andThrow(new \Exception('Service unavailable'));

        $this->mockClient->shouldReceive('chat')
            ->times(3)
            ->andReturn($mockChatResource);

        $this->expectException(DriverTemplateServerException::class);

        $message = AIMessage::user('Test message');
        $this->driver->sendMessage($message, ['model' => 'default-model-3.5-turbo']);
    }

    #[Test]
    #[Group('exponential-backoff')]
    public function it_calculates_exponential_backoff_delay(): void
    {
        $reflection = new \ReflectionClass($this->driver);
        $method = $reflection->getMethod('calculateRetryDelay');
        $method->setAccessible(true);

        $baseDelay = 1; // 1 millisecond
        $maxDelay = 10; // 10 milliseconds
        $exception = new \Exception('Service unavailable');

        // Test exponential backoff for different attempts
        $delay1 = $method->invoke($this->driver, 1, $baseDelay, $maxDelay, $exception);
        $delay2 = $method->invoke($this->driver, 2, $baseDelay, $maxDelay, $exception);
        $delay3 = $method->invoke($this->driver, 3, $baseDelay, $maxDelay, $exception);

        // Delays should increase exponentially (with jitter)
        $this->assertGreaterThan(0, $delay1);
        $this->assertGreaterThan($delay1 * 0.5, $delay2); // Account for jitter
        $this->assertGreaterThan($delay2 * 0.5, $delay3); // Account for jitter

        // Should not exceed max delay
        $this->assertLessThanOrEqual($maxDelay, $delay1);
        $this->assertLessThanOrEqual($maxDelay, $delay2);
        $this->assertLessThanOrEqual($maxDelay, $delay3);
    }

    #[Test]
    #[Group('rate-limit')]
    public function it_handles_rate_limit_with_retry_after_header(): void
    {
        // This test would need to mock HTTP response headers
        // For now, test the basic rate limit error mapping
        $mockChatResource = Mockery::mock();
        $mockChatResource->shouldReceive('create')
            ->once()
            ->andThrow(new \Exception('Rate limit exceeded. Please retry after 60 seconds'));

        $this->mockClient->shouldReceive('chat')
            ->once()
            ->andReturn($mockChatResource);

        $this->expectException(DriverTemplateRateLimitException::class);

        $message = AIMessage::user('Test message');
        $this->driver->sendMessage($message, ['model' => 'default-model-3.5-turbo']);
    }

    #[Test]
    #[Group('fail-fast')]
    public function it_fails_fast_on_authentication_errors(): void
    {
        $mockChatResource = Mockery::mock();
        $mockChatResource->shouldReceive('create')
            ->once() // Should not retry authentication errors
            ->andThrow(new \Exception('Authentication failed'));

        $this->mockClient->shouldReceive('chat')
            ->once()
            ->andReturn($mockChatResource);

        $this->expectException(DriverTemplateInvalidCredentialsException::class);

        $message = AIMessage::user('Test message');
        $this->driver->sendMessage($message, ['model' => 'default-model-3.5-turbo']);
    }

    #[Test]
    #[Group('fail-fast')]
    public function it_fails_fast_on_invalid_request_errors(): void
    {
        $mockChatResource = Mockery::mock();
        $mockChatResource->shouldReceive('create')
            ->once() // Should not retry invalid request errors
            ->andThrow(new \Exception('Invalid request format'));

        $this->mockClient->shouldReceive('chat')
            ->once()
            ->andReturn($mockChatResource);

        $this->expectException(DriverTemplateException::class);

        $message = AIMessage::user('Test message');
        $this->driver->sendMessage($message, ['model' => 'default-model-3.5-turbo']);
    }

    #[Test]
    #[Group('timeout')]
    public function it_handles_timeout_errors(): void
    {
        $mockChatResource = Mockery::mock();
        $mockChatResource->shouldReceive('create')
            ->once()
            ->andThrow(new \Exception('Request timeout'));

        $this->mockClient->shouldReceive('chat')
            ->once()
            ->andReturn($mockChatResource);

        $this->expectException(DriverTemplateException::class);
        $this->expectExceptionMessage('timeout');

        $message = AIMessage::user('Test message');
        $this->driver->sendMessage($message, ['model' => 'default-model-3.5-turbo']);
    }

    #[Test]
    #[Group('configuration')]
    public function it_uses_custom_retry_configuration(): void
    {
        // Create driver with custom retry config
        $customDriver = new DriverTemplateDriver([
            'api_key' => 'api-key-test-key',
            'retry_attempts' => 5,
            'retry_delay' => 1, // 1 millisecond for fast tests
            'max_retry_delay' => 5, // 5 milliseconds max for fast tests
        ]);
        $customDriver->setClient($this->mockClient);

        $mockChatResource = Mockery::mock();

        // Should retry 5 times with custom config
        $mockChatResource->shouldReceive('create')
            ->times(5)
            ->andThrow(new \Exception('Service unavailable'));

        $this->mockClient->shouldReceive('chat')
            ->times(5)
            ->andReturn($mockChatResource);

        $this->expectException(DriverTemplateServerException::class);

        $message = AIMessage::user('Test message');
        $customDriver->sendMessage($message, ['model' => 'default-model-3.5-turbo']);
    }

    #[Test]
    #[Group('error-context')]
    public function it_preserves_error_context_in_exceptions(): void
    {
        $mockChatResource = Mockery::mock();
        $originalException = new \Exception('Original API error message');
        $mockChatResource->shouldReceive('create')
            ->once()
            ->andThrow($originalException);

        $this->mockClient->shouldReceive('chat')
            ->once()
            ->andReturn($mockChatResource);

        try {
            $message = AIMessage::user('Test message');
            $this->driver->sendMessage($message, ['model' => 'default-model-3.5-turbo']);
            $this->fail('Expected exception was not thrown');
        } catch (DriverTemplateException $e) {
            // Should preserve original exception as previous
            $this->assertSame($originalException, $e->getPrevious());
            $this->assertStringContainsString('Original API error message', $e->getMessage());
        }
    }

    #[Test]
    #[Group('streaming-errors')]
    public function it_handles_streaming_errors_with_retry(): void
    {
        $mockChatResource = Mockery::mock();

        // First attempt fails, second succeeds
        $mockChatResource->shouldReceive('createStreamed')
            ->once()
            ->andThrow(new \Exception('Service unavailable'));

        $mockChatResource->shouldReceive('createStreamed')
            ->once()
            ->andReturn($this->createMockStreamResponse());

        $this->mockClient->shouldReceive('chat')
            ->twice()
            ->andReturn($mockChatResource);

        $message = AIMessage::user('Test message');
        $chunks = [];

        foreach ($this->driver->sendStreamingMessage($message, ['model' => 'default-model-3.5-turbo']) as $chunk) {
            $chunks[] = $chunk;
        }

        $this->assertNotEmpty($chunks);
    }

    #[Test]
    #[Group('models-api-errors')]
    public function it_handles_models_api_errors_with_retry(): void
    {
        $mockModelsResource = Mockery::mock();

        // First attempt fails, second succeeds
        $mockModelsResource->shouldReceive('list')
            ->once()
            ->andThrow(new \Exception('Service unavailable'));

        $mockModelsResource->shouldReceive('list')
            ->once()
            ->andReturn($this->createMockModelsResponse());

        $this->mockClient->shouldReceive('models')
            ->twice()
            ->andReturn($mockModelsResource);

        $models = $this->driver->getAvailableModels();
        $this->assertNotEmpty($models);
    }

    #[Test]
    #[Group('validation-errors')]
    public function it_handles_credential_validation_errors(): void
    {
        $mockModelsResource = Mockery::mock();
        $mockModelsResource->shouldReceive('list')
            ->once()
            ->andThrow(new \Exception('Invalid API key provided'));

        $this->mockClient->shouldReceive('models')
            ->once()
            ->andReturn($mockModelsResource);

        $result = $this->driver->validateCredentials();

        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
        $this->assertStringContainsString('Invalid credentials', $result['errors'][0]);
    }

    /**
     * Create a mock DriverTemplate API response for successful requests.
     */
    private function createMockResponse()
    {
        $mockResponse = Mockery::mock();

        // Mock the choices property
        $mockResponse->choices = [
            (object) [
                'message' => (object) [
                    'content' => 'Test response',
                    'role' => 'assistant',
                ],
                'finishReason' => 'stop',
            ],
        ];

        // Mock the usage property
        $mockResponse->usage = (object) [
            'promptTokens' => 10,
            'completionTokens' => 5,
            'totalTokens' => 15,
        ];

        // Mock the model property
        $mockResponse->model = 'default-model-3.5-turbo';

        return $mockResponse;
    }

    /**
     * Create a mock streaming response.
     */
    private function createMockStreamResponse()
    {
        $mockStream = Mockery::mock();
        $mockStream->shouldReceive('getIterator')
            ->andReturn(new \ArrayIterator([
                (object) [
                    'choices' => [
                        (object) [
                            'delta' => (object) ['content' => 'Test'],
                            'finish_reason' => null,
                        ],
                    ],
                ],
                (object) [
                    'choices' => [
                        (object) [
                            'delta' => (object) ['content' => ' response'],
                            'finish_reason' => 'stop',
                        ],
                    ],
                ],
            ]));

        return $mockStream;
    }

    /**
     * Create a mock models API response.
     */
    private function createMockModelsResponse()
    {
        $mockResponse = Mockery::mock();
        $mockResponse->data = [
            (object) [
                'id' => 'default-model-3.5-turbo',
                'object' => 'model',
                'created' => time(),
                'owned_by' => 'drivertemplate',
            ],
            (object) [
                'id' => 'default-model-4',
                'object' => 'model',
                'created' => time(),
                'owned_by' => 'drivertemplate',
            ],
        ];

        return $mockResponse;
    }

    #[Test]
    #[DataProvider('errorMappingProvider')]
    #[Group('exception-mapping')]
    public function it_maps_various_error_types_correctly(string $errorMessage, string $expectedExceptionClass): void
    {
        $mockChatResource = Mockery::mock();
        $mockChatResource->shouldReceive('create')
            ->once()
            ->andThrow(new \Exception($errorMessage));

        $this->mockClient->shouldReceive('chat')
            ->once()
            ->andReturn($mockChatResource);

        $this->expectException($expectedExceptionClass);

        $message = AIMessage::user('Test message');
        $this->driver->sendMessage($message, ['model' => 'default-model-3.5-turbo']);
    }

    public static function errorMappingProvider(): array
    {
        return [
            'invalid_api_key' => ['Invalid API key provided', DriverTemplateInvalidCredentialsException::class],
            'authentication_error' => ['Authentication failed', DriverTemplateInvalidCredentialsException::class],
            'rate_limit_exceeded' => ['Rate limit exceeded', DriverTemplateRateLimitException::class],
            'quota_exceeded' => ['You exceeded your current quota', DriverTemplateQuotaExceededException::class],
            'insufficient_quota' => ['Insufficient quota', DriverTemplateQuotaExceededException::class],
            'server_error' => ['Internal server error', DriverTemplateServerException::class],
            'service_unavailable' => ['Service temporarily unavailable', DriverTemplateServerException::class],
            'timeout' => ['Request timeout', DriverTemplateException::class],
            'unknown_error' => ['Unknown error occurred', DriverTemplateException::class],
        ];
    }

    #[Test]
    #[DataProvider('retryableErrorProvider')]
    #[Group('retry-logic')]
    public function it_identifies_retryable_errors_correctly(string $errorMessage, bool $shouldRetry): void
    {
        $mockChatResource = Mockery::mock();

        if ($shouldRetry) {
            // Should retry multiple times
            $mockChatResource->shouldReceive('create')
                ->times(3)
                ->andThrow(new \Exception($errorMessage));
        } else {
            // Should not retry
            $mockChatResource->shouldReceive('create')
                ->once()
                ->andThrow(new \Exception($errorMessage));
        }

        $this->mockClient->shouldReceive('chat')
            ->times($shouldRetry ? 3 : 1)
            ->andReturn($mockChatResource);

        $this->expectException(DriverTemplateException::class);

        $message = AIMessage::user('Test message');
        $this->driver->sendMessage($message, ['model' => 'default-model-3.5-turbo']);
    }

    public static function retryableErrorProvider(): array
    {
        return [
            'server_error_retryable' => ['Internal server error', true],
            'service_unavailable_retryable' => ['Service unavailable', true],
            'timeout_retryable' => ['Request timeout', true],
            'rate_limit_retryable' => ['Rate limit exceeded', true],
            'invalid_api_key_not_retryable' => ['Invalid API key provided', false],
            'authentication_not_retryable' => ['Authentication failed', false],
            'invalid_request_not_retryable' => ['Invalid request format', false],
            'permission_error_not_retryable' => ['Permission denied', false],
        ];
    }

    #[Test]
    #[Group('jitter')]
    public function it_applies_jitter_to_retry_delays(): void
    {
        $reflection = new \ReflectionClass($this->driver);
        $method = $reflection->getMethod('calculateRetryDelay');
        $method->setAccessible(true);

        $baseDelay = 1000;
        $maxDelay = 30000;
        $exception = new \Exception('Service unavailable');

        // Calculate multiple delays for the same attempt to test jitter
        $delays = [];
        for ($i = 0; $i < 10; $i++) {
            $delays[] = $method->invoke($this->driver, 2, $baseDelay, $maxDelay, $exception);
        }

        // With jitter, delays should vary
        $uniqueDelays = array_unique($delays);
        $this->assertGreaterThan(1, count($uniqueDelays), 'Jitter should produce varying delays');

        // All delays should be within reasonable bounds
        foreach ($delays as $delay) {
            $this->assertGreaterThan(0, $delay);
            $this->assertLessThanOrEqual($maxDelay, $delay);
        }
    }

    #[Test]
    #[Group('error-enhancement')]
    public function it_enhances_error_messages_with_helpful_context(): void
    {
        $mockChatResource = Mockery::mock();
        $mockChatResource->shouldReceive('create')
            ->once()
            ->andThrow(new \Exception('Invalid API key'));

        $this->mockClient->shouldReceive('chat')
            ->once()
            ->andReturn($mockChatResource);

        try {
            $message = AIMessage::user('Test message');
            $this->driver->sendMessage($message, ['model' => 'default-model-3.5-turbo']);
            $this->fail('Expected exception was not thrown');
        } catch (DriverTemplateInvalidCredentialsException $e) {
            // Should contain enhanced message with helpful context
            $message = $e->getMessage();
            $this->assertStringContainsString('Invalid DriverTemplate API key', $message);
            $this->assertStringContainsString('Please check your API key configuration', $message);
        }
    }

    #[Test]
    #[Group('network-errors')]
    public function it_handles_network_connection_errors(): void
    {
        $mockChatResource = Mockery::mock();
        $mockChatResource->shouldReceive('create')
            ->once()
            ->andThrow(new \Exception('Connection refused'));

        $this->mockClient->shouldReceive('chat')
            ->once()
            ->andReturn($mockChatResource);

        $this->expectException(DriverTemplateException::class);
        $this->expectExceptionMessage('Connection refused');

        $message = AIMessage::user('Test message');
        $this->driver->sendMessage($message, ['model' => 'default-model-3.5-turbo']);
    }

    #[Test]
    #[Group('malformed-response')]
    public function it_handles_malformed_api_responses(): void
    {
        $mockChatResource = Mockery::mock();
        $mockChatResource->shouldReceive('create')
            ->once()
            ->andThrow(new \Exception('Invalid JSON response'));

        $this->mockClient->shouldReceive('chat')
            ->once()
            ->andReturn($mockChatResource);

        $this->expectException(DriverTemplateException::class);
        $this->expectExceptionMessage('Invalid JSON response');

        $message = AIMessage::user('Test message');
        $this->driver->sendMessage($message, ['model' => 'default-model-3.5-turbo']);
    }
}
