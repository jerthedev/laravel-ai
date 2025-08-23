<?php

namespace JTD\LaravelAI\Tests\Unit\Drivers\OpenAI\Traits;

use Illuminate\Support\Facades\Cache;
use JTD\LaravelAI\Drivers\OpenAI\OpenAIDriver;
use JTD\LaravelAI\Tests\TestCase;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Unit tests for sync functionality in ManagesModels trait
 */
#[Group('unit')]
#[Group('openai')]
#[Group('sync')]
class ManagesModelsSyncTest extends TestCase
{
    protected OpenAIDriver $driver;

    protected array $mockConfig;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockConfig = [
            'api_key' => 'sk-test-api-key-1234567890abcdef1234567890abcdef',
            'organization' => 'test-org',
            'project' => 'test-project',
        ];

        // Clear cache before each test
        Cache::flush();
    }

    #[Test]
    public function it_syncs_models_successfully(): void
    {
        // Mock the driver to avoid actual API calls
        $driver = Mockery::mock(OpenAIDriver::class)->makePartial();
        $driver->shouldAllowMockingProtectedMethods();

        $driver->shouldReceive('getName')->andReturn('openai');
        $driver->shouldReceive('shouldRefreshModels')->andReturn(true);
        $driver->shouldReceive('getAvailableModels')
            ->with(true)
            ->andReturn([
                ['id' => 'gpt-4', 'capabilities' => ['function_calling']],
                ['id' => 'gpt-3.5-turbo', 'capabilities' => ['function_calling']],
            ]);

        $result = $driver->syncModels(true);

        $this->assertEquals('success', $result['status']);
        $this->assertGreaterThan(0, $result['models_synced']);
        $this->assertArrayHasKey('statistics', $result);
        $this->assertArrayHasKey('cached_until', $result);
        $this->assertArrayHasKey('last_sync', $result);
    }

    #[Test]
    public function it_skips_sync_when_cache_is_valid(): void
    {
        // Mock the driver to control cache behavior
        $driver = Mockery::mock(OpenAIDriver::class)->makePartial();
        $driver->shouldAllowMockingProtectedMethods();

        $driver->shouldReceive('getName')->andReturn('openai');
        $driver->shouldReceive('shouldRefreshModels')->andReturn(false);
        $driver->shouldReceive('getLastSyncTime')->andReturn(now()->subHours(6));

        $result = $driver->syncModels(false);

        $this->assertEquals('skipped', $result['status']);
        $this->assertEquals('cache_valid', $result['reason']);
        $this->assertArrayHasKey('last_sync', $result);
    }

    #[Test]
    public function it_forces_sync_when_force_refresh_is_true(): void
    {
        // Set up valid cache
        $cacheKey = 'laravel-ai:openai:models';
        Cache::put($cacheKey . ':last_sync', now()->subHours(6), now()->addDays(7));

        // Mock the driver
        $driver = Mockery::mock(OpenAIDriver::class)->makePartial();
        $driver->shouldReceive('getName')->andReturn('openai');
        $driver->shouldReceive('getAvailableModels')
            ->with(true)
            ->once()
            ->andReturn([
                ['id' => 'gpt-4', 'name' => 'GPT-4'],
            ]);

        $result = $driver->syncModels(true);

        $this->assertEquals('success', $result['status']);
    }

    #[Test]
    public function it_handles_sync_errors_gracefully(): void
    {
        $driver = Mockery::mock(OpenAIDriver::class)->makePartial();
        $driver->shouldReceive('getName')->andReturn('openai');
        $driver->shouldReceive('getAvailableModels')
            ->andThrow(new \Exception('API Error'));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('API Error');

        $driver->syncModels(true);

        // Check that failure was cached
        $cacheKey = 'laravel-ai:openai:models:last_failure';
        $failure = Cache::get($cacheKey);
        $this->assertNotNull($failure);
        $this->assertEquals('API Error', $failure['error']);
    }

    #[Test]
    public function it_checks_valid_credentials(): void
    {
        $driver = Mockery::mock(OpenAIDriver::class)->makePartial();
        $driver->shouldReceive('validateCredentials')
            ->once()
            ->andReturn(['status' => 'valid']);

        $result = $driver->hasValidCredentials();

        $this->assertTrue($result);
    }

    #[Test]
    public function it_returns_false_for_invalid_credentials(): void
    {
        $driver = Mockery::mock(OpenAIDriver::class)->makePartial();
        $driver->shouldReceive('validateCredentials')
            ->once()
            ->andThrow(new \Exception('Invalid credentials'));

        $result = $driver->hasValidCredentials();

        $this->assertFalse($result);
    }

    #[Test]
    public function it_gets_last_sync_time(): void
    {
        $syncTime = now()->subHours(2);
        $cacheKey = 'laravel-ai:openai:models:last_sync';
        Cache::put($cacheKey, $syncTime, now()->addDays(7));

        // Mock the driver to avoid credential validation
        $driver = Mockery::mock(OpenAIDriver::class)->makePartial();
        $driver->shouldAllowMockingProtectedMethods();
        $driver->shouldReceive('getModelsCacheKey')->andReturn('laravel-ai:openai:models');

        $result = $driver->getLastSyncTime();

        $this->assertNotNull($result);
        $this->assertEquals($syncTime->timestamp, $result->timestamp);
    }

    #[Test]
    public function it_returns_null_when_never_synced(): void
    {
        // Mock the driver to avoid credential validation
        $driver = Mockery::mock(OpenAIDriver::class)->makePartial();
        $driver->shouldAllowMockingProtectedMethods();
        $driver->shouldReceive('getModelsCacheKey')->andReturn('laravel-ai:openai:models');

        $result = $driver->getLastSyncTime();

        $this->assertNull($result);
    }

    #[Test]
    public function it_gets_syncable_models(): void
    {
        $mockModelsResponse = Mockery::mock();
        $mockModelsResponse->data = [
            (object) [
                'id' => 'gpt-4',
                'created' => 1234567890,
                'ownedBy' => 'openai',
            ],
        ];

        $driver = Mockery::mock(OpenAIDriver::class)->makePartial();
        $driver->shouldAllowMockingProtectedMethods();
        $driver->shouldReceive('executeWithRetry')
            ->once()
            ->andReturn($mockModelsResponse);

        $result = $driver->getSyncableModels();

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertEquals('gpt-4', $result[0]['id']);
        $this->assertArrayHasKey('name', $result[0]);
        $this->assertArrayHasKey('owned_by', $result[0]);
    }

    #[Test]
    public function it_stores_model_statistics(): void
    {
        $models = [
            ['id' => 'gpt-4', 'capabilities' => ['function_calling', 'vision']],
            ['id' => 'gpt-3.5-turbo', 'capabilities' => ['function_calling']],
            ['id' => 'gpt-4o', 'capabilities' => ['vision']],
        ];

        // Mock the driver to avoid credential validation
        $driver = Mockery::mock(OpenAIDriver::class)->makePartial();
        $driver->shouldAllowMockingProtectedMethods();
        $driver->shouldReceive('getModelsCacheKey')->andReturn('laravel-ai:openai:models');

        // Use reflection to call protected method
        $reflection = new \ReflectionClass(OpenAIDriver::class);
        $method = $reflection->getMethod('storeModelStatistics');
        $method->setAccessible(true);

        $stats = $method->invoke($driver, $models);

        $this->assertEquals(3, $stats['total_models']);
        $this->assertEquals(1, $stats['gpt_4_models']);
        $this->assertEquals(1, $stats['gpt_3_5_models']);
        $this->assertEquals(1, $stats['gpt_4o_models']);
        $this->assertEquals(2, $stats['function_calling_models']);
        $this->assertEquals(2, $stats['vision_models']);

        // Check that stats were cached
        $cacheKey = 'laravel-ai:openai:models:stats';
        $cachedStats = Cache::get($cacheKey);
        $this->assertNotNull($cachedStats);
        $this->assertEquals($stats, $cachedStats);
    }

    /**
     * Set a protected property on an object.
     */
    protected function setProtectedProperty($object, string $property, $value): void
    {
        $reflection = new \ReflectionClass($object);
        $prop = $reflection->getProperty($property);
        $prop->setAccessible(true);
        $prop->setValue($object, $value);
    }
}
