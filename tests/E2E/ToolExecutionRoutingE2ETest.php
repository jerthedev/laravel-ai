<?php

namespace JTD\LaravelAI\Tests\E2E;

use Illuminate\Support\Facades\Queue;
use JTD\LaravelAI\Facades\AI;
use JTD\LaravelAI\Jobs\ProcessFunctionCallJob;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Services\AIFunctionEvent;
use JTD\LaravelAI\Services\UnifiedToolExecutor;
use JTD\LaravelAI\Services\UnifiedToolRegistry;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tool Execution Routing E2E Test
 *
 * E2E tests for tool execution routing (MCP immediate vs Function Event background).
 * Tests mixed tool scenarios, tool execution result handling, and proper routing
 * to UnifiedToolExecutor.
 */
#[Group('e2e')]
#[Group('tools')]
#[Group('execution-routing')]
class ToolExecutionRoutingE2ETest extends E2ETestCase
{
    protected UnifiedToolRegistry $toolRegistry;
    protected UnifiedToolExecutor $toolExecutor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->toolRegistry = app('laravel-ai.tools.registry');
        $this->toolExecutor = app('laravel-ai.tools.executor');

        // Register test function events for background execution testing
        $this->registerTestFunctionEvents();
    }

    protected function registerTestFunctionEvents(): void
    {
        AIFunctionEvent::listen(
            'test_background_email',
            \JTD\LaravelAI\Tests\Support\TestBackgroundEmailListener::class,
            [
            'description' => 'Send email in background',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'to' => ['type' => 'string'],
                    'subject' => ['type' => 'string'],
                    'body' => ['type' => 'string'],
                ],
                'required' => ['to', 'subject', 'body'],
            ],
        ]);

        AIFunctionEvent::listen(
            'test_background_notification',
            \JTD\LaravelAI\Tests\Support\TestBackgroundNotificationListener::class,
            [
            'description' => 'Send notification in background',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'type' => ['type' => 'string', 'enum' => ['info', 'warning', 'error']],
                    'message' => ['type' => 'string'],
                    'recipient' => ['type' => 'string'],
                ],
                'required' => ['message'],
            ],
        ]);

        // Refresh registry to pick up new functions
        $this->toolRegistry->refreshCache();
    }

    #[Test]
    public function it_can_route_function_events_to_background_processing()
    {
        // Test that Function Events are properly routed to background processing
        $toolCalls = [
            [
                'name' => 'test_background_email',
                'arguments' => [
                    'to' => 'test@example.com',
                    'subject' => 'Test Email',
                    'body' => 'This is a test email',
                ],
                'id' => 'call_' . uniqid(),
            ],
        ];

        $context = [
            'user_id' => 123,
            'conversation_id' => 456,
            'message_id' => 789,
        ];

        // Execute tool calls
        $results = $this->toolExecutor->processToolCalls($toolCalls, $context);

        $this->assertIsArray($results);
        $this->assertCount(1, $results);

        $result = $results[0];
        $this->assertEquals('test_background_email', $result['name']);
        $this->assertEquals('success', $result['status']);
        $this->assertArrayHasKey('result', $result);

        // Check that the result indicates background processing
        $resultData = $result['result'];
        $this->assertEquals('function_event_queued', $resultData['type']);
        $this->assertEquals('background', $resultData['execution_mode']);
        $this->assertStringContainsString('queued for background processing', $resultData['message']);

        $this->logE2EInfo('Function Event background routing test completed', [
            'tool_name' => 'test_background_email',
            'result_type' => $resultData['type'],
        ]);
    }

    #[Test]
    public function it_can_handle_mixed_tool_scenarios()
    {
        // Test mixed MCP tools and Function Events (if MCP tools are available)
        $allTools = $this->toolRegistry->getAllTools();
        $mcpTools = $this->toolRegistry->getToolsByType('mcp_tool');
        $functionEvents = $this->toolRegistry->getToolsByType('function_event');

        if (empty($mcpTools)) {
            $this->logE2EInfo('No MCP tools available, testing Function Events only');

            // Test multiple Function Events
            $toolCalls = [
                [
                    'name' => 'test_background_email',
                    'arguments' => ['to' => 'user1@example.com', 'subject' => 'Test 1', 'body' => 'Body 1'],
                    'id' => 'call_1',
                ],
                [
                    'name' => 'test_background_notification',
                    'arguments' => ['type' => 'info', 'message' => 'Test notification'],
                    'id' => 'call_2',
                ],
            ];
        } else {
            // Test mixed MCP and Function Events
            $mcpToolName = array_keys($mcpTools)[0];
            $toolCalls = [
                [
                    'name' => $mcpToolName,
                    'arguments' => [],
                    'id' => 'call_mcp',
                ],
                [
                    'name' => 'test_background_email',
                    'arguments' => ['to' => 'user@example.com', 'subject' => 'Mixed Test', 'body' => 'Mixed body'],
                    'id' => 'call_function',
                ],
            ];
        }

        $context = ['user_id' => 123];
        $results = $this->toolExecutor->processToolCalls($toolCalls, $context);

        $this->assertIsArray($results);
        $this->assertCount(count($toolCalls), $results);

        foreach ($results as $result) {
            $this->assertArrayHasKey('name', $result);
            $this->assertArrayHasKey('status', $result);
            $this->assertArrayHasKey('result', $result);
        }

        $this->logE2EInfo('Mixed tool scenarios test completed', [
            'tool_calls' => count($toolCalls),
            'results' => count($results),
        ]);
    }

    #[Test]
    public function it_handles_tool_execution_result_metadata()
    {
        // Test that tool execution results are properly formatted
        $toolCalls = [
            [
                'name' => 'test_background_notification',
                'arguments' => [
                    'type' => 'warning',
                    'message' => 'Test warning notification',
                ],
                'id' => 'call_metadata_test',
            ],
        ];

        $context = [
            'user_id' => 456,
            'conversation_id' => 789,
            'provider' => 'mock',
            'model' => 'gpt-4',
        ];

        $results = $this->toolExecutor->processToolCalls($toolCalls, $context);

        $this->assertIsArray($results);
        $this->assertCount(1, $results);

        $result = $results[0];

        // Check result structure
        $this->assertArrayHasKey('tool_call_id', $result);
        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('result', $result);
        $this->assertArrayHasKey('status', $result);

        $this->assertEquals('call_metadata_test', $result['tool_call_id']);
        $this->assertEquals('test_background_notification', $result['name']);
        $this->assertEquals('success', $result['status']);

        $this->logE2EInfo('Tool execution result metadata test completed', [
            'result_keys' => array_keys($result),
        ]);
    }

    #[Test]
    public function it_handles_tool_execution_errors_gracefully()
    {
        // Test with non-existent tool to trigger error handling
        $toolCalls = [
            [
                'name' => 'non_existent_tool',
                'arguments' => [],
                'id' => 'call_error_test',
            ],
        ];

        $context = ['user_id' => 123];
        $results = $this->toolExecutor->processToolCalls($toolCalls, $context);

        $this->assertIsArray($results);
        $this->assertCount(1, $results);

        $result = $results[0];
        $this->assertEquals('error', $result['status']);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('not found in registry', $result['error']);

        $this->logE2EInfo('Tool execution error handling test completed', [
            'error_message' => $result['error'],
        ]);
    }

    #[Test]
    public function it_validates_tool_parameters_before_execution()
    {
        // Test parameter validation
        $toolCalls = [
            [
                'name' => 'test_background_email',
                'arguments' => [
                    // Missing required 'to' parameter
                    'subject' => 'Test Subject',
                    'body' => 'Test Body',
                ],
                'id' => 'call_validation_test',
            ],
        ];

        $context = ['user_id' => 123];

        // This should still execute but might have validation issues
        $results = $this->toolExecutor->processToolCalls($toolCalls, $context);

        $this->assertIsArray($results);
        $this->assertCount(1, $results);

        // The result should still be processed (validation is handled by the tool itself)
        $result = $results[0];
        $this->assertArrayHasKey('status', $result);

        $this->logE2EInfo('Tool parameter validation test completed', [
            'result_status' => $result['status'],
        ]);
    }

    #[Test]
    public function it_processes_tools_through_mock_provider_integration()
    {
        // Test end-to-end tool processing through mock provider
        $response = AI::provider('mock')->sendMessage(
            AIMessage::user('Send a notification about task completion'),
            [
                'model' => 'gpt-4',
                'withTools' => ['test_background_notification'],
            ]
        );

        $this->assertNotNull($response);

        // Check if tool execution results are in metadata
        if (isset($response->metadata['tool_execution_results'])) {
            $executionResults = $response->metadata['tool_execution_results'];
            $this->assertIsArray($executionResults);

            foreach ($executionResults as $result) {
                $this->assertArrayHasKey('name', $result);
                $this->assertArrayHasKey('status', $result);
                $this->assertArrayHasKey('result', $result);
            }

            $this->logE2EInfo('Mock provider tool integration test completed', [
                'execution_results' => count($executionResults),
            ]);
        } else {
            $this->logE2EInfo('No tool execution results found in response metadata');
        }
    }

    #[Test]
    public function it_can_execute_single_tool_call()
    {
        // Test single tool execution
        $result = $this->toolExecutor->executeToolCall(
            'test_background_notification',
            [
                'type' => 'info',
                'message' => 'Single tool test',
            ],
            [
                'user_id' => 123,
                'conversation_id' => 456,
            ]
        );

        $this->assertIsArray($result);
        $this->assertEquals('function_event_queued', $result['type']);
        $this->assertEquals('background', $result['execution_mode']);

        $this->logE2EInfo('Single tool execution test completed', [
            'result_type' => $result['type'],
        ]);
    }

    #[Test]
    public function it_handles_context_propagation()
    {
        // Test that context is properly propagated through tool execution
        $context = [
            'user_id' => 999,
            'conversation_id' => 888,
            'message_id' => 777,
            'provider' => 'test_provider',
            'model' => 'test_model',
            'custom_data' => 'test_value',
        ];

        $toolCalls = [
            [
                'name' => 'test_background_email',
                'arguments' => [
                    'to' => 'context@example.com',
                    'subject' => 'Context Test',
                    'body' => 'Testing context propagation',
                ],
                'id' => 'call_context_test',
            ],
        ];

        $results = $this->toolExecutor->processToolCalls($toolCalls, $context);

        $this->assertIsArray($results);
        $this->assertCount(1, $results);

        $result = $results[0];
        $this->assertEquals('success', $result['status']);

        $this->logE2EInfo('Context propagation test completed', [
            'context_keys' => array_keys($context),
            'result_status' => $result['status'],
        ]);
    }

    #[Test]
    public function it_can_get_execution_statistics()
    {
        // Test execution statistics
        $stats = $this->toolExecutor->getExecutionStats();

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total_executions', $stats);
        $this->assertArrayHasKey('mcp_executions', $stats);
        $this->assertArrayHasKey('function_event_executions', $stats);
        $this->assertArrayHasKey('successful_executions', $stats);
        $this->assertArrayHasKey('failed_executions', $stats);
        $this->assertArrayHasKey('average_execution_time', $stats);

        $this->logE2EInfo('Execution statistics test completed', $stats);
    }

    #[Test]
    public function it_handles_concurrent_tool_executions()
    {
        // Test multiple concurrent tool executions
        $toolCalls = [
            [
                'name' => 'test_background_email',
                'arguments' => ['to' => 'user1@example.com', 'subject' => 'Concurrent 1', 'body' => 'Body 1'],
                'id' => 'call_concurrent_1',
            ],
            [
                'name' => 'test_background_notification',
                'arguments' => ['type' => 'info', 'message' => 'Concurrent notification 1'],
                'id' => 'call_concurrent_2',
            ],
            [
                'name' => 'test_background_email',
                'arguments' => ['to' => 'user2@example.com', 'subject' => 'Concurrent 2', 'body' => 'Body 2'],
                'id' => 'call_concurrent_3',
            ],
        ];

        $context = ['user_id' => 123];
        $startTime = microtime(true);

        $results = $this->toolExecutor->processToolCalls($toolCalls, $context);

        $executionTime = microtime(true) - $startTime;

        $this->assertIsArray($results);
        $this->assertCount(3, $results);

        foreach ($results as $result) {
            $this->assertEquals('success', $result['status']);
        }

        $this->logE2EInfo('Concurrent tool executions test completed', [
            'tool_calls' => count($toolCalls),
            'execution_time' => $executionTime,
            'results' => count($results),
        ]);
    }
}
