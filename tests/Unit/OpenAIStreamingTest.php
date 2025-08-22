<?php

namespace JTD\LaravelAI\Tests\Unit;

use JTD\LaravelAI\Drivers\OpenAIDriver;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Models\AIResponse;
use JTD\LaravelAI\Models\TokenUsage;
use JTD\LaravelAI\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Mockery;

/**
 * OpenAI Streaming Response Tests
 *
 * Comprehensive tests for streaming functionality including
 * chunk processing, error handling, and response assembly.
 */
#[Group('unit')]
#[Group('openai')]
#[Group('streaming')]
class OpenAIStreamingTest extends TestCase
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
    public function it_can_process_streaming_chunks_correctly(): void
    {
        // Mock streaming response chunks
        $chunks = [
            $this->createMockStreamChunk('Hello', false),
            $this->createMockStreamChunk(' world', false),
            $this->createMockStreamChunk('!', false),
            $this->createMockStreamChunk('', true, 'stop'), // Final chunk
        ];

        // Create a mock stream that implements Iterator
        $mockStream = new \ArrayIterator($chunks);

        $mockChatResource = Mockery::mock();
        $mockChatResource->shouldReceive('createStreamed')
            ->once()
            ->with(Mockery::on(function ($params) {
                return $params['model'] === 'gpt-3.5-turbo' &&
                       $params['stream'] === true &&
                       isset($params['messages']);
            }))
            ->andReturn($mockStream);

        $this->mockClient->shouldReceive('chat')
            ->once()
            ->andReturn($mockChatResource);

        // Test streaming
        $message = AIMessage::user('Test message');
        $chunks = [];

        foreach ($this->driver->sendStreamingMessage($message, [
            'model' => 'gpt-3.5-turbo',
            'max_tokens' => 50,
        ]) as $chunk) {
            $chunks[] = $chunk;
        }

        // Verify chunks (4 stream chunks + 1 final response)
        $this->assertCount(5, $chunks);

        // Check individual stream chunks
        $this->assertInstanceOf(AIResponse::class, $chunks[0]);
        $this->assertEquals('Hello', $chunks[0]->content);
        $this->assertEquals('streaming', $chunks[0]->finishReason);

        $this->assertEquals(' world', $chunks[1]->content);
        $this->assertEquals('!', $chunks[2]->content);

        // Check stream end chunk
        $this->assertEquals('', $chunks[3]->content);
        $this->assertEquals('stop', $chunks[3]->finishReason);

        // Check final assembled response
        $finalResponse = $chunks[4];
        $this->assertEquals('Hello world!', $finalResponse->content);
        $this->assertEquals('stop', $finalResponse->finishReason);
    }

    #[Test]
    public function it_assembles_complete_response_from_chunks(): void
    {
        $chunks = [
            $this->createMockStreamChunk('The', false),
            $this->createMockStreamChunk(' quick', false),
            $this->createMockStreamChunk(' brown', false),
            $this->createMockStreamChunk(' fox', false),
            $this->createMockStreamChunk('', true, 'stop'),
        ];

        $this->setupMockStreamingResponse($chunks);

        $message = AIMessage::user('Tell me about a fox');
        $streamContent = '';
        $chunkCount = 0;
        $finalResponse = null;

        foreach ($this->driver->sendStreamingMessage($message, [
            'model' => 'gpt-3.5-turbo',
            'max_tokens' => 100,
        ]) as $chunk) {
            $chunkCount++;

            // The last chunk is the final assembled response
            if ($chunkCount === 6) { // 5 stream chunks + 1 final response
                $finalResponse = $chunk;
            } else {
                // Only accumulate content from streaming chunks
                if ($chunk->content) {
                    $streamContent .= $chunk->content;
                }
            }
        }

        $this->assertEquals('The quick brown fox', $streamContent);
        $this->assertEquals('The quick brown fox', $finalResponse->content);
        $this->assertEquals(6, $chunkCount);
    }

    #[Test]
    public function it_handles_streaming_with_function_calls(): void
    {
        $chunks = [
            $this->createMockStreamChunk('', false, null, [
                'functionCall' => (object) [
                    'name' => 'get_weather',
                    'arguments' => '{"location"',
                ]
            ]),
            $this->createMockStreamChunk('', false, null, [
                'functionCall' => (object) [
                    'arguments' => ': "San Francisco"}'
                ]
            ]),
            $this->createMockStreamChunk('', true, 'function_call'),
        ];

        $this->setupMockStreamingResponse($chunks);

        $message = AIMessage::user('What is the weather?');
        $chunks = [];

        foreach ($this->driver->sendStreamingMessage($message, [
            'model' => 'gpt-3.5-turbo',
            'functions' => [
                [
                    'name' => 'get_weather',
                    'description' => 'Get weather',
                    'parameters' => ['type' => 'object', 'properties' => []],
                ]
            ],
        ]) as $chunk) {
            $chunks[] = $chunk;
        }

        $this->assertCount(4, $chunks); // 3 stream chunks + 1 final response
        $this->assertEquals('function_call', $chunks[2]->finishReason);
        $this->assertNotNull($chunks[0]->functionCalls);
    }

    #[Test]
    public function it_handles_streaming_errors_gracefully(): void
    {
        $mockChatResource = Mockery::mock();
        $mockChatResource->shouldReceive('createStreamed')
            ->once()
            ->andThrow(new \Exception('API Error'));

        $this->mockClient->shouldReceive('chat')
            ->once()
            ->andReturn($mockChatResource);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('API Error');

        $message = AIMessage::user('Test message');

        foreach ($this->driver->sendStreamingMessage($message, [
            'model' => 'gpt-3.5-turbo',
        ]) as $chunk) {
            // Should not reach here
        }
    }

    #[Test]
    public function it_tracks_token_usage_in_streaming(): void
    {
        $chunks = [
            $this->createMockStreamChunk('Hello', false),
            $this->createMockStreamChunk(' world', false),
            $this->createMockStreamChunk('', true, 'stop', null, [
                'prompt_tokens' => 10,
                'completion_tokens' => 5,
                'total_tokens' => 15,
            ]),
        ];

        $this->setupMockStreamingResponse($chunks);

        $message = AIMessage::user('Test message');
        $finalChunk = null;

        foreach ($this->driver->sendStreamingMessage($message, [
            'model' => 'gpt-3.5-turbo',
        ]) as $chunk) {
            $finalChunk = $chunk;
        }

        $this->assertNotNull($finalChunk);
        $this->assertEquals(15, $finalChunk->tokenUsage->totalTokens);
        $this->assertEquals(10, $finalChunk->tokenUsage->inputTokens);
        $this->assertEquals(5, $finalChunk->tokenUsage->outputTokens);
    }

    #[Test]
    public function it_handles_empty_streaming_response(): void
    {
        $chunks = [
            $this->createMockStreamChunk('', true, 'stop'),
        ];

        $this->setupMockStreamingResponse($chunks);

        $message = AIMessage::user('Test message');
        $chunkCount = 0;

        foreach ($this->driver->sendStreamingMessage($message, [
            'model' => 'gpt-3.5-turbo',
        ]) as $chunk) {
            $chunkCount++;
            $this->assertEquals('', $chunk->content);
            $this->assertEquals('stop', $chunk->finishReason);
        }

        $this->assertEquals(2, $chunkCount); // 1 stream chunk + 1 final response
    }

    #[Test]
    public function it_handles_streaming_with_different_finish_reasons(): void
    {
        $testCases = [
            ['stop', 'Normal completion'],
            ['length', 'Max tokens reached'],
            ['content_filter', 'Content filtered'],
            ['function_call', 'Function call triggered'],
        ];

        foreach ($testCases as [$finishReason, $description]) {
            $chunks = [
                $this->createMockStreamChunk('Test', false),
                $this->createMockStreamChunk('', true, $finishReason),
            ];

            $this->setupMockStreamingResponse($chunks);

            $message = AIMessage::user('Test message');
            $finalChunk = null;

            foreach ($this->driver->sendStreamingMessage($message, [
                'model' => 'gpt-3.5-turbo',
            ]) as $chunk) {
                $finalChunk = $chunk;
            }

            $this->assertEquals($finishReason, $finalChunk->finishReason, $description);
        }
    }

    #[Test]
    public function it_preserves_model_information_in_streaming(): void
    {
        $chunks = [
            $this->createMockStreamChunk('Hello', false, null, null, null, 'gpt-3.5-turbo-0125'),
            $this->createMockStreamChunk('', true, 'stop', null, null, 'gpt-3.5-turbo-0125'),
        ];

        $this->setupMockStreamingResponse($chunks);

        $message = AIMessage::user('Test message');
        $chunks = [];

        foreach ($this->driver->sendStreamingMessage($message, [
            'model' => 'gpt-3.5-turbo-0125',
        ]) as $chunk) {
            $chunks[] = $chunk;
            $this->assertEquals('gpt-3.5-turbo-0125', $chunk->model);
            $this->assertEquals('openai', $chunk->provider);
        }

        $this->assertCount(3, $chunks); // 2 stream chunks + 1 final response
    }

    /**
     * Create a mock stream chunk for testing.
     */
    private function createMockStreamChunk(
        string $content,
        bool $isLast = false,
        ?string $finishReason = null,
        ?array $delta = null,
        ?array $usage = null,
        string $model = 'gpt-3.5-turbo'
    ) {
        $chunk = new \stdClass();
        $chunk->model = $model;

        $choice = new \stdClass();
        $choice->finishReason = $isLast ? ($finishReason ?? 'stop') : null;

        $choice->delta = new \stdClass();
        $choice->delta->content = $content;
        $choice->delta->role = 'assistant';

        if ($delta) {
            foreach ($delta as $key => $value) {
                $choice->delta->$key = $value;
            }
        }

        $chunk->choices = [$choice];

        if ($usage) {
            $chunk->usage = new \stdClass();
            $chunk->usage->promptTokens = $usage['prompt_tokens'] ?? 0;
            $chunk->usage->completionTokens = $usage['completion_tokens'] ?? 0;
            $chunk->usage->totalTokens = $usage['total_tokens'] ?? 0;
        }

        return $chunk;
    }

    /**
     * Setup mock streaming response.
     */
    private function setupMockStreamingResponse(array $chunks): void
    {
        // Create a mock stream that implements Iterator
        $mockStream = new \ArrayIterator($chunks);

        $mockChatResource = Mockery::mock();
        $mockChatResource->shouldReceive('createStreamed')
            ->once()
            ->andReturn($mockStream);

        $this->mockClient->shouldReceive('chat')
            ->once()
            ->andReturn($mockChatResource);
    }
}
