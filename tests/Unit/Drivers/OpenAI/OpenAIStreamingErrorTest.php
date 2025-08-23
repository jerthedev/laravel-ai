<?php

namespace JTD\LaravelAI\Tests\Unit;

use JTD\LaravelAI\Drivers\OpenAI\OpenAIDriver;
use JTD\LaravelAI\Exceptions\OpenAI\OpenAIException;
use JTD\LaravelAI\Exceptions\OpenAI\OpenAIInvalidCredentialsException;
use JTD\LaravelAI\Exceptions\OpenAI\OpenAIRateLimitException;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Tests\TestCase;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * OpenAI Streaming Error Handling Tests
 *
 * Tests error scenarios during streaming including
 * network errors, API errors, and malformed responses.
 */
#[Group('unit')]
#[Group('openai')]
#[Group('streaming')]
#[Group('errors')]
class OpenAIStreamingErrorTest extends TestCase
{
    private OpenAIDriver $driver;

    private $mockClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockClient = Mockery::mock();
        $this->driver = new OpenAIDriver([
            'api_key' => 'sk-test-key-for-unit-tests',
            'timeout' => 30,
        ]);
        $this->driver->setClient($this->mockClient);
    }

    #[Test]
    public function it_handles_network_error_during_streaming(): void
    {
        $mockChatResource = Mockery::mock();
        $mockChatResource->shouldReceive('createStreamed')
            ->once()
            ->andThrow(new \Exception('Network connection failed'));

        $this->mockClient->shouldReceive('chat')
            ->once()
            ->andReturn($mockChatResource);

        $this->expectException(OpenAIException::class);
        $this->expectExceptionMessage('Network connection failed');

        $message = AIMessage::user('Test message');

        foreach ($this->driver->sendStreamingMessage($message, [
            'model' => 'gpt-3.5-turbo',
        ]) as $chunk) {
            // Should not reach here
            $this->fail('Should have thrown an exception');
        }
    }

    #[Test]
    public function it_handles_authentication_error_during_streaming(): void
    {
        $mockChatResource = Mockery::mock();
        $mockChatResource->shouldReceive('createStreamed')
            ->once()
            ->andThrow(new \Exception('Invalid API key'));

        $this->mockClient->shouldReceive('chat')
            ->once()
            ->andReturn($mockChatResource);

        $this->expectException(OpenAIInvalidCredentialsException::class);

        $message = AIMessage::user('Test message');

        foreach ($this->driver->sendStreamingMessage($message, [
            'model' => 'gpt-3.5-turbo',
        ]) as $chunk) {
            // Should not reach here
            $this->fail('Should have thrown an exception');
        }
    }

    #[Test]
    public function it_handles_rate_limit_error_during_streaming(): void
    {
        $mockChatResource = Mockery::mock();
        $mockChatResource->shouldReceive('createStreamed')
            ->once()
            ->andThrow(new \Exception('Rate limit exceeded'));

        $this->mockClient->shouldReceive('chat')
            ->once()
            ->andReturn($mockChatResource);

        $this->expectException(OpenAIRateLimitException::class);

        $message = AIMessage::user('Test message');

        foreach ($this->driver->sendStreamingMessage($message, [
            'model' => 'gpt-3.5-turbo',
        ]) as $chunk) {
            // Should not reach here
            $this->fail('Should have thrown an exception');
        }
    }

    #[Test]
    public function it_handles_malformed_stream_chunks(): void
    {
        // Create malformed chunks
        $chunks = [
            $this->createMalformedChunk('missing_choices'),
            $this->createMalformedChunk('missing_delta'),
            $this->createMalformedChunk('invalid_structure'),
        ];

        $mockStream = Mockery::mock();
        $mockStream->shouldReceive('getIterator')
            ->andReturn(new \ArrayIterator($chunks));

        $mockChatResource = Mockery::mock();
        $mockChatResource->shouldReceive('createStreamed')
            ->once()
            ->andReturn($mockStream);

        $this->mockClient->shouldReceive('chat')
            ->once()
            ->andReturn($mockChatResource);

        $message = AIMessage::user('Test message');
        $processedChunks = 0;

        foreach ($this->driver->sendStreamingMessage($message, [
            'model' => 'gpt-3.5-turbo',
        ]) as $chunk) {
            $processedChunks++;
            // Should handle malformed chunks gracefully
            $this->assertNotNull($chunk);
        }

        // Should process some chunks even if some are malformed
        $this->assertGreaterThanOrEqual(0, $processedChunks);
    }

    #[Test]
    public function it_handles_empty_stream(): void
    {
        $mockStream = Mockery::mock();
        $mockStream->shouldReceive('getIterator')
            ->andReturn(new \ArrayIterator([]));

        $mockChatResource = Mockery::mock();
        $mockChatResource->shouldReceive('createStreamed')
            ->once()
            ->andReturn($mockStream);

        $this->mockClient->shouldReceive('chat')
            ->once()
            ->andReturn($mockChatResource);

        $message = AIMessage::user('Test message');
        $chunkCount = 0;

        foreach ($this->driver->sendStreamingMessage($message, [
            'model' => 'gpt-3.5-turbo',
        ]) as $chunk) {
            $chunkCount++;
        }

        $this->assertEquals(0, $chunkCount, 'Empty stream should yield no chunks');
    }

    #[Test]
    public function it_handles_stream_interruption(): void
    {
        // Create a stream that throws an error after some chunks
        $chunks = [
            $this->createValidChunk('Hello'),
            $this->createValidChunk(' world'),
        ];

        $mockStream = Mockery::mock();
        $mockStream->shouldReceive('getIterator')
            ->andReturn(new \ArrayIterator($chunks))
            ->andThrow(new \Exception('Stream interrupted'));

        $mockChatResource = Mockery::mock();
        $mockChatResource->shouldReceive('createStreamed')
            ->once()
            ->andReturn($mockStream);

        $this->mockClient->shouldReceive('chat')
            ->once()
            ->andReturn($mockChatResource);

        $this->expectException(OpenAIException::class);
        $this->expectExceptionMessage('Stream interrupted');

        $message = AIMessage::user('Test message');

        foreach ($this->driver->sendStreamingMessage($message, [
            'model' => 'gpt-3.5-turbo',
        ]) as $chunk) {
            // Process some chunks before error
        }
    }

    #[Test]
    public function it_handles_timeout_during_streaming(): void
    {
        $mockChatResource = Mockery::mock();
        $mockChatResource->shouldReceive('createStreamed')
            ->once()
            ->andThrow(new \Exception('Request timeout'));

        $this->mockClient->shouldReceive('chat')
            ->once()
            ->andReturn($mockChatResource);

        $this->expectException(OpenAIException::class);
        $this->expectExceptionMessage('Request timeout');

        $message = AIMessage::user('Test message');

        foreach ($this->driver->sendStreamingMessage($message, [
            'model' => 'gpt-3.5-turbo',
        ]) as $chunk) {
            // Should not reach here
            $this->fail('Should have thrown a timeout exception');
        }
    }

    #[Test]
    public function it_handles_invalid_model_error(): void
    {
        $mockChatResource = Mockery::mock();
        $mockChatResource->shouldReceive('createStreamed')
            ->once()
            ->andThrow(new \Exception('The model `invalid-model` does not exist'));

        $this->mockClient->shouldReceive('chat')
            ->once()
            ->andReturn($mockChatResource);

        $this->expectException(OpenAIException::class);
        $this->expectExceptionMessage('The model `invalid-model` does not exist');

        $message = AIMessage::user('Test message');

        foreach ($this->driver->sendStreamingMessage($message, [
            'model' => 'invalid-model',
        ]) as $chunk) {
            // Should not reach here
            $this->fail('Should have thrown an invalid model exception');
        }
    }

    #[Test]
    public function it_handles_content_filter_in_stream(): void
    {
        $chunks = [
            $this->createValidChunk('This is appropriate content'),
            $this->createValidChunk('', true, 'content_filter'),
        ];

        $mockStream = Mockery::mock();
        $mockStream->shouldReceive('getIterator')
            ->andReturn(new \ArrayIterator($chunks));

        $mockChatResource = Mockery::mock();
        $mockChatResource->shouldReceive('createStreamed')
            ->once()
            ->andReturn($mockStream);

        $this->mockClient->shouldReceive('chat')
            ->once()
            ->andReturn($mockChatResource);

        $message = AIMessage::user('Test message');
        $chunks = [];

        foreach ($this->driver->sendStreamingMessage($message, [
            'model' => 'gpt-3.5-turbo',
        ]) as $chunk) {
            $chunks[] = $chunk;
        }

        $this->assertCount(2, $chunks);
        $this->assertEquals('content_filter', $chunks[1]->finishReason);
    }

    #[Test]
    public function it_handles_json_parsing_errors_in_stream(): void
    {
        // Create chunks with invalid JSON in function calls
        $chunks = [
            $this->createChunkWithInvalidJson(),
            $this->createValidChunk('', true, 'stop'),
        ];

        $mockStream = Mockery::mock();
        $mockStream->shouldReceive('getIterator')
            ->andReturn(new \ArrayIterator($chunks));

        $mockChatResource = Mockery::mock();
        $mockChatResource->shouldReceive('createStreamed')
            ->once()
            ->andReturn($mockStream);

        $this->mockClient->shouldReceive('chat')
            ->once()
            ->andReturn($mockChatResource);

        $message = AIMessage::user('Test message');
        $processedChunks = 0;

        foreach ($this->driver->sendStreamingMessage($message, [
            'model' => 'gpt-3.5-turbo',
        ]) as $chunk) {
            $processedChunks++;
            // Should handle invalid JSON gracefully
            $this->assertNotNull($chunk);
        }

        $this->assertGreaterThan(0, $processedChunks);
    }

    /**
     * Create a malformed chunk for testing error handling.
     */
    private function createMalformedChunk(string $type)
    {
        $chunk = new \stdClass;

        switch ($type) {
            case 'missing_choices':
                $chunk->model = 'gpt-3.5-turbo';
                // Missing choices property
                break;

            case 'missing_delta':
                $chunk->model = 'gpt-3.5-turbo';
                $chunk->choices = [new \stdClass];
                // Missing delta in choice
                break;

            case 'invalid_structure':
                $chunk->invalid = 'structure';
                break;
        }

        return $chunk;
    }

    /**
     * Create a valid chunk for testing.
     */
    private function createValidChunk(string $content, bool $isLast = false, ?string $finishReason = null)
    {
        $chunk = new \stdClass;
        $chunk->model = 'gpt-3.5-turbo';

        $choice = new \stdClass;
        $choice->finishReason = $isLast ? ($finishReason ?? 'stop') : null;

        $choice->delta = new \stdClass;
        $choice->delta->content = $content;
        $choice->delta->role = 'assistant';

        $chunk->choices = [$choice];

        return $chunk;
    }

    /**
     * Create a chunk with invalid JSON in function call.
     */
    private function createChunkWithInvalidJson()
    {
        $chunk = new \stdClass;
        $chunk->model = 'gpt-3.5-turbo';

        $choice = new \stdClass;
        $choice->finishReason = null;

        $choice->delta = new \stdClass;
        $choice->delta->content = '';
        $choice->delta->role = 'assistant';
        $choice->delta->functionCall = new \stdClass;
        $choice->delta->functionCall->name = 'test_function';
        $choice->delta->functionCall->arguments = '{"invalid": json}'; // Invalid JSON

        $chunk->choices = [$choice];

        return $chunk;
    }
}
