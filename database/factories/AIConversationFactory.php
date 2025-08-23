<?php

namespace JTD\LaravelAI\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use JTD\LaravelAI\Models\AIConversation;
use JTD\LaravelAI\Models\AIProvider;
use JTD\LaravelAI\Models\AIProviderModel;

class AIConversationFactory extends Factory
{
    protected $model = AIConversation::class;

    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(3),
            'description' => $this->faker->optional()->paragraph(),
            'provider_name' => $this->faker->randomElement(['openai', 'gemini', 'xai']),
            'model_name' => $this->faker->randomElement(['gpt-4', 'gemini-pro', 'grok-beta']),
            'status' => AIConversation::STATUS_ACTIVE,
            'conversation_type' => $this->faker->randomElement([
                AIConversation::TYPE_CHAT,
                AIConversation::TYPE_ANALYSIS,
                AIConversation::TYPE_CREATIVE,
            ]),
            'language' => 'en',
            'total_messages' => $this->faker->numberBetween(0, 50),
            'total_input_tokens' => $this->faker->numberBetween(0, 10000),
            'total_output_tokens' => $this->faker->numberBetween(0, 10000),
            'total_cost' => $this->faker->randomFloat(6, 0, 10),
            'total_requests' => $this->faker->numberBetween(0, 25),
            'successful_requests' => $this->faker->numberBetween(0, 20),
            'failed_requests' => $this->faker->numberBetween(0, 5),
            'avg_response_time_ms' => $this->faker->numberBetween(500, 5000),
            'tags' => $this->faker->optional()->randomElements(['test', 'demo', 'production'], 2),
            'metadata' => $this->faker->optional()->randomElements([
                'context' => ['key' => 'value'],
                'settings' => ['temperature' => 0.7],
            ]),
            'system_prompt' => $this->faker->optional()->sentence(),
            'last_activity_at' => $this->faker->optional()->dateTimeBetween('-1 week', 'now'),
        ];
    }

    public function withProvider(AIProvider $provider, ?AIProviderModel $model = null): static
    {
        return $this->state(function () use ($provider, $model) {
            $state = [
                'ai_provider_id' => $provider->id,
                'provider_name' => $provider->name,
            ];

            if ($model) {
                $state['ai_provider_model_id'] = $model->id;
                $state['model_name'] = $model->name;
            }

            return $state;
        });
    }

    public function active(): static
    {
        return $this->state([
            'status' => AIConversation::STATUS_ACTIVE,
        ]);
    }

    public function archived(): static
    {
        return $this->state([
            'status' => AIConversation::STATUS_ARCHIVED,
        ]);
    }

    public function withMessages(int $count = 5): static
    {
        return $this->state([
            'total_messages' => $count,
        ]);
    }

    public function withCost(float $cost): static
    {
        return $this->state([
            'total_cost' => $cost,
        ]);
    }

    public function openai(): static
    {
        return $this->state([
            'provider_name' => 'openai',
            'model_name' => 'gpt-4',
        ]);
    }

    public function gemini(): static
    {
        return $this->state([
            'provider_name' => 'gemini',
            'model_name' => 'gemini-pro',
        ]);
    }

    public function xai(): static
    {
        return $this->state([
            'provider_name' => 'xai',
            'model_name' => 'grok-beta',
        ]);
    }
}
