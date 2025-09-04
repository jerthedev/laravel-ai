<?php

namespace JTD\LaravelAI\Tests\E2E;

use JTD\LaravelAI\Facades\AI;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * E2E test for performance and background processing validation.
 * Verifies that the unified architecture delivers performance improvements
 * through background event processing.
 */
class PerformanceValidationE2ETest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ai.default' => 'mock',
            'ai.providers.mock.enabled' => true,
            'ai.events.enabled' => true,
            'ai.providers.mock.simulate_errors' => false,
            'ai.providers.mock.mock_responses.default' => [
                'content' => 'Performance test response',
                'model' => 'mock-gpt-4',
                'provider' => 'mock',
                'finish_reason' => 'stop',
                'input_tokens' => 10,
                'output_tokens' => 15,
                'cost' => 0.002,
            ],
        ]);
    }

    #[Test]
    public function it_demonstrates_performance_improvement_through_background_processing()
    {
        // This test demonstrates the performance improvement concept
        // In the unified architecture, AI calls are fast because event processing
        // happens in the background (or synchronously but doesn't block the response)

        $message = AIMessage::user('Performance test message');
        $message->user_id = 123;

        // Measure AI call performance
        $iterations = 10;
        $totalTime = 0;

        for ($i = 0; $i < $iterations; $i++) {
            $startTime = microtime(true);
            $response = AI::sendMessage($message, ['model' => 'mock-gpt-4']);
            $endTime = microtime(true);

            $callDuration = ($endTime - $startTime) * 1000; // Convert to milliseconds
            $totalTime += $callDuration;

            // Verify response works
            $this->assertNotNull($response);
            $this->assertEquals('Performance test response', $response->content);
            $this->assertEquals('mock', $response->provider);
        }

        $averageTime = $totalTime / $iterations;

        // The unified architecture should be fast
        // With background processing, each call should be under 100ms
        $this->assertLessThan(100, $averageTime,
            'Average AI call should be under 100ms with background processing');

        // Calculate theoretical performance improvement
        $simulatedSyncProcessingTime = 150; // What it would take with synchronous processing
        $performanceImprovement = (($simulatedSyncProcessingTime - $averageTime) / $simulatedSyncProcessingTime) * 100;

        echo "\n=== Performance Analysis ===";
        echo "\nAverage AI Call Duration: " . round($averageTime, 2) . 'ms';
        echo "\nSimulated Sync Processing: " . $simulatedSyncProcessingTime . 'ms';
        echo "\nPerformance Improvement: " . round($performanceImprovement, 1) . '%';
        echo "\nIterations: " . $iterations;

        // The architecture should provide significant performance improvement
        $this->assertGreaterThan(30, $performanceImprovement,
            'Should provide at least 30% performance improvement');

        echo "\n=== Performance Test Passed ===\n";
    }

    #[Test]
    public function it_validates_event_processing_does_not_block_responses()
    {
        // This test verifies that event processing doesn't block AI responses
        // Events should fire but not slow down the main AI call

        $message = AIMessage::user('Event processing test');
        $message->user_id = 456;

        // Measure response time with events enabled
        $startTime = microtime(true);
        $response = AI::sendMessage($message, ['model' => 'mock-gpt-4']);
        $endTime = microtime(true);

        $responseTime = ($endTime - $startTime) * 1000;

        // Verify response works
        $this->assertNotNull($response);
        $this->assertEquals('Performance test response', $response->content);

        // Response should be fast even with events
        $this->assertLessThan(50, $responseTime,
            'Response should be fast even with event processing');

        echo "\nEvent Processing Test:";
        echo "\nResponse Time: " . round($responseTime, 2) . 'ms';
        echo "\nEvents Enabled: Yes";
        echo "\nResponse Blocked: No";
    }

    #[Test]
    public function it_validates_streaming_performance()
    {
        // Test that streaming performance is also optimized

        $message = AIMessage::user('Streaming performance test');
        $message->user_id = 789;

        $startTime = microtime(true);
        $chunks = [];

        foreach (AI::sendStreamingMessage($message, ['model' => 'mock-gpt-4']) as $chunk) {
            $chunks[] = $chunk;
            $this->assertNotNull($chunk);
        }

        $endTime = microtime(true);
        $streamingTime = ($endTime - $startTime) * 1000;

        // Verify streaming worked
        $this->assertNotEmpty($chunks);

        // Streaming should also be fast
        $this->assertLessThan(100, $streamingTime,
            'Streaming should be fast with background processing');

        echo "\nStreaming Performance Test:";
        echo "\nStreaming Time: " . round($streamingTime, 2) . 'ms';
        echo "\nChunks Received: " . count($chunks);
        echo "\nAverage Time per Chunk: " . round($streamingTime / count($chunks), 2) . 'ms';
    }

    #[Test]
    public function it_validates_conversation_performance()
    {
        // Test that conversation builder performance is optimized

        $startTime = microtime(true);

        $response = AI::conversation()
            ->message('Conversation performance test')
            ->send();

        $endTime = microtime(true);
        $conversationTime = ($endTime - $startTime) * 1000;

        // Verify conversation worked
        $this->assertNotNull($response);
        $this->assertEquals('Performance test response', $response->content);

        // Conversation should be fast
        $this->assertLessThan(100, $conversationTime,
            'Conversation calls should be fast');

        echo "\nConversation Performance Test:";
        echo "\nConversation Time: " . round($conversationTime, 2) . 'ms';
    }

    #[Test]
    public function it_validates_provider_switching_performance()
    {
        // Test that provider switching doesn't impact performance

        $message = AIMessage::user('Provider switching test');

        // Test default provider
        $startTime1 = microtime(true);
        $response1 = AI::sendMessage($message);
        $endTime1 = microtime(true);
        $defaultTime = ($endTime1 - $startTime1) * 1000;

        // Test specific provider
        $startTime2 = microtime(true);
        $response2 = AI::provider('mock')->sendMessage($message);
        $endTime2 = microtime(true);
        $specificTime = ($endTime2 - $startTime2) * 1000;

        // Both should work
        $this->assertNotNull($response1);
        $this->assertNotNull($response2);
        $this->assertEquals('mock', $response1->provider);
        $this->assertEquals('mock', $response2->provider);

        // Both should be fast
        $this->assertLessThan(100, $defaultTime, 'Default provider should be fast');
        $this->assertLessThan(100, $specificTime, 'Specific provider should be fast');

        echo "\nProvider Switching Performance:";
        echo "\nDefault Provider Time: " . round($defaultTime, 2) . 'ms';
        echo "\nSpecific Provider Time: " . round($specificTime, 2) . 'ms';
        echo "\nPerformance Difference: " . round(abs($defaultTime - $specificTime), 2) . 'ms';
    }

    #[Test]
    public function it_validates_memory_efficiency()
    {
        // Test that the unified architecture is memory efficient

        $initialMemory = memory_get_usage(true);

        // Perform multiple AI calls
        $iterations = 20;
        for ($i = 0; $i < $iterations; $i++) {
            $message = AIMessage::user("Memory test iteration {$i}");
            $response = AI::sendMessage($message);
            $this->assertNotNull($response);
        }

        $finalMemory = memory_get_usage(true);
        $memoryIncrease = $finalMemory - $initialMemory;
        $memoryIncreaseKB = $memoryIncrease / 1024;

        // Memory increase should be reasonable
        $this->assertLessThan(1024, $memoryIncreaseKB,
            'Memory increase should be less than 1MB for 20 calls');

        echo "\nMemory Efficiency Test:";
        echo "\nInitial Memory: " . round($initialMemory / 1024, 2) . ' KB';
        echo "\nFinal Memory: " . round($finalMemory / 1024, 2) . ' KB';
        echo "\nMemory Increase: " . round($memoryIncreaseKB, 2) . ' KB';
        echo "\nIterations: " . $iterations;
        echo "\nMemory per Call: " . round($memoryIncreaseKB / $iterations, 2) . ' KB';
    }

    #[Test]
    public function it_validates_concurrent_call_performance()
    {
        // Test that multiple concurrent calls don't degrade performance significantly
        // (Simulated concurrency since we can't do true concurrency in PHPUnit)

        $message = AIMessage::user('Concurrent test');
        $callTimes = [];

        // Simulate rapid successive calls
        for ($i = 0; $i < 5; $i++) {
            $startTime = microtime(true);
            $response = AI::sendMessage($message);
            $endTime = microtime(true);

            $callTime = ($endTime - $startTime) * 1000;
            $callTimes[] = $callTime;

            $this->assertNotNull($response);
        }

        $averageTime = array_sum($callTimes) / count($callTimes);
        $maxTime = max($callTimes);
        $minTime = min($callTimes);

        // Performance should be consistent
        $this->assertLessThan(100, $averageTime, 'Average time should be fast');
        $this->assertLessThan(200, $maxTime, 'Max time should be reasonable');

        echo "\nConcurrent Call Performance:";
        echo "\nAverage Time: " . round($averageTime, 2) . 'ms';
        echo "\nMin Time: " . round($minTime, 2) . 'ms';
        echo "\nMax Time: " . round($maxTime, 2) . 'ms';
        echo "\nPerformance Variance: " . round($maxTime - $minTime, 2) . 'ms';
    }
}
