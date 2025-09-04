<?php

namespace JTD\LaravelAI\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use JTD\LaravelAI\Services\BudgetService;
use JTD\LaravelAI\Services\CostAnalyticsService;

/**
 * Budget Dashboard Controller
 *
 * Provides API endpoints for budget status dashboard with real-time updates,
 * spending visualization, and budget hierarchy support.
 */
class BudgetDashboardController extends Controller
{
    /**
     * Budget Service.
     */
    protected BudgetService $budgetService;

    /**
     * Cost Analytics Service.
     */
    protected CostAnalyticsService $analyticsService;

    /**
     * Create a new controller instance.
     */
    public function __construct(BudgetService $budgetService, CostAnalyticsService $analyticsService)
    {
        $this->budgetService = $budgetService;
        $this->analyticsService = $analyticsService;
    }

    /**
     * Get comprehensive budget dashboard data.
     *
     * @param  Request  $request  HTTP request
     * @return JsonResponse Dashboard data
     */
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id ?? $request->input('user_id');
        $projectId = $request->input('project_id');
        $organizationId = $request->input('organization_id');

        try {
            $dashboardData = $this->getDashboardData($userId, $projectId, $organizationId);

            return response()->json([
                'success' => true,
                'data' => $dashboardData,
                'generated_at' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to load dashboard data',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get budget status overview.
     *
     * @param  Request  $request  HTTP request
     * @return JsonResponse Budget status
     */
    public function status(Request $request): JsonResponse
    {
        $userId = $request->user()->id ?? $request->input('user_id');
        $projectId = $request->input('project_id');
        $organizationId = $request->input('organization_id');

        try {
            $status = $this->getBudgetStatus($userId, $projectId, $organizationId);

            return response()->json([
                'success' => true,
                'data' => $status,
                'updated_at' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to load budget status',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get spending trends data.
     *
     * @param  Request  $request  HTTP request
     * @return JsonResponse Spending trends
     */
    public function trends(Request $request): JsonResponse
    {
        $userId = $request->user()->id ?? $request->input('user_id');
        $groupBy = $request->input('group_by', 'day');
        $dateRange = $request->input('date_range', 'month');

        try {
            $trends = $this->analyticsService->getHistoricalTrends($userId, $groupBy, $dateRange);

            return response()->json([
                'success' => true,
                'data' => $trends,
                'generated_at' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to load spending trends',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get cost breakdown data.
     *
     * @param  Request  $request  HTTP request
     * @return JsonResponse Cost breakdown
     */
    public function breakdown(Request $request): JsonResponse
    {
        $userId = $request->user()->id ?? $request->input('user_id');
        $breakdownType = $request->input('type', 'provider'); // provider, model, user
        $dateRange = $request->input('date_range', 'month');

        try {
            $breakdown = match ($breakdownType) {
                'provider' => $this->analyticsService->getCostBreakdownByProvider($userId, $dateRange),
                'model' => $this->analyticsService->getCostBreakdownByModel($userId, null, $dateRange),
                'user' => $this->analyticsService->getCostBreakdownByUser([$userId], $dateRange),
                default => $this->analyticsService->getCostBreakdownByProvider($userId, $dateRange),
            };

            return response()->json([
                'success' => true,
                'data' => $breakdown,
                'generated_at' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to load cost breakdown',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get real-time budget alerts.
     *
     * @param  Request  $request  HTTP request
     * @return JsonResponse Budget alerts
     */
    public function alerts(Request $request): JsonResponse
    {
        $userId = $request->user()->id ?? $request->input('user_id');
        $limit = $request->input('limit', 10);

        try {
            $alerts = $this->getRecentAlerts($userId, $limit);

            return response()->json([
                'success' => true,
                'data' => $alerts,
                'updated_at' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to load budget alerts',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get budget recommendations.
     *
     * @param  Request  $request  HTTP request
     * @return JsonResponse Budget recommendations
     */
    public function recommendations(Request $request): JsonResponse
    {
        $userId = $request->user()->id ?? $request->input('user_id');

        try {
            $recommendations = $this->getBudgetRecommendations($userId);

            return response()->json([
                'success' => true,
                'data' => $recommendations,
                'generated_at' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to load recommendations',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get comprehensive dashboard data.
     *
     * @param  int  $userId  User ID
     * @param  string|null  $projectId  Project ID
     * @param  string|null  $organizationId  Organization ID
     * @return array Dashboard data
     */
    protected function getDashboardData(int $userId, ?string $projectId = null, ?string $organizationId = null): array
    {
        $cacheKey = "budget_dashboard_{$userId}_{$projectId}_{$organizationId}";

        return Cache::remember($cacheKey, 300, function () use ($userId, $projectId, $organizationId) {
            return [
                'budget_status' => $this->getBudgetStatus($userId, $projectId, $organizationId),
                'spending_summary' => $this->getSpendingSummary($userId, $projectId, $organizationId),
                'recent_activity' => $this->getRecentActivity($userId, 10),
                'cost_breakdown' => $this->analyticsService->getCostBreakdownByProvider($userId, 'month'),
                'trends' => $this->analyticsService->getHistoricalTrends($userId, 'day', 'week'),
                'alerts' => $this->getRecentAlerts($userId, 5),
                'recommendations' => $this->getBudgetRecommendations($userId),
                'performance_metrics' => $this->getPerformanceMetrics($userId),
            ];
        });
    }

    /**
     * Get budget status for all budget types.
     *
     * @param  int  $userId  User ID
     * @param  string|null  $projectId  Project ID
     * @param  string|null  $organizationId  Organization ID
     * @return array Budget status
     */
    protected function getBudgetStatus(int $userId, ?string $projectId = null, ?string $organizationId = null): array
    {
        $budgetTypes = ['daily', 'monthly', 'per_request'];

        if ($projectId) {
            $budgetTypes[] = 'project';
        }

        if ($organizationId) {
            $budgetTypes[] = 'organization';
        }

        $status = [];

        foreach ($budgetTypes as $type) {
            $budgetData = $this->budgetService->getBudgetStatus($userId, $type, [
                'project_id' => $projectId,
                'organization_id' => $organizationId,
            ]);

            $status[$type] = [
                'limit' => $budgetData['limit'] ?? null,
                'spent' => $budgetData['spent'] ?? 0,
                'remaining' => $budgetData['remaining'] ?? null,
                'percentage_used' => $budgetData['percentage_used'] ?? 0,
                'status' => $this->getBudgetStatusLevel($budgetData['percentage_used'] ?? 0),
                'reset_date' => $this->getBudgetResetDate($type),
                'is_active' => $budgetData['limit'] !== null,
            ];
        }

        return $status;
    }

    /**
     * Get spending summary.
     *
     * @param  int  $userId  User ID
     * @param  string|null  $projectId  Project ID
     * @param  string|null  $organizationId  Organization ID
     * @return array Spending summary
     */
    protected function getSpendingSummary(int $userId, ?string $projectId = null, ?string $organizationId = null): array
    {
        return [
            'today' => $this->getSpendingForPeriod($userId, 'today', $projectId, $organizationId),
            'this_week' => $this->getSpendingForPeriod($userId, 'week', $projectId, $organizationId),
            'this_month' => $this->getSpendingForPeriod($userId, 'month', $projectId, $organizationId),
            'last_30_days' => $this->getSpendingForPeriod($userId, 'last_30_days', $projectId, $organizationId),
        ];
    }

    /**
     * Get spending for specific period.
     *
     * @param  int  $userId  User ID
     * @param  string  $period  Time period
     * @param  string|null  $projectId  Project ID
     * @param  string|null  $organizationId  Organization ID
     * @return array Spending data
     */
    protected function getSpendingForPeriod(int $userId, string $period, ?string $projectId = null, ?string $organizationId = null): array
    {
        $breakdown = $this->analyticsService->getCostBreakdownByProvider($userId, $period);

        return [
            'total_cost' => $breakdown['totals']['total_cost'] ?? 0,
            'total_requests' => $breakdown['totals']['total_requests'] ?? 0,
            'avg_cost_per_request' => $breakdown['totals']['avg_cost_per_request'] ?? 0,
            'providers_used' => $breakdown['totals']['unique_providers'] ?? 0,
        ];
    }

    /**
     * Get recent activity.
     *
     * @param  int  $userId  User ID
     * @param  int  $limit  Number of activities
     * @return array Recent activities
     */
    protected function getRecentActivity(int $userId, int $limit): array
    {
        // This would typically fetch from ai_usage_costs table
        // For now, return a placeholder structure
        return [
            'activities' => [],
            'total_count' => 0,
        ];
    }

    /**
     * Get recent budget alerts.
     *
     * @param  int  $userId  User ID
     * @param  int  $limit  Number of alerts
     * @return array Recent alerts
     */
    protected function getRecentAlerts(int $userId, int $limit): array
    {
        // This would use the BudgetAlertService
        return [
            'alerts' => [],
            'total_count' => 0,
            'unread_count' => 0,
        ];
    }

    /**
     * Get budget recommendations.
     *
     * @param  int  $userId  User ID
     * @return array Budget recommendations
     */
    protected function getBudgetRecommendations(int $userId): array
    {
        $efficiency = $this->analyticsService->getCostEfficiencyMetrics($userId, 'month');

        return [
            'recommendations' => $efficiency['recommendations'] ?? [],
            'optimization_opportunities' => $this->getOptimizationOpportunities($userId),
            'budget_adjustments' => $this->getBudgetAdjustmentSuggestions($userId),
        ];
    }

    /**
     * Get performance metrics.
     *
     * @param  int  $userId  User ID
     * @return array Performance metrics
     */
    protected function getPerformanceMetrics(int $userId): array
    {
        return [
            'avg_response_time' => 0,
            'success_rate' => 100,
            'cost_per_token' => 0,
            'efficiency_score' => 0,
        ];
    }

    /**
     * Get budget status level.
     *
     * @param  float  $percentageUsed  Percentage used
     * @return string Status level
     */
    protected function getBudgetStatusLevel(float $percentageUsed): string
    {
        return match (true) {
            $percentageUsed >= 100 => 'exceeded',
            $percentageUsed >= 90 => 'critical',
            $percentageUsed >= 75 => 'warning',
            $percentageUsed >= 50 => 'moderate',
            default => 'healthy',
        };
    }

    /**
     * Get budget reset date.
     *
     * @param  string  $budgetType  Budget type
     * @return string|null Reset date
     */
    protected function getBudgetResetDate(string $budgetType): ?string
    {
        return match ($budgetType) {
            'daily' => now()->addDay()->startOfDay()->toISOString(),
            'monthly' => now()->addMonth()->startOfMonth()->toISOString(),
            default => null,
        };
    }

    /**
     * Get optimization opportunities.
     *
     * @param  int  $userId  User ID
     * @return array Optimization opportunities
     */
    protected function getOptimizationOpportunities(int $userId): array
    {
        return [
            'high_cost_models' => [],
            'underutilized_providers' => [],
            'peak_usage_times' => [],
        ];
    }

    /**
     * Get budget adjustment suggestions.
     *
     * @param  int  $userId  User ID
     * @return array Budget adjustment suggestions
     */
    protected function getBudgetAdjustmentSuggestions(int $userId): array
    {
        return [
            'increase_daily' => false,
            'increase_monthly' => false,
            'suggested_daily_limit' => null,
            'suggested_monthly_limit' => null,
            'reasoning' => [],
        ];
    }
}
