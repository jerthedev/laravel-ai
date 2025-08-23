<?php

namespace JTD\LaravelAI\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use JTD\LaravelAI\Models\ConversationTemplate;

/**
 * Template Management Service
 *
 * Provides high-level management interface for conversation templates including
 * discovery, categorization, bulk operations, and administrative functions.
 */
class TemplateManagementService
{
    protected ConversationTemplateService $templateService;

    protected TemplateValidationService $validationService;

    public function __construct(
        ConversationTemplateService $templateService,
        TemplateValidationService $validationService
    ) {
        $this->templateService = $templateService;
        $this->validationService = $validationService;
    }

    /**
     * Get template dashboard data.
     */
    public function getDashboardData(): array
    {
        return [
            'statistics' => $this->templateService->getTemplateStatistics(),
            'recent_templates' => $this->getRecentTemplates(5),
            'popular_templates' => $this->templateService->getPopularTemplates(5),
            'highly_rated_templates' => $this->templateService->getHighlyRatedTemplates(5),
            'categories' => $this->getCategoryStatistics(),
            'usage_trends' => $this->getUsageTrends(),
        ];
    }

    /**
     * Get recent templates.
     */
    public function getRecentTemplates(int $limit = 10): Collection
    {
        return ConversationTemplate::active()
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Get category statistics.
     */
    public function getCategoryStatistics(): array
    {
        $categories = $this->templateService->getAvailableCategories();
        $statistics = [];

        foreach ($categories as $key => $name) {
            $count = ConversationTemplate::active()->inCategory($key)->count();
            $statistics[] = [
                'key' => $key,
                'name' => $name,
                'count' => $count,
                'percentage' => $count > 0 ? round(($count / ConversationTemplate::active()->count()) * 100, 1) : 0,
            ];
        }

        return $statistics;
    }

    /**
     * Get usage trends over time.
     */
    public function getUsageTrends(int $days = 30): array
    {
        $trends = [];
        $startDate = now()->subDays($days);

        // Get daily usage counts
        $dailyUsage = DB::table('ai_conversations')
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->whereNotNull('template_id')
            ->where('created_at', '>=', $startDate)
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        // Fill in missing dates with zero counts
        for ($i = 0; $i < $days; $i++) {
            $date = $startDate->copy()->addDays($i)->format('Y-m-d');
            $trends[] = [
                'date' => $date,
                'count' => $dailyUsage[$date]->count ?? 0,
            ];
        }

        return $trends;
    }

    /**
     * Bulk operations on templates.
     */
    public function bulkOperation(array $templateIds, string $operation, array $data = []): array
    {
        $results = [
            'success' => [],
            'failed' => [],
            'total' => count($templateIds),
        ];

        DB::transaction(function () use ($templateIds, $operation, $data, &$results) {
            foreach ($templateIds as $templateId) {
                try {
                    $template = ConversationTemplate::findOrFail($templateId);

                    switch ($operation) {
                        case 'publish':
                            $template->publish();
                            $results['success'][] = $templateId;
                            break;

                        case 'unpublish':
                            $template->unpublish();
                            $results['success'][] = $templateId;
                            break;

                        case 'activate':
                            $template->update(['is_active' => true]);
                            $results['success'][] = $templateId;
                            break;

                        case 'deactivate':
                            $template->update(['is_active' => false]);
                            $results['success'][] = $templateId;
                            break;

                        case 'delete':
                            $template->delete();
                            $results['success'][] = $templateId;
                            break;

                        case 'update_category':
                            if (isset($data['category'])) {
                                $template->update(['category' => $data['category']]);
                                $results['success'][] = $templateId;
                            } else {
                                $results['failed'][] = ['id' => $templateId, 'error' => 'Category not provided'];
                            }
                            break;

                        case 'add_tags':
                            if (isset($data['tags']) && is_array($data['tags'])) {
                                foreach ($data['tags'] as $tag) {
                                    $template->addTag($tag);
                                }
                                $results['success'][] = $templateId;
                            } else {
                                $results['failed'][] = ['id' => $templateId, 'error' => 'Tags not provided'];
                            }
                            break;

                        default:
                            $results['failed'][] = ['id' => $templateId, 'error' => 'Unknown operation'];
                    }
                } catch (\Exception $e) {
                    $results['failed'][] = ['id' => $templateId, 'error' => $e->getMessage()];
                }
            }
        });

        Log::info('Bulk template operation completed', [
            'operation' => $operation,
            'total' => $results['total'],
            'success' => count($results['success']),
            'failed' => count($results['failed']),
        ]);

        return $results;
    }

    /**
     * Template discovery and recommendations.
     */
    public function discoverTemplates(array $criteria = []): array
    {
        $recommendations = [];

        // Popular in category
        if (! empty($criteria['category'])) {
            $recommendations['popular_in_category'] = $this->templateService
                ->getTemplatesByCategory($criteria['category'], 5);
        }

        // Similar templates (based on tags)
        if (! empty($criteria['tags'])) {
            $recommendations['similar_templates'] = $this->findSimilarTemplates($criteria['tags'], 5);
        }

        // Trending templates
        $recommendations['trending'] = $this->getTrendingTemplates(5);

        // Recently updated
        $recommendations['recently_updated'] = ConversationTemplate::active()
            ->public()
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get();

        return $recommendations;
    }

    /**
     * Find similar templates based on tags.
     */
    protected function findSimilarTemplates(array $tags, int $limit = 10): Collection
    {
        $query = ConversationTemplate::active()->public();

        foreach ($tags as $tag) {
            $query->whereJsonContains('tags', $tag);
        }

        return $query->orderByDesc('usage_count')
            ->limit($limit)
            ->get();
    }

    /**
     * Get trending templates (high recent usage).
     */
    protected function getTrendingTemplates(int $limit = 10): Collection
    {
        // Templates with high usage in the last 7 days
        $recentlyUsedTemplateIds = DB::table('ai_conversations')
            ->selectRaw('template_id, COUNT(*) as recent_usage')
            ->whereNotNull('template_id')
            ->where('created_at', '>=', now()->subDays(7))
            ->groupBy('template_id')
            ->orderByDesc('recent_usage')
            ->limit($limit * 2) // Get more to filter
            ->pluck('template_id');

        return ConversationTemplate::active()
            ->public()
            ->whereIn('id', $recentlyUsedTemplateIds)
            ->orderByDesc('usage_count')
            ->limit($limit)
            ->get();
    }

    /**
     * Template health check.
     */
    public function performHealthCheck(ConversationTemplate $template): array
    {
        $issues = [];
        $warnings = [];
        $suggestions = [];

        // Validation check
        $templateData = $template->toArray();
        $validationErrors = $this->validationService->validateTemplate($templateData);
        if (! empty($validationErrors)) {
            $issues[] = 'Template has validation errors: ' . json_encode($validationErrors);
        }

        // Compatibility check
        $compatibilityIssues = $this->validationService->checkCompatibility($templateData);
        if (! empty($compatibilityIssues)) {
            $warnings = array_merge($warnings, $compatibilityIssues);
        }

        // Usage analysis
        if ($template->usage_count === 0) {
            $warnings[] = 'Template has never been used';
        } elseif ($template->usage_count < 5) {
            $suggestions[] = 'Consider improving template description or tags to increase discoverability';
        }

        // Parameter analysis
        $parameters = $template->parameters ?? [];
        if (empty($parameters)) {
            $suggestions[] = 'Consider adding parameters to make template more flexible';
        } else {
            $requiredParams = array_filter($parameters, fn ($p) => $p['required'] ?? false);
            if (count($requiredParams) > 5) {
                $warnings[] = 'Template has many required parameters, which may reduce usability';
            }
        }

        // Content analysis
        $templateData = $template->template_data ?? [];
        if (empty($templateData['system_prompt']) && empty($templateData['initial_messages'])) {
            $issues[] = 'Template has no system prompt or initial messages';
        }

        // Rating analysis
        if ($template->avg_rating !== null && $template->avg_rating < 3.0) {
            $warnings[] = 'Template has low average rating';
        }

        return [
            'status' => empty($issues) ? (empty($warnings) ? 'healthy' : 'warning') : 'error',
            'issues' => $issues,
            'warnings' => $warnings,
            'suggestions' => $suggestions,
            'score' => $this->calculateHealthScore($issues, $warnings, $suggestions),
        ];
    }

    /**
     * Calculate health score (0-100).
     */
    protected function calculateHealthScore(array $issues, array $warnings, array $suggestions): int
    {
        $score = 100;

        // Deduct points for issues and warnings
        $score -= count($issues) * 20;
        $score -= count($warnings) * 10;
        $score -= count($suggestions) * 5;

        return max(0, $score);
    }

    /**
     * Generate template usage report.
     */
    public function generateUsageReport(ConversationTemplate $template, int $days = 30): array
    {
        $startDate = now()->subDays($days);

        // Get usage statistics
        $totalUsage = DB::table('ai_conversations')
            ->where('template_id', $template->id)
            ->where('created_at', '>=', $startDate)
            ->count();

        $dailyUsage = DB::table('ai_conversations')
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->where('template_id', $template->id)
            ->where('created_at', '>=', $startDate)
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Get user statistics
        $uniqueUsers = DB::table('ai_conversations')
            ->where('template_id', $template->id)
            ->where('created_at', '>=', $startDate)
            ->distinct('created_by_id')
            ->count('created_by_id');

        // Get parameter usage
        $parameterUsage = [];
        if (! empty($template->parameters)) {
            foreach (array_keys($template->parameters) as $paramName) {
                $parameterUsage[$paramName] = DB::table('ai_conversations')
                    ->where('template_id', $template->id)
                    ->where('created_at', '>=', $startDate)
                    ->whereJsonContains('metadata->template_parameters', [$paramName => null])
                    ->count();
            }
        }

        return [
            'template' => $template->only(['uuid', 'name', 'category']),
            'period' => [
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => now()->format('Y-m-d'),
                'days' => $days,
            ],
            'usage' => [
                'total_conversations' => $totalUsage,
                'unique_users' => $uniqueUsers,
                'avg_daily_usage' => $totalUsage / $days,
                'daily_breakdown' => $dailyUsage,
            ],
            'parameters' => [
                'parameter_usage' => $parameterUsage,
                'most_used_parameter' => ! empty($parameterUsage) ? array_keys($parameterUsage, max($parameterUsage))[0] : null,
            ],
            'generated_at' => now()->toISOString(),
        ];
    }

    /**
     * Archive old unused templates.
     */
    public function archiveUnusedTemplates(int $daysUnused = 90): array
    {
        $cutoffDate = now()->subDays($daysUnused);

        $templates = ConversationTemplate::active()
            ->where('created_at', '<', $cutoffDate)
            ->where('usage_count', 0)
            ->get();

        $archived = [];
        foreach ($templates as $template) {
            $template->update(['is_active' => false]);
            $archived[] = $template->uuid;
        }

        Log::info('Archived unused templates', [
            'count' => count($archived),
            'days_unused' => $daysUnused,
            'template_uuids' => $archived,
        ]);

        return [
            'archived_count' => count($archived),
            'template_uuids' => $archived,
        ];
    }

    /**
     * Get template analytics.
     */
    public function getTemplateAnalytics(): array
    {
        return [
            'overview' => $this->templateService->getTemplateStatistics(),
            'category_distribution' => $this->getCategoryStatistics(),
            'usage_trends' => $this->getUsageTrends(30),
            'top_templates' => [
                'most_used' => ConversationTemplate::active()->orderByDesc('usage_count')->limit(10)->get(),
                'highest_rated' => ConversationTemplate::active()->whereNotNull('avg_rating')->orderByDesc('avg_rating')->limit(10)->get(),
                'most_recent' => ConversationTemplate::active()->orderByDesc('created_at')->limit(10)->get(),
            ],
            'health_summary' => $this->getHealthSummary(),
        ];
    }

    /**
     * Get overall health summary of all templates.
     */
    protected function getHealthSummary(): array
    {
        $templates = ConversationTemplate::active()->get();
        $healthCounts = ['healthy' => 0, 'warning' => 0, 'error' => 0];

        foreach ($templates as $template) {
            $health = $this->performHealthCheck($template);
            $healthCounts[$health['status']]++;
        }

        return [
            'total_templates' => $templates->count(),
            'health_distribution' => $healthCounts,
            'health_percentage' => [
                'healthy' => $templates->count() > 0 ? round(($healthCounts['healthy'] / $templates->count()) * 100, 1) : 0,
                'warning' => $templates->count() > 0 ? round(($healthCounts['warning'] / $templates->count()) * 100, 1) : 0,
                'error' => $templates->count() > 0 ? round(($healthCounts['error'] / $templates->count()) * 100, 1) : 0,
            ],
        ];
    }
}
