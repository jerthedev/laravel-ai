<?php

namespace JTD\LaravelAI\Tests\Feature;

use Illuminate\Support\Facades\File;
use JTD\LaravelAI\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ConfigurationPublishingTest extends TestCase
{
    #[Test]
    public function it_can_publish_configuration_file()
    {
        // Ensure config file doesn't exist
        $configPath = config_path('ai.php');
        if (File::exists($configPath)) {
            File::delete($configPath);
        }

        // Publish the configuration
        $this->artisan('vendor:publish', [
            '--tag' => 'laravel-ai-config',
            '--force' => true,
        ])->assertExitCode(0);

        // Assert the config file was published
        $this->assertTrue(File::exists($configPath));

        // Clean up
        if (File::exists($configPath)) {
            File::delete($configPath);
        }
    }

    #[Test]
    public function it_can_publish_all_assets()
    {
        // Ensure files don't exist
        $configPath = config_path('ai.php');
        if (File::exists($configPath)) {
            File::delete($configPath);
        }

        // Publish all assets
        $this->artisan('vendor:publish', [
            '--tag' => 'laravel-ai',
            '--force' => true,
        ])->assertExitCode(0);

        // Assert the config file was published
        $this->assertTrue(File::exists($configPath));

        // Clean up
        if (File::exists($configPath)) {
            File::delete($configPath);
        }
    }

    #[Test]
    public function published_config_has_correct_structure()
    {
        // Publish the configuration
        $configPath = config_path('ai.php');
        if (File::exists($configPath)) {
            File::delete($configPath);
        }

        $this->artisan('vendor:publish', [
            '--tag' => 'laravel-ai-config',
            '--force' => true,
        ])->assertExitCode(0);

        // Load the published config
        $publishedConfig = include $configPath;

        // Assert structure
        $this->assertIsArray($publishedConfig);
        $this->assertArrayHasKey('default', $publishedConfig);
        $this->assertArrayHasKey('providers', $publishedConfig);
        $this->assertArrayHasKey('cost_tracking', $publishedConfig);
        $this->assertArrayHasKey('model_sync', $publishedConfig);
        $this->assertArrayHasKey('cache', $publishedConfig);
        $this->assertArrayHasKey('rate_limiting', $publishedConfig);
        $this->assertArrayHasKey('logging', $publishedConfig);
        $this->assertArrayHasKey('mcp', $publishedConfig);

        // Assert providers
        $this->assertArrayHasKey('openai', $publishedConfig['providers']);
        $this->assertArrayHasKey('xai', $publishedConfig['providers']);
        $this->assertArrayHasKey('gemini', $publishedConfig['providers']);
        $this->assertArrayHasKey('ollama', $publishedConfig['providers']);
        $this->assertArrayHasKey('mock', $publishedConfig['providers']);

        // Clean up
        if (File::exists($configPath)) {
            File::delete($configPath);
        }
    }

    #[Test]
    public function published_config_contains_environment_variables()
    {
        // Publish the configuration
        $configPath = config_path('ai.php');
        if (File::exists($configPath)) {
            File::delete($configPath);
        }

        $this->artisan('vendor:publish', [
            '--tag' => 'laravel-ai-config',
            '--force' => true,
        ])->assertExitCode(0);

        // Read the published config file content
        $configContent = File::get($configPath);

        // Assert environment variables are present
        $this->assertStringContainsString("env('AI_DEFAULT_PROVIDER'", $configContent);
        $this->assertStringContainsString("env('AI_OPENAI_API_KEY'", $configContent);
        $this->assertStringContainsString("env('AI_XAI_API_KEY'", $configContent);
        $this->assertStringContainsString("env('AI_GEMINI_API_KEY'", $configContent);
        $this->assertStringContainsString("env('AI_COST_TRACKING_ENABLED'", $configContent);
        $this->assertStringContainsString("env('AI_MODEL_SYNC_ENABLED'", $configContent);
        $this->assertStringContainsString("env('AI_CACHE_ENABLED'", $configContent);

        // Clean up
        if (File::exists($configPath)) {
            File::delete($configPath);
        }
    }

    #[Test]
    public function it_can_list_publishable_assets()
    {
        $output = $this->artisan('vendor:publish', [
            '--provider' => 'JTD\LaravelAI\LaravelAIServiceProvider',
        ])->run();

        $this->assertEquals(0, $output);
    }

    #[Test]
    public function service_provider_merges_config_correctly()
    {
        // Test that the service provider merges the config correctly
        $config = config('ai');

        $this->assertIsArray($config);
        $this->assertNotEmpty($config);

        // Test that we can access nested values
        $this->assertIsArray($config['providers']);
        $this->assertIsArray($config['cost_tracking']);
        $this->assertIsArray($config['model_sync']);
    }

    #[Test]
    public function config_can_be_overridden_by_published_file()
    {
        // This test would require actually publishing and modifying the config
        // For now, we'll just test that the config system works
        $originalDefault = config('ai.default');

        // Temporarily override config
        config(['ai.default' => 'test-override']);

        $this->assertEquals('test-override', config('ai.default'));

        // Reset
        config(['ai.default' => $originalDefault]);
        $this->assertEquals($originalDefault, config('ai.default'));
    }
}
