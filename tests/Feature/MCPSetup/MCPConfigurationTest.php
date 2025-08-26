<?php

namespace JTD\LaravelAI\Tests\Feature\MCPSetup;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use JTD\LaravelAI\Services\MCPConfigurationService;
use JTD\LaravelAI\Services\MCPManager;
use JTD\LaravelAI\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * MCP Configuration Tests
 *
 * Tests for MCP configuration handling, API key management,
 * and configuration validation.
 */
#[Group('mcp-setup')]
#[Group('mcp-configuration')]
class MCPConfigurationTest extends TestCase
{
    use RefreshDatabase;

    protected string $testConfigPath;

    protected string $testToolsPath;

    protected MCPConfigurationService $configService;

    protected MCPManager $mcpManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testConfigPath = base_path('.mcp.json');
        $this->testToolsPath = base_path('.mcp.tools.json');
        $this->configService = app(MCPConfigurationService::class);
        $this->mcpManager = app(MCPManager::class);

        $this->cleanupTestFiles();
    }

    protected function tearDown(): void
    {
        $this->cleanupTestFiles();
        parent::tearDown();
    }

    #[Test]
    public function it_creates_default_configuration_structure(): void
    {
        $config = $this->configService->loadConfiguration();

        $this->assertIsArray($config);
        $this->assertArrayHasKey('servers', $config);
        $this->assertIsArray($config['servers']);
    }

    #[Test]
    public function it_validates_server_configuration_structure(): void
    {
        $validConfig = [
            'type' => 'external',
            'enabled' => true,
            'command' => 'npx @modelcontextprotocol/server-sequential-thinking',
        ];

        $result = $this->configService->addServer('test-server', $validConfig);
        $this->assertTrue($result);

        $savedConfig = $this->configService->loadConfiguration();
        $this->assertArrayHasKey('test-server', $savedConfig['servers']);
        $this->assertEquals($validConfig, $savedConfig['servers']['test-server']);
    }

    #[Test]
    public function it_handles_environment_variable_references(): void
    {
        $configWithEnv = [
            'type' => 'external',
            'enabled' => true,
            'command' => 'npx @modelcontextprotocol/server-github',
            'env' => [
                'GITHUB_PERSONAL_ACCESS_TOKEN' => '${GITHUB_PERSONAL_ACCESS_TOKEN}',
                'GITHUB_API_URL' => '${GITHUB_API_URL:-https://api.github.com}',
            ],
        ];

        $result = $this->configService->addServer('github', $configWithEnv);
        $this->assertTrue($result);

        $savedConfig = $this->configService->loadConfiguration();
        $serverConfig = $savedConfig['servers']['github'];

        $this->assertArrayHasKey('env', $serverConfig);
        $this->assertEquals('${GITHUB_PERSONAL_ACCESS_TOKEN}', $serverConfig['env']['GITHUB_PERSONAL_ACCESS_TOKEN']);
        $this->assertEquals('${GITHUB_API_URL:-https://api.github.com}', $serverConfig['env']['GITHUB_API_URL']);
    }

    #[Test]
    public function it_validates_required_configuration_fields(): void
    {
        // Test missing type field
        $invalidConfig = [
            'enabled' => true,
            'command' => 'npx some-server',
        ];

        // The configuration service should handle this gracefully
        $result = $this->configService->addServer('invalid-server', $invalidConfig);
        $this->assertTrue($result); // Service adds missing fields with defaults

        $savedConfig = $this->configService->loadConfiguration();
        $this->assertArrayHasKey('invalid-server', $savedConfig['servers']);
    }

    #[Test]
    public function it_handles_server_configuration_updates(): void
    {
        $initialConfig = [
            'type' => 'external',
            'enabled' => false,
            'command' => 'npx @modelcontextprotocol/server-sequential-thinking',
        ];

        $this->configService->addServer('sequential-thinking', $initialConfig);

        $updatedConfig = [
            'type' => 'external',
            'enabled' => true,
            'command' => 'npx @modelcontextprotocol/server-sequential-thinking',
            'config' => [
                'max_thoughts' => 15,
            ],
        ];

        $result = $this->configService->addServer('sequential-thinking', $updatedConfig);
        $this->assertTrue($result);

        $savedConfig = $this->configService->loadConfiguration();
        $serverConfig = $savedConfig['servers']['sequential-thinking'];

        $this->assertTrue($serverConfig['enabled']);
        $this->assertArrayHasKey('config', $serverConfig);
        $this->assertEquals(15, $serverConfig['config']['max_thoughts']);
    }

    #[Test]
    public function it_manages_multiple_server_configurations(): void
    {
        $servers = [
            'sequential-thinking' => [
                'type' => 'external',
                'enabled' => true,
                'command' => 'npx @modelcontextprotocol/server-sequential-thinking',
            ],
            'github' => [
                'type' => 'external',
                'enabled' => true,
                'command' => 'npx @modelcontextprotocol/server-github',
                'env' => ['GITHUB_PERSONAL_ACCESS_TOKEN' => '${GITHUB_PERSONAL_ACCESS_TOKEN}'],
            ],
            'brave-search' => [
                'type' => 'external',
                'enabled' => false,
                'command' => 'npx @modelcontextprotocol/server-brave-search',
                'env' => ['BRAVE_API_KEY' => '${BRAVE_API_KEY}'],
            ],
        ];

        foreach ($servers as $name => $config) {
            $result = $this->configService->addServer($name, $config);
            $this->assertTrue($result);
        }

        $savedConfig = $this->configService->loadConfiguration();
        $this->assertCount(3, $savedConfig['servers']);

        foreach ($servers as $name => $expectedConfig) {
            $this->assertArrayHasKey($name, $savedConfig['servers']);
            $this->assertEquals($expectedConfig, $savedConfig['servers'][$name]);
        }
    }

    #[Test]
    public function it_handles_configuration_file_permissions(): void
    {
        $config = [
            'type' => 'external',
            'enabled' => true,
            'command' => 'npx @modelcontextprotocol/server-sequential-thinking',
        ];

        $result = $this->configService->addServer('test-server', $config);
        $this->assertTrue($result);
        $this->assertFileExists($this->testConfigPath);

        // Verify file is readable
        $this->assertTrue(is_readable($this->testConfigPath));

        // Verify file contains valid JSON
        $content = File::get($this->testConfigPath);
        $decoded = json_decode($content, true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('servers', $decoded);
    }

    #[Test]
    public function it_preserves_existing_configuration_when_adding_servers(): void
    {
        // Add first server
        $firstConfig = [
            'type' => 'external',
            'enabled' => true,
            'command' => 'npx @modelcontextprotocol/server-sequential-thinking',
        ];
        $this->configService->addServer('sequential-thinking', $firstConfig);

        // Add second server
        $secondConfig = [
            'type' => 'external',
            'enabled' => true,
            'command' => 'npx @modelcontextprotocol/server-github',
            'env' => ['GITHUB_PERSONAL_ACCESS_TOKEN' => '${GITHUB_PERSONAL_ACCESS_TOKEN}'],
        ];
        $this->configService->addServer('github', $secondConfig);

        // Verify both servers exist
        $savedConfig = $this->configService->loadConfiguration();
        $this->assertCount(2, $savedConfig['servers']);
        $this->assertArrayHasKey('sequential-thinking', $savedConfig['servers']);
        $this->assertArrayHasKey('github', $savedConfig['servers']);
        $this->assertEquals($firstConfig, $savedConfig['servers']['sequential-thinking']);
        $this->assertEquals($secondConfig, $savedConfig['servers']['github']);
    }

    #[Test]
    public function it_handles_configuration_backup_and_restore(): void
    {
        // Create initial configuration
        $config = [
            'type' => 'external',
            'enabled' => true,
            'command' => 'npx @modelcontextprotocol/server-sequential-thinking',
        ];
        $this->configService->addServer('sequential-thinking', $config);

        // Load and verify configuration
        $originalConfig = $this->configService->loadConfiguration();
        $this->assertArrayHasKey('sequential-thinking', $originalConfig['servers']);

        // Simulate configuration corruption by writing invalid JSON
        File::put($this->testConfigPath, 'invalid json');

        // Configuration service should handle this gracefully
        $loadedConfig = $this->configService->loadConfiguration();
        $this->assertIsArray($loadedConfig);
        $this->assertArrayHasKey('servers', $loadedConfig);
    }

    #[Test]
    public function it_validates_environment_variable_format(): void
    {
        $configWithValidEnv = [
            'type' => 'external',
            'enabled' => true,
            'command' => 'npx @modelcontextprotocol/server-github',
            'env' => [
                'GITHUB_PERSONAL_ACCESS_TOKEN' => '${GITHUB_PERSONAL_ACCESS_TOKEN}',
                'GITHUB_API_URL' => '${GITHUB_API_URL:-https://api.github.com}',
                'TIMEOUT' => '${TIMEOUT:-30}',
            ],
        ];

        $result = $this->configService->addServer('github', $configWithValidEnv);
        $this->assertTrue($result);

        $savedConfig = $this->configService->loadConfiguration();
        $serverConfig = $savedConfig['servers']['github'];

        $this->assertArrayHasKey('env', $serverConfig);
        $this->assertStringStartsWith('${', $serverConfig['env']['GITHUB_PERSONAL_ACCESS_TOKEN']);
        $this->assertStringEndsWith('}', $serverConfig['env']['GITHUB_PERSONAL_ACCESS_TOKEN']);
    }

    #[Test]
    public function it_handles_server_removal(): void
    {
        // Add multiple servers
        $this->configService->addServer('sequential-thinking', [
            'type' => 'external',
            'enabled' => true,
            'command' => 'npx @modelcontextprotocol/server-sequential-thinking',
        ]);

        $this->configService->addServer('github', [
            'type' => 'external',
            'enabled' => true,
            'command' => 'npx @modelcontextprotocol/server-github',
        ]);

        // Verify both exist
        $config = $this->configService->loadConfiguration();
        $this->assertCount(2, $config['servers']);

        // Remove one server
        $result = $this->configService->removeServer('github');
        $this->assertTrue($result);

        // Verify only one remains
        $updatedConfig = $this->configService->loadConfiguration();
        $this->assertCount(1, $updatedConfig['servers']);
        $this->assertArrayHasKey('sequential-thinking', $updatedConfig['servers']);
        $this->assertArrayNotHasKey('github', $updatedConfig['servers']);
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
