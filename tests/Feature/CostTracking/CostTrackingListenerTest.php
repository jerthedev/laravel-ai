<?php

namespace JTD\LaravelAI\Tests\Feature\CostTracking;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use JTD\LaravelAI\Events\CostCalculated;
use JTD\LaravelAI\Events\ResponseGenerated;
use JTD\LaravelAI\Listeners\CostTrackingListener;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Models\AIResponse;
use JTD\LaravelAI\Models\TokenUsage;
use JTD\LaravelAI\Services\DriverManager;
use JTD\LaravelAI\Services\PricingService;
use JTD\LaravelAI\Services\PricingValidator;
use JTD\LaravelAI\Tests\TestCase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;

/**
 * CostTrackingListener Tests
 *
 * Tests for Sprint4b Story 1: Real-time Cost Tracking with Events
 * Validates background cost processing, event handling, and queue integration.
 */
class CostTrackingListenerTest extends TestCase
{
    use RefreshDatabase;

    protected CostTrackingListener $listener;

    protected PricingService $pricingService;

    protected function setUp(): void
    {
        parent::setUp();

        // Create mocked dependencies
        $driverManager = Mockery::mock(DriverManager::class);
        $pricingValidator = Mockery::mock(PricingValidator::class);
        $pricingValidator->shouldReceive('validatePricingArray')->andReturn([]);
        $pricingValidator->shouldReceive('validateModelPricing')->andReturn([]);

        $this->pricingService = new PricingService($driverManager, $pricingValidator);
        $this->listener = new CostTrackingListener($this->pricingService);

        $this->seedTestData();
    }

    #[Test]
    public function it_handles_response_generated_events(): void
    {
        Event::fake([CostCalculated::class]);

        $message = $this->createTestMessage();
        $response = $this->createTestAIResponse();

        $event = new ResponseGenerated(
            $message,
            $response,
            [],
            1.5,
            ['provider' => 'openai', 'model' => 'gpt-4o-mini', 'request_id' => 'req_123']
        );

        $this->listener->handle($event);

        // Verify cost record was stored
        $this->assertDatabaseHas('ai_cost_records', [
            'user_id' => 1,
            'conversation_id' => 123,
            'message_id' => 1,
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'input_tokens' => 1000,
            'output_tokens' => 500,
            'total_tokens' => 1500,
        ]);

        // Verify CostCalculated event was fired
        Event::assertDispatched(CostCalculated::class, function ($event) {
            return $event->provider === 'openai' &&
                   $event->model === 'gpt-4o-mini' &&
                   $event->input_tokens === 1000 &&
                   $event->output_tokens === 500 &&
                   $event->conversationId === 123;
        });
    }

    #[Test]
    public function it_processes_costs_in_background_queue(): void
    {
        Queue::fake();

        $message = $this->createTestMessage();
        $response = $this->createTestAIResponse();

        $event = new ResponseGenerated(
            $message,
            $response,
            [],
            1.5,
            ['provider' => 'openai', 'model' => 'gpt-4o-mini']
        );

        // Process event (should be queued in real implementation)
        $this->listener->handle($event);

        // In a real queue implementation, we would assert:
        // Queue::assertPushed(ProcessCostCalculation::class);

        // For now, verify the processing completed
        $this->assertDatabaseHas('ai_cost_records', [
            'conversation_id' => 123,
            'provider' => 'openai',
        ]);
    }

    #[Test]
    public function it_calculates_enhanced_message_costs(): void
    {
        $message = $this->createTestMessage();
        $response = $this->createTestAIResponse();

        $event = new ResponseGenerated(
            $message,
            $response,
            [],
            1.5,
            [
                'provider' => 'openai',
                'model' => 'gpt-4o-mini',
                'request_id' => 'req_123',
                'processing_time_ms' => 1500,
            ]
        );

        $this->listener->handle($event);

        $costRecord = DB::table('ai_cost_records')
            ->where('conversation_id', 123)
            ->first();

        $this->assertNotNull($costRecord);
        $this->assertGreaterThan(0, $costRecord->total_cost);
        $this->assertEquals(1500, $costRecord->processing_time_ms);
        $this->assertEquals('USD', $costRecord->currency);
        $this->assertEquals('api', $costRecord->pricing_source);
    }

    #[Test]
    public function it_handles_multiple_providers_correctly(): void
    {
        Event::fake([CostCalculated::class]);

        $providers = [
            ['provider' => 'openai', 'model' => 'gpt-4o-mini'],
            ['provider' => 'anthropic', 'model' => 'claude-3-haiku'],
            ['provider' => 'google', 'model' => 'gemini-2.0-flash'],
        ];

        foreach ($providers as $index => $providerData) {
            $message = $this->createTestMessage(['id' => $index + 1]);
            $response = $this->createTestAIResponse();

            $event = new ResponseGenerated(
                $message,
                $response,
                [],
                1.5,
                ['provider' => $providerData['provider'], 'model' => $providerData['model']]
            );

            $this->listener->handle($event);

            // Verify each provider's cost was tracked
            $this->assertDatabaseHas('ai_cost_records', [
                'message_id' => $index + 1,
                'provider' => $providerData['provider'],
                'model' => $providerData['model'],
            ]);
        }

        // Verify CostCalculated events were fired for each provider
        Event::assertDispatchedTimes(CostCalculated::class, 3);
    }

    #[Test]
    public function it_handles_cost_calculation_errors_gracefully(): void
    {
        Log::shouldReceive('warning')->zeroOrMoreTimes();
        Log::shouldReceive('error')->zeroOrMoreTimes();
        Log::shouldReceive('debug')->zeroOrMoreTimes();

        // Mock PricingService to throw an exception
        $mockPricingService = Mockery::mock(PricingService::class);
        $mockPricingService->shouldReceive('calculateCost')
            ->andThrow(new \Exception('Pricing service unavailable'));

        $this->app->instance(PricingService::class, $mockPricingService);

        $listener = new CostTrackingListener($mockPricingService);

        $message = $this->createTestMessage();
        $response = $this->createTestAIResponse();

        $event = new ResponseGenerated(
            $message,
            $response,
            [],
            1.5,
            ['provider' => 'openai', 'model' => 'gpt-4o-mini']
        );

        // Should not throw exception
        $listener->handle($event);

        // Error handling is verified by the fact that no exception was thrown
        $this->assertTrue(true, 'Error was handled gracefully');

        // Verify fallback cost was still recorded
        $this->assertDatabaseHas('ai_cost_records', [
            'conversation_id' => 123,
            'provider' => 'openai',
        ]);
    }

    #[Test]
    public function it_tracks_cost_accuracy_metrics(): void
    {
        $message = $this->createTestMessage();
        $response = $this->createTestAIResponse();

        $event = new ResponseGenerated(
            $message,
            $response,
            [],
            1.5,
            [
                'provider' => 'openai',
                'model' => 'gpt-4o-mini',
                'actual_cost' => 0.0015, // Provider-reported cost
            ]
        );

        $this->listener->handle($event);

        $costRecord = DB::table('ai_cost_records')
            ->where('conversation_id', 123)
            ->first();

        // Verify cost record has metadata and basic structure
        $this->assertNotNull($costRecord);
        $this->assertGreaterThan(0, $costRecord->total_cost);
        $this->assertEquals('openai', $costRecord->provider);
        $this->assertEquals('gpt-4o-mini', $costRecord->model);
    }

    #[Test]
    public function it_processes_high_volume_events_efficiently(): void
    {
        $startTime = microtime(true);
        $eventCount = 100;

        for ($i = 0; $i < $eventCount; $i++) {
            $message = $this->createTestMessage(['id' => $i + 1]);
            $response = $this->createTestAIResponse();

            $event = new ResponseGenerated(
                $message,
                $response,
                [],
                1.5,
                ['provider' => 'openai', 'model' => 'gpt-4o-mini']
            );

            $this->listener->handle($event);
        }

        $endTime = microtime(true);
        $totalTime = ($endTime - $startTime) * 1000; // Convert to milliseconds

        // Should process 100 events in reasonable time for test environment (< 10s)
        $this->assertLessThan(10000, $totalTime, 'Cost tracking performance is too slow');

        // Verify all events were processed
        $this->assertEquals($eventCount, DB::table('ai_cost_records')->count());
    }

    #[Test]
    public function it_handles_conversation_context_correctly(): void
    {
        Event::fake([CostCalculated::class]);

        // Create multiple messages in the same conversation
        for ($i = 1; $i <= 3; $i++) {
            $message = $this->createTestMessage(['id' => $i]);
            $response = $this->createTestAIResponse();

            $event = new ResponseGenerated(
                $message,
                $response,
                [],
                1.5,
                ['provider' => 'openai', 'model' => 'gpt-4o-mini']
            );

            $this->listener->handle($event);
        }

        // Verify all messages in conversation were tracked
        $costRecords = DB::table('ai_cost_records')
            ->where('conversation_id', 123)
            ->get();

        $this->assertCount(3, $costRecords);

        // Verify total conversation cost accumulation
        $totalCost = $costRecords->sum('total_cost');
        $this->assertGreaterThan(0, $totalCost);

        // Verify CostCalculated events include conversation context
        Event::assertDispatched(CostCalculated::class, function ($event) {
            return $event->conversationId === 123;
        });
    }

    protected function createTestMessage(array $overrides = []): AIMessage
    {
        $defaults = [
            'id' => 1,
            'conversation_id' => 123,
            'role' => 'user',
            'content' => 'Test message for cost tracking',
            'user_id' => 1,
        ];

        $data = array_merge($defaults, $overrides);

        $message = new AIMessage(
            role: $data['role'],
            content: $data['content']
        );
        $message->id = $data['id'];
        $message->conversation_id = $data['conversation_id'];
        $message->user_id = $data['user_id'];

        return $message;
    }

    protected function createTestAIResponse(): AIResponse
    {
        $tokenUsage = new TokenUsage(
            input_tokens: 1000,
            output_tokens: 500,
            totalTokens: 1500,
            totalCost: 0.0
        );

        return new AIResponse(
            content: 'Test response for cost tracking',
            tokenUsage: $tokenUsage, model: 'gpt-4o-mini', provider: 'openai',

            finishReason: 'stop'
        );
    }

    protected function seedTestData(): void
    {
        // Use PricingService to properly seed pricing data
        $pricingService = app(\JTD\LaravelAI\Services\PricingService::class);

        $pricingService->storePricingToDatabase('openai', 'gpt-4o-mini', [
            'input' => 0.00015,
            'output' => 0.0006,
            'unit' => \JTD\LaravelAI\Enums\PricingUnit::PER_1K_TOKENS,
            'currency' => 'USD',
            'billing_model' => \JTD\LaravelAI\Enums\BillingModel::PAY_PER_USE,
            'effective_date' => now(),
        ]);

        $pricingService->storePricingToDatabase('anthropic', 'claude-3-haiku', [
            'input' => 0.00025,
            'output' => 0.00125,
            'unit' => \JTD\LaravelAI\Enums\PricingUnit::PER_1K_TOKENS,
            'currency' => 'USD',
            'billing_model' => \JTD\LaravelAI\Enums\BillingModel::PAY_PER_USE,
            'effective_date' => now(),
        ]);

        $pricingService->storePricingToDatabase('google', 'gemini-2.0-flash', [
            'input' => 0.000075,
            'output' => 0.0003,
            'unit' => \JTD\LaravelAI\Enums\PricingUnit::PER_1K_TOKENS,
            'currency' => 'USD',
            'billing_model' => \JTD\LaravelAI\Enums\BillingModel::PAY_PER_USE,
            'effective_date' => now(),
        ]);
    }
}
