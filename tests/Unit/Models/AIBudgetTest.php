<?php

namespace JTD\LaravelAI\Tests\Unit\Models;

use JTD\LaravelAI\Models\AIBudget;
use JTD\LaravelAI\Models\User;
use JTD\LaravelAI\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AIBudgetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create a test user
        $this->user = User::factory()->create();
    }

    public function test_can_create_budget_for_user()
    {
        $budget = AIBudget::createForUser($this->user->id, 'monthly', [
            'limit_amount' => 100.50,
            'warning_threshold' => 75.0,
            'critical_threshold' => 85.0,
        ]);

        $this->assertInstanceOf(AIBudget::class, $budget);
        $this->assertEquals($this->user->id, $budget->user_id);
        $this->assertEquals('monthly', $budget->type);
        $this->assertEquals(100.50, $budget->limit_amount);
        $this->assertEquals(0, $budget->current_usage);
        $this->assertTrue($budget->is_active);
    }

    public function test_budget_usage_percentage_calculation()
    {
        $budget = AIBudget::factory()->create([
            'limit_amount' => 100.0,
            'current_usage' => 25.0,
        ]);

        $this->assertEquals(25.0, $budget->usage_percentage);
    }

    public function test_budget_remaining_amount_calculation()
    {
        $budget = AIBudget::factory()->create([
            'limit_amount' => 100.0,
            'current_usage' => 30.0,
        ]);

        $this->assertEquals(70.0, $budget->remaining_amount);
    }

    public function test_budget_status_normal()
    {
        $budget = AIBudget::factory()->create([
            'limit_amount' => 100.0,
            'current_usage' => 50.0,
            'warning_threshold' => 75.0,
            'critical_threshold' => 90.0,
        ]);

        $this->assertEquals('normal', $budget->status);
        $this->assertFalse($budget->isWarning());
        $this->assertFalse($budget->isCritical());
    }

    public function test_budget_status_warning()
    {
        $budget = AIBudget::factory()->create([
            'limit_amount' => 100.0,
            'current_usage' => 80.0,
            'warning_threshold' => 75.0,
            'critical_threshold' => 90.0,
        ]);

        $this->assertEquals('warning', $budget->status);
        $this->assertTrue($budget->isWarning());
        $this->assertFalse($budget->isCritical());
    }

    public function test_budget_status_critical()
    {
        $budget = AIBudget::factory()->create([
            'limit_amount' => 100.0,
            'current_usage' => 95.0,
            'warning_threshold' => 75.0,
            'critical_threshold' => 90.0,
        ]);

        $this->assertEquals('critical', $budget->status);
        $this->assertTrue($budget->isWarning());
        $this->assertTrue($budget->isCritical());
    }

    public function test_budget_exceeded()
    {
        $budget = AIBudget::factory()->create([
            'limit_amount' => 100.0,
            'current_usage' => 105.0,
        ]);

        $this->assertTrue($budget->isExceeded());
    }

    public function test_add_usage_to_budget()
    {
        $budget = AIBudget::factory()->create([
            'limit_amount' => 100.0,
            'current_usage' => 25.0,
        ]);

        $budget->addUsage(15.5);

        $budget->refresh();
        $this->assertEquals(40.5, $budget->current_usage);
    }

    public function test_reset_budget_period()
    {
        $budget = AIBudget::factory()->create([
            'current_usage' => 50.0,
            'type' => 'monthly',
        ]);

        $oldPeriodStart = $budget->period_start;
        $budget->resetPeriod();

        $budget->refresh();
        $this->assertEquals(0, $budget->current_usage);
        $this->assertNotEquals($oldPeriodStart, $budget->period_start);
    }

    public function test_scope_for_user()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        
        AIBudget::factory()->create(['user_id' => $user1->id]);
        AIBudget::factory()->create(['user_id' => $user2->id]);

        $budgets = AIBudget::forUser($user1->id)->get();
        
        $this->assertCount(1, $budgets);
        $this->assertEquals($user1->id, $budgets->first()->user_id);
    }

    public function test_scope_by_type()
    {
        AIBudget::factory()->create(['type' => 'daily']);
        AIBudget::factory()->create(['type' => 'monthly']);
        AIBudget::factory()->create(['type' => 'yearly']);

        $monthlyBudgets = AIBudget::byType('monthly')->get();
        
        $this->assertCount(1, $monthlyBudgets);
        $this->assertEquals('monthly', $monthlyBudgets->first()->type);
    }

    public function test_scope_active()
    {
        AIBudget::factory()->create(['is_active' => true]);
        AIBudget::factory()->create(['is_active' => false]);

        $activeBudgets = AIBudget::active()->get();
        
        $this->assertCount(1, $activeBudgets);
        $this->assertTrue($activeBudgets->first()->is_active);
    }

    public function test_scope_current_period()
    {
        $now = now();
        
        // Budget within current period
        AIBudget::factory()->create([
            'period_start' => $now->copy()->subHour(),
            'period_end' => $now->copy()->addHour(),
        ]);
        
        // Budget outside current period
        AIBudget::factory()->create([
            'period_start' => $now->copy()->subDays(2),
            'period_end' => $now->copy()->subDays(1),
        ]);

        $currentBudgets = AIBudget::currentPeriod()->get();
        
        $this->assertCount(1, $currentBudgets);
        $this->assertTrue($currentBudgets->first()->isCurrentPeriod());
    }

    public function test_is_current_period()
    {
        $now = now();
        
        $currentBudget = AIBudget::factory()->create([
            'period_start' => $now->copy()->subHour(),
            'period_end' => $now->copy()->addHour(),
        ]);
        
        $pastBudget = AIBudget::factory()->create([
            'period_start' => $now->copy()->subDays(2),
            'period_end' => $now->copy()->subDays(1),
        ]);

        $this->assertTrue($currentBudget->isCurrentPeriod());
        $this->assertFalse($pastBudget->isCurrentPeriod());
    }

    public function test_calculate_period_dates_daily()
    {
        $today = now()->startOfDay();
        $budget = AIBudget::createForUser($this->user->id, 'daily');

        $this->assertEquals($today->format('Y-m-d'), $budget->period_start->format('Y-m-d'));
        $this->assertEquals($today->format('Y-m-d'), $budget->period_end->format('Y-m-d'));
    }

    public function test_calculate_period_dates_monthly()
    {
        $startOfMonth = now()->startOfMonth();
        $endOfMonth = now()->endOfMonth();
        
        $budget = AIBudget::createForUser($this->user->id, 'monthly');

        $this->assertEquals($startOfMonth->format('Y-m-d'), $budget->period_start->format('Y-m-d'));
        $this->assertEquals($endOfMonth->format('Y-m-d'), $budget->period_end->format('Y-m-d'));
    }

    public function test_user_relationship()
    {
        $budget = AIBudget::factory()->create(['user_id' => $this->user->id]);

        $this->assertInstanceOf(User::class, $budget->user);
        $this->assertEquals($this->user->id, $budget->user->id);
    }
}