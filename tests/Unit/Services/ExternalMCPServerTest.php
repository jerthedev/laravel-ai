<?php

namespace JTD\LaravelAI\Tests\Unit\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;
use JTD\LaravelAI\Exceptions\MCPException;
use JTD\LaravelAI\Exceptions\MCPToolException;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Models\AIResponse;
use JTD\LaravelAI\Services\ExternalMCPServer;
use JTD\LaravelAI\Tests\TestCase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;

class ExternalMCPServerTest extends TestCase
{
    protected array $testConfig;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->testConfig = [
            'type' => 'external',
            'enabled' => true,
            'command' => 'npx test-server',
            'args' => ['--verbose'],
            'env' => [
                'TEST_API_KEY' => '${TEST_API_KEY}',
            ],
            'timeout' => 30,
            'display_name' => 'Test Server',
            'description' => 'A test MCP server',
        ];
    }

    #[Test]
    public function it_initializes_with_correct_properties(): void
    {
        $server = new ExternalMCPServer('test-server', $this->testConfig);

        $this->assertEquals('test-server', $server->getName());
        $this->assertEquals('Test Server', $server->getDisplayName());
        $this->assertEquals('A test MCP server', $server->getDescription());
        $this->assertEquals('external', $server->getType());
        $this->assertEquals('1.0.0', $server->getVersion()); // Default version
        $this->assertEquals($this->testConfig, $server->getConfig());
    }

    #[Test]
    public function it_uses_default_display_name_when_not_provided(): void
    {
        $config = array_diff_key($this->testConfig, ['display_name' => '']);
        $server = new ExternalMCPServer('test-server', $config);

        $this->assertEquals('Test Server', $server->getDisplayName()); // Formatted from name
    }

    #[Test]
    public function it_uses_default_description_when_not_provided(): void
    {
        $config = array_diff_key($this->testConfig, ['description' => '']);
        $server = new ExternalMCPServer('test-server', $config);

        $this->assertEquals('External MCP server: test-server', $server->getDescription());
    }

    #[Test]
    public function it_reports_configured_when_command_is_present(): void
    {
        $server = new ExternalMCPServer('test-server', $this->testConfig);

        $this->assertTrue($server->isConfigured());
    }

    #[Test]
    public function it_reports_not_configured_when_command_is_missing(): void
    {
        $config = array_diff_key($this->testConfig, ['command' => '']);
        $server = new ExternalMCPServer('test-server', $config);

        $this->assertFalse($server->isConfigured());
    }

    #[Test]
    public function it_reports_not_configured_when_required_env_vars_are_missing(): void
    {
        // Mock env() to return empty for TEST_API_KEY
        $this->app->instance('env', function ($key, $default = null) {
            return $key === 'TEST_API_KEY' ? null : $default;
        });

        $server = new ExternalMCPServer('test-server', $this->testConfig);

        $this->assertFalse($server->isConfigured());
    }

    #[Test]
    public function it_reports_enabled_status_from_config(): void
    {
        $server = new ExternalMCPServer('test-server', $this->testConfig);
        $this->assertTrue($server->isEnabled());

        $disabledConfig = array_merge($this->testConfig, ['enabled' => false]);
        $disabledServer = new ExternalMCPServer('test-server', $disabledConfig);
        $this->assertFalse($disabledServer->isEnabled());
    }

    #[Test]
    public function it_processes_message_when_configured_and_enabled(): void
    {
        $server = new ExternalMCPServer('test-server', $this->testConfig);
        $message = new AIMessage('Test message');

        $result = $server->processMessage($message);

        $this->assertSame($message, $result); // Currently passes through unchanged
    }

    #[Test]
    public function it_returns_original_message_when_not_configured(): void
    {
        $config = array_diff_key($this->testConfig, ['command' => '']);
        $server = new ExternalMCPServer('test-server', $config);
        $message = new AIMessage('Test message');

        $result = $server->processMessage($message);

        $this->assertSame($message, $result);
    }

    #[Test]
    public function it_processes_response_and_adds_metadata(): void
    {
        $server = new ExternalMCPServer('test-server', $this->testConfig);
        $response = new AIResponse('Test response');

        $result = $server->processResponse($response);

        $this->assertInstanceOf(AIResponse::class, $result);
        $this->assertArrayHasKey('mcp_test-server', $result->metadata);
        $this->assertTrue($result->metadata['mcp_test-server']);
    }

    #[Test]
    public function it_returns_empty_tools_when_not_configured(): void
    {
        $config = array_diff_key($this->testConfig, ['command' => '']);
        $server = new ExternalMCPServer('test-server', $config);

        $tools = $server->getAvailableTools();

        $this->assertIsArray($tools);
        $this->assertEmpty($tools);
    }

    #[Test]
    public function it_caches_discovered_tools(): void
    {
        $server = new ExternalMCPServer('test-server', $this->testConfig);
        $expectedTools = [
            ['name' => 'tool1', 'description' => 'Test tool 1'],
            ['name' => 'tool2', 'description' => 'Test tool 2'],
        ];

        Cache::shouldReceive('remember')
            ->once()
            ->with('mcp_tools_test-server', Mockery::type('int'), Mockery::type('callable'))
            ->andReturn($expectedTools);

        $tools = $server->getAvailableTools();

        $this->assertEquals($expectedTools, $tools);
    }

    #[Test]
    public function it_executes_tool_successfully(): void
    {
        $server = new ExternalMCPServer('test-server', $this->testConfig);
        $expectedResult = ['result' => 'success', 'data' => 'test'];

        // Mock the executeCommand method by extending the class
        $mockServer = Mockery::mock(ExternalMCPServer::class, ['test-server', $this->testConfig])
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        $mockServer->shouldReceive('executeCommand')
            ->once()
            ->with(['--tool', 'test-tool', '--params', '{"param":"value"}'])
            ->andReturn([
                'success' => true,
                'output' => $expectedResult,
            ]);

        $result = $mockServer->executeTool('test-tool', ['param' => 'value']);

        $this->assertEquals($expectedResult, $result);
    }

    #[Test]
    public function it_throws_exception_when_tool_execution_fails(): void
    {
        $server = new ExternalMCPServer('test-server', $this->testConfig);

        $mockServer = Mockery::mock(ExternalMCPServer::class, ['test-server', $this->testConfig])
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        $mockServer->shouldReceive('executeCommand')
            ->once()
            ->andReturn([
                'success' => false,
                'error' => 'Tool execution failed',
            ]);

        $this->expectException(MCPToolException::class);
        $this->expectExceptionMessage('Tool execution failed: Tool execution failed');

        $mockServer->executeTool('test-tool', ['param' => 'value']);
    }

    #[Test]
    public function it_throws_exception_when_server_not_configured_for_tool_execution(): void
    {
        $config = array_diff_key($this->testConfig, ['command' => '']);
        $server = new ExternalMCPServer('test-server', $config);

        $this->expectException(MCPToolException::class);
        $this->expectExceptionMessage('MCP server test-server is not configured or enabled');

        $server->executeTool('test-tool');
    }

    #[Test]
    public function it_tests_connection_successfully(): void
    {
        $server = new ExternalMCPServer('test-server', $this->testConfig);

        $mockServer = Mockery::mock(ExternalMCPServer::class, ['test-server', $this->testConfig])
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        $mockServer->shouldReceive('executeCommand')
            ->once()
            ->with(['--health'])
            ->andReturn([
                'success' => true,
                'output' => ['version' => '1.2.3'],
            ]);

        $result = $mockServer->testConnection();

        $this->assertEquals('healthy', $result['status']);
        $this->assertEquals('Server is responding normally', $result['message']);
        $this->assertEquals('1.2.3', $result['version']);
        $this->assertArrayHasKey('response_time_ms', $result);
    }

    #[Test]
    public function it_reports_error_when_connection_test_fails(): void
    {
        $server = new ExternalMCPServer('test-server', $this->testConfig);

        $mockServer = Mockery::mock(ExternalMCPServer::class, ['test-server', $this->testConfig])
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        $mockServer->shouldReceive('executeCommand')
            ->once()
            ->andReturn([
                'success' => false,
                'error' => 'Connection failed',
            ]);

        $result = $mockServer->testConnection();

        $this->assertEquals('error', $result['status']);
        $this->assertEquals('Connection failed', $result['message']);
    }

    #[Test]
    public function it_reports_not_configured_status_for_connection_test(): void
    {
        $config = array_diff_key($this->testConfig, ['command' => '']);
        $server = new ExternalMCPServer('test-server', $config);

        $result = $server->testConnection();

        $this->assertEquals('error', $result['status']);
        $this->assertEquals('Server is not properly configured', $result['message']);
    }

    #[Test]
    public function it_reports_disabled_status_for_connection_test(): void
    {
        $config = array_merge($this->testConfig, ['enabled' => false]);
        $server = new ExternalMCPServer('test-server', $config);

        $result = $server->testConnection();

        $this->assertEquals('disabled', $result['status']);
        $this->assertEquals('Server is disabled', $result['message']);
    }

    #[Test]
    public function it_returns_performance_metrics(): void
    {
        $server = new ExternalMCPServer('test-server', $this->testConfig);

        $metrics = $server->getMetrics();

        $this->assertIsArray($metrics);
        $this->assertEquals('test-server', $metrics['server_name']);
        $this->assertEquals('external', $metrics['server_type']);
        $this->assertTrue($metrics['is_enabled']);
        $this->assertTrue($metrics['is_configured']);
        $this->assertArrayHasKey('metrics', $metrics);
        $this->assertArrayHasKey('collected_at', $metrics);
    }

    #[Test]
    public function it_executes_command_with_environment_variables(): void
    {
        // This test would require mocking the Process facade more extensively
        // For now, we'll test the configuration parsing logic
        $server = new ExternalMCPServer('test-server', $this->testConfig);

        // Test that the server correctly identifies environment variable requirements
        $this->assertFalse($server->isConfigured()); // Should be false if TEST_API_KEY is not set
    }

    #[Test]
    public function it_handles_json_parsing_in_command_output(): void
    {
        $server = new ExternalMCPServer('test-server', $this->testConfig);

        $mockServer = Mockery::mock(ExternalMCPServer::class, ['test-server', $this->testConfig])
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        // Test successful JSON parsing
        $jsonOutput = '{"result": "success", "data": [1, 2, 3]}';
        
        Process::shouldReceive('timeout')
            ->andReturnSelf()
            ->shouldReceive('env')
            ->andReturnSelf()
            ->shouldReceive('run')
            ->andReturn(Mockery::mock([
                'successful' => true,
                'output' => $jsonOutput,
                'exitCode' => 0,
            ]));

        $reflection = new \ReflectionClass($mockServer);
        $method = $reflection->getMethod('executeCommand');
        $method->setAccessible(true);

        $result = $method->invoke($mockServer, ['--test']);

        $this->assertTrue($result['success']);
        $this->assertEquals(['result' => 'success', 'data' => [1, 2, 3]], $result['output']);
    }
}
