<?php

namespace JTD\LaravelAI\Tests\Unit\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use JTD\LaravelAI\Exceptions\MCPException;
use JTD\LaravelAI\Services\MCPConfigurationService;
use JTD\LaravelAI\Tests\TestCase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;

class MCPConfigurationServiceTest extends TestCase
{
    protected MCPConfigurationService $configService;

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

        $this->configService = new MCPConfigurationService;
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
    public function it_returns_default_configuration_when_file_does_not_exist(): void
    {
        $this->assertFileDoesNotExist($this->configPath);

        $config = $this->configService->loadConfiguration();

        $this->assertIsArray($config);
        $this->assertArrayHasKey('servers', $config);
        $this->assertArrayHasKey('global_config', $config);
        $this->assertEmpty($config['servers']);
        $this->assertIsArray($config['global_config']);
    }

    #[Test]
    public function it_loads_configuration_from_existing_file(): void
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
                'timeout' => 60,
                'max_concurrent' => 5,
            ],
        ];

        File::put($this->configPath, json_encode($testConfig));

        $config = $this->configService->loadConfiguration();

        $this->assertEquals($testConfig['servers'], $config['servers']);
        $this->assertEquals(60, $config['global_config']['timeout']);
        $this->assertEquals(5, $config['global_config']['max_concurrent']);
    }

    #[Test]
    public function it_merges_with_defaults_when_loading_partial_configuration(): void
    {
        $partialConfig = [
            'servers' => [
                'test-server' => [
                    'type' => 'external',
                    'enabled' => true,
                ],
            ],
        ];

        File::put($this->configPath, json_encode($partialConfig));

        $config = $this->configService->loadConfiguration();

        $this->assertEquals($partialConfig['servers'], $config['servers']);
        $this->assertArrayHasKey('global_config', $config);
        $this->assertArrayHasKey('timeout', $config['global_config']);
        $this->assertEquals(30, $config['global_config']['timeout']); // Default value
    }

    #[Test]
    public function it_throws_exception_for_invalid_json(): void
    {
        File::put($this->configPath, 'invalid json content');

        Log::shouldReceive('error')
            ->once()
            ->with('Failed to parse MCP configuration', Mockery::type('array'));

        $this->expectException(MCPException::class);
        $this->expectExceptionMessage('Invalid MCP configuration file');

        $this->configService->loadConfiguration();
    }

    #[Test]
    public function it_saves_configuration_successfully(): void
    {
        $config = [
            'servers' => [
                'test-server' => [
                    'type' => 'external',
                    'enabled' => true,
                    'command' => 'npx test-server',
                ],
            ],
            'global_config' => [
                'timeout' => 45,
            ],
        ];

        Log::shouldReceive('info')
            ->once()
            ->with('MCP configuration saved', ['file' => $this->configPath]);

        $result = $this->configService->saveConfiguration($config);

        $this->assertTrue($result);
        $this->assertFileExists($this->configPath);

        $savedContent = File::get($this->configPath);
        $savedConfig = json_decode($savedContent, true);

        $this->assertEquals($config, $savedConfig);
    }

    #[Test]
    public function it_fails_to_save_invalid_configuration(): void
    {
        $invalidConfig = [
            'servers' => [
                'invalid-server' => [
                    'type' => 'invalid-type', // Invalid type
                    'enabled' => 'not-boolean', // Invalid boolean
                ],
            ],
        ];

        $result = $this->configService->saveConfiguration($invalidConfig);

        $this->assertFalse($result);
        $this->assertFileDoesNotExist($this->configPath);
    }

    #[Test]
    public function it_validates_configuration_structure(): void
    {
        $validConfig = [
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

        $validation = $this->configService->validateConfiguration($validConfig);

        $this->assertTrue($validation['valid']);
        $this->assertEmpty($validation['errors']);
    }

    #[Test]
    public function it_detects_configuration_validation_errors(): void
    {
        $invalidConfig = [
            'servers' => [
                'test-server' => [
                    'type' => 'invalid-type',
                    'enabled' => 'not-boolean',
                    // Missing required command for external type
                ],
            ],
            'global_config' => [
                'timeout' => -1, // Invalid timeout
            ],
        ];

        $validation = $this->configService->validateConfiguration($invalidConfig);

        $this->assertFalse($validation['valid']);
        $this->assertNotEmpty($validation['errors']);
        $this->assertContains('The servers.test-server.type field must be one of: external.', $validation['errors']);
    }

    #[Test]
    public function it_validates_server_names(): void
    {
        $configWithInvalidName = [
            'servers' => [
                'Invalid Server Name!' => [ // Invalid characters
                    'type' => 'external',
                    'enabled' => true,
                    'command' => 'npx test',
                ],
            ],
        ];

        $validation = $this->configService->validateConfiguration($configWithInvalidName);

        $this->assertFalse($validation['valid']);
        $this->assertContains(
            "Server name 'Invalid Server Name!' must contain only lowercase letters, numbers, hyphens, and underscores",
            $validation['errors']
        );
    }

    #[Test]
    public function it_warns_about_missing_environment_variables(): void
    {
        $configWithEnvVars = [
            'servers' => [
                'test-server' => [
                    'type' => 'external',
                    'enabled' => true,
                    'command' => 'npx test-server',
                    'env' => [
                        'MISSING_VAR' => '${MISSING_VAR}',
                    ],
                ],
            ],
        ];

        $validation = $this->configService->validateConfiguration($configWithEnvVars);

        $this->assertTrue($validation['valid']); // Should still be valid
        $this->assertNotEmpty($validation['warnings']);
        $this->assertContains(
            "Environment variable 'MISSING_VAR' for server 'test-server' is not set",
            $validation['warnings']
        );
    }

    #[Test]
    public function it_loads_tools_configuration(): void
    {
        $toolsConfig = [
            'test-server' => [
                'tools' => [
                    ['name' => 'tool1', 'description' => 'Test tool 1'],
                    ['name' => 'tool2', 'description' => 'Test tool 2'],
                ],
                'discovered_at' => '2024-01-01T00:00:00Z',
            ],
        ];

        File::put($this->toolsPath, json_encode($toolsConfig));

        $tools = $this->configService->loadToolsConfiguration();

        $this->assertEquals($toolsConfig, $tools);
    }

    #[Test]
    public function it_returns_empty_array_when_tools_file_does_not_exist(): void
    {
        $this->assertFileDoesNotExist($this->toolsPath);

        $tools = $this->configService->loadToolsConfiguration();

        $this->assertIsArray($tools);
        $this->assertEmpty($tools);
    }

    #[Test]
    public function it_saves_tools_configuration(): void
    {
        $toolsConfig = [
            'test-server' => [
                'tools' => [
                    ['name' => 'tool1', 'description' => 'Test tool 1'],
                ],
            ],
        ];

        Log::shouldReceive('info')
            ->once()
            ->with('MCP tools configuration saved', ['file' => $this->toolsPath]);

        $result = $this->configService->saveToolsConfiguration($toolsConfig);

        $this->assertTrue($result);
        $this->assertFileExists($this->toolsPath);

        $savedContent = File::get($this->toolsPath);
        $savedTools = json_decode($savedContent, true);

        $this->assertEquals($toolsConfig, $savedTools);
    }

    #[Test]
    public function it_creates_default_configuration_file(): void
    {
        $this->assertFileDoesNotExist($this->configPath);

        $result = $this->configService->createDefaultConfiguration();

        $this->assertTrue($result);
        $this->assertFileExists($this->configPath);

        $config = $this->configService->loadConfiguration();
        $this->assertArrayHasKey('servers', $config);
        $this->assertArrayHasKey('global_config', $config);
    }

    #[Test]
    public function it_does_not_overwrite_existing_configuration(): void
    {
        $existingConfig = ['servers' => ['existing' => ['type' => 'external']]];
        File::put($this->configPath, json_encode($existingConfig));

        $result = $this->configService->createDefaultConfiguration();

        $this->assertFalse($result);

        $config = json_decode(File::get($this->configPath), true);
        $this->assertEquals($existingConfig, $config);
    }

    #[Test]
    public function it_adds_server_to_configuration(): void
    {
        $serverConfig = [
            'type' => 'external',
            'enabled' => true,
            'command' => 'npx new-server',
        ];

        Log::shouldReceive('info')->twice(); // Load and save

        $result = $this->configService->addServer('new-server', $serverConfig);

        $this->assertTrue($result);

        $config = $this->configService->loadConfiguration();
        $this->assertArrayHasKey('new-server', $config['servers']);
        $this->assertEquals($serverConfig, $config['servers']['new-server']);
    }

    #[Test]
    public function it_removes_server_from_configuration(): void
    {
        // First add a server
        $this->configService->addServer('test-server', [
            'type' => 'external',
            'enabled' => true,
            'command' => 'npx test',
        ]);

        Log::shouldReceive('info')->times(3); // Add, load, save

        $result = $this->configService->removeServer('test-server');

        $this->assertTrue($result);

        $config = $this->configService->loadConfiguration();
        $this->assertArrayNotHasKey('test-server', $config['servers']);
    }

    #[Test]
    public function it_returns_false_when_removing_non_existent_server(): void
    {
        $result = $this->configService->removeServer('non-existent');

        $this->assertFalse($result);
    }

    #[Test]
    public function it_updates_existing_server_configuration(): void
    {
        // First add a server
        $this->configService->addServer('test-server', [
            'type' => 'external',
            'enabled' => true,
            'command' => 'npx test',
        ]);

        $updates = [
            'enabled' => false,
            'timeout' => 60,
        ];

        Log::shouldReceive('info')->times(3); // Add, load, save

        $result = $this->configService->updateServer('test-server', $updates);

        $this->assertTrue($result);

        $config = $this->configService->loadConfiguration();
        $serverConfig = $config['servers']['test-server'];

        $this->assertFalse($serverConfig['enabled']);
        $this->assertEquals(60, $serverConfig['timeout']);
        $this->assertEquals('npx test', $serverConfig['command']); // Unchanged
    }

    #[Test]
    public function it_returns_configuration_file_paths(): void
    {
        $paths = $this->configService->getConfigurationPaths();

        $this->assertArrayHasKey('config', $paths);
        $this->assertArrayHasKey('tools', $paths);
        $this->assertEquals($this->configPath, $paths['config']);
        $this->assertEquals($this->toolsPath, $paths['tools']);
    }

    #[Test]
    public function it_checks_configuration_file_existence(): void
    {
        $existence = $this->configService->configurationExists();

        $this->assertArrayHasKey('config', $existence);
        $this->assertArrayHasKey('tools', $existence);
        $this->assertFalse($existence['config']);
        $this->assertFalse($existence['tools']);

        // Create files and check again
        File::put($this->configPath, '{}');
        File::put($this->toolsPath, '{}');

        $existence = $this->configService->configurationExists();
        $this->assertTrue($existence['config']);
        $this->assertTrue($existence['tools']);
    }
}
