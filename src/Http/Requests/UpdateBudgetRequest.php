<?php

namespace JTD\LaravelAI\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Update Budget Request
 *
 * Validates budget update data with proper rules for modifiable fields,
 * constraints, and authorization checks.
 */
class UpdateBudgetRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Authorization is handled in the controller
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'limit_amount' => [
                'nullable',
                'numeric',
                'min:0.01',
                'max:999999.99',
            ],
            'currency' => [
                'nullable',
                'string',
                'size:3',
                Rule::in(['USD', 'EUR', 'GBP', 'CAD', 'AUD', 'JPY']),
            ],
            'warning_threshold' => [
                'nullable',
                'numeric',
                'min:0',
                'max:100',
                'lt:critical_threshold',
            ],
            'critical_threshold' => [
                'nullable',
                'numeric',
                'min:0',
                'max:100',
                'gt:warning_threshold',
            ],
            'alert_enabled' => [
                'nullable',
                'boolean',
            ],
            'alert_channels' => [
                'nullable',
                'array',
                'max:4',
            ],
            'alert_channels.*' => [
                'string',
                Rule::in(['email', 'slack', 'sms', 'database']),
            ],
            'reset_frequency' => [
                'nullable',
                'string',
                Rule::in(['daily', 'weekly', 'monthly', 'quarterly', 'yearly', 'never']),
            ],
            'is_active' => [
                'nullable',
                'boolean',
            ],
            'description' => [
                'nullable',
                'string',
                'max:500',
            ],
            'metadata' => [
                'nullable',
                'array',
            ],
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'limit_amount.min' => 'Budget limit must be at least $0.01.',
            'limit_amount.max' => 'Budget limit cannot exceed $999,999.99.',
            'currency.size' => 'Currency must be a 3-letter code (e.g., USD).',
            'warning_threshold.lt' => 'Warning threshold must be less than critical threshold.',
            'critical_threshold.gt' => 'Critical threshold must be greater than warning threshold.',
            'alert_channels.max' => 'You can select up to 4 alert channels.',
            'alert_channels.*.in' => 'Invalid alert channel. Must be one of: email, slack, sms, database.',
            'reset_frequency.in' => 'Invalid reset frequency.',
            'description.max' => 'Description cannot exceed 500 characters.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'limit_amount' => 'budget limit',
            'warning_threshold' => 'warning threshold',
            'critical_threshold' => 'critical threshold',
            'alert_channels' => 'alert channels',
            'reset_frequency' => 'reset frequency',
            'is_active' => 'active status',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $this->validateBudgetConstraints($validator);
            $this->validateThresholdLogic($validator);
        });
    }

    /**
     * Validate budget constraints.
     */
    protected function validateBudgetConstraints($validator): void
    {
        $budgetId = $this->route('budget') ?? $this->route('id');
        $newLimit = $this->input('limit_amount');

        if (! $newLimit) {
            return;
        }

        // Get current budget to validate constraints
        $currentBudget = $this->getCurrentBudget($budgetId);

        if (! $currentBudget) {
            return;
        }

        // Validate hierarchy constraints
        $this->validateHierarchyConstraints($validator, $currentBudget, $newLimit);

        // Validate against current spending
        $this->validateAgainstCurrentSpending($validator, $currentBudget, $newLimit);
    }

    /**
     * Validate threshold logic.
     */
    protected function validateThresholdLogic($validator): void
    {
        $warning = $this->input('warning_threshold');
        $critical = $this->input('critical_threshold');

        // If only one threshold is provided, validate against current values
        if (($warning !== null && $critical === null) || ($warning === null && $critical !== null)) {
            $budgetId = $this->route('budget') ?? $this->route('id');
            $currentBudget = $this->getCurrentBudget($budgetId);

            if ($currentBudget) {
                $currentWarning = $currentBudget['warning_threshold'] ?? 75;
                $currentCritical = $currentBudget['critical_threshold'] ?? 90;

                $finalWarning = $warning ?? $currentWarning;
                $finalCritical = $critical ?? $currentCritical;

                if ($finalWarning >= $finalCritical) {
                    if ($warning !== null) {
                        $validator->errors()->add('warning_threshold',
                            'Warning threshold must be less than current critical threshold.');
                    } else {
                        $validator->errors()->add('critical_threshold',
                            'Critical threshold must be greater than current warning threshold.');
                    }
                }
            }
        }
    }

    /**
     * Validate hierarchy constraints.
     */
    protected function validateHierarchyConstraints($validator, array $currentBudget, float $newLimit): void
    {
        $type = $currentBudget['type'];

        switch ($type) {
            case 'daily':
                $monthlyLimit = $this->getMonthlyBudgetLimit($currentBudget['user_id']);
                if ($monthlyLimit && $newLimit * 31 > $monthlyLimit) {
                    $validator->errors()->add('limit_amount',
                        'Daily budget limit would exceed monthly budget when multiplied by 31 days.');
                }
                break;

            case 'project':
                $orgLimit = $this->getOrganizationBudgetLimit($currentBudget['organization_id']);
                if ($orgLimit && $newLimit > $orgLimit) {
                    $validator->errors()->add('limit_amount',
                        'Project budget limit cannot exceed organization budget limit.');
                }
                break;

            case 'monthly':
                // Check if daily budgets would be affected
                $dailyLimit = $this->getDailyBudgetLimit($currentBudget['user_id']);
                if ($dailyLimit && $dailyLimit * 31 > $newLimit) {
                    $validator->errors()->add('limit_amount',
                        'Monthly budget limit cannot be less than daily budget × 31.');
                }
                break;
        }
    }

    /**
     * Validate against current spending.
     */
    protected function validateAgainstCurrentSpending($validator, array $currentBudget, float $newLimit): void
    {
        $currentSpending = $this->getCurrentSpending($currentBudget);

        if ($currentSpending > $newLimit) {
            $validator->errors()->add('limit_amount',
                'New budget limit ($' . number_format($newLimit, 2) . ') cannot be less than current spending ($' .
                number_format($currentSpending, 2) . ').');
        }
    }

    /**
     * Get current budget data.
     */
    protected function getCurrentBudget(int $budgetId): ?array
    {
        // This would query the database for current budget
        // Implementation depends on your budget storage system
        return null;
    }

    /**
     * Get current spending for budget.
     */
    protected function getCurrentSpending(array $budget): float
    {
        // This would calculate current spending based on budget type and period
        return 0.0;
    }

    /**
     * Get monthly budget limit for user.
     */
    protected function getMonthlyBudgetLimit(int $userId): ?float
    {
        // This would query the user's monthly budget limit
        return null;
    }

    /**
     * Get daily budget limit for user.
     */
    protected function getDailyBudgetLimit(int $userId): ?float
    {
        // This would query the user's daily budget limit
        return null;
    }

    /**
     * Get organization budget limit.
     */
    protected function getOrganizationBudgetLimit(?string $organizationId): ?float
    {
        if (! $organizationId) {
            return null;
        }

        // This would query the organization's budget limit
        return null;
    }

    /**
     * Get validation rules for partial updates.
     */
    public function getPartialRules(): array
    {
        $rules = $this->rules();

        // Make all rules nullable for partial updates
        foreach ($rules as $field => $fieldRules) {
            if (! in_array('nullable', $fieldRules)) {
                array_unshift($rules[$field], 'nullable');
            }
        }

        return $rules;
    }

    /**
     * Check if this is a partial update.
     */
    public function isPartialUpdate(): bool
    {
        return $this->isMethod('PATCH');
    }

    /**
     * Get only the fields that are being updated.
     */
    public function getUpdatedFields(): array
    {
        $allowedFields = [
            'limit_amount',
            'currency',
            'warning_threshold',
            'critical_threshold',
            'alert_enabled',
            'alert_channels',
            'reset_frequency',
            'is_active',
            'description',
            'metadata',
        ];

        return array_intersect_key($this->validated(), array_flip($allowedFields));
    }
}
