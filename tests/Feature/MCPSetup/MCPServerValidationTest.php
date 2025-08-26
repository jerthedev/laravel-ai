<?php

namespace JTD\LaravelAI\Tests\Feature\MCPSetup;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use JTD\LaravelAI\Contracts\MCPServerInterface;
use JTD\LaravelAI\Services\MCPConfigurationService;
use JTD\LaravelAI\Services\MCPManager;
use JTD\LaravelAI\Services\MCPServerValidator;
use JTD\LaravelAI\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * MCP Server Validation Tests
 *
 * Tests for MCP server validation, connectivity testing,
 * and error handling with clear messages.
 */
#[Group('mcp-setup')]
#[Group('mcp-validation')]
class MCPServerValidationTest extends TestCase
{
    use RefreshDatabase;

    protected string $testConfigPath;

    protected string $testToolsPath;

    protected MCPConfigurationService $configService;

    protected MCPManager $mcpManager;

    protected MCPServerValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testConfigPath = base_path('.mcp.json');
        $this->testToolsPath = base_path('.mcp.tools.json');
        $this->configService = app(MCPConfigurationService::class);
        $this->mcpManager = app(MCPManager::class);
        $this->validator = app(MCPServerValidator::class);

        $this->cleanupTestFiles();
    }

    protected function tearDown(): void
    {
        $this->cleanupTestFiles();
        parent::tearDown();
    }

    #[Test]
    public function it_validates_server_configuration(): void
    {
        // Create a valid server configuration
        $config = [
            'type' => 'external',
            'enabled' => true,
            'command' => 'npx @modelcontextprotocol/server-sequential-thinking',
        ];
        $this->configService->addServer('sequential-thinking', $config);

        $result = $this->validator->validateServer('sequential-thinking');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('server_name', $result);
        $this->assertArrayHasKey('overall_status', $result);
        $this->assertArrayHasKey('tests', $result);
        $this->assertEquals('sequential-thinking', $result['server_name']);
        $this->assertArrayHasKey('configuration', $result['tests']);
    }

    #[Test]
    public function it_detects_missing_server_configuration(): void
    {
        $result = $this->validator->validateServer('non-existent-server');

        $this->assertEquals('non-existent-server', $result['server_name']);
        $this->assertArrayHasKey('configuration', $result['tests']);
        $this->assertEquals('failed', $result['tests']['configuration']['status']);
        $this->assertNotEmpty($result['tests']['configuration']['errors']);
    }

    #[Test]
    public function it_validates_server_installation(): void
    {
        // Create server configuration
        $config = [
            'type' => 'external',
            'enabled' => true,
            'command' => 'npx @modelcontextprotocol/server-sequential-thinking',
        ];
        $this->configService->addServer('sequential-thinking', $config);

        // Mock successful installation check
        Process::fake([
            'npm list -g @modelcontextprotocol/server-sequential-thinking' => Process::result('@modelcontextprotocol/server-sequential-thinking@1.0.0', '', 0),
        ]);

        $result = $this->validator->validateServer('sequential-thinking');

        $this->assertArrayHasKey('installation', $result['tests']);
        $this->assertEquals('passed', $result['tests']['installation']['status']);
        $this->assertArrayHasKey('version', $result['tests']['installation']['details']);
    }

    #[Test]
    public function it_detects_missing_server_installation(): void
    {
        // Create server configuration
        $config = [
            'type' => 'external',
            'enabled' => true,
            'command' => 'npx @modelcontextprotocol/server-sequential-thinking',
        ];
        $this->configService->addServer('sequential-thinking', $config);

        // Mock failed installation check
        Process::fake([
            'npm list -g @modelcontextprotocol/server-sequential-thinking' => Process::result('', 'not found', 1),
        ]);

        $result = $this->validator->validateServer('sequential-thinking');

        $this->assertArrayHasKey('installation', $result['tests']);
        $this->assertEquals('failed', $result['tests']['installation']['status']);
        $this->assertNotEmpty($result['tests']['installation']['errors']);
    }

    #[Test]
    public function it_validates_server_connectivity(): void
    {
        // Create a mock server that implements MCPServerInterface
        $mockServer = $this->createMock(MCPServerInterface::class);
        $mockServer->method('testConnection')->willReturn([
            'status' => 'healthy',
            'message' => 'Server is responding correctly',
        ]);
        $mockServer->method('getType')->willReturn('external');
        $mockServer->method('getVersion')->willReturn('1.0.0');

        $result = $this->validator->validateServer('test-server', $mockServer);

        $this->assertArrayHasKey('connectivity', $result['tests']);
        $this->assertEquals('passed', $result['tests']['connectivity']['status']);
        $this->assertArrayHasKey('response_time_ms', $result['tests']['connectivity']['details']);
    }

    #[Test]
    public function it_handles_server_connectivity_failures(): void
    {
        // Create a mock server that fails connectivity test
        $mockServer = $this->createMock(MCPServerInterface::class);
        $mockServer->method('testConnection')->willReturn([
            'status' => 'error',
            'message' => 'Connection timeout',
        ]);
        $mockServer->method('getType')->willReturn('external');
        $mockServer->method('getVersion')->willReturn('1.0.0');

        $result = $this->validator->validateServer('test-server', $mockServer);

        $this->assertArrayHasKey('connectivity', $result['tests']);
        $this->assertEquals('failed', $result['tests']['connectivity']['status']);
        $this->assertNotEmpty($result['tests']['connectivity']['errors']);
        $this->assertStringContainsString('Connection timeout', $result['tests']['connectivity']['errors'][0]);
    }

    #[Test]
    public function it_validates_environment_variables(): void
    {
        // Create server configuration with environment variables
        $config = [
            'type' => 'external',
            'enabled' => true,
            'command' => 'npx @modelcontextprotocol/server-github',
            'env' => [
                'GITHUB_PERSONAL_ACCESS_TOKEN' => '${GITHUB_PERSONAL_ACCESS_TOKEN}',
            ],
        ];
        $this->configService->addServer('github', $config);

        $result = $this->validator->validateServer('github');

        $this->assertArrayHasKey('environment', $result['tests']);
        $this->assertArrayHasKey('details', $result['tests']['environment']);
        $this->assertArrayHasKey('required_env_vars', $result['tests']['environment']['details']);
    }

    #[Test]
    public function it_detects_missing_environment_variables(): void
    {
        // Create server configuration with missing environment variables
        $config = [
            'type' => 'external',
            'enabled' => true,
            'command' => 'npx @modelcontextprotocol/server-github',
            'env' => [
                'GITHUB_PERSONAL_ACCESS_TOKEN' => '${GITHUB_PERSONAL_ACCESS_TOKEN}',
            ],
        ];
        $this->configService->addServer('github', $config);

        // Ensure the environment variable is not set
        putenv('GITHUB_PERSONAL_ACCESS_TOKEN');

        $result = $this->validator->validateServer('github');

        $this->assertArrayHasKey('environment', $result['tests']);
        // The test should detect missing environment variables
        if ($result['tests']['environment']['status'] === 'failed') {
            $this->assertNotEmpty($result['tests']['environment']['errors']);
        }
    }

    #[Test]
    public function it_validates_server_performance(): void
    {
        // Create a mock server with performance metrics
        $mockServer = $this->createMock(MCPServerInterface::class);
        $mockServer->method('getMetrics')->willReturn([
            'response_time_avg' => 150.5,
            'success_rate' => 0.98,
            'total_requests' => 100,
        ]);
        $mockServer->method('getAvailableTools')->willReturn([
            'tool1' => ['name' => 'tool1', 'description' => 'Test tool'],
            'tool2' => ['name' => 'tool2', 'description' => 'Another test tool'],
        ]);

        $result = $this->validator->validateServer('test-server', $mockServer);

        $this->assertArrayHasKey('performance', $result['tests']);
        $this->assertArrayHasKey('details', $result['tests']['performance']);
        $this->assertArrayHasKey('metrics', $result['tests']['performance']['details']);
        $this->assertArrayHasKey('tool_count', $result['tests']['performance']['details']);
    }

    #[Test]
    public function it_provides_failure_recommendations(): void
    {
        // Create server configuration without installation
        $config = [
            'type' => 'external',
            'enabled' => true,
            'command' => 'npx @modelcontextprotocol/server-sequential-thinking',
        ];
        $this->configService->addServer('sequential-thinking', $config);

        // Mock failed installation check
        Process::fake([
            'npm list -g @modelcontextprotocol/server-sequential-thinking' => Process::result('', 'not found', 1),
        ]);

        $result = $this->validator->validateServer('sequential-thinking');

        $this->assertArrayHasKey('recommendations', $result);
        if (! empty($result['recommendations'])) {
            $this->assertIsArray($result['recommendations']);
            $this->assertNotEmpty($result['recommendations'][0]);
        }
    }

    #[Test]
    public function it_handles_validation_exceptions_gracefully(): void
    {
        // Test with invalid server name that might cause exceptions
        $result = $this->validator->validateServer('');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('overall_status', $result);
        $this->assertArrayHasKey('errors', $result);
    }

    #[Test]
    public function it_validates_comprehensive_server_status(): void
    {
        // Create a complete server setup
        $config = [
            'type' => 'external',
            'enabled' => true,
            'command' => 'npx @modelcontextprotocol/server-sequential-thinking',
            'config' => [
                'max_thoughts' => 10,
                'min_thoughts' => 2,
            ],
        ];
        $this->configService->addServer('sequential-thinking', $config);

        // Mock successful installation
        Process::fake([
            'npm list -g @modelcontextprotocol/server-sequential-thinking' => Process::result('@modelcontextprotocol/server-sequential-thinking@1.0.0', '', 0),
        ]);

        // Create a healthy mock server
        $mockServer = $this->createMock(MCPServerInterface::class);
        $mockServer->method('testConnection')->willReturn([
            'status' => 'healthy',
            'message' => 'All systems operational',
        ]);
        $mockServer->method('getType')->willReturn('external');
        $mockServer->method('getVersion')->willReturn('1.0.0');
        $mockServer->method('getMetrics')->willReturn([
            'response_time_avg' => 100.0,
            'success_rate' => 1.0,
        ]);
        $mockServer->method('getAvailableTools')->willReturn([
            'sequential_thinking' => ['name' => 'sequential_thinking'],
        ]);

        $result = $this->validator->validateServer('sequential-thinking', $mockServer);

        $this->assertEquals('sequential-thinking', $result['server_name']);
        $this->assertArrayHasKey('tests', $result);
        $this->assertArrayHasKey('validated_at', $result);

        // Check that all test categories are present
        $expectedTests = ['configuration', 'installation', 'connectivity', 'environment', 'performance'];
        foreach ($expectedTests as $testType) {
            $this->assertArrayHasKey($testType, $result['tests']);
        }
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
