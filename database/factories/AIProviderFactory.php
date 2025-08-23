<?php

namespace JTD\LaravelAI\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use JTD\LaravelAI\Models\AIProvider;

class AIProviderFactory extends Factory
{
    protected $model = AIProvider::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->randomElement(['openai', 'gemini', 'xai', 'anthropic', 'cohere', 'huggingface']);

        return [
            'name' => $name,
            'slug' => $name,
            'driver' => $name,
            'description' => $this->faker->sentence(),
            'status' => 'active',
            'website_url' => $this->faker->optional()->url(),
            'documentation_url' => $this->faker->optional()->url(),
            'supports_streaming' => $this->faker->boolean(80),
            'supports_function_calling' => $this->faker->boolean(70),
            'supports_vision' => $this->faker->boolean(50),
            'max_tokens' => $this->faker->optional()->numberBetween(1000, 8000),
            'max_context_length' => $this->faker->optional()->numberBetween(4096, 128000),
            'default_temperature' => $this->faker->optional()->randomFloat(2, 0, 2),
            'supported_formats' => $this->faker->optional()->randomElements(['text', 'json', 'markdown'], 2),
            'rate_limits' => [
                'requests_per_minute' => $this->faker->numberBetween(60, 3000),
                'tokens_per_minute' => $this->faker->numberBetween(10000, 150000),
            ],
            'config' => [
                'timeout' => 30,
                'retry_attempts' => 3,
            ],
            'health_status' => 'healthy',
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

    public function openai(): static
    {
        return $this->state([
            'name' => 'openai',
            'slug' => 'openai',
            'driver' => 'openai',
            'website_url' => 'https://openai.com',
            'documentation_url' => 'https://platform.openai.com/docs',
        ]);
    }

    public function gemini(): static
    {
        return $this->state([
            'name' => 'gemini',
            'slug' => 'gemini',
            'driver' => 'gemini',
            'website_url' => 'https://ai.google.dev',
            'documentation_url' => 'https://ai.google.dev/docs',
        ]);
    }

    public function xai(): static
    {
        return $this->state([
            'name' => 'xai',
            'slug' => 'xai',
            'driver' => 'xai',
            'website_url' => 'https://x.ai',
            'documentation_url' => 'https://docs.x.ai',
        ]);
    }
}
