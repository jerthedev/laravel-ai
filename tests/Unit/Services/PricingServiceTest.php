<?php

namespace Tests\Unit\Services;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use JTD\LaravelAI\Enums\BillingModel;
use JTD\LaravelAI\Enums\PricingUnit;
use JTD\LaravelAI\Services\DriverManager;
use JTD\LaravelAI\Services\PricingService;
use JTD\LaravelAI\Services\PricingValidator;
use Tests\TestCase;

class PricingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected PricingService $pricingService;

    protected DriverManager $driverManager;

    protected PricingValidator $pricingValidator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->driverManager = $this->createMock(DriverManager::class);
        $this->pricingValidator = $this->createMock(PricingValidator::class);

        $this->pricingService = new PricingService(
            $this->driverManager,
            $this->pricingValidator
        );
    }

    public function test_get_model_pricing_from_database()
    {
        // Setup database data
        $this->setupDatabasePricing();

        $pricing = $this->pricingService->getModelPricing('openai', 'gpt-4o');

        $this->assertArrayHasKey('input', $pricing);
        $this->assertArrayHasKey('output', $pricing);
        $this->assertEquals('database', $pricing['source']);
        $this->assertEquals('USD', $pricing['currency']);
    }

    public function test_get_model_pricing_fallback_to_driver()
    {
        // No database data, should fallback to driver
        $mockPricingClass = $this->createMockPricingClass();

        $pricing = $this->pricingService->getModelPricing('openai', 'gpt-4o');

        $this->assertArrayHasKey('input', $pricing);
        $this->assertArrayHasKey('output', $pricing);
    }

    public function test_get_model_pricing_universal_fallback()
    {
        // No database data, no driver, should use universal fallback
        $pricing = $this->pricingService->getModelPricing('unknown', 'unknown-model');

        $this->assertArrayHasKey('input', $pricing);
        $this->assertArrayHasKey('output', $pricing);
        $this->assertEquals('universal_fallback', $pricing['source']);
        $this->assertEquals(PricingUnit::PER_1K_TOKENS, $pricing['unit']);
    }

    public function test_calculate_cost_with_database_pricing()
    {
        $this->setupDatabasePricing();

        $cost = $this->pricingService->calculateCost('openai', 'gpt-4o', 1000, 500);

        $this->assertArrayHasKey('total_cost', $cost);
        $this->assertArrayHasKey('input_cost', $cost);
        $this->assertArrayHasKey('output_cost', $cost);
        $this->assertEquals('openai', $cost['provider']);
        $this->assertEquals('gpt-4o', $cost['model']);
        $this->assertEquals(1000, $cost['input_tokens']);
        $this->assertEquals(500, $cost['output_tokens']);
    }

    public function test_calculate_cost_with_different_units()
    {
        // Test PER_1M_TOKENS
        $this->setupDatabasePricing('gpt-4o', PricingUnit::PER_1M_TOKENS, 5.0, 15.0);

        $cost = $this->pricingService->calculateCost('openai', 'gpt-4o', 1000000, 500000);

        $this->assertEquals(5.0, $cost['input_cost']);
        $this->assertEquals(7.5, $cost['output_cost']);
        $this->assertEquals(12.5, $cost['total_cost']);
    }

    public function test_store_pricing_to_database()
    {
        $this->pricingValidator
            ->expects($this->once())
            ->method('validateModelPricing')
            ->willReturn([]);

        $pricing = [
            'input' => 0.01,
            'output' => 0.03,
            'unit' => PricingUnit::PER_1K_TOKENS,
            'currency' => 'USD',
            'billing_model' => BillingModel::PAY_PER_USE,
            'effective_date' => '2024-01-01',
        ];

        $result = $this->pricingService->storePricingToDatabase('openai', 'gpt-4o', $pricing);

        $this->assertTrue($result);

        // Verify data was stored
        $this->assertDatabaseHas('ai_providers', ['name' => 'openai']);
        $this->assertDatabaseHas('ai_provider_models', ['name' => 'gpt-4o']);
        $this->assertDatabaseHas('ai_provider_model_costs', [
            'cost_type' => 'input',
            'cost_per_unit' => 0.01,
            'is_current' => true,
        ]);
    }

    public function test_store_pricing_validation_failure()
    {
        $this->pricingValidator
            ->expects($this->once())
            ->method('validateModelPricing')
            ->willReturn(['Invalid pricing data']);

        $pricing = ['invalid' => 'data'];

        $result = $this->pricingService->storePricingToDatabase('openai', 'gpt-4o', $pricing);

        $this->assertFalse($result);
    }

    public function test_normalize_pricing()
    {
        $pricing = [
            'input' => 1.0,
            'output' => 2.0,
            'unit' => PricingUnit::PER_1K_TOKENS,
            'currency' => 'USD',
        ];

        $normalized = $this->pricingService->normalizePricing($pricing, PricingUnit::PER_1M_TOKENS);

        $this->assertEquals(PricingUnit::PER_1M_TOKENS, $normalized['unit']);
        $this->assertEquals(1000.0, $normalized['input']); // 1.0 * 1000
        $this->assertEquals(2000.0, $normalized['output']); // 2.0 * 1000
    }

    public function test_compare_pricing()
    {
        $this->setupDatabasePricing('gpt-4o', PricingUnit::PER_1K_TOKENS, 0.01, 0.03);
        $this->setupDatabasePricing('gpt-3.5-turbo', PricingUnit::PER_1K_TOKENS, 0.001, 0.002);

        $comparisons = [
            ['provider' => 'openai', 'model' => 'gpt-4o'],
            ['provider' => 'openai', 'model' => 'gpt-3.5-turbo'],
        ];

        $results = $this->pricingService->comparePricing($comparisons, 1000, 1000);

        $this->assertCount(2, $results);
        $this->assertTrue($results[0]['total_cost'] < $results[1]['total_cost']); // Should be sorted by cost
    }

    public function test_cache_management()
    {
        Cache::shouldReceive('forget')
            ->once()
            ->with('pricing:db:openai:gpt-4o');

        $this->pricingService->clearCache('openai', 'gpt-4o');
    }

    public function test_warm_cache()
    {
        $this->setupDatabasePricing();

        Cache::shouldReceive('remember')
            ->times(4) // 4 common combinations
            ->andReturn(['mocked' => 'pricing']);

        $this->pricingService->warmCache();
    }

    protected function setupDatabasePricing(
        string $model = 'gpt-4o',
        PricingUnit $unit = PricingUnit::PER_1K_TOKENS,
        float $inputCost = 0.01,
        float $outputCost = 0.03
    ): void {
        // Create provider
        $providerId = DB::table('ai_providers')->insertGetId([
            'name' => 'openai',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create model
        $modelId = DB::table('ai_provider_models')->insertGetId([
            'ai_provider_id' => $providerId,
            'name' => $model,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create input cost
        DB::table('ai_provider_model_costs')->insert([
            'ai_provider_model_id' => $modelId,
            'cost_type' => 'input',
            'cost_per_unit' => $inputCost,
            'unit_type' => $unit->value,
            'currency' => 'USD',
            'billing_model' => BillingModel::PAY_PER_USE->value,
            'effective_from' => '2024-01-01',
            'is_current' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create output cost
        DB::table('ai_provider_model_costs')->insert([
            'ai_provider_model_id' => $modelId,
            'cost_type' => 'output',
            'cost_per_unit' => $outputCost,
            'unit_type' => $unit->value,
            'currency' => 'USD',
            'billing_model' => BillingModel::PAY_PER_USE->value,
            'effective_from' => '2024-01-01',
            'is_current' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function createMockPricingClass(): object
    {
        return new class
        {
            public function getModelPricing(string $model): array
            {
                return [
                    'input' => 0.01,
                    'output' => 0.03,
                    'unit' => PricingUnit::PER_1K_TOKENS,
                    'currency' => 'USD',
                    'billing_model' => BillingModel::PAY_PER_USE,
                    'source' => 'driver_static',
                ];
            }
        };
    }
}
