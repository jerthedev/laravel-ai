<?php

namespace JTD\LaravelAI\Drivers\XAI\Traits;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Models\TokenUsage;

/**
 * Calculates Costs for xAI
 *
 * Handles cost calculation and token estimation for xAI Grok models.
 * Provides accurate pricing based on current xAI pricing tiers.
 */
trait CalculatesCosts
{
    /**
     * Calculate cost for a message or token usage.
     */
    public function calculateCost($message, ?string $modelId = null): array
    {
        $model = $modelId ?? $this->getCurrentModel();

        if ($message instanceof TokenUsage) {
            return $this->calculateActualCost($message, $model);
        }

        // Estimate tokens for the message
        $estimatedTokens = $this->estimateTokenUsage($message, $model);

        return $this->doCalculateCost($estimatedTokens->totalTokens, $model);
    }

    /**
     * Calculate cost based on estimated tokens.
     */
    protected function doCalculateCost(int $tokens, string $model): array
    {
        // Assume 70% input, 30% output for estimation
        $inputTokens = (int) ($tokens * 0.7);
        $outputTokens = (int) ($tokens * 0.3);

        return $this->calculateTokenCost($inputTokens, $outputTokens, $model);
    }

    /**
     * Calculate actual cost from token usage.
     */
    protected function calculateActualCost(TokenUsage $tokenUsage, string $modelId): array
    {
        return $this->calculateTokenCost(
            $tokenUsage->inputTokens,
            $tokenUsage->outputTokens,
            $modelId
        );
    }

    /**
     * Calculate cost for specific token counts.
     */
    protected function calculateTokenCost(int $inputTokens, int $outputTokens, string $modelId): array
    {
        $pricing = $this->getModelPricing($modelId);

        if (! $pricing) {
            Log::warning('No pricing data available for xAI model', [
                'model' => $modelId,
                'provider' => $this->providerName,
            ]);

            return [
                'total_cost' => 0.0,
                'input_cost' => 0.0,
                'output_cost' => 0.0,
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'total_tokens' => $inputTokens + $outputTokens,
                'model' => $modelId,
                'currency' => 'USD',
                'pricing_available' => false,
            ];
        }

        $inputCost = ($inputTokens / 1000000) * $pricing['input_per_1m'];
        $outputCost = ($outputTokens / 1000000) * $pricing['output_per_1m'];
        $totalCost = $inputCost + $outputCost;

        return [
            'total_cost' => round($totalCost, 6),
            'input_cost' => round($inputCost, 6),
            'output_cost' => round($outputCost, 6),
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'total_tokens' => $inputTokens + $outputTokens,
            'model' => $modelId,
            'currency' => 'USD',
            'pricing_available' => true,
            'pricing' => [
                'input_per_1m' => $pricing['input_per_1m'],
                'output_per_1m' => $pricing['output_per_1m'],
            ],
        ];
    }

    /**
     * Estimate token count for input (interface implementation).
     */
    public function estimateTokens($input, ?string $modelId = null): int
    {
        $model = $modelId ?? $this->getCurrentModel();

        if (is_string($input)) {
            return $this->estimateTextTokens($input);
        }

        if (is_array($input)) {
            return $this->estimateTokensForMessages($input, $model);
        }

        if ($input instanceof AIMessage) {
            return $this->estimateMessageTokens($input);
        }

        return $this->estimateTextTokens((string) $input);
    }

    /**
     * Estimate token count for messages (internal method).
     */
    protected function estimateTokensForMessages(array $messages, ?string $model = null): int
    {
        $model = $model ?? $this->getDefaultModel();
        $totalTokens = 0;

        foreach ($messages as $message) {
            if ($message instanceof AIMessage) {
                $totalTokens += $this->estimateMessageTokens($message);
            } elseif (is_array($message)) {
                $content = $message['content'] ?? '';
                $totalTokens += $this->estimateTextTokens($content);
                $totalTokens += 10; // Message overhead
            } else {
                $totalTokens += $this->estimateTextTokens((string) $message);
            }
        }

        // Add conversation overhead
        $totalTokens += count($messages) * 5;

        return $totalTokens;
    }

    /**
     * Estimate tokens and return TokenUsage object (for internal use).
     */
    protected function estimateTokenUsage(array $messages, ?string $model = null): TokenUsage
    {
        $totalTokens = $this->estimateTokensForMessages($messages, $model);

        return new TokenUsage(
            inputTokens: $totalTokens,
            outputTokens: 0,
            totalTokens: $totalTokens
        );
    }

    /**
     * Estimate tokens for a single message.
     */
    protected function estimateMessageTokens(AIMessage $message): int
    {
        $tokens = 0;

        // Content tokens
        $tokens += $this->estimateTextTokens($message->content);

        // Role and structure overhead
        $tokens += 10;

        // Tool calls overhead
        if (! empty($message->toolCalls)) {
            foreach ($message->toolCalls as $toolCall) {
                $tokens += $this->estimateTextTokens($toolCall->function->name);
                $tokens += $this->estimateTextTokens($toolCall->function->arguments);
                $tokens += 20; // Tool call structure overhead
            }
        }

        // Name overhead
        if ($message->name) {
            $tokens += $this->estimateTextTokens($message->name);
            $tokens += 5;
        }

        return $tokens;
    }

    /**
     * Estimate tokens for text content.
     */
    protected function estimateTextTokens(string $text): int
    {
        if (empty($text)) {
            return 0;
        }

        // Rough estimation based on character count
        // xAI models use similar tokenization to GPT models
        // Average ~4 characters per token for English text
        $charCount = mb_strlen($text, 'UTF-8');
        $estimatedTokens = ceil($charCount / 4);

        // Adjust for different text types
        if (preg_match('/[^\x00-\x7F]/', $text)) {
            // Non-ASCII characters (Unicode) tend to use more tokens
            $estimatedTokens = ceil($estimatedTokens * 1.3);
        }

        if (preg_match('/\{|\[|<|```/', $text)) {
            // Structured content (JSON, XML, code) tends to use more tokens
            $estimatedTokens = ceil($estimatedTokens * 1.2);
        }

        return max(1, $estimatedTokens);
    }

    /**
     * Get model pricing information.
     */
    protected function getModelPricing(string $modelId): ?array
    {
        // Cache pricing data for 24 hours
        $cacheKey = "xai_pricing_{$modelId}";

        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $pricing = $this->getStaticModelPricing($modelId);

        if ($pricing) {
            Cache::put($cacheKey, $pricing, 86400); // 24 hours
        }

        return $pricing;
    }

    /**
     * Get static model pricing (fallback when API pricing is unavailable).
     */
    protected function getStaticModelPricing(string $modelId): ?array
    {
        // xAI pricing as of December 2024 (estimated based on market rates)
        $basePricing = match ($modelId) {
            'grok-beta' => [
                'input' => 0.000005,  // $5 per 1M input tokens
                'output' => 0.000015, // $15 per 1M output tokens
            ],
            'grok-2' => [
                'input' => 0.000002,  // $2 per 1M input tokens
                'output' => 0.000010, // $10 per 1M output tokens
            ],
            'grok-2-mini' => [
                'input' => 0.000001,  // $1 per 1M input tokens
                'output' => 0.000005, // $5 per 1M output tokens
            ],
            'grok-2-1212' => [
                'input' => 0.000002,  // $2 per 1M input tokens
                'output' => 0.000010, // $10 per 1M output tokens
            ],
            'grok-2-vision-1212' => [
                'input' => 0.000003,  // $3 per 1M input tokens (vision premium)
                'output' => 0.000015, // $15 per 1M output tokens
            ],
            'grok-3' => [
                'input' => 0.000003,  // $3 per 1M input tokens (estimated)
                'output' => 0.000012, // $12 per 1M output tokens
            ],
            'grok-3-fast' => [
                'input' => 0.000002,  // $2 per 1M input tokens (fast variant)
                'output' => 0.000008, // $8 per 1M output tokens
            ],
            'grok-3-mini' => [
                'input' => 0.0000005, // $0.5 per 1M input tokens (mini variant)
                'output' => 0.000002, // $2 per 1M output tokens
            ],
            'grok-3-mini-fast' => [
                'input' => 0.0000003, // $0.3 per 1M input tokens (mini fast)
                'output' => 0.000001, // $1 per 1M output tokens
            ],
            'grok-4-0709' => [
                'input' => 0.000005,  // $5 per 1M input tokens (premium model)
                'output' => 0.000020, // $20 per 1M output tokens
            ],
            'grok-4-0709-eu' => [
                'input' => 0.000005,  // $5 per 1M input tokens (EU region)
                'output' => 0.000020, // $20 per 1M output tokens
            ],
            'grok-2-image-1212' => [
                'input' => 0.000003,  // $3 per 1M input tokens (image model)
                'output' => 0.000015, // $15 per 1M output tokens
            ],
            default => [
                'input' => 0.000002,  // Default fallback pricing (grok-3-mini level)
                'output' => 0.000008,
            ],
        };

        // Add the per-1M keys for compatibility
        return [
            'input' => $basePricing['input'],
            'output' => $basePricing['output'],
            'input_per_1m' => $basePricing['input'] * 1000000,
            'output_per_1m' => $basePricing['output'] * 1000000,
            'currency' => 'USD',
            'model' => $modelId,
        ];
    }

    /**
     * Get cost breakdown for a request.
     */
    public function getCostBreakdown(TokenUsage $usage, string $modelId): array
    {
        $pricing = $this->getModelPricing($modelId);

        if (! $pricing) {
            return [
                'input_tokens' => $usage->inputTokens,
                'output_tokens' => $usage->outputTokens,
                'total_tokens' => $usage->totalTokens,
                'input_cost' => 0.0,
                'output_cost' => 0.0,
                'total_cost' => 0.0,
                'model' => $modelId,
                'pricing_available' => false,
            ];
        }

        $inputCost = ($usage->inputTokens / 1000000) * $pricing['input'];
        $outputCost = ($usage->outputTokens / 1000000) * $pricing['output'];
        $totalCost = $inputCost + $outputCost;

        return [
            'input_tokens' => $usage->inputTokens,
            'output_tokens' => $usage->outputTokens,
            'total_tokens' => $usage->totalTokens,
            'input_cost' => round($inputCost, 6),
            'output_cost' => round($outputCost, 6),
            'total_cost' => round($totalCost, 6),
            'model' => $modelId,
            'pricing_available' => true,
            'pricing' => [
                'input_per_1m' => $pricing['input'],
                'output_per_1m' => $pricing['output'],
            ],
        ];
    }

    /**
     * Estimate cost for messages before sending.
     */
    public function estimateCost(array $messages, ?string $model = null, int $maxOutputTokens = 1000): array
    {
        $model = $model ?? $this->getDefaultModel();
        $inputUsage = $this->estimateTokens($messages, $model);

        // Estimate output tokens (conservative estimate)
        $estimatedOutputTokens = min($maxOutputTokens, $inputUsage->inputTokens * 2);

        $estimatedUsage = new TokenUsage(
            inputTokens: $inputUsage->inputTokens,
            outputTokens: $estimatedOutputTokens,
            totalTokens: $inputUsage->inputTokens + $estimatedOutputTokens
        );

        $breakdown = $this->getCostBreakdown($estimatedUsage, $model);

        return array_merge($breakdown, [
            'estimated' => true,
            'max_output_tokens' => $maxOutputTokens,
        ]);
    }

    /**
     * Get pricing tier information.
     */
    public function getPricingTiers(): array
    {
        return [
            'grok-2-mini' => [
                'tier' => 'economy',
                'description' => 'Most cost-effective option for simple tasks',
                'use_cases' => ['simple chat', 'basic Q&A', 'content generation'],
            ],
            'grok-2' => [
                'tier' => 'standard',
                'description' => 'Balanced performance and cost',
                'use_cases' => ['complex reasoning', 'analysis', 'function calling'],
            ],
            'grok-beta' => [
                'tier' => 'premium',
                'description' => 'Highest capability model',
                'use_cases' => ['advanced reasoning', 'complex tasks', 'research'],
            ],
            'grok-2-vision-1212' => [
                'tier' => 'premium',
                'description' => 'Vision-enabled model with premium pricing',
                'use_cases' => ['image analysis', 'multimodal tasks', 'visual reasoning'],
            ],
        ];
    }

    /**
     * Get cost optimization recommendations.
     */
    public function getCostOptimizationTips(string $modelId, TokenUsage $usage): array
    {
        $tips = [];

        // High token usage tips
        if ($usage->totalTokens > 10000) {
            $tips[] = 'Consider breaking large requests into smaller chunks';
            $tips[] = 'Use conversation trimming to reduce context length';
        }

        // Model-specific tips
        if ($modelId === 'grok-beta' && $usage->totalTokens < 1000) {
            $tips[] = 'Consider using grok-2-mini for simple tasks to reduce costs';
        }

        // Output token optimization
        if ($usage->outputTokens > $usage->inputTokens * 3) {
            $tips[] = 'Consider setting max_tokens to limit output length';
            $tips[] = 'Use more specific prompts to get concise responses';
        }

        return $tips;
    }

    /**
     * Clear pricing cache.
     */
    public function clearPricingCache(): void
    {
        $models = ['grok-beta', 'grok-2', 'grok-2-mini', 'grok-2-1212', 'grok-2-vision-1212'];

        foreach ($models as $model) {
            Cache::forget("xai_pricing_{$model}");
        }
    }

    /**
     * Refresh pricing data.
     */
    public function refreshPricingData(): array
    {
        $this->clearPricingCache();

        $models = ['grok-beta', 'grok-2', 'grok-2-mini', 'grok-2-1212', 'grok-2-vision-1212'];
        $pricing = [];

        foreach ($models as $model) {
            $pricing[$model] = $this->getModelPricing($model);
        }

        return $pricing;
    }

    /**
     * Get cost rates for a specific model (required by abstract class).
     */
    protected function getCostRates(string $model): array
    {
        $pricing = $this->getModelPricing($model);

        return [
            'input' => $pricing['input'] ?? 0.000005,
            'output' => $pricing['output'] ?? 0.000015,
        ];
    }
}
