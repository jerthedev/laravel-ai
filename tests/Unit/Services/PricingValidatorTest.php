<?php

namespace JTD\LaravelAI\Tests\Unit\Services;

use JTD\LaravelAI\Enums\BillingModel;
use JTD\LaravelAI\Enums\PricingUnit;
use JTD\LaravelAI\Services\PricingValidator;
use JTD\LaravelAI\Tests\TestCase;

class PricingValidatorTest extends TestCase
{
    protected PricingValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new PricingValidator;
    }

    public function test_validate_valid_pricing_array()
    {
        $pricing = [
            'gpt-4o' => [
                'input' => 0.01,
                'output' => 0.03,
                'unit' => PricingUnit::PER_1K_TOKENS,
                'currency' => 'USD',
                'billing_model' => BillingModel::PAY_PER_USE,
                'effective_date' => '2024-01-01',
            ],
        ];

        $errors = $this->validator->validatePricingArray($pricing);

        $this->assertEmpty($errors);
    }

    public function test_validate_empty_pricing_array()
    {
        $errors = $this->validator->validatePricingArray([]);

        $this->assertContains('Pricing array cannot be empty', $errors);
    }

    public function test_validate_model_pricing_missing_required_fields()
    {
        $pricing = [
            'input' => 0.01,
            'output' => 0.03,
            // Missing unit, billing_model, currency
        ];

        $errors = $this->validator->validateModelPricing('gpt-4o', $pricing);

        $this->assertContains("Model 'gpt-4o' missing required field: unit", $errors);
        $this->assertContains("Model 'gpt-4o' missing required field: billing_model", $errors);
        $this->assertContains("Model 'gpt-4o' missing required field: currency", $errors);
    }

    public function test_validate_model_pricing_invalid_enum_types()
    {
        $pricing = [
            'input' => 0.01,
            'output' => 0.03,
            'unit' => 'invalid_unit',
            'billing_model' => 'invalid_billing',
            'currency' => 'USD',
        ];

        $errors = $this->validator->validateModelPricing('gpt-4o', $pricing);

        $this->assertContains("Model 'gpt-4o' unit must be PricingUnit enum", $errors);
        $this->assertContains("Model 'gpt-4o' billing_model must be BillingModel enum", $errors);
    }

    public function test_validate_token_pricing_missing_input_output()
    {
        $pricing = [
            'unit' => PricingUnit::PER_1K_TOKENS,
            'billing_model' => BillingModel::PAY_PER_USE,
            'currency' => 'USD',
            // Missing input and output
        ];

        $errors = $this->validator->validateModelPricing('gpt-4o', $pricing);

        $this->assertContains("Model 'gpt-4o' with token pricing must have 'input' and 'output' fields", $errors);
    }

    public function test_validate_unit_pricing_missing_cost()
    {
        $pricing = [
            'unit' => PricingUnit::PER_REQUEST,
            'billing_model' => BillingModel::PAY_PER_USE,
            'currency' => 'USD',
            // Missing cost field
        ];

        $errors = $this->validator->validateModelPricing('gpt-4o', $pricing);

        $this->assertContains("Model 'gpt-4o' with unit pricing must have 'cost' field", $errors);
    }

    public function test_validate_numeric_fields()
    {
        $pricing = [
            'input' => 'not_numeric',
            'output' => -1.0,
            'unit' => PricingUnit::PER_1K_TOKENS,
            'billing_model' => BillingModel::PAY_PER_USE,
            'currency' => 'USD',
        ];

        $errors = $this->validator->validateModelPricing('gpt-4o', $pricing);

        $this->assertContains("Model 'gpt-4o' field 'input' must be numeric", $errors);
        $this->assertContains("Model 'gpt-4o' field 'output' must be non-negative", $errors);
    }

    public function test_validate_date_fields()
    {
        $pricing = [
            'input' => 0.01,
            'output' => 0.03,
            'unit' => PricingUnit::PER_1K_TOKENS,
            'billing_model' => BillingModel::PAY_PER_USE,
            'currency' => 'USD',
            'effective_date' => 'invalid_date',
        ];

        $errors = $this->validator->validateModelPricing('gpt-4o', $pricing);

        $this->assertContains("Model 'gpt-4o' effective_date must be in YYYY-MM-DD format", $errors);
    }

    public function test_validate_invalid_date()
    {
        $pricing = [
            'input' => 0.01,
            'output' => 0.03,
            'unit' => PricingUnit::PER_1K_TOKENS,
            'billing_model' => BillingModel::PAY_PER_USE,
            'currency' => 'USD',
            'effective_date' => '2024-13-45', // Invalid date
        ];

        $errors = $this->validator->validateModelPricing('gpt-4o', $pricing);

        $this->assertContains("Model 'gpt-4o' effective_date is not a valid date", $errors);
    }

    public function test_validate_currency()
    {
        $this->assertTrue($this->validator->validateCurrency('USD'));
        $this->assertTrue($this->validator->validateCurrency('EUR'));
        $this->assertFalse($this->validator->validateCurrency('us'));
        $this->assertFalse($this->validator->validateCurrency('USDD'));
    }

    public function test_validate_pricing_consistency()
    {
        $pricing = [
            'cheap-model' => [
                'input' => 0.001,
                'output' => 0.002,
                'unit' => PricingUnit::PER_1K_TOKENS,
                'billing_model' => BillingModel::PAY_PER_USE,
                'currency' => 'USD',
            ],
            'expensive-model' => [
                'input' => 1.0, // 1000x more expensive
                'output' => 2.0,
                'unit' => PricingUnit::PER_1K_TOKENS,
                'billing_model' => BillingModel::PAY_PER_USE,
                'currency' => 'USD',
            ],
        ];

        $warnings = $this->validator->validatePricingConsistency($pricing);

        $this->assertNotEmpty($warnings);
        $this->assertStringContains('expensive-model', $warnings[0]);
        $this->assertStringContains('inconsistent', $warnings[0]);
    }

    public function test_get_validation_summary()
    {
        $pricing = [
            'valid-model' => [
                'input' => 0.01,
                'output' => 0.03,
                'unit' => PricingUnit::PER_1K_TOKENS,
                'billing_model' => BillingModel::PAY_PER_USE,
                'currency' => 'USD',
            ],
            'invalid-model' => [
                'input' => 'not_numeric',
                'unit' => 'invalid_unit',
                'currency' => 'USD',
            ],
        ];

        $summary = $this->validator->getValidationSummary($pricing);

        $this->assertFalse($summary['valid']);
        $this->assertEquals(2, $summary['model_count']);
        $this->assertGreaterThan(0, $summary['error_count']);
        $this->assertIsArray($summary['errors']);
        $this->assertIsArray($summary['warnings']);
    }

    public function test_validate_billing_model_compatibility()
    {
        // Test compatible combination
        $validPricing = [
            'input' => 0.01,
            'output' => 0.03,
            'unit' => PricingUnit::PER_1K_TOKENS,
            'billing_model' => BillingModel::PAY_PER_USE,
            'currency' => 'USD',
        ];

        $errors = $this->validator->validateModelPricing('valid-model', $validPricing);
        $this->assertEmpty(array_filter($errors, fn ($error) => str_contains($error, 'not compatible')));

        // Test incompatible combination (if any exist)
        // This would depend on the actual implementation of BillingModel::isCompatibleWith()
    }

    public function test_validate_multiple_models()
    {
        $pricing = [
            'model1' => [
                'input' => 0.01,
                'output' => 0.03,
                'unit' => PricingUnit::PER_1K_TOKENS,
                'billing_model' => BillingModel::PAY_PER_USE,
                'currency' => 'USD',
            ],
            'model2' => [
                'cost' => 0.05,
                'unit' => PricingUnit::PER_REQUEST,
                'billing_model' => BillingModel::PAY_PER_USE,
                'currency' => 'USD',
            ],
            'invalid-model' => [
                // Missing required fields
            ],
        ];

        $errors = $this->validator->validatePricingArray($pricing);

        // Should have errors for invalid-model but not for model1 and model2
        $this->assertNotEmpty($errors);
        $this->assertTrue(count($errors) >= 3); // At least 3 missing required fields
    }

    public function test_edge_cases()
    {
        // Test with zero costs (should be allowed for free tier)
        $freePricing = [
            'input' => 0.0,
            'output' => 0.0,
            'unit' => PricingUnit::PER_1K_TOKENS,
            'billing_model' => BillingModel::FREE_TIER,
            'currency' => 'USD',
        ];

        $errors = $this->validator->validateModelPricing('free-model', $freePricing);
        $this->assertEmpty($errors);

        // Test with very small costs
        $microPricing = [
            'input' => 0.000001,
            'output' => 0.000002,
            'unit' => PricingUnit::PER_1K_TOKENS,
            'billing_model' => BillingModel::PAY_PER_USE,
            'currency' => 'USD',
        ];

        $errors = $this->validator->validateModelPricing('micro-model', $microPricing);
        $this->assertEmpty($errors);
    }
}
