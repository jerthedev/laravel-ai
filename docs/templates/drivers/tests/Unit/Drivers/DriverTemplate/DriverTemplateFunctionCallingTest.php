<?php

namespace JTD\LaravelAI\Tests\Unit;

use JTD\LaravelAI\Drivers\DriverTemplate\DriverTemplateDriver;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Models\AIResponse;
use JTD\LaravelAI\Tests\TestCase;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * DriverTemplate Function Calling Tests
 *
 * Comprehensive tests for function calling functionality including
 * definition validation, execution, parallel calls, and error scenarios.
 */
#[Group('unit')]
#[Group('drivertemplate')]
#[Group('function-calling')]
class DriverTemplateFunctionCallingTest extends TestCase
{
    private DriverTemplateDriver $driver;
    private $mockClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockClient = Mockery::mock();
        $this->driver = new DriverTemplateDriver([
            'api_key' => 'api-key-test-key-for-unit-tests',
            'timeout' => 30,
        ]);
        $this->driver->setClient($this->mockClient);
    }

    #[Test]
    public function it_can_validate_function_definitions(): void
    {

        // TODO: Implement test
    }

    #[Test]
    public function it_can_format_functions_for_api(): void
    {

        // TODO: Implement test
    }

    #[Test]
    public function it_can_format_tools_for_api(): void
    {

        // TODO: Implement test
    }

    #[Test]
    public function it_can_send_message_with_functions(): void
    {

        // TODO: Implement test
            }))
            ->andReturn($mockResponse);

        $this->mockClient->shouldReceive('chat')
            ->once()
            ->andReturn($mockChatResource);

        $message = AIMessage::user('What is the weather in Paris?');
        $response = $this->driver->sendMessage($message, [
            'model' => 'default-model-3.5-turbo',
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

        // TODO: Implement test
            }))
            ->andReturn($mockResponse);

        $this->mockClient->shouldReceive('chat')
            ->once()
            ->andReturn($mockChatResource);

        $message = AIMessage::user('Calculate 2 + 2');
        $response = $this->driver->sendMessage($message, [
            'model' => 'default-model-3.5-turbo',
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
            (is_object($response->toolCalls) && !empty((array)$response->toolCalls))
        );
    }

    #[Test]
    public function it_can_create_function_result_message(): void
    {

        // TODO: Implement test
    }

    #[Test]
    public function it_can_create_tool_result_message(): void
    {

        // TODO: Implement test
    }

    #[Test]
    public function it_can_execute_function_calls(): void
    {

        // TODO: Implement test
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

        // TODO: Implement test
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

        // TODO: Implement test
            }
            return 'Function executed successfully';
        };

        $message = AIMessage::user('What time is it?');
        $response = $this->driver->conversationWithFunctions($message, $functions, $executor);

        $this->assertInstanceOf(AIResponse::class, $response);
        $this->assertEquals('The current time is 2:30 PM.', $response->content);
        $this->assertEquals('stop', $response->finishReason);
    }

    #[Test]
    public function it_validates_function_parameters_schema(): void
    {

        // TODO: Implement test
    }

    /**
     * Create mock function call response.
     */
    private function createMockFunctionCallResponse(string $name = 'get_weather', string $arguments = '{"location": "Paris"}'): object
    {
        $response = new \stdClass();
        $response->model = 'default-model-3.5-turbo';

        $choice = new \stdClass();
        $choice->finishReason = 'function_call';

        $message = new \stdClass();
        $message->role = 'assistant';
        $message->content = null;
        $message->functionCall = new \stdClass();
        $message->functionCall->name = $name;
        $message->functionCall->arguments = $arguments;

        $choice->message = $message;
        $response->choices = [$choice];

        $response->usage = new \stdClass();
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
        $response = new \stdClass();
        $response->model = 'default-model-3.5-turbo';

        $choice = new \stdClass();
        $choice->finishReason = 'tool_calls';

        $message = new \stdClass();
        $message->role = 'assistant';
        $message->content = null;

        $toolCall = new \stdClass();
        $toolCall->id = 'call_123456';
        $toolCall->type = 'function';
        $toolCall->function = new \stdClass();
        $toolCall->function->name = 'calculate';
        $toolCall->function->arguments = '{"expression": "2 + 2"}';

        $message->toolCalls = [$toolCall];
        $choice->message = $message;
        $response->choices = [$choice];

        $response->usage = new \stdClass();
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
        $response = new \stdClass();
        $response->model = 'default-model-3.5-turbo';

        $choice = new \stdClass();
        $choice->finishReason = 'stop';

        $message = new \stdClass();
        $message->role = 'assistant';
        $message->content = $content;

        $choice->message = $message;
        $response->choices = [$choice];

        $response->usage = new \stdClass();
        $response->usage->promptTokens = 30;
        $response->usage->completionTokens = 20;
        $response->usage->totalTokens = 50;

        return $response;
    }
}
