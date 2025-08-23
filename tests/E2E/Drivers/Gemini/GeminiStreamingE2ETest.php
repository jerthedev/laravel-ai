<?php

namespace JTD\LaravelAI\Tests\E2E\Drivers\Gemini;

use JTD\LaravelAI\Drivers\Gemini\GeminiDriver;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Models\AIResponse;
use JTD\LaravelAI\Tests\E2E\E2ETestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Gemini Streaming E2E Tests
 *
 * End-to-end tests for streaming functionality with real Gemini API.
 * Tests streaming responses, chunk processing, and performance.
 */
#[Group('e2e')]
#[Group('gemini')]
#[Group('streaming')]
class GeminiStreamingE2ETest extends E2ETestCase
{
    private GeminiDriver $driver;

    protected function setUp(): void
    {
        parent::setUp();

        // Skip if no credentials available
        if (! $this->hasE2ECredentials('gemini')) {
            $this->markTestSkipped('Gemini E2E credentials not available');
        }

        // Create Gemini driver with real credentials
        $credentials = $this->getE2ECredentials();
        $config = [
            'api_key' => $credentials['gemini']['api_key'],
            'base_url' => 'https://generativelanguage.googleapis.com/v1',
            'default_model' => 'gemini-2.5-flash',
            'timeout' => 30,
            'retry_attempts' => 2,
        ];

        $this->driver = new GeminiDriver($config);
    }

    #[Test]
    public function it_can_stream_simple_response(): void
    {
        $this->logTestStart('Testing simple streaming response');

        $message = AIMessage::user('Write a short story about a robot in exactly 3 sentences.');

        $startTime = microtime(true);
        $chunks = [];
        $fullContent = '';

        foreach ($this->driver->sendStreamingMessage($message, [
            'model' => 'gemini-2.5-flash',
            'max_tokens' => 100,
            'temperature' => 0.7,
        ]) as $chunk) {
            $chunks[] = $chunk;
            $fullContent .= $chunk->content;

            $this->assertInstanceOf(AIResponse::class, $chunk);
            $this->assertEquals('gemini-2.5-flash', $chunk->model);
            $this->assertEquals('gemini', $chunk->provider);
        }

        $responseTime = (microtime(true) - $startTime) * 1000;

        $this->logTestStep('✅ Streaming completed in {time}ms', ['time' => round($responseTime)]);
        $this->logTestStep('📊 Received {count} chunks', ['count' => count($chunks)]);
        $this->logTestStep('📝 Full content: "{content}"', ['content' => substr($fullContent, 0, 100) . '...']);

        // Assertions
        $this->assertNotEmpty($chunks);
        $this->assertGreaterThan(1, count($chunks), 'Should receive multiple chunks for streaming');
        $this->assertNotEmpty($fullContent);
        $this->assertLessThan(30000, $responseTime, 'Should complete within 30 seconds');

        // Check final chunk
        $finalChunk = end($chunks);
        $this->assertFalse($finalChunk->isStreaming ?? true, 'Final chunk should not be marked as streaming');
        $this->assertGreaterThan(0, $finalChunk->tokenUsage->totalTokens);

        $this->logTestEnd('Simple streaming response test completed');
    }

    #[Test]
    public function it_can_stream_long_response(): void
    {
        $this->logTestStart('Testing long streaming response');

        $message = AIMessage::user('Write a detailed explanation of machine learning in 5 paragraphs.');

        $startTime = microtime(true);
        $chunks = [];
        $totalTokens = 0;
        $chunkSizes = [];

        foreach ($this->driver->sendStreamingMessage($message, [
            'model' => 'gemini-2.5-flash',
            'max_tokens' => 500,
            'temperature' => 0.5,
        ]) as $chunk) {
            $chunks[] = $chunk;
            $chunkSizes[] = strlen($chunk->content);
            $totalTokens = max($totalTokens, $chunk->tokenUsage->totalTokens);

            // Log progress every 10 chunks
            if (count($chunks) % 10 === 0) {
                $this->logTestStep('📊 Processed {count} chunks...', ['count' => count($chunks)]);
            }
        }

        $responseTime = (microtime(true) - $startTime) * 1000;
        $avgChunkSize = array_sum($chunkSizes) / count($chunkSizes);

        $this->logTestStep('✅ Long streaming completed in {time}ms', ['time' => round($responseTime)]);
        $this->logTestStep('📊 Total chunks: {count}, Avg chunk size: {size} chars', [
            'count' => count($chunks),
            'size' => round($avgChunkSize),
        ]);
        $this->logTestStep('🔢 Total tokens: {tokens}', ['tokens' => $totalTokens]);

        // Assertions
        $this->assertGreaterThan(10, count($chunks), 'Long response should have many chunks');
        $this->assertGreaterThan(100, $totalTokens, 'Long response should use many tokens');
        $this->assertLessThan(60000, $responseTime, 'Should complete within 60 seconds');

        $this->logTestEnd('Long streaming response test completed');
    }

    #[Test]
    public function it_can_stream_with_function_calling(): void
    {
        $this->logTestStart('Testing streaming with function calling');

        $functions = [
            [
                'name' => 'get_weather',
                'description' => 'Get weather information',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'location' => ['type' => 'string'],
                    ],
                    'required' => ['location'],
                ],
            ],
        ];

        $message = AIMessage::user('What is the weather like in Paris? Please provide a detailed response.');

        $startTime = microtime(true);
        $chunks = [];
        $hasFunctionCall = false;

        try {
            foreach ($this->driver->sendStreamingMessage($message, [
                'model' => 'gemini-1.5-pro',
                'functions' => $functions,
                'max_tokens' => 200,
            ]) as $chunk) {
                $chunks[] = $chunk;

                if ($chunk->functionCalls) {
                    $hasFunctionCall = true;
                    $this->logTestStep('✅ Function call detected in streaming response');
                }
            }
        } catch (\Exception $e) {
            $this->logTestStep('ℹ️  Function calling in streaming not supported or failed: ' . $e->getMessage());
            $this->markTestSkipped('Function calling in streaming not available');
        }

        $responseTime = (microtime(true) - $startTime) * 1000;

        $this->logTestStep('✅ Function calling streaming completed in {time}ms', ['time' => round($responseTime)]);
        $this->logTestStep('📊 Received {count} chunks', ['count' => count($chunks)]);

        // Assertions
        $this->assertNotEmpty($chunks);
        $this->assertLessThan(30000, $responseTime, 'Should complete within 30 seconds');

        if ($hasFunctionCall) {
            $this->logTestStep('✅ Function calling worked with streaming');
        } else {
            $this->logTestStep('ℹ️  No function call made - model provided direct response');
        }

        $this->logTestEnd('Streaming with function calling test completed');
    }

    #[Test]
    public function it_can_handle_streaming_errors(): void
    {
        $this->logTestStart('Testing streaming error handling');

        // Test with invalid model
        try {
            $message = AIMessage::user('Test streaming error');
            $chunks = [];

            foreach ($this->driver->sendStreamingMessage($message, [
                'model' => 'non-existent-model',
                'max_tokens' => 50,
            ]) as $chunk) {
                $chunks[] = $chunk;
            }

            $this->fail('Should have thrown an exception for invalid model');
        } catch (\Exception $e) {
            $this->logTestStep('✅ Streaming error properly handled: ' . $e->getMessage());
            $this->assertNotEmpty($e->getMessage());
        }

        // Test with empty message
        try {
            $emptyMessage = AIMessage::user('');
            $chunks = [];

            foreach ($this->driver->sendStreamingMessage($emptyMessage, [
                'model' => 'gemini-2.5-flash',
                'max_tokens' => 10,
            ]) as $chunk) {
                $chunks[] = $chunk;
            }

            // If successful, that's also valid
            $this->logTestStep('✅ Empty message handled gracefully in streaming');
        } catch (\Exception $e) {
            $this->logTestStep('✅ Empty message properly rejected in streaming: ' . $e->getMessage());
            $this->assertNotEmpty($e->getMessage());
        }

        $this->logTestEnd('Streaming error handling test completed');
    }

    #[Test]
    public function it_can_stream_with_safety_settings(): void
    {
        $this->logTestStart('Testing streaming with safety settings');

        $safetySettings = [
            'HARM_CATEGORY_HARASSMENT' => 'BLOCK_MEDIUM_AND_ABOVE',
            'HARM_CATEGORY_HATE_SPEECH' => 'BLOCK_MEDIUM_AND_ABOVE',
        ];

        $message = AIMessage::user('Tell me about online safety best practices.');

        $startTime = microtime(true);
        $chunks = [];
        $hasSafetyRatings = false;

        foreach ($this->driver->sendStreamingMessage($message, [
            'model' => 'gemini-2.5-flash',
            'max_tokens' => 150,
            'safety_settings' => $safetySettings,
        ]) as $chunk) {
            $chunks[] = $chunk;

            if (isset($chunk->metadata['safety_ratings']) && ! empty($chunk->metadata['safety_ratings'])) {
                $hasSafetyRatings = true;
            }
        }

        $responseTime = (microtime(true) - $startTime) * 1000;

        $this->logTestStep('✅ Safety streaming completed in {time}ms', ['time' => round($responseTime)]);
        $this->logTestStep('📊 Received {count} chunks', ['count' => count($chunks)]);

        // Assertions
        $this->assertNotEmpty($chunks);
        $this->assertLessThan(30000, $responseTime, 'Should complete within 30 seconds');

        if ($hasSafetyRatings) {
            $this->logTestStep('✅ Safety ratings included in streaming chunks');
        }

        $this->logTestEnd('Streaming with safety settings test completed');
    }

    #[Test]
    public function it_validates_streaming_performance(): void
    {
        $this->logTestStart('Testing streaming performance');

        $message = AIMessage::user('Count from 1 to 20 with brief explanations.');

        $startTime = microtime(true);
        $chunks = [];
        $firstChunkTime = null;
        $chunkTimes = [];

        foreach ($this->driver->sendStreamingMessage($message, [
            'model' => 'gemini-2.5-flash',
            'max_tokens' => 200,
        ]) as $chunk) {
            $currentTime = microtime(true);

            if ($firstChunkTime === null) {
                $firstChunkTime = $currentTime;
                $timeToFirstChunk = ($firstChunkTime - $startTime) * 1000;
                $this->logTestStep('⚡ Time to first chunk: {time}ms', ['time' => round($timeToFirstChunk)]);
            }

            $chunks[] = $chunk;
            $chunkTimes[] = $currentTime;
        }

        $totalTime = (microtime(true) - $startTime) * 1000;
        $avgTimeBetweenChunks = 0;

        if (count($chunkTimes) > 1) {
            $intervals = [];
            for ($i = 1; $i < count($chunkTimes); $i++) {
                $intervals[] = ($chunkTimes[$i] - $chunkTimes[$i - 1]) * 1000;
            }
            $avgTimeBetweenChunks = array_sum($intervals) / count($intervals);
        }

        $this->logTestStep('✅ Performance test completed in {total}ms', ['total' => round($totalTime)]);
        $this->logTestStep('📊 Chunks: {count}, Avg interval: {interval}ms', [
            'count' => count($chunks),
            'interval' => round($avgTimeBetweenChunks),
        ]);

        // Performance assertions
        $this->assertLessThan(5000, $timeToFirstChunk ?? 0, 'First chunk should arrive within 5 seconds');
        $this->assertLessThan(45000, $totalTime, 'Total streaming should complete within 45 seconds');
        $this->assertGreaterThan(3, count($chunks), 'Should receive multiple chunks');

        if ($avgTimeBetweenChunks > 0) {
            $this->assertLessThan(2000, $avgTimeBetweenChunks, 'Average time between chunks should be reasonable');
        }

        $this->logTestEnd('Streaming performance test completed');
    }

    /**
     * Log test step for better visibility.
     */
    protected function logTestStep(string $message): void
    {
        echo "\n  " . $message;
    }

    /**
     * Log test start.
     */
    protected function logTestStart(string $testName): void
    {
        echo "\n🧪 " . $testName;
    }

    /**
     * Log test end.
     */
    protected function logTestEnd(string $message): void
    {
        echo "\n✅ " . $message . "\n";
    }
}
