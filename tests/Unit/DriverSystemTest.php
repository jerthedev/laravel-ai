<?php

namespace JTD\LaravelAI\Tests\Unit;

use JTD\LaravelAI\Contracts\AIProviderInterface;
use JTD\LaravelAI\Exceptions\InvalidConfigurationException;
use JTD\LaravelAI\Exceptions\ProviderNotFoundException;
use JTD\LaravelAI\Providers\MockProvider;
use JTD\LaravelAI\Services\ConfigurationValidator;
use JTD\LaravelAI\Services\DriverManager;
use JTD\LaravelAI\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class DriverSystemTest extends TestCase
{
    protected DriverManager $driverManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->driverManager = new DriverManager($this->app);
    }

    #[Test]
    public function it_can_create_driver_manager()
    {
        $this->assertInstanceOf(DriverManager::class, $this->driverManager);
    }

    #[Test]
    public function it_can_get_default_driver()
    {
        $driver = $this->driverManager->driver();

        $this->assertInstanceOf(AIProviderInterface::class, $driver);
        $this->assertInstanceOf(MockProvider::class, $driver);
    }

    #[Test]
    public function it_can_get_specific_driver()
    {
        $driver = $this->driverManager->driver('mock');

        $this->assertInstanceOf(MockProvider::class, $driver);
    }

    #[Test]
    public function it_throws_exception_for_unknown_driver()
    {
        $this->expectException(ProviderNotFoundException::class);
        $this->expectExceptionMessage('Provider [unknown] is not configured');

        $this->driverManager->driver('unknown');
    }

    #[Test]
    public function it_can_extend_with_custom_driver()
    {
        $this->driverManager->extend('custom', function ($app, $config) {
            return new MockProvider($config);
        });

        // Set up configuration for the custom provider
        config(['ai.providers.custom' => ['driver' => 'custom']]);

        $driver = $this->driverManager->driver('custom');

        $this->assertInstanceOf(MockProvider::class, $driver);
    }

    #[Test]
    public function it_caches_driver_instances()
    {
        $driver1 = $this->driverManager->driver('mock');
        $driver2 = $this->driverManager->driver('mock');

        $this->assertSame($driver1, $driver2);
    }

    #[Test]
    public function it_can_refresh_driver_instances()
    {
        $driver1 = $this->driverManager->driver('mock');

        $this->driverManager->refreshDriver('mock');

        $driver2 = $this->driverManager->driver('mock');

        $this->assertNotSame($driver1, $driver2);
    }

    #[Test]
    public function it_can_refresh_all_drivers()
    {
        $driver1 = $this->driverManager->driver('mock');

        $this->driverManager->refreshAllDrivers();

        $driver2 = $this->driverManager->driver('mock');

        $this->assertNotSame($driver1, $driver2);
    }

    #[Test]
    public function it_gets_available_providers()
    {
        $providers = $this->driverManager->getAvailableProviders();

        $this->assertIsArray($providers);
        $this->assertContains('mock', $providers);
    }

    #[Test]
    public function it_gets_provider_registry()
    {
        $registry = $this->driverManager->getProviderRegistry();

        $this->assertIsArray($registry);
        $this->assertArrayHasKey('mock', $registry);
        $this->assertArrayHasKey('openai', $registry);
        $this->assertArrayHasKey('xai', $registry);
        $this->assertArrayHasKey('gemini', $registry);
        $this->assertArrayHasKey('ollama', $registry);
    }

    #[Test]
    public function it_can_register_provider()
    {
        $this->driverManager->registerProvider('test-provider', [
            'description' => 'Test provider',
            'supports_streaming' => true,
        ]);

        $registry = $this->driverManager->getProviderRegistry();

        $this->assertArrayHasKey('test-provider', $registry);
        $this->assertEquals('Test provider', $registry['test-provider']['description']);
        $this->assertTrue($registry['test-provider']['supports_streaming']);
    }

    #[Test]
    public function it_can_check_if_provider_exists()
    {
        $this->assertTrue($this->driverManager->hasProvider('mock'));
        $this->assertFalse($this->driverManager->hasProvider('nonexistent'));
    }

    #[Test]
    public function it_can_get_provider_info()
    {
        $info = $this->driverManager->getProviderInfo('mock');

        $this->assertIsArray($info);
        $this->assertEquals('mock', $info['name']);
        $this->assertArrayHasKey('description', $info);
        $this->assertArrayHasKey('supports_streaming', $info);
    }

    #[Test]
    public function it_throws_exception_for_unknown_provider_info()
    {
        $this->expectException(ProviderNotFoundException::class);

        $this->driverManager->getProviderInfo('unknown');
    }

    #[Test]
    public function it_can_validate_provider()
    {
        $result = $this->driverManager->validateProvider('mock');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('status', $result);
        $this->assertEquals('valid', $result['status']);
    }

    #[Test]
    public function it_can_get_provider_health()
    {
        $health = $this->driverManager->getProviderHealth('mock');

        $this->assertIsArray($health);
        $this->assertArrayHasKey('status', $health);
        $this->assertEquals('healthy', $health['status']);
    }

    #[Test]
    public function it_handles_provider_validation_errors()
    {
        // Test with a provider that doesn't exist
        $result = $this->driverManager->validateProvider('nonexistent');

        $this->assertIsArray($result);
        $this->assertEquals('invalid', $result['status']);
        $this->assertArrayHasKey('message', $result);
    }

    #[Test]
    public function configuration_validator_can_validate_valid_config()
    {
        $validator = new ConfigurationValidator;

        $config = [
            'default' => 'mock',
            'providers' => [
                'mock' => [
                    'driver' => 'mock',
                ],
            ],
        ];

        $result = $validator->validate($config);

        $this->assertTrue($result);
    }

    #[Test]
    public function configuration_validator_throws_exception_for_invalid_config()
    {
        $validator = new ConfigurationValidator;

        $this->expectException(InvalidConfigurationException::class);

        $validator->validate([
            'default' => 'nonexistent',
            'providers' => [],
        ]);
    }

    #[Test]
    public function configuration_validator_validates_provider_credentials()
    {
        $validator = new ConfigurationValidator;

        // Test invalid API key format
        $this->expectException(InvalidConfigurationException::class);

        $validator->validate([
            'default' => 'openai',
            'providers' => [
                'openai' => [
                    'driver' => 'openai',
                    'api_key' => 'short', // Too short
                ],
            ],
        ]);
    }

    #[Test]
    public function configuration_validator_validates_cost_tracking_config()
    {
        $validator = new ConfigurationValidator;

        $this->expectException(InvalidConfigurationException::class);

        $validator->validate([
            'default' => 'mock',
            'providers' => [
                'mock' => ['driver' => 'mock'],
            ],
            'cost_tracking' => [
                'enabled' => 'yes', // Should be boolean
            ],
        ]);
    }

    #[Test]
    public function driver_manager_registers_built_in_providers()
    {
        $registry = $this->driverManager->getProviderRegistry();

        $expectedProviders = ['mock', 'openai', 'xai', 'gemini', 'ollama'];

        foreach ($expectedProviders as $provider) {
            $this->assertArrayHasKey($provider, $registry);
            $this->assertArrayHasKey('description', $registry[$provider]);
            $this->assertArrayHasKey('supports_streaming', $registry[$provider]);
        }
    }

    #[Test]
    public function driver_manager_handles_unimplemented_drivers()
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('OpenAI driver not yet implemented');

        // Set up OpenAI configuration
        config(['ai.providers.openai' => ['driver' => 'openai', 'api_key' => 'test']]);

        $this->driverManager->driver('openai');
    }
}
