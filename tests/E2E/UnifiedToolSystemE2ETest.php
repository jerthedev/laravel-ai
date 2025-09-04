<?php

namespace JTD\LaravelAI\Tests\E2E;

use JTD\LaravelAI\Services\AIFunctionEvent;
use JTD\LaravelAI\Services\UnifiedToolExecutor;
use JTD\LaravelAI\Services\UnifiedToolRegistry;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Unified Tool System E2E Test
 *
 * Comprehensive E2E test for unified tool discovery, registration, and basic functionality.
 * Tests MCP tool loading from .mcp.tools.json, Function Event discovery, fallback to live discovery,
 * and tool metadata validation.
 */
#[Group('e2e')]
#[Group('tools')]
class UnifiedToolSystemE2ETest extends E2ETestCase
{
    protected UnifiedToolRegistry $toolRegistry;

    protected UnifiedToolExecutor $toolExecutor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->toolRegistry = app('laravel-ai.tools.registry');
        $this->toolExecutor = app('laravel-ai.tools.executor');
    }

    #[Test]
    public function it_can_discover_and_register_tools_from_unified_registry()
    {
        // Test that the unified tool registry is properly registered
        $this->assertInstanceOf(UnifiedToolRegistry::class, $this->toolRegistry);
        $this->assertInstanceOf(UnifiedToolExecutor::class, $this->toolExecutor);

        // Get all tools from the registry
        $allTools = $this->toolRegistry->getAllTools();

        $this->assertIsArray($allTools);
        $this->logE2EInfo('Discovered tools', ['count' => count($allTools), 'tools' => array_keys($allTools)]);

        // Verify tool structure
        foreach ($allTools as $toolName => $tool) {
            $this->assertIsString($toolName);
            $this->assertIsArray($tool);

            // Check required fields
            $this->assertArrayHasKey('name', $tool);
            $this->assertArrayHasKey('type', $tool);
            $this->assertArrayHasKey('execution_mode', $tool);
            $this->assertArrayHasKey('source', $tool);

            // Validate tool types
            $this->assertContains($tool['type'], ['mcp_tool', 'function_event']);
            $this->assertContains($tool['execution_mode'], ['immediate', 'background']);
            $this->assertContains($tool['source'], ['mcp', 'function_event']);
        }
    }

    #[Test]
    public function it_can_load_mcp_tools_from_configuration()
    {
        // Get MCP tools specifically
        $mcpTools = $this->toolRegistry->getToolsByType('mcp_tool');

        $this->assertIsArray($mcpTools);
        $this->logE2EInfo('MCP tools discovered', ['count' => count($mcpTools)]);

        foreach ($mcpTools as $toolName => $tool) {
            $this->assertEquals('mcp_tool', $tool['type']);
            $this->assertEquals('immediate', $tool['execution_mode']);
            $this->assertEquals('mcp', $tool['source']);

            // Check MCP-specific fields
            if (isset($tool['server'])) {
                $this->assertIsString($tool['server']);
            }
        }
    }

    #[Test]
    public function it_can_discover_function_events()
    {
        // Register a test function event
        AIFunctionEvent::listen(
            'test_function_event',
            \JTD\LaravelAI\Tests\Support\TestFunctionEventListener::class,
            [
                'description' => 'Test function for E2E testing',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'message' => ['type' => 'string'],
                    ],
                    'required' => ['message'],
                ],
            ]
        );

        // Refresh the registry to pick up the new function
        $this->toolRegistry->refreshCache();

        // Get Function Events specifically
        $functionEvents = $this->toolRegistry->getToolsByType('function_event');

        $this->assertIsArray($functionEvents);
        $this->assertArrayHasKey('test_function_event', $functionEvents);

        $testTool = $functionEvents['test_function_event'];
        $this->assertEquals('function_event', $testTool['type']);
        $this->assertEquals('background', $testTool['execution_mode']);
        $this->assertEquals('function_event', $testTool['source']);
        $this->assertEquals('Test function for E2E testing', $testTool['description']);

        $this->logE2EInfo('Function Events discovered', ['count' => count($functionEvents)]);
    }

    #[Test]
    public function it_can_validate_tool_names()
    {
        // Get some existing tools
        $allTools = $this->toolRegistry->getAllTools();
        $existingToolNames = array_slice(array_keys($allTools), 0, 2);

        if (empty($existingToolNames)) {
            $this->markTestSkipped('No tools available for validation testing');
        }

        // Test valid tool names
        $missingTools = $this->toolRegistry->validateToolNames($existingToolNames);
        $this->assertEmpty($missingTools, 'Valid tool names should not be reported as missing');

        // Test invalid tool names
        $invalidTools = ['non_existent_tool_1', 'non_existent_tool_2'];
        $missingTools = $this->toolRegistry->validateToolNames($invalidTools);
        $this->assertEquals($invalidTools, $missingTools, 'Invalid tool names should be reported as missing');

        // Test mixed valid and invalid
        $mixedTools = array_merge($existingToolNames, $invalidTools);
        $missingTools = $this->toolRegistry->validateToolNames($mixedTools);
        $this->assertEquals($invalidTools, $missingTools, 'Only invalid tools should be reported as missing');

        $this->logE2EInfo('Tool validation test completed', [
            'existing_tools' => $existingToolNames,
            'invalid_tools' => $invalidTools,
        ]);
    }

    #[Test]
    public function it_can_get_tools_by_execution_mode()
    {
        // Test immediate execution tools (MCP)
        $immediateTools = $this->toolRegistry->getToolsByExecutionMode('immediate');
        $this->assertIsArray($immediateTools);

        foreach ($immediateTools as $tool) {
            $this->assertEquals('immediate', $tool['execution_mode']);
        }

        // Test background execution tools (Function Events)
        $backgroundTools = $this->toolRegistry->getToolsByExecutionMode('background');
        $this->assertIsArray($backgroundTools);

        foreach ($backgroundTools as $tool) {
            $this->assertEquals('background', $tool['execution_mode']);
        }

        $this->logE2EInfo('Tools by execution mode', [
            'immediate_count' => count($immediateTools),
            'background_count' => count($backgroundTools),
        ]);
    }

    #[Test]
    public function it_can_search_tools()
    {
        $allTools = $this->toolRegistry->getAllTools();

        if (empty($allTools)) {
            $this->markTestSkipped('No tools available for search testing');
        }

        // Get a tool name to search for
        $firstToolName = array_keys($allTools)[0];
        $searchResults = $this->toolRegistry->searchTools($firstToolName);

        $this->assertIsArray($searchResults);
        $this->assertArrayHasKey($firstToolName, $searchResults);

        // Test partial search
        $partialName = substr($firstToolName, 0, 3);
        if (strlen($partialName) >= 3) {
            $partialResults = $this->toolRegistry->searchTools($partialName);
            $this->assertIsArray($partialResults);
        }

        $this->logE2EInfo('Tool search test completed', [
            'search_term' => $firstToolName,
            'results_count' => count($searchResults),
        ]);
    }

    #[Test]
    public function it_can_get_tool_statistics()
    {
        $stats = $this->toolRegistry->getStats();

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total_tools', $stats);
        $this->assertArrayHasKey('mcp_tools', $stats);
        $this->assertArrayHasKey('function_events', $stats);
        $this->assertArrayHasKey('immediate_execution', $stats);
        $this->assertArrayHasKey('background_execution', $stats);
        $this->assertArrayHasKey('categories', $stats);

        // Validate statistics consistency
        $this->assertEquals(
            $stats['total_tools'],
            $stats['mcp_tools'] + $stats['function_events'],
            'Total tools should equal sum of MCP tools and Function Events'
        );

        $this->assertEquals(
            $stats['total_tools'],
            $stats['immediate_execution'] + $stats['background_execution'],
            'Total tools should equal sum of immediate and background execution tools'
        );

        $this->logE2EInfo('Tool statistics', $stats);
    }

    #[Test]
    public function it_can_refresh_tool_cache()
    {
        // Get initial tool count
        $initialTools = $this->toolRegistry->getAllTools();
        $initialCount = count($initialTools);

        // Register a new function event
        AIFunctionEvent::listen(
            'cache_refresh_test',
            \JTD\LaravelAI\Tests\Support\TestFunctionEventListener::class,
            [
                'description' => 'Test function for cache refresh testing',
            ]
        );

        // Tools should still be the same (cached)
        $cachedTools = $this->toolRegistry->getAllTools();
        $this->assertCount($initialCount, $cachedTools);

        // Refresh cache
        $this->toolRegistry->refreshCache();

        // Now we should see the new tool
        $refreshedTools = $this->toolRegistry->getAllTools();
        $this->assertGreaterThan($initialCount, count($refreshedTools));
        $this->assertArrayHasKey('cache_refresh_test', $refreshedTools);

        $this->logE2EInfo('Cache refresh test completed', [
            'initial_count' => $initialCount,
            'after_refresh_count' => count($refreshedTools),
        ]);
    }

    #[Test]
    public function it_handles_fallback_to_live_discovery()
    {
        // Clear the cache to force live discovery
        $this->toolRegistry->refreshCache();

        // Get tools (should trigger live discovery)
        $tools = $this->toolRegistry->getAllTools(true); // Force refresh

        $this->assertIsArray($tools);

        // Verify that tools are properly discovered even without cache
        foreach ($tools as $toolName => $tool) {
            $this->assertIsString($toolName);
            $this->assertIsArray($tool);
            $this->assertArrayHasKey('type', $tool);
            $this->assertArrayHasKey('execution_mode', $tool);
        }

        $this->logE2EInfo('Live discovery fallback test completed', [
            'tools_discovered' => count($tools),
        ]);
    }

    #[Test]
    public function it_validates_tool_metadata_structure()
    {
        $allTools = $this->toolRegistry->getAllTools();

        foreach ($allTools as $toolName => $tool) {
            // Validate required metadata fields
            $this->assertArrayHasKey('name', $tool);
            $this->assertArrayHasKey('description', $tool);
            $this->assertArrayHasKey('parameters', $tool);
            $this->assertArrayHasKey('type', $tool);
            $this->assertArrayHasKey('execution_mode', $tool);
            $this->assertArrayHasKey('source', $tool);

            // Validate parameter structure
            $parameters = $tool['parameters'];
            $this->assertIsArray($parameters);

            if (isset($parameters['type'])) {
                $this->assertEquals('object', $parameters['type']);
            }

            if (isset($parameters['properties'])) {
                $this->assertIsArray($parameters['properties']);
            }

            // Validate type-specific metadata
            if ($tool['type'] === 'mcp_tool') {
                // MCP tools should have server information
                $this->assertArrayHasKey('server', $tool);
            }

            if ($tool['type'] === 'function_event') {
                // Function events should have category
                $this->assertArrayHasKey('category', $tool);
            }
        }

        $this->logE2EInfo('Tool metadata validation completed', [
            'validated_tools' => count($allTools),
        ]);
    }
}
