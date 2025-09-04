<?php

namespace JTD\LaravelAI\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when a user approaches or exceeds their budget limits.
 *
 * This event enables background processing for budget alerts, notifications,
 * and administrative actions without impacting response times.
 */
class BudgetThresholdReached
{
    use Dispatchable;
    use SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public int $userId,
        public string $budgetType,
        public float $current_spending,
        public float $budget_limit,
        public float $threshold_percentage,
        public string $severity // 'warning', 'critical', 'exceeded'
    ) {}
}
