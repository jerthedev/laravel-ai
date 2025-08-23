<?php

namespace JTD\LaravelAI\Tests\Unit\Drivers\Gemini\Support;

use JTD\LaravelAI\Drivers\Gemini\Support\ModelPricing;
use JTD\LaravelAI\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * ModelPricing Unit Tests
 *
 * Tests the Gemini model pricing functionality.
 */
#[Group('unit')]
#[Group('gemini')]
#[Group('support')]
class ModelPricingTest extends TestCase
{
    #[Test]
    public function it_returns_correct_pricing_for_known_models(): void
    {
        $pricing = ModelPricing::getModelPricing('gemini-pro');
        $this->assertEquals(0.0005, $pricing['input']);
        $this->assertEquals(0.0015, $pricing['output']);

        $pricing = ModelPricing::getModelPricing('gemini-1.5-pro');
        $this->assertEquals(0.0035, $pricing['input']);
        $this->assertEquals(0.0105, $pricing['output']);

        $pricing = ModelPricing::getModelPricing('gemini-1.5-flash');
        $this->assertEquals(0.00035, $pricing['input']);
        $this->assertEquals(0.00105, $pricing['output']);
    }

    #[Test]
    public function it_returns_fallback_pricing_for_unknown_models(): void
    {
        $pricing = ModelPricing::getModelPricing('unknown-model');
        $this->assertEquals(0.0005, $pricing['input']); // Gemini Pro fallback
        $this->assertEquals(0.0015, $pricing['output']);
    }

    #[Test]
    public function it_calculates_cost_correctly(): void
    {
        $cost = ModelPricing::calculateCost(1000, 500, 'gemini-pro');

        $this->assertEquals('gemini-pro', $cost['model']);
        $this->assertEquals(1000, $cost['input_tokens']);
        $this->assertEquals(500, $cost['output_tokens']);
        $this->assertEquals(0.0005, $cost['input_cost']); // (1000/1000) * 0.0005
        $this->assertEquals(0.00075, $cost['output_cost']); // (500/1000) * 0.0015
        $this->assertEquals(0.00125, $cost['total_cost']);
        $this->assertEquals('USD', $cost['currency']);
    }

    #[Test]
    public function it_estimates_cost_correctly(): void
    {
        $estimate = ModelPricing::estimateCost(1000, 'gemini-pro');

        $this->assertEquals('gemini-pro', $estimate['model']);
        $this->assertEquals(750, $estimate['input_tokens']); // 75% of 1000
        $this->assertEquals(250, $estimate['output_tokens']); // 25% of 1000
        $this->assertGreaterThan(0, $estimate['total_cost']);
        $this->assertEquals('USD', $estimate['currency']);
    }

    #[Test]
    public function it_calculates_cost_efficiency(): void
    {
        $efficiency = ModelPricing::getCostEfficiency('gemini-pro');
        $this->assertIsFloat($efficiency);
        $this->assertGreaterThan(0, $efficiency);

        // More expensive models should have lower efficiency scores
        $proEfficiency = ModelPricing::getCostEfficiency('gemini-pro');
        $flashEfficiency = ModelPricing::getCostEfficiency('gemini-1.5-flash');
        $this->assertGreaterThan($proEfficiency, $flashEfficiency);
    }

    #[Test]
    public function it_compares_model_costs(): void
    {
        $comparisons = ModelPricing::compareModelCosts(1000, 500, ['gemini-pro', 'gemini-1.5-flash']);

        $this->assertIsArray($comparisons);
        $this->assertCount(2, $comparisons);
        $this->assertArrayHasKey('gemini-pro', $comparisons);
        $this->assertArrayHasKey('gemini-1.5-flash', $comparisons);

        // Results should be sorted by total cost (ascending)
        $costs = array_values($comparisons);
        $this->assertLessThanOrEqual($costs[1]['total_cost'], $costs[0]['total_cost']);
    }

    #[Test]
    public function it_gets_most_cost_effective_model(): void
    {
        $cheapest = ModelPricing::getMostCostEffectiveModel();
        $this->assertIsString($cheapest);

        // With vision requirement
        $cheapestVision = ModelPricing::getMostCostEffectiveModel(['vision' => true]);
        $this->assertIsString($cheapestVision);
        $this->assertNotEquals('gemini-pro', $cheapestVision); // Should not be text-only model
    }

    #[Test]
    #[DataProvider('rateLimitProvider')]
    public function it_returns_correct_rate_limits(string $modelId, bool $isPaidTier, int $expectedRpm): void
    {
        $limits = ModelPricing::getRateLimits($modelId, $isPaidTier);

        $this->assertEquals($expectedRpm, $limits['requests_per_minute']);
        $this->assertEquals($isPaidTier ? 'paid' : 'free', $limits['tier']);
        $this->assertArrayHasKey('requests_per_day', $limits);
        $this->assertArrayHasKey('tokens_per_minute', $limits);
        $this->assertArrayHasKey('concurrent_requests', $limits);
    }

    public static function rateLimitProvider(): array
    {
        return [
            ['gemini-pro', false, 60],
            ['gemini-pro', true, 360],
            ['gemini-1.5-pro', false, 2],
            ['gemini-1.5-pro', true, 360],
            ['gemini-1.5-flash', false, 15],
            ['gemini-1.5-flash', true, 1000],
        ];
    }

    #[Test]
    public function it_estimates_monthly_cost(): void
    {
        $usagePattern = [
            'daily_input_tokens' => 10000,
            'daily_output_tokens' => 5000,
            'working_days_per_month' => 22,
        ];

        $estimate = ModelPricing::estimateMonthlyCost($usagePattern, 'gemini-pro');

        $this->assertEquals('gemini-pro', $estimate['model']);
        $this->assertEquals(220000, $estimate['monthly_usage']['input_tokens']); // 10000 * 22
        $this->assertEquals(110000, $estimate['monthly_usage']['output_tokens']); // 5000 * 22
        $this->assertArrayHasKey('monthly_cost', $estimate);
        $this->assertArrayHasKey('daily_average', $estimate);
        $this->assertArrayHasKey('assumptions', $estimate);
    }

    #[Test]
    public function it_returns_pricing_tiers(): void
    {
        $tiers = ModelPricing::getPricingTiers();

        $this->assertIsArray($tiers);
        $this->assertArrayHasKey('free', $tiers);
        $this->assertArrayHasKey('paid', $tiers);

        $freeTier = $tiers['free'];
        $this->assertArrayHasKey('name', $freeTier);
        $this->assertArrayHasKey('description', $freeTier);
        $this->assertArrayHasKey('features', $freeTier);
        $this->assertArrayHasKey('limitations', $freeTier);

        $paidTier = $tiers['paid'];
        $this->assertArrayHasKey('name', $paidTier);
        $this->assertArrayHasKey('description', $paidTier);
        $this->assertArrayHasKey('features', $paidTier);
        $this->assertArrayHasKey('benefits', $paidTier);
    }

    #[Test]
    public function it_checks_free_tier_access(): void
    {
        $this->assertTrue(ModelPricing::hasFreeTierAccess('gemini-pro'));
        $this->assertTrue(ModelPricing::hasFreeTierAccess('gemini-1.5-pro'));
        $this->assertFalse(ModelPricing::hasFreeTierAccess('unknown-model'));
    }

    #[Test]
    public function it_returns_all_model_pricing(): void
    {
        $allPricing = ModelPricing::getAllModelPricing();

        $this->assertIsArray($allPricing);
        $this->assertArrayHasKey('gemini-pro', $allPricing);
        $this->assertArrayHasKey('gemini-1.5-pro', $allPricing);
        $this->assertArrayHasKey('gemini-1.5-flash', $allPricing);

        foreach ($allPricing as $modelId => $pricing) {
            $this->assertArrayHasKey('input', $pricing);
            $this->assertArrayHasKey('output', $pricing);
            $this->assertIsFloat($pricing['input']);
            $this->assertIsFloat($pricing['output']);
        }
    }

    #[Test]
    public function it_returns_pricing_history(): void
    {
        $history = ModelPricing::getPricingHistory('gemini-pro');

        $this->assertIsArray($history);
        $this->assertEquals('gemini-pro', $history['model']);
        $this->assertArrayHasKey('current_pricing', $history);
        $this->assertArrayHasKey('price_changes', $history);
        $this->assertArrayHasKey('last_updated', $history);
    }

    #[Test]
    public function it_calculates_break_even_point(): void
    {
        $breakEven = ModelPricing::calculateBreakEvenPoint('gemini-pro', 'gemini-1.5-flash', 10000);

        $this->assertIsArray($breakEven);
        $this->assertArrayHasKey('cheaper_model', $breakEven);
        $this->assertArrayHasKey('expensive_model', $breakEven);
        $this->assertArrayHasKey('daily_cost_difference', $breakEven);
        $this->assertArrayHasKey('monthly_savings', $breakEven);
        $this->assertArrayHasKey('yearly_savings', $breakEven);
        $this->assertEquals(10000, $breakEven['tokens_per_day']);

        $this->assertIsString($breakEven['cheaper_model']);
        $this->assertIsString($breakEven['expensive_model']);
        $this->assertIsFloat($breakEven['daily_cost_difference']);
        $this->assertGreaterThanOrEqual(0, $breakEven['daily_cost_difference']);
    }

    #[Test]
    public function it_normalizes_model_names(): void
    {
        // Test that model name normalization works through pricing lookup
        $pricing1 = ModelPricing::getModelPricing('gemini-pro');
        $pricing2 = ModelPricing::getModelPricing('gemini-pro-latest');

        // Should return same pricing after normalization
        $this->assertEquals($pricing1['input'], $pricing2['input']);
        $this->assertEquals($pricing1['output'], $pricing2['output']);
    }

    #[Test]
    public function it_handles_zero_token_calculations(): void
    {
        $cost = ModelPricing::calculateCost(0, 0, 'gemini-pro');

        $this->assertEquals(0, $cost['input_cost']);
        $this->assertEquals(0, $cost['output_cost']);
        $this->assertEquals(0, $cost['total_cost']);
    }

    #[Test]
    public function it_handles_large_token_calculations(): void
    {
        $cost = ModelPricing::calculateCost(1000000, 500000, 'gemini-pro');

        $this->assertEquals(0.5, $cost['input_cost']); // (1M/1000) * 0.0005
        $this->assertEquals(0.75, $cost['output_cost']); // (500K/1000) * 0.0015
        $this->assertEquals(1.25, $cost['total_cost']);
    }
}
