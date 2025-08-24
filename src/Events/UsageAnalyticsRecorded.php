<?php

namespace JTD\LaravelAI\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Usage Analytics Recorded Event
 *
 * Fired when usage analytics data is successfully recorded, enabling
 * downstream processing, reporting, and real-time dashboard updates.
 */
class UsageAnalyticsRecorded
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Analytics data.
     */
    public array $analyticsData;

    /**
     * Create a new event instance.
     *
     * @param  array  $analyticsData  Analytics data
     */
    public function __construct(array $analyticsData)
    {
        $this->analyticsData = $analyticsData;
    }

    /**
     * Get user ID from analytics data.
     *
     * @return int|null User ID
     */
    public function getUserId(): ?int
    {
        return $this->analyticsData['user_id'] ?? null;
    }

    /**
     * Get provider from analytics data.
     *
     * @return string|null Provider
     */
    public function getProvider(): ?string
    {
        return $this->analyticsData['provider'] ?? null;
    }

    /**
     * Get model from analytics data.
     *
     * @return string|null Model
     */
    public function getModel(): ?string
    {
        return $this->analyticsData['model'] ?? null;
    }

    /**
     * Get total tokens from analytics data.
     *
     * @return int Total tokens
     */
    public function getTotalTokens(): int
    {
        return $this->analyticsData['total_tokens'] ?? 0;
    }

    /**
     * Get processing time from analytics data.
     *
     * @return float Processing time in milliseconds
     */
    public function getProcessingTime(): float
    {
        return $this->analyticsData['processing_time_ms'] ?? 0;
    }

    /**
     * Check if request was successful.
     *
     * @return bool Success status
     */
    public function isSuccessful(): bool
    {
        return $this->analyticsData['success'] ?? true;
    }

    /**
     * Get conversation ID from analytics data.
     *
     * @return string|null Conversation ID
     */
    public function getConversationId(): ?string
    {
        return $this->analyticsData['conversation_id'] ?? null;
    }

    /**
     * Get timestamp from analytics data.
     *
     * @return \Carbon\Carbon|null Timestamp
     */
    public function getTimestamp(): ?\Carbon\Carbon
    {
        return $this->analyticsData['timestamp'] ?? null;
    }

    /**
     * Get analytics summary for logging/monitoring.
     *
     * @return array Analytics summary
     */
    public function getSummary(): array
    {
        return [
            'user_id' => $this->getUserId(),
            'provider' => $this->getProvider(),
            'model' => $this->getModel(),
            'total_tokens' => $this->getTotalTokens(),
            'processing_time_ms' => $this->getProcessingTime(),
            'success' => $this->isSuccessful(),
            'conversation_id' => $this->getConversationId(),
            'timestamp' => $this->getTimestamp()?->toISOString(),
        ];
    }

    /**
     * Get performance metrics from analytics data.
     *
     * @return array Performance metrics
     */
    public function getPerformanceMetrics(): array
    {
        return [
            'processing_time_ms' => $this->getProcessingTime(),
            'response_time_ms' => $this->analyticsData['response_time_ms'] ?? 0,
            'tokens_per_second' => $this->calculateTokensPerSecond(),
            'content_length' => $this->analyticsData['content_length'] ?? 0,
            'response_length' => $this->analyticsData['response_length'] ?? 0,
            'efficiency_score' => $this->calculateEfficiencyScore(),
        ];
    }

    /**
     * Calculate tokens per second.
     *
     * @return float Tokens per second
     */
    protected function calculateTokensPerSecond(): float
    {
        $processingTime = $this->getProcessingTime();
        $totalTokens = $this->getTotalTokens();
        
        if ($processingTime > 0 && $totalTokens > 0) {
            return ($totalTokens / $processingTime) * 1000; // Convert ms to seconds
        }
        
        return 0;
    }

    /**
     * Calculate efficiency score.
     *
     * @return float Efficiency score (0-100)
     */
    protected function calculateEfficiencyScore(): float
    {
        $tokensPerSecond = $this->calculateTokensPerSecond();
        $processingTime = $this->getProcessingTime();
        
        // Simple efficiency calculation based on tokens/second and processing time
        if ($tokensPerSecond > 0 && $processingTime > 0) {
            $timeScore = max(0, 100 - ($processingTime / 100)); // Penalty for slow processing
            $throughputScore = min(100, $tokensPerSecond); // Reward for high throughput
            
            return ($timeScore + $throughputScore) / 2;
        }
        
        return 0;
    }

    /**
     * Get usage patterns from analytics data.
     *
     * @return array Usage patterns
     */
    public function getUsagePatterns(): array
    {
        return [
            'hour' => $this->analyticsData['hour'] ?? null,
            'day_of_week' => $this->analyticsData['day_of_week'] ?? null,
            'week_of_year' => $this->analyticsData['week_of_year'] ?? null,
            'month' => $this->analyticsData['month'] ?? null,
            'quarter' => $this->analyticsData['quarter'] ?? null,
            'year' => $this->analyticsData['year'] ?? null,
        ];
    }

    /**
     * Check if this is a high-usage event.
     *
     * @return bool High usage indicator
     */
    public function isHighUsage(): bool
    {
        return $this->getTotalTokens() > 10000 || $this->getProcessingTime() > 30000;
    }

    /**
     * Check if this is a slow processing event.
     *
     * @return bool Slow processing indicator
     */
    public function isSlowProcessing(): bool
    {
        return $this->getProcessingTime() > 10000; // 10 seconds
    }

    /**
     * Get context data from analytics.
     *
     * @return array Context data
     */
    public function getContext(): array
    {
        return $this->analyticsData['context'] ?? [];
    }

    /**
     * Get error information if request failed.
     *
     * @return array|null Error information
     */
    public function getErrorInfo(): ?array
    {
        if (!$this->isSuccessful()) {
            return [
                'error_type' => $this->analyticsData['error_type'] ?? 'unknown',
                'has_error' => true,
            ];
        }
        
        return null;
    }

    /**
     * Convert analytics data to array for storage/transmission.
     *
     * @return array Analytics data array
     */
    public function toArray(): array
    {
        return $this->analyticsData;
    }

    /**
     * Get formatted analytics data for reporting.
     *
     * @return array Formatted data
     */
    public function getFormattedData(): array
    {
        return [
            'summary' => $this->getSummary(),
            'performance' => $this->getPerformanceMetrics(),
            'patterns' => $this->getUsagePatterns(),
            'context' => $this->getContext(),
            'error' => $this->getErrorInfo(),
            'flags' => [
                'high_usage' => $this->isHighUsage(),
                'slow_processing' => $this->isSlowProcessing(),
                'successful' => $this->isSuccessful(),
            ],
        ];
    }
}
