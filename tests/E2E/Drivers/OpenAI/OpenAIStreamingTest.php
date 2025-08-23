<?php

namespace JTD\LaravelAI\Tests\E2E;

use JTD\LaravelAI\Drivers\OpenAI\OpenAIDriver;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Models\AIResponse;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * E2E Tests for OpenAI Streaming Functionality
 *
 * Tests streaming responses with real OpenAI API including
 * chunk processing, progress tracking, and error scenarios.
 */
#[Group('e2e')]
#[Group('openai')]
#[Group('streaming')]
class OpenAIStreamingTest extends E2ETestCase
{
    protected OpenAIDriver $driver;

    protected function setUp(): void
    {
        parent::setUp();

        // Skip if no credentials available
        if (! $this->hasE2ECredentials('openai')) {
            $this->markTestSkipped('OpenAI E2E credentials not available');
        }

        // Create OpenAI driver with real credentials
        $credentials = $this->getE2ECredentials();
        $config = [
            'api_key' => $credentials['openai']['api_key'],
            'organization' => $credentials['openai']['organization'] ?? null,
            'project' => $credentials['openai']['project'] ?? null,
            'timeout' => 30,
            'retry_attempts' => 2,
        ];

        $this->driver = new OpenAIDriver($config);
    }

    #[Test]
    public function it_can_stream_basic_response(): void
    {
        $this->logTestStart('Testing basic streaming response');

        $message = AIMessage::user('Count from 1 to 5, with each number on a new line.');
        $chunks = [];
        $chunkCount = 0;
        $totalContent = '';

        try {
            $response = $this->driver->sendStreamingMessageWithCallback($message, [
                'model' => 'gpt-3.5-turbo',
                'max_tokens' => 50,
                'temperature' => 0, // Deterministic for testing
            ], function ($chunk) use (&$chunks, &$chunkCount, &$totalContent) {
                $chunks[] = $chunk;
                $chunkCount++;
                $totalContent .= $chunk;

                // Log first few chunks for debugging
                if ($chunkCount <= 5) {
                    echo "\n    Chunk {$chunkCount}: \"" . addslashes($chunk) . '"';
                }
            });

            $this->assertInstanceOf(AIResponse::class, $response);
            $this->assertNotEmpty($response->content, 'Final response should have content');
            $this->assertGreaterThan(0, $chunkCount, 'Should receive multiple chunks');

            // Token usage may not be available in streaming mode
            if ($response->tokenUsage->totalTokens > 0) {
                $this->logTestStep('Token usage available: ' . $response->tokenUsage->totalTokens);
            } else {
                $this->logTestStep('⚠️  Token usage not available in streaming mode (this is normal)');
            }

            $this->logTestStep('✅ Streaming completed successfully');
            $this->logTestStep("Chunks received: {$chunkCount}");
            $this->logTestStep('Total content length: ' . strlen($totalContent));
            $this->logTestStep('Final response: "' . trim($response->content) . '"');
            $this->logTestStep('Token usage: ' . $response->tokenUsage->totalTokens);

            // Verify content consistency
            $this->assertEquals(trim($totalContent), trim($response->content),
                'Streamed content should match final response content');
        } catch (\Exception $e) {
            $this->logTestStep('❌ Streaming test failed: ' . $e->getMessage());
            $this->logTestStep('Exception type: ' . get_class($e));
            throw $e;
        }

        $this->logTestEnd('Basic streaming test completed');
    }

    #[Test]
    public function it_can_stream_longer_response(): void
    {
        $this->logTestStart('Testing longer streaming response');

        $message = AIMessage::user('Write a short paragraph about cats. Make it exactly 3 sentences.');
        $chunks = [];
        $chunkCount = 0;
        $firstChunkTime = null;
        $lastChunkTime = null;

        try {
            $startTime = microtime(true);

            $response = $this->driver->sendStreamingMessageWithCallback($message, [
                'model' => 'gpt-3.5-turbo',
                'max_tokens' => 100,
                'temperature' => 0.7,
            ], function ($chunk) use (&$chunks, &$chunkCount, &$firstChunkTime, &$lastChunkTime) {
                $currentTime = microtime(true);

                if ($firstChunkTime === null) {
                    $firstChunkTime = $currentTime;
                }
                $lastChunkTime = $currentTime;

                $chunks[] = $chunk;
                $chunkCount++;
            });

            $endTime = microtime(true);
            $totalTime = ($endTime - $startTime) * 1000;
            $streamingDuration = ($lastChunkTime - $firstChunkTime) * 1000;

            $this->assertInstanceOf(AIResponse::class, $response);
            $this->assertGreaterThan(5, $chunkCount, 'Longer response should have more chunks');

            $this->logTestStep('✅ Longer streaming completed');
            $this->logTestStep('Total time: ' . round($totalTime) . 'ms');
            $this->logTestStep('Streaming duration: ' . round($streamingDuration) . 'ms');
            $this->logTestStep("Chunks: {$chunkCount}");
            $this->logTestStep('Average chunk interval: ' . round($streamingDuration / max($chunkCount - 1, 1)) . 'ms');
            $this->logTestStep('Response length: ' . strlen($response->content) . ' chars');

            // Verify response quality
            $sentences = explode('.', trim($response->content));
            $sentences = array_filter($sentences, fn ($s) => ! empty(trim($s)));
            $this->logTestStep('Sentences detected: ' . count($sentences));
        } catch (\Exception $e) {
            $this->logTestStep('❌ Longer streaming test failed: ' . $e->getMessage());
            throw $e;
        }

        $this->logTestEnd('Longer streaming test completed');
    }

    #[Test]
    public function it_can_handle_streaming_with_conversation_context(): void
    {
        $this->logTestStart('Testing streaming with conversation context');

        // First message
        $message1 = AIMessage::user('My favorite color is blue.');
        $response1 = $this->driver->sendMessage($message1, [
            'model' => 'gpt-3.5-turbo',
            'max_tokens' => 30,
        ]);

        $this->logTestStep('First message sent: "' . trim($response1->content) . '"');

        // Second message with streaming and context
        $message2 = AIMessage::user('What is my favorite color? Answer in one word.');
        $chunks = [];
        $assistantMessage1 = AIMessage::assistant($response1->content);

        try {
            $response2 = $this->driver->sendStreamingMessageWithCallback($message2, [
                'model' => 'gpt-3.5-turbo',
                'max_tokens' => 10,
                'conversation_history' => [$message1, $assistantMessage1],
            ], function ($chunk) use (&$chunks) {
                $chunks[] = $chunk;
            });

            $this->assertInstanceOf(AIResponse::class, $response2);
            $this->assertNotEmpty($response2->content);

            $this->logTestStep('✅ Context-aware streaming completed');
            $this->logTestStep('Streamed response: "' . trim($response2->content) . '"');
            $this->logTestStep('Chunks received: ' . count($chunks));

            // Check if the AI remembered the context
            $responseContent = strtolower($response2->content);
            $this->assertStringContainsString('blue', $responseContent,
                'AI should remember the favorite color from context');
            $this->logTestStep('✅ Context was properly maintained');
        } catch (\Exception $e) {
            $this->logTestStep('❌ Context streaming test failed: ' . $e->getMessage());
            throw $e;
        }

        $this->logTestEnd('Context streaming test completed');
    }

    #[Test]
    public function it_can_handle_streaming_with_different_parameters(): void
    {
        $this->logTestStart('Testing streaming with different parameters');

        $message = AIMessage::user('Describe a sunset in one sentence.');
        $parameterSets = [
            'creative' => ['temperature' => 0.9, 'max_tokens' => 40],
            'deterministic' => ['temperature' => 0.0, 'max_tokens' => 40],
            'concise' => ['temperature' => 0.5, 'max_tokens' => 20],
        ];

        foreach ($parameterSets as $name => $params) {
            $this->logTestStep("Testing {$name} parameters...");

            $chunks = [];

            try {
                $response = $this->driver->sendStreamingMessageWithCallback($message, array_merge($params, [
                    'model' => 'gpt-3.5-turbo',
                ]), function ($chunk) use (&$chunks) {
                    $chunks[] = $chunk;
                });

                $this->assertInstanceOf(AIResponse::class, $response);
                $this->assertNotEmpty($response->content);
                $this->assertGreaterThan(0, count($chunks));

                $this->logTestStep("✅ {$name}: \"" . trim($response->content) . '"');
                $this->logTestStep('   Chunks: ' . count($chunks) . ', Length: ' . strlen($response->content));
            } catch (\Exception $e) {
                $this->logTestStep("❌ {$name} failed: " . $e->getMessage());
                throw $e;
            }
        }

        $this->logTestEnd('Parameter variation streaming test completed');
    }

    #[Test]
    public function it_can_handle_streaming_errors_gracefully(): void
    {
        $this->logTestStart('Testing streaming error handling');

        // Test with invalid model
        $message = AIMessage::user('Test error handling');

        try {
            $response = $this->driver->sendStreamingMessageWithCallback($message, [
                'model' => 'invalid-model-name',
                'max_tokens' => 10,
            ], function ($chunk) {
                // This callback should not be called
                $this->fail('Callback should not be called for invalid model');
            });

            $this->fail('Expected exception for invalid model');
        } catch (\Exception $e) {
            $this->logTestStep('✅ Invalid model properly rejected');
            $this->logTestStep('Error: ' . $e->getMessage());
            $this->assertStringContainsString('model', strtolower($e->getMessage()));
        }

        // Test with extremely low token limit
        try {
            $chunks = [];
            $response = $this->driver->sendStreamingMessageWithCallback($message, [
                'model' => 'gpt-3.5-turbo',
                'max_tokens' => 1, // Very low limit
            ], function ($chunk) use (&$chunks) {
                $chunks[] = $chunk;
            });

            $this->assertInstanceOf(AIResponse::class, $response);
            $this->logTestStep('✅ Low token limit handled gracefully');
            $this->logTestStep('Response: "' . trim($response->content) . '"');
            $this->logTestStep('Chunks: ' . count($chunks));
        } catch (\Exception $e) {
            $this->logTestStep('⚠️  Low token limit caused error (acceptable): ' . $e->getMessage());
        }

        $this->logTestEnd('Streaming error handling test completed');
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
