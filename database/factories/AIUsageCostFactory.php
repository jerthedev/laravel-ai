<?php

namespace JTD\LaravelAI\Database\Factories;

use JTD\LaravelAI\Models\AIUsageCost;
use JTD\LaravelAI\Models\User;
use JTD\LaravelAI\Models\AIConversation;
use JTD\LaravelAI\Models\AIMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

class AIUsageCostFactory extends Factory
{
    protected $model = AIUsageCost::class;

    public function definition(): array
    {
        $inputTokens = $this->faker->numberBetween(100, 5000);
        $outputTokens = $this->faker->numberBetween(50, 2000);
        $totalTokens = $inputTokens + $outputTokens;
        
        // Realistic cost calculation based on tokens
        $inputCostPer1k = $this->faker->randomFloat(6, 0.001, 0.03); // $0.001 to $0.03 per 1k tokens
        $outputCostPer1k = $inputCostPer1k * $this->faker->randomFloat(2, 1.5, 4.0); // Output typically costs more
        
        $inputCost = ($inputTokens / 1000) * $inputCostPer1k;
        $outputCost = ($outputTokens / 1000) * $outputCostPer1k;
        $totalCost = $inputCost + $outputCost;

        return [
            'user_id' => User::factory(),
            'conversation_id' => $this->faker->uuid(),
            'message_id' => null,
            'provider' => $this->faker->randomElement(['openai', 'anthropic', 'google', 'xai']),
            'model' => $this->faker->randomElement([
                'gpt-4', 'gpt-3.5-turbo', 'claude-3-opus', 'claude-3-sonnet', 
                'gemini-pro', 'grok-beta'
            ]),
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'total_tokens' => $totalTokens,
            'input_cost' => round($inputCost, 6),
            'output_cost' => round($outputCost, 6),
            'total_cost' => round($totalCost, 6),
            'currency' => 'USD',
            'pricing_source' => $this->faker->randomElement(['api', 'database', 'fallback']),
            'processing_time_ms' => $this->faker->numberBetween(500, 10000),
            'metadata' => [],
        ];
    }

    public function expensive(): static
    {
        return $this->state(fn() => [
            'input_tokens' => $this->faker->numberBetween(8000, 15000),
            'output_tokens' => $this->faker->numberBetween(3000, 8000),
            'total_cost' => $this->faker->randomFloat(6, 1.0, 5.0),
        ])->afterMaking(function (AIUsageCost $usageCost) {
            $usageCost->total_tokens = $usageCost->input_tokens + $usageCost->output_tokens;
        });
    }

    public function cheap(): static
    {
        return $this->state(fn() => [
            'input_tokens' => $this->faker->numberBetween(50, 500),
            'output_tokens' => $this->faker->numberBetween(20, 200),
            'total_cost' => $this->faker->randomFloat(6, 0.001, 0.05),
        ])->afterMaking(function (AIUsageCost $usageCost) {
            $usageCost->total_tokens = $usageCost->input_tokens + $usageCost->output_tokens;
        });
    }

    public function slow(): static
    {
        return $this->state(fn() => [
            'processing_time_ms' => $this->faker->numberBetween(8000, 20000),
        ]);
    }

    public function fast(): static
    {
        return $this->state(fn() => [
            'processing_time_ms' => $this->faker->numberBetween(200, 1000),
        ]);
    }

    public function openai(): static
    {
        return $this->state(fn() => [
            'provider' => 'openai',
            'model' => $this->faker->randomElement(['gpt-4', 'gpt-3.5-turbo']),
        ]);
    }

    public function anthropic(): static
    {
        return $this->state(fn() => [
            'provider' => 'anthropic',
            'model' => $this->faker->randomElement(['claude-3-opus', 'claude-3-sonnet', 'claude-3-haiku']),
        ]);
    }

    public function google(): static
    {
        return $this->state(fn() => [
            'provider' => 'google',
            'model' => $this->faker->randomElement(['gemini-pro', 'gemini-pro-vision']),
        ]);
    }

    public function withConversation(): static
    {
        return $this->state(fn() => [
            'conversation_id' => AIConversation::factory(),
        ]);
    }

    public function withMessage(): static
    {
        return $this->state(fn() => [
            'message_id' => AIMessage::factory(),
        ]);
    }

    public function withMetadata(): static
    {
        return $this->state(fn() => [
            'metadata' => [
                'request_id' => $this->faker->uuid(),
                'session_id' => $this->faker->uuid(),
                'user_agent' => $this->faker->userAgent(),
                'ip_address' => $this->faker->ipv4(),
            ],
        ]);
    }

    public function today(): static
    {
        return $this->state(fn() => [
            'created_at' => now()->subHours($this->faker->numberBetween(0, 23)),
        ]);
    }

    public function thisWeek(): static
    {
        return $this->state(fn() => [
            'created_at' => now()->subDays($this->faker->numberBetween(0, 6)),
        ]);
    }

    public function thisMonth(): static
    {
        return $this->state(fn() => [
            'created_at' => now()->subDays($this->faker->numberBetween(0, 29)),
        ]);
    }

    public function highTokens(): static
    {
        return $this->state(fn() => [
            'input_tokens' => $this->faker->numberBetween(5000, 12000),
            'output_tokens' => $this->faker->numberBetween(2000, 8000),
        ])->afterMaking(function (AIUsageCost $usageCost) {
            $usageCost->total_tokens = $usageCost->input_tokens + $usageCost->output_tokens;
        });
    }
}