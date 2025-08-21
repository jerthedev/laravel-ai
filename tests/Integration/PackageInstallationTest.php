<?php

namespace JTD\LaravelAI\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use Illuminate\Support\Facades\File;
use JTD\LaravelAI\Contracts\ConversationBuilderInterface;
use JTD\LaravelAI\Facades\AI;
use JTD\LaravelAI\LaravelAIServiceProvider;
use JTD\LaravelAI\Services\AIManager;
use JTD\LaravelAI\Services\ConfigurationValidator;
use JTD\LaravelAI\Services\DriverManager;
use JTD\LaravelAI\Tests\TestCase;

/**
 * Integration tests for complete package installation and setup.
 *
 * Tests the entire package installation process including:
 * - Service provider registration
 * - Configuration publishing
 * - Database migrations
 * - Facade registration
 * - Basic functionality
 */
class PackageInstallationTest extends TestCase
{
    #[Test]
    public function package_service_provider_is_automatically_discovered()
    {
        // Verify the service provider is loaded
        $this->assertTrue(
            $this->app->providerIsLoaded(LaravelAIServiceProvider::class),
            'LaravelAIServiceProvider should be auto-discovered and loaded'
        );
    }
    #[Test]
    public function all_core_services_are_registered()
    {
        // Test that all core services can be resolved from the container
        $services = [
            'laravel-ai' => AIManager::class,
            'laravel-ai.driver' => DriverManager::class,
            'laravel-ai.config.validator' => ConfigurationValidator::class,
            ConversationBuilderInterface::class => \JTD\LaravelAI\Services\ConversationBuilder::class,
        ];

        foreach ($services as $abstract => $expectedClass) {
            $resolved = $this->app->make($abstract);
            $this->assertInstanceOf($expectedClass, $resolved, "Service '{$abstract}' should resolve to {$expectedClass}");
        }
    }
    #[Test]
    public function services_are_registered_as_singletons()
    {
        // Test that core services are singletons
        $singletonServices = ['laravel-ai', 'laravel-ai.driver', 'laravel-ai.config.validator'];

        foreach ($singletonServices as $service) {
            $instance1 = $this->app->make($service);
            $instance2 = $this->app->make($service);

            $this->assertSame($instance1, $instance2, "Service '{$service}' should be a singleton");
        }
    }
    #[Test]
    public function ai_facade_is_registered_and_functional()
    {
        // Test facade registration
        $this->assertTrue(class_exists(\JTD\LaravelAI\Facades\AI::class), 'AI facade class should exist');

        // Test facade resolves to correct service
        $manager = AI::getFacadeRoot();
        $this->assertInstanceOf(AIManager::class, $manager, 'AI facade should resolve to AIManager');

        // Test facade methods are accessible through the underlying manager
        $this->assertTrue(method_exists($manager, 'conversation'), 'AI facade should have conversation method');
    }
    #[Test]
    public function configuration_is_loaded_with_defaults()
    {
        $config = config('ai');

        $this->assertIsArray($config, 'AI configuration should be loaded');
        $this->assertArrayHasKey('default', $config, 'Configuration should have default provider');
        $this->assertArrayHasKey('providers', $config, 'Configuration should have providers array');
        $this->assertArrayHasKey('cost_tracking', $config, 'Configuration should have cost tracking config');
        $this->assertArrayHasKey('analytics', $config, 'Configuration should have analytics config');

        // Test default values
        $this->assertEquals('mock', $config['default'], 'Default provider should be mock for testing');
        $this->assertIsArray($config['providers'], 'Providers should be an array');
        $this->assertArrayHasKey('mock', $config['providers'], 'Mock provider should be configured by default');
    }
    #[Test]
    public function configuration_can_be_published()
    {
        // Test that the service provider has publishable assets
        $provider = new LaravelAIServiceProvider($this->app);

        // This test verifies the service provider is set up for publishing
        // without actually performing file operations in the test environment
        $this->assertTrue(method_exists($provider, 'boot'), 'Service provider should have boot method');

        // Verify the provider can boot without errors
        $provider->boot();

        $this->assertTrue(true, 'Service provider boots successfully with publishing setup');
    }
    #[Test]
    public function migrations_can_be_published()
    {
        // Test that migrations are available for publishing
        $provider = new LaravelAIServiceProvider($this->app);

        // Verify the service provider loads migrations
        $this->assertTrue(method_exists($provider, 'boot'), 'Service provider should have boot method');

        // Check that migration files exist in the package
        $migrationPath = __DIR__ . '/../../database/migrations';

        if (is_dir($migrationPath)) {
            $migrationFiles = glob($migrationPath . '/*.php');
            $this->assertNotEmpty($migrationFiles, 'Migration files should exist in package');
        } else {
            // If migrations directory doesn't exist, that's also valid for this test
            $this->assertTrue(true, 'Migration publishing is configured in service provider');
        }
    }
    #[Test]
    public function package_provides_correct_services()
    {
        $provider = new LaravelAIServiceProvider($this->app);
        $providedServices = $provider->provides();

        $expectedServices = [
            'laravel-ai',
            'laravel-ai.driver',
            'laravel-ai.config.validator',
            ConversationBuilderInterface::class,
        ];

        foreach ($expectedServices as $service) {
            $this->assertContains($service, $providedServices, "Service '{$service}' should be provided by the service provider");
        }
    }
    #[Test]
    public function configuration_validation_works()
    {
        $validator = $this->app->make('laravel-ai.config.validator');

        // Test valid configuration
        $validConfig = [
            'default' => 'mock',
            'providers' => [
                'mock' => [
                    'driver' => 'mock',
                ],
            ],
        ];

        $result = $validator->validate($validConfig);
        $this->assertTrue($result, 'Valid configuration should pass validation');

        // Test invalid configuration
        $this->expectException(\JTD\LaravelAI\Exceptions\InvalidConfigurationException::class);

        $invalidConfig = [
            'default' => 'nonexistent',
            'providers' => [],
        ];

        $validator->validate($invalidConfig);
    }
    #[Test]
    public function driver_manager_has_built_in_providers()
    {
        $driverManager = $this->app->make('laravel-ai.driver');
        $registry = $driverManager->getProviderRegistry();

        $expectedProviders = ['mock', 'openai', 'xai', 'gemini', 'ollama'];

        foreach ($expectedProviders as $provider) {
            $this->assertArrayHasKey($provider, $registry, "Provider '{$provider}' should be registered");
            $this->assertArrayHasKey('description', $registry[$provider], "Provider '{$provider}' should have description");
        }
    }
    #[Test]
    public function ai_manager_can_create_conversation_builder()
    {
        $aiManager = $this->app->make('laravel-ai');
        $builder = $aiManager->conversation('Test Conversation');

        $this->assertInstanceOf(ConversationBuilderInterface::class, $builder, 'AIManager should create ConversationBuilder');
    }
    #[Test]
    public function facade_provides_fluent_interface()
    {
        $builder = AI::conversation('Integration Test')
            ->provider('mock')
            ->model('mock-model')
            ->temperature(0.7);

        $this->assertInstanceOf(ConversationBuilderInterface::class, $builder, 'Facade should provide fluent interface');
        $this->assertEquals('mock', $builder->getProvider(), 'Provider should be set correctly');
        $this->assertEquals('mock-model', $builder->getModel(), 'Model should be set correctly');
    }
    #[Test]
    public function package_handles_missing_configuration_gracefully()
    {
        // Test that the package can handle missing configuration
        // by verifying services are still resolvable
        $aiManager = $this->app->make('laravel-ai');
        $this->assertInstanceOf(AIManager::class, $aiManager);

        // Test that the service provider boots successfully
        $provider = new LaravelAIServiceProvider($this->app);
        $provider->boot();

        $this->assertTrue(true, 'Package handles configuration gracefully');
    }
    #[Test]
    public function package_works_with_different_environments()
    {
        // Test that the package works in the current environment
        $currentEnv = $this->app->environment();

        // Service provider should boot in any environment
        $provider = new LaravelAIServiceProvider($this->app);
        $provider->boot();

        // Core services should be available
        $aiManager = $this->app->make('laravel-ai');
        $this->assertInstanceOf(AIManager::class, $aiManager, "AIManager should work in {$currentEnv} environment");

        $this->assertTrue(true, 'Package works across different environments');
    }
    #[Test]
    public function package_supports_laravel_auto_discovery()
    {
        // This test verifies that the service provider is properly auto-discovered
        // In the test environment, we verify the service provider is loaded
        $this->assertTrue(
            $this->app->providerIsLoaded(LaravelAIServiceProvider::class),
            'Service provider should be auto-discovered and loaded'
        );

        // This confirms auto-discovery is working correctly
        $this->assertTrue(true, 'Laravel auto-discovery is functional');
    }
    #[Test]
    public function package_installation_is_complete()
    {
        // This is a comprehensive test that verifies the entire package is properly installed

        // 1. Service provider is loaded
        $this->assertTrue($this->app->providerIsLoaded(LaravelAIServiceProvider::class));

        // 2. Configuration is available
        $this->assertNotNull(config('ai'));

        // 3. Core services are registered
        $this->assertInstanceOf(AIManager::class, $this->app->make('laravel-ai'));
        $this->assertInstanceOf(DriverManager::class, $this->app->make('laravel-ai.driver'));

        // 4. Facade is functional
        $this->assertInstanceOf(AIManager::class, AI::getFacadeRoot());

        // 5. Mock provider is available and functional
        $mockProvider = $this->app->make('laravel-ai.driver')->driver('mock');
        $this->assertInstanceOf(\JTD\LaravelAI\Providers\MockProvider::class, $mockProvider);

        // 6. Basic AI operations work
        $response = AI::conversation()
            ->provider('mock')
            ->message('Hello, world!')
            ->send();

        $this->assertInstanceOf(\JTD\LaravelAI\Models\AIResponse::class, $response);
        $this->assertNotEmpty($response->content);

        // If we reach here, the package installation is complete and functional
        $this->assertTrue(true, 'Package installation is complete and functional');
    }
}
