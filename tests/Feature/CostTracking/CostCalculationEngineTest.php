<?php

namespace JTD\LaravelAI\Tests\Feature\CostTracking;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
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
 * Cost Calculation Engine Tests
 *
 * Tests for Sprint4b Story 1: Real-time Cost Tracking with Events
 * Validates cost calculation accuracy, provider billing alignment,
 * and real-time processing via ResponseGenerated events.
 */
class CostCalculationEngineTest extends TestCase
{
    use RefreshDatabase;

    protected PricingService $pricingService;

    protected CostTrackingListener $costTrackingListener;

    protected function setUp(): void
    {
        parent::setUp();

        // Create mocked dependencies
        $driverManager = Mockery::mock(DriverManager::class);
        $pricingValidator = Mockery::mock(PricingValidator::class);
        $pricingValidator->shouldReceive('validatePricingArray')->andReturn([]);
        $pricingValidator->shouldReceive('validateModelPricing')->andReturn([]);

        $this->pricingService = new PricingService($driverManager, $pricingValidator);
        $this->costTrackingListener = new CostTrackingListener($this->pricingService);

        $this->seedPricingData();
    }

    #[Test]
    public function it_calculates_accurate_costs_for_openai_models(): void
    {
        $provider = 'openai';
        $model = 'gpt-4o-mini';
        $inputTokens = 1000;
        $outputTokens = 500;

        $costData = $this->pricingService->calculateCost($provider, $model, $inputTokens, $outputTokens);

        $this->assertIsArray($costData);
        $this->assertArrayHasKey('total_cost', $costData);
        $this->assertArrayHasKey('input_cost', $costData);
        $this->assertArrayHasKey('output_cost', $costData);
        $this->assertArrayHasKey('currency', $costData);
        $this->assertArrayHasKey('source', $costData);

        // Verify cost calculation accuracy
        $this->assertGreaterThan(0, $costData['total_cost']);
        $this->assertEquals($costData['input_cost'] + $costData['output_cost'], $costData['total_cost']);
        $this->assertEquals('USD', $costData['currency']);
        $this->assertEquals($provider, $costData['provider']);
        $this->assertEquals($model, $costData['model']);
    }

    #[Test]
    public function it_calculates_costs_for_multiple_providers(): void
    {
        $testCases = [
            ['provider' => 'openai', 'model' => 'gpt-4o-mini', 'input' => 1000, 'output' => 500],
            ['provider' => 'anthropic', 'model' => 'claude-3-haiku', 'input' => 1200, 'output' => 600],
            ['provider' => 'google', 'model' => 'gemini-2.0-flash', 'input' => 800, 'output' => 400],
        ];

        foreach ($testCases as $case) {
            $costData = $this->pricingService->calculateCost(
                $case['provider'],
                $case['model'],
                $case['input'],
                $case['output']
            );

            $this->assertIsArray($costData);
            $this->assertGreaterThan(0, $costData['total_cost']);
            $this->assertEquals($case['provider'], $costData['provider']);
            $this->assertEquals($case['model'], $costData['model']);
            $this->assertEquals($case['input'], $costData['input_tokens']);
            $this->assertEquals($case['output'], $costData['output_tokens']);
        }
    }

    #[Test]
    public function it_handles_real_time_cost_calculation_via_events(): void
    {
        Event::fake();

        $message = new AIMessage(
            role: 'user',
            content: 'Test message for cost calculation'
        );
        $message->id = 1;
        $message->conversation_id = 123;
        $message->user_id = 1;

        $tokenUsage = new TokenUsage(
            input_tokens: 1000,
            output_tokens: 500,
            totalTokens: 1500,
            totalCost: 0.0 // Will be calculated
        );

        $response = new AIResponse(
            content: 'Test response',
            tokenUsage: $tokenUsage, model: 'gpt-4o-mini', provider: 'openai',

            finishReason: 'stop'
        );

        $event = new ResponseGenerated(
            $message,
            $response,
            [],
            1.5,
            ['provider' => 'openai', 'model' => 'gpt-4o-mini']
        );

        // Process the event through cost tracking listener
        $this->costTrackingListener->handle($event);

        // Verify cost was calculated and stored
        $this->assertDatabaseHas('ai_usage_costs', [
            'user_id' => 1,
            'conversation_id' => 123,
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'input_tokens' => 1000,
            'output_tokens' => 500,
            'total_tokens' => 1500,
        ]);

        // Verify cost is greater than 0
        $costRecord = DB::table('ai_usage_costs')->where('conversation_id', 123)->first();
        $this->assertGreaterThan(0, $costRecord->total_cost);
    }

    #[Test]
    public function it_validates_cost_accuracy_against_provider_billing(): void
    {
        // Test with known OpenAI pricing (as of test creation)
        $provider = 'openai';
        $model = 'gpt-4o-mini';
        $inputTokens = 1000000; // 1M tokens
        $outputTokens = 1000000; // 1M tokens

        $costData = $this->pricingService->calculateCost($provider, $model, $inputTokens, $outputTokens);

        // Verify cost is within expected range for OpenAI pricing
        // gpt-4o-mini: ~$0.15 per 1M input tokens, ~$0.60 per 1M output tokens
        $expectedMinCost = 0.70; // $0.15 + $0.60 - tolerance
        $expectedMaxCost = 0.80; // $0.15 + $0.60 + tolerance

        $this->assertGreaterThanOrEqual($expectedMinCost, $costData['total_cost']);
        $this->assertLessThanOrEqual($expectedMaxCost, $costData['total_cost']);
    }

    #[Test]
    public function it_handles_fallback_pricing_when_database_unavailable(): void
    {
        // Temporarily disable database pricing
        DB::table('ai_provider_model_costs')->truncate();

        $provider = 'openai';
        $model = 'unknown-model';
        $inputTokens = 1000;
        $outputTokens = 500;

        $costData = $this->pricingService->calculateCost($provider, $model, $inputTokens, $outputTokens);

        $this->assertIsArray($costData);
        $this->assertGreaterThan(0, $costData['total_cost']);
        $this->assertEquals('driver_static', $costData['source']);
    }

    #[Test]
    public function it_calculates_costs_with_different_token_ratios(): void
    {
        $provider = 'openai';
        $model = 'gpt-4o-mini';

        $testCases = [
            ['input' => 2000, 'output' => 100], // High input, low output
            ['input' => 100, 'output' => 2000], // Low input, high output
            ['input' => 1000, 'output' => 1000], // Equal input/output
            ['input' => 0, 'output' => 1000], // Only output tokens
            ['input' => 1000, 'output' => 0], // Only input tokens
        ];

        foreach ($testCases as $case) {
            $costData = $this->pricingService->calculateCost(
                $provider,
                $model,
                $case['input'],
                $case['output']
            );

            $this->assertIsArray($costData);
            $this->assertGreaterThanOrEqual(0, $costData['total_cost']);
            $this->assertEquals($case['input'], $costData['input_tokens']);
            $this->assertEquals($case['output'], $costData['output_tokens']);
        }
    }

    #[Test]
    public function it_maintains_cost_calculation_performance(): void
    {
        $provider = 'openai';
        $model = 'gpt-4o-mini';
        $inputTokens = 1000;
        $outputTokens = 500;

        $startTime = microtime(true);

        // Perform multiple cost calculations
        for ($i = 0; $i < 100; $i++) {
            $this->pricingService->calculateCost($provider, $model, $inputTokens, $outputTokens);
        }

        $endTime = microtime(true);
        $totalTime = ($endTime - $startTime) * 1000; // Convert to milliseconds

        // Cost calculation should be fast (< 50ms for 100 calculations)
        $this->assertLessThan(50, $totalTime, 'Cost calculation performance is too slow');
    }

    protected function seedPricingData(): void
    {
        // Use PricingService to properly seed pricing data with correct schema
        $pricingData = [
            [
                'provider' => 'openai',
                'model' => 'gpt-4o-mini',
                'pricing' => [
                    'input' => 0.00015,
                    'output' => 0.0006,
                    'unit' => \JTD\LaravelAI\Enums\PricingUnit::PER_1K_TOKENS,
                    'currency' => 'USD',
                    'billing_model' => \JTD\LaravelAI\Enums\BillingModel::PAY_PER_USE,
                    'effective_date' => now(),
                ],
            ],
            [
                'provider' => 'anthropic',
                'model' => 'claude-3-haiku',
                'pricing' => [
                    'input' => 0.00025,
                    'output' => 0.00125,
                    'unit' => \JTD\LaravelAI\Enums\PricingUnit::PER_1K_TOKENS,
                    'currency' => 'USD',
                    'billing_model' => \JTD\LaravelAI\Enums\BillingModel::PAY_PER_USE,
                    'effective_date' => now(),
                ],
            ],
            [
                'provider' => 'google',
                'model' => 'gemini-2.0-flash',
                'pricing' => [
                    'input' => 0.000075,
                    'output' => 0.0003,
                    'unit' => \JTD\LaravelAI\Enums\PricingUnit::PER_1K_TOKENS,
                    'currency' => 'USD',
                    'billing_model' => \JTD\LaravelAI\Enums\BillingModel::PAY_PER_USE,
                    'effective_date' => now(),
                ],
            ],
        ];

        foreach ($pricingData as $data) {
            $this->pricingService->storePricingToDatabase(
                $data['provider'],
                $data['model'],
                $data['pricing']
            );
        }
    }
}
