<?php

namespace JTD\LaravelAI\Database\Factories;

use JTD\LaravelAI\Models\AIBudget;
use JTD\LaravelAI\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AIBudgetFactory extends Factory
{
    protected $model = AIBudget::class;

    public function definition(): array
    {
        $type = $this->faker->randomElement(['daily', 'weekly', 'monthly', 'yearly']);
        $periodDates = $this->calculatePeriodDates($type);
        
        return [
            'user_id' => User::factory(),
            'type' => $type,
            'limit_amount' => $this->faker->randomFloat(2, 10, 1000),
            'current_usage' => $this->faker->randomFloat(2, 0, 100),
            'currency' => 'USD',
            'warning_threshold' => $this->faker->randomFloat(2, 70, 80),
            'critical_threshold' => $this->faker->randomFloat(2, 85, 95),
            'period_start' => $periodDates['start'],
            'period_end' => $periodDates['end'],
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn() => [
            'is_active' => false,
        ]);
    }

    public function daily(): static
    {
        $periodDates = $this->calculatePeriodDates('daily');
        
        return $this->state(fn() => [
            'type' => 'daily',
            'period_start' => $periodDates['start'],
            'period_end' => $periodDates['end'],
        ]);
    }

    public function monthly(): static
    {
        $periodDates = $this->calculatePeriodDates('monthly');
        
        return $this->state(fn() => [
            'type' => 'monthly',
            'period_start' => $periodDates['start'],
            'period_end' => $periodDates['end'],
        ]);
    }

    public function exceeded(): static
    {
        return $this->state(fn(array $attributes) => [
            'current_usage' => ($attributes['limit_amount'] ?? 100) * 1.1,
        ]);
    }

    public function warning(): static
    {
        return $this->state(fn(array $attributes) => [
            'current_usage' => ($attributes['limit_amount'] ?? 100) * 0.8,
            'warning_threshold' => 75.0,
        ]);
    }

    public function critical(): static
    {
        return $this->state(fn(array $attributes) => [
            'current_usage' => ($attributes['limit_amount'] ?? 100) * 0.92,
            'critical_threshold' => 90.0,
        ]);
    }

    protected function calculatePeriodDates(string $type): array
    {
        $now = now();
        
        return match ($type) {
            'daily' => [
                'start' => $now->copy()->startOfDay(),
                'end' => $now->copy()->endOfDay(),
            ],
            'weekly' => [
                'start' => $now->copy()->startOfWeek(),
                'end' => $now->copy()->endOfWeek(),
            ],
            'monthly' => [
                'start' => $now->copy()->startOfMonth(),
                'end' => $now->copy()->endOfMonth(),
            ],
            'yearly' => [
                'start' => $now->copy()->startOfYear(),
                'end' => $now->copy()->endOfYear(),
            ],
            default => [
                'start' => $now->copy()->startOfMonth(),
                'end' => $now->copy()->endOfMonth(),
            ],
        };
    }
}