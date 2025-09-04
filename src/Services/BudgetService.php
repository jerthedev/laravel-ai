<?php

namespace JTD\LaravelAI\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use JTD\LaravelAI\Events\BudgetThresholdReached;
use JTD\LaravelAI\Exceptions\BudgetExceededException;
use JTD\LaravelAI\Models\AIBudget;
use JTD\LaravelAI\Models\AIUsageCost;

/**
 * Budget Service
 *
 * Handles budget checking, cost estimation, and threshold monitoring
 * for AI usage across different budget types and scopes.
 */
class BudgetService
{
    /**
     * Create a new budget for a user.
     *
     * @param  array  $budgetData  Budget configuration data
     * @return array The created budget data
     */
    public function createBudget(array $budgetData): array
    {
        $budget = AIBudget::createForUser(
            $budgetData['user_id'],
            $budgetData['type'] ?? 'monthly',
            $budgetData
        );

        return $budget->toArray();
    }

    /**
     * Check budget limits for a user and estimated cost.
     *
     * @param  int  $userId  The user ID
     * @param  float  $estimatedCost  The estimated cost
     * @param  array  $context  Additional context (project_id, etc.)
     *
     * @throws BudgetExceededException
     */
    public function checkBudgetLimits(int $userId, float $estimatedCost, array $context = []): void
    {
        // Check daily budget
        $this->checkDailyBudget($userId, $estimatedCost);

        // Check monthly budget
        $this->checkMonthlyBudget($userId, $estimatedCost);

        // Check per-request budget
        $this->checkPerRequestBudget($userId, $estimatedCost);

        // Check project budget if applicable
        if (isset($context['project_id'])) {
            $this->checkProjectBudget($context['project_id'], $estimatedCost);
        }
    }

    /**
     * Check daily budget limit.
     *
     * @param  int  $userId  The user ID
     * @param  float  $estimatedCost  The estimated cost
     *
     * @throws BudgetExceededException
     */
    public function checkDailyBudget(int $userId, float $estimatedCost): void
    {
        $dailyLimit = $this->getDailyBudgetLimit($userId);
        if ($dailyLimit === null) {
            return; // No daily limit set
        }

        $todaySpending = $this->getTodaySpending($userId);
        $projectedSpending = $todaySpending + $estimatedCost;

        if ($projectedSpending > $dailyLimit) {
            throw new BudgetExceededException(
                "Daily budget of \${$dailyLimit} would be exceeded. " .
                "Current spending: \${$todaySpending}, Estimated cost: \${$estimatedCost}"
            );
        }

        // Check for threshold warnings
        $this->checkThresholds($userId, 'daily', $projectedSpending, $dailyLimit);
    }

    /**
     * Check monthly budget limit.
     *
     * @param  int  $userId  The user ID
     * @param  float  $estimatedCost  The estimated cost
     *
     * @throws BudgetExceededException
     */
    public function checkMonthlyBudget(int $userId, float $estimatedCost): void
    {
        $monthlyLimit = $this->getMonthlyBudgetLimit($userId);
        if ($monthlyLimit === null) {
            return; // No monthly limit set
        }

        $monthSpending = $this->getMonthSpending($userId);
        $projectedSpending = $monthSpending + $estimatedCost;

        if ($projectedSpending > $monthlyLimit) {
            throw new BudgetExceededException(
                "Monthly budget of \${$monthlyLimit} would be exceeded. " .
                "Current spending: \${$monthSpending}, Estimated cost: \${$estimatedCost}"
            );
        }

        // Check for threshold warnings
        $this->checkThresholds($userId, 'monthly', $projectedSpending, $monthlyLimit);
    }

    /**
     * Check per-request budget limit.
     *
     * @param  int  $userId  The user ID
     * @param  float  $estimatedCost  The estimated cost
     *
     * @throws BudgetExceededException
     */
    public function checkPerRequestBudget(int $userId, float $estimatedCost): void
    {
        $perRequestLimit = $this->getPerRequestBudgetLimit($userId);
        if ($perRequestLimit === null) {
            return; // No per-request limit set
        }

        if ($estimatedCost > $perRequestLimit) {
            throw new BudgetExceededException(
                "Per-request budget of \${$perRequestLimit} would be exceeded. " .
                "Estimated cost: \${$estimatedCost}"
            );
        }
    }

    /**
     * Check project budget limit.
     *
     * @param  int  $projectId  The project ID
     * @param  float  $estimatedCost  The estimated cost
     *
     * @throws BudgetExceededException
     */
    public function checkProjectBudget(int $projectId, float $estimatedCost): void
    {
        $projectLimit = $this->getProjectBudgetLimit($projectId);
        if ($projectLimit === null) {
            return; // No project limit set
        }

        $projectSpending = $this->getProjectSpending($projectId);
        $projectedSpending = $projectSpending + $estimatedCost;

        if ($projectedSpending > $projectLimit) {
            throw new BudgetExceededException(
                "Project budget of \${$projectLimit} would be exceeded. " .
                "Current spending: \${$projectSpending}, Estimated cost: \${$estimatedCost}"
            );
        }

        // Check for threshold warnings
        $this->checkThresholds($projectId, 'project', $projectedSpending, $projectLimit);
    }

    /**
     * Check budget thresholds and fire warning events.
     *
     * @param  int  $entityId  The user or project ID
     * @param  string  $budgetType  The budget type
     * @param  float  $currentSpending  The current spending
     * @param  float  $budgetLimit  The budget limit
     */
    protected function checkThresholds(int $entityId, string $budgetType, float $currentSpending, float $budgetLimit): void
    {
        $percentage = ($currentSpending / $budgetLimit) * 100;

        if ($percentage >= 95) {
            event(new BudgetThresholdReached(
                userId: $entityId,
                budgetType: $budgetType,
                current_spending: $currentSpending,
                budget_limit: $budgetLimit,
                threshold_percentage: $percentage,
                severity: 'critical'
            ));
        } elseif ($percentage >= 80) {
            event(new BudgetThresholdReached(
                userId: $entityId,
                budgetType: $budgetType,
                current_spending: $currentSpending,
                budget_limit: $budgetLimit,
                threshold_percentage: $percentage,
                severity: 'warning'
            ));
        }
    }

    /**
     * Get daily budget limit for a user.
     *
     * @param  int  $userId  The user ID
     * @return float|null The daily limit or null if not set
     */
    protected function getDailyBudgetLimit(int $userId): ?float
    {
        $budget = AIBudget::forUser($userId)
            ->byType('daily')
            ->currentPeriod()
            ->active()
            ->first();

        return $budget?->limit_amount ?? config('ai.middleware.budget_enforcement.daily_limit');
    }

    /**
     * Get monthly budget limit for a user.
     *
     * @param  int  $userId  The user ID
     * @return float|null The monthly limit or null if not set
     */
    protected function getMonthlyBudgetLimit(int $userId): ?float
    {
        $budget = AIBudget::forUser($userId)
            ->byType('monthly')
            ->currentPeriod()
            ->active()
            ->first();

        return $budget?->limit_amount ?? config('ai.middleware.budget_enforcement.monthly_limit');
    }

    /**
     * Get per-request budget limit for a user.
     *
     * @param  int  $userId  The user ID
     * @return float|null The per-request limit or null if not set
     */
    protected function getPerRequestBudgetLimit(int $userId): ?float
    {
        $budget = AIBudget::forUser($userId)
            ->byType('per_request')
            ->active()
            ->first();

        return $budget?->limit_amount ?? config('ai.middleware.budget_enforcement.per_request_limit');
    }

    /**
     * Get project budget limit.
     *
     * @param  int  $projectId  The project ID
     * @return float|null The project limit or null if not set
     */
    protected function getProjectBudgetLimit(int $projectId): ?float
    {
        // TODO: Implement database lookup for project budget settings
        return null; // No project budgets implemented yet
    }

    /**
     * Get today's spending for a user.
     *
     * @param  int  $userId  The user ID
     * @return float The spending amount
     */
    protected function getTodaySpending(int $userId): float
    {
        $cacheKey = "ai_spending:daily:{$userId}:" . Carbon::today()->format('Y-m-d');

        return Cache::remember($cacheKey, 3600, function () use ($userId) {
            return AIUsageCost::getTotalCostForUser($userId, 'today');
        });
    }

    /**
     * Get this month's spending for a user.
     *
     * @param  int  $userId  The user ID
     * @return float The spending amount
     */
    protected function getMonthSpending(int $userId): float
    {
        $cacheKey = "ai_spending:monthly:{$userId}:" . Carbon::now()->format('Y-m');

        return Cache::remember($cacheKey, 3600, function () use ($userId) {
            return AIUsageCost::getTotalCostForUser($userId, 'month');
        });
    }

    /**
     * Get project spending.
     *
     * @param  int  $projectId  The project ID
     * @return float The spending amount
     */
    protected function getProjectSpending(int $projectId): float
    {
        $cacheKey = "ai_spending:project:{$projectId}";

        return Cache::remember($cacheKey, 1800, function () {
            // TODO: Implement database query for project spending
            return 0.0;
        });
    }

    /**
     * Get budget status for a user.
     *
     * @param  int  $userId  The user ID
     * @param  string  $budgetType  The budget type (daily, monthly, etc.)
     * @return array Budget status information
     */
    public function getBudgetStatus(int $userId, string $budgetType = 'monthly'): array
    {
        $budget = AIBudget::forUser($userId)
            ->byType($budgetType)
            ->currentPeriod()
            ->active()
            ->first();

        if (! $budget) {
            return [
                'exists' => false,
                'message' => 'No budget configured',
            ];
        }

        return [
            'exists' => true,
            'budget_id' => $budget->id,
            'user_id' => $userId,
            'type' => $budgetType,
            'limit_amount' => $budget->limit_amount,
            'current_usage' => $budget->current_usage,
            'remaining_amount' => $budget->remaining_amount,
            'usage_percentage' => $budget->usage_percentage,
            'warning_threshold' => $budget->warning_threshold,
            'critical_threshold' => $budget->critical_threshold,
            'status' => $budget->status,
            // Add aliases for test compatibility
            'limit' => $budget->limit_amount,
            'spent' => $budget->current_usage,
            'percentage_used' => $budget->usage_percentage,
        ];
    }

    /**
     * Get budget status level based on usage percentage.
     */
    private function getBudgetStatusLevel(float $usagePercentage, array $budget): string
    {
        if ($usagePercentage >= $budget['critical_threshold']) {
            return 'critical';
        } elseif ($usagePercentage >= $budget['warning_threshold']) {
            return 'warning';
        }

        return 'normal';
    }

    /**
     * Check budget compliance and trigger threshold events.
     *
     * @param  int  $userId  The user ID
     * @param  string  $budgetType  The budget type (daily, monthly, etc.)
     * @param  float  $additionalCost  Additional cost to add to current usage
     */
    public function checkBudgetCompliance(int $userId, string $budgetType, float $additionalCost = 0.0): void
    {
        $budget = AIBudget::forUser($userId)
            ->byType($budgetType)
            ->currentPeriod()
            ->active()
            ->first();

        if (! $budget) {
            return; // No budget configured
        }

        // Update current usage
        if ($additionalCost > 0) {
            $budget->addUsage($additionalCost);
            $budget->refresh(); // Reload from database to get updated values
        }

        // Check thresholds and dispatch events
        if ($budget->isCritical()) {
            event(new BudgetThresholdReached(
                userId: $userId,
                budgetType: $budgetType,
                current_spending: $budget->current_usage,
                budget_limit: $budget->limit_amount,
                threshold_percentage: $budget->usage_percentage,
                severity: 'critical'
            ));
        } elseif ($budget->isWarning()) {
            event(new BudgetThresholdReached(
                userId: $userId,
                budgetType: $budgetType,
                current_spending: $budget->current_usage,
                budget_limit: $budget->limit_amount,
                threshold_percentage: $budget->usage_percentage,
                severity: 'warning'
            ));
        }
    }
}
