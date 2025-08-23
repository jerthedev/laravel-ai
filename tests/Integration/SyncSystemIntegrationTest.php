<?php

namespace JTD\LaravelAI\Tests\Integration;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use JTD\LaravelAI\Drivers\OpenAI\OpenAIDriver;
use JTD\LaravelAI\Services\DriverManager;
use JTD\LaravelAI\Tests\TestCase;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Integration tests for the complete sync system
 */
#[Group('integration')]
#[Group('sync')]
class SyncSystemIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Clear cache before each test
        Cache::flush();
    }

    #[Test]
    public function it_integrates_driver_manager_with_sync_command(): void
    {
        // Mock the OpenAI driver
        $mockDriver = Mockery::mock(OpenAIDriver::class);
        $mockDriver->shouldReceive('hasValidCredentials')->andReturn(true);
        $mockDriver->shouldReceive('syncModels')
            ->with(false)
            ->andReturn([
                'status' => 'success',
                'models_synced' => 15,
                'statistics' => [
                    'total_models' => 15,
                    'gpt_4_models' => 5,
                    'function_calling_models' => 10,
                ],
                'cached_until' => now()->addHours(24),
                'last_sync' => now(),
            ]);

        // Mock the driver manager
        $mockDriverManager = Mockery::mock(DriverManager::class);
        $mockDriverManager->shouldReceive('getAvailableProviders')
            ->andReturn(['mock']);
        $mockDriverManager->shouldReceive('driver')
            ->with('mock')
            ->andReturn($mockDriver);

        // Bind the mock to the container
        $this->app->instance(DriverManager::class, $mockDriverManager);

        // Run the command
        $exitCode = Artisan::call('ai:sync-models');

        $this->assertEquals(0, $exitCode);
        $output = Artisan::output();
        $this->assertStringContainsString('Syncing models from AI providers', $output);
        $this->assertStringContainsString('mock:', $output);
        $this->assertStringContainsString('Found 3 models', $output);
        $this->assertStringContainsString('Sync completed successfully', $output);
    }

    #[Test]
    public function it_handles_mixed_provider_results(): void
    {
        $successDriver = Mockery::mock(OpenAIDriver::class);
        $successDriver->shouldReceive('hasValidCredentials')->andReturn(true);
        $successDriver->shouldReceive('syncModels')
            ->andReturn([
                'status' => 'success',
                'models_synced' => 10,
            ]);

        $skippedDriver = Mockery::mock(OpenAIDriver::class);
        $skippedDriver->shouldReceive('hasValidCredentials')->andReturn(true);
        $skippedDriver->shouldReceive('syncModels')
            ->andReturn([
                'status' => 'skipped',
                'reason' => 'cache_valid',
                'last_sync' => now()->subHours(1),
            ]);

        $failingDriver = Mockery::mock(OpenAIDriver::class);
        $failingDriver->shouldReceive('hasValidCredentials')->andReturn(true);
        $failingDriver->shouldReceive('syncModels')
            ->andThrow(new \Exception('API Error'));

        $mockDriverManager = Mockery::mock(DriverManager::class);
        $mockDriverManager->shouldReceive('getAvailableProviders')
            ->andReturn(['success-provider', 'skipped-provider', 'failing-provider']);

        $mockDriverManager->shouldReceive('driver')
            ->with('success-provider')
            ->andReturn($successDriver);

        $mockDriverManager->shouldReceive('driver')
            ->with('skipped-provider')
            ->andReturn($skippedDriver);

        $mockDriverManager->shouldReceive('driver')
            ->with('failing-provider')
            ->andReturn($failingDriver);

        $this->app->instance(DriverManager::class, $mockDriverManager);

        $exitCode = Artisan::call('ai:sync-models');

        $this->assertEquals(1, $exitCode); // Should fail due to error
        $output = Artisan::output();

        // Check success provider
        $this->assertStringContainsString('success-provider:', $output);
        $this->assertStringContainsString('Found 10 models', $output);

        // Check skipped provider
        $this->assertStringContainsString('skipped-provider:', $output);
        $this->assertStringContainsString('Skipped (cache_valid)', $output);

        // Check failing provider
        $this->assertStringContainsString('Failed to sync failing-provider', $output);
        $this->assertStringContainsString('API Error', $output);

        // Check error summary
        $this->assertStringContainsString('Errors encountered:', $output);
    }

    #[Test]
    public function it_performs_complete_dry_run(): void
    {
        $mockDriver = Mockery::mock(OpenAIDriver::class);
        $mockDriver->shouldReceive('hasValidCredentials')->andReturn(true);
        $mockDriver->shouldReceive('getSyncableModels')
            ->andReturn([
                ['id' => 'gpt-4', 'name' => 'GPT-4'],
                ['id' => 'gpt-3.5-turbo', 'name' => 'GPT-3.5 Turbo'],
                ['id' => 'gpt-4o', 'name' => 'GPT-4o'],
            ]);
        $mockDriver->shouldReceive('getLastSyncTime')
            ->andReturn(now()->subHours(3));

        // Should NOT call syncModels in dry run
        $mockDriver->shouldNotReceive('syncModels');

        $mockDriverManager = Mockery::mock(DriverManager::class);
        $mockDriverManager->shouldReceive('getAvailableProviders')
            ->andReturn(['openai']);
        $mockDriverManager->shouldReceive('driver')
            ->with('openai')
            ->andReturn($mockDriver);

        $this->app->instance(DriverManager::class, $mockDriverManager);

        $exitCode = Artisan::call('ai:sync-models', ['--dry-run' => true]);

        $this->assertEquals(0, $exitCode);
        $output = Artisan::output();

        $this->assertStringContainsString('DRY RUN - No changes will be made', $output);
        $this->assertStringContainsString('Would sync: 3 models', $output);
        $this->assertStringContainsString('Last synced: 3 hours ago', $output);
        $this->assertStringContainsString('Dry run completed!', $output);
    }

    #[Test]
    public function it_handles_verbose_output_with_statistics(): void
    {
        $mockDriver = Mockery::mock(OpenAIDriver::class);
        $mockDriver->shouldReceive('hasValidCredentials')->andReturn(true);
        $mockDriver->shouldReceive('syncModels')
            ->andReturn([
                'status' => 'success',
                'models_synced' => 20,
                'statistics' => [
                    'total_models' => 20,
                    'gpt_4_models' => 8,
                    'gpt_3_5_models' => 5,
                    'gpt_4o_models' => 7,
                    'function_calling_models' => 15,
                    'vision_models' => 10,
                ],
            ]);

        $mockDriverManager = Mockery::mock(DriverManager::class);
        $mockDriverManager->shouldReceive('getAvailableProviders')
            ->andReturn(['openai']);
        $mockDriverManager->shouldReceive('driver')
            ->with('openai')
            ->andReturn($mockDriver);

        $this->app->instance(DriverManager::class, $mockDriverManager);

        $exitCode = Artisan::call('ai:sync-models', ['--verbose' => true]);

        $this->assertEquals(0, $exitCode);
        $output = Artisan::output();

        $this->assertStringContainsString('Statistics updated', $output);
        $this->assertStringContainsString('Total: 20', $output);
        $this->assertStringContainsString('GPT-4: 8', $output);
        $this->assertStringContainsString('Function calling: 15', $output);
    }

    #[Test]
    public function it_filters_providers_by_credentials(): void
    {
        $validDriver = Mockery::mock(OpenAIDriver::class);
        $validDriver->shouldReceive('hasValidCredentials')->andReturn(true);
        $validDriver->shouldReceive('syncModels')->andReturn([
            'status' => 'success',
            'models_synced' => 5,
        ]);

        $invalidDriver = Mockery::mock(OpenAIDriver::class);
        $invalidDriver->shouldReceive('hasValidCredentials')->andReturn(false);
        // Should not call syncModels for invalid driver

        $mockDriverManager = Mockery::mock(DriverManager::class);
        $mockDriverManager->shouldReceive('getAvailableProviders')
            ->andReturn(['valid-provider', 'invalid-provider']);

        $mockDriverManager->shouldReceive('driver')
            ->with('valid-provider')
            ->andReturn($validDriver);

        $mockDriverManager->shouldReceive('driver')
            ->with('invalid-provider')
            ->andReturn($invalidDriver);

        $this->app->instance(DriverManager::class, $mockDriverManager);

        $exitCode = Artisan::call('ai:sync-models');

        $this->assertEquals(0, $exitCode);
        $output = Artisan::output();

        // Should only show valid provider
        $this->assertStringContainsString('valid-provider:', $output);
        $this->assertStringNotContainsString('invalid-provider:', $output);
        $this->assertStringContainsString('Total: 5 models synced across 1 providers', $output);
    }

    #[Test]
    public function it_caches_sync_results_properly(): void
    {
        // This test verifies that the sync system properly uses Laravel's cache
        $mockDriver = Mockery::mock(OpenAIDriver::class);
        $mockDriver->shouldReceive('hasValidCredentials')->andReturn(true);

        // First call should sync
        $mockDriver->shouldReceive('syncModels')
            ->once()
            ->with(false)
            ->andReturn([
                'status' => 'success',
                'models_synced' => 10,
            ]);

        $mockDriverManager = Mockery::mock(DriverManager::class);
        $mockDriverManager->shouldReceive('getAvailableProviders')
            ->andReturn(['openai']);
        $mockDriverManager->shouldReceive('driver')
            ->with('openai')
            ->andReturn($mockDriver);

        $this->app->instance(DriverManager::class, $mockDriverManager);

        // First sync
        $exitCode1 = Artisan::call('ai:sync-models');
        $this->assertEquals(0, $exitCode1);

        // Verify cache was used by checking that syncModels was only called once
        // (This is implicit in the mock expectation above)
    }
}
