<?php

namespace JTD\LaravelAI\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use JTD\LaravelAI\Models\AIProvider;
use JTD\LaravelAI\Models\AIProviderModel;
use JTD\LaravelAI\Models\ConversationTemplate;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\JTD\LaravelAI\Models\ConversationTemplate>
 */
class ConversationTemplateFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = ConversationTemplate::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $categories = [
            ConversationTemplate::CATEGORY_GENERAL,
            ConversationTemplate::CATEGORY_BUSINESS,
            ConversationTemplate::CATEGORY_CREATIVE,
            ConversationTemplate::CATEGORY_TECHNICAL,
            ConversationTemplate::CATEGORY_EDUCATIONAL,
            ConversationTemplate::CATEGORY_ANALYSIS,
            ConversationTemplate::CATEGORY_SUPPORT,
        ];

        return [
            'uuid' => $this->faker->uuid(),
            'name' => $this->faker->sentence(3),
            'description' => $this->faker->paragraph(),
            'category' => $this->faker->randomElement($categories),
            'template_data' => [
                'system_prompt' => 'You are a helpful assistant specialized in ' . $this->faker->word() . '.',
                'initial_messages' => [
                    [
                        'role' => 'user',
                        'content' => 'Hello! I need help with ' . $this->faker->word() . '.',
                    ],
                    [
                        'role' => 'assistant',
                        'content' => 'I\'d be happy to help you with that! What specific questions do you have?',
                    ],
                ],
                'title' => $this->faker->sentence(2),
            ],
            'parameters' => [
                'user_name' => [
                    'type' => 'string',
                    'required' => false,
                    'default' => 'User',
                    'description' => 'Name of the user',
                ],
                'topic' => [
                    'type' => 'string',
                    'required' => true,
                    'description' => 'Main topic of discussion',
                ],
                'difficulty_level' => [
                    'type' => 'enum',
                    'options' => ['beginner', 'intermediate', 'advanced'],
                    'default' => 'intermediate',
                    'description' => 'Difficulty level for explanations',
                ],
            ],
            'default_configuration' => [
                'temperature' => $this->faker->randomFloat(1, 0.1, 1.0),
                'max_tokens' => $this->faker->numberBetween(100, 2000),
                'top_p' => $this->faker->randomFloat(1, 0.1, 1.0),
            ],
            'ai_provider_id' => null,
            'ai_provider_model_id' => null,
            'provider_name' => $this->faker->randomElement(['OpenAI', 'Gemini', 'xAI']),
            'model_name' => $this->faker->randomElement(['gpt-4', 'gpt-3.5-turbo', 'gemini-pro', 'grok-beta']),
            'is_public' => $this->faker->boolean(30), // 30% chance of being public
            'is_active' => true,
            'published_at' => function (array $attributes) {
                return $attributes['is_public'] ? $this->faker->dateTimeBetween('-1 month', 'now') : null;
            },
            'usage_count' => $this->faker->numberBetween(0, 1000),
            'avg_rating' => $this->faker->optional(0.7)->randomFloat(1, 1.0, 5.0), // 70% chance of having a rating
            'tags' => $this->faker->randomElements([
                'helpful', 'educational', 'business', 'creative', 'technical',
                'analysis', 'support', 'beginner-friendly', 'advanced', 'popular',
            ], $this->faker->numberBetween(1, 4)),
            'language' => $this->faker->randomElement(['en', 'es', 'fr', 'de', 'it']),
            'metadata' => [
                'created_by_system' => $this->faker->boolean(20),
                'featured' => $this->faker->boolean(10),
                'version' => '1.0',
            ],
            'created_by_id' => 1, // Default user ID
            'created_by_type' => 'App\\Models\\User',
            'created_at' => $this->faker->dateTimeBetween('-6 months', 'now'),
            'updated_at' => function (array $attributes) {
                return $this->faker->dateTimeBetween($attributes['created_at'], 'now');
            },
        ];
    }

    /**
     * Indicate that the template is public.
     */
    public function public(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_public' => true,
            'published_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
        ]);
    }

    /**
     * Indicate that the template is private.
     */
    public function private(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_public' => false,
            'published_at' => null,
        ]);
    }

    /**
     * Indicate that the template is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the template is popular (high usage).
     */
    public function popular(): static
    {
        return $this->state(fn (array $attributes) => [
            'usage_count' => $this->faker->numberBetween(500, 2000),
            'avg_rating' => $this->faker->randomFloat(1, 4.0, 5.0),
            'is_public' => true,
            'published_at' => $this->faker->dateTimeBetween('-3 months', '-1 month'),
        ]);
    }

    /**
     * Indicate that the template is highly rated.
     */
    public function highlyRated(): static
    {
        return $this->state(fn (array $attributes) => [
            'avg_rating' => $this->faker->randomFloat(1, 4.5, 5.0),
            'usage_count' => $this->faker->numberBetween(100, 500),
        ]);
    }

    /**
     * Create a template with specific category.
     */
    public function category(string $category): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => $category,
        ]);
    }

    /**
     * Create a template with specific tags.
     */
    public function withTags(array $tags): static
    {
        return $this->state(fn (array $attributes) => [
            'tags' => $tags,
        ]);
    }

    /**
     * Create a template with specific parameters.
     */
    public function withParameters(array $parameters): static
    {
        return $this->state(fn (array $attributes) => [
            'parameters' => $parameters,
        ]);
    }

    /**
     * Create a template with AI provider and model.
     */
    public function withProvider(AIProvider $provider, AIProviderModel $model): static
    {
        return $this->state(fn (array $attributes) => [
            'ai_provider_id' => $provider->id,
            'ai_provider_model_id' => $model->id,
            'provider_name' => $provider->name,
            'model_name' => $model->name,
        ]);
    }

    /**
     * Create a simple template with minimal data.
     */
    public function simple(): static
    {
        return $this->state(fn (array $attributes) => [
            'template_data' => [
                'system_prompt' => 'You are a helpful assistant.',
            ],
            'parameters' => [],
            'default_configuration' => [
                'temperature' => 0.7,
                'max_tokens' => 1000,
            ],
            'tags' => ['simple'],
        ]);
    }

    /**
     * Create a template with complex parameters.
     */
    public function complex(): static
    {
        return $this->state(fn (array $attributes) => [
            'parameters' => [
                'user_name' => [
                    'type' => 'string',
                    'required' => true,
                    'description' => 'Full name of the user',
                    'max_length' => 100,
                ],
                'age' => [
                    'type' => 'integer',
                    'required' => false,
                    'min' => 13,
                    'max' => 120,
                    'description' => 'Age of the user',
                ],
                'interests' => [
                    'type' => 'array',
                    'required' => false,
                    'min_items' => 1,
                    'max_items' => 10,
                    'description' => 'List of user interests',
                ],
                'experience_level' => [
                    'type' => 'enum',
                    'options' => ['novice', 'beginner', 'intermediate', 'advanced', 'expert'],
                    'required' => true,
                    'description' => 'Experience level in the topic',
                ],
                'preferred_style' => [
                    'type' => 'enum',
                    'options' => ['formal', 'casual', 'technical', 'friendly'],
                    'default' => 'friendly',
                    'description' => 'Preferred communication style',
                ],
            ],
            'template_data' => [
                'system_prompt' => 'You are an expert {{topic}} assistant. Adapt your communication style to {{preferred_style}} and adjust complexity for {{experience_level}} level users.',
                'initial_messages' => [
                    [
                        'role' => 'user',
                        'content' => 'Hi, I\'m {{user_name}} and I\'m {{experience_level}} in {{topic}}. Can you help me?',
                    ],
                ],
                'title' => '{{topic}} Assistant for {{user_name}}',
            ],
            'tags' => ['complex', 'personalized', 'adaptive'],
        ]);
    }

    /**
     * Create a business-focused template.
     */
    public function business(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => ConversationTemplate::CATEGORY_BUSINESS,
            'template_data' => [
                'system_prompt' => 'You are a professional business consultant with expertise in strategy, operations, and growth.',
                'initial_messages' => [
                    [
                        'role' => 'user',
                        'content' => 'I need business advice for my {{business_type}} company.',
                    ],
                ],
            ],
            'parameters' => [
                'business_type' => [
                    'type' => 'enum',
                    'options' => ['startup', 'small business', 'enterprise', 'non-profit'],
                    'required' => true,
                    'description' => 'Type of business',
                ],
                'industry' => [
                    'type' => 'string',
                    'required' => true,
                    'description' => 'Industry sector',
                ],
            ],
            'tags' => ['business', 'consulting', 'strategy', 'professional'],
        ]);
    }
}
