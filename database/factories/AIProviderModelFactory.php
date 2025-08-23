<?php

namespace JTD\LaravelAI\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use JTD\LaravelAI\Models\AIProvider;
use JTD\LaravelAI\Models\AIProviderModel;

class AIProviderModelFactory extends Factory
{
    protected $model = AIProviderModel::class;

    public function definition(): array
    {
        $modelNames = ['gpt-4', 'gpt-3.5-turbo', 'gemini-pro', 'grok-beta', 'claude-3'];
        $modelId = $this->faker->randomElement($modelNames);

        return [
            'ai_provider_id' => AIProvider::factory(),
            'model_id' => $modelId,
            'name' => ucwords(str_replace('-', ' ', $modelId)),
            'version' => $this->faker->optional()->semver(),
            'description' => $this->faker->sentence(),
            'status' => 'active',
            'supports_chat' => true,
            'supports_completion' => $this->faker->boolean(50),
            'supports_streaming' => $this->faker->boolean(80),
            'supports_function_calling' => $this->faker->boolean(70),
            'supports_vision' => $this->faker->boolean(30),
            'supports_audio' => $this->faker->boolean(20),
            'supports_embeddings' => $this->faker->boolean(40),
            'supports_fine_tuning' => $this->faker->boolean(30),
            'max_tokens' => $this->faker->numberBetween(1000, 4000),
            'context_length' => $this->faker->randomElement([4096, 8192, 16384, 32768, 128000]),
            'default_temperature' => $this->faker->randomFloat(2, 0, 2),
            'min_temperature' => 0.0,
            'max_temperature' => 2.0,
            'supported_formats' => $this->faker->optional()->randomElements(['text', 'json', 'markdown'], 2),
            'input_token_cost' => $this->faker->randomFloat(8, 0.0001, 0.01),
            'output_token_cost' => $this->faker->randomFloat(8, 0.0001, 0.01),
            'pricing_currency' => 'USD',
            'pricing_model' => 'per_token',
            'avg_response_time_ms' => $this->faker->numberBetween(500, 3000),
            'total_requests' => 0,
            'successful_requests' => 0,
            'failed_requests' => 0,
            'is_default' => false,
            'context_window' => $this->faker->randomElement([4096, 8192, 16384, 32768, 128000]),
        ];
    }

    public function active(): static
    {
        return $this->state([
            'status' => 'active',
        ]);
    }

    public function inactive(): static
    {
        return $this->state([
            'status' => 'inactive',
        ]);
    }

    public function default(): static
    {
        return $this->state([
            'is_default' => true,
        ]);
    }

    public function deprecated(): static
    {
        return $this->state([
            'is_deprecated' => true,
        ]);
    }

    public function withContextLength(int $tokens): static
    {
        return $this->state([
            'context_length' => $tokens,
        ]);
    }

    public function gpt4(): static
    {
        return $this->state([
            'model_id' => 'gpt-4',
            'name' => 'GPT-4',
            'context_length' => 8192,
            'max_tokens' => 4096,
        ]);
    }

    public function geminiPro(): static
    {
        return $this->state([
            'model_id' => 'gemini-pro',
            'name' => 'Gemini Pro',
            'context_length' => 32768,
            'max_tokens' => 2048,
        ]);
    }

    public function grokBeta(): static
    {
        return $this->state([
            'model_id' => 'grok-beta',
            'name' => 'Grok Beta',
            'context_length' => 16384,
            'max_tokens' => 4096,
        ]);
    }
}
