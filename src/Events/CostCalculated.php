<?php

namespace JTD\LaravelAI\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Models\AIResponse;

/**
 * Event fired when cost is calculated for an AI interaction.
 *
 * This event is fired after a response is generated and cost calculation
 * is completed. It enables background processing for cost tracking,
 * budget monitoring, and analytics.
 */
class CostCalculated
{
    use Dispatchable;
    use SerializesModels;

    /**
     * The original message.
     */
    public AIMessage $message;

    /**
     * The generated response.
     */
    public AIResponse $response;

    /**
     * The provider used.
     */
    public string $provider;

    /**
     * The model used.
     */
    public string $model;

    /**
     * Total cost in USD.
     */
    public float $totalCost;

    /**
     * Input tokens cost.
     */
    public float $inputCost;

    /**
     * Output tokens cost.
     */
    public float $outputCost;

    /**
     * Cost breakdown details.
     */
    public array $costBreakdown;

    /**
     * Conversation ID if applicable.
     */
    public ?string $conversationId;

    /**
     * User ID if applicable.
     *
     * @var mixed
     */
    public $userId;

    /**
     * Timestamp when cost was calculated.
     */
    public \DateTime $calculatedAt;

    /**
     * Create a new event instance.
     *
     * @param  mixed  $userId
     */
    public function __construct(
        AIMessage $message,
        AIResponse $response,
        string $provider,
        string $model,
        float $totalCost,
        float $inputCost,
        float $outputCost,
        array $costBreakdown = [],
        ?string $conversationId = null,
        $userId = null,
        ?\DateTime $calculatedAt = null
    ) {
        $this->message = $message;
        $this->response = $response;
        $this->provider = $provider;
        $this->model = $model;
        $this->totalCost = $totalCost;
        $this->inputCost = $inputCost;
        $this->outputCost = $outputCost;
        $this->costBreakdown = $costBreakdown;
        $this->conversationId = $conversationId;
        $this->userId = $userId;
        $this->calculatedAt = $calculatedAt ?? new \DateTime;
    }

    /**
     * Get the cost per token for input.
     */
    public function getInputCostPerToken(): float
    {
        $inputTokens = $this->response->tokenUsage->inputTokens;
        return $inputTokens > 0 ? $this->inputCost / $inputTokens : 0;
    }

    /**
     * Get the cost per token for output.
     */
    public function getOutputCostPerToken(): float
    {
        $outputTokens = $this->response->tokenUsage->outputTokens;
        return $outputTokens > 0 ? $this->outputCost / $outputTokens : 0;
    }

    /**
     * Get formatted cost string.
     */
    public function getFormattedCost(): string
    {
        return '$' . number_format($this->totalCost, 6);
    }

    /**
     * Check if cost exceeds a threshold.
     */
    public function exceedsThreshold(float $threshold): bool
    {
        return $this->totalCost > $threshold;
    }
}
