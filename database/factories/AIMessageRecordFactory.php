<?php

namespace JTD\LaravelAI\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use JTD\LaravelAI\Models\AIConversation;
use JTD\LaravelAI\Models\AIMessageRecord;

class AIMessageRecordFactory extends Factory
{
    protected $model = AIMessageRecord::class;

    public function definition(): array
    {
        $roles = ['user', 'assistant', 'system'];
        $role = $this->faker->randomElement($roles);

        $content = match ($role) {
            'system' => $this->faker->sentence(8),
            'user' => $this->faker->sentence(6),
            'assistant' => $this->faker->paragraph(2),
            default => $this->faker->sentence(),
        };

        return [
            'ai_conversation_id' => AIConversation::factory(),
            'sequence_number' => $this->faker->numberBetween(1, 100),
            'role' => $role,
            'content' => $content,
            'content_type' => 'text',
            'input_tokens' => $this->faker->numberBetween(5, 250),
            'output_tokens' => $this->faker->numberBetween(5, 250),
            'total_tokens' => $this->faker->numberBetween(10, 500),
            'cost' => $this->faker->randomFloat(6, 0.001, 0.1),
            'cost_currency' => 'USD',
            'response_time_ms' => $this->faker->numberBetween(100, 5000),
            'finish_reason' => $this->faker->randomElement(['stop', 'length', 'content_filter']),
            'status' => 'completed',
            'is_streaming' => false,
            'is_regenerated' => false,
            'regeneration_count' => 0,
        ];
    }

    public function user(): static
    {
        return $this->state([
            'role' => 'user',
            'content' => $this->faker->sentence(6),
        ]);
    }

    public function assistant(): static
    {
        return $this->state([
            'role' => 'assistant',
            'content' => $this->faker->paragraph(2),
        ]);
    }

    public function system(): static
    {
        return $this->state([
            'role' => 'system',
            'content' => $this->faker->sentence(8),
        ]);
    }

    public function withTokens(int $tokens): static
    {
        return $this->state([
            'total_tokens' => $tokens,
            'input_tokens' => intval($tokens * 0.4),
            'output_tokens' => intval($tokens * 0.6),
        ]);
    }

    public function withSequence(int $sequence): static
    {
        return $this->state([
            'sequence_number' => $sequence,
        ]);
    }

    public function withCost(float $cost): static
    {
        return $this->state([
            'cost' => $cost,
        ]);
    }
}
