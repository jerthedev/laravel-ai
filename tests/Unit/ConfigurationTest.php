<?php

namespace JTD\LaravelAI\Tests\Unit;

use JTD\LaravelAI\Exceptions\InvalidConfigurationException;
use JTD\LaravelAI\Services\ConfigurationValidator;
use JTD\LaravelAI\Tests\TestCase;

class ConfigurationTest extends TestCase
{
    protected ConfigurationValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new ConfigurationValidator;
    }

    /** @test */
    public function it_loads_default_configuration()
    {
        $config = config('ai');

        $this->assertIsArray($config);
        $this->assertArrayHasKey('default', $config);
        $this->assertArrayHasKey('providers', $config);
        $this->assertArrayHasKey('cost_tracking', $config);
        $this->assertArrayHasKey('model_sync', $config);
        $this->assertArrayHasKey('cache', $config);
        $this->assertArrayHasKey('rate_limiting', $config);
        $this->assertArrayHasKey('logging', $config);
        $this->assertArrayHasKey('mcp', $config);
    }

    /** @test */
    public function it_has_correct_default_provider()
    {
        $this->assertEquals('mock', config('ai.default'));
    }

    /** @test */
    public function it_has_required_providers_configured()
    {
        $providers = config('ai.providers');

        $this->assertIsArray($providers);
        $this->assertArrayHasKey('openai', $providers);
        $this->assertArrayHasKey('xai', $providers);
        $this->assertArrayHasKey('gemini', $providers);
        $this->assertArrayHasKey('ollama', $providers);
        $this->assertArrayHasKey('mock', $providers);
    }

    /** @test */
    public function it_validates_valid_configuration()
    {
        $config = config('ai');

        $this->assertTrue($this->validator->validate($config));
    }

    /** @test */
    public function it_throws_exception_for_missing_default_provider()
    {
        $config = config('ai');
        unset($config['default']);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Invalid AI configuration structure');

        $this->validator->validate($config);
    }

    /** @test */
    public function it_throws_exception_for_empty_providers()
    {
        $config = config('ai');
        $config['providers'] = [];

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Invalid AI configuration structure');

        $this->validator->validate($config);
    }

    /** @test */
    public function it_throws_exception_for_invalid_default_provider()
    {
        $config = config('ai');
        $config['default'] = 'nonexistent';

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage("Default provider 'nonexistent' is not configured");

        $this->validator->validate($config);
    }

    /** @test */
    public function it_validates_openai_provider_configuration()
    {
        $config = [
            'default' => 'openai',
            'providers' => [
                'openai' => [
                    'driver' => 'openai',
                    'api_key' => 'sk-test-key-123456789',
                    'base_url' => 'https://api.openai.com/v1',
                    'timeout' => 30,
                    'retry_attempts' => 3,
                ],
            ],
        ];

        $this->assertTrue($this->validator->validate($config));
    }

    /** @test */
    public function it_throws_exception_for_invalid_openai_api_key()
    {
        $config = [
            'default' => 'openai',
            'providers' => [
                'openai' => [
                    'driver' => 'openai',
                    'api_key' => 'short', // Too short
                    'base_url' => 'https://api.openai.com/v1',
                ],
            ],
        ];

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage("Invalid configuration for provider 'openai'");

        $this->validator->validate($config);
    }

    /** @test */
    public function it_validates_gemini_provider_configuration()
    {
        $config = [
            'default' => 'gemini',
            'providers' => [
                'gemini' => [
                    'driver' => 'gemini',
                    'api_key' => 'test-gemini-key-123',
                    'base_url' => 'https://generativelanguage.googleapis.com/v1',
                    'safety_settings' => [
                        'HARM_CATEGORY_HARASSMENT' => 'BLOCK_MEDIUM_AND_ABOVE',
                    ],
                ],
            ],
        ];

        $this->assertTrue($this->validator->validate($config));
    }

    /** @test */
    public function it_validates_ollama_provider_configuration()
    {
        $config = [
            'default' => 'ollama',
            'providers' => [
                'ollama' => [
                    'driver' => 'ollama',
                    'base_url' => 'http://localhost:11434',
                    'keep_alive' => '5m',
                    'num_ctx' => 2048,
                ],
            ],
        ];

        $this->assertTrue($this->validator->validate($config));
    }

    /** @test */
    public function it_throws_exception_for_unknown_driver()
    {
        $config = [
            'default' => 'unknown',
            'providers' => [
                'unknown' => [
                    'driver' => 'unknown-driver',
                ],
            ],
        ];

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage("Unknown driver 'unknown-driver'");

        $this->validator->validate($config);
    }

    /** @test */
    public function it_validates_cost_tracking_configuration()
    {
        $config = [
            'default' => 'mock',
            'providers' => ['mock' => ['driver' => 'mock']],
            'cost_tracking' => [
                'enabled' => true,
                'currency' => 'USD',
                'precision' => 6,
                'batch_size' => 100,
                'auto_calculate' => true,
            ],
        ];

        $this->assertTrue($this->validator->validate($config));
    }

    /** @test */
    public function it_throws_exception_for_invalid_currency()
    {
        $config = [
            'default' => 'mock',
            'providers' => ['mock' => ['driver' => 'mock']],
            'cost_tracking' => [
                'currency' => 'INVALID',
            ],
        ];

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Invalid cost tracking configuration');

        $this->validator->validate($config);
    }

    /** @test */
    public function it_validates_model_sync_configuration()
    {
        $config = [
            'default' => 'mock',
            'providers' => ['mock' => ['driver' => 'mock']],
            'model_sync' => [
                'enabled' => true,
                'frequency' => 'hourly',
                'auto_sync' => true,
                'batch_size' => 50,
                'timeout' => 60,
            ],
        ];

        $this->assertTrue($this->validator->validate($config));
    }

    /** @test */
    public function it_throws_exception_for_invalid_sync_frequency()
    {
        $config = [
            'default' => 'mock',
            'providers' => ['mock' => ['driver' => 'mock']],
            'model_sync' => [
                'frequency' => 'invalid',
            ],
        ];

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Invalid model sync configuration');

        $this->validator->validate($config);
    }

    /** @test */
    public function it_validates_cache_configuration()
    {
        $config = [
            'default' => 'mock',
            'providers' => ['mock' => ['driver' => 'mock']],
            'cache' => [
                'enabled' => true,
                'store' => 'redis',
                'prefix' => 'ai:',
                'ttl' => [
                    'models' => 3600,
                    'costs' => 86400,
                    'responses' => 300,
                ],
            ],
        ];

        $this->assertTrue($this->validator->validate($config));
    }

    /** @test */
    public function it_validates_logging_configuration()
    {
        $config = [
            'default' => 'mock',
            'providers' => ['mock' => ['driver' => 'mock']],
            'logging' => [
                'enabled' => true,
                'channel' => 'ai',
                'level' => 'info',
                'log_requests' => true,
                'log_responses' => false,
                'log_costs' => true,
                'log_errors' => true,
            ],
        ];

        $this->assertTrue($this->validator->validate($config));
    }

    /** @test */
    public function it_throws_exception_for_invalid_log_level()
    {
        $config = [
            'default' => 'mock',
            'providers' => ['mock' => ['driver' => 'mock']],
            'logging' => [
                'level' => 'invalid',
            ],
        ];

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Invalid logging configuration');

        $this->validator->validate($config);
    }

    /** @test */
    public function it_validates_mcp_configuration()
    {
        $config = [
            'default' => 'mock',
            'providers' => ['mock' => ['driver' => 'mock']],
            'mcp' => [
                'enabled' => true,
                'servers' => [
                    'sequential-thinking' => [
                        'enabled' => true,
                        'max_thoughts' => 10,
                        'timeout' => 30,
                    ],
                ],
            ],
        ];

        $this->assertTrue($this->validator->validate($config));
    }

    /** @test */
    public function it_can_access_nested_configuration_values()
    {
        // These are overridden to false in test environment
        $this->assertEquals(false, config('ai.cost_tracking.enabled'));
        $this->assertEquals(false, config('ai.model_sync.enabled'));
        $this->assertEquals(false, config('ai.cache.enabled'));

        // These should still have their default values
        $this->assertEquals('USD', config('ai.cost_tracking.currency'));
        $this->assertEquals(6, config('ai.cost_tracking.precision'));
        $this->assertEquals('hourly', config('ai.model_sync.frequency'));
        $this->assertEquals('redis', config('ai.cache.store'));
        $this->assertEquals('ai:', config('ai.cache.prefix'));
    }

    /** @test */
    public function it_has_proper_environment_variable_defaults()
    {
        // Test that environment variables are properly referenced
        $configFile = file_get_contents(__DIR__ . '/../../config/ai.php');

        $this->assertStringContainsString("env('AI_DEFAULT_PROVIDER'", $configFile);
        $this->assertStringContainsString("env('AI_OPENAI_API_KEY'", $configFile);
        $this->assertStringContainsString("env('AI_XAI_API_KEY'", $configFile);
        $this->assertStringContainsString("env('AI_GEMINI_API_KEY'", $configFile);
        $this->assertStringContainsString("env('AI_COST_TRACKING_ENABLED'", $configFile);
        $this->assertStringContainsString("env('AI_MODEL_SYNC_ENABLED'", $configFile);
        $this->assertStringContainsString("env('AI_CACHE_ENABLED'", $configFile);
    }
}
