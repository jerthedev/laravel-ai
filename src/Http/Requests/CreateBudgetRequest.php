<?php

namespace JTD\LaravelAI\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Create Budget Request
 *
 * Validates budget creation data with proper rules for budget types,
 * limits, thresholds, and authorization checks.
 */
class CreateBudgetRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Basic authorization - user must be authenticated
        // Additional authorization logic can be added here
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'user_id' => [
                'nullable',
                'integer',
                'exists:users,id',
                function ($attribute, $value, $fail) {
                    // User can only create budgets for themselves unless they're admin
                    if ($value && $value !== $this->user()->id && !$this->isAdmin()) {
                        $fail('You can only create budgets for yourself.');
                    }
                },
            ],
            'project_id' => [
                'nullable',
                'string',
                'max:255',
                function ($attribute, $value, $fail) {
                    // Validate project access if project_id is provided
                    if ($value && !$this->hasProjectAccess($value)) {
                        $fail('You do not have access to this project.');
                    }
                },
            ],
            'organization_id' => [
                'nullable',
                'string',
                'max:255',
                function ($attribute, $value, $fail) {
                    // Validate organization access if organization_id is provided
                    if ($value && !$this->hasOrganizationAccess($value)) {
                        $fail('You do not have access to this organization.');
                    }
                },
            ],
            'type' => [
                'required',
                'string',
                Rule::in(['daily', 'monthly', 'per_request', 'project', 'organization']),
            ],
            'limit_amount' => [
                'required',
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
            'user_id.exists' => 'The specified user does not exist.',
            'type.required' => 'Budget type is required.',
            'type.in' => 'Budget type must be one of: daily, monthly, per_request, project, organization.',
            'limit_amount.required' => 'Budget limit amount is required.',
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
            'user_id' => 'user',
            'project_id' => 'project',
            'organization_id' => 'organization',
            'limit_amount' => 'budget limit',
            'warning_threshold' => 'warning threshold',
            'critical_threshold' => 'critical threshold',
            'alert_channels' => 'alert channels',
            'reset_frequency' => 'reset frequency',
            'is_active' => 'active status',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Set default values
        $this->merge([
            'user_id' => $this->user_id ?? $this->user()->id,
            'currency' => $this->currency ?? 'USD',
            'warning_threshold' => $this->warning_threshold ?? 75.0,
            'critical_threshold' => $this->critical_threshold ?? 90.0,
            'alert_enabled' => $this->alert_enabled ?? true,
            'alert_channels' => $this->alert_channels ?? ['email', 'database'],
            'is_active' => $this->is_active ?? true,
        ]);

        // Validate budget type constraints
        $this->validateBudgetTypeConstraints();
    }

    /**
     * Validate budget type specific constraints.
     */
    protected function validateBudgetTypeConstraints(): void
    {
        $type = $this->input('type');

        switch ($type) {
            case 'project':
                if (!$this->input('project_id')) {
                    $this->merge(['project_id' => $this->route('project_id')]);
                }
                break;

            case 'organization':
                if (!$this->input('organization_id')) {
                    $this->merge(['organization_id' => $this->route('organization_id')]);
                }
                break;

            case 'per_request':
                // Per-request budgets should have lower limits
                $limit = $this->input('limit_amount');
                if ($limit && $limit > 100) {
                    $this->merge(['limit_amount' => min($limit, 100)]);
                }
                break;
        }
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $this->validateUniqueConstraints($validator);
            $this->validateBudgetHierarchy($validator);
        });
    }

    /**
     * Validate unique budget constraints.
     */
    protected function validateUniqueConstraints($validator): void
    {
        $type = $this->input('type');
        $userId = $this->input('user_id');
        $projectId = $this->input('project_id');
        $organizationId = $this->input('organization_id');

        // Check for existing budget of same type
        $existingBudget = $this->checkExistingBudget($type, $userId, $projectId, $organizationId);

        if ($existingBudget) {
            $validator->errors()->add('type', "A {$type} budget already exists for this scope.");
        }
    }

    /**
     * Validate budget hierarchy constraints.
     */
    protected function validateBudgetHierarchy($validator): void
    {
        $type = $this->input('type');
        $limit = $this->input('limit_amount');

        // Validate against parent budget limits
        if ($type === 'daily') {
            $monthlyLimit = $this->getMonthlyBudgetLimit();
            if ($monthlyLimit && $limit * 31 > $monthlyLimit) {
                $validator->errors()->add('limit_amount', 
                    'Daily budget limit would exceed monthly budget when multiplied by 31 days.');
            }
        }

        if ($type === 'project') {
            $orgLimit = $this->getOrganizationBudgetLimit();
            if ($orgLimit && $limit > $orgLimit) {
                $validator->errors()->add('limit_amount', 
                    'Project budget limit cannot exceed organization budget limit.');
            }
        }
    }

    /**
     * Check if budget already exists.
     */
    protected function checkExistingBudget(string $type, int $userId, ?string $projectId, ?string $organizationId): bool
    {
        // This would query the database to check for existing budgets
        // Implementation depends on your budget storage system
        return false;
    }

    /**
     * Get monthly budget limit for user.
     */
    protected function getMonthlyBudgetLimit(): ?float
    {
        // This would query the user's monthly budget limit
        return null;
    }

    /**
     * Get organization budget limit.
     */
    protected function getOrganizationBudgetLimit(): ?float
    {
        // This would query the organization's budget limit
        return null;
    }

    /**
     * Check if user has project access.
     */
    protected function hasProjectAccess(string $projectId): bool
    {
        // This would check project membership/permissions
        return true; // Placeholder
    }

    /**
     * Check if user has organization access.
     */
    protected function hasOrganizationAccess(string $organizationId): bool
    {
        // This would check organization membership/permissions
        return true; // Placeholder
    }

    /**
     * Check if user is admin.
     */
    protected function isAdmin(): bool
    {
        return $this->user()->is_admin ?? false;
    }
}
