<?php

namespace JTD\LaravelAI\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use JTD\LaravelAI\Services\TrendAnalysisService;

/**
 * Forecasting Controller
 *
 * Provides API endpoints for trend analysis, cost forecasting, and
 * optimization recommendations with comprehensive analytics.
 */
class ForecastingController extends Controller
{
    /**
     * Trend Analysis Service.
     */
    protected TrendAnalysisService $trendAnalysisService;

    /**
     * Create a new controller instance.
     */
    public function __construct(TrendAnalysisService $trendAnalysisService)
    {
        $this->trendAnalysisService = $trendAnalysisService;
    }

    /**
     * Get usage trends analysis.
     *
     * @param  Request  $request  HTTP request
     * @return JsonResponse Usage trends
     */
    public function usageTrends(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'nullable|integer|exists:users,id',
            'period' => 'string|in:daily,weekly,monthly',
            'days' => 'integer|min:7|max:365',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $userId = $request->input('user_id') ?? $request->user()->id;
            $period = $request->input('period', 'daily');
            $days = $request->input('days', 30);

            $trends = $this->trendAnalysisService->analyzeUsageTrends($userId, $period, $days);

            return response()->json([
                'success' => true,
                'data' => $trends,
                'generated_at' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to analyze usage trends',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get cost trends and forecasting.
     *
     * @param  Request  $request  HTTP request
     * @return JsonResponse Cost trends
     */
    public function costTrends(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'nullable|integer|exists:users,id',
            'period' => 'string|in:daily,weekly,monthly',
            'days' => 'integer|min:7|max:365',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $userId = $request->input('user_id') ?? $request->user()->id;
            $period = $request->input('period', 'daily');
            $days = $request->input('days', 30);

            $trends = $this->trendAnalysisService->analyzeCostTrends($userId, $period, $days);

            return response()->json([
                'success' => true,
                'data' => $trends,
                'generated_at' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to analyze cost trends',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Compare provider performance.
     *
     * @param  Request  $request  HTTP request
     * @return JsonResponse Provider comparison
     */
    public function providerComparison(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'nullable|integer|exists:users,id',
            'days' => 'integer|min:7|max:365',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $userId = $request->input('user_id') ?? $request->user()->id;
            $days = $request->input('days', 30);

            $comparison = $this->trendAnalysisService->compareProviderPerformance($userId, $days);

            return response()->json([
                'success' => true,
                'data' => $comparison,
                'generated_at' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to compare provider performance',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Compare model performance.
     *
     * @param  Request  $request  HTTP request
     * @return JsonResponse Model comparison
     */
    public function modelComparison(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'nullable|integer|exists:users,id',
            'provider' => 'nullable|string|max:50',
            'days' => 'integer|min:7|max:365',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $userId = $request->input('user_id') ?? $request->user()->id;
            $provider = $request->input('provider');
            $days = $request->input('days', 30);

            $comparison = $this->trendAnalysisService->compareModelPerformance($userId, $provider, $days);

            return response()->json([
                'success' => true,
                'data' => $comparison,
                'generated_at' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to compare model performance',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get optimization recommendations.
     *
     * @param  Request  $request  HTTP request
     * @return JsonResponse Optimization recommendations
     */
    public function optimizationRecommendations(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'nullable|integer|exists:users,id',
            'days' => 'integer|min:7|max:365',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $userId = $request->input('user_id') ?? $request->user()->id;
            $days = $request->input('days', 30);

            $recommendations = $this->trendAnalysisService->generateOptimizationRecommendations($userId, $days);

            return response()->json([
                'success' => true,
                'data' => $recommendations,
                'generated_at' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to generate optimization recommendations',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get comprehensive analytics report.
     *
     * @param  Request  $request  HTTP request
     * @return JsonResponse Comprehensive report
     */
    public function comprehensiveReport(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'nullable|integer|exists:users,id',
            'period' => 'string|in:daily,weekly,monthly',
            'days' => 'integer|min:7|max:365',
            'include_forecasting' => 'boolean',
            'include_comparisons' => 'boolean',
            'include_recommendations' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $userId = $request->input('user_id') ?? $request->user()->id;
            $period = $request->input('period', 'daily');
            $days = $request->input('days', 30);
            $includeForecasting = $request->input('include_forecasting', true);
            $includeComparisons = $request->input('include_comparisons', true);
            $includeRecommendations = $request->input('include_recommendations', true);

            $report = [
                'user_id' => $userId,
                'analysis_period' => $period,
                'days_analyzed' => $days,
                'generated_at' => now()->toISOString(),
            ];

            // Always include usage and cost trends
            $report['usage_trends'] = $this->trendAnalysisService->analyzeUsageTrends($userId, $period, $days);
            $report['cost_trends'] = $this->trendAnalysisService->analyzeCostTrends($userId, $period, $days);

            // Optional sections
            if ($includeComparisons) {
                $report['provider_comparison'] = $this->trendAnalysisService->compareProviderPerformance($userId, $days);
                $report['model_comparison'] = $this->trendAnalysisService->compareModelPerformance($userId, null, $days);
            }

            if ($includeRecommendations) {
                $report['optimization_recommendations'] = $this->trendAnalysisService->generateOptimizationRecommendations($userId, $days);
            }

            // Generate executive summary
            $report['executive_summary'] = $this->generateExecutiveSummary($report);

            return response()->json([
                'success' => true,
                'data' => $report,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to generate comprehensive report',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate executive summary from report data.
     *
     * @param  array  $report  Report data
     * @return array Executive summary
     */
    protected function generateExecutiveSummary(array $report): array
    {
        $summary = [
            'key_metrics' => [],
            'trends' => [],
            'recommendations' => [],
            'alerts' => [],
        ];

        // Extract key metrics
        if (isset($report['usage_trends']['trend_analysis'])) {
            $usageTrend = $report['usage_trends']['trend_analysis'];
            $summary['key_metrics']['usage_trend'] = $usageTrend['trend_direction'] ?? 'unknown';
            $summary['key_metrics']['usage_growth_rate'] = $usageTrend['growth_rate'] ?? 0;
        }

        if (isset($report['cost_trends']['cost_trends'])) {
            $costTrend = $report['cost_trends']['cost_trends'];
            $summary['key_metrics']['cost_trend'] = $costTrend['trend_direction'] ?? 'unknown';
            $summary['key_metrics']['cost_growth_rate'] = $costTrend['growth_rate'] ?? 0;
        }

        // Extract trend insights
        if (isset($report['cost_trends']['cost_forecast'])) {
            $forecast = $report['cost_trends']['cost_forecast'];
            $summary['trends']['monthly_cost_projection'] = $forecast['monthly_projection'] ?? 0;
            $summary['trends']['forecast_confidence'] = $forecast['forecast_confidence'] ?? 'unknown';
        }

        // Extract top recommendations
        if (isset($report['optimization_recommendations']['priority_recommendations'])) {
            $recommendations = $report['optimization_recommendations']['priority_recommendations'];
            $summary['recommendations'] = array_slice($recommendations, 0, 3); // Top 3
        }

        // Generate alerts
        if (isset($report['cost_trends']['cost_trends']['growth_rate'])) {
            $growthRate = $report['cost_trends']['cost_trends']['growth_rate'];
            if ($growthRate > 50) {
                $summary['alerts'][] = [
                    'type' => 'high_cost_growth',
                    'message' => "Cost growth rate is {$growthRate}%, which is significantly high",
                    'severity' => 'high',
                ];
            }
        }

        return $summary;
    }
}
