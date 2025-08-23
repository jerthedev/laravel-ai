<?php

namespace JTD\LaravelAI\Tests\Unit\Drivers\Gemini\Support;

use JTD\LaravelAI\Drivers\Gemini\Support\ErrorMapper;
use JTD\LaravelAI\Exceptions\Gemini\GeminiException;
use JTD\LaravelAI\Tests\TestCase;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * ErrorMapper Unit Tests
 *
 * Tests the Gemini error mapping functionality.
 */
#[Group('unit')]
#[Group('gemini')]
#[Group('support')]
class ErrorMapperTest extends TestCase
{
    #[Test]
    public function it_maps_invalid_credentials_exception(): void
    {
        $originalException = new \Exception('Invalid API key provided');

        $mappedException = ErrorMapper::mapException($originalException);

        $this->assertInstanceOf(GeminiException::class, $mappedException);
        $this->assertStringContainsString('Invalid API key', $mappedException->getMessage());
    }

    #[Test]
    public function it_maps_rate_limit_exception(): void
    {
        $originalException = new \Exception('Rate limit exceeded for requests per minute');

        $mappedException = ErrorMapper::mapException($originalException);

        $this->assertInstanceOf(GeminiException::class, $mappedException);
        $this->assertStringContainsString('Rate limit exceeded', $mappedException->getMessage());
    }

    #[Test]
    public function it_maps_safety_exception(): void
    {
        $originalException = new \Exception('Content blocked by safety filters');

        $mappedException = ErrorMapper::mapException($originalException);

        $this->assertInstanceOf(GeminiException::class, $mappedException);
        $this->assertStringContainsString('Content blocked by safety filters', $mappedException->getMessage());
    }

    #[Test]
    public function it_maps_server_exception(): void
    {
        $originalException = new \Exception('Internal server error');

        $mappedException = ErrorMapper::mapException($originalException);

        $this->assertInstanceOf(GeminiException::class, $mappedException);
        $this->assertStringContainsString('Internal server error', $mappedException->getMessage());
    }

    #[Test]
    public function it_extracts_error_info_correctly(): void
    {
        $originalException = new \Exception('Invalid API key provided');

        $errorInfo = ErrorMapper::extractErrorInfo($originalException);

        $this->assertEquals('invalid_api_key', $errorInfo['type']);
        $this->assertEquals('Invalid API key provided', $errorInfo['message']);
    }

    #[Test]
    public function it_identifies_retryable_errors(): void
    {
        $serverErrorException = new \Exception('Server error occurred');
        $this->assertTrue(ErrorMapper::isRetryableError($serverErrorException));

        // Test non-retryable error
        $credentialsException = new \Exception('Invalid API key provided');
        $this->assertFalse(ErrorMapper::isRetryableError($credentialsException));
    }

    #[Test]
    public function it_enhances_error_messages(): void
    {
        $message = 'API key not found';
        $enhanced = ErrorMapper::enhanceErrorMessage($message, 'invalid_api_key');

        $this->assertStringContainsString('Invalid Gemini API key', $enhanced);
        $this->assertStringContainsString($message, $enhanced);
    }

    #[Test]
    public function it_extracts_rate_limit_delay(): void
    {
        $exception = new \Exception('Rate limit exceeded');

        $delay = ErrorMapper::extractRateLimitDelay($exception);

        $this->assertEquals(60000, $delay); // Default delay in milliseconds
    }

    #[Test]
    public function it_gets_retry_config_for_error_types(): void
    {
        $rateLimitConfig = ErrorMapper::getRetryConfig('rate_limit_exceeded');
        $this->assertEquals(5, $rateLimitConfig['max_attempts']);
        $this->assertEquals(60000, $rateLimitConfig['base_delay']);
        $this->assertFalse($rateLimitConfig['use_exponential_backoff']);

        $serverErrorConfig = ErrorMapper::getRetryConfig('server_error');
        $this->assertEquals(3, $serverErrorConfig['max_attempts']);
        $this->assertEquals(1000, $serverErrorConfig['base_delay']);
        $this->assertTrue($serverErrorConfig['use_exponential_backoff']);
    }

    #[Test]
    public function it_falls_back_to_generic_exception(): void
    {
        $originalException = new \Exception('Unknown error');

        $mappedException = ErrorMapper::mapException($originalException);

        $this->assertInstanceOf(GeminiException::class, $mappedException);
        $this->assertStringContainsString('Unknown error', $mappedException->getMessage());
    }

    #[Test]
    public function it_parses_error_message_for_context(): void
    {
        $exception = new \Exception('Rate limit exceeded for requests per minute');

        $errorInfo = ErrorMapper::extractErrorInfo($exception);

        $this->assertEquals('rate_limit_exceeded', $errorInfo['type']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
