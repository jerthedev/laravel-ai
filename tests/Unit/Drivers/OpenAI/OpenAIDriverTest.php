<?php

namespace JTD\LaravelAI\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\DataProvider;
use Mockery;
use OpenAI\Client as OpenAIClient;
use OpenAI\Resources\Chat;
use OpenAI\Resources\Models;
use JTD\LaravelAI\Drivers\OpenAIDriver;
use JTD\LaravelAI\Exceptions\OpenAI\OpenAIException;
use JTD\LaravelAI\Exceptions\OpenAI\OpenAIInvalidCredentialsException;
use JTD\LaravelAI\Exceptions\OpenAI\OpenAIQuotaExceededException;
use JTD\LaravelAI\Exceptions\OpenAI\OpenAIRateLimitException;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Models\AIResponse;
use JTD\LaravelAI\Models\TokenUsage;
use JTD\LaravelAI\Tests\TestCase;

/**
 * OpenAI Driver Unit Tests
 *
 * Tests the OpenAI driver implementation including API integration,
 * error handling, cost calculation, and streaming responses.
 */
#[Group('unit')]
#[Group('openai')]
class OpenAIDriverTest extends TestCase
{
    protected OpenAIDriver $driver;
    protected $mockClient; // Anonymous class, can't type hint
    protected array $validConfig;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validConfig = [
            'api_key' => 'sk-test-key-1234567890abcdef',
            'organization' => 'org-test123',
            'project' => 'proj-test456',
            'timeout' => 30,
            'connect_timeout' => 10,
            'retry_attempts' => 3,
            'retry_delay' => 1000,
        ];

        // Create driver with valid config
        $this->driver = new OpenAIDriver($this->validConfig);

        // Create a mock client using anonymous class since OpenAI\Client is final
        $this->mockClient = new class {
            public $chatMock;
            public $modelsMock;

            public function __construct() {
                $this->chatMock = Mockery::mock();
                $this->modelsMock = Mockery::mock();
            }

            public function chat() {
                return $this->chatMock;
            }

            public function models() {
                return $this->modelsMock;
            }
        };

        // Inject mock client for testing
        $this->driver->setClient($this->mockClient);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_can_create_openai_driver_with_valid_config(): void
    {
        $driver = new OpenAIDriver($this->validConfig);

        $this->assertInstanceOf(OpenAIDriver::class, $driver);
        $this->assertEquals('openai', $driver->getName());
        $this->assertInstanceOf(OpenAIClient::class, $driver->getClient());
    }

    #[Test]
    public function it_throws_exception_for_missing_api_key(): void
    {
        $this->expectException(OpenAIInvalidCredentialsException::class);
        $this->expectExceptionMessage('OpenAI API key is required');

        $config = $this->validConfig;
        unset($config['api_key']);

        new OpenAIDriver($config);
    }

    #[Test]
    public function it_throws_exception_for_invalid_api_key_format(): void
    {
        $this->expectException(OpenAIInvalidCredentialsException::class);
        $this->expectExceptionMessage('Invalid OpenAI API key format');

        $config = $this->validConfig;
        $config['api_key'] = 'invalid-key-format';

        new OpenAIDriver($config);
    }

    #[Test]
    #[DataProvider('validApiKeyProvider')]
    public function it_accepts_valid_api_key_formats(string $apiKey): void
    {
        $config = $this->validConfig;
        $config['api_key'] = $apiKey;

        $driver = new OpenAIDriver($config);

        $this->assertInstanceOf(OpenAIDriver::class, $driver);
    }

    public static function validApiKeyProvider(): array
    {
        return [
            'standard_key' => ['sk-1234567890abcdef1234567890abcdef'],
            'short_key' => ['sk-test123'],
            'long_key' => ['sk-' . str_repeat('a', 100)],
        ];
    }

    #[Test]
    public function it_can_set_and_get_custom_client(): void
    {
        $customClient = new class {
            public function chat() { return null; }
            public function models() { return null; }
        };

        $this->driver->setClient($customClient);

        $this->assertSame($customClient, $this->driver->getClient());
    }

    #[Test]
    public function it_handles_configuration_with_optional_parameters(): void
    {
        $minimalConfig = ['api_key' => 'sk-test123'];
        $driver = new OpenAIDriver($minimalConfig);

        $this->assertInstanceOf(OpenAIDriver::class, $driver);
        $this->assertEquals('openai', $driver->getName());
    }

    #[Test]
    public function it_handles_configuration_with_all_parameters(): void
    {
        $fullConfig = [
            'api_key' => 'sk-test123',
            'organization' => 'org-test',
            'project' => 'proj-test',
            'base_url' => 'https://custom.openai.com/v1',
            'timeout' => 60,
            'connect_timeout' => 15,
            'retry_attempts' => 5,
            'retry_delay' => 2000,
        ];

        $driver = new OpenAIDriver($fullConfig);

        $this->assertInstanceOf(OpenAIDriver::class, $driver);
    }

    #[Test]
    public function it_can_send_message_successfully(): void
    {
        $message = AIMessage::user('Hello, how are you?');
        $mockResponse = $this->createMockApiResponse([
            'choices' => [
                (object) [
                    'message' => (object) [
                        'content' => 'I am doing well, thank you!',
                        'role' => 'assistant',
                    ],
                    'finish_reason' => 'stop',
                ],
            ],
            'usage' => (object) [
                'promptTokens' => 10,
                'completionTokens' => 15,
                'totalTokens' => 25,
            ],
        ]);

        $this->mockClient->chatMock->shouldReceive('create')
            ->once()
            ->with(Mockery::type('array'))
            ->andReturn($mockResponse);

        $response = $this->driver->sendMessage($message);

        $this->assertInstanceOf(AIResponse::class, $response);
        $this->assertEquals('I am doing well, thank you!', $response->content);
        $this->assertEquals('openai', $response->provider);
        $this->assertEquals(25, $response->tokenUsage->totalTokens); // Updated to match mock data
    }

    #[Test]
    public function it_can_send_streaming_message(): void
    {
        $message = AIMessage::user('Hello, stream this!');

        // Mock streaming response chunks
        $mockStreamChunks = [
            $this->createMockStreamChunk('Hello'),
            $this->createMockStreamChunk(' there'),
            $this->createMockStreamChunk('!', true), // Final chunk
        ];

        $this->mockClient->chatMock->shouldReceive('createStreamed')
            ->once()
            ->with(Mockery::on(function ($params) {
                return isset($params['stream']) && $params['stream'] === true;
            }))
            ->andReturn($mockStreamChunks);

        $chunks = iterator_to_array($this->driver->sendStreamingMessage($message));

        $this->assertGreaterThan(0, count($chunks));
        $this->assertInstanceOf(AIResponse::class, $chunks[0]);
    }

    #[Test]
    public function it_can_get_available_models(): void
    {
        $mockModelsResponse = (object) [
            'data' => [
                (object) [
                    'id' => 'gpt-3.5-turbo',
                    'created' => 1677610602,
                    'owned_by' => 'openai',
                ],
                (object) [
                    'id' => 'gpt-4',
                    'created' => 1687882411,
                    'owned_by' => 'openai',
                ],
                (object) [
                    'id' => 'text-davinci-003', // This should be filtered out
                    'created' => 1669599635,
                    'owned_by' => 'openai',
                ],
            ],
        ];

        $this->mockClient->modelsMock->shouldReceive('list')
            ->once()
            ->andReturn($mockModelsResponse);

        $models = $this->driver->getAvailableModels();

        $this->assertIsArray($models);
        $this->assertCount(2, $models); // Only chat models should be returned

        $modelIds = array_column($models, 'id');
        $this->assertContains('gpt-3.5-turbo', $modelIds);
        $this->assertContains('gpt-4', $modelIds);
        $this->assertNotContains('text-davinci-003', $modelIds);

        // Check model structure
        $this->assertArrayHasKey('name', $models[0]);
        $this->assertArrayHasKey('description', $models[0]);
        $this->assertArrayHasKey('context_length', $models[0]);
        $this->assertArrayHasKey('capabilities', $models[0]);
        $this->assertArrayHasKey('pricing', $models[0]);
    }

    #[Test]
    public function it_throws_not_implemented_for_get_model_info(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('Method not yet implemented');

        $this->driver->getModelInfo('gpt-4');
    }

    #[Test]
    public function it_can_calculate_cost_for_message(): void
    {
        $message = AIMessage::user('Hello, how are you today?');

        $cost = $this->driver->calculateCost($message, 'gpt-3.5-turbo');

        $this->assertIsArray($cost);
        $this->assertArrayHasKey('model', $cost);
        $this->assertArrayHasKey('input_tokens', $cost);
        $this->assertArrayHasKey('estimated_output_tokens', $cost);
        $this->assertArrayHasKey('input_cost', $cost);
        $this->assertArrayHasKey('estimated_output_cost', $cost);
        $this->assertArrayHasKey('estimated_total_cost', $cost);
        $this->assertArrayHasKey('currency', $cost);

        $this->assertEquals('gpt-3.5-turbo', $cost['model']);
        $this->assertEquals('USD', $cost['currency']);
        $this->assertGreaterThan(0, $cost['input_tokens']);
        $this->assertGreaterThan(0, $cost['estimated_total_cost']);
    }

    #[Test]
    public function it_can_calculate_cost_with_default_model(): void
    {
        $message = AIMessage::user('Test message');

        $cost = $this->driver->calculateCost($message);

        $this->assertIsArray($cost);
        $this->assertEquals('gpt-3.5-turbo', $cost['model']); // Default model
    }

    #[Test]
    public function it_can_estimate_tokens_for_string(): void
    {
        $text = 'Hello, this is a test message for token estimation.';

        $tokens = $this->driver->estimateTokens($text);

        $this->assertIsInt($tokens);
        $this->assertGreaterThan(0, $tokens);
        $this->assertLessThan(50, $tokens); // Should be reasonable for this short text
    }

    #[Test]
    public function it_can_estimate_tokens_for_message(): void
    {
        $message = AIMessage::user('Hello, how are you?');

        $tokens = $this->driver->estimateTokens($message);

        $this->assertIsInt($tokens);
        $this->assertGreaterThan(0, $tokens);
    }

    #[Test]
    public function it_can_estimate_tokens_for_message_array(): void
    {
        $messages = [
            AIMessage::system('You are a helpful assistant.'),
            AIMessage::user('Hello!'),
            AIMessage::assistant('Hi there!'),
        ];

        $tokens = $this->driver->estimateTokens($messages);

        $this->assertIsInt($tokens);
        $this->assertGreaterThan(0, $tokens);
    }

    #[Test]
    public function it_can_validate_credentials(): void
    {
        // Mock successful models list response for credential validation
        $this->mockClient->modelsMock->shouldReceive('list')
            ->once()
            ->andReturn((object) ['data' => []]);

        $result = $this->driver->validateCredentials();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('valid', $result);
        $this->assertArrayHasKey('provider', $result);
        $this->assertEquals('openai', $result['provider']);
    }

    #[Test]
    public function it_can_get_health_status(): void
    {
        // Mock successful models list response
        $this->mockClient->modelsMock->shouldReceive('list')
            ->once()
            ->andReturn((object) ['data' => []]);

        // Mock successful chat completion for health check
        $mockResponse = $this->createMockApiResponse();
        $this->mockClient->chatMock->shouldReceive('create')
            ->once()
            ->andReturn($mockResponse);

        $status = $this->driver->getHealthStatus();

        $this->assertIsArray($status);
        $this->assertArrayHasKey('status', $status);
        $this->assertArrayHasKey('provider', $status);
        $this->assertEquals('openai', $status['provider']);
        $this->assertContains($status['status'], ['healthy', 'degraded', 'unhealthy']);
    }

    #[Test]
    public function it_throws_not_implemented_for_get_usage_stats(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('Method not yet implemented');

        $this->driver->getUsageStats();
    }

    #[Test]
    public function it_throws_invalid_argument_for_invalid_estimate_tokens_input(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Input must be a string, AIMessage, or array of AIMessages');

        $this->driver->estimateTokens(123); // Invalid input type
    }

    #[Test]
    public function it_has_correct_provider_name(): void
    {
        $this->assertEquals('openai', $this->driver->getName());
    }

    #[Test]
    public function it_can_be_instantiated_with_minimal_config(): void
    {
        $config = ['api_key' => 'sk-minimal-test'];
        $driver = new OpenAIDriver($config);

        $this->assertInstanceOf(OpenAIDriver::class, $driver);
        $this->assertEquals('openai', $driver->getName());
    }

    #[Test]
    public function it_handles_conversation_context_with_system_message(): void
    {
        $message = AIMessage::user('What is 2+2?');
        $options = [
            'system_message' => 'You are a math tutor.',
            'conversation_id' => 'conv-123',
        ];

        $mockResponse = $this->createMockApiResponse([
            'choices' => [
                (object) [
                    'message' => (object) [
                        'content' => '2+2 equals 4.',
                        'role' => 'assistant',
                    ],
                    'finish_reason' => 'stop',
                ],
            ],
        ]);

        $this->mockClient->chatMock->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($params) {
                // Verify system message is included
                return isset($params['messages']) &&
                       count($params['messages']) >= 2 &&
                       $params['messages'][0]['role'] === 'system' &&
                       $params['messages'][0]['content'] === 'You are a math tutor.';
            }))
            ->andReturn($mockResponse);

        $response = $this->driver->sendMessage($message, $options);

        $this->assertInstanceOf(AIResponse::class, $response);
        $this->assertEquals('2+2 equals 4.', $response->content);
    }

    #[Test]
    public function it_handles_conversation_history(): void
    {
        $message = AIMessage::user('And what about 3+3?');
        $options = [
            'conversation_history' => [
                AIMessage::user('What is 2+2?'),
                AIMessage::assistant('2+2 equals 4.'),
            ],
        ];

        $mockResponse = $this->createMockApiResponse();

        $this->mockClient->chatMock->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($params) {
                // Verify conversation history is included
                return isset($params['messages']) &&
                       count($params['messages']) === 3; // 2 history + 1 current
            }))
            ->andReturn($mockResponse);

        $response = $this->driver->sendMessage($message, $options);

        $this->assertInstanceOf(AIResponse::class, $response);
    }

    #[Test]
    public function it_can_get_capabilities(): void
    {
        $capabilities = $this->driver->getCapabilities();

        $this->assertIsArray($capabilities);
        $this->assertArrayHasKey('streaming', $capabilities);
        $this->assertArrayHasKey('function_calling', $capabilities);
        $this->assertArrayHasKey('vision', $capabilities);
        $this->assertArrayHasKey('json_mode', $capabilities);
        $this->assertTrue($capabilities['streaming']);
        $this->assertTrue($capabilities['function_calling']);
    }

    #[Test]
    public function it_can_get_default_model(): void
    {
        $defaultModel = $this->driver->getDefaultModel();

        $this->assertEquals('gpt-3.5-turbo', $defaultModel);
    }

    #[Test]
    public function it_can_set_and_get_model(): void
    {
        $newModel = 'gpt-4';

        $result = $this->driver->setModel($newModel);

        $this->assertSame($this->driver, $result); // Should return self for chaining
        $this->assertEquals($newModel, $this->driver->getCurrentModel());
    }

    #[Test]
    public function it_can_set_and_get_options(): void
    {
        $newOptions = [
            'temperature' => 0.8,
            'max_tokens' => 150,
        ];

        $result = $this->driver->setOptions($newOptions);
        $options = $this->driver->getOptions();

        $this->assertSame($this->driver, $result); // Should return self for chaining
        $this->assertArrayHasKey('temperature', $options);
        $this->assertArrayHasKey('max_tokens', $options);
        $this->assertEquals(0.8, $options['temperature']);
        $this->assertEquals(150, $options['max_tokens']);
    }

    #[Test]
    public function it_can_check_feature_support(): void
    {
        $this->assertTrue($this->driver->supportsFeature('streaming'));
        $this->assertTrue($this->driver->supportsFeature('function_calling'));
        $this->assertFalse($this->driver->supportsFeature('nonexistent_feature'));
    }

    #[Test]
    public function it_can_get_provider_version(): void
    {
        $version = $this->driver->getVersion();

        $this->assertEquals('v1', $version);
    }

    #[Test]
    public function it_can_create_function_result_message(): void
    {
        $functionName = 'get_weather';
        $result = 'The weather is sunny, 25°C';

        $message = $this->driver->createFunctionResultMessage($functionName, $result);

        $this->assertInstanceOf(AIMessage::class, $message);
        $this->assertEquals($result, $message->content);
        $this->assertEquals('function', $message->role);
        $this->assertEquals($functionName, $message->metadata['function_name']);
    }

    #[Test]
    public function it_can_create_tool_result_message(): void
    {
        $toolCallId = 'call_123';
        $result = 'Tool execution completed successfully';

        $message = $this->driver->createToolResultMessage($toolCallId, $result);

        $this->assertInstanceOf(AIMessage::class, $message);
        $this->assertEquals($result, $message->content);
        $this->assertEquals('tool', $message->role);
        $this->assertEquals($toolCallId, $message->metadata['tool_call_id']);
    }

    #[Test]
    public function it_throws_not_implemented_for_get_rate_limits(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('Method not yet implemented');

        $this->driver->getRateLimits();
    }

    #[Test]
    public function it_handles_api_errors_gracefully(): void
    {
        $message = AIMessage::user('Test error handling');

        // Mock an API error
        $this->mockClient->chatMock->shouldReceive('create')
            ->once()
            ->andThrow(new \Exception('API Error'));

        $this->expectException(\Exception::class);
        $this->driver->sendMessage($message);
    }

    #[Test]
    public function it_handles_message_with_function_calls(): void
    {
        $message = AIMessage::user('What is the weather?');
        $options = [
            'functions' => [
                [
                    'name' => 'get_weather',
                    'description' => 'Get current weather',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'location' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ];

        $mockResponse = $this->createMockApiResponse([
            'choices' => [
                (object) [
                    'message' => (object) [
                        'content' => null,
                        'role' => 'assistant',
                        'function_call' => (object) [
                            'name' => 'get_weather',
                            'arguments' => '{"location": "New York"}',
                        ],
                    ],
                    'finish_reason' => 'function_call',
                ],
            ],
        ]);

        $this->mockClient->chatMock->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($params) {
                return isset($params['functions']) && count($params['functions']) === 1;
            }))
            ->andReturn($mockResponse);

        $response = $this->driver->sendMessage($message, $options);

        $this->assertInstanceOf(AIResponse::class, $response);
        $this->assertEquals('function_call', $response->finishReason);
        $this->assertNotNull($response->functionCalls);
    }

    #[Test]
    public function it_handles_message_with_tools(): void
    {
        $message = AIMessage::user('Calculate 2+2');
        $options = [
            'tools' => [
                [
                    'type' => 'function',
                    'function' => [
                        'name' => 'calculate',
                        'description' => 'Perform calculations',
                        'parameters' => [
                            'type' => 'object',
                            'properties' => [
                                'expression' => ['type' => 'string'],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $mockResponse = $this->createMockApiResponse([
            'choices' => [
                (object) [
                    'message' => (object) [
                        'content' => null,
                        'role' => 'assistant',
                        'tool_calls' => [
                            (object) [
                                'id' => 'call_123',
                                'type' => 'function',
                                'function' => (object) [
                                    'name' => 'calculate',
                                    'arguments' => '{"expression": "2+2"}',
                                ],
                            ],
                        ],
                    ],
                    'finish_reason' => 'tool_calls',
                ],
            ],
        ]);

        $this->mockClient->chatMock->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($params) {
                return isset($params['tools']) && count($params['tools']) === 1;
            }))
            ->andReturn($mockResponse);

        $response = $this->driver->sendMessage($message, $options);

        $this->assertInstanceOf(AIResponse::class, $response);
        $this->assertEquals('tool_calls', $response->finishReason);
        $this->assertNotNull($response->toolCalls);
    }

    /**
     * Test helper to create a mock OpenAI API response.
     */
    protected function createMockApiResponse(array $data = []): object
    {
        return (object) array_merge([
            'choices' => [
                (object) [
                    'message' => (object) [
                        'content' => 'Mock response content',
                        'role' => 'assistant',
                    ],
                    'finish_reason' => 'stop',
                ],
            ],
            'usage' => (object) [
                'promptTokens' => 10,
                'completionTokens' => 15,
                'totalTokens' => 25,
            ],
            'model' => 'gpt-3.5-turbo',
            'id' => 'chatcmpl-test123',
        ], $data);
    }

    /**
     * Test helper to create a mock streaming response chunk.
     */
    protected function createMockStreamChunk(string $content = 'chunk', bool $isLast = false): object
    {
        return (object) [
            'choices' => [
                (object) [
                    'delta' => (object) [
                        'content' => $isLast ? null : $content,
                    ],
                    'finish_reason' => $isLast ? 'stop' : null,
                ],
            ],
            'usage' => $isLast ? (object) ['totalTokens' => 25] : null,
        ];
    }
}
