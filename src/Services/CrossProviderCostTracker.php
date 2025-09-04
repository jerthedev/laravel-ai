<?php

namespace JTD\LaravelAI\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use JTD\LaravelAI\Models\AIConversation;
use JTD\LaravelAI\ValueObjects\AIResponse;

/**
 * Cross-Provider Cost Tracker
 *
 * Tracks and analyzes costs across multiple providers within conversations,
 * providing insights into cost efficiency and provider switching impact.
 */
class CrossProviderCostTracker
{
    protected ProviderHistoryService $historyService;

    public function __construct(ProviderHistoryService $historyService)
    {
        $this->historyService = $historyService;
    }

    /**
     * Track cost for a message exchange across providers.
     */
    public function trackMessageCost(
        AIConversation $conversation,
        AIResponse $response,
        array $context = []
    ): void {
        // Update the active provider session
        $this->historyService->updateSessionMetrics($conversation, $response, true);

        // Update conversation totals
        $this->updateConversationTotals($conversation, $response);

        // Log cost tracking
        Log::debug('Cross-provider cost tracked', [
            'conversation_id' => $conversation->id,
            'provider' => $conversation->provider_name,
            'model' => $conversation->model_name,
            'message_cost' => $response->tokenUsage->totalCost,
            'total_conversation_cost' => $conversation->total_cost,
        ]);
    }

    /**
     * Update conversation totals with new cost data.
     */
    protected function updateConversationTotals(
        AIConversation $conversation,
        AIResponse $response
    ): void {
        $conversation->increment('total_input_tokens', $response->tokenUsage->input_tokens);
        $conversation->increment('total_output_tokens', $response->tokenUsage->output_tokens);
        $conversation->increment('total_cost', $response->tokenUsage->totalCost);
        $conversation->increment('total_messages');
        $conversation->increment('total_requests');
        $conversation->increment('successful_requests');

        $conversation->touch('last_activity_at');
    }

    /**
     * Get comprehensive cost analysis for a conversation.
     */
    public function getConversationCostAnalysis(AIConversation $conversation): array
    {
        $providerHistory = $this->historyService->getConversationHistory($conversation);

        return [
            'total_cost' => $conversation->total_cost,
            'total_tokens' => $conversation->total_input_tokens + $conversation->total_output_tokens,
            'total_messages' => $conversation->total_messages,
            'provider_breakdown' => $this->getProviderCostBreakdown($providerHistory),
            'cost_efficiency' => $this->calculateCostEfficiency($providerHistory),
            'switching_impact' => $this->analyzeSwitchingImpact($providerHistory),
            'cost_trends' => $this->analyzeCostTrends($providerHistory),
            'recommendations' => $this->generateCostRecommendations($providerHistory),
        ];
    }

    /**
     * Get cost breakdown by provider.
     */
    protected function getProviderCostBreakdown(Collection $providerHistory): array
    {
        return $providerHistory->groupBy('provider_name')
            ->map(function ($sessions, $providerName) {
                $totalCost = $sessions->sum('total_cost');
                $totalTokens = $sessions->sum(function ($session) {
                    return $session->total_input_tokens + $session->total_output_tokens;
                });
                $totalMessages = $sessions->sum('message_count');

                return [
                    'provider' => $providerName,
                    'total_cost' => $totalCost,
                    'total_tokens' => $totalTokens,
                    'total_messages' => $totalMessages,
                    'cost_per_token' => $totalTokens > 0 ? $totalCost / $totalTokens : 0,
                    'cost_per_message' => $totalMessages > 0 ? $totalCost / $totalMessages : 0,
                    'session_count' => $sessions->count(),
                    'average_session_cost' => $sessions->avg('total_cost'),
                    'cost_percentage' => 0, // Will be calculated later
                ];
            })
            ->tap(function ($breakdown) {
                $totalCost = $breakdown->sum('total_cost');
                $breakdown->transform(function ($item) use ($totalCost) {
                    $item['cost_percentage'] = $totalCost > 0
                        ? round(($item['total_cost'] / $totalCost) * 100, 2)
                        : 0;

                    return $item;
                });
            })
            ->values()
            ->toArray();
    }

    /**
     * Calculate cost efficiency metrics.
     */
    protected function calculateCostEfficiency(Collection $providerHistory): array
    {
        $totalCost = $providerHistory->sum('total_cost');
        $totalTokens = $providerHistory->sum(function ($session) {
            return $session->total_input_tokens + $session->total_output_tokens;
        });
        $totalMessages = $providerHistory->sum('message_count');

        // Find most and least efficient providers
        $providerEfficiency = $providerHistory->groupBy('provider_name')
            ->map(function ($sessions) {
                $cost = $sessions->sum('total_cost');
                $tokens = $sessions->sum(function ($session) {
                    return $session->total_input_tokens + $session->total_output_tokens;
                });

                return $tokens > 0 ? $cost / $tokens : PHP_FLOAT_MAX;
            })
            ->sort();

        return [
            'overall_cost_per_token' => $totalTokens > 0 ? $totalCost / $totalTokens : 0,
            'overall_cost_per_message' => $totalMessages > 0 ? $totalCost / $totalMessages : 0,
            'most_efficient_provider' => $providerEfficiency->keys()->first(),
            'least_efficient_provider' => $providerEfficiency->keys()->last(),
            'efficiency_variance' => $this->calculateEfficiencyVariance($providerHistory),
            'potential_savings' => $this->calculatePotentialSavings($providerHistory),
        ];
    }

    /**
     * Analyze the impact of provider switching on costs.
     */
    protected function analyzeSwitchingImpact(Collection $providerHistory): array
    {
        $switches = $providerHistory->where('switch_type', '!=', 'initial');

        if ($switches->isEmpty()) {
            return [
                'total_switches' => 0,
                'cost_impact' => 0,
                'average_cost_change' => 0,
                'beneficial_switches' => 0,
                'detrimental_switches' => 0,
            ];
        }

        $costChanges = [];
        $beneficialSwitches = 0;
        $detrimentalSwitches = 0;

        foreach ($switches as $switch) {
            $previousSession = $providerHistory
                ->where('started_at', '<', $switch->started_at)
                ->where('previous_provider_name', $switch->previous_provider_name)
                ->last();

            if ($previousSession) {
                $previousCostPerMessage = $previousSession->cost_per_message ?? 0;
                $currentCostPerMessage = $switch->cost_per_message ?? 0;

                $costChange = $currentCostPerMessage - $previousCostPerMessage;
                $costChanges[] = $costChange;

                if ($costChange < 0) {
                    $beneficialSwitches++;
                } elseif ($costChange > 0) {
                    $detrimentalSwitches++;
                }
            }
        }

        return [
            'total_switches' => $switches->count(),
            'cost_impact' => array_sum($costChanges),
            'average_cost_change' => count($costChanges) > 0 ? array_sum($costChanges) / count($costChanges) : 0,
            'beneficial_switches' => $beneficialSwitches,
            'detrimental_switches' => $detrimentalSwitches,
            'switch_success_rate' => $switches->count() > 0
                ? round(($beneficialSwitches / $switches->count()) * 100, 2)
                : 0,
        ];
    }

    /**
     * Analyze cost trends over time.
     */
    protected function analyzeCostTrends(Collection $providerHistory): array
    {
        if ($providerHistory->count() < 2) {
            return ['trend' => 'insufficient_data'];
        }

        $sessions = $providerHistory->sortBy('started_at');
        $firstHalf = $sessions->take(ceil($sessions->count() / 2));
        $secondHalf = $sessions->skip(ceil($sessions->count() / 2));

        $firstHalfAvgCost = $firstHalf->avg('cost_per_message') ?? 0;
        $secondHalfAvgCost = $secondHalf->avg('cost_per_message') ?? 0;

        $trendDirection = $secondHalfAvgCost > $firstHalfAvgCost ? 'increasing' : 'decreasing';
        $trendMagnitude = abs($secondHalfAvgCost - $firstHalfAvgCost);

        return [
            'trend' => $trendDirection,
            'magnitude' => $trendMagnitude,
            'first_half_avg_cost' => $firstHalfAvgCost,
            'second_half_avg_cost' => $secondHalfAvgCost,
            'cost_volatility' => $this->calculateCostVolatility($sessions),
        ];
    }

    /**
     * Generate cost optimization recommendations.
     */
    protected function generateCostRecommendations(Collection $providerHistory): array
    {
        $recommendations = [];

        // Analyze provider efficiency
        $providerEfficiency = $providerHistory->groupBy('provider_name')
            ->map(function ($sessions) {
                return $sessions->avg('cost_per_message');
            })
            ->sort();

        if ($providerEfficiency->count() > 1) {
            $mostEfficient = $providerEfficiency->keys()->first();
            $leastEfficient = $providerEfficiency->keys()->last();

            if ($providerEfficiency->last() > $providerEfficiency->first() * 1.5) {
                $recommendations[] = [
                    'type' => 'provider_optimization',
                    'priority' => 'high',
                    'message' => "Consider using {$mostEfficient} more frequently. It's significantly more cost-effective than {$leastEfficient}.",
                    'potential_savings' => $this->calculateProviderSwitchSavings($providerHistory, $mostEfficient),
                ];
            }
        }

        // Analyze switching patterns
        $switches = $providerHistory->where('switch_type', '!=', 'initial');
        $fallbackSwitches = $switches->where('switch_type', 'fallback');

        if ($fallbackSwitches->count() > $switches->count() * 0.3) {
            $recommendations[] = [
                'type' => 'reliability_improvement',
                'priority' => 'medium',
                'message' => 'High fallback rate detected. Consider reviewing primary provider reliability.',
                'fallback_rate' => round(($fallbackSwitches->count() / $switches->count()) * 100, 2),
            ];
        }

        return $recommendations;
    }

    /**
     * Calculate efficiency variance across providers.
     */
    protected function calculateEfficiencyVariance(Collection $providerHistory): float
    {
        $costPerMessage = $providerHistory->pluck('cost_per_message')->filter();

        if ($costPerMessage->count() < 2) {
            return 0.0;
        }

        $mean = $costPerMessage->avg();
        $variance = $costPerMessage->map(function ($cost) use ($mean) {
            return pow($cost - $mean, 2);
        })->avg();

        return round($variance, 6);
    }

    /**
     * Calculate potential savings from optimal provider usage.
     */
    protected function calculatePotentialSavings(Collection $providerHistory): float
    {
        $providerEfficiency = $providerHistory->groupBy('provider_name')
            ->map(function ($sessions) {
                return [
                    'cost_per_message' => $sessions->avg('cost_per_message'),
                    'message_count' => $sessions->sum('message_count'),
                ];
            });

        if ($providerEfficiency->count() < 2) {
            return 0.0;
        }

        $mostEfficientCost = $providerEfficiency->min('cost_per_message');
        $totalMessages = $providerEfficiency->sum('message_count');
        $actualCost = $providerHistory->sum('total_cost');
        $optimalCost = $totalMessages * $mostEfficientCost;

        return max(0, $actualCost - $optimalCost);
    }

    /**
     * Calculate cost volatility.
     */
    protected function calculateCostVolatility(Collection $sessions): float
    {
        $costs = $sessions->pluck('cost_per_message')->filter();

        if ($costs->count() < 2) {
            return 0.0;
        }

        $mean = $costs->avg();
        $standardDeviation = sqrt($costs->map(function ($cost) use ($mean) {
            return pow($cost - $mean, 2);
        })->avg());

        return $mean > 0 ? round(($standardDeviation / $mean) * 100, 2) : 0.0;
    }

    /**
     * Calculate potential savings from switching to a specific provider.
     */
    protected function calculateProviderSwitchSavings(
        Collection $providerHistory,
        string $targetProvider
    ): float {
        $targetSessions = $providerHistory->where('provider_name', $targetProvider);
        $otherSessions = $providerHistory->where('provider_name', '!=', $targetProvider);

        if ($targetSessions->isEmpty() || $otherSessions->isEmpty()) {
            return 0.0;
        }

        $targetCostPerMessage = $targetSessions->avg('cost_per_message');
        $otherCostPerMessage = $otherSessions->avg('cost_per_message');
        $otherMessageCount = $otherSessions->sum('message_count');

        return max(0, ($otherCostPerMessage - $targetCostPerMessage) * $otherMessageCount);
    }
}
