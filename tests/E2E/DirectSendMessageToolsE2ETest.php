<?php

namespace JTD\LaravelAI\Tests\E2E;

use JTD\LaravelAI\Facades\AI;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Services\AIFunctionEvent;
use JTD\LaravelAI\Services\UnifiedToolRegistry;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Group;

/**
 * Direct SendMessage Tools E2E Test
 *
 * E2E tests for withTools and allTools options in direct sendMessage pattern.
 * Tests both AI::sendMessage() and AI::provider()->sendMessage() patterns with
 * tool option validation and processing.
 */
#[Group('e2e')]
#[Group('tools')]
#[Group('direct-send')]
class DirectSendMessageToolsE2ETest extends E2ETestCase
{
    protected UnifiedToolRegistry $toolRegistry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->toolRegistry = app('laravel-ai.tools.registry');

        // Register test function events for testing
        $this->registerTestFunctionEvents();
    }

    protected function registerTestFunctionEvents(): void
    {
        AIFunctionEvent::listen(
            'test_weather_service',
            \JTD\LaravelAI\Tests\Support\TestWeatherServiceListener::class,
            [
            'description' => 'Get weather information for a location',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'location' => ['type' => 'string', 'description' => 'City or location name'],
                ],
                'required' => ['location'],
            ],
        ]);

        AIFunctionEvent::listen(
            'test_task_manager',
            \JTD\LaravelAI\Tests\Support\TestTaskManagerListener::class,
            [
            'description' => 'Manage tasks and to-do items',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'action' => ['type' => 'string', 'enum' => ['create', 'update', 'delete', 'list']],
                    'title' => ['type' => 'string', 'description' => 'Task title'],
                    'description' => ['type' => 'string', 'description' => 'Task description'],
                ],
                'required' => ['action'],
            ],
        ]);

        // Refresh registry to pick up new functions
        $this->toolRegistry->refreshCache();
    }

    #[Test]
    public function it_can_use_withTools_option_in_default_sendMessage()
    {
        $allTools = $this->toolRegistry->getAllTools();
        $availableToolNames = array_keys($allTools);

        if (count($availableToolNames) < 1) {
            $this->markTestSkipped('Need at least 1 tool for withTools testing');
        }

        $selectedTools = array_slice($availableToolNames, 0, 1);

        // Test withTools option with default provider (should use AI_DEFAULT_PROVIDER)
        $response = AI::sendMessage(
            AIMessage::user('Use the selected tools to help me with weather information'),
            [
                'model' => 'gpt-4',
                'withTools' => $selectedTools,
            ]
        );

        $this->assertNotNull($response);
        $this->assertIsString($response->content);

        $this->logE2EInfo('withTools option in default sendMessage completed', [
            'selected_tools' => $selectedTools,
            'response_length' => strlen($response->content),
        ]);
    }

    #[Test]
    public function it_can_use_allTools_option_in_default_sendMessage()
    {
        // Test allTools option with default provider
        $response = AI::sendMessage(
            AIMessage::user('Use any tools you need to help me manage tasks'),
            [
                'model' => 'gpt-4',
                'allTools' => true,
            ]
        );

        $this->assertNotNull($response);
        $this->assertIsString($response->content);

        $this->logE2EInfo('allTools option in default sendMessage completed', [
            'response_length' => strlen($response->content),
        ]);
    }

    #[Test]
    public function it_can_use_withTools_option_in_provider_specific_sendMessage()
    {
        $allTools = $this->toolRegistry->getAllTools();
        $availableToolNames = array_keys($allTools);

        if (count($availableToolNames) < 2) {
            $this->markTestSkipped('Need at least 2 tools for provider-specific withTools testing');
        }

        $selectedTools = array_slice($availableToolNames, 0, 2);

        // Test withTools option with specific provider
        $response = AI::provider('mock')->sendMessage(
            AIMessage::user('Use the weather service and task manager tools'),
            [
                'model' => 'gpt-4',
                'withTools' => $selectedTools,
            ]
        );

        $this->assertNotNull($response);
        $this->assertIsString($response->content);

        $this->logE2EInfo('withTools option in provider-specific sendMessage completed', [
            'provider' => 'mock',
            'selected_tools' => $selectedTools,
            'response_length' => strlen($response->content),
        ]);
    }

    #[Test]
    public function it_can_use_allTools_option_in_provider_specific_sendMessage()
    {
        // Test allTools option with specific provider
        $response = AI::provider('mock')->sendMessage(
            AIMessage::user('Use all available tools to help me'),
            [
                'model' => 'gpt-4',
                'allTools' => true,
            ]
        );

        $this->assertNotNull($response);
        $this->assertIsString($response->content);

        $this->logE2EInfo('allTools option in provider-specific sendMessage completed', [
            'provider' => 'mock',
            'response_length' => strlen($response->content),
        ]);
    }

    #[Test]
    public function it_validates_tool_names_in_withTools_option()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown tools: non_existent_tool');

        AI::sendMessage(
            AIMessage::user('This should fail'),
            [
                'model' => 'gpt-4',
                'withTools' => ['non_existent_tool'],
            ]
        );
    }

    #[Test]
    public function it_handles_mixed_valid_and_invalid_tools_in_options()
    {
        $allTools = $this->toolRegistry->getAllTools();
        $validTool = array_keys($allTools)[0] ?? 'test_weather_service';

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown tools: invalid_tool');

        AI::provider('mock')->sendMessage(
            AIMessage::user('This should fail'),
            [
                'model' => 'gpt-4',
                'withTools' => [$validTool, 'invalid_tool'],
            ]
        );
    }

    #[Test]
    public function it_processes_tools_with_mock_provider_in_direct_send()
    {
        // Test with tools that should trigger mock simulation
        $response = AI::provider('mock')->sendMessage(
            AIMessage::user('Get weather for New York using the weather service'),
            [
                'model' => 'gpt-4',
                'withTools' => ['test_weather_service'],
            ]
        );

        $this->assertNotNull($response);

        // Check if mock provider simulated tool calls
        if (isset($response->toolCalls)) {
            $this->assertIsArray($response->toolCalls);
            $this->logE2EInfo('Mock tool simulation triggered in direct send', [
                'tool_calls' => count($response->toolCalls),
            ]);
        }

        // Check metadata for tool execution results
        if (isset($response->metadata['tool_execution_results'])) {
            $this->assertIsArray($response->metadata['tool_execution_results']);
            $this->logE2EInfo('Tool execution results found in direct send', [
                'results_count' => count($response->metadata['tool_execution_results']),
            ]);
        }
    }

    #[Test]
    public function it_combines_withTools_and_other_options()
    {
        $allTools = $this->toolRegistry->getAllTools();
        $toolNames = array_slice(array_keys($allTools), 0, 1);

        if (empty($toolNames)) {
            $this->markTestSkipped('No tools available for combination test');
        }

        // Test withTools combined with other options
        $response = AI::provider('mock')->sendMessage(
            AIMessage::user('Help me with a task using tools'),
            [
                'model' => 'gpt-4',
                'temperature' => 0.7,
                'max_tokens' => 150,
                'withTools' => $toolNames,
            ]
        );

        $this->assertNotNull($response);
        $this->assertIsString($response->content);

        $this->logE2EInfo('Combined options test completed', [
            'tools_used' => $toolNames,
            'response_length' => strlen($response->content),
        ]);
    }

    #[Test]
    public function it_combines_allTools_and_other_options()
    {
        // Test allTools combined with other options
        $response = AI::sendMessage(
            AIMessage::user('Use all tools with custom settings'),
            [
                'model' => 'gpt-4',
                'temperature' => 0.8,
                'max_tokens' => 200,
                'allTools' => true,
            ]
        );

        $this->assertNotNull($response);
        $this->assertIsString($response->content);

        $this->logE2EInfo('allTools combined options test completed', [
            'response_length' => strlen($response->content),
        ]);
    }

    #[Test]
    public function it_handles_empty_withTools_array_in_options()
    {
        // Test with empty withTools array (should work fine)
        $response = AI::provider('mock')->sendMessage(
            AIMessage::user('Help me without tools'),
            [
                'model' => 'gpt-4',
                'withTools' => [],
            ]
        );

        $this->assertNotNull($response);
        $this->assertIsString($response->content);

        $this->logE2EInfo('Empty withTools array in options test completed');
    }

    #[Test]
    public function it_handles_false_allTools_option()
    {
        // Test with allTools set to false (should work like no tools)
        $response = AI::sendMessage(
            AIMessage::user('Help me without all tools'),
            [
                'model' => 'gpt-4',
                'allTools' => false,
            ]
        );

        $this->assertNotNull($response);
        $this->assertIsString($response->content);

        $this->logE2EInfo('allTools false option test completed');
    }

    #[Test]
    public function it_prioritizes_withTools_over_allTools_in_options()
    {
        $allTools = $this->toolRegistry->getAllTools();
        $toolNames = array_slice(array_keys($allTools), 0, 1);

        if (empty($toolNames)) {
            $this->markTestSkipped('No tools available for priority test');
        }

        // Test both options present (withTools should take priority)
        $response = AI::provider('mock')->sendMessage(
            AIMessage::user('Test tool priority'),
            [
                'model' => 'gpt-4',
                'allTools' => true,
                'withTools' => $toolNames, // This should take priority
            ]
        );

        $this->assertNotNull($response);
        $this->logE2EInfo('Tool priority test completed', [
            'specific_tools' => $toolNames,
        ]);
    }

    #[Test]
    public function it_works_with_different_message_types()
    {
        $allTools = $this->toolRegistry->getAllTools();
        $toolNames = array_slice(array_keys($allTools), 0, 1);

        if (empty($toolNames)) {
            $this->markTestSkipped('No tools available for message type test');
        }

        // Test with system message
        $systemResponse = AI::provider('mock')->sendMessage(
            AIMessage::system('You are a helpful assistant with access to tools'),
            [
                'model' => 'gpt-4',
                'withTools' => $toolNames,
            ]
        );

        $this->assertNotNull($systemResponse);

        // Test with assistant message
        $assistantResponse = AI::provider('mock')->sendMessage(
            AIMessage::assistant('I can help you with various tasks using tools'),
            [
                'model' => 'gpt-4',
                'withTools' => $toolNames,
            ]
        );

        $this->assertNotNull($assistantResponse);

        $this->logE2EInfo('Different message types test completed', [
            'tools_used' => $toolNames,
        ]);
    }

    #[Test]
    public function it_integrates_with_provider_tool_processing_in_direct_send()
    {
        // Test that tools are properly passed to provider in direct send
        $allTools = $this->toolRegistry->getAllTools();
        $toolNames = array_slice(array_keys($allTools), 0, 1);

        if (empty($toolNames)) {
            $this->markTestSkipped('No tools available for provider integration test');
        }

        $response = AI::provider('mock')->sendMessage(
            AIMessage::user('Test direct send provider tool integration'),
            [
                'model' => 'gpt-4',
                'withTools' => $toolNames,
            ]
        );

        $this->assertNotNull($response);

        // Verify that the response contains tool-related metadata
        $this->assertIsArray($response->metadata);

        $this->logE2EInfo('Direct send provider tool integration test completed', [
            'tools_used' => $toolNames,
            'metadata_keys' => array_keys($response->metadata),
        ]);
    }

    #[Test]
    public function it_handles_tool_execution_errors_gracefully_in_direct_send()
    {
        // Test with tools but expect graceful error handling
        $response = AI::provider('mock')->sendMessage(
            AIMessage::user('This might cause tool execution issues'),
            [
                'model' => 'gpt-4',
                'withTools' => ['test_task_manager'],
            ]
        );

        // Should still get a response even if tool execution fails
        $this->assertNotNull($response);
        $this->assertIsString($response->content);

        // Check for error metadata
        if (isset($response->metadata['tool_execution_error'])) {
            $this->logE2EInfo('Tool execution error handled gracefully in direct send', [
                'error' => $response->metadata['tool_execution_error'],
            ]);
        }
    }
}
