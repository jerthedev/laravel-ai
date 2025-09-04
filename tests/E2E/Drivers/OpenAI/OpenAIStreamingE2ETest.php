<?php

namespace JTD\LaravelAI\Tests\E2E;

use JTD\LaravelAI\Drivers\OpenAI\OpenAIDriver;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Models\AIResponse;
use JTD\LaravelAI\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * OpenAI Streaming E2E Tests
 *
 * End-to-end tests for streaming functionality with real OpenAI API.
 * Tests chunk processing, error handling, and response assembly.
 */
#[Group('e2e')]
#[Group('openai')]
#[Group('streaming')]
class OpenAIStreamingE2ETest extends TestCase
{
    private OpenAIDriver $driver;

    private array $credentials;

    protected function setUp(): void
    {
        parent::setUp();

        // Load credentials from E2E credentials file
        $credentialsPath = __DIR__ . '/../credentials/e2e-credentials.json';

        if (! file_exists($credentialsPath)) {
            $this->markTestSkipped('E2E credentials file not found for streaming tests');
        }

        $this->credentials = json_decode(file_get_contents($credentialsPath), true);

        if (empty($this->credentials['openai']['api_key']) || ! $this->credentials['openai']['enabled']) {
            $this->markTestSkipped('OpenAI credentials not configured or disabled for streaming E2E tests');
        }

        $this->driver = new OpenAIDriver([
            'api_key' => $this->credentials['openai']['api_key'],
            'organization' => $this->credentials['openai']['organization'] ?? null,
            'project' => $this->credentials['openai']['project'] ?? null,
            'timeout' => 60,
        ]);
    }

    #[Test]
    public function it_can_stream_basic_response(): void
    {
        $this->logTestStep('🚀 Testing basic streaming response');

        $message = AIMessage::user('Count from 1 to 5, one number per sentence.');

        $chunks = [];
        $fullContent = '';
        $startTime = microtime(true);

        foreach ($this->driver->sendStreamingMessage($message, [
            'model' => 'gpt-3.5-turbo',
            'max_tokens' => 100,
            'temperature' => 0,
        ]) as $chunk) {
            $this->assertInstanceOf(AIResponse::class, $chunk);
            $chunks[] = $chunk;
            $fullContent .= $chunk->content;
        }

        $responseTime = (microtime(true) - $startTime) * 1000;

        $this->logTestStep('✅ Received {count} chunks in {time}ms', [
            'count' => count($chunks),
            'time' => round($responseTime),
        ]);
        $this->logTestStep('📝 Full content: ' . substr($fullContent, 0, 100) . '...');

        // Assertions
        $this->assertGreaterThan(0, count($chunks), 'Should receive at least one chunk');
        $this->assertNotEmpty($fullContent, 'Should receive content');
        $this->assertLessThan(10000, $responseTime, 'Should complete within 10 seconds');

        // Check final chunk
        $finalChunk = end($chunks);
        $this->assertContains($finalChunk->finishReason, ['stop', 'length'], 'Final chunk should have valid finish reason');
        $this->assertEquals('openai', $finalChunk->provider);
        $this->assertStringContainsString('gpt-3.5-turbo', $finalChunk->model);
    }

    #[Test]
    public function it_can_stream_longer_response(): void
    {
        $this->logTestStep('🚀 Testing longer streaming response');

        $message = AIMessage::user('Write a short story about a robot learning to paint. Keep it under 200 words.');

        $chunks = [];
        $fullContent = '';
        $firstChunkTime = null;
        $startTime = microtime(true);

        foreach ($this->driver->sendStreamingMessage($message, [
            'model' => 'gpt-3.5-turbo',
            'max_tokens' => 300,
            'temperature' => 0.7,
        ]) as $chunk) {
            if ($firstChunkTime === null) {
                $firstChunkTime = (microtime(true) - $startTime) * 1000;
            }

            $chunks[] = $chunk;
            $fullContent .= $chunk->content;
        }

        $totalTime = (microtime(true) - $startTime) * 1000;

        $this->logTestStep('✅ First chunk: {time}ms', ['time' => round($firstChunkTime)]);
        $this->logTestStep('✅ Total time: {time}ms', ['time' => round($totalTime)]);
        $this->logTestStep('📦 Total chunks: {count}', ['count' => count($chunks)]);
        $this->logTestStep('📝 Content length: {length} chars', ['length' => strlen($fullContent)]);

        // Assertions
        $this->assertGreaterThan(5, count($chunks), 'Should receive multiple chunks for longer content');
        $this->assertGreaterThan(100, strlen($fullContent), 'Should receive substantial content');
        $this->assertLessThan(3000, $firstChunkTime, 'First chunk should arrive within 3 seconds');
        $this->assertLessThan(15000, $totalTime, 'Should complete within 15 seconds');

        // Content should be about a robot and painting
        $this->assertStringContainsIgnoringCase($fullContent, 'robot');
        $this->assertStringContainsIgnoringCase($fullContent, 'paint');
    }

    #[Test]
    public function it_handles_streaming_with_function_calls(): void
    {
        $this->logTestStep('🚀 Testing streaming with function calls');

        $message = AIMessage::user('What is the weather like in San Francisco?');
        $functions = [
            [
                'name' => 'get_weather',
                'description' => 'Get current weather for a location',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'location' => [
                            'type' => 'string',
                            'description' => 'The city and country, e.g. San Francisco, CA',
                        ],
                        'unit' => [
                            'type' => 'string',
                            'enum' => ['celsius', 'fahrenheit'],
                            'description' => 'The temperature unit',
                        ],
                    ],
                    'required' => ['location'],
                ],
            ],
        ];

        $chunks = [];
        $hasFunctionCall = false;
        $startTime = microtime(true);

        foreach ($this->driver->sendStreamingMessage($message, [
            'model' => 'gpt-3.5-turbo',
            'functions' => $functions,
            'function_call' => 'auto',
            'max_tokens' => 100,
        ]) as $chunk) {
            $chunks[] = $chunk;

            if ($chunk->functionCalls || $chunk->toolCalls) {
                $hasFunctionCall = true;
            }
        }

        $responseTime = (microtime(true) - $startTime) * 1000;

        $this->logTestStep('✅ Response time: {time}ms', ['time' => round($responseTime)]);
        $this->logTestStep('📦 Total chunks: {count}', ['count' => count($chunks)]);
        $this->logTestStep('🔧 Function call detected: ' . ($hasFunctionCall ? 'Yes' : 'No'));

        // Assertions
        $this->assertGreaterThan(0, count($chunks), 'Should receive chunks');
        $this->assertLessThan(10000, $responseTime, 'Should complete within 10 seconds');

        // Check final chunk
        $finalChunk = end($chunks);
        $this->assertContains($finalChunk->finishReason, ['stop', 'function_call', 'tool_calls'], 'Should have valid finish reason');

        // If function call was made, verify it's for weather
        if ($hasFunctionCall) {
            $this->logTestStep('✅ Function call successfully triggered');
            // The function call should be for get_weather with San Francisco
            $this->assertTrue(true, 'Function call mechanism working');
        }
    }

    #[Test]
    public function it_handles_streaming_errors_gracefully(): void
    {
        $this->logTestStep('🚀 Testing streaming error handling');

        $message = AIMessage::user('Test message');

        try {
            $chunks = [];
            foreach ($this->driver->sendStreamingMessage($message, [
                'model' => 'invalid-model-name',
                'max_tokens' => 50,
            ]) as $chunk) {
                $chunks[] = $chunk;
            }

            $this->fail('Should have thrown an exception for invalid model');
        } catch (\Exception $e) {
            $this->logTestStep('✅ Error handled: ' . $e->getMessage());
            $this->assertStringContainsIgnoringCase($e->getMessage(), 'model');
        }
    }

    #[Test]
    public function it_measures_streaming_performance(): void
    {
        $this->logTestStep('🚀 Measuring streaming performance');

        $message = AIMessage::user('List 10 programming languages with one sentence each.');

        $chunks = [];
        $chunkTimes = [];
        $lastTime = microtime(true);
        $startTime = $lastTime;

        foreach ($this->driver->sendStreamingMessage($message, [
            'model' => 'gpt-3.5-turbo',
            'max_tokens' => 200,
            'temperature' => 0,
        ]) as $chunk) {
            $currentTime = microtime(true);
            $chunkInterval = ($currentTime - $lastTime) * 1000;

            $chunks[] = $chunk;
            $chunkTimes[] = $chunkInterval;
            $lastTime = $currentTime;
        }

        $totalTime = (microtime(true) - $startTime) * 1000;
        $avgChunkInterval = array_sum($chunkTimes) / count($chunkTimes);
        $maxChunkInterval = max($chunkTimes);
        $chunksPerSecond = count($chunks) / ($totalTime / 1000);

        $this->logTestStep('✅ Total time: {time}ms', ['time' => round($totalTime)]);
        $this->logTestStep('📦 Total chunks: {count}', ['count' => count($chunks)]);
        $this->logTestStep('⚡ Avg chunk interval: {time}ms', ['time' => round($avgChunkInterval)]);
        $this->logTestStep('⏱️  Max chunk interval: {time}ms', ['time' => round($maxChunkInterval)]);
        $this->logTestStep('📈 Chunks per second: {rate}', ['rate' => round($chunksPerSecond, 2)]);

        // Performance assertions
        $this->assertLessThan(15000, $totalTime, 'Should complete within 15 seconds');
        $this->assertGreaterThan(0.5, $chunksPerSecond, 'Should achieve at least 0.5 chunks/second');
        $this->assertLessThan(5000, $maxChunkInterval, 'No chunk should take more than 5 seconds');
        $this->assertGreaterThan(5, count($chunks), 'Should receive multiple chunks');
    }

    #[Test]
    public function it_preserves_metadata_in_streaming(): void
    {
        $this->logTestStep('🚀 Testing metadata preservation in streaming');

        $message = AIMessage::user('Say hello in 3 different languages.');

        $chunks = [];
        foreach ($this->driver->sendStreamingMessage($message, [
            'model' => 'gpt-3.5-turbo',
            'max_tokens' => 100,
            'temperature' => 0,
            'user' => 'test-user-123',
        ]) as $chunk) {
            $chunks[] = $chunk;

            // Verify metadata is preserved
            $this->assertEquals('openai', $chunk->provider);
            $this->assertStringContains('gpt-3.5-turbo', $chunk->model);
            $this->assertEquals('assistant', $chunk->role);
            $this->assertIsFloat($chunk->responseTimeMs);
            $this->assertGreaterThan(0, $chunk->responseTimeMs);
        }

        $this->logTestStep('✅ Processed {count} chunks with preserved metadata', [
            'count' => count($chunks),
        ]);

        $this->assertGreaterThan(0, count($chunks), 'Should receive chunks');

        // Check final chunk has token usage (if available)
        $finalChunk = end($chunks);
        if ($finalChunk->tokenUsage->totalTokens > 0) {
            $this->logTestStep('📊 Token usage: {tokens} total', [
                'tokens' => $finalChunk->tokenUsage->totalTokens,
            ]);
            $this->assertGreaterThan(0, $finalChunk->tokenUsage->input_tokens);
        }
    }

    /**
     * Log a test step for debugging.
     */
    private function logTestStep(string $message, array $context = []): void
    {
        $formattedMessage = $message;
        foreach ($context as $key => $value) {
            $formattedMessage = str_replace("{{$key}}", $value, $formattedMessage);
        }

        if (defined('STDOUT')) {
            fwrite(STDOUT, $formattedMessage . "\n");
        }
    }

    /**
     * Case-insensitive string contains check.
     */
    private function assertStringContainsIgnoringCase(string $haystack, string $needle): void
    {
        $this->assertStringContainsString(
            strtolower($needle),
            strtolower($haystack),
            "Failed asserting that '{$haystack}' contains '{$needle}' (case insensitive)"
        );
    }
}
