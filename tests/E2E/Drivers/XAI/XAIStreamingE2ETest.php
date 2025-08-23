<?php

namespace JTD\LaravelAI\Tests\E2E;

use JTD\LaravelAI\Drivers\XAI\XAIDriver;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Models\AIResponse;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * E2E Test for xAI Streaming Functionality
 *
 * Tests real streaming responses from xAI API to ensure
 * the streaming implementation works correctly with actual API responses.
 */
#[Group('e2e')]
#[Group('xai')]
#[Group('streaming')]
class XAIStreamingE2ETest extends E2ETestCase
{
    protected XAIDriver $driver;

    protected array $credentials;

    protected function setUp(): void
    {
        parent::setUp();

        // Load credentials
        $this->credentials = $this->getE2ECredentials();

        // Skip if streaming is disabled or no credentials
        if (! $this->hasE2ECredentials('xai') ||
            ! ($this->credentials['xai']['enable_streaming'] ?? true)) {
            $this->markTestSkipped('xAI credentials not configured or disabled for streaming E2E tests');
        }

        $this->driver = new XAIDriver([
            'api_key' => $this->credentials['xai']['api_key'],
            'base_url' => $this->credentials['xai']['base_url'] ?? 'https://api.x.ai/v1',
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
            'model' => 'grok-3-mini',
            'max_tokens' => 100,
            'temperature' => 0,
        ]) as $chunk) {
            $this->assertInstanceOf(AIResponse::class, $chunk);
            $chunks[] = $chunk;
            $fullContent .= $chunk->content;
        }

        $responseTime = (microtime(true) - $startTime) * 1000;

        $this->logTestStep('✅ Received ' . count($chunks) . ' chunks in ' . round($responseTime) . 'ms');
        $this->logTestStep('📝 Full content: ' . substr($fullContent, 0, 100) . '...');

        // Assertions
        $this->assertGreaterThan(0, count($chunks), 'Should receive at least one chunk');
        $this->assertNotEmpty($fullContent, 'Full content should not be empty');

        // Verify chunk properties
        foreach ($chunks as $chunk) {
            $this->assertEquals('xai', $chunk->provider);
            $this->assertStringStartsWith('grok-3', $chunk->model);
        }

        $this->logTestStep('✅ Basic streaming test completed successfully');
    }

    #[Test]
    public function it_can_stream_longer_response(): void
    {
        $this->logTestStep('🚀 Testing longer streaming response');

        $message = AIMessage::user('Write a short story about a robot learning to paint. Keep it under 200 words.');

        $chunks = [];
        $fullContent = '';
        $startTime = microtime(true);
        $firstChunkTime = null;

        foreach ($this->driver->sendStreamingMessage($message, [
            'model' => 'grok-3-mini',
            'max_tokens' => 300,
            'temperature' => 0.7,
        ]) as $chunk) {
            if ($firstChunkTime === null) {
                $firstChunkTime = microtime(true);
            }

            $this->assertInstanceOf(AIResponse::class, $chunk);
            $chunks[] = $chunk;
            $fullContent .= $chunk->content;

            // Log progress every 10 chunks
            if (count($chunks) % 10 === 0) {
                $this->logTestStep('📦 Received ' . count($chunks) . ' chunks so far...');
            }
        }

        $totalTime = (microtime(true) - $startTime) * 1000;
        $timeToFirstChunk = $firstChunkTime ? ($firstChunkTime - $startTime) * 1000 : 0;

        $this->logTestStep('✅ Received ' . count($chunks) . ' chunks total');
        $this->logTestStep('⏱️  Time to first chunk: ' . round($timeToFirstChunk) . 'ms');
        $this->logTestStep('⏱️  Total time: ' . round($totalTime) . 'ms');
        $this->logTestStep('📝 Content length: ' . strlen($fullContent) . ' characters');
        $this->logTestStep('📖 Story preview: ' . substr($fullContent, 0, 150) . '...');

        // Assertions
        $this->assertGreaterThan(5, count($chunks), 'Should receive multiple chunks for longer content');
        $this->assertGreaterThan(50, strlen($fullContent), 'Story should be substantial');
        $this->assertLessThan(10000, $timeToFirstChunk, 'First chunk should arrive quickly');

        $this->logTestStep('✅ Longer streaming test completed successfully');
    }

    #[Test]
    public function it_handles_streaming_errors_gracefully(): void
    {
        $this->logTestStep('🚀 Testing streaming error handling');

        // Test with invalid model
        $message = AIMessage::user('This should fail with invalid model.');

        try {
            $chunks = [];
            foreach ($this->driver->sendStreamingMessage($message, [
                'model' => 'invalid-model-name',
                'max_tokens' => 50,
            ]) as $chunk) {
                $chunks[] = $chunk;
            }

            $this->fail('Expected exception for invalid model, but streaming succeeded');
        } catch (\Exception $e) {
            $this->logTestStep('✅ Exception caught as expected: ' . $e->getMessage());
            $this->logTestStep('Exception type: ' . get_class($e));

            // Verify it's a reasonable error
            $this->assertNotEmpty($e->getMessage());

            // Check if it's a model-related error
            $message = strtolower($e->getMessage());
            $isModelError = str_contains($message, 'model') ||
                           str_contains($message, 'invalid') ||
                           str_contains($message, 'not found');

            if ($isModelError) {
                $this->logTestStep('✅ Error is model-related as expected');
            } else {
                $this->logTestStep('⚠️  Error is not model-related, but still handled gracefully');
            }
        }

        $this->logTestStep('✅ Streaming error handling test completed');
    }

    #[Test]
    public function it_can_stream_with_conversation_context(): void
    {
        $this->logTestStep('🚀 Testing streaming with conversation context');

        $messages = [
            AIMessage::system('You are a helpful assistant that responds concisely.'),
            AIMessage::user('What is 2+2?'),
        ];

        $chunks = [];
        $fullContent = '';
        $startTime = microtime(true);

        foreach ($this->driver->sendStreamingMessage($messages, [
            'model' => 'grok-3-mini',
            'max_tokens' => 50,
            'temperature' => 0,
        ]) as $chunk) {
            $this->assertInstanceOf(AIResponse::class, $chunk);
            $chunks[] = $chunk;
            $fullContent .= $chunk->content;
        }

        $responseTime = (microtime(true) - $startTime) * 1000;

        $this->logTestStep('✅ Received ' . count($chunks) . ' chunks in ' . round($responseTime) . 'ms');
        $this->logTestStep('📝 Response: ' . trim($fullContent));

        // Assertions
        $this->assertGreaterThan(0, count($chunks));
        $this->assertNotEmpty($fullContent);

        // The response should contain "4" since we asked for 2+2
        $this->assertStringContainsString('4', $fullContent, 'Response should contain the answer "4"');

        $this->logTestStep('✅ Conversation context streaming test completed');
    }

    #[Test]
    public function it_maintains_streaming_performance(): void
    {
        $this->logTestStep('🚀 Testing streaming performance characteristics');

        $message = AIMessage::user('List the first 10 prime numbers with brief explanations.');

        $chunks = [];
        $chunkTimes = [];
        $startTime = microtime(true);
        $lastChunkTime = $startTime;

        foreach ($this->driver->sendStreamingMessage($message, [
            'model' => 'grok-3-mini',
            'max_tokens' => 200,
            'temperature' => 0,
        ]) as $chunk) {
            $currentTime = microtime(true);
            $timeSinceLastChunk = ($currentTime - $lastChunkTime) * 1000;

            $chunks[] = $chunk;
            $chunkTimes[] = $timeSinceLastChunk;
            $lastChunkTime = $currentTime;
        }

        $totalTime = (microtime(true) - $startTime) * 1000;
        $avgChunkInterval = count($chunkTimes) > 0 ? array_sum($chunkTimes) / count($chunkTimes) : 0;
        $maxChunkInterval = count($chunkTimes) > 0 ? max($chunkTimes) : 0;

        $this->logTestStep('✅ Performance metrics:');
        $this->logTestStep('  📦 Total chunks: ' . count($chunks));
        $this->logTestStep('  ⏱️  Total time: ' . round($totalTime) . 'ms');
        $this->logTestStep('  📊 Avg chunk interval: ' . round($avgChunkInterval) . 'ms');
        $this->logTestStep('  📈 Max chunk interval: ' . round($maxChunkInterval) . 'ms');
        $this->logTestStep('  🚀 Chunks per second: ' . round(count($chunks) / ($totalTime / 1000), 2));

        // Performance assertions
        $this->assertLessThan(30000, $totalTime, 'Total response time should be reasonable');
        $this->assertLessThan(5000, $maxChunkInterval, 'No single chunk should take too long');

        if (count($chunks) > 1) {
            $this->assertLessThan(2000, $avgChunkInterval, 'Average chunk interval should be reasonable');
        }

        $this->logTestStep('✅ Streaming performance test completed');
    }

    /**
     * Log test step with interpolation support.
     */
    protected function logTestStep(string $message, array $context = []): void
    {
        foreach ($context as $key => $value) {
            $message = str_replace('{' . $key . '}', $value, $message);
        }
        echo "\n  " . $message;
    }
}
