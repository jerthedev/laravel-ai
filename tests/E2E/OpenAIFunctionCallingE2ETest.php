<?php

namespace JTD\LaravelAI\Tests\E2E;

use JTD\LaravelAI\Drivers\OpenAIDriver;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Models\AIResponse;
use JTD\LaravelAI\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * OpenAI Function Calling E2E Tests
 *
 * End-to-end tests for function calling functionality with real OpenAI API.
 * Tests definition validation, execution, and error scenarios.
 */
#[Group('e2e')]
#[Group('openai')]
#[Group('function-calling')]
class OpenAIFunctionCallingE2ETest extends TestCase
{
    private OpenAIDriver $driver;
    private array $credentials;

    protected function setUp(): void
    {
        parent::setUp();

        // Load credentials from E2E credentials file
        $credentialsPath = __DIR__ . '/../credentials/e2e-credentials.json';
        
        if (!file_exists($credentialsPath)) {
            $this->markTestSkipped('E2E credentials file not found for function calling tests');
        }

        $this->credentials = json_decode(file_get_contents($credentialsPath), true);
        
        if (empty($this->credentials['openai']['api_key']) || !$this->credentials['openai']['enabled']) {
            $this->markTestSkipped('OpenAI credentials not configured or disabled for function calling E2E tests');
        }

        $this->driver = new OpenAIDriver([
            'api_key' => $this->credentials['openai']['api_key'],
            'organization' => $this->credentials['openai']['organization'] ?? null,
            'project' => $this->credentials['openai']['project'] ?? null,
            'timeout' => 60,
        ]);
    }

    #[Test]
    public function it_can_call_weather_function(): void
    {
        $this->logTestStep('🚀 Testing weather function calling');

        $functions = [
            [
                'name' => 'get_weather',
                'description' => 'Get current weather for a location',
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
                            'description' => 'The temperature unit',
                        ],
                    ],
                    'required' => ['location'],
                ],
            ],
        ];

        $message = AIMessage::user('What is the weather like in Tokyo, Japan?');
        
        $startTime = microtime(true);
        $response = $this->driver->sendMessage($message, [
            'model' => 'gpt-3.5-turbo',
            'functions' => $functions,
            'function_call' => 'auto',
            'max_tokens' => 100,
        ]);
        $responseTime = (microtime(true) - $startTime) * 1000;

        $this->logTestStep("✅ Response time: {time}ms", ['time' => round($responseTime)]);
        $this->logTestStep("🔧 Finish reason: {reason}", ['reason' => $response->finishReason]);

        // Assertions
        $this->assertInstanceOf(AIResponse::class, $response);
        $this->assertLessThan(10000, $responseTime, 'Should complete within 10 seconds');
        
        if ($response->finishReason === 'function_call') {
            $this->assertNotNull($response->functionCalls, 'Should have function calls');
            $this->assertEquals('get_weather', $response->functionCalls['name']);
            
            // Parse arguments
            $arguments = json_decode($response->functionCalls['arguments'], true);
            $this->assertArrayHasKey('location', $arguments);
            $this->assertStringContainsIgnoringCase($arguments['location'], 'tokyo');
            
            $this->logTestStep("✅ Function called: {name}", ['name' => $response->functionCalls['name']]);
            $this->logTestStep("📍 Location: {location}", ['location' => $arguments['location']]);
        } else {
            $this->logTestStep("ℹ️  Model chose not to call function, responded directly");
            $this->assertNotEmpty($response->content, 'Should have content if no function call');
        }
    }

    #[Test]
    public function it_can_call_calculator_function(): void
    {
        $this->logTestStep('🚀 Testing calculator function calling');

        $functions = [
            [
                'name' => 'calculate',
                'description' => 'Perform mathematical calculations',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'expression' => [
                            'type' => 'string',
                            'description' => 'Mathematical expression to evaluate (e.g., "2 + 3 * 4")',
                        ],
                    ],
                    'required' => ['expression'],
                ],
            ],
        ];

        $message = AIMessage::user('Calculate 15 * 7 + 23');
        
        $startTime = microtime(true);
        $response = $this->driver->sendMessage($message, [
            'model' => 'gpt-3.5-turbo',
            'functions' => $functions,
            'function_call' => 'auto',
            'max_tokens' => 100,
        ]);
        $responseTime = (microtime(true) - $startTime) * 1000;

        $this->logTestStep("✅ Response time: {time}ms", ['time' => round($responseTime)]);
        $this->logTestStep("🔧 Finish reason: {reason}", ['reason' => $response->finishReason]);

        // Assertions
        $this->assertInstanceOf(AIResponse::class, $response);
        $this->assertLessThan(10000, $responseTime, 'Should complete within 10 seconds');
        
        if ($response->finishReason === 'function_call') {
            $this->assertNotNull($response->functionCalls, 'Should have function calls');
            $this->assertEquals('calculate', $response->functionCalls['name']);
            
            // Parse arguments
            $arguments = json_decode($response->functionCalls['arguments'], true);
            $this->assertArrayHasKey('expression', $arguments);
            
            $this->logTestStep("✅ Function called: {name}", ['name' => $response->functionCalls['name']]);
            $this->logTestStep("🧮 Expression: {expr}", ['expr' => $arguments['expression']]);
        } else {
            $this->logTestStep("ℹ️  Model chose not to call function, responded directly");
            $this->assertNotEmpty($response->content, 'Should have content if no function call');
        }
    }

    #[Test]
    public function it_can_use_tools_format(): void
    {
        $this->logTestStep('🚀 Testing tools format function calling');

        $tools = [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_random_fact',
                    'description' => 'Get a random interesting fact',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'category' => [
                                'type' => 'string',
                                'enum' => ['science', 'history', 'nature', 'technology'],
                                'description' => 'The category of fact to retrieve',
                            ],
                        ],
                        'required' => ['category'],
                    ],
                ],
            ],
        ];

        $message = AIMessage::user('Tell me an interesting science fact');
        
        $startTime = microtime(true);
        $response = $this->driver->sendMessage($message, [
            'model' => 'gpt-3.5-turbo',
            'tools' => $tools,
            'tool_choice' => 'auto',
            'max_tokens' => 100,
        ]);
        $responseTime = (microtime(true) - $startTime) * 1000;

        $this->logTestStep("✅ Response time: {time}ms", ['time' => round($responseTime)]);
        $this->logTestStep("🔧 Finish reason: {reason}", ['reason' => $response->finishReason]);

        // Assertions
        $this->assertInstanceOf(AIResponse::class, $response);
        $this->assertLessThan(10000, $responseTime, 'Should complete within 10 seconds');
        
        if ($response->finishReason === 'tool_calls') {
            $this->assertNotNull($response->toolCalls, 'Should have tool calls');
            $this->assertEquals('get_random_fact', $response->toolCalls[0]['function']['name']);
            
            // Parse arguments
            $arguments = json_decode($response->toolCalls[0]['function']['arguments'], true);
            $this->assertArrayHasKey('category', $arguments);
            $this->assertEquals('science', $arguments['category']);
            
            $this->logTestStep("✅ Tool called: {name}", ['name' => $response->toolCalls[0]['function']['name']]);
            $this->logTestStep("📚 Category: {cat}", ['cat' => $arguments['category']]);
        } else {
            $this->logTestStep("ℹ️  Model chose not to call tool, responded directly");
            $this->assertNotEmpty($response->content, 'Should have content if no tool call');
        }
    }

    #[Test]
    public function it_can_handle_multiple_functions(): void
    {
        $this->logTestStep('🚀 Testing multiple function definitions');

        $functions = [
            [
                'name' => 'get_weather',
                'description' => 'Get weather for a location',
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
                'description' => 'Get current time for a timezone',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'timezone' => ['type' => 'string'],
                    ],
                    'required' => ['timezone'],
                ],
            ],
            [
                'name' => 'translate_text',
                'description' => 'Translate text to another language',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'text' => ['type' => 'string'],
                        'target_language' => ['type' => 'string'],
                    ],
                    'required' => ['text', 'target_language'],
                ],
            ],
        ];

        $message = AIMessage::user('What time is it in New York?');
        
        $startTime = microtime(true);
        $response = $this->driver->sendMessage($message, [
            'model' => 'gpt-3.5-turbo',
            'functions' => $functions,
            'function_call' => 'auto',
            'max_tokens' => 100,
        ]);
        $responseTime = (microtime(true) - $startTime) * 1000;

        $this->logTestStep("✅ Response time: {time}ms", ['time' => round($responseTime)]);
        $this->logTestStep("🔧 Finish reason: {reason}", ['reason' => $response->finishReason]);

        // Assertions
        $this->assertInstanceOf(AIResponse::class, $response);
        $this->assertLessThan(10000, $responseTime, 'Should complete within 10 seconds');
        
        if ($response->finishReason === 'function_call') {
            $this->assertNotNull($response->functionCalls, 'Should have function calls');
            $this->assertEquals('get_time', $response->functionCalls['name']);
            
            // Parse arguments
            $arguments = json_decode($response->functionCalls['arguments'], true);
            $this->assertArrayHasKey('timezone', $arguments);
            
            $this->logTestStep("✅ Correct function selected: {name}", ['name' => $response->functionCalls['name']]);
            $this->logTestStep("🌍 Timezone: {tz}", ['tz' => $arguments['timezone']]);
        } else {
            $this->logTestStep("ℹ️  Model chose not to call function, responded directly");
        }
    }

    #[Test]
    public function it_can_force_specific_function_call(): void
    {
        $this->logTestStep('🚀 Testing forced function calling');

        $functions = [
            [
                'name' => 'search_database',
                'description' => 'Search the database for information',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => [
                            'type' => 'string',
                            'description' => 'Search query',
                        ],
                        'table' => [
                            'type' => 'string',
                            'description' => 'Database table to search',
                        ],
                    ],
                    'required' => ['query'],
                ],
            ],
        ];

        $message = AIMessage::user('Find all users with the name John');
        
        $startTime = microtime(true);
        $response = $this->driver->sendMessage($message, [
            'model' => 'gpt-3.5-turbo',
            'functions' => $functions,
            'function_call' => ['name' => 'search_database'], // Force this function
            'max_tokens' => 100,
        ]);
        $responseTime = (microtime(true) - $startTime) * 1000;

        $this->logTestStep("✅ Response time: {time}ms", ['time' => round($responseTime)]);
        $this->logTestStep("🔧 Finish reason: {reason}", ['reason' => $response->finishReason]);

        // Assertions
        $this->assertInstanceOf(AIResponse::class, $response);
        $this->assertLessThan(10000, $responseTime, 'Should complete within 10 seconds');
        $this->assertEquals('function_call', $response->finishReason, 'Should be forced to call function');
        $this->assertNotNull($response->functionCalls, 'Should have function calls');
        $this->assertEquals('search_database', $response->functionCalls['name']);
        
        // Parse arguments
        $arguments = json_decode($response->functionCalls['arguments'], true);
        $this->assertArrayHasKey('query', $arguments);
        $this->assertStringContainsIgnoringCase($arguments['query'], 'john');
        
        $this->logTestStep("✅ Forced function called: {name}", ['name' => $response->functionCalls['name']]);
        $this->logTestStep("🔍 Query: {query}", ['query' => $arguments['query']]);
    }

    #[Test]
    public function it_handles_invalid_function_definitions(): void
    {
        $this->logTestStep('🚀 Testing invalid function definition handling');

        $invalidFunctions = [
            [
                // Missing required 'name' field
                'description' => 'Invalid function without name',
                'parameters' => ['type' => 'object'],
            ],
        ];

        $message = AIMessage::user('Test message');
        
        try {
            $response = $this->driver->sendMessage($message, [
                'model' => 'gpt-3.5-turbo',
                'functions' => $invalidFunctions,
                'function_call' => 'auto',
            ]);
            
            $this->fail('Should have thrown an exception for invalid function definition');
            
        } catch (\Exception $e) {
            $this->logTestStep("✅ Error handled: " . $e->getMessage());
            $this->assertStringContainsIgnoringCase($e->getMessage(), 'name');
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
