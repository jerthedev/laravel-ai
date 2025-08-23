<?php

namespace JTD\LaravelAI\Tests\Unit\Services;

use JTD\LaravelAI\Contracts\AIProviderInterface;
use JTD\LaravelAI\Services\DriverManager;
use JTD\LaravelAI\Tests\TestCase;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Unit tests for DriverManager sync functionality
 */
#[Group('unit')]
#[Group('driver-manager')]
#[Group('sync')]
class DriverManagerSyncTest extends TestCase
{
    protected DriverManager $driverManager;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock the application environment method
        $this->app->shouldReceive('environment')
            ->with('production')
            ->andReturn(false);

        $this->driverManager = new DriverManager($this->app);
    }

    #[Test]
    public function it_gets_providers_with_valid_credentials(): void
    {
        $validDriver = Mockery::mock(AIProviderInterface::class);
        $validDriver->shouldReceive('hasValidCredentials')->andReturn(true);

        $invalidDriver = Mockery::mock(AIProviderInterface::class);
        $invalidDriver->shouldReceive('hasValidCredentials')->andReturn(false);

        $failingDriver = Mockery::mock(AIProviderInterface::class);

        // Mock the driver manager methods
        $driverManager = Mockery::mock(DriverManager::class)->makePartial();
        $driverManager->shouldReceive('getAvailableProviders')
            ->andReturn(['valid-provider', 'invalid-provider', 'failing-provider']);

        $driverManager->shouldReceive('driver')
            ->with('valid-provider')
            ->andReturn($validDriver);

        $driverManager->shouldReceive('driver')
            ->with('invalid-provider')
            ->andReturn($invalidDriver);

        $driverManager->shouldReceive('driver')
            ->with('failing-provider')
            ->andThrow(new \Exception('Driver instantiation failed'));

        $result = $driverManager->getProvidersWithValidCredentials();

        $this->assertIsArray($result);
        $this->assertContains('valid-provider', $result);
        $this->assertNotContains('invalid-provider', $result);
        $this->assertNotContains('failing-provider', $result);
    }

    #[Test]
    public function it_skips_mock_provider_in_production(): void
    {
        $this->app->shouldReceive('environment')
            ->with('production')
            ->andReturn(true);

        $mockDriver = Mockery::mock(AIProviderInterface::class);
        $mockDriver->shouldReceive('hasValidCredentials')->andReturn(true);

        $driverManager = Mockery::mock(DriverManager::class)->makePartial();
        $driverManager->shouldReceive('getAvailableProviders')
            ->andReturn(['mock', 'openai']);

        $driverManager->shouldReceive('driver')
            ->with('openai')
            ->andReturn($mockDriver);

        $result = $driverManager->getProvidersWithValidCredentials();

        $this->assertNotContains('mock', $result);
        $this->assertContains('openai', $result);
    }

    #[Test]
    public function it_checks_if_provider_has_valid_credentials(): void
    {
        $validDriver = Mockery::mock(AIProviderInterface::class);
        $validDriver->shouldReceive('hasValidCredentials')->andReturn(true);

        $driverManager = Mockery::mock(DriverManager::class)->makePartial();
        $driverManager->shouldReceive('driver')
            ->with('valid-provider')
            ->andReturn($validDriver);

        $result = $driverManager->hasValidCredentials('valid-provider');

        $this->assertTrue($result);
    }

    #[Test]
    public function it_returns_false_for_invalid_credentials(): void
    {
        $driverManager = Mockery::mock(DriverManager::class)->makePartial();
        $driverManager->shouldReceive('driver')
            ->with('invalid-provider')
            ->andThrow(new \Exception('Invalid credentials'));

        $result = $driverManager->hasValidCredentials('invalid-provider');

        $this->assertFalse($result);
    }

    #[Test]
    public function it_gets_all_syncable_models(): void
    {
        $driver1 = Mockery::mock(AIProviderInterface::class);
        $driver1->shouldReceive('getSyncableModels')
            ->andReturn([
                ['id' => 'gpt-4', 'name' => 'GPT-4'],
                ['id' => 'gpt-3.5-turbo', 'name' => 'GPT-3.5 Turbo'],
            ]);

        $driver2 = Mockery::mock(AIProviderInterface::class);
        $driver2->shouldReceive('getSyncableModels')
            ->andReturn([
                ['id' => 'gemini-pro', 'name' => 'Gemini Pro'],
            ]);

        $driverManager = Mockery::mock(DriverManager::class)->makePartial();
        $driverManager->shouldReceive('getProvidersWithValidCredentials')
            ->andReturn(['openai', 'gemini']);

        $driverManager->shouldReceive('driver')
            ->with('openai')
            ->andReturn($driver1);

        $driverManager->shouldReceive('driver')
            ->with('gemini')
            ->andReturn($driver2);

        $result = $driverManager->getAllSyncableModels();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('openai', $result);
        $this->assertArrayHasKey('gemini', $result);
        $this->assertCount(2, $result['openai']);
        $this->assertCount(1, $result['gemini']);
    }

    #[Test]
    public function it_handles_errors_when_getting_syncable_models(): void
    {
        $validDriver = Mockery::mock(AIProviderInterface::class);
        $validDriver->shouldReceive('getSyncableModels')
            ->andReturn([['id' => 'gpt-4']]);

        $failingDriver = Mockery::mock(AIProviderInterface::class);
        $failingDriver->shouldReceive('getSyncableModels')
            ->andThrow(new \Exception('API Error'));

        $driverManager = Mockery::mock(DriverManager::class)->makePartial();
        $driverManager->shouldReceive('getProvidersWithValidCredentials')
            ->andReturn(['valid-provider', 'failing-provider']);

        $driverManager->shouldReceive('driver')
            ->with('valid-provider')
            ->andReturn($validDriver);

        $driverManager->shouldReceive('driver')
            ->with('failing-provider')
            ->andReturn($failingDriver);

        $result = $driverManager->getAllSyncableModels();

        $this->assertArrayHasKey('valid-provider', $result);
        $this->assertArrayNotHasKey('failing-provider', $result);
    }

    #[Test]
    public function it_syncs_all_provider_models(): void
    {
        $driver1 = Mockery::mock(AIProviderInterface::class);
        $driver1->shouldReceive('syncModels')
            ->with(false)
            ->andReturn([
                'status' => 'success',
                'models_synced' => 15,
            ]);

        $driver2 = Mockery::mock(AIProviderInterface::class);
        $driver2->shouldReceive('syncModels')
            ->with(false)
            ->andReturn([
                'status' => 'skipped',
                'reason' => 'cache_valid',
            ]);

        $driverManager = Mockery::mock(DriverManager::class)->makePartial();
        $driverManager->shouldReceive('getProvidersWithValidCredentials')
            ->andReturn(['openai', 'gemini']);

        $driverManager->shouldReceive('driver')
            ->with('openai')
            ->andReturn($driver1);

        $driverManager->shouldReceive('driver')
            ->with('gemini')
            ->andReturn($driver2);

        $result = $driverManager->syncAllProviderModels(false);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('openai', $result);
        $this->assertArrayHasKey('gemini', $result);
        $this->assertEquals('success', $result['openai']['status']);
        $this->assertEquals('skipped', $result['gemini']['status']);
    }

    #[Test]
    public function it_handles_sync_errors_for_individual_providers(): void
    {
        $validDriver = Mockery::mock(AIProviderInterface::class);
        $validDriver->shouldReceive('syncModels')
            ->andReturn(['status' => 'success']);

        $failingDriver = Mockery::mock(AIProviderInterface::class);
        $failingDriver->shouldReceive('syncModels')
            ->andThrow(new \Exception('Sync failed'));

        $driverManager = Mockery::mock(DriverManager::class)->makePartial();
        $driverManager->shouldReceive('getProvidersWithValidCredentials')
            ->andReturn(['valid-provider', 'failing-provider']);

        $driverManager->shouldReceive('driver')
            ->with('valid-provider')
            ->andReturn($validDriver);

        $driverManager->shouldReceive('driver')
            ->with('failing-provider')
            ->andReturn($failingDriver);

        $result = $driverManager->syncAllProviderModels();

        $this->assertEquals('success', $result['valid-provider']['status']);
        $this->assertEquals('error', $result['failing-provider']['status']);
        $this->assertEquals('Sync failed', $result['failing-provider']['error']);
    }

    #[Test]
    public function it_gets_all_last_sync_times(): void
    {
        $syncTime1 = now()->subHours(2);
        $syncTime2 = now()->subHours(4);

        $driver1 = Mockery::mock(AIProviderInterface::class);
        $driver1->shouldReceive('getLastSyncTime')->andReturn($syncTime1);

        $driver2 = Mockery::mock(AIProviderInterface::class);
        $driver2->shouldReceive('getLastSyncTime')->andReturn($syncTime2);

        $failingDriver = Mockery::mock(AIProviderInterface::class);

        $driverManager = Mockery::mock(DriverManager::class)->makePartial();
        $driverManager->shouldReceive('getAvailableProviders')
            ->andReturn(['provider1', 'provider2', 'failing-provider']);

        $driverManager->shouldReceive('driver')
            ->with('provider1')
            ->andReturn($driver1);

        $driverManager->shouldReceive('driver')
            ->with('provider2')
            ->andReturn($driver2);

        $driverManager->shouldReceive('driver')
            ->with('failing-provider')
            ->andThrow(new \Exception('Driver error'));

        $result = $driverManager->getAllLastSyncTimes();

        $this->assertIsArray($result);
        $this->assertEquals($syncTime1, $result['provider1']);
        $this->assertEquals($syncTime2, $result['provider2']);
        $this->assertNull($result['failing-provider']);
    }
}
