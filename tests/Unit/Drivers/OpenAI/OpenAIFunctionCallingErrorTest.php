<?php

namespace JTD\LaravelAI\Tests\Unit;

use JTD\LaravelAI\Drivers\OpenAIDriver;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Mockery;

/**
 * OpenAI Function Calling Error Tests
 *
 * Tests error scenarios in function calling including
 * invalid definitions, execution errors, and API errors.
 */
#[Group('unit')]
#[Group('openai')]
#[Group('function-calling')]
#[Group('errors')]
class OpenAIFunctionCallingErrorTest extends TestCase
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
    public function it_validates_function_name_requirements(): void
    {
        $testCases = [
            [
                'function' => [],
                'expectedError' => 'Function name is required',
            ],
            [
                'function' => ['name' => ''],
                'expectedError' => 'Function name must be a non-empty string',
            ],
            [
                'function' => ['name' => 123],
                'expectedError' => 'Function name must be a non-empty string',
            ],
            [
                'function' => ['name' => 'invalid-name!'],
                'expectedError' => 'Function name can only contain letters, numbers, underscores, and hyphens',
            ],
        ];

        foreach ($testCases as $testCase) {
            $errors = $this->driver->validateFunctionDefinition($testCase['function']);
            $this->assertNotEmpty($errors, "Should have errors for invalid function");
            $this->assertStringContainsString($testCase['expectedError'], implode(', ', $errors));
        }
    }

    #[Test]
    public function it_validates_function_description_requirements(): void
    {
        $testCases = [
            [
                'function' => ['name' => 'test_func'],
                'expectedError' => 'Function description is required',
            ],
            [
                'function' => ['name' => 'test_func', 'description' => ''],
                'expectedError' => 'Function description must be a non-empty string',
            ],
            [
                'function' => ['name' => 'test_func', 'description' => 123],
                'expectedError' => 'Function description must be a non-empty string',
            ],
        ];

        foreach ($testCases as $testCase) {
            $errors = $this->driver->validateFunctionDefinition($testCase['function']);
            $this->assertNotEmpty($errors, "Should have errors for invalid function");
            $this->assertStringContainsString($testCase['expectedError'], implode(', ', $errors));
        }
    }

    #[Test]
    public function it_validates_function_parameters_structure(): void
    {
        $testCases = [
            [
                'function' => [
                    'name' => 'test_func',
                    'description' => 'Test function',
                    'parameters' => 'invalid',
                ],
                'expectedError' => 'Function parameters must be an array',
            ],
            [
                'function' => [
                    'name' => 'test_func',
                    'description' => 'Test function',
                    'parameters' => ['type' => 'string'],
                ],
                'expectedError' => 'Parameters type must be "object"',
            ],
            [
                'function' => [
                    'name' => 'test_func',
                    'description' => 'Test function',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => 'invalid',
                    ],
                ],
                'expectedError' => 'Parameters properties must be an array',
            ],
            [
                'function' => [
                    'name' => 'test_func',
                    'description' => 'Test function',
                    'parameters' => [
                        'type' => 'object',
                        'required' => 'invalid',
                    ],
                ],
                'expectedError' => 'Parameters required must be an array',
            ],
        ];

        foreach ($testCases as $testCase) {
            $errors = $this->driver->validateFunctionDefinition($testCase['function']);
            $this->assertNotEmpty($errors, "Should have errors for invalid function");
            $this->assertStringContainsString($testCase['expectedError'], implode(', ', $errors));
        }
    }

    #[Test]
    public function it_throws_error_for_invalid_function_format(): void
    {
        $invalidFunction = [
            'name' => 'test_func',
            // Missing description
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Function must have a description');

        // Use reflection to access protected method
        $reflection = new \ReflectionClass($this->driver);
        $method = $reflection->getMethod('validateAndFormatFunction');
        $method->setAccessible(true);
        $method->invoke($this->driver, $invalidFunction);
    }

    #[Test]
    public function it_throws_error_for_invalid_tool_format(): void
    {
        $invalidTool = [
            'type' => 'invalid_type',
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported tool type: invalid_type');

        // Use reflection to access protected method
        $reflection = new \ReflectionClass($this->driver);
        $method = $reflection->getMethod('validateAndFormatTool');
        $method->setAccessible(true);
        $method->invoke($this->driver, $invalidTool);
    }

    #[Test]
    public function it_throws_error_for_function_tool_without_function(): void
    {
        $invalidTool = [
            'type' => 'function',
            // Missing function definition
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Function tool must have a function definition');

        // Use reflection to access protected method
        $reflection = new \ReflectionClass($this->driver);
        $method = $reflection->getMethod('validateAndFormatTool');
        $method->setAccessible(true);
        $method->invoke($this->driver, $invalidTool);
    }

    #[Test]
    public function it_handles_function_execution_with_invalid_json(): void
    {
        $functionCalls = [
            [
                'name' => 'test_function',
                'arguments' => 'invalid json {',
                'id' => null,
            ],
        ];

        $executor = function ($name, $args) {
            return 'success';
        };

        $results = $this->driver->executeFunctionCalls($functionCalls, $executor);

        $this->assertCount(1, $results);
        $this->assertFalse($results[0]['success']);
        $this->assertStringContainsString('Error:', $results[0]['result']);
    }

    #[Test]
    public function it_handles_function_execution_without_executor(): void
    {
        $functionCalls = [
            [
                'name' => 'test_function',
                'arguments' => '{}',
                'id' => null,
            ],
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Function executor callback is required');

        $this->driver->executeFunctionCalls($functionCalls, null);
    }

    #[Test]
    public function it_handles_function_execution_exceptions(): void
    {
        $functionCalls = [
            [
                'name' => 'failing_function',
                'arguments' => '{"param": "value"}',
                'id' => null,
            ],
        ];

        $executor = function ($name, $args) {
            throw new \RuntimeException("Function execution failed: {$name}");
        };

        $results = $this->driver->executeFunctionCalls($functionCalls, $executor);

        $this->assertCount(1, $results);
        $this->assertFalse($results[0]['success']);
        $this->assertEquals('Error: Function execution failed: failing_function', $results[0]['result']);
        $this->assertEquals('Function execution failed: failing_function', $results[0]['error']);
    }

    #[Test]
    public function it_validates_function_result_types(): void
    {
        // Use reflection to access protected method
        $reflection = new \ReflectionClass($this->driver);
        $method = $reflection->getMethod('validateFunctionResult');
        $method->setAccessible(true);

        // Test string result
        $result = $method->invoke($this->driver, 'string result');
        $this->assertEquals('string result', $result);

        // Test array result
        $result = $method->invoke($this->driver, ['key' => 'value']);
        $this->assertEquals('{"key":"value"}', $result);

        // Test object result
        $obj = new \stdClass();
        $obj->prop = 'value';
        $result = $method->invoke($this->driver, $obj);
        $this->assertEquals('{"prop":"value"}', $result);

        // Test numeric result
        $result = $method->invoke($this->driver, 42);
        $this->assertEquals('42', $result);

        // Test boolean result
        $result = $method->invoke($this->driver, true);
        $this->assertEquals('1', $result);
    }

    #[Test]
    public function it_handles_api_errors_during_function_calls(): void
    {
        $functions = [
            [
                'name' => 'test_function',
                'description' => 'Test function',
                'parameters' => ['type' => 'object'],
            ],
        ];

        $mockChatResource = Mockery::mock();
        $mockChatResource->shouldReceive('create')
            ->once()
            ->andThrow(new \Exception('API Error: Invalid function definition'));

        $this->mockClient->shouldReceive('chat')
            ->once()
            ->andReturn($mockChatResource);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('API Error: Invalid function definition');

        $message = AIMessage::user('Test message');
        $this->driver->sendMessage($message, [
            'model' => 'gpt-3.5-turbo',
            'functions' => $functions,
            'function_call' => 'auto',
        ]);
    }

    #[Test]
    public function it_handles_malformed_function_call_responses(): void
    {
        // Mock response with malformed function call
        $mockResponse = new \stdClass();
        $mockResponse->model = 'gpt-3.5-turbo';
        
        $choice = new \stdClass();
        $choice->finishReason = 'function_call';
        
        $message = new \stdClass();
        $message->role = 'assistant';
        $message->content = null;
        $message->functionCall = new \stdClass();
        // Missing name and arguments
        
        $choice->message = $message;
        $mockResponse->choices = [$choice];
        
        $mockResponse->usage = new \stdClass();
        $mockResponse->usage->promptTokens = 10;
        $mockResponse->usage->completionTokens = 5;
        $mockResponse->usage->totalTokens = 15;

        $mockChatResource = Mockery::mock();
        $mockChatResource->shouldReceive('create')
            ->once()
            ->andReturn($mockResponse);

        $this->mockClient->shouldReceive('chat')
            ->once()
            ->andReturn($mockChatResource);

        $functions = [
            [
                'name' => 'test_function',
                'description' => 'Test function',
                'parameters' => ['type' => 'object'],
            ],
        ];

        $message = AIMessage::user('Test message');
        $response = $this->driver->sendMessage($message, [
            'model' => 'gpt-3.5-turbo',
            'functions' => $functions,
            'function_call' => 'auto',
        ]);

        // Should handle malformed response gracefully
        $this->assertEquals('function_call', $response->finishReason);
        $this->assertNotNull($response->functionCalls);
    }

    #[Test]
    public function it_handles_conversation_with_functions_errors(): void
    {
        $functions = [
            [
                'name' => 'failing_function',
                'description' => 'Function that will fail',
                'parameters' => ['type' => 'object'],
            ],
        ];

        // Mock function call response
        $mockFunctionResponse = new \stdClass();
        $mockFunctionResponse->model = 'gpt-3.5-turbo';
        
        $choice = new \stdClass();
        $choice->finishReason = 'function_call';
        
        $message = new \stdClass();
        $message->role = 'assistant';
        $message->content = null;
        $message->functionCall = new \stdClass();
        $message->functionCall->name = 'failing_function';
        $message->functionCall->arguments = '{}';
        
        $choice->message = $message;
        $mockFunctionResponse->choices = [$choice];
        
        $mockFunctionResponse->usage = new \stdClass();
        $mockFunctionResponse->usage->promptTokens = 10;
        $mockFunctionResponse->usage->completionTokens = 5;
        $mockFunctionResponse->usage->totalTokens = 15;

        // Mock final response after function error
        $mockFinalResponse = new \stdClass();
        $mockFinalResponse->model = 'gpt-3.5-turbo';
        
        $finalChoice = new \stdClass();
        $finalChoice->finishReason = 'stop';
        
        $finalMessage = new \stdClass();
        $finalMessage->role = 'assistant';
        $finalMessage->content = 'I encountered an error executing the function.';
        
        $finalChoice->message = $finalMessage;
        $mockFinalResponse->choices = [$finalChoice];
        
        $mockFinalResponse->usage = new \stdClass();
        $mockFinalResponse->usage->promptTokens = 20;
        $mockFinalResponse->usage->completionTokens = 10;
        $mockFinalResponse->usage->totalTokens = 30;

        $mockChatResource = Mockery::mock();
        $mockChatResource->shouldReceive('create')
            ->twice()
            ->andReturn($mockFunctionResponse, $mockFinalResponse);

        $this->mockClient->shouldReceive('chat')
            ->twice()
            ->andReturn($mockChatResource);

        $executor = function ($name, $args) {
            throw new \Exception("Function failed: {$name}");
        };

        $message = AIMessage::user('Test message');
        $response = $this->driver->conversationWithFunctions($message, $functions, $executor);

        // Should handle function execution error and continue conversation
        $this->assertEquals('I encountered an error executing the function.', $response->content);
        $this->assertEquals('stop', $response->finishReason);
    }

    #[Test]
    public function it_provides_helpful_function_calling_examples(): void
    {
        $examples = $this->driver->getFunctionCallingExamples();

        $this->assertIsArray($examples);
        $this->assertArrayHasKey('weather_function', $examples);
        $this->assertArrayHasKey('calculator_function', $examples);

        // Validate weather function example
        $weatherExample = $examples['weather_function'];
        $this->assertEquals('get_weather', $weatherExample['name']);
        $this->assertArrayHasKey('description', $weatherExample);
        $this->assertArrayHasKey('parameters', $weatherExample);
        $this->assertEquals('object', $weatherExample['parameters']['type']);

        // Validate calculator function example
        $calcExample = $examples['calculator_function'];
        $this->assertEquals('calculate', $calcExample['name']);
        $this->assertArrayHasKey('description', $calcExample);
        $this->assertArrayHasKey('parameters', $calcExample);
    }
}
