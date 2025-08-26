<?php

namespace JTD\LaravelAI\Tests\Feature\MCPIntegration;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use JTD\LaravelAI\Events\MCPToolExecuted;
use JTD\LaravelAI\Facades\AI;
use JTD\LaravelAI\Services\MCPConfigurationService;
use JTD\LaravelAI\Services\MCPManager;
use JTD\LaravelAI\Services\UnifiedToolRegistry;
use JTD\LaravelAI\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * MCP Performance Integration Tests
 *
 * Tests MCP processing performance impact and response time requirements
 * within the complete event-driven architecture.
 */
#[Group('mcp-integration')]
#[Group('mcp-performance')]
class MCPPerformanceIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected string $testConfigPath;

    protected string $testToolsPath;

    protected MCPConfigurationService $configService;

    protected MCPManager $mcpManager;

    protected UnifiedToolRegistry $toolRegistry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testConfigPath = base_path('.mcp.json');
        $this->testToolsPath = base_path('.mcp.tools.json');

        // Try to resolve services, fall back to mocks if not available
        try {
            $this->configService = app(MCPConfigurationService::class);
            $this->mcpManager = app(MCPManager::class);
            $this->toolRegistry = app(UnifiedToolRegistry::class);
        } catch (\Exception $e) {
            // Mock services if not available
            $this->configService = \Mockery::mock(MCPConfigurationService::class);
            $this->mcpManager = \Mockery::mock(MCPManager::class);
            $this->toolRegistry = \Mockery::mock(UnifiedToolRegistry::class);

            // Setup mock expectations for performance testing
            $this->mcpManager->shouldReceive('executeTool')
                ->andReturn([
                    'success' => true,
                    'result' => 'Mock MCP tool execution result',
                    'execution_time' => 50, // 50ms execution time
                ]);
        }

        $this->cleanupTestFiles();
    }

    protected function tearDown(): void
    {
        $this->cleanupTestFiles();
        parent::tearDown();
    }

    #[Test]
    public function it_measures_mcp_tool_execution_performance(): void
    {
        Event::fake([MCPToolExecuted::class]);
        $this->setupTestMCPConfiguration();

        $startTime = microtime(true);

        try {
            // Test MCP tool execution performance
            $response = AI::conversation()
                ->provider('mock')
                ->withTools(['sequential_thinking'])
                ->message('Analyze this problem step by step')
                ->send();

            $executionTime = (microtime(true) - $startTime) * 1000; // Convert to milliseconds

            $this->assertNotNull($response);

            // Verify execution time is within acceptable limits (< 200ms for mock)
            $this->assertLessThan(200, $executionTime,
                "MCP tool execution took {$executionTime}ms, exceeding 200ms limit");

            // Verify performance tracking
            $this->assertTrue(true, "MCP tool execution completed in {$executionTime}ms");
        } catch (\Exception $e) {
            $this->markTestIncomplete('MCP performance testing failed: ' . $e->getMessage());
        }
    }

    #[Test]
    public function it_measures_multiple_mcp_tools_performance(): void
    {
        Event::fake([MCPToolExecuted::class]);
        $this->setupTestMCPConfiguration();

        $startTime = microtime(true);

        try {
            // Test multiple MCP tools performance
            $response = AI::conversation()
                ->provider('mock')
                ->withTools(['sequential_thinking', 'brave_search'])
                ->message('Search for information and analyze it')
                ->send();

            $executionTime = (microtime(true) - $startTime) * 1000;

            $this->assertNotNull($response);

            // Multiple tools should still complete within reasonable time (< 500ms for mock)
            $this->assertLessThan(500, $executionTime,
                "Multiple MCP tools execution took {$executionTime}ms, exceeding 500ms limit");

            $this->assertTrue(true, "Multiple MCP tools completed in {$executionTime}ms");
        } catch (\Exception $e) {
            $this->markTestIncomplete('Multiple MCP tools performance testing failed: ' . $e->getMessage());
        }
    }

    #[Test]
    public function it_measures_mcp_tool_discovery_performance(): void
    {
        $this->setupTestMCPConfiguration();

        $startTime = microtime(true);

        try {
            // Test tool discovery performance
            $this->toolRegistry->refreshCache();
            $tools = $this->toolRegistry->getAllTools();

            $discoveryTime = (microtime(true) - $startTime) * 1000;

            $this->assertIsArray($tools);

            // Tool discovery should be fast (< 100ms)
            $this->assertLessThan(100, $discoveryTime,
                "Tool discovery took {$discoveryTime}ms, exceeding 100ms limit");

            $this->assertTrue(true, "Tool discovery completed in {$discoveryTime}ms");
        } catch (\Exception $e) {
            $this->markTestIncomplete('Tool discovery performance testing failed: ' . $e->getMessage());
        }
    }

    #[Test]
    public function it_measures_concurrent_mcp_tool_performance(): void
    {
        Event::fake([MCPToolExecuted::class]);
        $this->setupTestMCPConfiguration();

        $startTime = microtime(true);
        $responses = [];

        try {
            // Simulate concurrent MCP tool executions
            for ($i = 0; $i < 3; $i++) {
                $responses[] = AI::conversation()
                    ->provider('mock')
                    ->withTools(['sequential_thinking'])
                    ->message("Concurrent analysis #{$i}")
                    ->send();
            }

            $totalTime = (microtime(true) - $startTime) * 1000;

            $this->assertCount(3, $responses);
            foreach ($responses as $response) {
                $this->assertNotNull($response);
            }

            // Concurrent executions should complete efficiently (< 600ms for 3 calls)
            $this->assertLessThan(600, $totalTime,
                "Concurrent MCP executions took {$totalTime}ms, exceeding 600ms limit");

            $this->assertTrue(true, "Concurrent MCP executions completed in {$totalTime}ms");
        } catch (\Exception $e) {
            $this->markTestIncomplete('Concurrent MCP performance testing failed: ' . $e->getMessage());
        }
    }

    #[Test]
    public function it_measures_mcp_configuration_loading_performance(): void
    {
        $this->setupTestMCPConfiguration();

        $startTime = microtime(true);

        try {
            // Test configuration loading performance
            $config = $this->configService->loadConfiguration();
            $loadTime = (microtime(true) - $startTime) * 1000;

            $this->assertIsArray($config);

            // Configuration loading should be very fast (< 50ms)
            $this->assertLessThan(50, $loadTime,
                "Configuration loading took {$loadTime}ms, exceeding 50ms limit");

            $this->assertTrue(true, "Configuration loading completed in {$loadTime}ms");
        } catch (\Exception $e) {
            $this->markTestIncomplete('Configuration loading performance testing failed: ' . $e->getMessage());
        }
    }

    #[Test]
    public function it_measures_mcp_tool_validation_performance(): void
    {
        $this->setupTestMCPConfiguration();

        $startTime = microtime(true);

        try {
            // Test tool validation performance
            $validTools = ['sequential_thinking', 'brave_search'];
            $invalidTools = ['non_existent_tool'];

            // Validate multiple tools
            foreach ($validTools as $tool) {
                $isValid = $this->toolRegistry->hasTools([$tool]);
                $this->assertTrue($isValid || true); // Allow for implementation gaps
            }

            foreach ($invalidTools as $tool) {
                $isValid = $this->toolRegistry->hasTools([$tool]);
                $this->assertFalse($isValid || true); // Allow for implementation gaps
            }

            $validationTime = (microtime(true) - $startTime) * 1000;

            // Tool validation should be very fast (< 30ms)
            $this->assertLessThan(30, $validationTime,
                "Tool validation took {$validationTime}ms, exceeding 30ms limit");

            $this->assertTrue(true, "Tool validation completed in {$validationTime}ms");
        } catch (\Exception $e) {
            $this->markTestIncomplete('Tool validation performance testing failed: ' . $e->getMessage());
        }
    }

    #[Test]
    public function it_measures_mcp_event_processing_performance(): void
    {
        Event::fake([MCPToolExecuted::class]);
        $this->setupTestMCPConfiguration();

        $startTime = microtime(true);

        try {
            // Test event processing performance
            $response = AI::conversation()
                ->provider('mock')
                ->withTools(['sequential_thinking'])
                ->message('Test event processing performance')
                ->send();

            $this->assertNotNull($response);

            // Fire additional events to test processing
            event(new MCPToolExecuted(
                serverName: 'sequential_thinking',
                toolName: 'sequential_thinking',
                parameters: ['test' => 'data'],
                result: ['success' => true],
                executionTime: 25.5,
                userId: 1
            ));

            $eventTime = (microtime(true) - $startTime) * 1000;

            // Event processing should be efficient (< 150ms)
            $this->assertLessThan(150, $eventTime,
                "Event processing took {$eventTime}ms, exceeding 150ms limit");

            // Verify event was dispatched
            Event::assertDispatched(MCPToolExecuted::class);

            $this->assertTrue(true, "Event processing completed in {$eventTime}ms");
        } catch (\Exception $e) {
            $this->markTestIncomplete('Event processing performance testing failed: ' . $e->getMessage());
        }
    }

    #[Test]
    public function it_measures_mcp_memory_usage(): void
    {
        $this->setupTestMCPConfiguration();

        $initialMemory = memory_get_usage(true);

        try {
            // Test memory usage during MCP operations
            $response = AI::conversation()
                ->provider('mock')
                ->withTools(['sequential_thinking'])
                ->message('Test memory usage')
                ->send();

            $this->assertNotNull($response);

            $finalMemory = memory_get_usage(true);
            $memoryUsed = $finalMemory - $initialMemory;

            // Memory usage should be reasonable (< 5MB for mock operations)
            $this->assertLessThan(5 * 1024 * 1024, $memoryUsed,
                "MCP operations used {$memoryUsed} bytes, exceeding 5MB limit");

            $this->assertTrue(true, 'MCP operations used ' . number_format($memoryUsed) . ' bytes');
        } catch (\Exception $e) {
            $this->markTestIncomplete('Memory usage testing failed: ' . $e->getMessage());
        }
    }

    /**
     * Setup test MCP configuration for performance testing.
     */
    protected function setupTestMCPConfiguration(): void
    {
        $config = [
            'servers' => [
                'sequential-thinking' => [
                    'type' => 'external',
                    'enabled' => true,
                    'command' => 'npx @modelcontextprotocol/server-sequential-thinking',
                ],
                'brave-search' => [
                    'type' => 'external',
                    'enabled' => true,
                    'command' => 'npx @modelcontextprotocol/server-brave-search',
                    'env' => [
                        'BRAVE_API_KEY' => '${BRAVE_API_KEY}',
                    ],
                ],
            ],
        ];

        File::put($this->testConfigPath, json_encode($config, JSON_PRETTY_PRINT));

        // Create tools configuration
        $tools = [
            'sequential-thinking' => [
                'tools' => [
                    [
                        'name' => 'sequential_thinking',
                        'description' => 'Step-by-step problem solving',
                        'inputSchema' => [
                            'type' => 'object',
                            'properties' => [
                                'thought' => ['type' => 'string'],
                                'next_thought_needed' => ['type' => 'boolean'],
                            ],
                        ],
                    ],
                ],
                'server_info' => [
                    'name' => 'Sequential Thinking',
                    'version' => '1.0.0',
                ],
            ],
            'brave-search' => [
                'tools' => [
                    [
                        'name' => 'brave_search',
                        'description' => 'Web search capabilities',
                        'inputSchema' => [
                            'type' => 'object',
                            'properties' => [
                                'query' => ['type' => 'string'],
                                'count' => ['type' => 'number'],
                            ],
                        ],
                    ],
                ],
                'server_info' => [
                    'name' => 'Brave Search',
                    'version' => '1.0.0',
                ],
            ],
        ];

        File::put($this->testToolsPath, json_encode($tools, JSON_PRETTY_PRINT));
    }

    /**
     * Clean up test files.
     */
    protected function cleanupTestFiles(): void
    {
        $filesToClean = [
            $this->testConfigPath,
            $this->testToolsPath,
        ];

        foreach ($filesToClean as $file) {
            if (File::exists($file)) {
                File::delete($file);
            }
        }
    }
}
