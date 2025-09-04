<?php

namespace JTD\LaravelAI\Tests\E2E\Drivers\Gemini;

use JTD\LaravelAI\Drivers\Gemini\GeminiDriver;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Models\AIResponse;
use JTD\LaravelAI\Tests\E2E\E2ETestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Advanced E2E Integration Tests for Gemini Driver
 *
 * Tests advanced scenarios including multimodal content, safety settings,
 * parameter variations, and edge cases with real Gemini API.
 */
#[Group('e2e')]
#[Group('gemini')]
#[Group('integration')]
class GeminiAdvancedIntegrationTest extends E2ETestCase
{
    protected GeminiDriver $driver;

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
    public function it_can_handle_multimodal_content(): void
    {
        $this->logTestStart('Testing multimodal content processing');

        // Create test images
        $redPixel = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8/5+hHgAHggJ/PchI7wAAAABJRU5ErkJggg==');

        $this->logTestStep('Testing single image analysis...');

        try {
            $singleImageMessage = $this->driver->createMultimodalMessage(
                'What color is this image? Answer with just the color name.',
                [['data' => $redPixel, 'mime_type' => 'image/png']]
            );

            $startTime = microtime(true);
            $response = $this->driver->sendMessage($singleImageMessage, [
                'model' => 'gemini-1.5-pro',
                'max_tokens' => 10,
            ]);
            $responseTime = (microtime(true) - $startTime) * 1000;

            $this->logTestStep('✅ Single image processed in {time}ms', ['time' => round($responseTime)]);
            $this->logTestStep('🎨 Color detected: "{content}"', ['content' => trim($response->content)]);

            $this->assertInstanceOf(AIResponse::class, $response);
            $this->assertNotEmpty($response->content);
            $this->assertEquals('gemini-1.5-pro', $response->model);
            $this->assertGreaterThan(250, $response->tokenUsage->totalTokens); // Images add significant tokens
        } catch (\Exception $e) {
            $this->logTestStep('❌ Single image test failed: ' . $e->getMessage());
            throw $e;
        }

        $this->logTestEnd('Multimodal content test completed');
    }

    #[Test]
    public function it_can_handle_safety_settings(): void
    {
        $this->logTestStart('Testing safety settings');

        $safetySettings = [
            'HARM_CATEGORY_HARASSMENT' => 'BLOCK_MEDIUM_AND_ABOVE',
            'HARM_CATEGORY_HATE_SPEECH' => 'BLOCK_MEDIUM_AND_ABOVE',
            'HARM_CATEGORY_SEXUALLY_EXPLICIT' => 'BLOCK_MEDIUM_AND_ABOVE',
            'HARM_CATEGORY_DANGEROUS_CONTENT' => 'BLOCK_MEDIUM_AND_ABOVE',
        ];

        $this->logTestStep('Testing with strict safety settings...');

        $message = AIMessage::user('Tell me about online safety and digital citizenship.');

        $startTime = microtime(true);
        $response = $this->driver->sendMessage($message, [
            'model' => 'gemini-2.5-flash',
            'max_tokens' => 100,
            'safety_settings' => $safetySettings,
        ]);
        $responseTime = (microtime(true) - $startTime) * 1000;

        $this->logTestStep('✅ Safe content processed in {time}ms', ['time' => round($responseTime)]);
        $this->logTestStep('🛡️  Response: "{content}"', ['content' => substr(trim($response->content), 0, 100) . '...']);

        $this->assertInstanceOf(AIResponse::class, $response);
        $this->assertNotEmpty($response->content);

        // Check if safety ratings are included in metadata
        if (isset($response->metadata['safety_ratings'])) {
            $this->assertIsArray($response->metadata['safety_ratings']);
            $this->logTestStep('✅ Safety ratings included in response metadata');
        }

        $this->logTestEnd('Safety settings test completed');
    }

    #[Test]
    public function it_can_handle_parameter_variations(): void
    {
        $this->logTestStart('Testing parameter variations');

        $message = AIMessage::user('Write a creative short story about a robot.');

        // Test different temperature settings
        $temperatures = [0.0, 0.5, 1.0];

        foreach ($temperatures as $temp) {
            $this->logTestStep('Testing temperature: ' . $temp);

            $startTime = microtime(true);
            $response = $this->driver->sendMessage($message, [
                'model' => 'gemini-2.5-flash',
                'max_tokens' => 50,
                'temperature' => $temp,
            ]);
            $responseTime = (microtime(true) - $startTime) * 1000;

            $this->assertInstanceOf(AIResponse::class, $response);
            $this->assertNotEmpty($response->content);

            $this->logTestStep('✅ Temperature ' . $temp . ': ' . round($responseTime) . 'ms, ' . $response->tokenUsage->totalTokens . ' tokens');
        }

        // Test different max_tokens settings
        $tokenLimits = [10, 50, 100];

        foreach ($tokenLimits as $limit) {
            $this->logTestStep('Testing max_tokens: ' . $limit);

            $startTime = microtime(true);
            $response = $this->driver->sendMessage($message, [
                'model' => 'gemini-2.5-flash',
                'max_tokens' => $limit,
                'temperature' => 0.5,
            ]);
            $responseTime = (microtime(true) - $startTime) * 1000;

            $this->assertInstanceOf(AIResponse::class, $response);
            $this->assertNotEmpty($response->content);
            $this->assertLessThanOrEqual($limit + 10, $response->tokenUsage->output_tokens); // Allow small margin

            $this->logTestStep('✅ Max tokens ' . $limit . ': ' . $response->tokenUsage->output_tokens . ' actual tokens, ' . round($responseTime) . 'ms');
        }

        $this->logTestEnd('Parameter variations test completed');
    }

    #[Test]
    public function it_can_handle_long_conversations(): void
    {
        $this->logTestStart('Testing long conversation handling');

        $messages = [
            AIMessage::user('Hello, I want to discuss artificial intelligence.'),
            AIMessage::assistant('Hello! I\'d be happy to discuss AI with you. What aspect interests you most?'),
            AIMessage::user('I\'m curious about machine learning algorithms.'),
            AIMessage::assistant('Machine learning is fascinating! There are supervised, unsupervised, and reinforcement learning approaches.'),
            AIMessage::user('Can you explain supervised learning?'),
            AIMessage::assistant('Supervised learning uses labeled training data to learn patterns and make predictions on new data.'),
            AIMessage::user('What are some common supervised learning algorithms?'),
        ];

        $this->logTestStep('Processing conversation with ' . count($messages) . ' messages...');

        $startTime = microtime(true);
        $response = $this->driver->sendMessages($messages, [
            'model' => 'gemini-2.5-flash',
            'max_tokens' => 100,
        ]);
        $responseTime = (microtime(true) - $startTime) * 1000;

        $this->logTestStep('✅ Long conversation processed in ' . round($responseTime) . 'ms');
        $this->logTestStep('💬 Response: "' . substr(trim($response->content), 0, 100) . '..."');

        $this->assertInstanceOf(AIResponse::class, $response);
        $this->assertNotEmpty($response->content);
        $this->assertGreaterThan(100, $response->tokenUsage->input_tokens); // Should have significant input tokens

        // Response should be contextually relevant to supervised learning
        $content = strtolower($response->content);
        $this->assertTrue(
            str_contains($content, 'algorithm') ||
            str_contains($content, 'learning') ||
            str_contains($content, 'supervised'),
            'Response should be contextually relevant to the conversation'
        );

        $this->logTestEnd('Long conversation test completed');
    }

    #[Test]
    public function it_can_handle_edge_cases(): void
    {
        $this->logTestStart('Testing edge cases');

        // Test empty message handling
        $this->logTestStep('Testing empty message handling...');
        try {
            $emptyMessage = AIMessage::user('');
            $response = $this->driver->sendMessage($emptyMessage, [
                'model' => 'gemini-2.5-flash',
                'max_tokens' => 10,
            ]);

            // If successful, log it
            $this->logTestStep('✅ Empty message handled gracefully');
            $this->assertInstanceOf(AIResponse::class, $response);
        } catch (\Exception $e) {
            $this->logTestStep('✅ Empty message properly rejected: ' . $e->getMessage());
            $this->assertNotEmpty($e->getMessage());
        }

        // Test special characters
        $this->logTestStep('Testing special characters...');
        $specialMessage = AIMessage::user('Handle these: 🚀 émojis and spëcial çharacters! 中文 العربية');

        $startTime = microtime(true);
        $response = $this->driver->sendMessage($specialMessage, [
            'model' => 'gemini-2.5-flash',
            'max_tokens' => 50,
        ]);
        $responseTime = (microtime(true) - $startTime) * 1000;

        $this->assertInstanceOf(AIResponse::class, $response);
        $this->assertNotEmpty($response->content);

        $this->logTestStep('✅ Special characters handled in ' . round($responseTime) . 'ms');

        $this->logTestEnd('Edge cases test completed');
    }

    #[Test]
    public function it_can_sync_models_with_statistics(): void
    {
        $this->logTestStart('Testing model synchronization with statistics');

        $startTime = microtime(true);
        $result = $this->driver->syncModels(true); // Force refresh
        $responseTime = (microtime(true) - $startTime) * 1000;

        $this->logTestStep('✅ Models synced in {time}ms', ['time' => round($responseTime)]);
        $this->logTestStep('📊 Synced {count} models', ['count' => $result['models_synced']]);

        // Assertions
        $this->assertIsArray($result);
        $this->assertEquals('success', $result['status']);
        $this->assertGreaterThan(0, $result['models_synced']);
        $this->assertArrayHasKey('statistics', $result);
        $this->assertArrayHasKey('cached_until', $result);

        if (isset($result['statistics'])) {
            $stats = $result['statistics'];
            $this->logTestStep('📈 Statistics: Total={total}, Vision={vision}, Functions={functions}', [
                'total' => $stats['total'] ?? 0,
                'vision' => $stats['by_capability']['vision'] ?? 0,
                'functions' => $stats['by_capability']['function_calling'] ?? 0,
            ]);

            // Verify statistics structure
            $this->assertArrayHasKey('total', $stats);
            $this->assertArrayHasKey('by_capability', $stats);
            $this->assertIsArray($stats['by_capability']);
        }

        $this->logTestEnd('Model synchronization with statistics test completed');
    }

    #[Test]
    public function it_can_estimate_tokens_accurately(): void
    {
        $this->logTestStart('Testing token estimation accuracy');

        $text = 'This is a test message for token estimation accuracy with multiple sentences and various words.';
        $estimated = $this->driver->estimateTokens($text);

        $this->logTestStep('📊 Estimated tokens: ' . $estimated);

        // Send the actual message to get real token count
        $message = AIMessage::user($text);
        $response = $this->driver->sendMessage($message, [
            'model' => 'gemini-2.5-flash',
            'max_tokens' => 20,
        ]);

        $actualInput = $response->tokenUsage->input_tokens;

        $this->logTestStep('📊 Actual input tokens: ' . $actualInput);

        // Estimation should be reasonably close (within 50% margin)
        $this->assertGreaterThan($actualInput * 0.5, $estimated);
        $this->assertLessThan($actualInput * 1.5, $estimated);

        $accuracy = (1 - abs($estimated - $actualInput) / $actualInput) * 100;
        $this->logTestStep('🎯 Estimation accuracy: ' . round($accuracy, 1) . '%');

        $this->logTestEnd('Token estimation accuracy test completed');
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
