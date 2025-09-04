<?php

namespace JTD\LaravelAI\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Intelligent Budget Cache Service
 *
 * High-performance caching service specifically designed for budget-related queries
 * with smart cache warming, invalidation strategies, and sub-millisecond lookups.
 */
class BudgetCacheService
{
    /**
     * Cache TTL for different data types (in seconds).
     */
    protected array $cacheTtls = [
        'budget_limits' => 3600,        // 1 hour - budget limits change infrequently
        'daily_spending' => 300,        // 5 minutes - balance between accuracy and performance
        'monthly_spending' => 600,      // 10 minutes - less frequent updates needed
        'project_spending' => 300,      // 5 minutes
        'organization_spending' => 600, // 10 minutes
        'per_request_limits' => 1800,   // 30 minutes
    ];

    /**
     * Cache key prefixes for organized cache management.
     */
    protected array $keyPrefixes = [
        'budget_limit' => 'bl',
        'daily_spending' => 'ds',
        'monthly_spending' => 'ms',
        'project_spending' => 'ps',
        'org_spending' => 'os',
        'project_limit' => 'pl',
        'org_limit' => 'ol',
    ];

    /**
     * Performance metrics tracking.
     */
    protected array $metrics = [
        'cache_hits' => 0,
        'cache_misses' => 0,
        'db_queries' => 0,
        'avg_lookup_time_ms' => 0,
    ];

    /**
     * Get budget limit for user with intelligent caching.
     *
     * @param  int  $userId  User ID
     * @param  string  $type  Budget type (daily, monthly, per_request)
     * @return float|null Budget limit or null if not set
     */
    public function getBudgetLimit(int $userId, string $type): ?float
    {
        $startTime = microtime(true);
        $cacheKey = $this->buildCacheKey('budget_limit', $userId, $type);

        $limit = Cache::remember($cacheKey, $this->cacheTtls['budget_limits'], function () use ($userId, $type) {
            $this->trackDbQuery();

            return DB::table('ai_budgets')
                ->where('user_id', $userId)
                ->where('type', $type)
                ->where('is_active', true)
                ->value('limit_amount');
        });

        $this->trackLookupTime(microtime(true) - $startTime);

        if (Cache::has($cacheKey)) {
            $this->metrics['cache_hits']++;
        } else {
            $this->metrics['cache_misses']++;
        }

        return $limit ? (float) $limit : null;
    }

    /**
     * Get daily spending for user with optimized time-based caching.
     *
     * @param  int  $userId  User ID
     * @param  Carbon|null  $date  Date to check (defaults to today)
     * @return float Daily spending amount
     */
    public function getDailySpending(int $userId, ?Carbon $date = null): float
    {
        $date = $date ?: today();
        $dateKey = $date->format('Y-m-d');
        $cacheKey = $this->buildCacheKey('daily_spending', $userId, $dateKey);

        $spending = Cache::remember($cacheKey, $this->cacheTtls['daily_spending'], function () use ($userId, $date) {
            $this->trackDbQuery();

            return (float) DB::table('ai_usage_costs')
                ->where('user_id', $userId)
                ->whereBetween('created_at', [$date->startOfDay(), $date->copy()->endOfDay()])
                ->sum('total_cost');
        });

        return $spending;
    }

    /**
     * Get monthly spending for user with month-aware caching.
     *
     * @param  int  $userId  User ID
     * @param  Carbon|null  $date  Date in the month to check (defaults to current month)
     * @return float Monthly spending amount
     */
    public function getMonthlySpending(int $userId, ?Carbon $date = null): float
    {
        $date = $date ?: now();
        $monthKey = $date->format('Y-m');
        $cacheKey = $this->buildCacheKey('monthly_spending', $userId, $monthKey);

        $spending = Cache::remember($cacheKey, $this->cacheTtls['monthly_spending'], function () use ($userId, $date) {
            $this->trackDbQuery();

            $startOfMonth = $date->copy()->startOfMonth();
            $endOfMonth = $date->copy()->endOfMonth();

            return (float) DB::table('ai_usage_costs')
                ->where('user_id', $userId)
                ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
                ->sum('total_cost');
        });

        return $spending;
    }

    /**
     * Get project spending with project-scoped caching.
     *
     * @param  string  $projectId  Project ID
     * @param  string  $period  Period (daily, monthly, all)
     * @return float Project spending amount
     */
    public function getProjectSpending(string $projectId, string $period = 'all'): float
    {
        $periodKey = $period === 'daily' ? today()->format('Y-m-d') :
                    ($period === 'monthly' ? now()->format('Y-m') : 'all');
        $cacheKey = $this->buildCacheKey('project_spending', $projectId, $period, $periodKey);

        $spending = Cache::remember($cacheKey, $this->cacheTtls['project_spending'], function () use ($projectId, $period) {
            $this->trackDbQuery();

            $query = DB::table('ai_usage_costs')
                ->whereJsonContains('metadata->context->project_id', $projectId);

            if ($period === 'daily') {
                $query->whereDate('created_at', today());
            } elseif ($period === 'monthly') {
                $query->whereYear('created_at', now()->year)
                    ->whereMonth('created_at', now()->month);
            }

            return (float) $query->sum('total_cost');
        });

        return $spending;
    }

    /**
     * Get organization spending with organization-scoped caching.
     *
     * @param  string  $organizationId  Organization ID
     * @param  string  $period  Period (daily, monthly, all)
     * @return float Organization spending amount
     */
    public function getOrganizationSpending(string $organizationId, string $period = 'all'): float
    {
        $periodKey = $period === 'daily' ? today()->format('Y-m-d') :
                    ($period === 'monthly' ? now()->format('Y-m') : 'all');
        $cacheKey = $this->buildCacheKey('org_spending', $organizationId, $period, $periodKey);

        $spending = Cache::remember($cacheKey, $this->cacheTtls['organization_spending'], function () use ($organizationId, $period) {
            $this->trackDbQuery();

            $query = DB::table('ai_usage_costs')
                ->whereJsonContains('metadata->context->organization_id', $organizationId);

            if ($period === 'daily') {
                $query->whereDate('created_at', today());
            } elseif ($period === 'monthly') {
                $query->whereYear('created_at', now()->year)
                    ->whereMonth('created_at', now()->month);
            }

            return (float) $query->sum('total_cost');
        });

        return $spending;
    }

    /**
     * Get project budget limit.
     *
     * @param  string  $projectId  Project ID
     * @param  string  $type  Budget type
     * @return float|null Project budget limit
     */
    public function getProjectBudgetLimit(string $projectId, string $type = 'project'): ?float
    {
        $cacheKey = $this->buildCacheKey('project_limit', $projectId, $type);

        $limit = Cache::remember($cacheKey, $this->cacheTtls['budget_limits'], function () use ($projectId, $type) {
            $this->trackDbQuery();

            return DB::table('ai_budgets')
                ->where('project_id', $projectId)
                ->where('type', $type)
                ->where('is_active', true)
                ->value('limit_amount');
        });

        return $limit ? (float) $limit : null;
    }

    /**
     * Get organization budget limit.
     *
     * @param  string  $organizationId  Organization ID
     * @param  string  $type  Budget type
     * @return float|null Organization budget limit
     */
    public function getOrganizationBudgetLimit(string $organizationId, string $type = 'organization'): ?float
    {
        $cacheKey = $this->buildCacheKey('org_limit', $organizationId, $type);

        $limit = Cache::remember($cacheKey, $this->cacheTtls['budget_limits'], function () use ($organizationId, $type) {
            $this->trackDbQuery();

            return DB::table('ai_budgets')
                ->where('organization_id', $organizationId)
                ->where('type', $type)
                ->where('is_active', true)
                ->value('limit_amount');
        });

        return $limit ? (float) $limit : null;
    }

    /**
     * Warm up cache for a set of users and common queries.
     *
     * @param  array  $userIds  User IDs to warm up cache for
     * @param  array  $types  Budget types to cache
     */
    public function warmUpCache(array $userIds, array $types = ['daily', 'monthly']): void
    {
        $startTime = microtime(true);
        $warmedKeys = 0;

        foreach ($userIds as $userId) {
            foreach ($types as $type) {
                try {
                    // Warm budget limits
                    $this->getBudgetLimit($userId, $type);
                    $warmedKeys++;

                    // Warm spending data
                    if ($type === 'daily') {
                        $this->getDailySpending($userId);
                        $warmedKeys++;
                    } elseif ($type === 'monthly') {
                        $this->getMonthlySpending($userId);
                        $warmedKeys++;
                    }
                } catch (\Exception $e) {
                    Log::warning('Cache warmup failed', [
                        'user_id' => $userId,
                        'type' => $type,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        $duration = (microtime(true) - $startTime) * 1000;

        Log::info('Budget cache warmup completed', [
            'warmed_keys' => $warmedKeys,
            'user_count' => count($userIds),
            'duration_ms' => round($duration, 2),
        ]);
    }

    /**
     * Invalidate cache for user when spending is updated.
     *
     * @param  int  $userId  User ID
     * @param  array  $context  Additional context for invalidation
     */
    public function invalidateUserCache(int $userId, array $context = []): void
    {
        $keysToInvalidate = [];

        // Invalidate user-specific caches
        $todayKey = today()->format('Y-m-d');
        $monthKey = now()->format('Y-m');

        $keysToInvalidate[] = $this->buildCacheKey('daily_spending', $userId, $todayKey);
        $keysToInvalidate[] = $this->buildCacheKey('monthly_spending', $userId, $monthKey);

        // Invalidate project caches if applicable
        if (isset($context['project_id'])) {
            $keysToInvalidate[] = $this->buildCacheKey('project_spending', $context['project_id'], 'daily', $todayKey);
            $keysToInvalidate[] = $this->buildCacheKey('project_spending', $context['project_id'], 'monthly', $monthKey);
            $keysToInvalidate[] = $this->buildCacheKey('project_spending', $context['project_id'], 'all', 'all');
        }

        // Invalidate organization caches if applicable
        if (isset($context['organization_id'])) {
            $keysToInvalidate[] = $this->buildCacheKey('org_spending', $context['organization_id'], 'daily', $todayKey);
            $keysToInvalidate[] = $this->buildCacheKey('org_spending', $context['organization_id'], 'monthly', $monthKey);
            $keysToInvalidate[] = $this->buildCacheKey('org_spending', $context['organization_id'], 'all', 'all');
        }

        // Invalidate all keys
        foreach ($keysToInvalidate as $key) {
            Cache::forget($key);
        }

        Log::debug('Budget cache invalidated', [
            'user_id' => $userId,
            'keys_invalidated' => count($keysToInvalidate),
            'context' => $context,
        ]);
    }

    /**
     * Get comprehensive budget data for user in a single optimized query.
     *
     * @param  int  $userId  User ID
     * @param  array  $types  Budget types to fetch
     * @return array Budget data with limits and spending
     */
    public function getUserBudgetData(int $userId, array $types = ['daily', 'monthly']): array
    {
        $budgetData = [];

        foreach ($types as $type) {
            $budgetData[$type] = [
                'limit' => $this->getBudgetLimit($userId, $type),
                'spending' => $type === 'daily'
                    ? $this->getDailySpending($userId)
                    : $this->getMonthlySpending($userId),
            ];

            // Calculate derived metrics
            if ($budgetData[$type]['limit'] && $budgetData[$type]['spending']) {
                $budgetData[$type]['remaining'] = $budgetData[$type]['limit'] - $budgetData[$type]['spending'];
                $budgetData[$type]['percentage_used'] = ($budgetData[$type]['spending'] / $budgetData[$type]['limit']) * 100;
                $budgetData[$type]['is_over_budget'] = $budgetData[$type]['spending'] > $budgetData[$type]['limit'];
            }
        }

        return $budgetData;
    }

    /**
     * Get cache performance metrics.
     *
     * @return array Performance metrics
     */
    public function getPerformanceMetrics(): array
    {
        $totalLookups = $this->metrics['cache_hits'] + $this->metrics['cache_misses'];
        $cacheHitRate = $totalLookups > 0 ? ($this->metrics['cache_hits'] / $totalLookups) * 100 : 0;

        return [
            'cache_hits' => $this->metrics['cache_hits'],
            'cache_misses' => $this->metrics['cache_misses'],
            'cache_hit_rate' => round($cacheHitRate, 2),
            'db_queries' => $this->metrics['db_queries'],
            'avg_lookup_time_ms' => round($this->metrics['avg_lookup_time_ms'], 3),
        ];
    }

    /**
     * Reset performance metrics.
     */
    public function resetMetrics(): void
    {
        $this->metrics = [
            'cache_hits' => 0,
            'cache_misses' => 0,
            'db_queries' => 0,
            'avg_lookup_time_ms' => 0,
        ];
    }

    /**
     * Build optimized cache key.
     *
     * @param  string  $type  Cache type
     * @param  mixed  ...$components  Key components
     * @return string Cache key
     */
    protected function buildCacheKey(string $type, ...$components): string
    {
        $prefix = $this->keyPrefixes[$type] ?? $type;
        $key = implode(':', array_merge([$prefix], array_map('strval', $components)));

        // Ensure key is under Redis/Memcached limits (250 chars)
        return strlen($key) > 240 ? $prefix . ':' . md5($key) : $key;
    }

    /**
     * Track database query for metrics.
     */
    protected function trackDbQuery(): void
    {
        $this->metrics['db_queries']++;
    }

    /**
     * Track lookup time for performance metrics.
     *
     * @param  float  $duration  Duration in seconds
     */
    protected function trackLookupTime(float $duration): void
    {
        $durationMs = $duration * 1000;
        $this->metrics['avg_lookup_time_ms'] =
            ($this->metrics['avg_lookup_time_ms'] + $durationMs) / 2;
    }

    /**
     * Clean up expired cache entries (if cache driver supports it).
     */
    public function cleanupExpiredCache(): int
    {
        $cleaned = 0;

        // This would be implemented based on the cache driver
        // For now, just return 0 as a placeholder
        return $cleaned;
    }

    /**
     * Get cache storage usage statistics.
     *
     * @return array Storage statistics
     */
    public function getCacheStorageStats(): array
    {
        // This would be implemented based on the cache driver
        return [
            'total_keys' => 0,
            'memory_usage_mb' => 0,
            'hit_ratio' => 0,
        ];
    }
}
