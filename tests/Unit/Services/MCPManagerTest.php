<?php

namespace JTD\LaravelAI\Tests\Unit\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use JTD\LaravelAI\Contracts\MCPServerInterface;
use JTD\LaravelAI\Exceptions\MCPException;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Models\AIResponse;
use JTD\LaravelAI\Services\ExternalMCPServer;
use JTD\LaravelAI\Services\MCPManager;
use JTD\LaravelAI\Tests\TestCase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;

class MCPManagerTest extends TestCase
{
    protected MCPManager $mcpManager;
    protected string $configPath;
    protected string $toolsPath;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->configPath = base_path('.mcp.json');
        $this->toolsPath = base_path('.mcp.tools.json');
        
        // Clean up any existing files
        if (File::exists($this->configPath)) {
            File::delete($this->configPath);
        }
        if (File::exists($this->toolsPath)) {
            File::delete($this->toolsPath);
        }
        
        $this->mcpManager = new MCPManager();
    }

    protected function tearDown(): void
    {
        // Clean up test files
        if (File::exists($this->configPath)) {
            File::delete($this->configPath);
        }
        if (File::exists($this->toolsPath)) {
            File::delete($this->toolsPath);
        }
        
        parent::tearDown();
    }

    #[Test]
    public function it_loads_empty_configuration_when_no_file_exists(): void
    {
        $this->assertFileDoesNotExist($this->configPath);
        
        $mcpManager = new MCPManager();
        $config = $mcpManager->getConfiguration();
        
        $this->assertIsArray($config);
        $this->assertEmpty($config);
    }

    #[Test]
    public function it_loads_configuration_from_file(): void
    {
        $testConfig = [
            'servers' => [
                'test-server' => [
                    'type' => 'external',
                    'enabled' => true,
                    'command' => 'npx test-server',
                ],
            ],
            'global_config' => [
                'timeout' => 30,
                'max_concurrent' => 3,
            ],
        ];

        File::put($this->configPath, json_encode($testConfig));

        $mcpManager = new MCPManager();
        $config = $mcpManager->getConfiguration();

        $this->assertEquals($testConfig, $config);
    }

    #[Test]
    public function it_handles_invalid_json_configuration_gracefully(): void
    {
        File::put($this->configPath, 'invalid json content');

        Log::shouldReceive('error')
            ->once()
            ->with('Failed to parse MCP configuration', Mockery::type('array'));

        $mcpManager = new MCPManager();
        $config = $mcpManager->getConfiguration();

        $this->assertIsArray($config);
        $this->assertEmpty($config);
    }

    #[Test]
    public function it_registers_server_successfully(): void
    {
        $mockServer = Mockery::mock(MCPServerInterface::class);
        $mockServer->shouldReceive('getType')->andReturn('external');
        $mockServer->shouldReceive('getVersion')->andReturn('1.0.0');

        Log::shouldReceive('info')
            ->once()
            ->with('MCP server registered', Mockery::type('array'));

        $this->mcpManager->registerServer('test-server', $mockServer);

        $this->assertTrue($this->mcpManager->hasServer('test-server'));
        $this->assertSame($mockServer, $this->mcpManager->getServer('test-server'));
    }

    #[Test]
    public function it_unregisters_server_successfully(): void
    {
        $mockServer = Mockery::mock(MCPServerInterface::class);
        $mockServer->shouldReceive('getType')->andReturn('external');
        $mockServer->shouldReceive('getVersion')->andReturn('1.0.0');

        Log::shouldReceive('info')->twice(); // Register and unregister

        $this->mcpManager->registerServer('test-server', $mockServer);
        $this->assertTrue($this->mcpManager->hasServer('test-server'));

        $this->mcpManager->unregisterServer('test-server');
        $this->assertFalse($this->mcpManager->hasServer('test-server'));
        $this->assertNull($this->mcpManager->getServer('test-server'));
    }

    #[Test]
    public function it_returns_only_enabled_servers(): void
    {
        $enabledServer = Mockery::mock(MCPServerInterface::class);
        $enabledServer->shouldReceive('getType')->andReturn('external');
        $enabledServer->shouldReceive('getVersion')->andReturn('1.0.0');
        $enabledServer->shouldReceive('isEnabled')->andReturn(true);

        $disabledServer = Mockery::mock(MCPServerInterface::class);
        $disabledServer->shouldReceive('getType')->andReturn('external');
        $disabledServer->shouldReceive('getVersion')->andReturn('1.0.0');
        $disabledServer->shouldReceive('isEnabled')->andReturn(false);

        Log::shouldReceive('info')->twice();

        $this->mcpManager->registerServer('enabled-server', $enabledServer);
        $this->mcpManager->registerServer('disabled-server', $disabledServer);

        $enabledServers = $this->mcpManager->getEnabledServers();

        $this->assertCount(1, $enabledServers);
        $this->assertArrayHasKey('enabled-server', $enabledServers);
        $this->assertArrayNotHasKey('disabled-server', $enabledServers);
    }

    #[Test]
    public function it_processes_message_through_enabled_servers(): void
    {
        $message = new AIMessage('Test message');
        $processedMessage = new AIMessage('Processed message');

        $mockServer = Mockery::mock(MCPServerInterface::class);
        $mockServer->shouldReceive('getType')->andReturn('external');
        $mockServer->shouldReceive('getVersion')->andReturn('1.0.0');
        $mockServer->shouldReceive('isEnabled')->andReturn(true);
        $mockServer->shouldReceive('processMessage')
            ->with($message)
            ->andReturn($processedMessage);

        Log::shouldReceive('info')->once(); // Registration
        Log::shouldReceive('debug')->once(); // Processing

        $this->mcpManager->registerServer('test-server', $mockServer);

        $result = $this->mcpManager->processMessage($message, ['test-server']);

        $this->assertSame($processedMessage, $result);
    }

    #[Test]
    public function it_skips_unavailable_servers_during_message_processing(): void
    {
        $message = new AIMessage('Test message');

        Log::shouldReceive('warning')
            ->once()
            ->with('Attempted to use unavailable MCP server', Mockery::type('array'));

        $result = $this->mcpManager->processMessage($message, ['non-existent-server']);

        $this->assertSame($message, $result);
    }

    #[Test]
    public function it_throws_exception_when_server_processing_fails(): void
    {
        $message = new AIMessage('Test message');

        $mockServer = Mockery::mock(MCPServerInterface::class);
        $mockServer->shouldReceive('getType')->andReturn('external');
        $mockServer->shouldReceive('getVersion')->andReturn('1.0.0');
        $mockServer->shouldReceive('isEnabled')->andReturn(true);
        $mockServer->shouldReceive('processMessage')
            ->with($message)
            ->andThrow(new \Exception('Processing failed'));

        Log::shouldReceive('info')->once(); // Registration
        Log::shouldReceive('error')->once(); // Processing error

        $this->mcpManager->registerServer('test-server', $mockServer);

        $this->expectException(MCPException::class);
        $this->expectExceptionMessage("MCP server 'test-server' failed to process message: Processing failed");

        $this->mcpManager->processMessage($message, ['test-server']);
    }

    #[Test]
    public function it_processes_response_through_enabled_servers(): void
    {
        $response = new AIResponse('Test response');
        $processedResponse = new AIResponse('Processed response');

        $mockServer = Mockery::mock(MCPServerInterface::class);
        $mockServer->shouldReceive('getType')->andReturn('external');
        $mockServer->shouldReceive('getVersion')->andReturn('1.0.0');
        $mockServer->shouldReceive('isEnabled')->andReturn(true);
        $mockServer->shouldReceive('processResponse')
            ->with($response)
            ->andReturn($processedResponse);

        Log::shouldReceive('info')->once(); // Registration
        Log::shouldReceive('debug')->once(); // Processing

        $this->mcpManager->registerServer('test-server', $mockServer);

        $result = $this->mcpManager->processResponse($response, ['test-server']);

        $this->assertSame($processedResponse, $result);
    }

    #[Test]
    public function it_executes_tool_on_available_server(): void
    {
        $toolResult = ['result' => 'success'];

        $mockServer = Mockery::mock(MCPServerInterface::class);
        $mockServer->shouldReceive('getType')->andReturn('external');
        $mockServer->shouldReceive('getVersion')->andReturn('1.0.0');
        $mockServer->shouldReceive('isEnabled')->andReturn(true);
        $mockServer->shouldReceive('executeTool')
            ->with('test-tool', ['param' => 'value'])
            ->andReturn($toolResult);

        Log::shouldReceive('info')->twice(); // Registration and execution

        $this->mcpManager->registerServer('test-server', $mockServer);

        $result = $this->mcpManager->executeTool('test-server', 'test-tool', ['param' => 'value']);

        $this->assertEquals($toolResult, $result);
    }

    #[Test]
    public function it_throws_exception_when_executing_tool_on_unavailable_server(): void
    {
        $this->expectException(MCPException::class);
        $this->expectExceptionMessage("MCP server 'non-existent' is not available");

        $this->mcpManager->executeTool('non-existent', 'test-tool');
    }

    #[Test]
    public function it_discovers_tools_from_enabled_servers(): void
    {
        $tools = [
            ['name' => 'tool1', 'description' => 'Test tool 1'],
            ['name' => 'tool2', 'description' => 'Test tool 2'],
        ];

        $mockServer = Mockery::mock(MCPServerInterface::class);
        $mockServer->shouldReceive('getType')->andReturn('external');
        $mockServer->shouldReceive('getVersion')->andReturn('1.0.0');
        $mockServer->shouldReceive('isEnabled')->andReturn(true);
        $mockServer->shouldReceive('getAvailableTools')->andReturn($tools);
        $mockServer->shouldReceive('getDisplayName')->andReturn('Test Server');
        $mockServer->shouldReceive('getDescription')->andReturn('Test Description');

        Log::shouldReceive('info')->once(); // Registration

        Cache::shouldReceive('remember')
            ->once()
            ->andReturn([
                'tools' => $tools,
                'statistics' => ['servers_checked' => 1, 'tools_found' => 2, 'errors' => 0],
                'discovered_at' => now()->toISOString(),
            ]);

        $this->mcpManager->registerServer('test-server', $mockServer);

        $result = $this->mcpManager->discoverTools();

        $this->assertArrayHasKey('tools', $result);
        $this->assertArrayHasKey('statistics', $result);
        $this->assertEquals(2, $result['statistics']['tools_found']);
    }
}
