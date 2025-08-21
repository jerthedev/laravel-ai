<?php

namespace JTD\LaravelAI\Models;

/**
 * Data Transfer Object for token usage and cost tracking.
 *
 * Tracks input tokens, output tokens, total tokens, and associated costs
 * for AI provider requests and responses.
 */
class TokenUsage
{
    /**
     * @var int Number of input tokens used
     */
    public int $inputTokens;

    /**
     * @var int Number of output tokens generated
     */
    public int $outputTokens;

    /**
     * @var int Total tokens used (input + output)
     */
    public int $totalTokens;

    /**
     * @var float|null Cost for input tokens
     */
    public ?float $inputCost;

    /**
     * @var float|null Cost for output tokens
     */
    public ?float $outputCost;

    /**
     * @var float|null Total cost (input + output)
     */
    public ?float $totalCost;

    /**
     * @var string Currency for cost calculations
     */
    public string $currency;

    /**
     * @var array|null Detailed cost breakdown
     */
    public ?array $costBreakdown;

    /**
     * @var string|null Model used for cost calculation
     */
    public ?string $model;

    /**
     * @var string|null Provider used for cost calculation
     */
    public ?string $provider;

    /**
     * Create a new TokenUsage instance.
     *
     * @param  int  $inputTokens  Number of input tokens
     * @param  int  $outputTokens  Number of output tokens
     * @param  int|null  $totalTokens  Total tokens (calculated if null)
     * @param  float|null  $inputCost  Cost for input tokens
     * @param  float|null  $outputCost  Cost for output tokens
     * @param  float|null  $totalCost  Total cost (calculated if null)
     * @param  string  $currency  Currency code
     * @param  array|null  $costBreakdown  Detailed cost breakdown
     * @param  string|null  $model  Model used
     * @param  string|null  $provider  Provider used
     */
    public function __construct(
        int $inputTokens,
        int $outputTokens,
        ?int $totalTokens = null,
        ?float $inputCost = null,
        ?float $outputCost = null,
        ?float $totalCost = null,
        string $currency = 'USD',
        ?array $costBreakdown = null,
        ?string $model = null,
        ?string $provider = null
    ) {
        $this->inputTokens = $inputTokens;
        $this->outputTokens = $outputTokens;
        $this->totalTokens = $totalTokens ?? ($inputTokens + $outputTokens);
        $this->inputCost = $inputCost;
        $this->outputCost = $outputCost;
        $this->totalCost = $totalCost ?? (($inputCost ?? 0) + ($outputCost ?? 0));
        $this->currency = $currency;
        $this->costBreakdown = $costBreakdown;
        $this->model = $model;
        $this->provider = $provider;
    }

    /**
     * Create a TokenUsage instance with only token counts.
     *
     * @param  int  $inputTokens  Input tokens
     * @param  int  $outputTokens  Output tokens
     */
    public static function create(int $inputTokens, int $outputTokens): static
    {
        return new static($inputTokens, $outputTokens);
    }

    /**
     * Create a TokenUsage instance with tokens and costs.
     *
     * @param  int  $inputTokens  Input tokens
     * @param  int  $outputTokens  Output tokens
     * @param  float  $inputCost  Input cost
     * @param  float  $outputCost  Output cost
     * @param  string  $currency  Currency
     */
    public static function withCosts(
        int $inputTokens,
        int $outputTokens,
        float $inputCost,
        float $outputCost,
        string $currency = 'USD'
    ): static {
        return new static(
            $inputTokens,
            $outputTokens,
            null,
            $inputCost,
            $outputCost,
            null,
            $currency
        );
    }

    /**
     * Create an empty TokenUsage instance.
     */
    public static function empty(): static
    {
        return new static(0, 0);
    }

    /**
     * Add another TokenUsage to this one.
     *
     * @param  TokenUsage  $other  Other token usage to add
     * @return static New TokenUsage instance with combined values
     */
    public function add(TokenUsage $other): static
    {
        return new static(
            $this->inputTokens + $other->inputTokens,
            $this->outputTokens + $other->outputTokens,
            $this->totalTokens + $other->totalTokens,
            ($this->inputCost ?? 0) + ($other->inputCost ?? 0),
            ($this->outputCost ?? 0) + ($other->outputCost ?? 0),
            ($this->totalCost ?? 0) + ($other->totalCost ?? 0),
            $this->currency, // Keep the original currency
            null, // Cost breakdown would be complex to merge
            $this->model,
            $this->provider
        );
    }

    /**
     * Calculate costs based on provided rates.
     *
     * @param  float  $inputTokenRate  Cost per 1K input tokens
     * @param  float  $outputTokenRate  Cost per 1K output tokens
     * @param  string  $currency  Currency code
     * @return static New TokenUsage instance with calculated costs
     */
    public function calculateCosts(
        float $inputTokenRate,
        float $outputTokenRate,
        string $currency = 'USD'
    ): static {
        $inputCost = ($this->inputTokens / 1000) * $inputTokenRate;
        $outputCost = ($this->outputTokens / 1000) * $outputTokenRate;

        return new static(
            $this->inputTokens,
            $this->outputTokens,
            $this->totalTokens,
            $inputCost,
            $outputCost,
            $inputCost + $outputCost,
            $currency,
            [
                'input_token_rate' => $inputTokenRate,
                'output_token_rate' => $outputTokenRate,
                'input_cost_calculation' => "{$this->inputTokens} tokens / 1000 * {$inputTokenRate}",
                'output_cost_calculation' => "{$this->outputTokens} tokens / 1000 * {$outputTokenRate}",
            ],
            $this->model,
            $this->provider
        );
    }

    /**
     * Convert to array representation.
     */
    public function toArray(): array
    {
        $data = [
            'input_tokens' => $this->inputTokens,
            'output_tokens' => $this->outputTokens,
            'total_tokens' => $this->totalTokens,
            'currency' => $this->currency,
        ];

        if ($this->inputCost !== null) {
            $data['input_cost'] = $this->inputCost;
        }

        if ($this->outputCost !== null) {
            $data['output_cost'] = $this->outputCost;
        }

        if ($this->totalCost !== null) {
            $data['total_cost'] = $this->totalCost;
        }

        if ($this->costBreakdown) {
            $data['cost_breakdown'] = $this->costBreakdown;
        }

        if ($this->model) {
            $data['model'] = $this->model;
        }

        if ($this->provider) {
            $data['provider'] = $this->provider;
        }

        return $data;
    }

    /**
     * Create TokenUsage from array data.
     *
     * @param  array  $data  Token usage data
     */
    public static function fromArray(array $data): static
    {
        return new static(
            $data['input_tokens'] ?? 0,
            $data['output_tokens'] ?? 0,
            $data['total_tokens'] ?? null,
            $data['input_cost'] ?? null,
            $data['output_cost'] ?? null,
            $data['total_cost'] ?? null,
            $data['currency'] ?? 'USD',
            $data['cost_breakdown'] ?? null,
            $data['model'] ?? null,
            $data['provider'] ?? null
        );
    }

    /**
     * Check if this token usage has cost information.
     */
    public function hasCosts(): bool
    {
        return $this->totalCost !== null && $this->totalCost > 0;
    }

    /**
     * Get the cost per token.
     */
    public function getCostPerToken(): ?float
    {
        if (! $this->hasCosts() || $this->totalTokens === 0) {
            return null;
        }

        return $this->totalCost / $this->totalTokens;
    }

    /**
     * Get the input token percentage of total tokens.
     */
    public function getInputTokenPercentage(): float
    {
        if ($this->totalTokens === 0) {
            return 0.0;
        }

        return ($this->inputTokens / $this->totalTokens) * 100;
    }

    /**
     * Get the output token percentage of total tokens.
     */
    public function getOutputTokenPercentage(): float
    {
        if ($this->totalTokens === 0) {
            return 0.0;
        }

        return ($this->outputTokens / $this->totalTokens) * 100;
    }

    /**
     * Format the total cost as a currency string.
     *
     * @param  int  $decimals  Number of decimal places
     */
    public function formatTotalCost(int $decimals = 4): ?string
    {
        if ($this->totalCost === null) {
            return null;
        }

        return number_format($this->totalCost, $decimals) . ' ' . $this->currency;
    }

    /**
     * Get a summary string of the token usage.
     */
    public function getSummary(): string
    {
        $summary = "{$this->totalTokens} tokens ({$this->inputTokens} input, {$this->outputTokens} output)";

        if ($this->hasCosts()) {
            $summary .= " - {$this->formatTotalCost()}";
        }

        return $summary;
    }

    /**
     * Check if the token usage is empty (no tokens used).
     */
    public function isEmpty(): bool
    {
        return $this->totalTokens === 0;
    }
}
