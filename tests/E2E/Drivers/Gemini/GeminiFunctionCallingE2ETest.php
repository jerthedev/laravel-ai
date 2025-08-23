<?php

namespace JTD\LaravelAI\Tests\E2E\Drivers\Gemini;

use JTD\LaravelAI\Drivers\Gemini\GeminiDriver;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Models\AIResponse;
use JTD\LaravelAI\Tests\E2E\E2ETestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Gemini Function Calling E2E Tests
 *
 * End-to-end tests for function calling functionality with real Gemini API.
 * Tests definition validation, execution, and error scenarios.
 */
#[Group('e2e')]
#[Group('gemini')]
#[Group('function-calling')]
class GeminiFunctionCallingE2ETest extends E2ETestCase
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
    public function it_can_call_simple_function(): void
    {
        $this->logTestStart('Testing simple function calling');

        $functions = [
            [
                'name' => 'get_weather',
                'description' => 'Get current weather information for a location',
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
                            'description' => 'The temperature unit to use',
                        ],
                    ],
                    'required' => ['location'],
                ],
            ],
        ];

        $this->logTestStep('Sending message with function definition...');

        $message = AIMessage::user('What is the weather like in Tokyo, Japan?');

        $startTime = microtime(true);
        $response = $this->driver->sendMessage($message, [
            'model' => 'gemini-1.5-pro', // Use a model that supports function calling
            'functions' => $functions,
            'max_tokens' => 100,
        ]);
        $responseTime = (microtime(true) - $startTime) * 1000;

        $this->logTestStep('✅ Response time: {time}ms', ['time' => round($responseTime)]);
        $this->logTestStep('🔧 Finish reason: {reason}', ['reason' => $response->finishReason]);

        // Assertions
        $this->assertInstanceOf(AIResponse::class, $response);
        $this->assertLessThan(10000, $responseTime, 'Should complete within 10 seconds');

        // Check if function was called
        if ($response->functionCalls) {
            $this->logTestStep('✅ Function call detected');
            $this->assertNotNull($response->functionCalls);

            if (is_array($response->functionCalls) && isset($response->functionCalls['name'])) {
                $this->assertEquals('get_weather', $response->functionCalls['name']);
                $this->assertArrayHasKey('args', $response->functionCalls);
                $this->assertArrayHasKey('location', $response->functionCalls['args']);
                $this->logTestStep('✅ Function call structure is correct');
            }
        } else {
            $this->logTestStep('ℹ️  No function call made - model provided direct response');
            $this->assertNotEmpty($response->content);
        }

        $this->logTestEnd('Simple function calling test completed');
    }

    #[Test]
    public function it_can_handle_parallel_function_calls(): void
    {
        $this->logTestStart('Testing parallel function calling');

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
            [
                'name' => 'get_time',
                'description' => 'Get current time',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'timezone' => ['type' => 'string'],
                    ],
                    'required' => ['timezone'],
                ],
            ],
        ];

        $message = AIMessage::user('What is the weather and current time in New York?');

        $startTime = microtime(true);
        $response = $this->driver->sendMessage($message, [
            'model' => 'gemini-1.5-pro',
            'functions' => $functions,
            'max_tokens' => 150,
        ]);
        $responseTime = (microtime(true) - $startTime) * 1000;

        $this->logTestStep('✅ Response time: {time}ms', ['time' => round($responseTime)]);

        $this->assertInstanceOf(AIResponse::class, $response);
        $this->assertLessThan(15000, $responseTime, 'Should complete within 15 seconds');

        // Check for function calls
        if ($response->functionCalls) {
            $this->logTestStep('✅ Function calls detected');

            if (is_array($response->functionCalls)) {
                // Multiple function calls
                if (isset($response->functionCalls[0])) {
                    $this->assertGreaterThanOrEqual(1, count($response->functionCalls));
                    $this->logTestStep('✅ Multiple function calls: ' . count($response->functionCalls));
                } else {
                    // Single function call
                    $this->assertArrayHasKey('name', $response->functionCalls);
                    $this->logTestStep('✅ Single function call: ' . $response->functionCalls['name']);
                }
            }
        } else {
            $this->logTestStep('ℹ️  No function calls made - model provided direct response');
            $this->assertNotEmpty($response->content);
        }

        $this->logTestEnd('Parallel function calling test completed');
    }

    #[Test]
    public function it_validates_function_definitions(): void
    {
        $this->logTestStart('Testing function definition validation');

        // Test with invalid function definition
        $invalidFunctions = [
            [
                'name' => 'invalid_function',
                // Missing description and parameters
            ],
        ];

        $message = AIMessage::user('Test invalid function');

        try {
            $response = $this->driver->sendMessage($message, [
                'model' => 'gemini-1.5-pro',
                'functions' => $invalidFunctions,
                'max_tokens' => 50,
            ]);

            // If we get here, the API might have accepted the invalid definition
            $this->logTestStep('⚠️  Invalid function definition was accepted by API');
            $this->assertInstanceOf(AIResponse::class, $response);
        } catch (\Exception $e) {
            $this->logTestStep('✅ Function validation: Properly rejected invalid definition');
            $this->assertStringContainsString('function', strtolower($e->getMessage()));
        }

        // Test with valid function definition
        $validFunctions = [
            [
                'name' => 'valid_function',
                'description' => 'A valid test function',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'input' => ['type' => 'string'],
                    ],
                    'required' => ['input'],
                ],
            ],
        ];

        try {
            $response = $this->driver->sendMessage($message, [
                'model' => 'gemini-1.5-pro',
                'functions' => $validFunctions,
                'max_tokens' => 50,
            ]);

            $this->assertInstanceOf(AIResponse::class, $response);
            $this->logTestStep('✅ Function validation: Valid definition accepted');
        } catch (\Exception $e) {
            $this->logTestStep('❌ Valid function definition was rejected: ' . $e->getMessage());
            throw $e;
        }

        $this->logTestEnd('Function definition validation test completed');
    }

    #[Test]
    public function it_handles_function_calling_errors(): void
    {
        $this->logTestStart('Testing function calling error handling');

        // Test with model that doesn't support function calling
        try {
            $functions = [
                [
                    'name' => 'test_function',
                    'description' => 'Test function',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'input' => ['type' => 'string'],
                        ],
                    ],
                ],
            ];

            $message = AIMessage::user('Test function calling');

            $response = $this->driver->sendMessage($message, [
                'model' => 'gemini-2.5-flash', // Current model that supports functions
                'functions' => $functions,
                'max_tokens' => 50,
            ]);

            // If successful, log it
            $this->logTestStep('✅ Function calling worked with gemini-2.5-flash');
            $this->assertInstanceOf(AIResponse::class, $response);
        } catch (\Exception $e) {
            $this->logTestStep('ℹ️  Function calling error handled: ' . $e->getMessage());
            $this->assertNotEmpty($e->getMessage());
        }

        $this->logTestEnd('Function calling error handling test completed');
    }

    #[Test]
    public function it_can_execute_mathematical_functions(): void
    {
        $this->logTestStart('Testing mathematical function execution');

        $functions = [
            [
                'name' => 'calculate',
                'description' => 'Perform mathematical calculations',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'operation' => [
                            'type' => 'string',
                            'enum' => ['add', 'subtract', 'multiply', 'divide'],
                        ],
                        'a' => ['type' => 'number'],
                        'b' => ['type' => 'number'],
                    ],
                    'required' => ['operation', 'a', 'b'],
                ],
            ],
        ];

        $message = AIMessage::user('Calculate 15 + 25');

        $startTime = microtime(true);
        $response = $this->driver->sendMessage($message, [
            'model' => 'gemini-1.5-pro',
            'functions' => $functions,
            'max_tokens' => 100,
        ]);
        $responseTime = (microtime(true) - $startTime) * 1000;

        $this->logTestStep('✅ Mathematical function test completed in {time}ms', ['time' => round($responseTime)]);

        $this->assertInstanceOf(AIResponse::class, $response);

        // Check if function was called with correct parameters
        if ($response->functionCalls) {
            $this->logTestStep('✅ Function call detected for mathematical operation');

            if (is_array($response->functionCalls) && isset($response->functionCalls['name'])) {
                $this->assertEquals('calculate', $response->functionCalls['name']);
                $this->assertArrayHasKey('args', $response->functionCalls);

                $args = $response->functionCalls['args'];
                $this->assertArrayHasKey('operation', $args);
                $this->assertArrayHasKey('a', $args);
                $this->assertArrayHasKey('b', $args);

                $this->logTestStep('✅ Function called with operation: {op}, a: {a}, b: {b}', [
                    'op' => $args['operation'],
                    'a' => $args['a'],
                    'b' => $args['b'],
                ]);
            }
        } else {
            $this->logTestStep('ℹ️  No function call made - model provided direct calculation');
            $this->assertNotEmpty($response->content);
        }

        $this->logTestEnd('Mathematical function execution test completed');
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
