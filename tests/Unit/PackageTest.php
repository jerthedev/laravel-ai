<?php

namespace JTD\LaravelAI\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use JTD\LaravelAI\LaravelAIServiceProvider;
use JTD\LaravelAI\Tests\TestCase;

class PackageTest extends TestCase
{
    #[Test]
    public function it_loads_the_service_provider()
    {
        $this->assertTrue(
            $this->app->providerIsLoaded(LaravelAIServiceProvider::class),
            'LaravelAIServiceProvider should be loaded'
        );
    }
    #[Test]
    public function it_registers_the_ai_facade()
    {
        $this->assertTrue(
            class_exists('JTD\LaravelAI\Facades\AI'),
            'AI facade class should exist'
        );
    }
    #[Test]
    public function it_has_correct_package_configuration()
    {
        // Test that configuration is loaded
        $this->assertNotNull(config('ai'));
        $this->assertEquals('mock', config('ai.default'));
        $this->assertIsArray(config('ai.providers'));
    }
    #[Test]
    public function it_has_correct_database_configuration()
    {
        $this->assertEquals('testing', config('database.default'));
        $this->assertEquals('sqlite', config('database.connections.testing.driver'));
        $this->assertEquals(':memory:', config('database.connections.testing.database'));
    }
    #[Test]
    public function it_has_correct_test_environment_setup()
    {
        $this->assertEquals('testing', app()->environment());
        $this->assertEquals('array', config('cache.default'));
        $this->assertEquals('sync', config('queue.default'));
    }
    #[Test]
    public function it_can_resolve_services_from_container()
    {
        // These will fail until we implement the actual services,
        // but they verify the container setup is working
        $this->assertTrue(app()->bound('config'));
        $this->assertTrue(app()->bound('db'));
        $this->assertTrue(app()->bound('cache'));
        $this->assertTrue(app()->bound('queue'));
    }
    #[Test]
    public function it_has_proper_autoloading_setup()
    {
        // Test that our namespace is properly autoloaded
        $this->assertTrue(
            class_exists('JTD\LaravelAI\Tests\TestCase'),
            'Test namespace should be autoloaded'
        );
    }
    #[Test]
    public function it_has_required_directories()
    {
        $basePath = dirname(__DIR__, 2);

        $this->assertDirectoryExists($basePath . '/src');
        $this->assertDirectoryExists($basePath . '/src/Contracts');
        $this->assertDirectoryExists($basePath . '/src/Facades');
        $this->assertDirectoryExists($basePath . '/src/Models');
        $this->assertDirectoryExists($basePath . '/src/Services');
        $this->assertDirectoryExists($basePath . '/src/Drivers');
        $this->assertDirectoryExists($basePath . '/src/Events');
        $this->assertDirectoryExists($basePath . '/src/Exceptions');
        $this->assertDirectoryExists($basePath . '/config');
        $this->assertDirectoryExists($basePath . '/database/migrations');
        $this->assertDirectoryExists($basePath . '/tests/Unit');
        $this->assertDirectoryExists($basePath . '/tests/Feature');
        $this->assertDirectoryExists($basePath . '/tests/Integration');
        $this->assertDirectoryExists($basePath . '/tests/Mocks');
    }
    #[Test]
    public function it_has_required_files()
    {
        $basePath = dirname(__DIR__, 2);

        $this->assertFileExists($basePath . '/composer.json');
        $this->assertFileExists($basePath . '/README.md');
        $this->assertFileExists($basePath . '/LICENSE.md');
        $this->assertFileExists($basePath . '/phpunit.xml');
    }
    #[Test]
    public function composer_json_has_correct_structure()
    {
        $basePath = dirname(__DIR__, 2);
        $composerJson = json_decode(file_get_contents($basePath . '/composer.json'), true);

        $this->assertEquals('jerthedev/laravel-ai', $composerJson['name']);
        $this->assertEquals('MIT', $composerJson['license']);
        $this->assertArrayHasKey('autoload', $composerJson);
        $this->assertArrayHasKey('autoload-dev', $composerJson);
        $this->assertArrayHasKey('extra', $composerJson);
        $this->assertArrayHasKey('laravel', $composerJson['extra']);
        $this->assertArrayHasKey('providers', $composerJson['extra']['laravel']);
        $this->assertArrayHasKey('aliases', $composerJson['extra']['laravel']);
    }
    #[Test]
    public function it_has_correct_php_and_laravel_requirements()
    {
        $basePath = dirname(__DIR__, 2);
        $composerJson = json_decode(file_get_contents($basePath . '/composer.json'), true);

        $this->assertArrayHasKey('php', $composerJson['require']);
        $this->assertStringContainsString('8.1', $composerJson['require']['php']);
        $this->assertArrayHasKey('illuminate/contracts', $composerJson['require']);
        $this->assertStringContainsString('10.0', $composerJson['require']['illuminate/contracts']);
    }
}
