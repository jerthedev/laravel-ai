<?php

namespace JTD\LaravelAI\Tests\E2E\Drivers\Gemini;

use JTD\LaravelAI\Drivers\Gemini\GeminiDriver;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Models\AIResponse;
use JTD\LaravelAI\Tests\E2E\E2ETestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Gemini Driver End-to-End Tests
 *
 * These tests run against the real Gemini API using credentials
 * from tests/credentials/e2e-credentials.json.
 *
 * Tests are skipped if credentials are not available.
 */
#[Group('e2e')]
#[Group('gemini')]
#[Group('basic')]
class GeminiDriverE2ETest extends E2ETestCase
{
    protected ?GeminiDriver $driver = null;

    protected array $credentials = [];

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
            'default_model' => 'gemini-pro',
            'timeout' => 30,
            'retry_attempts' => 2,
        ];

        $this->driver = new GeminiDriver($config);
    }

    #[Test]
    public function it_can_validate_real_credentials(): void
    {
        $result = $this->driver->validateCredentials();

        $this->assertEquals('valid', $result['status']);
        $this->assertTrue($result['valid']);
        $this->assertEquals('gemini', $result['provider']);
        $this->assertArrayHasKey('details', $result);
        $this->assertTrue($result['details']['api_accessible']);
    }

    #[Test]
    public function it_can_get_real_available_models(): void
    {
        $models = $this->driver->getAvailableModels();

        $this->assertIsArray($models);
        $this->assertNotEmpty($models);

        // Check that we have at least the basic models
        $modelIds = array_column($models, 'id');
        $this->assertContains('gemini-pro', $modelIds);

        // Verify model structure
        $firstModel = $models[0];
        $this->assertArrayHasKey('id', $firstModel);
        $this->assertArrayHasKey('name', $firstModel);
        $this->assertArrayHasKey('capabilities', $firstModel);
        $this->assertArrayHasKey('context_length', $firstModel);
    }

    #[Test]
    public function it_can_send_real_message(): void
    {
        $message = AIMessage::user('Hello! Please respond with exactly "Test successful" and nothing else.');

        $response = $this->driver->sendMessage($message, [
            'model' => 'gemini-pro',
            'max_tokens' => 10,
        ]);

        $this->assertInstanceOf(AIResponse::class, $response);
        $this->assertNotEmpty($response->content);
        $this->assertEquals('gemini-pro', $response->model);
        $this->assertEquals('gemini', $response->provider);
        $this->assertGreaterThan(0, $response->tokenUsage->totalTokens);
        $this->assertGreaterThan(0, $response->responseTimeMs);
    }

    #[Test]
    public function it_can_handle_real_multimodal_request(): void
    {
        // Create a simple test image (1x1 red pixel PNG)
        $imageData = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8/5+hHgAHggJ/PchI7wAAAABJRU5ErkJggg==');

        $message = $this->driver->createMultimodalMessage(
            'What color is this image? Please respond with just the color name.',
            [['data' => $imageData, 'mime_type' => 'image/png']]
        );

        $response = $this->driver->sendMessage($message, [
            'model' => 'gemini-1.5-pro', // Updated to use newer model
            'max_tokens' => 5,
        ]);

        $this->assertInstanceOf(AIResponse::class, $response);
        $this->assertNotEmpty($response->content);
        $this->assertEquals('gemini-1.5-pro', $response->model);
        $this->assertGreaterThan(250, $response->tokenUsage->totalTokens); // Images add ~258 tokens
    }

    #[Test]
    public function it_can_get_real_health_status(): void
    {
        $health = $this->driver->getHealthStatus();

        $this->assertIsArray($health);
        $this->assertContains($health['status'], ['healthy', 'degraded']);
        $this->assertEquals('gemini', $health['provider']);
        $this->assertArrayHasKey('details', $health);
        $this->assertTrue($health['details']['api_accessible']);
        $this->assertGreaterThan(0, $health['details']['models_available']);
    }

    #[Test]
    public function it_can_sync_real_models(): void
    {
        $result = $this->driver->syncModels(true); // Force refresh

        $this->assertIsArray($result);
        $this->assertEquals('success', $result['status']);
        $this->assertGreaterThan(0, $result['models_synced']);
        $this->assertArrayHasKey('statistics', $result);
        $this->assertArrayHasKey('cached_until', $result);
    }

    #[Test]
    public function it_can_calculate_real_costs(): void
    {
        $message = AIMessage::user('Hello');
        $response = $this->driver->sendMessage($message, [
            'model' => 'gemini-pro',
            'max_tokens' => 5,
        ]);

        $cost = $this->driver->calculateResponseCost($response);

        $this->assertIsArray($cost);
        $this->assertEquals('gemini-pro', $cost['model']);
        $this->assertGreaterThan(0, $cost['input_tokens']);
        $this->assertGreaterThan(0, $cost['output_tokens']);
        $this->assertGreaterThan(0, $cost['total_cost']);
        $this->assertEquals('USD', $cost['currency']);
    }

    #[Test]
    public function it_can_test_real_connectivity(): void
    {
        $result = $this->driver->testConnectivity();

        $this->assertIsArray($result);
        $this->assertTrue($result['connected']);
        $this->assertGreaterThan(0, $result['response_time_ms']);
        $this->assertGreaterThan(0, $result['models_count']);
    }

    #[Test]
    public function it_can_handle_safety_settings(): void
    {
        $message = AIMessage::user('Tell me about safety in AI systems.');

        $response = $this->driver->sendMessage($message, [
            'model' => 'gemini-pro',
            'max_tokens' => 50,
            'safety_settings' => [
                'HARM_CATEGORY_HARASSMENT' => 'BLOCK_MEDIUM_AND_ABOVE',
                'HARM_CATEGORY_HATE_SPEECH' => 'BLOCK_MEDIUM_AND_ABOVE',
            ],
        ]);

        $this->assertInstanceOf(AIResponse::class, $response);
        $this->assertNotEmpty($response->content);

        // Check if safety ratings are included in metadata
        if (isset($response->metadata['safety_ratings'])) {
            $this->assertIsArray($response->metadata['safety_ratings']);
        }
    }

    #[Test]
    public function it_can_handle_conversation_context(): void
    {
        $messages = [
            AIMessage::user('My name is John.'),
            AIMessage::assistant('Hello John! Nice to meet you.'),
            AIMessage::user('What is my name?'),
        ];

        $response = $this->driver->sendMessages($messages, [
            'model' => 'gemini-pro',
            'max_tokens' => 10,
        ]);

        $this->assertInstanceOf(AIResponse::class, $response);
        $this->assertNotEmpty($response->content);
        // The response should reference the name "John" from the conversation context
        $this->assertStringContainsStringIgnoringCase('john', strtolower($response->content));
    }

    #[Test]
    public function it_can_estimate_tokens_accurately(): void
    {
        $text = 'This is a test message for token estimation accuracy.';
        $estimated = $this->driver->estimateTokens($text);

        // Send the actual message to get real token count
        $message = AIMessage::user($text);
        $response = $this->driver->sendMessage($message, [
            'model' => 'gemini-pro',
            'max_tokens' => 5,
        ]);

        $actualInput = $response->tokenUsage->input_tokens;

        // Estimation should be reasonably close (within 50% margin)
        $this->assertGreaterThan($actualInput * 0.5, $estimated);
        $this->assertLessThan($actualInput * 1.5, $estimated);
    }

    #[Test]
    public function it_can_perform_comprehensive_health_check(): void
    {
        $healthCheck = $this->driver->performHealthCheck();

        $this->assertIsArray($healthCheck);
        $this->assertContains($healthCheck['overall_health'], ['healthy', 'unhealthy']);
        $this->assertArrayHasKey('checks', $healthCheck);

        $checks = $healthCheck['checks'];
        $this->assertArrayHasKey('configuration', $checks);
        $this->assertArrayHasKey('connectivity', $checks);
        $this->assertArrayHasKey('authentication', $checks);
        $this->assertArrayHasKey('models_access', $checks);
        $this->assertArrayHasKey('generation_access', $checks);

        // All checks should pass for valid credentials
        foreach ($checks as $checkName => $result) {
            $this->assertTrue($result['passed'], "Health check '{$checkName}' failed: " . $result['message']);
        }
    }

    #[Test]
    public function it_can_get_cost_efficiency_metrics(): void
    {
        $metrics = $this->driver->getCostEfficiencyMetrics('gemini-pro');

        $this->assertIsArray($metrics);
        $this->assertEquals('gemini-pro', $metrics['model']);
        $this->assertArrayHasKey('input_cost_per_1k', $metrics);
        $this->assertArrayHasKey('output_cost_per_1k', $metrics);
        $this->assertArrayHasKey('context_length', $metrics);
        $this->assertArrayHasKey('efficiency_score', $metrics);
        $this->assertGreaterThan(0, $metrics['efficiency_score']);
    }

    #[Test]
    public function it_can_compare_model_costs(): void
    {
        $input = 'This is a test message for cost comparison.';
        $comparisons = $this->driver->compareModelCosts($input, ['gemini-pro', 'gemini-1.5-pro']);

        $this->assertIsArray($comparisons);
        $this->assertCount(2, $comparisons);
        $this->assertArrayHasKey('gemini-pro', $comparisons);
        $this->assertArrayHasKey('gemini-1.5-pro', $comparisons);

        foreach ($comparisons as $modelId => $cost) {
            $this->assertEquals($modelId, $cost['model']);
            $this->assertArrayHasKey('estimated_total_cost', $cost);
            $this->assertGreaterThan(0, $cost['estimated_total_cost']);
        }
    }

    protected function tearDown(): void
    {
        $this->driver = null;
        parent::tearDown();
    }
}
