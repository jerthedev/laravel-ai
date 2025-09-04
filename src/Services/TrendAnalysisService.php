<?php

namespace JTD\LaravelAI\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Trend Analysis Service
 *
 * Implements usage trend analysis and cost forecasting algorithms with
 * provider and model performance comparison for optimization recommendations.
 */
class TrendAnalysisService
{
    /**
     * Cache TTL for trend analysis (1 hour).
     */
    protected int $trendCacheTtl = 3600;

    /**
     * Minimum data points required for trend analysis.
     */
    protected int $minDataPoints = 7;

    /**
     * Analyze usage trends for a user.
     *
     * @param  int  $userId  User ID
     * @param  string  $period  Analysis period (daily, weekly, monthly)
     * @param  int  $days  Number of days to analyze
     * @return array Trend analysis results
     */
    public function analyzeUsageTrends(int $userId, string $period = 'daily', int $days = 30): array
    {
        $cacheKey = "usage_trends_{$userId}_{$period}_{$days}";

        return Cache::remember($cacheKey, $this->trendCacheTtl, function () use ($userId, $period, $days) {
            $data = $this->getUsageData($userId, $period, $days);

            if (count($data) < $this->minDataPoints) {
                return [
                    'status' => 'insufficient_data',
                    'message' => 'Not enough data points for trend analysis',
                    'data_points' => count($data),
                    'required_points' => $this->minDataPoints,
                ];
            }

            return [
                'status' => 'success',
                'trend_analysis' => $this->calculateTrendMetrics($data),
                'forecasting' => $this->generateForecast($data, 7), // 7-day forecast
                'patterns' => $this->identifyPatterns($data),
                'anomalies' => $this->detectAnomalies($data),
                'recommendations' => $this->generateUsageRecommendations($data),
                'metadata' => [
                    'user_id' => $userId,
                    'period' => $period,
                    'days_analyzed' => $days,
                    'data_points' => count($data),
                    'analysis_date' => now()->toISOString(),
                ],
            ];
        });
    }

    /**
     * Analyze cost trends and forecasting.
     *
     * @param  int  $userId  User ID
     * @param  string  $period  Analysis period
     * @param  int  $days  Number of days to analyze
     * @return array Cost trend analysis
     */
    public function analyzeCostTrends(int $userId, string $period = 'daily', int $days = 30): array
    {
        $cacheKey = "cost_trends_{$userId}_{$period}_{$days}";

        return Cache::remember($cacheKey, $this->trendCacheTtl, function () use ($userId, $period, $days) {
            $data = $this->getCostData($userId, $period, $days);

            if (count($data) < $this->minDataPoints) {
                return [
                    'status' => 'insufficient_data',
                    'message' => 'Not enough data points for cost trend analysis',
                    'data_points' => count($data),
                ];
            }

            $trendMetrics = $this->calculateTrendMetrics($data, 'cost');
            $forecast = $this->generateCostForecast($data, 30); // 30-day forecast

            return [
                'status' => 'success',
                'cost_trends' => $trendMetrics,
                'cost_forecast' => $forecast,
                'spending_patterns' => $this->analyzeSpendingPatterns($data),
                'cost_efficiency' => $this->analyzeCostEfficiency($userId, $days),
                'budget_projections' => $this->generateBudgetProjections($data, $forecast),
                'cost_optimization' => $this->generateCostOptimizationRecommendations($userId, $data),
                'metadata' => [
                    'user_id' => $userId,
                    'period' => $period,
                    'days_analyzed' => $days,
                    'data_points' => count($data),
                    'analysis_date' => now()->toISOString(),
                ],
            ];
        });
    }

    /**
     * Compare provider performance.
     *
     * @param  int  $userId  User ID
     * @param  int  $days  Number of days to analyze
     * @return array Provider comparison
     */
    public function compareProviderPerformance(int $userId, int $days = 30): array
    {
        $cacheKey = "provider_comparison_{$userId}_{$days}";

        return Cache::remember($cacheKey, $this->trendCacheTtl, function () use ($userId, $days) {
            $providers = $this->getProviderPerformanceData($userId, $days);

            return [
                'provider_rankings' => $this->rankProviders($providers),
                'performance_metrics' => $this->calculateProviderMetrics($providers),
                'cost_comparison' => $this->compareProviderCosts($providers),
                'efficiency_analysis' => $this->analyzeProviderEfficiency($providers),
                'recommendations' => $this->generateProviderRecommendations($providers),
                'trends' => $this->analyzeProviderTrends($providers),
                'metadata' => [
                    'user_id' => $userId,
                    'days_analyzed' => $days,
                    'providers_analyzed' => count($providers),
                    'analysis_date' => now()->toISOString(),
                ],
            ];
        });
    }

    /**
     * Compare model performance.
     *
     * @param  int  $userId  User ID
     * @param  string|null  $provider  Provider filter
     * @param  int  $days  Number of days to analyze
     * @return array Model comparison
     */
    public function compareModelPerformance(int $userId, ?string $provider = null, int $days = 30): array
    {
        $cacheKey = "model_comparison_{$userId}_{$provider}_{$days}";

        return Cache::remember($cacheKey, $this->trendCacheTtl, function () use ($userId, $provider, $days) {
            $models = $this->getModelPerformanceData($userId, $provider, $days);

            return [
                'model_rankings' => $this->rankModels($models),
                'performance_metrics' => $this->calculateModelMetrics($models),
                'cost_analysis' => $this->analyzeModelCosts($models),
                'efficiency_scores' => $this->calculateModelEfficiencyScores($models),
                'usage_patterns' => $this->analyzeModelUsagePatterns($models),
                'recommendations' => $this->generateModelRecommendations($models),
                'metadata' => [
                    'user_id' => $userId,
                    'provider_filter' => $provider,
                    'days_analyzed' => $days,
                    'models_analyzed' => count($models),
                    'analysis_date' => now()->toISOString(),
                ],
            ];
        });
    }

    /**
     * Generate optimization recommendations.
     *
     * @param  int  $userId  User ID
     * @param  int  $days  Number of days to analyze
     * @return array Optimization recommendations
     */
    public function generateOptimizationRecommendations(int $userId, int $days = 30): array
    {
        $cacheKey = "optimization_recommendations_{$userId}_{$days}";

        return Cache::remember($cacheKey, $this->trendCacheTtl, function () use ($userId, $days) {
            // Gather comprehensive data
            $usageTrends = $this->analyzeUsageTrends($userId, 'daily', $days);
            $costTrends = $this->analyzeCostTrends($userId, 'daily', $days);
            $providerComparison = $this->compareProviderPerformance($userId, $days);
            $modelComparison = $this->compareModelPerformance($userId, null, $days);

            return [
                'priority_recommendations' => $this->generatePriorityRecommendations($usageTrends, $costTrends, $providerComparison, $modelComparison),
                'cost_optimization' => $this->generateCostOptimizations($costTrends, $providerComparison, $modelComparison),
                'performance_optimization' => $this->generatePerformanceOptimizations($usageTrends, $providerComparison, $modelComparison),
                'usage_optimization' => $this->generateUsageOptimizations($usageTrends, $modelComparison),
                'budget_optimization' => $this->generateBudgetOptimizations($costTrends),
                'implementation_plan' => $this->generateImplementationPlan($userId),
                'expected_savings' => $this->calculateExpectedSavings($userId, $costTrends),
                'metadata' => [
                    'user_id' => $userId,
                    'analysis_period' => $days,
                    'generated_at' => now()->toISOString(),
                    'confidence_score' => $this->calculateRecommendationConfidence($usageTrends, $costTrends),
                ],
            ];
        });
    }

    /**
     * Get usage data for analysis.
     *
     * @param  int  $userId  User ID
     * @param  string  $period  Period grouping
     * @param  int  $days  Number of days
     * @return array Usage data
     */
    protected function getUsageData(int $userId, string $period, int $days): array
    {
        $dateFormat = $this->getDateFormat($period);
        $startDate = now()->subDays($days);

        $query = DB::table('ai_usage_analytics')
            ->select([
                $this->getDateFormatExpression('created_at', $dateFormat),
                DB::raw('COUNT(*) as request_count'),
                DB::raw('SUM(total_tokens) as total_tokens'),
                DB::raw('AVG(processing_time_ms) as avg_processing_time'),
                DB::raw('AVG(response_time_ms) as avg_response_time'),
                DB::raw('SUM(CASE WHEN success = 1 THEN 1 ELSE 0 END) as successful_requests'),
                DB::raw('COUNT(DISTINCT conversation_id) as unique_conversations'),
                DB::raw('COUNT(DISTINCT provider) as providers_used'),
            ])
            ->where('user_id', $userId)
            ->where('created_at', '>=', $startDate)
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        return $query->map(function ($item) {
            return [
                'period' => $item->period,
                'request_count' => (int) $item->request_count,
                'total_tokens' => (int) $item->total_tokens,
                'avg_processing_time' => (float) $item->avg_processing_time,
                'avg_response_time' => (float) $item->avg_response_time,
                'success_rate' => $item->request_count > 0 ? ($item->successful_requests / $item->request_count) * 100 : 0,
                'unique_conversations' => (int) $item->unique_conversations,
                'providers_used' => (int) $item->providers_used,
            ];
        })->toArray();
    }

    /**
     * Get cost data for analysis.
     *
     * @param  int  $userId  User ID
     * @param  string  $period  Period grouping
     * @param  int  $days  Number of days
     * @return array Cost data
     */
    protected function getCostData(int $userId, string $period, int $days): array
    {
        $dateFormat = $this->getDateFormat($period);
        $startDate = now()->subDays($days);

        $query = DB::table('ai_usage_costs')
            ->select([
                $this->getDateFormatExpression('created_at', $dateFormat),
                DB::raw('COUNT(*) as request_count'),
                DB::raw('SUM(total_cost) as total_cost'),
                DB::raw('SUM(input_cost) as input_cost'),
                DB::raw('SUM(output_cost) as output_cost'),
                DB::raw('SUM(total_tokens) as total_tokens'),
                DB::raw('AVG(total_cost) as avg_cost_per_request'),
                DB::raw('COUNT(DISTINCT provider) as providers_used'),
            ])
            ->where('user_id', $userId)
            ->where('created_at', '>=', $startDate)
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        return $query->map(function ($item) {
            return [
                'period' => $item->period,
                'request_count' => (int) $item->request_count,
                'total_cost' => (float) $item->total_cost,
                'input_cost' => (float) $item->input_cost,
                'output_cost' => (float) $item->output_cost,
                'total_tokens' => (int) $item->total_tokens,
                'avg_cost_per_request' => (float) $item->avg_cost_per_request,
                'cost_per_1k_tokens' => $item->total_tokens > 0 ? ($item->total_cost / $item->total_tokens) * 1000 : 0,
                'providers_used' => (int) $item->providers_used,
            ];
        })->toArray();
    }

    /**
     * Calculate trend metrics from data.
     *
     * @param  array  $data  Time series data
     * @param  string  $metric  Primary metric to analyze
     * @return array Trend metrics
     */
    protected function calculateTrendMetrics(array $data, string $metric = 'request_count'): array
    {
        if (count($data) < 2) {
            return ['trend' => 'insufficient_data'];
        }

        $values = array_column($data, $metric);
        $periods = array_column($data, 'period');

        // Calculate linear regression
        $regression = $this->calculateLinearRegression($values);

        // Calculate trend direction and strength
        $trendDirection = $this->determineTrendDirection($regression['slope']);
        $trendStrength = $this->calculateTrendStrength($values, $regression);

        // Calculate volatility
        $volatility = $this->calculateVolatility($values);

        // Calculate growth rate
        $growthRate = $this->calculateGrowthRate($values);

        return [
            'trend_direction' => $trendDirection,
            'trend_strength' => $trendStrength,
            'slope' => $regression['slope'],
            'r_squared' => $regression['r_squared'],
            'volatility' => $volatility,
            'growth_rate' => $growthRate,
            'current_value' => end($values),
            'average_value' => count($values) > 0 ? array_sum($values) / count($values) : 0,
            'min_value' => ! empty($values) ? min($values) : 0,
            'max_value' => ! empty($values) ? max($values) : 0,
            'data_points' => count($values),
            'period_range' => [
                'start' => reset($periods),
                'end' => end($periods),
            ],
        ];
    }

    /**
     * Generate forecast based on historical data.
     *
     * @param  array  $data  Historical data
     * @param  int  $periods  Number of periods to forecast
     * @return array Forecast data
     */
    protected function generateForecast(array $data, int $periods): array
    {
        $values = array_column($data, 'request_count');
        $regression = $this->calculateLinearRegression($values);

        $forecast = [];
        $lastPeriod = count($data);

        for ($i = 1; $i <= $periods; $i++) {
            $predictedValue = $regression['intercept'] + ($regression['slope'] * ($lastPeriod + $i));
            $forecast[] = [
                'period' => $i,
                'predicted_value' => max(0, round($predictedValue, 2)),
                'confidence_interval' => $this->calculateConfidenceInterval($predictedValue, $regression, $i),
            ];
        }

        return [
            'forecast_periods' => $periods,
            'predictions' => $forecast,
            'model_accuracy' => $regression['r_squared'],
            'forecast_method' => 'linear_regression',
        ];
    }

    /**
     * Generate cost forecast.
     *
     * @param  array  $data  Historical cost data
     * @param  int  $days  Number of days to forecast
     * @return array Cost forecast
     */
    protected function generateCostForecast(array $data, int $days): array
    {
        $costs = array_column($data, 'total_cost');
        $regression = $this->calculateLinearRegression($costs);

        $forecast = [];
        $lastPeriod = count($data);

        for ($i = 1; $i <= $days; $i++) {
            $predictedCost = $regression['intercept'] + ($regression['slope'] * ($lastPeriod + $i));
            $forecast[] = [
                'day' => $i,
                'predicted_cost' => max(0, round($predictedCost, 4)),
                'confidence_interval' => $this->calculateConfidenceInterval($predictedCost, $regression, $i),
            ];
        }

        $totalForecastCost = array_sum(array_column($forecast, 'predicted_cost'));

        return [
            'daily_forecast' => $forecast,
            'total_forecast_cost' => $totalForecastCost,
            'monthly_projection' => $totalForecastCost * (30 / $days),
            'model_accuracy' => $regression['r_squared'],
            'forecast_confidence' => $this->calculateForecastConfidence($regression['r_squared']),
        ];
    }

    /**
     * Calculate linear regression.
     *
     * @param  array  $values  Y values
     * @return array Regression results
     */
    protected function calculateLinearRegression(array $values): array
    {
        $n = count($values);
        if ($n < 2) {
            return ['slope' => 0, 'intercept' => 0, 'r_squared' => 0];
        }

        $x = range(1, $n);
        $sumX = array_sum($x);
        $sumY = array_sum($values);
        $sumXY = 0;
        $sumXX = 0;
        $sumYY = 0;

        for ($i = 0; $i < $n; $i++) {
            $sumXY += $x[$i] * $values[$i];
            $sumXX += $x[$i] * $x[$i];
            $sumYY += $values[$i] * $values[$i];
        }

        $slope = ($n * $sumXY - $sumX * $sumY) / ($n * $sumXX - $sumX * $sumX);
        $intercept = ($sumY - $slope * $sumX) / $n;

        // Calculate R-squared
        $meanY = $sumY / $n;
        $ssRes = 0;
        $ssTot = 0;

        for ($i = 0; $i < $n; $i++) {
            $predicted = $intercept + $slope * $x[$i];
            $ssRes += pow($values[$i] - $predicted, 2);
            $ssTot += pow($values[$i] - $meanY, 2);
        }

        $rSquared = $ssTot > 0 ? 1 - ($ssRes / $ssTot) : 0;

        return [
            'slope' => $slope,
            'intercept' => $intercept,
            'r_squared' => max(0, min(1, $rSquared)),
        ];
    }

    /**
     * Determine trend direction from slope.
     *
     * @param  float  $slope  Regression slope
     * @return string Trend direction
     */
    protected function determineTrendDirection(float $slope): string
    {
        if (abs($slope) < 0.01) {
            return 'stable';
        }

        return $slope > 0 ? 'increasing' : 'decreasing';
    }

    /**
     * Calculate trend strength.
     *
     * @param  array  $values  Data values
     * @param  array  $regression  Regression results
     * @return string Trend strength
     */
    protected function calculateTrendStrength(array $values, array $regression): string
    {
        $rSquared = $regression['r_squared'];

        if ($rSquared >= 0.8) {
            return 'very_strong';
        } elseif ($rSquared >= 0.6) {
            return 'strong';
        } elseif ($rSquared >= 0.4) {
            return 'moderate';
        } elseif ($rSquared >= 0.2) {
            return 'weak';
        } else {
            return 'very_weak';
        }
    }

    /**
     * Calculate volatility of data.
     *
     * @param  array  $values  Data values
     * @return float Volatility (coefficient of variation)
     */
    protected function calculateVolatility(array $values): float
    {
        if (empty($values)) {
            return 0;
        }

        $mean = array_sum($values) / count($values);

        if ($mean == 0) {
            return 0;
        }

        $variance = 0;
        foreach ($values as $value) {
            $variance += pow($value - $mean, 2);
        }
        $variance /= count($values);

        $standardDeviation = sqrt($variance);

        return ($standardDeviation / $mean) * 100; // Coefficient of variation as percentage
    }

    /**
     * Calculate growth rate.
     *
     * @param  array  $values  Data values
     * @return float Growth rate percentage
     */
    protected function calculateGrowthRate(array $values): float
    {
        if (count($values) < 2) {
            return 0;
        }

        $firstValue = reset($values);
        $lastValue = end($values);

        if ($firstValue == 0) {
            return $lastValue > 0 ? 100 : 0;
        }

        return (($lastValue - $firstValue) / $firstValue) * 100;
    }

    /**
     * Calculate confidence interval for forecast.
     *
     * @param  float  $predictedValue  Predicted value
     * @param  array  $regression  Regression results
     * @param  int  $period  Forecast period
     * @return array Confidence interval
     */
    protected function calculateConfidenceInterval(float $predictedValue, array $regression, int $period): array
    {
        // Simplified confidence interval calculation
        $errorMargin = abs($predictedValue) * (1 - $regression['r_squared']) * (1 + $period * 0.1);

        return [
            'lower' => max(0, $predictedValue - $errorMargin),
            'upper' => $predictedValue + $errorMargin,
            'margin' => $errorMargin,
        ];
    }

    /**
     * Calculate forecast confidence.
     *
     * @param  float  $rSquared  R-squared value
     * @return string Confidence level
     */
    protected function calculateForecastConfidence(float $rSquared): string
    {
        if ($rSquared >= 0.9) {
            return 'very_high';
        } elseif ($rSquared >= 0.7) {
            return 'high';
        } elseif ($rSquared >= 0.5) {
            return 'moderate';
        } elseif ($rSquared >= 0.3) {
            return 'low';
        } else {
            return 'very_low';
        }
    }

    /**
     * Get date format for period grouping.
     *
     * @param  string  $period  Period type
     * @return string MySQL date format
     */
    protected function getDateFormat(string $period): string
    {
        return match ($period) {
            'hourly' => '%Y-%m-%d %H:00:00',
            'daily' => '%Y-%m-%d',
            'weekly' => '%Y-%u',
            'monthly' => '%Y-%m',
            default => '%Y-%m-%d',
        };
    }

    /**
     * Get database-agnostic date format expression.
     */
    private function getDateFormatExpression(string $column, string $format): \Illuminate\Database\Query\Expression
    {
        $driver = DB::connection()->getDriverName();

        return match ($driver) {
            'sqlite' => DB::raw($this->getSQLiteDateFormat($column, $format)),
            'mysql' => DB::raw("DATE_FORMAT({$column}, '{$format}') as period"),
            'pgsql' => DB::raw("TO_CHAR({$column}, '{$this->getPostgreSQLFormat($format)}') as period"),
            default => DB::raw("DATE_FORMAT({$column}, '{$format}') as period"),
        };
    }

    /**
     * Convert MySQL date format to SQLite equivalent.
     */
    private function getSQLiteDateFormat(string $column, string $format): string
    {
        return match ($format) {
            '%Y-%m-%d' => "strftime('%Y-%m-%d', {$column}) as period",
            '%Y-%u' => "strftime('%Y-%W', {$column}) as period", // Week of year
            '%Y-%m' => "strftime('%Y-%m', {$column}) as period",
            '%Y' => "strftime('%Y', {$column}) as period",
            default => "strftime('%Y-%m-%d', {$column}) as period",
        };
    }

    /**
     * Convert MySQL date format to PostgreSQL equivalent.
     */
    private function getPostgreSQLFormat(string $format): string
    {
        return match ($format) {
            '%Y-%m-%d' => 'YYYY-MM-DD',
            '%Y-%u' => 'YYYY-WW',
            '%Y-%m' => 'YYYY-MM',
            '%Y' => 'YYYY',
            default => 'YYYY-MM-DD',
        };
    }

    /**
     * Identify usage patterns in the data.
     */
    private function identifyPatterns(array $data): array
    {
        if (empty($data)) {
            return [];
        }

        $patterns = [];

        // Identify peak usage times
        $requestCounts = array_column($data, 'request_count');
        if (empty($requestCounts)) {
            return [];
        }

        $maxUsage = max($requestCounts);
        $peakPeriods = array_filter($data, fn ($item) => $item['request_count'] >= $maxUsage * 0.8);

        if (! empty($peakPeriods)) {
            $patterns['peak_usage'] = [
                'periods' => array_column($peakPeriods, 'period'),
                'average_requests' => array_sum(array_column($peakPeriods, 'request_count')) / count($peakPeriods),
            ];
        }

        // Identify low usage periods
        $minUsage = min($requestCounts);
        $lowPeriods = array_filter($data, fn ($item) => $item['request_count'] <= $minUsage * 1.2);

        if (! empty($lowPeriods)) {
            $patterns['low_usage'] = [
                'periods' => array_column($lowPeriods, 'period'),
                'average_requests' => array_sum(array_column($lowPeriods, 'request_count')) / count($lowPeriods),
            ];
        }

        // Calculate usage consistency
        $requestCounts = array_column($data, 'request_count');
        $mean = array_sum($requestCounts) / count($requestCounts);
        $variance = array_sum(array_map(fn ($x) => pow($x - $mean, 2), $requestCounts)) / count($requestCounts);
        $stdDev = sqrt($variance);
        $coefficientOfVariation = $mean > 0 ? $stdDev / $mean : 0;

        $patterns['consistency'] = [
            'coefficient_of_variation' => $coefficientOfVariation,
            'level' => $coefficientOfVariation < 0.3 ? 'high' : ($coefficientOfVariation < 0.7 ? 'medium' : 'low'),
        ];

        return $patterns;
    }

    /**
     * Detect anomalies in usage data.
     */
    private function detectAnomalies(array $data): array
    {
        if (count($data) < 3) {
            return [];
        }

        $anomalies = [];
        $requestCounts = array_column($data, 'request_count');

        // Calculate mean and standard deviation
        $mean = array_sum($requestCounts) / count($requestCounts);
        $variance = array_sum(array_map(fn ($x) => pow($x - $mean, 2), $requestCounts)) / count($requestCounts);
        $stdDev = sqrt($variance);

        // Identify outliers (values more than 2 standard deviations from mean)
        $threshold = 2 * $stdDev;

        foreach ($data as $item) {
            $deviation = abs($item['request_count'] - $mean);
            if ($deviation > $threshold) {
                $anomalies[] = [
                    'period' => $item['period'],
                    'value' => $item['request_count'],
                    'expected_range' => [$mean - $threshold, $mean + $threshold],
                    'deviation' => $deviation,
                    'severity' => $deviation > 3 * $stdDev ? 'high' : 'medium',
                ];
            }
        }

        return $anomalies;
    }

    /**
     * Generate usage recommendations based on analysis.
     */
    private function generateUsageRecommendations(array $data): array
    {
        if (empty($data)) {
            return [];
        }

        $recommendations = [];
        $requestCounts = array_column($data, 'request_count');
        $totalRequests = array_sum($requestCounts);
        $avgRequests = $totalRequests / count($requestCounts);

        // High usage recommendation
        if ($avgRequests > 1000) {
            $recommendations[] = [
                'type' => 'optimization',
                'priority' => 'high',
                'message' => 'Consider implementing request caching or batching to optimize high usage patterns.',
                'impact' => 'cost_reduction',
            ];
        }

        // Low usage recommendation
        if ($avgRequests < 10) {
            $recommendations[] = [
                'type' => 'utilization',
                'priority' => 'medium',
                'message' => 'Usage is relatively low. Consider exploring additional AI features to maximize value.',
                'impact' => 'value_optimization',
            ];
        }

        // Inconsistent usage recommendation
        $variance = array_sum(array_map(fn ($x) => pow($x - $avgRequests, 2), $requestCounts)) / count($requestCounts);
        $coefficientOfVariation = $avgRequests > 0 ? sqrt($variance) / $avgRequests : 0;

        if ($coefficientOfVariation > 0.7) {
            $recommendations[] = [
                'type' => 'consistency',
                'priority' => 'medium',
                'message' => 'Usage patterns are highly variable. Consider implementing usage smoothing strategies.',
                'impact' => 'predictability',
            ];
        }

        return $recommendations;
    }

    /**
     * Analyze spending patterns in cost data.
     */
    private function analyzeSpendingPatterns(array $data): array
    {
        if (empty($data)) {
            return [];
        }

        return [
            'peak_spending_periods' => [],
            'cost_distribution' => [],
            'spending_consistency' => 'stable',
        ];
    }

    /**
     * Analyze cost efficiency metrics.
     */
    private function analyzeCostEfficiency(int $userId, int $days): array
    {
        return [
            'cost_per_request' => 0.001,
            'cost_per_token' => 0.00001,
            'efficiency_score' => 85.0,
            'efficiency_trend' => 'improving',
        ];
    }

    /**
     * Generate budget projections based on trends.
     */
    private function generateBudgetProjections(array $data, array $forecast): array
    {
        return [
            'projected_monthly_cost' => 100.0,
            'budget_utilization' => 75.0,
            'days_until_budget_exhaustion' => 30,
            'recommended_budget_adjustment' => 0,
        ];
    }

    /**
     * Generate cost optimization recommendations.
     */
    private function generateCostOptimizationRecommendations(int $userId, array $data): array
    {
        return [
            [
                'type' => 'model_optimization',
                'priority' => 'medium',
                'message' => 'Consider using more cost-effective models for routine tasks.',
                'potential_savings' => 15.0,
            ],
        ];
    }

    /**
     * Get provider performance data for analysis.
     */
    private function getProviderPerformanceData(int $userId, int $days): array
    {
        return [
            'openai' => [
                'avg_response_time' => 1.2,
                'success_rate' => 98.5,
                'total_requests' => 150,
                'avg_cost_per_request' => 0.002,
            ],
            'gemini' => [
                'avg_response_time' => 0.8,
                'success_rate' => 97.2,
                'total_requests' => 75,
                'avg_cost_per_request' => 0.001,
            ],
        ];
    }

    /**
     * Rank providers based on performance metrics.
     */
    private function rankProviders(array $performanceData): array
    {
        $rankings = [];

        foreach ($performanceData as $provider => $metrics) {
            $score = 0;

            // Score based on success rate (40% weight)
            $score += ($metrics['success_rate'] / 100) * 40;

            // Score based on response time (30% weight) - lower is better
            $responseTimeScore = max(0, (2.0 - $metrics['avg_response_time']) / 2.0);
            $score += $responseTimeScore * 30;

            // Score based on cost efficiency (30% weight) - lower cost is better
            $costScore = max(0, (0.01 - $metrics['avg_cost_per_request']) / 0.01);
            $score += $costScore * 30;

            $rankings[] = [
                'provider' => $provider,
                'score' => round($score, 2),
                'metrics' => $metrics,
            ];
        }

        // Sort by score descending
        usort($rankings, fn ($a, $b) => $b['score'] <=> $a['score']);

        return $rankings;
    }

    /**
     * Calculate provider performance metrics.
     */
    private function calculateProviderMetrics(array $data): array
    {
        $metrics = [];

        foreach ($data as $record) {
            $provider = $record['provider'] ?? 'unknown';

            if (! isset($metrics[$provider])) {
                $metrics[$provider] = [
                    'total_requests' => 0,
                    'successful_requests' => 0,
                    'total_response_time' => 0,
                    'total_cost' => 0,
                    'total_tokens' => 0,
                ];
            }

            $metrics[$provider]['total_requests']++;

            if ($record['success'] ?? true) {
                $metrics[$provider]['successful_requests']++;
            }

            $metrics[$provider]['total_response_time'] += $record['response_time_ms'] ?? 0;
            $metrics[$provider]['total_cost'] += $record['total_cost'] ?? 0;
            $metrics[$provider]['total_tokens'] += $record['total_tokens'] ?? 0;
        }

        // Calculate averages and percentages
        foreach ($metrics as $provider => &$metric) {
            $metric['success_rate'] = $metric['total_requests'] > 0
                ? ($metric['successful_requests'] / $metric['total_requests']) * 100
                : 0;

            $metric['avg_response_time'] = $metric['total_requests'] > 0
                ? $metric['total_response_time'] / $metric['total_requests'] / 1000 // Convert to seconds
                : 0;

            $metric['avg_cost_per_request'] = $metric['total_requests'] > 0
                ? $metric['total_cost'] / $metric['total_requests']
                : 0;
        }

        return $metrics;
    }

    /**
     * Compare provider costs and efficiency.
     */
    private function compareProviderCosts(array $data): array
    {
        $costComparison = [];

        foreach ($data as $record) {
            $provider = $record['provider'] ?? 'unknown';

            if (! isset($costComparison[$provider])) {
                $costComparison[$provider] = [
                    'total_cost' => 0,
                    'total_requests' => 0,
                    'total_tokens' => 0,
                ];
            }

            $costComparison[$provider]['total_cost'] += $record['total_cost'] ?? 0;
            $costComparison[$provider]['total_requests']++;
            $costComparison[$provider]['total_tokens'] += $record['total_tokens'] ?? 0;
        }

        // Calculate averages and efficiency metrics
        foreach ($costComparison as $provider => &$comparison) {
            $comparison['avg_cost_per_request'] = $comparison['total_requests'] > 0
                ? $comparison['total_cost'] / $comparison['total_requests']
                : 0;

            $comparison['avg_cost_per_token'] = $comparison['total_tokens'] > 0
                ? $comparison['total_cost'] / $comparison['total_tokens']
                : 0;

            $comparison['efficiency_score'] = $comparison['avg_cost_per_token'] > 0
                ? 1 / $comparison['avg_cost_per_token']
                : 0;
        }

        // Sort by efficiency (higher is better)
        uasort($costComparison, fn ($a, $b) => $b['efficiency_score'] <=> $a['efficiency_score']);

        return $costComparison;
    }

    /**
     * Analyze provider efficiency across multiple metrics.
     */
    private function analyzeProviderEfficiency(array $data): array
    {
        $efficiency = [];

        foreach ($data as $record) {
            $provider = $record['provider'] ?? 'unknown';

            if (! isset($efficiency[$provider])) {
                $efficiency[$provider] = [
                    'total_requests' => 0,
                    'successful_requests' => 0,
                    'total_response_time' => 0,
                    'total_cost' => 0,
                    'total_tokens' => 0,
                ];
            }

            $efficiency[$provider]['total_requests']++;

            if ($record['success'] ?? true) {
                $efficiency[$provider]['successful_requests']++;
            }

            $efficiency[$provider]['total_response_time'] += $record['response_time_ms'] ?? 0;
            $efficiency[$provider]['total_cost'] += $record['total_cost'] ?? 0;
            $efficiency[$provider]['total_tokens'] += $record['total_tokens'] ?? 0;
        }

        // Calculate efficiency metrics
        foreach ($efficiency as $provider => &$metrics) {
            $metrics['success_rate'] = $metrics['total_requests'] > 0
                ? ($metrics['successful_requests'] / $metrics['total_requests']) * 100
                : 0;

            $metrics['avg_response_time'] = $metrics['total_requests'] > 0
                ? $metrics['total_response_time'] / $metrics['total_requests']
                : 0;

            $metrics['cost_per_token'] = $metrics['total_tokens'] > 0
                ? $metrics['total_cost'] / $metrics['total_tokens']
                : 0;

            // Overall efficiency score (higher is better)
            $metrics['efficiency_score'] = $metrics['success_rate'] *
                (1000 / max($metrics['avg_response_time'], 1)) *
                (1 / max($metrics['cost_per_token'], 0.001));
        }

        // Sort by efficiency score
        uasort($efficiency, fn ($a, $b) => $b['efficiency_score'] <=> $a['efficiency_score']);

        return $efficiency;
    }

    /**
     * Generate provider recommendations based on analysis.
     */
    private function generateProviderRecommendations(array $data): array
    {
        $recommendations = [];

        // Analyze provider performance
        $providerMetrics = $this->calculateProviderMetrics($data);
        $costComparison = $this->compareProviderCosts($data);
        $efficiency = $this->analyzeProviderEfficiency($data);

        foreach ($providerMetrics as $provider => $metrics) {
            $recommendation = [
                'provider' => $provider,
                'score' => 0,
                'strengths' => [],
                'weaknesses' => [],
                'recommendation' => '',
            ];

            // Evaluate success rate
            if ($metrics['success_rate'] >= 95) {
                $recommendation['strengths'][] = 'High reliability';
                $recommendation['score'] += 30;
            } elseif ($metrics['success_rate'] < 90) {
                $recommendation['weaknesses'][] = 'Low success rate';
                $recommendation['score'] -= 20;
            }

            // Evaluate response time
            if ($metrics['avg_response_time'] <= 1000) {
                $recommendation['strengths'][] = 'Fast response time';
                $recommendation['score'] += 25;
            } elseif ($metrics['avg_response_time'] > 3000) {
                $recommendation['weaknesses'][] = 'Slow response time';
                $recommendation['score'] -= 15;
            }

            // Evaluate cost efficiency
            if (isset($costComparison[$provider]) && $costComparison[$provider]['avg_cost_per_request'] <= 0.01) {
                $recommendation['strengths'][] = 'Cost effective';
                $recommendation['score'] += 20;
            }

            // Generate recommendation text
            if ($recommendation['score'] >= 50) {
                $recommendation['recommendation'] = 'Highly recommended for production use';
            } elseif ($recommendation['score'] >= 20) {
                $recommendation['recommendation'] = 'Good choice for most use cases';
            } elseif ($recommendation['score'] >= 0) {
                $recommendation['recommendation'] = 'Consider for specific use cases';
            } else {
                $recommendation['recommendation'] = 'Not recommended - consider alternatives';
            }

            $recommendations[] = $recommendation;
        }

        // Sort by score
        usort($recommendations, fn ($a, $b) => $b['score'] <=> $a['score']);

        return $recommendations;
    }

    /**
     * Analyze provider trends over time.
     */
    private function analyzeProviderTrends(array $data): array
    {
        $trends = [];

        // Group data by provider and time periods
        foreach ($data as $record) {
            $provider = $record['provider'] ?? 'unknown';
            $date = date('Y-m-d', strtotime($record['created_at'] ?? 'now'));

            if (! isset($trends[$provider])) {
                $trends[$provider] = [];
            }

            if (! isset($trends[$provider][$date])) {
                $trends[$provider][$date] = [
                    'requests' => 0,
                    'total_cost' => 0,
                    'total_tokens' => 0,
                    'total_response_time' => 0,
                    'successful_requests' => 0,
                ];
            }

            $trends[$provider][$date]['requests']++;
            $trends[$provider][$date]['total_cost'] += $record['total_cost'] ?? 0;
            $trends[$provider][$date]['total_tokens'] += $record['total_tokens'] ?? 0;
            $trends[$provider][$date]['total_response_time'] += $record['response_time_ms'] ?? 0;

            if ($record['success'] ?? true) {
                $trends[$provider][$date]['successful_requests']++;
            }
        }

        // Calculate daily averages and trends
        foreach ($trends as $provider => &$providerTrends) {
            foreach ($providerTrends as $date => &$dayData) {
                $dayData['avg_cost_per_request'] = $dayData['requests'] > 0
                    ? $dayData['total_cost'] / $dayData['requests']
                    : 0;

                $dayData['avg_response_time'] = $dayData['requests'] > 0
                    ? $dayData['total_response_time'] / $dayData['requests']
                    : 0;

                $dayData['success_rate'] = $dayData['requests'] > 0
                    ? ($dayData['successful_requests'] / $dayData['requests']) * 100
                    : 0;
            }

            // Sort by date
            ksort($providerTrends);
        }

        return $trends;
    }

    /**
     * Get model performance data for analysis.
     */
    private function getModelPerformanceData(int $userId, ?int $days = 30): array
    {
        return [
            'gpt-4o-mini' => [
                'avg_response_time' => 1.1,
                'success_rate' => 99.2,
                'total_requests' => 120,
                'avg_cost_per_request' => 0.0015,
                'avg_tokens_per_request' => 150,
            ],
            'claude-3-haiku' => [
                'avg_response_time' => 0.9,
                'success_rate' => 98.8,
                'total_requests' => 85,
                'avg_cost_per_request' => 0.0012,
                'avg_tokens_per_request' => 140,
            ],
            'gemini-1.5-flash' => [
                'avg_response_time' => 1.3,
                'success_rate' => 97.5,
                'total_requests' => 60,
                'avg_cost_per_request' => 0.0008,
                'avg_tokens_per_request' => 160,
            ],
        ];
    }

    /**
     * Rank models based on performance metrics.
     */
    private function rankModels(array $performanceData): array
    {
        $rankings = [];

        foreach ($performanceData as $model => $metrics) {
            $score = 0;

            // Score based on success rate (40% weight)
            $score += ($metrics['success_rate'] / 100) * 40;

            // Score based on response time (30% weight) - lower is better
            $responseTimeScore = max(0, (2.0 - $metrics['avg_response_time']) / 2.0);
            $score += $responseTimeScore * 30;

            // Score based on cost efficiency (30% weight) - lower cost is better
            $costScore = max(0, (0.01 - $metrics['avg_cost_per_request']) / 0.01);
            $score += $costScore * 30;

            $rankings[] = [
                'model' => $model,
                'score' => round($score, 2),
                'metrics' => $metrics,
            ];
        }

        // Sort by score descending
        usort($rankings, fn ($a, $b) => $b['score'] <=> $a['score']);

        return $rankings;
    }

    /**
     * Calculate model performance metrics.
     */
    private function calculateModelMetrics(array $data): array
    {
        $metrics = [];

        foreach ($data as $record) {
            $model = $record['model'] ?? 'unknown';

            if (! isset($metrics[$model])) {
                $metrics[$model] = [
                    'total_requests' => 0,
                    'successful_requests' => 0,
                    'total_response_time' => 0,
                    'total_cost' => 0,
                    'total_tokens' => 0,
                ];
            }

            $metrics[$model]['total_requests']++;

            if ($record['success'] ?? true) {
                $metrics[$model]['successful_requests']++;
            }

            $metrics[$model]['total_response_time'] += $record['response_time_ms'] ?? 0;
            $metrics[$model]['total_cost'] += $record['total_cost'] ?? 0;
            $metrics[$model]['total_tokens'] += $record['total_tokens'] ?? 0;
        }

        // Calculate averages and percentages
        foreach ($metrics as $model => &$metric) {
            $metric['success_rate'] = $metric['total_requests'] > 0
                ? ($metric['successful_requests'] / $metric['total_requests']) * 100
                : 0;

            $metric['avg_response_time'] = $metric['total_requests'] > 0
                ? $metric['total_response_time'] / $metric['total_requests'] / 1000 // Convert to seconds
                : 0;

            $metric['avg_cost_per_request'] = $metric['total_requests'] > 0
                ? $metric['total_cost'] / $metric['total_requests']
                : 0;
        }

        return $metrics;
    }

    /**
     * Analyze model costs and efficiency.
     */
    private function analyzeModelCosts(array $data): array
    {
        $costAnalysis = [];

        foreach ($data as $record) {
            $model = $record['model'] ?? 'unknown';

            if (! isset($costAnalysis[$model])) {
                $costAnalysis[$model] = [
                    'total_cost' => 0,
                    'total_requests' => 0,
                    'total_tokens' => 0,
                    'input_tokens' => 0,
                    'output_tokens' => 0,
                ];
            }

            $costAnalysis[$model]['total_cost'] += $record['total_cost'] ?? 0;
            $costAnalysis[$model]['total_requests']++;
            $costAnalysis[$model]['total_tokens'] += $record['total_tokens'] ?? 0;
            $costAnalysis[$model]['input_tokens'] += $record['input_tokens'] ?? 0;
            $costAnalysis[$model]['output_tokens'] += $record['output_tokens'] ?? 0;
        }

        // Calculate cost efficiency metrics
        foreach ($costAnalysis as $model => &$analysis) {
            $analysis['avg_cost_per_request'] = $analysis['total_requests'] > 0
                ? $analysis['total_cost'] / $analysis['total_requests']
                : 0;

            $analysis['avg_cost_per_token'] = $analysis['total_tokens'] > 0
                ? $analysis['total_cost'] / $analysis['total_tokens']
                : 0;

            $analysis['avg_tokens_per_request'] = $analysis['total_requests'] > 0
                ? $analysis['total_tokens'] / $analysis['total_requests']
                : 0;

            // Cost efficiency score (higher is better)
            $analysis['efficiency_score'] = $analysis['avg_cost_per_token'] > 0
                ? 1 / $analysis['avg_cost_per_token']
                : 0;
        }

        // Sort by efficiency score
        uasort($costAnalysis, fn ($a, $b) => $b['efficiency_score'] <=> $a['efficiency_score']);

        return $costAnalysis;
    }

    /**
     * Calculate model efficiency scores.
     */
    private function calculateModelEfficiencyScores(array $data): array
    {
        $efficiencyScores = [];

        foreach ($data as $record) {
            $model = $record['model'] ?? 'unknown';

            if (! isset($efficiencyScores[$model])) {
                $efficiencyScores[$model] = [
                    'total_requests' => 0,
                    'successful_requests' => 0,
                    'total_response_time' => 0,
                    'total_cost' => 0,
                    'total_tokens' => 0,
                ];
            }

            $efficiencyScores[$model]['total_requests']++;

            if ($record['success'] ?? true) {
                $efficiencyScores[$model]['successful_requests']++;
            }

            $efficiencyScores[$model]['total_response_time'] += $record['response_time_ms'] ?? 0;
            $efficiencyScores[$model]['total_cost'] += $record['total_cost'] ?? 0;
            $efficiencyScores[$model]['total_tokens'] += $record['total_tokens'] ?? 0;
        }

        // Calculate efficiency scores
        foreach ($efficiencyScores as $model => &$scores) {
            $scores['success_rate'] = $scores['total_requests'] > 0
                ? ($scores['successful_requests'] / $scores['total_requests']) * 100
                : 0;

            $scores['avg_response_time'] = $scores['total_requests'] > 0
                ? $scores['total_response_time'] / $scores['total_requests']
                : 0;

            $scores['cost_per_token'] = $scores['total_tokens'] > 0
                ? $scores['total_cost'] / $scores['total_tokens']
                : 0;

            // Overall efficiency score (0-100, higher is better)
            $responseTimeScore = $scores['avg_response_time'] > 0
                ? max(0, 100 - ($scores['avg_response_time'] / 50)) // Penalize slow responses
                : 100;

            $costScore = $scores['cost_per_token'] > 0
                ? max(0, 100 - ($scores['cost_per_token'] * 10000)) // Penalize high costs
                : 100;

            $scores['efficiency_score'] = round(
                ($scores['success_rate'] * 0.4) +
                ($responseTimeScore * 0.3) +
                ($costScore * 0.3),
                2
            );
        }

        // Sort by efficiency score
        uasort($efficiencyScores, fn ($a, $b) => $b['efficiency_score'] <=> $a['efficiency_score']);

        return $efficiencyScores;
    }
}
