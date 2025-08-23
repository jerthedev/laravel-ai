<?php

namespace JTD\LaravelAI\Services;

use JTD\LaravelAI\Enums\BillingModel;
use JTD\LaravelAI\Enums\PricingUnit;

/**
 * Pricing validation service for ensuring consistent pricing data across AI providers.
 *
 * This service validates pricing arrays, enums, and unit-specific fields to ensure
 * data integrity and consistency in the pricing system.
 */
class PricingValidator
{
    /**
     * Validate a complete pricing array for multiple models.
     *
     * @param  array  $pricing  Associative array with model names as keys and pricing data as values
     * @return array Array of validation errors (empty if valid)
     */
    public function validatePricingArray(array $pricing): array
    {
        $errors = [];

        if (empty($pricing)) {
            $errors[] = 'Pricing array cannot be empty';

            return $errors;
        }

        foreach ($pricing as $model => $data) {
            $modelErrors = $this->validateModelPricing($model, $data);
            $errors = array_merge($errors, $modelErrors);
        }

        return $errors;
    }

    /**
     * Validate pricing data for a single model.
     *
     * @param  string  $model  The model identifier
     * @param  array  $data  The pricing data for the model
     * @return array Array of validation errors for this model
     */
    public function validateModelPricing(string $model, array $data): array
    {
        $errors = [];

        // Validate required fields
        $errors = array_merge($errors, $this->validateRequiredFields($model, $data));

        // Validate enum fields
        $errors = array_merge($errors, $this->validateEnumFields($model, $data));

        // Validate unit-specific fields
        $errors = array_merge($errors, $this->validateUnitSpecificFields($model, $data));

        // Validate numeric fields
        $errors = array_merge($errors, $this->validateNumericFields($model, $data));

        // Validate date fields
        $errors = array_merge($errors, $this->validateDateFields($model, $data));

        // Validate billing model compatibility
        $errors = array_merge($errors, $this->validateBillingModelCompatibility($model, $data));

        return $errors;
    }

    /**
     * Validate required fields are present.
     */
    private function validateRequiredFields(string $model, array $data): array
    {
        $errors = [];
        $required = ['unit', 'billing_model', 'currency'];

        foreach ($required as $field) {
            if (! isset($data[$field])) {
                $errors[] = "Model '{$model}' missing required field: {$field}";
            }
        }

        return $errors;
    }

    /**
     * Validate enum fields have correct types.
     */
    private function validateEnumFields(string $model, array $data): array
    {
        $errors = [];

        if (isset($data['unit']) && ! $data['unit'] instanceof PricingUnit) {
            $errors[] = "Model '{$model}' unit must be PricingUnit enum";
        }

        if (isset($data['billing_model']) && ! $data['billing_model'] instanceof BillingModel) {
            $errors[] = "Model '{$model}' billing_model must be BillingModel enum";
        }

        return $errors;
    }

    /**
     * Validate unit-specific required fields.
     */
    private function validateUnitSpecificFields(string $model, array $data): array
    {
        $errors = [];

        if (! isset($data['unit'])) {
            return $errors; // Already handled in required fields validation
        }

        $unit = $data['unit'];

        if (in_array($unit, [PricingUnit::PER_1K_TOKENS, PricingUnit::PER_1M_TOKENS])) {
            if (! isset($data['input']) || ! isset($data['output'])) {
                $errors[] = "Model '{$model}' with token pricing must have 'input' and 'output' fields";
            }
        } elseif (in_array($unit, [
            PricingUnit::PER_IMAGE,
            PricingUnit::PER_REQUEST,
            PricingUnit::PER_MINUTE,
            PricingUnit::PER_HOUR,
            PricingUnit::PER_1K_CHARACTERS,
            PricingUnit::PER_AUDIO_FILE,
            PricingUnit::PER_MB,
            PricingUnit::PER_GB,
        ])) {
            if (! isset($data['cost'])) {
                $errors[] = "Model '{$model}' with unit pricing must have 'cost' field";
            }
        }

        return $errors;
    }

    /**
     * Validate numeric fields have correct types and values.
     */
    private function validateNumericFields(string $model, array $data): array
    {
        $errors = [];

        $numericFields = ['input', 'output', 'cost'];

        foreach ($numericFields as $field) {
            if (isset($data[$field])) {
                if (! is_numeric($data[$field])) {
                    $errors[] = "Model '{$model}' field '{$field}' must be numeric";
                } elseif ($data[$field] < 0) {
                    $errors[] = "Model '{$model}' field '{$field}' must be non-negative";
                }
            }
        }

        return $errors;
    }

    /**
     * Validate date fields have correct format.
     */
    private function validateDateFields(string $model, array $data): array
    {
        $errors = [];

        if (isset($data['effective_date'])) {
            $date = $data['effective_date'];
            if (! is_string($date) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                $errors[] = "Model '{$model}' effective_date must be in YYYY-MM-DD format";
            } else {
                // Validate it's a real date
                $dateParts = explode('-', $date);
                if (! checkdate((int) $dateParts[1], (int) $dateParts[2], (int) $dateParts[0])) {
                    $errors[] = "Model '{$model}' effective_date is not a valid date";
                }
            }
        }

        return $errors;
    }

    /**
     * Validate billing model compatibility with pricing unit.
     */
    private function validateBillingModelCompatibility(string $model, array $data): array
    {
        $errors = [];

        if (! isset($data['unit']) || ! isset($data['billing_model'])) {
            return $errors; // Already handled in other validations
        }

        $unit = $data['unit'];
        $billingModel = $data['billing_model'];

        if (! $billingModel->isCompatibleWith($unit)) {
            $errors[] = "Model '{$model}' billing model '{$billingModel->value}' is not compatible with unit '{$unit->value}'";
        }

        return $errors;
    }

    /**
     * Validate currency code format.
     */
    public function validateCurrency(string $currency): bool
    {
        // Basic validation for 3-letter currency codes
        return preg_match('/^[A-Z]{3}$/', $currency) === 1;
    }

    /**
     * Validate pricing consistency across models.
     *
     * This checks for potential issues like vastly different pricing
     * that might indicate data entry errors.
     */
    public function validatePricingConsistency(array $pricing): array
    {
        $warnings = [];

        if (count($pricing) < 2) {
            return $warnings; // Need at least 2 models to compare
        }

        // Group models by unit type
        $unitGroups = [];
        foreach ($pricing as $model => $data) {
            if (isset($data['unit'])) {
                $unitType = $data['unit']->getBaseUnit()->value;
                $unitGroups[$unitType][] = ['model' => $model, 'data' => $data];
            }
        }

        // Check for outliers within each unit group
        foreach ($unitGroups as $unitType => $models) {
            if (count($models) < 2) {
                continue;
            }

            $warnings = array_merge($warnings, $this->checkPricingOutliers($unitType, $models));
        }

        return $warnings;
    }

    /**
     * Check for pricing outliers within a unit group.
     */
    private function checkPricingOutliers(string $unitType, array $models): array
    {
        $warnings = [];

        // Extract costs for comparison
        $costs = [];
        foreach ($models as $modelData) {
            $data = $modelData['data'];
            if (isset($data['input']) && isset($data['output'])) {
                $avgCost = ($data['input'] + $data['output']) / 2;
            } elseif (isset($data['cost'])) {
                $avgCost = $data['cost'];
            } else {
                continue;
            }

            $costs[] = ['model' => $modelData['model'], 'cost' => $avgCost];
        }

        if (count($costs) < 2) {
            return $warnings;
        }

        // Calculate median and identify outliers
        $costValues = array_column($costs, 'cost');
        sort($costValues);
        $median = $costValues[intval(count($costValues) / 2)];

        foreach ($costs as $costData) {
            $ratio = $costData['cost'] / $median;
            if ($ratio > 10 || $ratio < 0.1) {
                $warnings[] = "Model '{$costData['model']}' pricing may be inconsistent (ratio to median: " . round($ratio, 2) . ')';
            }
        }

        return $warnings;
    }

    /**
     * Get validation summary for a pricing array.
     */
    public function getValidationSummary(array $pricing): array
    {
        $errors = $this->validatePricingArray($pricing);
        $warnings = $this->validatePricingConsistency($pricing);

        return [
            'valid' => empty($errors),
            'model_count' => count($pricing),
            'error_count' => count($errors),
            'warning_count' => count($warnings),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }
}
