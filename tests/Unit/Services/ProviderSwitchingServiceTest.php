<?php

namespace JTD\LaravelAI\Tests\Unit\Services;

use Illuminate\Support\Facades\Event;
use JTD\LaravelAI\Events\ProviderSwitched;
use JTD\LaravelAI\Exceptions\ProviderSwitchException;
use JTD\LaravelAI\Models\AIConversation;
use JTD\LaravelAI\Models\AIProvider;
use JTD\LaravelAI\Models\AIProviderModel;
use JTD\LaravelAI\Services\ConversationContextManager;
use JTD\LaravelAI\Services\ConversationService;
use JTD\LaravelAI\Services\DriverManager;
use JTD\LaravelAI\Services\ProviderSwitchingService;
use JTD\LaravelAI\Tests\TestCase;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class ProviderSwitchingServiceTest extends TestCase
{
    protected ProviderSwitchingService $service;

    protected $driverManager;

    protected $conversationService;

    protected $contextManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->driverManager = Mockery::mock(DriverManager::class);
        $this->conversationService = Mockery::mock(ConversationService::class);
        $this->contextManager = Mockery::mock(ConversationContextManager::class);

        $this->service = new ProviderSwitchingService(
            $this->driverManager,
            $this->conversationService,
            $this->contextManager
        );
    }

    #[Test]
    public function it_can_switch_provider_successfully(): void
    {
        Event::fake();

        // Create test data
        $conversation = AIConversation::factory()->create([
            'provider_name' => 'openai',
            'model_name' => 'gpt-4',
        ]);

        $newProvider = AIProvider::factory()->create([
            'name' => 'gemini',
            'status' => 'active',
        ]);

        $newModel = AIProviderModel::factory()->create([
            'ai_provider_id' => $newProvider->id,
            'model_id' => 'gemini-pro',
            'name' => 'Gemini Pro',
            'status' => 'active',
        ]);

        // Mock driver validation
        $mockDriver = Mockery::mock(\JTD\LaravelAI\Contracts\AIProviderInterface::class);
        $mockDriver->shouldReceive('validateHealth')->andReturn(true);
        $this->driverManager->shouldReceive('driver')->with('gemini')->andReturn($mockDriver);

        // Mock context preservation
        $this->contextManager->shouldReceive('preserveContextForSwitch')
            ->once()
            ->andReturn([
                'messages' => [],
                'total_tokens' => 100,
                'truncated' => false,
                'preservation_strategy' => 'intelligent_truncation',
                'original_count' => 5,
                'preserved_count' => 5,
            ]);

        // Execute switch
        $result = $this->service->switchProvider($conversation, 'gemini');

        // Assertions
        $this->assertEquals('gemini', $result->provider_name);
        $this->assertEquals('Gemini Pro', $result->model_name);
        $this->assertEquals($newProvider->id, $result->ai_provider_id);
        $this->assertEquals($newModel->id, $result->ai_provider_model_id);

        // Verify event was dispatched
        Event::assertDispatched(ProviderSwitched::class, function ($event) use ($conversation) {
            return $event->conversation->id === $conversation->id &&
                   $event->fromProvider === 'openai' &&
                   $event->toProvider === 'gemini';
        });
    }

    #[Test]
    public function it_throws_exception_when_provider_not_found(): void
    {
        $conversation = AIConversation::factory()->create();

        $this->expectException(ProviderSwitchException::class);
        $this->expectExceptionMessage('Failed to switch provider');

        $this->service->switchProvider($conversation, 'nonexistent');
    }

    #[Test]
    public function it_throws_exception_when_provider_health_check_fails(): void
    {
        $conversation = AIConversation::factory()->create();

        $provider = AIProvider::factory()->create([
            'name' => 'unhealthy',
            'status' => 'active',
        ]);

        // Mock driver that fails health check
        $mockDriver = Mockery::mock(\JTD\LaravelAI\Contracts\AIProviderInterface::class);
        $mockDriver->shouldReceive('validateHealth')->andReturn(false);
        $this->driverManager->shouldReceive('driver')->with('unhealthy')->andReturn($mockDriver);

        $this->expectException(ProviderSwitchException::class);

        $this->service->switchProvider($conversation, 'unhealthy');
    }

    #[Test]
    public function it_can_switch_with_fallback(): void
    {
        $conversation = AIConversation::factory()->create([
            'provider_name' => 'openai',
        ]);

        // Create fallback providers
        $geminiProvider = AIProvider::factory()->create([
            'name' => 'gemini',
            'status' => 'active',
        ]);
        $geminiModel = AIProviderModel::factory()->create([
            'ai_provider_id' => $geminiProvider->id,
            'model_id' => 'gemini-pro',
            'name' => 'Gemini Pro',
            'status' => 'active',
        ]);

        $xaiProvider = AIProvider::factory()->create([
            'name' => 'xai',
            'status' => 'active',
        ]);
        $xaiModel = AIProviderModel::factory()->create([
            'ai_provider_id' => $xaiProvider->id,
            'model_id' => 'grok-beta',
            'name' => 'Grok Beta',
            'status' => 'active',
        ]);

        // Mock first provider fails, second succeeds
        $failingDriver = Mockery::mock(\JTD\LaravelAI\Contracts\AIProviderInterface::class);
        $failingDriver->shouldReceive('validateHealth')->andReturn(false);
        $this->driverManager->shouldReceive('driver')->with('gemini')->andReturn($failingDriver);

        $workingDriver = Mockery::mock(\JTD\LaravelAI\Contracts\AIProviderInterface::class);
        $workingDriver->shouldReceive('validateHealth')->andReturn(true);
        $this->driverManager->shouldReceive('driver')->with('xai')->andReturn($workingDriver);

        // Mock context preservation for successful switch
        $this->contextManager->shouldReceive('preserveContextForSwitch')
            ->once()
            ->andReturn([
                'messages' => [],
                'total_tokens' => 100,
                'truncated' => false,
                'preservation_strategy' => 'intelligent_truncation',
                'original_count' => 5,
                'preserved_count' => 5,
            ]);

        $providerPriority = ['gemini', 'xai'];
        $result = $this->service->switchWithFallback($conversation, $providerPriority);

        $this->assertEquals('xai', $result->provider_name);
        $this->assertEquals('Grok Beta', $result->model_name);
    }

    #[Test]
    public function it_throws_exception_when_all_fallbacks_fail(): void
    {
        $conversation = AIConversation::factory()->create();

        // Create providers that will fail
        AIProvider::factory()->create(['name' => 'provider1', 'status' => 'active']);
        AIProvider::factory()->create(['name' => 'provider2', 'status' => 'active']);

        // Mock all drivers to fail
        $failingDriver = Mockery::mock(\JTD\LaravelAI\Contracts\AIProviderInterface::class);
        $failingDriver->shouldReceive('validateHealth')->andReturn(false);
        $this->driverManager->shouldReceive('driver')->andReturn($failingDriver);

        $this->expectException(ProviderSwitchException::class);
        $this->expectExceptionMessage('All provider fallback attempts failed');

        $this->service->switchWithFallback($conversation, ['provider1', 'provider2']);
    }

    #[Test]
    public function it_can_get_available_providers(): void
    {
        $conversation = AIConversation::factory()->create([
            'provider_name' => 'openai',
        ]);

        // Create other providers
        $geminiProvider = AIProvider::factory()->create([
            'name' => 'gemini',
            'status' => 'active',
        ]);
        $geminiModel = AIProviderModel::factory()->create([
            'ai_provider_id' => $geminiProvider->id,
            'name' => 'gemini-pro',
            'status' => 'active',
        ]);

        $xaiProvider = AIProvider::factory()->create([
            'name' => 'xai',
            'status' => 'active',
        ]);
        $xaiModel = AIProviderModel::factory()->create([
            'ai_provider_id' => $xaiProvider->id,
            'name' => 'grok-beta',
            'status' => 'active',
        ]);

        $availableProviders = $this->service->getAvailableProviders($conversation);

        $this->assertCount(2, $availableProviders);

        // Should not include current provider (openai)
        $providerNames = array_column($availableProviders, 'name');
        $this->assertNotContains('openai', $providerNames);

        // Should include both gemini and xai (order doesn't matter)
        $this->assertContains('gemini', $providerNames);
        $this->assertContains('xai', $providerNames);
    }

    #[Test]
    public function it_preserves_context_when_switching(): void
    {
        $conversation = AIConversation::factory()->create([
            'provider_name' => 'openai',
        ]);

        $newProvider = AIProvider::factory()->create([
            'name' => 'gemini',
            'status' => 'active',
        ]);
        $newModel = AIProviderModel::factory()->create([
            'ai_provider_id' => $newProvider->id,
            'model_id' => 'gemini-pro',
            'name' => 'Gemini Pro',
            'status' => 'active',
            'context_length' => 8192,
        ]);

        // Mock driver
        $mockDriver = Mockery::mock(\JTD\LaravelAI\Contracts\AIProviderInterface::class);
        $mockDriver->shouldReceive('validateHealth')->andReturn(true);
        $this->driverManager->shouldReceive('driver')->with('gemini')->andReturn($mockDriver);

        // Mock context preservation
        $this->contextManager->shouldReceive('preserveContextForSwitch')
            ->once()
            ->withArgs(function ($conv, $model, $options) use ($conversation, $newModel) {
                return $conv->id === $conversation->id &&
                       $model->id === $newModel->id &&
                       is_array($options);
            })
            ->andReturn([
                'messages' => [
                    ['role' => 'user', 'content' => 'Hello'],
                    ['role' => 'assistant', 'content' => 'Hi there!'],
                ],
                'total_tokens' => 150,
                'truncated' => false,
                'preservation_strategy' => 'intelligent_truncation',
                'original_count' => 2,
                'preserved_count' => 2,
            ]);

        $result = $this->service->switchProvider($conversation, 'gemini', null, [
            'preserve_context' => true,
        ]);

        // Verify context preservation was called
        $this->contextManager->shouldHaveReceived('preserveContextForSwitch');

        // Verify metadata was updated
        $result->refresh();
        $this->assertArrayHasKey('last_context_preservation', $result->metadata);
    }

    #[Test]
    public function it_tracks_provider_switch_in_metadata(): void
    {
        $conversation = AIConversation::factory()->create([
            'provider_name' => 'openai',
            'model_name' => 'gpt-4',
        ]);

        $newProvider = AIProvider::factory()->create([
            'name' => 'gemini',
            'status' => 'active',
        ]);
        $newModel = AIProviderModel::factory()->create([
            'ai_provider_id' => $newProvider->id,
            'model_id' => 'gemini-pro',
            'name' => 'Gemini Pro',
            'status' => 'active',
        ]);

        // Mock driver
        $mockDriver = Mockery::mock(\JTD\LaravelAI\Contracts\AIProviderInterface::class);
        $mockDriver->shouldReceive('validateHealth')->andReturn(true);
        $this->driverManager->shouldReceive('driver')->with('gemini')->andReturn($mockDriver);

        // Mock context preservation
        $this->contextManager->shouldReceive('preserveContextForSwitch')
            ->andReturn([
                'messages' => [],
                'total_tokens' => 100,
                'truncated' => false,
                'preservation_strategy' => 'intelligent_truncation',
                'original_count' => 5,
                'preserved_count' => 5,
            ]);

        $result = $this->service->switchProvider($conversation, 'gemini', null, [
            'reason' => 'cost_optimization',
        ]);

        $result->refresh();
        $this->assertArrayHasKey('provider_switches', $result->metadata);

        $switches = $result->metadata['provider_switches'];
        $this->assertCount(1, $switches);
        $this->assertEquals('openai', $switches[0]['from_provider']);
        $this->assertEquals('gemini', $switches[0]['to_provider']);
        $this->assertEquals('cost_optimization', $switches[0]['reason']);
    }

    public static function switchOptionsProvider(): array
    {
        return [
            'with context preservation' => [['preserve_context' => true]],
            'without context preservation' => [['preserve_context' => false]],
            'with custom reason' => [['reason' => 'performance_optimization']],
            'with fallback reason' => [['reason' => 'fallback']],
        ];
    }

    #[Test]
    #[DataProvider('switchOptionsProvider')]
    public function it_handles_different_switch_options(array $options): void
    {
        $conversation = AIConversation::factory()->create([
            'provider_name' => 'openai',
        ]);

        $newProvider = AIProvider::factory()->create([
            'name' => 'gemini',
            'status' => 'active',
        ]);
        $newModel = AIProviderModel::factory()->create([
            'ai_provider_id' => $newProvider->id,
            'model_id' => 'gemini-pro',
            'name' => 'Gemini Pro',
            'status' => 'active',
        ]);

        // Mock driver
        $mockDriver = Mockery::mock(\JTD\LaravelAI\Contracts\AIProviderInterface::class);
        $mockDriver->shouldReceive('validateHealth')->andReturn(true);
        $this->driverManager->shouldReceive('driver')->with('gemini')->andReturn($mockDriver);

        // Mock context preservation conditionally
        if ($options['preserve_context'] ?? true) {
            $this->contextManager->shouldReceive('preserveContextForSwitch')
                ->once()
                ->andReturn([
                    'messages' => [],
                    'total_tokens' => 100,
                    'truncated' => false,
                    'preservation_strategy' => 'intelligent_truncation',
                    'original_count' => 5,
                    'preserved_count' => 5,
                ]);
        } else {
            $this->contextManager->shouldNotReceive('preserveContextForSwitch');
        }

        $result = $this->service->switchProvider($conversation, 'gemini', null, $options);

        $this->assertEquals('gemini', $result->provider_name);

        if (isset($options['reason'])) {
            $result->refresh();
            $switches = $result->metadata['provider_switches'] ?? [];
            $this->assertEquals($options['reason'], $switches[0]['reason'] ?? null);
        }
    }
}
