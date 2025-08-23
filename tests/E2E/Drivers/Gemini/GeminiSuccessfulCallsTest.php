<?php

namespace JTD\LaravelAI\Tests\E2E\Drivers\Gemini;

use JTD\LaravelAI\Drivers\Gemini\GeminiDriver;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Models\AIResponse;
use JTD\LaravelAI\Tests\E2E\E2ETestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Gemini Successful Calls E2E Tests
 *
 * Tests basic successful API calls and core functionality
 * with real Gemini API credentials.
 */
#[Group('e2e')]
#[Group('gemini')]
#[Group('successful-calls')]
class GeminiSuccessfulCallsTest extends E2ETestCase
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
    public function it_can_send_successful_message(): void
    {
        $this->logTestStart('Testing successful Gemini API call');

        $message = AIMessage::user('Say "Hello World" and nothing else.');

        $startTime = microtime(true);
        $response = $this->driver->sendMessage($message, [
            'model' => 'gemini-2.5-flash',
            'max_tokens' => 50,
            'temperature' => 0,
        ]);
        $responseTime = (microtime(true) - $startTime) * 1000;

        $this->logTestStep('✅ Response received in ' . round($responseTime) . 'ms');
        $this->logTestStep('📝 Content: "' . trim($response->content) . '"');
        $this->logTestStep('🔢 Tokens: ' . $response->tokenUsage->totalTokens . ' total');
        $this->logTestStep('🔧 Finish reason: ' . ($response->finishReason ?? 'null'));
        $this->logTestStep('🛡️  Metadata: ' . json_encode($response->metadata ?? []));

        // Assertions
        $this->assertInstanceOf(AIResponse::class, $response);
        $this->assertNotEmpty($response->content);
        $this->assertEquals('gemini-2.5-flash', $response->model);
        $this->assertEquals('gemini', $response->provider);
        $this->assertGreaterThan(0, $response->tokenUsage->totalTokens);
        $this->assertGreaterThan(0, $response->responseTimeMs);
        $this->assertLessThan(10000, $responseTime, 'Should respond within 10 seconds');

        $this->logTestEnd('Successful message test completed');
    }

    #[Test]
    public function it_can_get_available_models(): void
    {
        $this->logTestStart('Testing model availability');

        $startTime = microtime(true);
        $models = $this->driver->getAvailableModels();
        $responseTime = (microtime(true) - $startTime) * 1000;

        $this->logTestStep('✅ Models retrieved in ' . round($responseTime) . 'ms');
        $this->logTestStep('📋 Found ' . count($models) . ' models');

        // Log some model details
        $modelIds = array_column($models, 'id');
        $this->logTestStep('🤖 Available models: ' . implode(', ', array_slice($modelIds, 0, 5)) .
                          (count($modelIds) > 5 ? '...' : ''));

        // Assertions
        $this->assertIsArray($models);
        $this->assertNotEmpty($models);
        $this->assertContains('gemini-pro', $modelIds);

        // Check model structure
        $firstModel = $models[0];
        $this->assertArrayHasKey('id', $firstModel);
        $this->assertArrayHasKey('name', $firstModel);
        $this->assertArrayHasKey('capabilities', $firstModel);
        $this->assertArrayHasKey('context_length', $firstModel);
        $this->assertArrayHasKey('pricing', $firstModel);

        $this->logTestEnd('Model availability test completed');
    }

    #[Test]
    public function it_can_validate_credentials_successfully(): void
    {
        $this->logTestStart('Testing credential validation');

        $startTime = microtime(true);
        $result = $this->driver->validateCredentials();
        $responseTime = (microtime(true) - $startTime) * 1000;

        $this->logTestStep('✅ Credentials validated in ' . round($responseTime) . 'ms');
        $this->logTestStep('🔐 Status: ' . $result['status']);

        // Assertions
        $this->assertIsArray($result);
        $this->assertEquals('valid', $result['status']);
        $this->assertTrue($result['valid']);
        $this->assertEquals('gemini', $result['provider']);
        $this->assertArrayHasKey('details', $result);

        if (isset($result['details']['api_accessible'])) {
            $this->assertTrue($result['details']['api_accessible']);
        }

        $this->logTestEnd('Credential validation test completed');
    }

    #[Test]
    public function it_can_handle_conversation_context(): void
    {
        $this->logTestStart('Testing conversation context');

        $messages = [
            AIMessage::user('My name is Alice.'),
            AIMessage::assistant('Hello Alice! Nice to meet you.'),
            AIMessage::user('What is my name?'),
        ];

        $startTime = microtime(true);
        $response = $this->driver->sendMessages($messages, [
            'model' => 'gemini-2.5-flash',
            'max_tokens' => 50,
        ]);
        $responseTime = (microtime(true) - $startTime) * 1000;

        $this->logTestStep('✅ Conversation processed in ' . round($responseTime) . 'ms');
        $this->logTestStep('💬 Response: "' . trim($response->content) . '"');
        $this->logTestStep('🔧 Finish reason: ' . ($response->finishReason ?? 'null'));
        $this->logTestStep('🔢 Tokens: ' . $response->tokenUsage->totalTokens . ' total');
        $this->logTestStep('🛡️  Metadata: ' . json_encode($response->metadata ?? []));

        // Assertions
        $this->assertInstanceOf(AIResponse::class, $response);
        $this->assertNotEmpty($response->content);

        // The response should reference the name "Alice" from the conversation context
        $this->assertStringContainsStringIgnoringCase('alice', strtolower($response->content));

        $this->logTestEnd('Conversation context test completed');
    }

    #[Test]
    public function it_can_calculate_cost_accurately(): void
    {
        $this->logTestStart('Testing cost calculation');

        // Send a message to get actual token usage
        $message = AIMessage::user('Count from 1 to 10');
        $response = $this->driver->sendMessage($message, [
            'model' => 'gemini-2.5-flash',
            'max_tokens' => 50,
        ]);

        $this->logTestStep('📝 Message sent, calculating cost...');

        // Calculate cost based on actual usage
        $startTime = microtime(true);
        $cost = $this->driver->calculateResponseCost($response);
        $responseTime = (microtime(true) - $startTime) * 1000;

        $this->logTestStep('✅ Cost calculated in ' . round($responseTime) . 'ms');
        $this->logTestStep('💰 Total cost: $' . number_format($cost['total_cost'], 6));
        $this->logTestStep('🔢 Input tokens: ' . $cost['input_tokens'] . ', Output tokens: ' . $cost['output_tokens']);

        // Assertions
        $this->assertIsArray($cost);
        $this->assertEquals('gemini-2.5-flash', $cost['model']);
        $this->assertGreaterThan(0, $cost['input_tokens']);
        $this->assertGreaterThan(0, $cost['output_tokens']);
        $this->assertGreaterThan(0, $cost['total_cost']);
        $this->assertEquals('USD', $cost['currency']);

        // Cost should be reasonable (not too high for a simple message)
        $this->assertLessThan(0.10, $cost['total_cost'], 'Cost should be reasonable for simple message');

        $this->logTestEnd('Cost calculation test completed');
    }

    #[Test]
    public function it_can_handle_different_models(): void
    {
        $this->logTestStart('Testing different models');

        $models = ['gemini-2.5-flash', 'gemini-2.0-flash'];
        $message = AIMessage::user('Say "Hello from [MODEL_NAME]"');

        foreach ($models as $model) {
            $this->logTestStep('Testing model: ' . $model);

            try {
                $startTime = microtime(true);
                $response = $this->driver->sendMessage($message, [
                    'model' => $model,
                    'max_tokens' => 100,
                    'temperature' => 0,
                ]);
                $responseTime = (microtime(true) - $startTime) * 1000;

                $this->assertInstanceOf(AIResponse::class, $response);
                $this->assertEquals($model, $response->model);
                $this->assertNotEmpty($response->content);
                $this->assertGreaterThan(0, $response->tokenUsage->totalTokens);

                $this->logTestStep('✅ ' . $model . ': "' . trim($response->content) . '" (' . $response->tokenUsage->totalTokens . ' tokens, ' . round($responseTime) . 'ms)');
                $this->logTestStep('🔧 Finish reason: ' . ($response->finishReason ?? 'null'));
            } catch (\Exception $e) {
                $this->logTestStep('❌ ' . $model . ' failed: ' . $e->getMessage());

                // Don't fail the test if a specific model isn't available
                if (str_contains($e->getMessage(), 'not found') || str_contains($e->getMessage(), 'not available')) {
                    $this->logTestStep('ℹ️  Model ' . $model . ' not available, skipping');

                    continue;
                }

                throw $e;
            }
        }

        $this->logTestEnd('Different models test completed');
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
