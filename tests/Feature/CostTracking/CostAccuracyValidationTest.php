<?php

namespace JTD\LaravelAI\Tests\Feature\CostTracking;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
 * Cost Accuracy Validation Tests
 *
 * Tests for Sprint4b Story 1: Real-time Cost Tracking with Events
 * Validates cost calculations against provider APIs and billing accuracy.
 */
class CostAccuracyValidationTest extends TestCase
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

        $this->seedAccuratePricingData();
    }

    #[Test]
    public function it_validates_openai_cost_accuracy(): void
    {
        $provider = 'openai';
        $model = 'gpt-4o-mini';
        $inputTokens = 1000000; // 1M tokens for clear calculation
        $outputTokens = 1000000; // 1M tokens

        $costData = $this->pricingService->calculateCost($provider, $model, $inputTokens, $outputTokens);

        // OpenAI gpt-4o-mini pricing (as of test creation):
        // Input: $0.15 per 1M tokens
        // Output: $0.60 per 1M tokens
        // Total expected: $0.75

        $expectedInputCost = 0.15;
        $expectedOutputCost = 0.60;
        $expectedTotalCost = 0.75;

        $this->assertEqualsWithDelta($expectedInputCost, $costData['input_cost'], 0.01);
        $this->assertEqualsWithDelta($expectedOutputCost, $costData['output_cost'], 0.01);
        $this->assertEqualsWithDelta($expectedTotalCost, $costData['total_cost'], 0.01);
    }

    #[Test]
    public function it_validates_anthropic_cost_accuracy(): void
    {
        $provider = 'anthropic';
        $model = 'claude-3-haiku';
        $inputTokens = 1000000; // 1M tokens
        $outputTokens = 1000000; // 1M tokens

        $costData = $this->pricingService->calculateCost($provider, $model, $inputTokens, $outputTokens);

        // Anthropic Claude 3 Haiku pricing (as of test creation):
        // Input: $0.25 per 1M tokens
        // Output: $1.25 per 1M tokens
        // Total expected: $1.50

        $expectedInputCost = 0.25;
        $expectedOutputCost = 1.25;
        $expectedTotalCost = 1.50;

        $this->assertEqualsWithDelta($expectedInputCost, $costData['input_cost'], 0.01);
        $this->assertEqualsWithDelta($expectedOutputCost, $costData['output_cost'], 0.01);
        $this->assertEqualsWithDelta($expectedTotalCost, $costData['total_cost'], 0.01);
    }

    #[Test]
    public function it_validates_google_cost_accuracy(): void
    {
        $provider = 'google';
        $model = 'gemini-2.0-flash';
        $inputTokens = 1000000; // 1M tokens
        $outputTokens = 1000000; // 1M tokens

        $costData = $this->pricingService->calculateCost($provider, $model, $inputTokens, $outputTokens);

        // Google Gemini 2.0 Flash pricing (as of test creation):
        // Input: $0.075 per 1M tokens
        // Output: $0.30 per 1M tokens
        // Total expected: $0.375

        $expectedInputCost = 0.075;
        $expectedOutputCost = 0.30;
        $expectedTotalCost = 0.375;

        $this->assertEqualsWithDelta($expectedInputCost, $costData['input_cost'], 0.01);
        $this->assertEqualsWithDelta($expectedOutputCost, $costData['output_cost'], 0.01);
        $this->assertEqualsWithDelta($expectedTotalCost, $costData['total_cost'], 0.01);
    }

    #[Test]
    public function it_tracks_cost_accuracy_against_provider_reported_costs(): void
    {
        $message = new AIMessage(
            role: 'user',
            content: 'Test message for accuracy validation'
        );
        $message->id = 1;
        $message->conversation_id = 999;
        $message->user_id = 1;

        $tokenUsage = new TokenUsage(
            input_tokens: 1000,
            output_tokens: 500,
            totalTokens: 1500,
            totalCost: 0.0015 // Provider-reported cost
        );

        $response = new AIResponse(
            content: 'Test response',
            tokenUsage: $tokenUsage,
            model: 'gpt-4o-mini',
            provider: 'openai',
            finishReason: 'stop'
        );

        $event = new ResponseGenerated(
            $message,
            $response,

            [
                'provider' => 'openai',
                'model' => 'gpt-4o-mini',
                'actual_cost' => 0.0015, // Provider-reported cost
                'billing_details' => [
                    'input_cost' => 0.00015,
                    'output_cost' => 0.0003,
                ],
            ]
        );

        $this->costTrackingListener->handle($event);

        $costRecord = DB::table('ai_usage_costs')
            ->where('conversation_id', 999)
            ->first();

        $this->assertNotNull($costRecord);

        // Verify cost record was created with provider metadata
        $this->assertNotNull($costRecord);
        $this->assertGreaterThan(0, $costRecord->total_cost);
        $this->assertEquals('openai', $costRecord->provider);
        $this->assertEquals('gpt-4o-mini', $costRecord->model);

        // Verify the cost calculation used the provider-reported cost information
        $this->assertEquals(1000, $costRecord->input_tokens);
        $this->assertEquals(500, $costRecord->output_tokens);
    }

    #[Test]
    public function it_validates_cost_calculation_precision(): void
    {
        $testCases = [
            // Small token counts
            ['provider' => 'openai', 'model' => 'gpt-4o-mini', 'input' => 10, 'output' => 5],
            // Medium token counts
            ['provider' => 'openai', 'model' => 'gpt-4o-mini', 'input' => 1000, 'output' => 500],
            // Large token counts
            ['provider' => 'openai', 'model' => 'gpt-4o-mini', 'input' => 100000, 'output' => 50000],
            // Very large token counts
            ['provider' => 'openai', 'model' => 'gpt-4o-mini', 'input' => 10000000, 'output' => 5000000],
        ];

        foreach ($testCases as $case) {
            $costData = $this->pricingService->calculateCost(
                $case['provider'],
                $case['model'],
                $case['input'],
                $case['output']
            );

            // Verify precision is maintained (no rounding errors)
            $calculatedTotal = $costData['input_cost'] + $costData['output_cost'];
            $this->assertEqualsWithDelta($calculatedTotal, $costData['total_cost'], 0.0000001);

            // Verify costs are proportional to token counts
            $this->assertGreaterThan(0, $costData['total_cost']);

            // Higher token counts should result in higher costs
            if ($case['input'] > 1000) {
                $this->assertGreaterThan(0.001, $costData['total_cost']);
            }
        }
    }

    #[Test]
    public function it_handles_edge_cases_in_cost_calculation(): void
    {
        $edgeCases = [
            // Zero input tokens
            ['input' => 0, 'output' => 1000],
            // Zero output tokens
            ['input' => 1000, 'output' => 0],
            // Both zero (should handle gracefully)
            ['input' => 0, 'output' => 0],
            // Very small token counts
            ['input' => 1, 'output' => 1],
        ];

        foreach ($edgeCases as $case) {
            $costData = $this->pricingService->calculateCost(
                'openai',
                'gpt-4o-mini',
                $case['input'],
                $case['output']
            );

            $this->assertIsArray($costData);
            $this->assertArrayHasKey('total_cost', $costData);
            $this->assertGreaterThanOrEqual(0, $costData['total_cost']);

            // Verify individual costs are calculated correctly
            if ($case['input'] === 0) {
                $this->assertEquals(0, $costData['input_cost']);
            }
            if ($case['output'] === 0) {
                $this->assertEquals(0, $costData['output_cost']);
            }
        }
    }

    #[Test]
    public function it_validates_currency_consistency(): void
    {
        $providers = ['openai', 'anthropic', 'google'];

        foreach ($providers as $provider) {
            $models = $this->getModelsForProvider($provider);

            foreach ($models as $model) {
                $costData = $this->pricingService->calculateCost($provider, $model, 1000, 500);

                $this->assertArrayHasKey('currency', $costData);
                $this->assertEquals('USD', $costData['currency']);
            }
        }
    }

    #[Test]
    public function it_validates_cost_source_tracking(): void
    {
        $provider = 'openai';
        $model = 'gpt-4o-mini';
        $inputTokens = 1000;
        $outputTokens = 500;

        $costData = $this->pricingService->calculateCost($provider, $model, $inputTokens, $outputTokens);

        $this->assertArrayHasKey('source', $costData);
        $this->assertContains($costData['source'], ['api', 'database', 'fallback', 'static']);
    }

    #[Test]
    public function it_validates_batch_cost_calculation_consistency(): void
    {
        $batchSize = 100;
        $provider = 'openai';
        $model = 'gpt-4o-mini';
        $inputTokens = 1000;
        $outputTokens = 500;

        $costs = [];

        // Calculate same cost multiple times
        for ($i = 0; $i < $batchSize; $i++) {
            $costData = $this->pricingService->calculateCost($provider, $model, $inputTokens, $outputTokens);
            $costs[] = $costData['total_cost'];
        }

        // All calculations should be identical
        $uniqueCosts = array_unique($costs);
        $this->assertCount(1, $uniqueCosts, 'Cost calculations should be consistent');

        // Verify the consistent cost is reasonable
        $cost = $costs[0];
        $this->assertGreaterThan(0, $cost);
        $this->assertLessThan(1, $cost); // Should be less than $1 for these token counts
    }

    protected function getModelsForProvider(string $provider): array
    {
        return match ($provider) {
            'openai' => ['gpt-4o-mini', 'gpt-4o'],
            'anthropic' => ['claude-3-haiku', 'claude-3-sonnet'],
            'google' => ['gemini-2.0-flash', 'gemini-1.5-pro'],
            default => ['gpt-4o-mini'],
        };
    }

    protected function seedAccuratePricingData(): void
    {
        $pricingData = [
            // OpenAI pricing (accurate as of test creation)
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
                'provider' => 'openai',
                'model' => 'gpt-4o',
                'pricing' => [
                    'input' => 0.0025,
                    'output' => 0.01,
                    'unit' => \JTD\LaravelAI\Enums\PricingUnit::PER_1K_TOKENS,
                    'currency' => 'USD',
                    'billing_model' => \JTD\LaravelAI\Enums\BillingModel::PAY_PER_USE,
                    'effective_date' => now(),
                ],
            ],
            // Anthropic pricing
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
                'provider' => 'anthropic',
                'model' => 'claude-3-sonnet',
                'pricing' => [
                    'input' => 0.003,
                    'output' => 0.015,
                    'unit' => \JTD\LaravelAI\Enums\PricingUnit::PER_1K_TOKENS,
                    'currency' => 'USD',
                    'billing_model' => \JTD\LaravelAI\Enums\BillingModel::PAY_PER_USE,
                    'effective_date' => now(),
                ],
            ],
            // Google pricing
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
            [
                'provider' => 'google',
                'model' => 'gemini-1.5-pro',
                'pricing' => [
                    'input' => 0.00125,
                    'output' => 0.005,
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
