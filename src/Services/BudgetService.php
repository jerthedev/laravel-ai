<?php

namespace JTD\LaravelAI\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use JTD\LaravelAI\Events\BudgetThresholdReached;
use JTD\LaravelAI\Exceptions\BudgetExceededException;

/**
 * Budget Service
 * 
 * Handles budget checking, cost estimation, and threshold monitoring
 * for AI usage across different budget types and scopes.
 */
class BudgetService
{
    /**
     * Check budget limits for a user and estimated cost.
     *
     * @param  int  $userId  The user ID
     * @param  float  $estimatedCost  The estimated cost
     * @param  array  $context  Additional context (project_id, etc.)
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
            event(new BudgetThresholdReached($entityId, $budgetType, $currentSpending, $budgetLimit, $percentage, 'critical'));
        } elseif ($percentage >= 80) {
            event(new BudgetThresholdReached($entityId, $budgetType, $currentSpending, $budgetLimit, $percentage, 'warning'));
        }
    }

    /**
     * Get daily budget limit for a user.
     *
     * @param  int  $userId  The user ID
     * @return float|null  The daily limit or null if not set
     */
    protected function getDailyBudgetLimit(int $userId): ?float
    {
        // TODO: Implement database lookup for user budget settings
        // For now, return config default or null
        return config('ai.middleware.budget_enforcement.daily_limit');
    }

    /**
     * Get monthly budget limit for a user.
     *
     * @param  int  $userId  The user ID
     * @return float|null  The monthly limit or null if not set
     */
    protected function getMonthlyBudgetLimit(int $userId): ?float
    {
        // TODO: Implement database lookup for user budget settings
        return config('ai.middleware.budget_enforcement.monthly_limit');
    }

    /**
     * Get per-request budget limit for a user.
     *
     * @param  int  $userId  The user ID
     * @return float|null  The per-request limit or null if not set
     */
    protected function getPerRequestBudgetLimit(int $userId): ?float
    {
        // TODO: Implement database lookup for user budget settings
        return config('ai.middleware.budget_enforcement.per_request_limit');
    }

    /**
     * Get project budget limit.
     *
     * @param  int  $projectId  The project ID
     * @return float|null  The project limit or null if not set
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
     * @return float  The spending amount
     */
    protected function getTodaySpending(int $userId): float
    {
        $cacheKey = "ai_spending:daily:{$userId}:" . Carbon::today()->format('Y-m-d');
        
        return Cache::remember($cacheKey, 3600, function () use ($userId) {
            // TODO: Implement database query for today's spending
            // For now, return 0 as placeholder
            return 0.0;
        });
    }

    /**
     * Get this month's spending for a user.
     *
     * @param  int  $userId  The user ID
     * @return float  The spending amount
     */
    protected function getMonthSpending(int $userId): float
    {
        $cacheKey = "ai_spending:monthly:{$userId}:" . Carbon::now()->format('Y-m');
        
        return Cache::remember($cacheKey, 3600, function () use ($userId) {
            // TODO: Implement database query for this month's spending
            return 0.0;
        });
    }

    /**
     * Get project spending.
     *
     * @param  int  $projectId  The project ID
     * @return float  The spending amount
     */
    protected function getProjectSpending(int $projectId): float
    {
        $cacheKey = "ai_spending:project:{$projectId}";
        
        return Cache::remember($cacheKey, 1800, function () use ($projectId) {
            // TODO: Implement database query for project spending
            return 0.0;
        });
    }
}
