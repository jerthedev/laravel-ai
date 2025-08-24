<?php

namespace JTD\LaravelAI\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Cost Anomaly Detected Event
 *
 * Fired when cost anomalies are detected that might indicate issues
 * such as unexpected high costs or unusual usage patterns.
 */
class CostAnomalyDetected
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  int  $userId  User ID
     * @param  float  $currentCost  Current cost that triggered anomaly
     * @param  float  $averageCost  User's average cost
     * @param  array  $costData  Detailed cost data
     * @param  array  $metadata  Additional metadata
     */
    public function __construct(
        public int $userId,
        public float $currentCost,
        public float $averageCost,
        public array $costData,
        public array $metadata = []
    ) {
        $this->metadata = array_merge([
            'detected_at' => now()->toISOString(),
            'anomaly_type' => $this->getAnomalyType(),
            'severity' => $this->getSeverity(),
            'multiplier' => $this->getMultiplier(),
        ], $metadata);
    }

    /**
     * Get the anomaly type based on cost difference.
     *
     * @return string
     */
    protected function getAnomalyType(): string
    {
        $multiplier = $this->getMultiplier();
        
        return match (true) {
            $multiplier >= 10 => 'extreme',
            $multiplier >= 5 => 'high',
            $multiplier >= 3 => 'moderate',
            default => 'minor',
        };
    }

    /**
     * Get the severity level.
     *
     * @return string
     */
    protected function getSeverity(): string
    {
        $multiplier = $this->getMultiplier();
        
        return match (true) {
            $multiplier >= 10 => 'critical',
            $multiplier >= 5 => 'high',
            $multiplier >= 3 => 'medium',
            default => 'low',
        };
    }

    /**
     * Get the cost multiplier compared to average.
     *
     * @return float
     */
    public function getMultiplier(): float
    {
        return $this->averageCost > 0 ? $this->currentCost / $this->averageCost : 0;
    }

    /**
     * Get formatted anomaly information.
     *
     * @return array
     */
    public function getAnomalyInfo(): array
    {
        return [
            'user_id' => $this->userId,
            'current_cost' => number_format($this->currentCost, 6),
            'average_cost' => number_format($this->averageCost, 6),
            'multiplier' => round($this->getMultiplier(), 2),
            'anomaly_type' => $this->getAnomalyType(),
            'severity' => $this->getSeverity(),
            'provider' => $this->costData['provider'] ?? 'unknown',
            'model' => $this->costData['model'] ?? 'unknown',
            'total_tokens' => $this->costData['total_tokens'] ?? 0,
            'detected_at' => $this->metadata['detected_at'],
        ];
    }

    /**
     * Check if this is a critical anomaly requiring immediate attention.
     *
     * @return bool
     */
    public function isCritical(): bool
    {
        return $this->getSeverity() === 'critical';
    }

    /**
     * Get potential causes for the anomaly.
     *
     * @return array
     */
    public function getPotentialCauses(): array
    {
        $causes = [];
        
        if ($this->getMultiplier() >= 10) {
            $causes[] = 'Possible API pricing change or billing error';
            $causes[] = 'Unusually large input or complex request';
        }
        
        if ($this->getMultiplier() >= 5) {
            $causes[] = 'Model change to more expensive option';
            $causes[] = 'Increased token usage or longer conversations';
        }
        
        if (($this->costData['total_tokens'] ?? 0) > 10000) {
            $causes[] = 'High token usage detected';
        }
        
        return $causes;
    }
}
