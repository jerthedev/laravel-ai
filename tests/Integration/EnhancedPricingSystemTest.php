<?php

namespace Tests\Integration;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use JTD\LaravelAI\Services\PricingService;
use JTD\LaravelAI\Services\PricingValidator;
use JTD\LaravelAI\Services\DriverManager;
use JTD\LaravelAI\Enums\PricingUnit;
use JTD\LaravelAI\Enums\BillingModel;

class EnhancedPricingSystemTest extends TestCase
{
    use RefreshDatabase;

    protected PricingService $pricingService;
    protected PricingValidator $pricingValidator;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->pricingService = app(PricingService::class);
        $this->pricingValidator = app(PricingValidator::class);
    }

    public function test_complete_pricing_workflow()
    {
        // 1. Store pricing to database
        $pricing = [
            'input' => 0.0025,
            'output' => 0.01,
            'unit' => PricingUnit::PER_1K_TOKENS,
            'currency' => 'USD',
            'billing_model' => BillingModel::PAY_PER_USE,
            'effective_date' => '2024-01-01',
        ];

        $stored = $this->pricingService->storePricingToDatabase('openai', 'gpt-4o', $pricing);
        $this->assertTrue($stored);

        // 2. Retrieve pricing from database
        $retrievedPricing = $this->pricingService->getModelPricing('openai', 'gpt-4o');
        
        $this->assertEquals('database', $retrievedPricing['source']);
        $this->assertEquals(0.0025, $retrievedPricing['input']);
        $this->assertEquals(0.01, $retrievedPricing['output']);
        $this->assertEquals(PricingUnit::PER_1K_TOKENS, $retrievedPricing['unit']);

        // 3. Calculate cost using database pricing
        $cost = $this->pricingService->calculateCost('openai', 'gpt-4o', 1000, 500);
        
        $this->assertEquals(0.0025, $cost['input_cost']); // 1000/1000 * 0.0025
        $this->assertEquals(0.005, $cost['output_cost']); // 500/1000 * 0.01
        $this->assertEquals(0.0075, $cost['total_cost']);
        $this->assertEquals('database', $cost['source']);

        // 4. Test cache functionality
        Cache::flush();
        
        // First call should hit database
        $pricing1 = $this->pricingService->getModelPricing('openai', 'gpt-4o');
        
        // Second call should hit cache
        $pricing2 = $this->pricingService->getModelPricing('openai', 'gpt-4o');
        
        $this->assertEquals($pricing1, $pricing2);
    }

    public function test_fallback_chain()
    {
        // Test 1: No database data, should fallback to driver static
        $pricing = $this->pricingService->getModelPricing('openai', 'gpt-4o-mini');
        
        // Should get pricing from driver or universal fallback
        $this->assertArrayHasKey('input', $pricing);
        $this->assertArrayHasKey('output', $pricing);
        $this->assertContains($pricing['source'], ['driver_static', 'universal_fallback']);

        // Test 2: Unknown provider, should use universal fallback
        $unknownPricing = $this->pricingService->getModelPricing('unknown', 'unknown-model');
        
        $this->assertEquals('universal_fallback', $unknownPricing['source']);
        $this->assertEquals(PricingUnit::PER_1K_TOKENS, $unknownPricing['unit']);
        $this->assertEquals(0.00001, $unknownPricing['input']);
        $this->assertEquals(0.00002, $unknownPricing['output']);
    }

    public function test_unit_normalization()
    {
        // Store pricing in PER_1K_TOKENS
        $pricing1k = [
            'input' => 1.0,
            'output' => 2.0,
            'unit' => PricingUnit::PER_1K_TOKENS,
            'currency' => 'USD',
            'billing_model' => BillingModel::PAY_PER_USE,
        ];

        $this->pricingService->storePricingToDatabase('test', 'model-1k', $pricing1k);

        // Normalize to PER_1M_TOKENS
        $retrieved = $this->pricingService->getModelPricing('test', 'model-1k');
        $normalized = $this->pricingService->normalizePricing($retrieved, PricingUnit::PER_1M_TOKENS);

        $this->assertEquals(PricingUnit::PER_1M_TOKENS, $normalized['unit']);
        $this->assertEquals(1000.0, $normalized['input']); // 1.0 * 1000
        $this->assertEquals(2000.0, $normalized['output']); // 2.0 * 1000
    }

    public function test_pricing_comparison()
    {
        // Store pricing for multiple models
        $models = [
            'cheap-model' => ['input' => 0.001, 'output' => 0.002],
            'expensive-model' => ['input' => 0.01, 'output' => 0.03],
            'mid-model' => ['input' => 0.005, 'output' => 0.015],
        ];

        foreach ($models as $model => $costs) {
            $pricing = array_merge($costs, [
                'unit' => PricingUnit::PER_1K_TOKENS,
                'currency' => 'USD',
                'billing_model' => BillingModel::PAY_PER_USE,
            ]);
            
            $this->pricingService->storePricingToDatabase('test', $model, $pricing);
        }

        // Compare pricing
        $comparisons = [
            ['provider' => 'test', 'model' => 'cheap-model'],
            ['provider' => 'test', 'model' => 'expensive-model'],
            ['provider' => 'test', 'model' => 'mid-model'],
        ];

        $results = $this->pricingService->comparePricing($comparisons, 1000, 1000);

        // Should be sorted by total cost (cheapest first)
        $this->assertEquals('cheap-model', $results[0]['model']);
        $this->assertEquals('mid-model', $results[1]['model']);
        $this->assertEquals('expensive-model', $results[2]['model']);

        // Verify costs
        $this->assertEquals(0.003, $results[0]['total_cost']); // 0.001 + 0.002
        $this->assertEquals(0.02, $results[1]['total_cost']); // 0.005 + 0.015
        $this->assertEquals(0.04, $results[2]['total_cost']); // 0.01 + 0.03
    }

    public function test_validation_integration()
    {
        // Test valid pricing
        $validPricing = [
            'input' => 0.01,
            'output' => 0.03,
            'unit' => PricingUnit::PER_1K_TOKENS,
            'currency' => 'USD',
            'billing_model' => BillingModel::PAY_PER_USE,
            'effective_date' => '2024-01-01',
        ];

        $errors = $this->pricingValidator->validateModelPricing('test-model', $validPricing);
        $this->assertEmpty($errors);

        $stored = $this->pricingService->storePricingToDatabase('test', 'valid-model', $validPricing);
        $this->assertTrue($stored);

        // Test invalid pricing
        $invalidPricing = [
            'input' => 'not-numeric',
            'unit' => 'invalid-unit',
            // Missing required fields
        ];

        $errors = $this->pricingValidator->validateModelPricing('test-model', $invalidPricing);
        $this->assertNotEmpty($errors);

        $stored = $this->pricingService->storePricingToDatabase('test', 'invalid-model', $invalidPricing);
        $this->assertFalse($stored);
    }

    public function test_database_versioning()
    {
        // Store initial pricing
        $pricing1 = [
            'input' => 0.01,
            'output' => 0.03,
            'unit' => PricingUnit::PER_1K_TOKENS,
            'currency' => 'USD',
            'billing_model' => BillingModel::PAY_PER_USE,
            'effective_date' => '2024-01-01',
        ];

        $this->pricingService->storePricingToDatabase('test', 'versioned-model', $pricing1);

        // Store updated pricing
        $pricing2 = [
            'input' => 0.005,
            'output' => 0.015,
            'unit' => PricingUnit::PER_1K_TOKENS,
            'currency' => 'USD',
            'billing_model' => BillingModel::PAY_PER_USE,
            'effective_date' => '2024-02-01',
        ];

        $this->pricingService->storePricingToDatabase('test', 'versioned-model', $pricing2);

        // Should get the latest pricing
        $currentPricing = $this->pricingService->getModelPricing('test', 'versioned-model');
        $this->assertEquals(0.005, $currentPricing['input']);
        $this->assertEquals(0.015, $currentPricing['output']);

        // Verify old pricing is marked as not current
        $oldPricingCount = DB::table('ai_provider_model_costs')
            ->join('ai_provider_models', 'ai_provider_model_costs.ai_provider_model_id', '=', 'ai_provider_models.id')
            ->join('ai_providers', 'ai_provider_models.ai_provider_id', '=', 'ai_providers.id')
            ->where('ai_providers.name', 'test')
            ->where('ai_provider_models.name', 'versioned-model')
            ->where('ai_provider_model_costs.is_current', false)
            ->count();

        $this->assertGreaterThan(0, $oldPricingCount);
    }

    public function test_cache_invalidation()
    {
        // Store initial pricing
        $pricing1 = [
            'input' => 0.01,
            'output' => 0.03,
            'unit' => PricingUnit::PER_1K_TOKENS,
            'currency' => 'USD',
            'billing_model' => BillingModel::PAY_PER_USE,
        ];

        $this->pricingService->storePricingToDatabase('test', 'cache-model', $pricing1);

        // Get pricing (should cache it)
        $cached1 = $this->pricingService->getModelPricing('test', 'cache-model');

        // Update pricing (should invalidate cache)
        $pricing2 = [
            'input' => 0.005,
            'output' => 0.015,
            'unit' => PricingUnit::PER_1K_TOKENS,
            'currency' => 'USD',
            'billing_model' => BillingModel::PAY_PER_USE,
        ];

        $this->pricingService->storePricingToDatabase('test', 'cache-model', $pricing2);

        // Get pricing again (should get updated pricing, not cached)
        $cached2 = $this->pricingService->getModelPricing('test', 'cache-model');

        $this->assertNotEquals($cached1['input'], $cached2['input']);
        $this->assertEquals(0.005, $cached2['input']);
    }

    public function test_different_pricing_units()
    {
        // Test PER_REQUEST pricing
        $requestPricing = [
            'cost' => 0.05,
            'unit' => PricingUnit::PER_REQUEST,
            'currency' => 'USD',
            'billing_model' => BillingModel::PAY_PER_USE,
        ];

        $this->pricingService->storePricingToDatabase('test', 'request-model', $requestPricing);

        $retrieved = $this->pricingService->getModelPricing('test', 'request-model');
        $this->assertEquals(0.05, $retrieved['cost']);
        $this->assertEquals(PricingUnit::PER_REQUEST, $retrieved['unit']);

        // Test PER_IMAGE pricing
        $imagePricing = [
            'cost' => 0.04,
            'unit' => PricingUnit::PER_IMAGE,
            'currency' => 'USD',
            'billing_model' => BillingModel::PAY_PER_USE,
        ];

        $this->pricingService->storePricingToDatabase('test', 'image-model', $imagePricing);

        $retrieved = $this->pricingService->getModelPricing('test', 'image-model');
        $this->assertEquals(0.04, $retrieved['cost']);
        $this->assertEquals(PricingUnit::PER_IMAGE, $retrieved['unit']);
    }

    public function test_concurrent_access()
    {
        // This test simulates concurrent access to the pricing system
        $pricing = [
            'input' => 0.01,
            'output' => 0.03,
            'unit' => PricingUnit::PER_1K_TOKENS,
            'currency' => 'USD',
            'billing_model' => BillingModel::PAY_PER_USE,
        ];

        // Store pricing
        $this->pricingService->storePricingToDatabase('test', 'concurrent-model', $pricing);

        // Simulate multiple concurrent reads
        $results = [];
        for ($i = 0; $i < 5; $i++) {
            $results[] = $this->pricingService->getModelPricing('test', 'concurrent-model');
        }

        // All results should be identical
        foreach ($results as $result) {
            $this->assertEquals($results[0], $result);
            $this->assertEquals('database', $result['source']);
        }
    }
}
