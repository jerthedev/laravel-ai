<?php

namespace JTD\LaravelAI\Tests\E2E;

use JTD\LaravelAI\Facades\AI;
use JTD\LaravelAI\Services\AIFunctionEvent;
use JTD\LaravelAI\Services\UnifiedToolRegistry;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * ConversationBuilder Tools E2E Test
 *
 * E2E tests for withTools() and allTools() methods in ConversationBuilder pattern.
 * Tests tool validation, error handling, fluent interface chaining, and integration
 * with provider tool processing.
 */
#[Group('e2e')]
#[Group('tools')]
#[Group('conversation-builder')]
class ConversationBuilderToolsE2ETest extends E2ETestCase
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
            'test_email_sender',
            \JTD\LaravelAI\Tests\Support\TestEmailSenderListener::class,
            [
                'description' => 'Send an email to a recipient',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'to' => ['type' => 'string', 'description' => 'Email recipient'],
                        'subject' => ['type' => 'string', 'description' => 'Email subject'],
                        'body' => ['type' => 'string', 'description' => 'Email body'],
                    ],
                    'required' => ['to', 'subject', 'body'],
                ],
            ]);

        AIFunctionEvent::listen(
            'test_calculator',
            \JTD\LaravelAI\Tests\Support\TestCalculatorListener::class,
            [
                'description' => 'Perform mathematical calculations',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'operation' => ['type' => 'string', 'enum' => ['add', 'subtract', 'multiply', 'divide']],
                        'a' => ['type' => 'number', 'description' => 'First number'],
                        'b' => ['type' => 'number', 'description' => 'Second number'],
                    ],
                    'required' => ['operation', 'a', 'b'],
                ],
            ]);

        // Refresh registry to pick up new functions
        $this->toolRegistry->refreshCache();
    }

    #[Test]
    public function it_can_use_with_tools_method_with_mock_provider()
    {
        // Get available tools
        $allTools = $this->toolRegistry->getAllTools();
        $availableToolNames = array_keys($allTools);

        if (count($availableToolNames) < 2) {
            $this->markTestSkipped('Need at least 2 tools for withTools testing');
        }

        $selectedTools = array_slice($availableToolNames, 0, 2);

        // Test withTools with mock provider
        $conversation = AI::conversation()
            ->provider('mock')
            ->model('gpt-4')
            ->withTools($selectedTools)
            ->message('Use the selected tools to help me');

        // Verify fluent interface
        $this->assertInstanceOf(\JTD\LaravelAI\Services\ConversationBuilder::class, $conversation);

        // Send the message
        $response = $conversation->send();

        $this->assertNotNull($response);
        $this->assertIsString($response->content);

        $this->logE2EInfo('withTools test with mock provider completed', [
            'selected_tools' => $selectedTools,
            'response_length' => strlen($response->content),
        ]);
    }

    #[Test]
    public function it_can_use_all_tools_method_with_mock_provider()
    {
        // Test allTools with mock provider
        $conversation = AI::conversation()
            ->provider('mock')
            ->model('gpt-4')
            ->allTools()
            ->message('Use any tools you need to help me');

        // Verify fluent interface
        $this->assertInstanceOf(\JTD\LaravelAI\Services\ConversationBuilder::class, $conversation);

        // Send the message
        $response = $conversation->send();

        $this->assertNotNull($response);
        $this->assertIsString($response->content);

        $this->logE2EInfo('allTools test with mock provider completed', [
            'response_length' => strlen($response->content),
        ]);
    }

    #[Test]
    public function it_validates_tool_names_in_with_tools()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown tools: non_existent_tool');

        AI::conversation()
            ->provider('mock')
            ->withTools(['non_existent_tool'])
            ->message('This should fail')
            ->send();
    }

    #[Test]
    public function it_handles_mixed_valid_and_invalid_tools()
    {
        $allTools = $this->toolRegistry->getAllTools();
        $validTool = array_keys($allTools)[0] ?? 'test_calculator';

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown tools: invalid_tool');

        AI::conversation()
            ->provider('mock')
            ->withTools([$validTool, 'invalid_tool'])
            ->message('This should fail')
            ->send();
    }

    #[Test]
    public function it_can_chain_with_tools_with_other_methods()
    {
        $allTools = $this->toolRegistry->getAllTools();
        $toolNames = array_slice(array_keys($allTools), 0, 1);

        if (empty($toolNames)) {
            $this->markTestSkipped('No tools available for chaining test');
        }

        // Test method chaining
        $response = AI::conversation()
            ->provider('mock')
            ->model('gpt-4')
            ->temperature(0.7)
            ->maxTokens(100)
            ->withTools($toolNames)
            ->systemPrompt('You are a helpful assistant')
            ->message('Help me with a task')
            ->send();

        $this->assertNotNull($response);
        $this->assertIsString($response->content);

        $this->logE2EInfo('Method chaining test completed', [
            'tools_used' => $toolNames,
            'response_length' => strlen($response->content),
        ]);
    }

    #[Test]
    public function it_can_chain_all_tools_with_other_methods()
    {
        // Test method chaining with allTools
        $response = AI::conversation()
            ->provider('mock')
            ->model('gpt-4')
            ->temperature(0.8)
            ->maxTokens(150)
            ->allTools()
            ->systemPrompt('You are a helpful assistant with access to tools')
            ->message('Use any tools you need')
            ->send();

        $this->assertNotNull($response);
        $this->assertIsString($response->content);

        $this->logE2EInfo('allTools method chaining test completed', [
            'response_length' => strlen($response->content),
        ]);
    }

    #[Test]
    public function it_processes_tools_with_mock_provider_simulation()
    {
        // Test with tools that should trigger mock simulation
        $response = AI::conversation()
            ->provider('mock')
            ->model('gpt-4')
            ->withTools(['test_calculator'])
            ->message('Calculate 15 + 25 using the calculator tool')
            ->send();

        $this->assertNotNull($response);

        // Check if mock provider simulated tool calls
        if (isset($response->toolCalls)) {
            $this->assertIsArray($response->toolCalls);
            $this->logE2EInfo('Mock tool simulation triggered', [
                'tool_calls' => count($response->toolCalls),
            ]);
        }

        // Check metadata for tool execution results
        if (isset($response->metadata['tool_execution_results'])) {
            $this->assertIsArray($response->metadata['tool_execution_results']);
            $this->logE2EInfo('Tool execution results found', [
                'results_count' => count($response->metadata['tool_execution_results']),
            ]);
        }
    }

    #[Test]
    public function it_handles_empty_tool_arrays()
    {
        // Test with empty tool array (should work fine)
        $response = AI::conversation()
            ->provider('mock')
            ->withTools([])
            ->message('Help me without tools')
            ->send();

        $this->assertNotNull($response);
        $this->assertIsString($response->content);

        $this->logE2EInfo('Empty tools array test completed');
    }

    #[Test]
    public function it_can_use_with_tools_multiple_times()
    {
        $allTools = $this->toolRegistry->getAllTools();
        $toolNames = array_keys($allTools);

        if (count($toolNames) < 2) {
            $this->markTestSkipped('Need at least 2 tools for multiple withTools test');
        }

        // Test calling withTools multiple times (should override)
        $conversation = AI::conversation()
            ->provider('mock')
            ->withTools([$toolNames[0]])
            ->withTools([$toolNames[1]]); // This should override the first call

        $response = $conversation
            ->message('Use the tools')
            ->send();

        $this->assertNotNull($response);
        $this->logE2EInfo('Multiple withTools calls test completed');
    }

    #[Test]
    public function it_can_override_all_tools_with_with_tools()
    {
        $allTools = $this->toolRegistry->getAllTools();
        $toolNames = array_keys($allTools);

        if (empty($toolNames)) {
            $this->markTestSkipped('No tools available for override test');
        }

        // Test allTools followed by withTools (should override)
        $response = AI::conversation()
            ->provider('mock')
            ->allTools()
            ->withTools([$toolNames[0]]) // This should override allTools
            ->message('Use specific tool')
            ->send();

        $this->assertNotNull($response);
        $this->logE2EInfo('allTools override test completed');
    }

    #[Test]
    public function it_integrates_with_provider_tool_processing()
    {
        // Test that tools are properly passed to provider
        $allTools = $this->toolRegistry->getAllTools();
        $toolNames = array_slice(array_keys($allTools), 0, 1);

        if (empty($toolNames)) {
            $this->markTestSkipped('No tools available for provider integration test');
        }

        $response = AI::conversation()
            ->provider('mock')
            ->withTools($toolNames)
            ->message('Test provider tool integration')
            ->send();

        $this->assertNotNull($response);

        // Verify that the response contains tool-related metadata
        $this->assertIsArray($response->metadata);

        $this->logE2EInfo('Provider tool integration test completed', [
            'tools_used' => $toolNames,
            'metadata_keys' => array_keys($response->metadata),
        ]);
    }

    #[Test]
    public function it_handles_tool_execution_errors_gracefully()
    {
        // Test with tools but expect graceful error handling
        $response = AI::conversation()
            ->provider('mock')
            ->withTools(['test_calculator'])
            ->message('This might cause tool execution issues')
            ->send();

        // Should still get a response even if tool execution fails
        $this->assertNotNull($response);
        $this->assertIsString($response->content);

        // Check for error metadata
        if (isset($response->metadata['tool_execution_error'])) {
            $this->logE2EInfo('Tool execution error handled gracefully', [
                'error' => $response->metadata['tool_execution_error'],
            ]);
        }
    }
}
