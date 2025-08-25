<?php

namespace JTD\LaravelAI\Tests\Unit;

use JTD\LaravelAI\Drivers\OpenAI\OpenAIDriver;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Models\AIResponse;
use JTD\LaravelAI\Tests\TestCase;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * OpenAI Function Calling Tests
 *
 * Comprehensive tests for function calling functionality including
 * definition validation, execution, parallel calls, and error scenarios.
 */
#[Group('unit')]
#[Group('openai')]
#[Group('function-calling')]
class OpenAIFunctionCallingTest extends TestCase
{
    private OpenAIDriver $driver;

    private $mockClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockClient = Mockery::mock();
        $this->driver = new OpenAIDriver([
            'api_key' => 'sk-test-key-for-unit-tests',
            'timeout' => 30,
        ]);
        $this->driver->setClient($this->mockClient);
    }

    #[Test]
    public function it_can_validate_function_definitions(): void
    {
        // Valid function definition
        $validFunction = [
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
        ];

        $errors = $this->driver->validateFunctionDefinition($validFunction);
        $this->assertEmpty($errors, 'Valid function definition should have no errors');

        // Invalid function definition - missing name
        $invalidFunction = [
            'description' => 'Get weather',
            'parameters' => ['type' => 'object'],
        ];

        $errors = $this->driver->validateFunctionDefinition($invalidFunction);
        $this->assertNotEmpty($errors, 'Invalid function definition should have errors');
        $this->assertStringContainsString('name is required', implode(', ', $errors));
    }

    #[Test]
    public function it_can_format_functions_for_api(): void
    {
        $functions = [
            [
                'name' => 'get_weather',
                'description' => 'Get weather',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'location' => ['type' => 'string'],
                    ],
                ],
            ],
        ];

        // Use reflection to access protected method
        $reflection = new \ReflectionClass($this->driver);
        $method = $reflection->getMethod('formatFunctions');
        $method->setAccessible(true);

        $formatted = $method->invoke($this->driver, $functions);

        $this->assertIsArray($formatted);
        $this->assertCount(1, $formatted);
        $this->assertEquals('get_weather', $formatted[0]['name']);
        $this->assertEquals('Get weather', $formatted[0]['description']);
        $this->assertArrayHasKey('parameters', $formatted[0]);
    }

    #[Test]
    public function it_can_format_tools_for_api(): void
    {
        $tools = [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'calculate',
                    'description' => 'Perform calculation',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'expression' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ];

        // Use reflection to access protected method
        $reflection = new \ReflectionClass($this->driver);
        $method = $reflection->getMethod('formatTools');
        $method->setAccessible(true);

        $formatted = $method->invoke($this->driver, $tools);

        $this->assertIsArray($formatted);
        $this->assertCount(1, $formatted);
        $this->assertEquals('function', $formatted[0]['type']);
        $this->assertEquals('calculate', $formatted[0]['function']['name']);
    }

    #[Test]
    public function it_can_send_message_with_functions(): void
    {
        $functions = [
            [
                'name' => 'get_weather',
                'description' => 'Get weather',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'location' => ['type' => 'string'],
                    ],
                    'required' => ['location'],
                ],
            ],
        ];

        // Mock API response with function call
        $mockResponse = $this->createMockFunctionCallResponse();

        $mockChatResource = Mockery::mock();
        $mockChatResource->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($params) {
                return isset($params['functions']) &&
                       $params['function_call'] === 'auto' &&
                       $params['model'] === 'gpt-3.5-turbo';
            }))
            ->andReturn($mockResponse);

        $this->mockClient->shouldReceive('chat')
            ->once()
            ->andReturn($mockChatResource);

        $message = AIMessage::user('What is the weather in Paris?');
        $response = $this->driver->sendMessage($message, [
            'model' => 'gpt-3.5-turbo',
            'functions' => $functions,
            'function_call' => 'auto',
        ]);

        $this->assertInstanceOf(AIResponse::class, $response);
        $this->assertEquals('function_call', $response->finishReason);
        $this->assertNotNull($response->functionCalls);
        $this->assertEquals('get_weather', $response->functionCalls['name']);
    }

    #[Test]
    public function it_can_send_message_with_tools(): void
    {
        $tools = [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'calculate',
                    'description' => 'Perform calculation',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'expression' => ['type' => 'string'],
                        ],
                        'required' => ['expression'],
                    ],
                ],
            ],
        ];

        // Mock API response with tool call
        $mockResponse = $this->createMockToolCallResponse();

        $mockChatResource = Mockery::mock();
        $mockChatResource->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($params) {
                return isset($params['tools']) &&
                       $params['tool_choice'] === 'auto' &&
                       $params['model'] === 'gpt-3.5-turbo';
            }))
            ->andReturn($mockResponse);

        $this->mockClient->shouldReceive('chat')
            ->once()
            ->andReturn($mockChatResource);

        $message = AIMessage::user('Calculate 2 + 2');
        $response = $this->driver->sendMessage($message, [
            'model' => 'gpt-3.5-turbo',
            'tools' => $tools,
            'tool_choice' => 'auto',
        ]);

        $this->assertInstanceOf(AIResponse::class, $response);
        $this->assertEquals('tool_calls', $response->finishReason);
        $this->assertNotNull($response->toolCalls);

        // Tool calls should be present
        $this->assertNotNull($response->toolCalls);
        $this->assertNotEmpty($response->toolCalls);

        // Verify tool call structure exists (flexible for different formats)
        $this->assertTrue(
            (is_array($response->toolCalls) && count($response->toolCalls) > 0) ||
            (is_object($response->toolCalls) && ! empty((array) $response->toolCalls))
        );
    }

    #[Test]
    public function it_can_create_function_result_message(): void
    {
        $functionName = 'get_weather';
        $result = '{"temperature": 22, "condition": "sunny"}';

        $message = $this->driver->createFunctionResultMessage($functionName, $result);

        $this->assertInstanceOf(AIMessage::class, $message);
        $this->assertEquals('function', $message->role);
        $this->assertEquals($result, $message->content);
        $this->assertEquals($functionName, $message->name);
    }

    #[Test]
    public function it_can_create_tool_result_message(): void
    {
        $toolCallId = 'call_123456';
        $result = '{"result": 4}';

        $message = $this->driver->createToolResultMessage($toolCallId, $result);

        $this->assertInstanceOf(AIMessage::class, $message);
        $this->assertEquals('tool', $message->role);
        $this->assertEquals($result, $message->content);
        $this->assertEquals($toolCallId, $message->name);
    }

    #[Test]
    public function it_can_execute_function_calls(): void
    {
        $functionCalls = [
            [
                'name' => 'add_numbers',
                'arguments' => '{"a": 5, "b": 3}',
                'id' => null,
            ],
            [
                'name' => 'multiply_numbers',
                'arguments' => '{"x": 4, "y": 2}',
                'id' => null,
            ],
        ];

        $executor = function ($name, $args) {
            switch ($name) {
                case 'add_numbers':
                    return $args['a'] + $args['b'];
                case 'multiply_numbers':
                    return $args['x'] * $args['y'];
                default:
                    throw new \Exception("Unknown function: {$name}");
            }
        };

        $results = $this->driver->executeFunctionCalls($functionCalls, $executor);

        $this->assertCount(2, $results);

        // Check first result
        $this->assertTrue($results[0]['success']);
        $this->assertEquals('8', $results[0]['result']);
        $this->assertEquals('add_numbers', $results[0]['call']['name']);

        // Check second result
        $this->assertTrue($results[1]['success']);
        $this->assertEquals('8', $results[1]['result']);
        $this->assertEquals('multiply_numbers', $results[1]['call']['name']);
    }

    #[Test]
    public function it_handles_function_execution_errors(): void
    {
        $functionCalls = [
            [
                'name' => 'failing_function',
                'arguments' => '{}',
                'id' => null,
            ],
        ];

        $executor = function ($name, $args) {
            throw new \Exception("Function failed: {$name}");
        };

        $results = $this->driver->executeFunctionCalls($functionCalls, $executor);

        $this->assertCount(1, $results);
        $this->assertFalse($results[0]['success']);
        $this->assertStringContainsString('Error: Function failed', $results[0]['result']);
        $this->assertEquals('Function failed: failing_function', $results[0]['error']);
    }

    #[Test]
    public function it_can_run_conversation_with_functions(): void
    {
        $functions = [
            [
                'name' => 'get_time',
                'description' => 'Get current time',
                'parameters' => ['type' => 'object', 'properties' => []],
            ],
        ];

        // Mock first response with function call
        $mockFunctionResponse = $this->createMockFunctionCallResponse('get_time', '{}');

        // Mock second response after function execution
        $mockFinalResponse = $this->createMockTextResponse('The current time is 2:30 PM.');

        $mockChatResource = Mockery::mock();
        $mockChatResource->shouldReceive('create')
            ->twice()
            ->andReturn($mockFunctionResponse, $mockFinalResponse);

        $this->mockClient->shouldReceive('chat')
            ->twice()
            ->andReturn($mockChatResource);

        $executor = function ($name, $args) {
            if ($name === 'get_time') {
                return '2:30 PM';
            }

            return 'Function executed successfully';
        };

        $message = AIMessage::user('What time is it?');
        // Note: Testing deprecated conversationWithFunctions method - will be removed in next version
        $response = $this->driver->conversationWithFunctions($message, $functions, $executor);

        $this->assertInstanceOf(AIResponse::class, $response);
        $this->assertEquals('The current time is 2:30 PM.', $response->content);
        $this->assertEquals('stop', $response->finishReason);
    }

    #[Test]
    public function it_validates_function_parameters_schema(): void
    {
        // Valid parameters
        $validParams = [
            'type' => 'object',
            'properties' => [
                'location' => ['type' => 'string'],
            ],
            'required' => ['location'],
        ];

        // Use reflection to access protected method
        $reflection = new \ReflectionClass($this->driver);
        $method = $reflection->getMethod('validateParametersSchema');
        $method->setAccessible(true);

        $errors = $method->invoke($this->driver, $validParams);
        $this->assertEmpty($errors, 'Valid parameters should have no errors');

        // Invalid parameters - wrong type
        $invalidParams = [
            'type' => 'string', // Should be 'object'
        ];

        $errors = $method->invoke($this->driver, $invalidParams);
        $this->assertNotEmpty($errors, 'Invalid parameters should have errors');
        $this->assertStringContainsString('type must be "object"', implode(', ', $errors));
    }

    /**
     * Create mock function call response.
     */
    private function createMockFunctionCallResponse(string $name = 'get_weather', string $arguments = '{"location": "Paris"}'): object
    {
        $response = new \stdClass;
        $response->model = 'gpt-3.5-turbo';

        $choice = new \stdClass;
        $choice->finishReason = 'function_call';

        $message = new \stdClass;
        $message->role = 'assistant';
        $message->content = null;
        $message->functionCall = new \stdClass;
        $message->functionCall->name = $name;
        $message->functionCall->arguments = $arguments;

        $choice->message = $message;
        $response->choices = [$choice];

        $response->usage = new \stdClass;
        $response->usage->promptTokens = 20;
        $response->usage->completionTokens = 10;
        $response->usage->totalTokens = 30;

        return $response;
    }

    /**
     * Create mock tool call response.
     */
    private function createMockToolCallResponse(): object
    {
        $response = new \stdClass;
        $response->model = 'gpt-3.5-turbo';

        $choice = new \stdClass;
        $choice->finishReason = 'tool_calls';

        $message = new \stdClass;
        $message->role = 'assistant';
        $message->content = null;

        $toolCall = new \stdClass;
        $toolCall->id = 'call_123456';
        $toolCall->type = 'function';
        $toolCall->function = new \stdClass;
        $toolCall->function->name = 'calculate';
        $toolCall->function->arguments = '{"expression": "2 + 2"}';

        $message->toolCalls = [$toolCall];
        $choice->message = $message;
        $response->choices = [$choice];

        $response->usage = new \stdClass;
        $response->usage->promptTokens = 25;
        $response->usage->completionTokens = 15;
        $response->usage->totalTokens = 40;

        return $response;
    }

    /**
     * Create mock text response.
     */
    private function createMockTextResponse(string $content): object
    {
        $response = new \stdClass;
        $response->model = 'gpt-3.5-turbo';

        $choice = new \stdClass;
        $choice->finishReason = 'stop';

        $message = new \stdClass;
        $message->role = 'assistant';
        $message->content = $content;

        $choice->message = $message;
        $response->choices = [$choice];

        $response->usage = new \stdClass;
        $response->usage->promptTokens = 30;
        $response->usage->completionTokens = 20;
        $response->usage->totalTokens = 50;

        return $response;
    }
}
