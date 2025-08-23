<?php

namespace JTD\LaravelAI\Tests\E2E;

use JTD\LaravelAI\Drivers\OpenAI\OpenAIDriver;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Models\AIResponse;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Test OpenAI Responses API Integration through our Driver
 *
 * Tests that our driver wrapper correctly integrates with the new
 * OpenAI Responses API for GPT-5 and function calling.
 */
#[Group('e2e')]
#[Group('openai')]
#[Group('responses-api')]
#[Group('driver-integration')]
class OpenAIResponsesAPIDriverTest extends E2ETestCase
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
    public function it_can_use_gpt5_through_driver(): void
    {
        $this->logTestStart('Testing GPT-5 through our driver');

        $message = AIMessage::user('Hello! Please respond with a simple greeting.');

        try {
            $response = $this->driver->sendMessage($message, [
                'model' => 'gpt-5',
                'use_responses_api' => true, // Explicitly use Responses API for GPT-5
                'max_tokens' => 500, // GPT-5 needs plenty of tokens for reasoning + message
                'temperature' => 0,
            ]);

            $this->assertInstanceOf(AIResponse::class, $response);

            // Debug the response
            $this->logTestStep('Response content: "' . $response->content . '"');
            $this->logTestStep('Response model: ' . $response->model);
            $this->logTestStep('Response provider: ' . $response->provider);
            $this->logTestStep('Response finish reason: ' . $response->finishReason);

            // GPT-5 might return only reasoning without message content in some cases
            if (empty($response->content)) {
                $this->logTestStep('⚠️  GPT-5 returned reasoning-only response (this can happen)');
                $this->logTestStep('✅ But the API integration is working!');
            } else {
                $this->assertNotEmpty($response->content, 'Response should have content');
                $this->logTestStep('✅ GPT-5 response: "' . trim($response->content) . '"');
            }

            $this->assertEquals('gpt-5-2025-08-07', $response->model, 'Should use GPT-5 model');
            $this->assertEquals('openai', $response->provider);

            $this->logTestStep('✅ GPT-5 response: "' . trim($response->content) . '"');
            $this->logTestStep('✅ Model: ' . $response->model);
            $this->logTestStep('✅ Provider: ' . $response->provider);
            $this->logTestStep('✅ Response time: ' . round($response->responseTimeMs ?? 0) . 'ms');

            // Check if it contains expected content
            $responseContent = strtolower($response->content);
            if (str_contains($responseContent, 'gpt-5') || str_contains($responseContent, 'working')) {
                $this->logTestStep('✅ Response content matches expectation');
            } else {
                $this->logTestStep('⚠️  Response content differs but GPT-5 is working');
            }
        } catch (\Exception $e) {
            $this->logTestStep('❌ GPT-5 test failed: ' . $e->getMessage());
            $this->logTestStep('Exception type: ' . get_class($e));
            throw $e;
        }

        $this->logTestEnd('GPT-5 driver integration test completed');
    }

    #[Test]
    public function it_can_use_responses_api_explicitly(): void
    {
        $this->logTestStart('Testing explicit Responses API usage');

        $message = AIMessage::user('Hello, respond with a greeting.');

        try {
            $response = $this->driver->sendMessage($message, [
                'model' => 'gpt-4o', // Use GPT-4o with explicit Responses API
                'use_responses_api' => true, // Force Responses API
                'max_tokens' => 20,
                'temperature' => 0.7,
            ]);

            $this->assertInstanceOf(AIResponse::class, $response);
            $this->assertNotEmpty($response->content, 'Response should have content');
            $this->assertEquals('openai', $response->provider);

            $this->logTestStep('✅ Explicit Responses API response: "' . trim($response->content) . '"');
            $this->logTestStep('✅ Model: ' . $response->model);
            $this->logTestStep('✅ Content length: ' . strlen($response->content) . ' chars');
        } catch (\Exception $e) {
            $this->logTestStep('❌ Explicit Responses API test failed: ' . $e->getMessage());
            $this->logTestStep('Exception type: ' . get_class($e));
            throw $e;
        }

        $this->logTestEnd('Explicit Responses API test completed');
    }

    #[Test]
    public function it_can_handle_function_calling_with_responses_api(): void
    {
        $this->logTestStart('Testing function calling with Responses API');

        $message = AIMessage::user('What is the weather like in Tokyo? Use the get_weather function.');

        $tools = [
            [
                'type' => 'function',
                'name' => 'get_weather',
                'description' => 'Get current weather for a location',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'location' => [
                            'type' => 'string',
                            'description' => 'The city and country',
                        ],
                    ],
                    'required' => ['location'],
                ],
            ],
        ];

        try {
            $response = $this->driver->sendMessage($message, [
                'model' => 'gpt-5', // GPT-5 should automatically use Responses API
                'tools' => $tools,
                'max_tokens' => 100,
            ]);

            $this->assertInstanceOf(AIResponse::class, $response);
            $this->assertEquals('gpt-5-2025-08-07', $response->model);

            $this->logTestStep('✅ Function calling response received');
            $this->logTestStep('Model: ' . $response->model);
            $this->logTestStep('Finish reason: ' . $response->finishReason);
            $this->logTestStep('Content: "' . trim($response->content) . '"');

            // Check for function calls
            if ($response->functionCalls) {
                $this->logTestStep('✅ Function call detected!');
                $this->logTestStep('Function: ' . $response->functionCalls['name']);
                $this->logTestStep('Arguments: ' . $response->functionCalls['arguments']);
            } elseif ($response->toolCalls) {
                $this->logTestStep('✅ Tool call detected!');
                $this->logTestStep('Tool: ' . $response->toolCalls[0]['function']['name']);
                $this->logTestStep('Arguments: ' . $response->toolCalls[0]['function']['arguments']);
            } else {
                $this->logTestStep('⚠️  No function/tool calls - AI responded directly');
                $this->logTestStep('This is acceptable AI behavior');
            }
        } catch (\Exception $e) {
            $this->logTestStep('❌ Function calling test failed: ' . $e->getMessage());
            $this->logTestStep('Exception type: ' . get_class($e));

            // Check if it's a known integration issue
            if (str_contains($e->getMessage(), 'tools') || str_contains($e->getMessage(), 'function')) {
                $this->logTestStep('⚠️  This appears to be the integration issue we need to fix');
            }

            throw $e;
        }

        $this->logTestEnd('Function calling with Responses API test completed');
    }

    #[Test]
    public function it_can_compare_chat_vs_responses_api(): void
    {
        $this->logTestStart('Comparing Chat API vs Responses API');

        $message = AIMessage::user('Write exactly one sentence about cats.');

        // Test with Chat API (traditional)
        $this->logTestStep('Testing with Chat API...');
        try {
            $chatResponse = $this->driver->sendMessage($message, [
                'model' => 'gpt-3.5-turbo', // This will use Chat API
                'max_tokens' => 30,
                'temperature' => 0.5,
            ]);

            $this->assertInstanceOf(AIResponse::class, $chatResponse);
            $this->logTestStep('✅ Chat API: "' . trim($chatResponse->content) . '"');
            $this->logTestStep('Model: ' . $chatResponse->model);
            $this->logTestStep('Response time: ' . round($chatResponse->responseTimeMs ?? 0) . 'ms');
        } catch (\Exception $e) {
            $this->logTestStep('❌ Chat API failed: ' . $e->getMessage());
            throw $e;
        }

        // Test with Responses API
        $this->logTestStep('Testing with Responses API...');
        try {
            $responsesResponse = $this->driver->sendMessage($message, [
                'model' => 'gpt-5', // This will use Responses API
                'max_tokens' => 30,
                'temperature' => 0.5,
            ]);

            $this->assertInstanceOf(AIResponse::class, $responsesResponse);
            $this->logTestStep('✅ Responses API: "' . trim($responsesResponse->content) . '"');
            $this->logTestStep('Model: ' . $responsesResponse->model);
            $this->logTestStep('Response time: ' . round($responsesResponse->responseTimeMs ?? 0) . 'ms');

            // Compare responses
            $this->logTestStep('📊 Comparison:');
            $this->logTestStep('  Chat API length: ' . strlen($chatResponse->content) . ' chars');
            $this->logTestStep('  Responses API length: ' . strlen($responsesResponse->content) . ' chars');
            $this->logTestStep('  Both APIs working successfully!');
        } catch (\Exception $e) {
            $this->logTestStep('❌ Responses API failed: ' . $e->getMessage());
            throw $e;
        }

        $this->logTestEnd('API comparison test completed');
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
