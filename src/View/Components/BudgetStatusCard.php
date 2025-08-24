<?php

namespace JTD\LaravelAI\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Budget Status Card Component
 *
 * Displays budget status with visual indicators, spending progress,
 * and real-time updates for dashboard integration.
 */
class BudgetStatusCard extends Component
{
    /**
     * Budget type (daily, monthly, per_request, project, organization).
     */
    public string $budgetType;

    /**
     * Budget limit amount.
     */
    public ?float $limit;

    /**
     * Amount spent.
     */
    public float $spent;

    /**
     * Amount remaining.
     */
    public ?float $remaining;

    /**
     * Percentage used.
     */
    public float $percentageUsed;

    /**
     * Budget status level.
     */
    public string $status;

    /**
     * Budget reset date.
     */
    public ?string $resetDate;

    /**
     * Whether budget is active.
     */
    public bool $isActive;

    /**
     * Additional CSS classes.
     */
    public string $class;

    /**
     * Create a new component instance.
     *
     * @param  string  $budgetType  Budget type
     * @param  float|null  $limit  Budget limit
     * @param  float  $spent  Amount spent
     * @param  float|null  $remaining  Amount remaining
     * @param  float  $percentageUsed  Percentage used
     * @param  string  $status  Budget status
     * @param  string|null  $resetDate  Reset date
     * @param  bool  $isActive  Whether budget is active
     * @param  string  $class  Additional CSS classes
     */
    public function __construct(
        string $budgetType,
        ?float $limit = null,
        float $spent = 0,
        ?float $remaining = null,
        float $percentageUsed = 0,
        string $status = 'healthy',
        ?string $resetDate = null,
        bool $isActive = true,
        string $class = ''
    ) {
        $this->budgetType = $budgetType;
        $this->limit = $limit;
        $this->spent = $spent;
        $this->remaining = $remaining;
        $this->percentageUsed = $percentageUsed;
        $this->status = $status;
        $this->resetDate = $resetDate;
        $this->isActive = $isActive;
        $this->class = $class;
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View
    {
        return view('laravel-ai::components.budget-status-card');
    }

    /**
     * Get budget type display name.
     *
     * @return string Display name
     */
    public function getBudgetTypeDisplay(): string
    {
        return match ($this->budgetType) {
            'daily' => 'Daily Budget',
            'monthly' => 'Monthly Budget',
            'per_request' => 'Per-Request Budget',
            'project' => 'Project Budget',
            'organization' => 'Organization Budget',
            default => ucfirst($this->budgetType) . ' Budget',
        };
    }

    /**
     * Get status color class.
     *
     * @return string Color class
     */
    public function getStatusColorClass(): string
    {
        return match ($this->status) {
            'exceeded' => 'text-red-600 bg-red-50 border-red-200',
            'critical' => 'text-red-500 bg-red-50 border-red-200',
            'warning' => 'text-yellow-600 bg-yellow-50 border-yellow-200',
            'moderate' => 'text-blue-600 bg-blue-50 border-blue-200',
            'healthy' => 'text-green-600 bg-green-50 border-green-200',
            default => 'text-gray-600 bg-gray-50 border-gray-200',
        };
    }

    /**
     * Get progress bar color class.
     *
     * @return string Progress bar color
     */
    public function getProgressBarColor(): string
    {
        return match ($this->status) {
            'exceeded' => 'bg-red-500',
            'critical' => 'bg-red-400',
            'warning' => 'bg-yellow-400',
            'moderate' => 'bg-blue-400',
            'healthy' => 'bg-green-400',
            default => 'bg-gray-400',
        };
    }

    /**
     * Get status icon.
     *
     * @return string Status icon
     */
    public function getStatusIcon(): string
    {
        return match ($this->status) {
            'exceeded' => '🚨',
            'critical' => '⚠️',
            'warning' => '⚡',
            'moderate' => '📊',
            'healthy' => '✅',
            default => 'ℹ️',
        };
    }

    /**
     * Get formatted spent amount.
     *
     * @return string Formatted amount
     */
    public function getFormattedSpent(): string
    {
        return '$' . number_format($this->spent, 2);
    }

    /**
     * Get formatted limit amount.
     *
     * @return string Formatted amount
     */
    public function getFormattedLimit(): string
    {
        return $this->limit ? '$' . number_format($this->limit, 2) : 'No limit';
    }

    /**
     * Get formatted remaining amount.
     *
     * @return string Formatted amount
     */
    public function getFormattedRemaining(): string
    {
        if ($this->remaining === null) {
            return 'Unlimited';
        }

        if ($this->remaining < 0) {
            return '$' . number_format(abs($this->remaining), 2) . ' over';
        }

        return '$' . number_format($this->remaining, 2) . ' left';
    }

    /**
     * Get formatted percentage.
     *
     * @return string Formatted percentage
     */
    public function getFormattedPercentage(): string
    {
        return number_format($this->percentageUsed, 1) . '%';
    }

    /**
     * Get reset date display.
     *
     * @return string|null Reset date display
     */
    public function getResetDateDisplay(): ?string
    {
        if (!$this->resetDate) {
            return null;
        }

        $resetDate = \Carbon\Carbon::parse($this->resetDate);
        $now = \Carbon\Carbon::now();

        if ($resetDate->isToday()) {
            return 'Resets today at ' . $resetDate->format('g:i A');
        }

        if ($resetDate->isTomorrow()) {
            return 'Resets tomorrow at ' . $resetDate->format('g:i A');
        }

        if ($resetDate->diffInDays($now) <= 7) {
            return 'Resets ' . $resetDate->format('l \a\t g:i A');
        }

        return 'Resets ' . $resetDate->format('M j, Y \a\t g:i A');
    }

    /**
     * Get status message.
     *
     * @return string Status message
     */
    public function getStatusMessage(): string
    {
        if (!$this->isActive) {
            return 'Budget not configured';
        }

        return match ($this->status) {
            'exceeded' => 'Budget exceeded! Immediate action required.',
            'critical' => 'Budget nearly exhausted. Monitor usage closely.',
            'warning' => 'Budget usage is high. Consider reviewing spending.',
            'moderate' => 'Budget usage is moderate. Continue monitoring.',
            'healthy' => 'Budget usage is within healthy limits.',
            default => 'Budget status unknown.',
        };
    }

    /**
     * Get recommended actions.
     *
     * @return array Recommended actions
     */
    public function getRecommendedActions(): array
    {
        if (!$this->isActive) {
            return ['Configure budget limits to enable monitoring'];
        }

        return match ($this->status) {
            'exceeded' => [
                'Increase budget limit immediately',
                'Review recent high-cost operations',
                'Consider pausing non-essential AI usage',
            ],
            'critical' => [
                'Monitor usage closely',
                'Prepare to increase budget if needed',
                'Review cost optimization opportunities',
            ],
            'warning' => [
                'Review spending patterns',
                'Consider cost optimization',
                'Plan for potential budget increase',
            ],
            'moderate' => [
                'Continue monitoring usage',
                'Look for optimization opportunities',
            ],
            'healthy' => [
                'Maintain current usage patterns',
                'Continue regular monitoring',
            ],
            default => [],
        };
    }

    /**
     * Check if budget needs attention.
     *
     * @return bool Whether budget needs attention
     */
    public function needsAttention(): bool
    {
        return in_array($this->status, ['exceeded', 'critical', 'warning']);
    }

    /**
     * Get urgency level.
     *
     * @return string Urgency level
     */
    public function getUrgencyLevel(): string
    {
        return match ($this->status) {
            'exceeded' => 'urgent',
            'critical' => 'high',
            'warning' => 'medium',
            'moderate' => 'low',
            'healthy' => 'none',
            default => 'unknown',
        };
    }
}
