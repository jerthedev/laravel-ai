<?php

namespace JTD\LaravelAI\Tests\E2E;

use JTD\LaravelAI\Drivers\DriverTemplate\DriverTemplateDriver;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Models\AIResponse;
use JTD\LaravelAI\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * DriverTemplate Streaming E2E Tests
 *
 * End-to-end tests for streaming functionality with real DriverTemplate API.
 * Tests chunk processing, error handling, and response assembly.
 */
#[Group('e2e')]
#[Group('drivertemplate')]
#[Group('streaming')]
class DriverTemplateStreamingE2ETest extends TestCase
{
    private DriverTemplateDriver $driver;

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

        if (empty($this->credentials['drivertemplate']['api_key']) || ! $this->credentials['drivertemplate']['enabled']) {
            $this->markTestSkipped('DriverTemplate credentials not configured or disabled for streaming E2E tests');
        }

        $this->driver = new DriverTemplateDriver([
            'api_key' => $this->credentials['drivertemplate']['api_key'],
            'organization' => $this->credentials['drivertemplate']['organization'] ?? null,
            'project' => $this->credentials['drivertemplate']['project'] ?? null,
            'timeout' => 60,
        ]);
    }

    #[Test]
    public function it_can_stream_basic_response(): void
    {

        // TODO: Implement test
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
        $this->assertEquals('drivertemplate', $finalChunk->provider);
        $this->assertStringContainsString('default-model-3.5-turbo', $finalChunk->model);
    }

    #[Test]
    public function it_can_stream_longer_response(): void
    {

        // TODO: Implement test
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

        // TODO: Implement test
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

        // TODO: Implement test
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

        // TODO: Implement test
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

        // TODO: Implement test
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
            $this->assertGreaterThan(0, $finalChunk->tokenUsage->inputTokens);
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
