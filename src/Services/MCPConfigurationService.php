<?php

namespace JTD\LaravelAI\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use JTD\LaravelAI\Exceptions\MCPException;

/**
 * MCP Configuration Service
 *
 * Handles loading, validation, and management of MCP configuration files.
 * Manages both .mcp.json (server configuration) and .mcp.tools.json (tool discovery).
 */
class MCPConfigurationService
{
    /**
     * Configuration file path.
     */
    protected string $configPath;

    /**
     * Tools file path.
     */
    protected string $toolsPath;

    /**
     * Default configuration structure.
     */
    protected array $defaultConfig = [
        'servers' => [],
        'global_config' => [
            'timeout' => 30,
            'max_concurrent' => 3,
            'retry_attempts' => 2,
        ],
    ];

    /**
     * Configuration validation rules.
     */
    protected array $validationRules = [
        'servers' => 'required|array',
        'servers.*.type' => 'required|string|in:external',
        'servers.*.enabled' => 'required|boolean',
        'servers.*.command' => 'required_if:servers.*.type,external|string',
        'servers.*.args' => 'sometimes|array',
        'servers.*.env' => 'sometimes|array',
        'servers.*.config' => 'sometimes|array',
        'servers.*.timeout' => 'sometimes|integer|min:1|max:300',
        'global_config' => 'sometimes|array',
        'global_config.timeout' => 'sometimes|integer|min:1|max:300',
        'global_config.max_concurrent' => 'sometimes|integer|min:1|max:10',
        'global_config.retry_attempts' => 'sometimes|integer|min:0|max:5',
    ];

    /**
     * Create a new MCP configuration service instance.
     */
    public function __construct()
    {
        $this->configPath = base_path('.mcp.json');
        $this->toolsPath = base_path('.mcp.tools.json');
    }

    /**
     * Load configuration from file.
     */
    public function loadConfiguration(): array
    {
        if (!File::exists($this->configPath)) {
            return $this->defaultConfig;
        }

        try {
            $content = File::get($this->configPath);
            $config = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
            
            // Merge with defaults to ensure all required keys exist
            return array_merge_recursive($this->defaultConfig, $config);
        } catch (\JsonException $e) {
            Log::error('Failed to parse MCP configuration', [
                'file' => $this->configPath,
                'error' => $e->getMessage(),
            ]);
            
            throw new MCPException("Invalid MCP configuration file: {$e->getMessage()}");
        }
    }

    /**
     * Save configuration to file.
     */
    public function saveConfiguration(array $config): bool
    {
        try {
            $validation = $this->validateConfiguration($config);
            
            if (!empty($validation['errors'])) {
                throw new MCPException('Configuration validation failed: ' . implode(', ', $validation['errors']));
            }

            $json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            File::put($this->configPath, $json);
            
            Log::info('MCP configuration saved', ['file' => $this->configPath]);
            
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to save MCP configuration', [
                'file' => $this->configPath,
                'error' => $e->getMessage(),
            ]);
            
            return false;
        }
    }

    /**
     * Validate configuration structure and values.
     */
    public function validateConfiguration(array $config): array
    {
        $errors = [];
        $warnings = [];

        // Use Laravel validator for basic structure validation
        $validator = Validator::make($config, $this->validationRules);
        
        if ($validator->fails()) {
            $errors = array_merge($errors, $validator->errors()->all());
        }

        // Additional custom validation
        if (isset($config['servers'])) {
            foreach ($config['servers'] as $name => $serverConfig) {
                $serverErrors = $this->validateServerConfiguration($name, $serverConfig);
                $errors = array_merge($errors, $serverErrors['errors']);
                $warnings = array_merge($warnings, $serverErrors['warnings']);
            }
        }

        return [
            'errors' => $errors,
            'warnings' => $warnings,
            'valid' => empty($errors),
        ];
    }

    /**
     * Validate individual server configuration.
     */
    protected function validateServerConfiguration(string $name, array $config): array
    {
        $errors = [];
        $warnings = [];

        // Validate server name
        if (!preg_match('/^[a-z0-9\-_]+$/', $name)) {
            $errors[] = "Server name '{$name}' must contain only lowercase letters, numbers, hyphens, and underscores";
        }

        // Validate external server specifics
        if (($config['type'] ?? '') === 'external') {
            // Check command exists (basic validation)
            $command = $config['command'] ?? '';
            if (empty($command)) {
                $errors[] = "External server '{$name}' requires a command";
            } elseif (!str_contains($command, 'npx') && !str_contains($command, 'node')) {
                $warnings[] = "Server '{$name}' command doesn't appear to be a Node.js command";
            }

            // Validate environment variables
            if (isset($config['env'])) {
                foreach ($config['env'] as $key => $value) {
                    if (str_starts_with($value, '${') && str_ends_with($value, '}')) {
                        $envVar = substr($value, 2, -1);
                        if (empty(env($envVar))) {
                            $warnings[] = "Environment variable '{$envVar}' for server '{$name}' is not set";
                        }
                    }
                }
            }

            // Validate timeout
            $timeout = $config['timeout'] ?? $config['global_config']['timeout'] ?? 30;
            if ($timeout > 60) {
                $warnings[] = "Server '{$name}' timeout ({$timeout}s) is quite high, consider reducing it";
            }
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * Load tools configuration from file.
     */
    public function loadToolsConfiguration(): array
    {
        if (!File::exists($this->toolsPath)) {
            return [];
        }

        try {
            $content = File::get($this->toolsPath);
            return json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            Log::error('Failed to parse MCP tools configuration', [
                'file' => $this->toolsPath,
                'error' => $e->getMessage(),
            ]);
            
            return [];
        }
    }

    /**
     * Save tools configuration to file.
     */
    public function saveToolsConfiguration(array $tools): bool
    {
        try {
            $json = json_encode($tools, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            File::put($this->toolsPath, $json);
            
            Log::info('MCP tools configuration saved', ['file' => $this->toolsPath]);
            
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to save MCP tools configuration', [
                'file' => $this->toolsPath,
                'error' => $e->getMessage(),
            ]);
            
            return false;
        }
    }

    /**
     * Create a default configuration file.
     */
    public function createDefaultConfiguration(): bool
    {
        if (File::exists($this->configPath)) {
            return false; // Don't overwrite existing configuration
        }

        return $this->saveConfiguration($this->defaultConfig);
    }

    /**
     * Add a server to the configuration.
     */
    public function addServer(string $name, array $serverConfig): bool
    {
        $config = $this->loadConfiguration();
        $config['servers'][$name] = $serverConfig;
        
        return $this->saveConfiguration($config);
    }

    /**
     * Remove a server from the configuration.
     */
    public function removeServer(string $name): bool
    {
        $config = $this->loadConfiguration();
        
        if (!isset($config['servers'][$name])) {
            return false;
        }
        
        unset($config['servers'][$name]);
        
        return $this->saveConfiguration($config);
    }

    /**
     * Update a server configuration.
     */
    public function updateServer(string $name, array $serverConfig): bool
    {
        $config = $this->loadConfiguration();
        
        if (!isset($config['servers'][$name])) {
            return false;
        }
        
        $config['servers'][$name] = array_merge($config['servers'][$name], $serverConfig);
        
        return $this->saveConfiguration($config);
    }

    /**
     * Get configuration file paths.
     */
    public function getConfigurationPaths(): array
    {
        return [
            'config' => $this->configPath,
            'tools' => $this->toolsPath,
        ];
    }

    /**
     * Check if configuration files exist.
     */
    public function configurationExists(): array
    {
        return [
            'config' => File::exists($this->configPath),
            'tools' => File::exists($this->toolsPath),
        ];
    }
}
