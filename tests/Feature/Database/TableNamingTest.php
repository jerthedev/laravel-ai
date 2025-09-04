<?php

namespace JTD\LaravelAI\Tests\Feature\Database;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use JTD\LaravelAI\Models\AIBudget;
use JTD\LaravelAI\Models\AIUsageCost;
use JTD\LaravelAI\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('database')]
#[Group('table_naming')]
class TableNamingTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_uses_correct_table_names_for_cost_records(): void
    {
        $this->assertEquals('ai_cost_records', (new AIUsageCost)->getTable());
    }

    #[Test]
    public function it_uses_correct_table_names_for_user_budgets(): void
    {
        $this->assertEquals('ai_user_budgets', (new AIBudget)->getTable());
    }

    #[Test]
    public function it_can_create_cost_records_in_correct_table(): void
    {
        $costRecord = AIUsageCost::create([
            'user_id' => 1,
            'conversation_id' => 'test-conversation',
            'message_id' => 1,
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'input_tokens' => 100,
            'output_tokens' => 50,
            'total_tokens' => 150,
            'input_cost' => 0.01,
            'output_cost' => 0.02,
            'total_cost' => 0.03,
            'currency' => 'USD',
            'pricing_source' => 'api',
            'processing_time_ms' => 1000,
        ]);

        $this->assertDatabaseHas('ai_cost_records', [
            'id' => $costRecord->id,
            'user_id' => 1,
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'total_cost' => 0.03,
        ]);
    }

    #[Test]
    public function it_can_create_user_budgets_in_correct_table(): void
    {
        $budget = AIBudget::create([
            'user_id' => 1,
            'type' => 'monthly',
            'limit_amount' => 100.00,
            'current_usage' => 0.00,
            'currency' => 'USD',
            'warning_threshold' => 80.0,
            'critical_threshold' => 90.0,
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('ai_user_budgets', [
            'id' => $budget->id,
            'user_id' => 1,
            'type' => 'monthly',
            'limit_amount' => 100.00,
            'is_active' => true,
        ]);
    }

    #[Test]
    public function it_maintains_model_relationships_with_new_table_names(): void
    {
        $budget = AIBudget::create([
            'user_id' => 1,
            'type' => 'monthly',
            'limit_amount' => 100.00,
            'current_usage' => 25.50,
            'currency' => 'USD',
            'warning_threshold' => 80.0,
            'critical_threshold' => 90.0,
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
            'is_active' => true,
        ]);

        AIUsageCost::create([
            'user_id' => 1,
            'conversation_id' => 'test-conversation',
            'message_id' => 1,
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'input_tokens' => 100,
            'output_tokens' => 50,
            'total_tokens' => 150,
            'input_cost' => 0.01,
            'output_cost' => 0.02,
            'total_cost' => 0.03,
            'currency' => 'USD',
            'pricing_source' => 'api',
            'processing_time_ms' => 1000,
        ]);

        // Test budget relationship to usage costs
        $usageCosts = $budget->usageCosts;
        $this->assertCount(1, $usageCosts);
        $this->assertEquals(0.03, $usageCosts->first()->total_cost);
    }

    #[Test]
    public function it_confirms_old_table_names_do_not_exist(): void
    {
        // Confirm old table names are no longer used
        $this->assertFalse(Schema::hasTable('ai_usage_costs'));
        $this->assertFalse(Schema::hasTable('ai_budgets'));
    }

    #[Test]
    public function it_confirms_new_table_names_exist(): void
    {
        // Confirm new table names are in use
        $this->assertTrue(Schema::hasTable('ai_cost_records'));
        $this->assertTrue(Schema::hasTable('ai_user_budgets'));
    }

    #[Test]
    public function it_maintains_proper_table_structure_for_cost_records(): void
    {
        $expectedColumns = [
            'id',
            'user_id',
            'conversation_id',
            'message_id',
            'provider',
            'model',
            'input_tokens',
            'output_tokens',
            'total_tokens',
            'input_cost',
            'output_cost',
            'total_cost',
            'currency',
            'pricing_source',
            'processing_time_ms',
            'metadata',
            'created_at',
            'updated_at',
        ];

        foreach ($expectedColumns as $column) {
            $this->assertTrue(
                Schema::hasColumn('ai_cost_records', $column),
                "Column '$column' should exist in ai_cost_records table"
            );
        }
    }

    #[Test]
    public function it_maintains_proper_table_structure_for_user_budgets(): void
    {
        $expectedColumns = [
            'id',
            'user_id',
            'type',
            'limit_amount',
            'current_usage',
            'currency',
            'warning_threshold',
            'critical_threshold',
            'period_start',
            'period_end',
            'is_active',
            'created_at',
            'updated_at',
        ];

        foreach ($expectedColumns as $column) {
            $this->assertTrue(
                Schema::hasColumn('ai_user_budgets', $column),
                "Column '$column' should exist in ai_user_budgets table"
            );
        }
    }
}
