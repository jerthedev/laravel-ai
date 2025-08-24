<?php

namespace JTD\LaravelAI\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use JTD\LaravelAI\Models\AIConversation;
use JTD\LaravelAI\Models\AIConversationProviderHistory;
use JTD\LaravelAI\ValueObjects\AIResponse;

/**
 * Provider History Service
 *
 * Manages tracking and analysis of provider usage history within conversations.
 */
class ProviderHistoryService
{
    /**
     * Start tracking a new provider session.
     */
    public function startProviderSession(
        AIConversation $conversation,
        string $switchType,
        array $context = []
    ): AIConversationProviderHistory {
        // End any active session for this conversation
        $this->endActiveSession($conversation);

        $historyData = [
            'switch_type' => $switchType,
            'switch_reason' => $context['reason'] ?? null,
            'switch_context' => $context,
            'previous_provider_name' => $context['previous_provider'] ?? null,
            'previous_model_name' => $context['previous_model'] ?? null,
        ];

        $history = AIConversationProviderHistory::createForSwitch(
            $conversation,
            $switchType,
            $historyData
        );

        Log::info('Provider session started', [
            'conversation_id' => $conversation->id,
            'provider' => $conversation->provider_name,
            'model' => $conversation->model_name,
            'switch_type' => $switchType,
            'history_id' => $history->id,
        ]);

        return $history;
    }

    /**
     * End the active provider session for a conversation.
     */
    public function endActiveSession(AIConversation $conversation): ?AIConversationProviderHistory
    {
        $activeSession = $this->getActiveSession($conversation);

        if ($activeSession) {
            $activeSession->endSession();

            Log::info('Provider session ended', [
                'conversation_id' => $conversation->id,
                'provider' => $activeSession->provider_name,
                'duration_minutes' => $activeSession->duration,
                'message_count' => $activeSession->message_count,
                'total_cost' => $activeSession->total_cost,
            ]);
        }

        return $activeSession;
    }

    /**
     * Get the active provider session for a conversation.
     */
    public function getActiveSession(AIConversation $conversation): ?AIConversationProviderHistory
    {
        return AIConversationProviderHistory::where('ai_conversation_id', $conversation->id)
            ->active()
            ->latest('started_at')
            ->first();
    }

    /**
     * Update session metrics after a message exchange.
     */
    public function updateSessionMetrics(
        AIConversation $conversation,
        AIResponse $response,
        bool $successful = true
    ): void {
        $activeSession = $this->getActiveSession($conversation);

        if (! $activeSession) {
            // Create initial session if none exists
            $activeSession = $this->startProviderSession($conversation, 'initial');
        }

        // Update metrics
        $activeSession->message_count += 1;
        $activeSession->total_input_tokens += $response->tokenUsage->inputTokens;
        $activeSession->total_output_tokens += $response->tokenUsage->outputTokens;
        $activeSession->total_cost += $response->tokenUsage->totalCost;

        if ($successful) {
            $activeSession->successful_requests += 1;
        } else {
            $activeSession->failed_requests += 1;
        }

        // Update average response time if available
        if (isset($response->metadata['response_time_ms'])) {
            $currentAvg = $activeSession->avg_response_time_ms ?? 0;
            $totalRequests = $activeSession->successful_requests + $activeSession->failed_requests;

            $activeSession->avg_response_time_ms = (int) (
                ($currentAvg * ($totalRequests - 1) + $response->metadata['response_time_ms']) / $totalRequests
            );
        }

        $activeSession->updatePerformanceMetrics();
    }

    /**
     * Get provider history for a conversation.
     */
    public function getConversationHistory(AIConversation $conversation): Collection
    {
        return AIConversationProviderHistory::where('ai_conversation_id', $conversation->id)
            ->with(['provider', 'model'])
            ->orderBy('started_at', 'asc')
            ->get();
    }

    /**
     * Get provider usage statistics.
     */
    public function getProviderStatistics(array $filters = []): array
    {
        $query = AIConversationProviderHistory::query();

        // Apply filters
        if (isset($filters['provider'])) {
            $query->forProvider($filters['provider']);
        }

        if (isset($filters['start_date']) && isset($filters['end_date'])) {
            $query->withinDateRange(
                Carbon::parse($filters['start_date']),
                Carbon::parse($filters['end_date'])
            );
        }

        if (isset($filters['switch_type'])) {
            $query->bySwitchType($filters['switch_type']);
        }

        $sessions = $query->get();

        return [
            'total_sessions' => $sessions->count(),
            'total_messages' => $sessions->sum('message_count'),
            'total_cost' => $sessions->sum('total_cost'),
            'total_tokens' => $sessions->sum(function ($session) {
                return $session->total_input_tokens + $session->total_output_tokens;
            }),
            'average_session_duration' => $sessions->where('ended_at', '!=', null)->avg('duration'),
            'success_rate' => $sessions->avg('success_rate'),
            'provider_breakdown' => $this->getProviderBreakdown($sessions),
            'switch_type_breakdown' => $this->getSwitchTypeBreakdown($sessions),
            'cost_efficiency' => $this->getCostEfficiencyMetrics($sessions),
        ];
    }

    /**
     * Get provider breakdown statistics.
     */
    protected function getProviderBreakdown(Collection $sessions): array
    {
        return $sessions->groupBy('provider_name')
            ->map(function ($providerSessions, $providerName) {
                return [
                    'provider' => $providerName,
                    'session_count' => $providerSessions->count(),
                    'message_count' => $providerSessions->sum('message_count'),
                    'total_cost' => $providerSessions->sum('total_cost'),
                    'average_cost_per_message' => $providerSessions->avg('cost_per_message'),
                    'success_rate' => $providerSessions->avg('success_rate'),
                    'average_response_time' => $providerSessions->avg('avg_response_time_ms'),
                ];
            })
            ->values()
            ->toArray();
    }

    /**
     * Get switch type breakdown statistics.
     */
    protected function getSwitchTypeBreakdown(Collection $sessions): array
    {
        return $sessions->groupBy('switch_type')
            ->map(function ($typeSessions, $switchType) use ($sessions) {
                return [
                    'switch_type' => $switchType,
                    'count' => $typeSessions->count(),
                    'percentage' => round(($typeSessions->count() / $sessions->count()) * 100, 2),
                    'average_cost' => $typeSessions->avg('total_cost'),
                    'success_rate' => $typeSessions->avg('success_rate'),
                ];
            })
            ->values()
            ->toArray();
    }

    /**
     * Get cost efficiency metrics.
     */
    protected function getCostEfficiencyMetrics(Collection $sessions): array
    {
        $activeSessions = $sessions->where('ended_at', '!=', null);

        return [
            'most_cost_effective_provider' => $sessions->sortBy('cost_per_message')->first()?->provider_name,
            'least_cost_effective_provider' => $sessions->sortByDesc('cost_per_message')->first()?->provider_name,
            'average_cost_per_token' => $sessions->sum('total_cost') / max($sessions->sum('total_tokens'), 1),
            'cost_variance_by_provider' => $this->calculateCostVariance($sessions),
        ];
    }

    /**
     * Calculate cost variance by provider.
     */
    protected function calculateCostVariance(Collection $sessions): array
    {
        return $sessions->groupBy('provider_name')
            ->map(function ($providerSessions) {
                $costs = $providerSessions->pluck('cost_per_message')->filter();
                $mean = $costs->avg();
                $variance = $costs->map(function ($cost) use ($mean) {
                    return pow($cost - $mean, 2);
                })->avg();

                return [
                    'mean_cost_per_message' => $mean,
                    'variance' => $variance,
                    'standard_deviation' => sqrt($variance),
                ];
            })
            ->toArray();
    }

    /**
     * Get fallback frequency analysis.
     */
    public function getFallbackAnalysis(array $filters = []): array
    {
        $fallbackSessions = AIConversationProviderHistory::bySwitchType('fallback');

        if (isset($filters['start_date']) && isset($filters['end_date'])) {
            $fallbackSessions->withinDateRange(
                Carbon::parse($filters['start_date']),
                Carbon::parse($filters['end_date'])
            );
        }

        $sessions = $fallbackSessions->get();

        return [
            'total_fallbacks' => $sessions->count(),
            'fallback_rate' => $this->calculateFallbackRate($filters),
            'most_common_fallback_source' => $sessions->groupBy('previous_provider_name')
                ->sortByDesc(function ($group) {
                    return $group->count();
                })
                ->keys()
                ->first(),
            'most_reliable_fallback_target' => $sessions->groupBy('provider_name')
                ->map(function ($group) {
                    return $group->avg('success_rate');
                })
                ->sortByDesc(function ($value) {
                    return $value;
                })
                ->keys()
                ->first(),
            'average_fallback_success_rate' => $sessions->avg('success_rate'),
        ];
    }

    /**
     * Calculate overall fallback rate.
     */
    protected function calculateFallbackRate(array $filters): float
    {
        $totalSessions = AIConversationProviderHistory::query();
        $fallbackSessions = AIConversationProviderHistory::bySwitchType('fallback');

        if (isset($filters['start_date']) && isset($filters['end_date'])) {
            $dateRange = [Carbon::parse($filters['start_date']), Carbon::parse($filters['end_date'])];
            $totalSessions->withinDateRange(...$dateRange);
            $fallbackSessions->withinDateRange(...$dateRange);
        }

        $total = $totalSessions->count();
        $fallbacks = $fallbackSessions->count();

        return $total > 0 ? round(($fallbacks / $total) * 100, 2) : 0.0;
    }
}
