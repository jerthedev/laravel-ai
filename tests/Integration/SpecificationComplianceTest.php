<?php

namespace JTD\LaravelAI\Tests\Integration;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use JTD\LaravelAI\Models\AIBudget;
use JTD\LaravelAI\Models\AIUsageCost;
use JTD\LaravelAI\Services\BudgetCacheService;
use JTD\LaravelAI\Services\CostAnalyticsService;
use JTD\LaravelAI\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('integration')]
#[Group('specification_compliance')]
class SpecificationComplianceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_complies_with_specification_table_naming_for_cost_records(): void
    {
        // Verify the model uses the correct table name per specification
        $model = new AIUsageCost;
        $this->assertEquals('ai_cost_records', $model->getTable());

        // Verify we can create records according to specification
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

        // Verify database compliance
        $this->assertDatabaseHas('ai_cost_records', [
            'id' => $costRecord->id,
            'user_id' => 1,
            'provider' => 'openai',
            'total_cost' => 0.03,
        ]);
    }

    #[Test]
    public function it_complies_with_specification_table_naming_for_user_budgets(): void
    {
        // Verify the model uses the correct table name per specification
        $model = new AIBudget;
        $this->assertEquals('ai_user_budgets', $model->getTable());

        // Verify we can create records according to specification
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

        // Verify database compliance
        $this->assertDatabaseHas('ai_user_budgets', [
            'id' => $budget->id,
            'user_id' => 1,
            'type' => 'monthly',
            'limit_amount' => 100.00,
        ]);
    }

    #[Test]
    public function it_maintains_service_integration_with_new_table_names(): void
    {
        // Create test data
        AIUsageCost::create([
            'user_id' => 1,
            'conversation_id' => 'test-conversation',
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

        AIBudget::create([
            'user_id' => 1,
            'type' => 'daily',
            'limit_amount' => 10.00,
            'current_usage' => 0.03,
            'currency' => 'USD',
            'warning_threshold' => 80.0,
            'critical_threshold' => 90.0,
            'period_start' => now()->startOfDay(),
            'period_end' => now()->endOfDay(),
            'is_active' => true,
        ]);

        // Test CostAnalyticsService works with new table names
        $analyticsService = app(CostAnalyticsService::class);
        $providerBreakdown = $analyticsService->getCostBreakdownByProvider(1, 'today');

        $this->assertIsArray($providerBreakdown);
        $this->assertArrayHasKey('breakdown', $providerBreakdown);
        $this->assertArrayHasKey('totals', $providerBreakdown);

        if (! empty($providerBreakdown['breakdown'])) {
            $this->assertEquals('openai', $providerBreakdown['breakdown'][0]['provider']);
            $this->assertEquals(0.03, $providerBreakdown['breakdown'][0]['total_cost']);
        }

        // Test BudgetCacheService works with new table names
        $budgetService = app(BudgetCacheService::class);
        $dailySpending = $budgetService->getDailySpending(1);
        $budgetLimit = $budgetService->getBudgetLimit(1, 'daily');

        $this->assertEquals(0.03, $dailySpending);
        $this->assertEquals(10.00, $budgetLimit);
    }

    #[Test]
    public function it_supports_specification_required_queries(): void
    {
        // Create test data that matches specification examples
        AIUsageCost::create([
            'user_id' => 1,
            'conversation_id' => 'conversation-123',
            'message_id' => 1,
            'provider' => 'openai',
            'model' => 'gpt-4',
            'input_tokens' => 200,
            'output_tokens' => 100,
            'total_tokens' => 300,
            'input_cost' => 0.006,
            'output_cost' => 0.012,
            'total_cost' => 0.018,
            'currency' => 'USD',
            'pricing_source' => 'database',
            'processing_time_ms' => 1500,
        ]);

        AIBudget::create([
            'user_id' => 1,
            'type' => 'monthly',
            'limit_amount' => 100.00,
            'current_usage' => 0.018,
            'currency' => 'USD',
            'warning_threshold' => 80.0,
            'critical_threshold' => 90.0,
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
            'is_active' => true,
        ]);

        // Test specification-required queries work with new table names

        // Query 1: Get user's total cost
        $totalCost = DB::table('ai_cost_records')
            ->where('user_id', 1)
            ->sum('total_cost');
        $this->assertEquals(0.018, $totalCost);

        // Query 2: Get active user budgets
        $activeBudgets = DB::table('ai_user_budgets')
            ->where('user_id', 1)
            ->where('is_active', true)
            ->get();
        $this->assertCount(1, $activeBudgets);

        // Query 3: Get cost breakdown by provider
        $providerCosts = DB::table('ai_cost_records')
            ->select('provider', DB::raw('SUM(total_cost) as total'))
            ->where('user_id', 1)
            ->groupBy('provider')
            ->get();
        $this->assertCount(1, $providerCosts);
        $this->assertEquals('openai', $providerCosts[0]->provider);
        $this->assertEquals(0.018, $providerCosts[0]->total);

        // Query 4: Join costs with budgets (relationship query)
        $costBudgetJoin = DB::table('ai_cost_records as costs')
            ->join('ai_user_budgets as budgets', 'costs.user_id', '=', 'budgets.user_id')
            ->where('costs.user_id', 1)
            ->where('budgets.type', 'monthly')
            ->select('costs.total_cost', 'budgets.limit_amount')
            ->first();

        $this->assertNotNull($costBudgetJoin);
        $this->assertEquals(0.018, $costBudgetJoin->total_cost);
        $this->assertEquals(100.00, $costBudgetJoin->limit_amount);
    }

    #[Test]
    public function it_maintains_specification_data_integrity(): void
    {
        // Test that all required columns exist per specification
        $costRecordColumns = DB::getSchemaBuilder()->getColumnListing('ai_cost_records');
        $requiredCostColumns = [
            'user_id', 'conversation_id', 'message_id', 'provider', 'model',
            'input_tokens', 'output_tokens', 'total_tokens', 'input_cost',
            'output_cost', 'total_cost', 'currency', 'pricing_source',
        ];

        foreach ($requiredCostColumns as $column) {
            $this->assertContains($column, $costRecordColumns,
                "Required column '$column' missing from ai_cost_records table"
            );
        }

        $budgetColumns = DB::getSchemaBuilder()->getColumnListing('ai_user_budgets');
        $requiredBudgetColumns = [
            'user_id', 'type', 'limit_amount', 'current_usage', 'currency',
            'warning_threshold', 'critical_threshold', 'period_start',
            'period_end', 'is_active',
        ];

        foreach ($requiredBudgetColumns as $column) {
            $this->assertContains($column, $budgetColumns,
                "Required column '$column' missing from ai_user_budgets table"
            );
        }
    }

    #[Test]
    public function it_enforces_specification_data_types(): void
    {
        // Create record with specification-compliant data types
        $costRecord = AIUsageCost::create([
            'user_id' => 1,
            'conversation_id' => 'conv-123',
            'message_id' => 456,
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'input_tokens' => 150,
            'output_tokens' => 75,
            'total_tokens' => 225,
            'input_cost' => 0.0045,
            'output_cost' => 0.009,
            'total_cost' => 0.0135,
            'currency' => 'USD',
            'pricing_source' => 'api',
            'processing_time_ms' => 1200,
            'metadata' => ['context' => 'test'],
        ]);

        $budget = AIBudget::create([
            'user_id' => 1,
            'type' => 'daily',
            'limit_amount' => 25.50,
            'current_usage' => 0.0135,
            'currency' => 'USD',
            'warning_threshold' => 85.5,
            'critical_threshold' => 95.0,
            'period_start' => now()->startOfDay(),
            'period_end' => now()->endOfDay(),
            'is_active' => true,
        ]);

        // Verify data types are preserved correctly
        $retrievedCost = AIUsageCost::find($costRecord->id);
        $this->assertIsInt($retrievedCost->user_id);
        $this->assertIsInt($retrievedCost->input_tokens);
        $this->assertIsFloat($retrievedCost->total_cost);
        $this->assertIsArray($retrievedCost->metadata);

        $retrievedBudget = AIBudget::find($budget->id);
        $this->assertIsInt($retrievedBudget->user_id);
        $this->assertIsFloat($retrievedBudget->limit_amount);
        $this->assertIsBool($retrievedBudget->is_active);
    }
}
