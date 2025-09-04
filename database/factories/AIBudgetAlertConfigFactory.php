<?php

namespace JTD\LaravelAI\Database\Factories;

use JTD\LaravelAI\Models\AIBudgetAlertConfig;
use JTD\LaravelAI\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AIBudgetAlertConfigFactory extends Factory
{
    protected $model = AIBudgetAlertConfig::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'project_id' => null,
            'organization_id' => null,
            'budget_type' => $this->faker->randomElement(['daily', 'weekly', 'monthly', 'yearly', 'per_request']),
            'enabled' => true,
            'min_threshold_percentage' => $this->faker->randomFloat(2, 70, 90),
            'email_enabled' => true,
            'email_severities' => ['medium', 'high', 'critical'],
            'additional_emails' => [],
            'slack_enabled' => false,
            'slack_severities' => ['high', 'critical'],
            'slack_webhook' => null,
            'sms_enabled' => false,
            'sms_severities' => ['critical'],
            'sms_phone' => null,
            'is_active' => true,
        ];
    }

    public function disabled(): static
    {
        return $this->state(fn() => [
            'enabled' => false,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn() => [
            'is_active' => false,
        ]);
    }

    public function withSlack(): static
    {
        return $this->state(fn() => [
            'slack_enabled' => true,
            'slack_webhook' => 'https://hooks.slack.com/services/T00000000/B00000000/XXXXXXXXXXXXXXXXXXXXXXXX',
        ]);
    }

    public function withSms(): static
    {
        return $this->state(fn() => [
            'sms_enabled' => true,
            'sms_phone' => $this->faker->phoneNumber(),
        ]);
    }

    public function forProject(): static
    {
        return $this->state(fn() => [
            'user_id' => null,
            'project_id' => 'project_' . $this->faker->uuid(),
        ]);
    }

    public function forOrganization(): static
    {
        return $this->state(fn() => [
            'user_id' => null,
            'organization_id' => 'org_' . $this->faker->uuid(),
        ]);
    }

    public function daily(): static
    {
        return $this->state(fn() => [
            'budget_type' => 'daily',
            'min_threshold_percentage' => 75.0,
        ]);
    }

    public function monthly(): static
    {
        return $this->state(fn() => [
            'budget_type' => 'monthly',
            'min_threshold_percentage' => 80.0,
        ]);
    }

    public function withAdditionalEmails(): static
    {
        return $this->state(fn() => [
            'additional_emails' => [
                $this->faker->safeEmail(),
                $this->faker->safeEmail(),
            ],
        ]);
    }

    public function criticalOnly(): static
    {
        return $this->state(fn() => [
            'email_severities' => ['critical'],
            'slack_severities' => ['critical'],
            'sms_severities' => ['critical'],
            'min_threshold_percentage' => 95.0,
        ]);
    }

    public function allChannels(): static
    {
        return $this->state(fn() => [
            'email_enabled' => true,
            'slack_enabled' => true,
            'slack_webhook' => 'https://hooks.slack.com/services/T00000000/B00000000/XXXXXXXXXXXXXXXXXXXXXXXX',
            'sms_enabled' => true,
            'sms_phone' => $this->faker->phoneNumber(),
        ]);
    }
}