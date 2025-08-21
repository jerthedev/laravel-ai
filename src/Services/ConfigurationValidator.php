<?php

namespace JTD\LaravelAI\Services;

use Illuminate\Support\Facades\Validator;
use JTD\LaravelAI\Exceptions\InvalidConfigurationException;

class ConfigurationValidator
{
    /**
     * Validate the AI configuration.
     *
     * @throws InvalidConfigurationException
     */
    public function validate(array $config): bool
    {
        $this->validateStructure($config);
        $this->validateProviders($config['providers'] ?? []);
        $this->validateDefaultProvider($config);
        $this->validateCostTracking($config['cost_tracking'] ?? []);
        $this->validateModelSync($config['model_sync'] ?? []);
        $this->validateCache($config['cache'] ?? []);
        $this->validateRateLimit($config['rate_limiting'] ?? []);
        $this->validateLogging($config['logging'] ?? []);
        $this->validateMcp($config['mcp'] ?? []);

        return true;
    }

    /**
     * Validate the basic configuration structure.
     *
     * @throws InvalidConfigurationException
     */
    protected function validateStructure(array $config): void
    {
        $validator = Validator::make($config, [
            'default' => 'required|string',
            'providers' => 'required|array|min:1',
            'cost_tracking' => 'array',
            'model_sync' => 'array',
            'cache' => 'array',
            'rate_limiting' => 'array',
            'logging' => 'array',
            'mcp' => 'array',
            'timeout' => 'nullable|integer|min:1|max:300',
            'retry_attempts' => 'nullable|integer|min:0|max:10',
            'debug' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            throw new InvalidConfigurationException(
                'Invalid AI configuration structure: ' . $validator->errors()->first()
            );
        }
    }

    /**
     * Validate provider configurations.
     *
     * @throws InvalidConfigurationException
     */
    protected function validateProviders(array $providers): void
    {
        foreach ($providers as $name => $config) {
            $this->validateProvider($name, $config);
        }
    }

    /**
     * Validate a single provider configuration.
     *
     * @throws InvalidConfigurationException
     */
    protected function validateProvider(string $name, array $config): void
    {
        $rules = [
            'driver' => 'required|string',
            'timeout' => 'integer|min:1|max:300',
            'retry_attempts' => 'integer|min:0|max:10',
            'retry_delay' => 'integer|min:0|max:10000',
        ];

        // Add driver-specific validation rules
        switch ($config['driver'] ?? null) {
            case 'openai':
                // Only require API key if it's not null (allows for environment variable setup)
                if (isset($config['api_key']) && $config['api_key'] !== null) {
                    $rules['api_key'] = 'string|min:10';
                }
                $rules['base_url'] = 'nullable|url';
                $rules['organization'] = 'nullable|string';
                $rules['project'] = 'nullable|string';
                break;

            case 'xai':
                if (isset($config['api_key']) && $config['api_key'] !== null) {
                    $rules['api_key'] = 'string|min:10';
                }
                $rules['base_url'] = 'nullable|url';
                break;

            case 'gemini':
                if (isset($config['api_key']) && $config['api_key'] !== null) {
                    $rules['api_key'] = 'string|min:10';
                }
                $rules['base_url'] = 'nullable|url';
                $rules['safety_settings'] = 'array';
                break;

            case 'ollama':
                $rules['base_url'] = 'required|url';
                $rules['keep_alive'] = 'nullable|string';
                $rules['num_ctx'] = 'nullable|integer|min:1';
                break;

            case 'mock':
                $rules['valid_credentials'] = 'boolean';
                $rules['mock_responses'] = 'array';
                break;

            default:
                throw new InvalidConfigurationException(
                    "Unknown driver '{$config['driver']}' for provider '{$name}'"
                );
        }

        $validator = Validator::make($config, $rules);

        if ($validator->fails()) {
            throw new InvalidConfigurationException(
                "Invalid configuration for provider '{$name}': " . $validator->errors()->first()
            );
        }
    }

    /**
     * Validate that the default provider exists.
     *
     * @throws InvalidConfigurationException
     */
    protected function validateDefaultProvider(array $config): void
    {
        $defaultProvider = $config['default'] ?? null;
        $providers = $config['providers'] ?? [];

        if (! isset($providers[$defaultProvider])) {
            throw new InvalidConfigurationException(
                "Default provider '{$defaultProvider}' is not configured in providers array"
            );
        }
    }

    /**
     * Validate cost tracking configuration.
     *
     * @throws InvalidConfigurationException
     */
    protected function validateCostTracking(array $config): void
    {
        $validator = Validator::make($config, [
            'enabled' => 'boolean',
            'currency' => 'string|size:3',
            'precision' => 'integer|min:0|max:10',
            'batch_size' => 'integer|min:1|max:1000',
            'auto_calculate' => 'boolean',
        ]);

        if ($validator->fails()) {
            throw new InvalidConfigurationException(
                'Invalid cost tracking configuration: ' . $validator->errors()->first()
            );
        }
    }

    /**
     * Validate model sync configuration.
     *
     * @throws InvalidConfigurationException
     */
    protected function validateModelSync(array $config): void
    {
        $validator = Validator::make($config, [
            'enabled' => 'boolean',
            'frequency' => 'in:hourly,daily,weekly',
            'auto_sync' => 'boolean',
            'batch_size' => 'integer|min:1|max:100',
            'timeout' => 'integer|min:1|max:300',
        ]);

        if ($validator->fails()) {
            throw new InvalidConfigurationException(
                'Invalid model sync configuration: ' . $validator->errors()->first()
            );
        }
    }

    /**
     * Validate cache configuration.
     *
     * @throws InvalidConfigurationException
     */
    protected function validateCache(array $config): void
    {
        $validator = Validator::make($config, [
            'enabled' => 'boolean',
            'store' => 'string',
            'prefix' => 'string',
            'ttl' => 'array',
            'ttl.models' => 'integer|min:0',
            'ttl.costs' => 'integer|min:0',
            'ttl.responses' => 'integer|min:0',
        ]);

        if ($validator->fails()) {
            throw new InvalidConfigurationException(
                'Invalid cache configuration: ' . $validator->errors()->first()
            );
        }
    }

    /**
     * Validate rate limiting configuration.
     *
     * @throws InvalidConfigurationException
     */
    protected function validateRateLimit(array $config): void
    {
        $validator = Validator::make($config, [
            'enabled' => 'boolean',
            'global' => 'array',
            'global.requests_per_minute' => 'integer|min:1',
            'global.requests_per_hour' => 'integer|min:1',
            'per_provider' => 'array',
            'per_user' => 'array',
            'per_user.requests_per_minute' => 'integer|min:1',
            'per_user.requests_per_hour' => 'integer|min:1',
        ]);

        if ($validator->fails()) {
            throw new InvalidConfigurationException(
                'Invalid rate limiting configuration: ' . $validator->errors()->first()
            );
        }
    }

    /**
     * Validate logging configuration.
     *
     * @throws InvalidConfigurationException
     */
    protected function validateLogging(array $config): void
    {
        $validator = Validator::make($config, [
            'enabled' => 'boolean',
            'channel' => 'string',
            'level' => 'in:emergency,alert,critical,error,warning,notice,info,debug',
            'log_requests' => 'boolean',
            'log_responses' => 'boolean',
            'log_costs' => 'boolean',
            'log_errors' => 'boolean',
        ]);

        if ($validator->fails()) {
            throw new InvalidConfigurationException(
                'Invalid logging configuration: ' . $validator->errors()->first()
            );
        }
    }

    /**
     * Validate MCP configuration.
     *
     * @throws InvalidConfigurationException
     */
    protected function validateMcp(array $config): void
    {
        $validator = Validator::make($config, [
            'enabled' => 'boolean',
            'servers' => 'array',
        ]);

        if ($validator->fails()) {
            throw new InvalidConfigurationException(
                'Invalid MCP configuration: ' . $validator->errors()->first()
            );
        }

        // Validate each MCP server configuration
        foreach ($config['servers'] ?? [] as $name => $serverConfig) {
            $serverValidator = Validator::make($serverConfig, [
                'enabled' => 'boolean',
                'timeout' => 'integer|min:1|max:300',
                'endpoint' => 'nullable|url',
                'max_thoughts' => 'nullable|integer|min:1|max:50',
            ]);

            if ($serverValidator->fails()) {
                throw new InvalidConfigurationException(
                    "Invalid MCP server '{$name}' configuration: " . $serverValidator->errors()->first()
                );
            }
        }
    }
}
