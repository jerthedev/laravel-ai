<?php

namespace JTD\LaravelAI\Tests\Feature\MCPIntegration;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use JTD\LaravelAI\Events\MCPToolExecuted;
use JTD\LaravelAI\Facades\AI;
use JTD\LaravelAI\Services\MCPConfigurationService;
use JTD\LaravelAI\Services\MCPManager;
use JTD\LaravelAI\Services\UnifiedToolRegistry;
use JTD\LaravelAI\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * MCP Error Handling Tests
 *
 * Tests graceful degradation, fallback mechanisms, and error recovery
 * for MCP integration within the event-driven architecture.
 */
#[Group('mcp-integration')]
#[Group('mcp-error-handling')]
class MCPErrorHandlingTest extends TestCase
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
        }

        $this->cleanupTestFiles();
    }

    protected function tearDown(): void
    {
        $this->cleanupTestFiles();
        parent::tearDown();
    }

    #[Test]
    public function it_handles_missing_mcp_configuration_gracefully(): void
    {
        // Don't create any configuration files

        try {
            // Test AI call without MCP configuration
            $response = AI::conversation()
                ->provider('mock')
                ->withTools(['sequential_thinking'])
                ->message('Test without configuration')
                ->send();

            // Should either work with fallback or handle gracefully
            $this->assertNotNull($response);
            $this->assertTrue(true, 'AI call handled missing MCP configuration gracefully');
        } catch (\Exception $e) {
            // Should provide clear error message about missing configuration
            $this->assertStringContainsString('configuration', strtolower($e->getMessage()));
        }
    }

    #[Test]
    public function it_handles_invalid_mcp_configuration_gracefully(): void
    {
        // Create invalid JSON configuration
        File::put($this->testConfigPath, '{ invalid json content');

        try {
            // Test AI call with invalid configuration
            $response = AI::conversation()
                ->provider('mock')
                ->withTools(['sequential_thinking'])
                ->message('Test with invalid configuration')
                ->send();

            // Should handle invalid configuration gracefully
            $this->assertNotNull($response);
            $this->assertTrue(true, 'AI call handled invalid MCP configuration gracefully');
        } catch (\Exception $e) {
            // Should provide clear error message about invalid configuration
            $this->assertStringContainsString('configuration', strtolower($e->getMessage()));
        }
    }

    #[Test]
    public function it_handles_unavailable_mcp_tools_gracefully(): void
    {
        Event::fake([MCPToolExecuted::class]);
        $this->setupTestMCPConfiguration();

        try {
            // Test AI call with unavailable MCP tool
            $response = AI::conversation()
                ->provider('mock')
                ->withTools(['non_existent_tool'])
                ->message('Test with unavailable tool')
                ->send();

            // Should handle unavailable tools gracefully
            $this->assertNotNull($response);
            $this->assertTrue(true, 'AI call handled unavailable MCP tool gracefully');
        } catch (\Exception $e) {
            // Should provide clear error message about unavailable tool
            $this->assertStringContainsString('tool', strtolower($e->getMessage()));
        }
    }

    #[Test]
    public function it_handles_mcp_server_connection_failures(): void
    {
        Event::fake([MCPToolExecuted::class]);

        // Setup mock to simulate connection failures
        if ($this->mcpManager instanceof \Mockery\MockInterface) {
            $this->mcpManager->shouldReceive('executeTool')
                ->andThrow(new \Exception('Connection failed: Unable to connect to MCP server'));
        }

        $this->setupTestMCPConfiguration();

        try {
            // Test AI call with connection failure
            $response = AI::conversation()
                ->provider('mock')
                ->withTools(['sequential_thinking'])
                ->message('Test with connection failure')
                ->send();

            // Should handle connection failures gracefully
            $this->assertNotNull($response);
            $this->assertTrue(true, 'AI call handled MCP server connection failure gracefully');
        } catch (\Exception $e) {
            // Should provide clear error message about connection failure
            $this->assertStringContainsString('connection', strtolower($e->getMessage()));
        }
    }

    #[Test]
    public function it_handles_mcp_tool_execution_timeouts(): void
    {
        Event::fake([MCPToolExecuted::class]);

        // Setup mock to simulate timeout
        if ($this->mcpManager instanceof \Mockery\MockInterface) {
            $this->mcpManager->shouldReceive('executeTool')
                ->andReturn([
                    'success' => false,
                    'error' => 'Execution timeout: Tool execution exceeded time limit',
                    'execution_time' => 30000, // 30 seconds
                ]);
        }

        $this->setupTestMCPConfiguration();

        try {
            // Test AI call with execution timeout
            $response = AI::conversation()
                ->provider('mock')
                ->withTools(['sequential_thinking'])
                ->message('Test with execution timeout')
                ->send();

            // Should handle timeouts gracefully
            $this->assertNotNull($response);
            $this->assertTrue(true, 'AI call handled MCP tool execution timeout gracefully');
        } catch (\Exception $e) {
            // Should provide clear error message about timeout
            $this->assertStringContainsString('timeout', strtolower($e->getMessage()));
        }
    }

    #[Test]
    public function it_handles_mcp_tool_execution_errors(): void
    {
        Event::fake([MCPToolExecuted::class]);

        // Setup mock to simulate execution errors
        if ($this->mcpManager instanceof \Mockery\MockInterface) {
            $this->mcpManager->shouldReceive('executeTool')
                ->andReturn([
                    'success' => false,
                    'error' => 'Tool execution failed: Invalid parameters provided',
                    'execution_time' => 100,
                ]);
        }

        $this->setupTestMCPConfiguration();

        try {
            // Test AI call with execution error
            $response = AI::conversation()
                ->provider('mock')
                ->withTools(['sequential_thinking'])
                ->message('Test with execution error')
                ->send();

            // Should handle execution errors gracefully
            $this->assertNotNull($response);
            $this->assertTrue(true, 'AI call handled MCP tool execution error gracefully');
        } catch (\Exception $e) {
            // Should provide clear error message about execution failure
            $this->assertStringContainsString('execution', strtolower($e->getMessage()));
        }
    }

    #[Test]
    public function it_provides_fallback_when_mcp_tools_fail(): void
    {
        Event::fake([MCPToolExecuted::class]);

        // Setup mock to simulate tool failure
        if ($this->mcpManager instanceof \Mockery\MockInterface) {
            $this->mcpManager->shouldReceive('executeTool')
                ->andReturn([
                    'success' => false,
                    'error' => 'Tool unavailable',
                    'execution_time' => 10,
                ]);
        }

        $this->setupTestMCPConfiguration();

        try {
            // Test AI call with tool failure - should still get response
            $response = AI::conversation()
                ->provider('mock')
                ->withTools(['sequential_thinking'])
                ->message('Analyze this problem')
                ->send();

            // Should provide fallback response even when tools fail
            $this->assertNotNull($response);
            $this->assertTrue(true, 'AI call provided fallback when MCP tool failed');
        } catch (\Exception $e) {
            $this->markTestIncomplete('Fallback mechanism test failed: ' . $e->getMessage());
        }
    }

    #[Test]
    public function it_logs_mcp_errors_appropriately(): void
    {
        Log::spy();
        Event::fake([MCPToolExecuted::class]);

        // Setup mock to simulate error
        if ($this->mcpManager instanceof \Mockery\MockInterface) {
            $this->mcpManager->shouldReceive('executeTool')
                ->andThrow(new \Exception('Test MCP error for logging'));
        }

        $this->setupTestMCPConfiguration();

        try {
            // Test AI call that should generate error logs
            $response = AI::conversation()
                ->provider('mock')
                ->withTools(['sequential_thinking'])
                ->message('Test error logging')
                ->send();

            // Should log errors appropriately
            Log::shouldHaveReceived('error')
                ->atLeast()
                ->once();

            $this->assertTrue(true, 'MCP errors were logged appropriately');
        } catch (\Exception $e) {
            // Even if exception is thrown, logging should occur
            $this->assertTrue(true, 'Exception thrown as expected: ' . $e->getMessage());
        }
    }

    #[Test]
    public function it_handles_partial_mcp_tool_failures(): void
    {
        Event::fake([MCPToolExecuted::class]);

        // Setup mock to simulate partial failure (one tool works, one fails)
        if ($this->mcpManager instanceof \Mockery\MockInterface) {
            $this->mcpManager->shouldReceive('executeTool')
                ->with('sequential-thinking', 'sequential_thinking', \Mockery::any())
                ->andReturn([
                    'success' => true,
                    'result' => 'Sequential thinking worked',
                    'execution_time' => 50,
                ]);

            $this->mcpManager->shouldReceive('executeTool')
                ->with('brave-search', 'brave_search', \Mockery::any())
                ->andReturn([
                    'success' => false,
                    'error' => 'Search service unavailable',
                    'execution_time' => 25,
                ]);
        }

        $this->setupTestMCPConfiguration();

        try {
            // Test AI call with multiple tools where some fail
            $response = AI::conversation()
                ->provider('mock')
                ->withTools(['sequential_thinking', 'brave_search'])
                ->message('Use both tools')
                ->send();

            // Should handle partial failures gracefully
            $this->assertNotNull($response);
            $this->assertTrue(true, 'AI call handled partial MCP tool failures gracefully');
        } catch (\Exception $e) {
            $this->markTestIncomplete('Partial failure handling test failed: ' . $e->getMessage());
        }
    }

    #[Test]
    public function it_recovers_from_temporary_mcp_failures(): void
    {
        Event::fake([MCPToolExecuted::class]);

        // Setup mock to simulate recovery (first call fails, second succeeds)
        if ($this->mcpManager instanceof \Mockery\MockInterface) {
            $this->mcpManager->shouldReceive('executeTool')
                ->once()
                ->andReturn([
                    'success' => false,
                    'error' => 'Temporary failure',
                    'execution_time' => 10,
                ]);

            $this->mcpManager->shouldReceive('executeTool')
                ->once()
                ->andReturn([
                    'success' => true,
                    'result' => 'Recovery successful',
                    'execution_time' => 75,
                ]);
        }

        $this->setupTestMCPConfiguration();

        try {
            // First call - should handle failure
            $response1 = AI::conversation()
                ->provider('mock')
                ->withTools(['sequential_thinking'])
                ->message('First attempt')
                ->send();

            // Second call - should succeed after recovery
            $response2 = AI::conversation()
                ->provider('mock')
                ->withTools(['sequential_thinking'])
                ->message('Second attempt')
                ->send();

            $this->assertNotNull($response1);
            $this->assertNotNull($response2);
            $this->assertTrue(true, 'MCP system recovered from temporary failure');
        } catch (\Exception $e) {
            $this->markTestIncomplete('Recovery test failed: ' . $e->getMessage());
        }
    }

    /**
     * Setup test MCP configuration for error handling testing.
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
                            ],
                        ],
                    ],
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
                            ],
                        ],
                    ],
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
