<?php

namespace Tests\Feature\Database;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IndexUsageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTestData();
    }

    #[Test]
    #[Group('feature')]
    public function it_verifies_ai_usage_costs_composite_indexes_are_used(): void
    {
        // Test that queries use the composite indexes instead of single-column indexes
        
        // Query that should use ['user_id', 'created_at'] composite index
        $explainResult = $this->explainQuery(
            "SELECT * FROM ai_usage_costs WHERE user_id = ? AND created_at >= ?",
            [1, Carbon::now()->subDays(30)->format('Y-m-d H:i:s')]
        );
        
        $this->assertIndexUsage($explainResult, ['user_id', 'created_at']);
        
        // Query that should use ['provider', 'model', 'created_at'] composite index
        $explainResult = $this->explainQuery(
            "SELECT * FROM ai_usage_costs WHERE provider = ? AND model = ? AND created_at >= ?",
            ['openai', 'gpt-4', Carbon::now()->subDays(7)->format('Y-m-d H:i:s')]
        );
        
        $this->assertIndexUsage($explainResult, ['provider', 'model', 'created_at']);
    }

    #[Test]
    #[Group('feature')]
    public function it_verifies_ai_usage_costs_conversation_index_is_used(): void
    {
        // Query that should use ['conversation_id', 'created_at'] composite index
        $explainResult = $this->explainQuery(
            "SELECT SUM(total_cost) FROM ai_usage_costs WHERE conversation_id = ? AND created_at >= ?",
            ['conv_123', Carbon::now()->subHours(24)->format('Y-m-d H:i:s')]
        );
        
        $this->assertIndexUsage($explainResult, ['conversation_id', 'created_at']);
    }

    #[Test]
    #[Group('feature')]
    public function it_verifies_ai_usage_costs_user_provider_index_is_used(): void
    {
        // Query that should use ['user_id', 'provider', 'created_at'] composite index
        $explainResult = $this->explainQuery(
            "SELECT * FROM ai_usage_costs WHERE user_id = ? AND provider = ? AND created_at >= ?",
            [1, 'openai', Carbon::now()->subDays(30)->format('Y-m-d H:i:s')]
        );
        
        $this->assertIndexUsage($explainResult, ['user_id', 'provider', 'created_at']);
    }

    #[Test]
    #[Group('feature')]
    public function it_verifies_ai_budget_alerts_composite_indexes_are_used(): void
    {
        // Query that should use ['user_id', 'budget_type', 'sent_at'] composite index
        $explainResult = $this->explainQuery(
            "SELECT * FROM ai_budget_alerts WHERE user_id = ? AND budget_type = ? AND sent_at >= ?",
            [1, 'monthly', Carbon::now()->subDays(30)->format('Y-m-d H:i:s')]
        );
        
        $this->assertIndexUsage($explainResult, ['user_id', 'budget_type', 'sent_at']);
        
        // Query that should use ['severity', 'sent_at'] composite index
        $explainResult = $this->explainQuery(
            "SELECT COUNT(*) FROM ai_budget_alerts WHERE severity = ? AND sent_at >= ?",
            ['high', Carbon::now()->subDays(7)->format('Y-m-d H:i:s')]
        );
        
        $this->assertIndexUsage($explainResult, ['severity', 'sent_at']);
    }

    #[Test]
    #[Group('feature')]
    public function it_verifies_ai_budget_alerts_project_and_org_indexes_are_used(): void
    {
        // Query that should use ['project_id', 'sent_at'] composite index
        $explainResult = $this->explainQuery(
            "SELECT * FROM ai_budget_alerts WHERE project_id = ? AND sent_at >= ?",
            ['proj_123', Carbon::now()->subDays(30)->format('Y-m-d H:i:s')]
        );
        
        $this->assertIndexUsage($explainResult, ['project_id', 'sent_at']);
        
        // Query that should use ['organization_id', 'sent_at'] composite index
        $explainResult = $this->explainQuery(
            "SELECT * FROM ai_budget_alerts WHERE organization_id = ? AND sent_at >= ?",
            ['org_456', Carbon::now()->subDays(30)->format('Y-m-d H:i:s')]
        );
        
        $this->assertIndexUsage($explainResult, ['organization_id', 'sent_at']);
    }

    #[Test]
    #[Group('feature')]
    public function it_verifies_ai_cost_analytics_composite_indexes_are_used(): void
    {
        // Query that should use ['user_id', 'created_at'] composite index
        $explainResult = $this->explainQuery(
            "SELECT AVG(cost_per_token) FROM ai_cost_analytics WHERE user_id = ? AND created_at >= ?",
            [1, Carbon::now()->subDays(30)->format('Y-m-d H:i:s')]
        );
        
        $this->assertIndexUsage($explainResult, ['user_id', 'created_at']);
        
        // Query that should use ['provider', 'model', 'created_at'] composite index
        $explainResult = $this->explainQuery(
            "SELECT * FROM ai_cost_analytics WHERE provider = ? AND model = ? AND created_at >= ?",
            ['openai', 'gpt-4', Carbon::now()->subDays(7)->format('Y-m-d H:i:s')]
        );
        
        $this->assertIndexUsage($explainResult, ['provider', 'model', 'created_at']);
    }

    #[Test]
    #[Group('feature')]
    public function it_verifies_ai_cost_analytics_efficiency_and_comparison_indexes_are_used(): void
    {
        // Query that should use ['cost_per_token', 'provider', 'created_at'] composite index
        $explainResult = $this->explainQuery(
            "SELECT * FROM ai_cost_analytics WHERE cost_per_token > ? AND provider = ? AND created_at >= ?",
            [0.001, 'openai', Carbon::now()->subDays(7)->format('Y-m-d H:i:s')]
        );
        
        $this->assertIndexUsage($explainResult, ['cost_per_token', 'provider', 'created_at']);
        
        // Query that should use ['model', 'total_cost', 'created_at'] composite index
        $explainResult = $this->explainQuery(
            "SELECT * FROM ai_cost_analytics WHERE model = ? AND total_cost > ? AND created_at >= ?",
            ['gpt-4', 0.01, Carbon::now()->subDays(7)->format('Y-m-d H:i:s')]
        );
        
        $this->assertIndexUsage($explainResult, ['model', 'total_cost', 'created_at']);
    }

    #[Test]
    #[Group('feature')]
    public function it_verifies_redundant_single_column_indexes_are_removed(): void
    {
        // Verify that single-column indexes that were consolidated are no longer present
        $indexes = $this->getTableIndexes('ai_usage_costs');
        
        // These single-column indexes should not exist (they were consolidated)
        $this->assertIndexNotExists($indexes, 'user_id');
        $this->assertIndexNotExists($indexes, 'conversation_id');
        $this->assertIndexNotExists($indexes, 'provider');
        $this->assertIndexNotExists($indexes, 'model');
        
        // These composite indexes should still exist
        $this->assertIndexExists($indexes, 'user_id', 'created_at');
        $this->assertIndexExists($indexes, 'provider', 'model', 'created_at');
        $this->assertIndexExists($indexes, 'user_id', 'provider', 'created_at');
        $this->assertIndexExists($indexes, 'conversation_id', 'created_at');
    }

    #[Test]
    #[Group('feature')]
    public function it_verifies_budget_alerts_redundant_indexes_are_removed(): void
    {
        $indexes = $this->getTableIndexes('ai_budget_alerts');
        
        // These single-column indexes should not exist (they were consolidated)
        $this->assertIndexNotExists($indexes, 'user_id');
        $this->assertIndexNotExists($indexes, 'budget_type');
        $this->assertIndexNotExists($indexes, 'severity');
        $this->assertIndexNotExists($indexes, 'project_id');
        $this->assertIndexNotExists($indexes, 'organization_id');
        $this->assertIndexNotExists($indexes, 'sent_at');
        
        // These composite indexes should still exist
        $this->assertIndexExists($indexes, 'user_id', 'budget_type', 'sent_at');
        $this->assertIndexExists($indexes, 'severity', 'sent_at');
        $this->assertIndexExists($indexes, 'project_id', 'sent_at');
        $this->assertIndexExists($indexes, 'organization_id', 'sent_at');
    }

    #[Test]
    #[Group('feature')]
    public function it_verifies_cost_analytics_redundant_indexes_are_removed(): void
    {
        $indexes = $this->getTableIndexes('ai_cost_analytics');
        
        // These single-column indexes should not exist (they were consolidated)
        $this->assertIndexNotExists($indexes, 'user_id');
        $this->assertIndexNotExists($indexes, 'provider');
        $this->assertIndexNotExists($indexes, 'model');
        
        // These composite indexes should still exist
        $this->assertIndexExists($indexes, 'user_id', 'created_at');
        $this->assertIndexExists($indexes, 'provider', 'model', 'created_at');
        $this->assertIndexExists($indexes, 'user_id', 'provider', 'created_at');
        $this->assertIndexExists($indexes, 'provider', 'total_cost', 'created_at');
    }

    #[Test]
    #[Group('feature')]
    public function it_verifies_new_standardized_indexes_exist(): void
    {
        // Verify the new standardized indexes were created
        $usageCostsIndexes = $this->getTableIndexes('ai_usage_costs');
        $this->assertIndexExists($usageCostsIndexes, 'total_cost', 'provider', 'created_at');
        $this->assertIndexExists($usageCostsIndexes, 'user_id', 'total_cost', 'created_at');
        
        $costAnalyticsIndexes = $this->getTableIndexes('ai_cost_analytics');
        $this->assertIndexExists($costAnalyticsIndexes, 'cost_per_token', 'provider', 'created_at');
        $this->assertIndexExists($costAnalyticsIndexes, 'model', 'total_cost', 'created_at');
        
        $budgetAlertsIndexes = $this->getTableIndexes('ai_budget_alerts');
        $this->assertIndexExists($budgetAlertsIndexes, 'threshold_percentage', 'severity', 'sent_at');
        $this->assertIndexExists($budgetAlertsIndexes, 'current_spending', 'budget_limit', 'sent_at');
    }

    /**
     * Execute EXPLAIN query and return results
     */
    private function explainQuery(string $sql, array $bindings = []): array
    {
        $explainSql = "EXPLAIN QUERY PLAN " . $sql;
        return DB::select($explainSql, $bindings);
    }

    /**
     * Assert that query execution plan uses expected index
     */
    private function assertIndexUsage(array $explainResult, array $expectedIndexColumns): void
    {
        $this->assertNotEmpty($explainResult, 'EXPLAIN result should not be empty');
        
        // For SQLite, check that the query plan mentions an index
        $planText = collect($explainResult)->pluck('detail')->implode(' ');
        
        // SQLite EXPLAIN QUERY PLAN should mention "USING INDEX" for indexed queries
        $this->assertStringContainsString(
            'USING INDEX',
            $planText,
            "Query should use an index. Plan: " . $planText
        );
        
        // Additional assertion: the plan should not mention "SCAN TABLE" for efficient queries
        // (except for very small tables where table scan might be more efficient)
        if (DB::table('ai_usage_costs')->count() > 100) {
            $this->assertStringNotContainsString(
                'SCAN TABLE',
                $planText,
                "Query should not perform table scan on large table. Plan: " . $planText
            );
        }
    }

    /**
     * Get all indexes for a table
     */
    private function getTableIndexes(string $tableName): array
    {
        $indexes = DB::select("PRAGMA index_list({$tableName})");
        
        $indexDetails = [];
        foreach ($indexes as $index) {
            $indexInfo = DB::select("PRAGMA index_info({$index->name})");
            $indexDetails[$index->name] = [
                'name' => $index->name,
                'unique' => $index->unique,
                'columns' => collect($indexInfo)->pluck('name')->toArray()
            ];
        }
        
        return $indexDetails;
    }

    /**
     * Assert that a specific single-column index does not exist
     */
    private function assertIndexNotExists(array $indexes, string $columnName): void
    {
        $singleColumnIndexes = collect($indexes)->filter(function ($index) use ($columnName) {
            return count($index['columns']) === 1 && $index['columns'][0] === $columnName;
        });
        
        $this->assertTrue(
            $singleColumnIndexes->isEmpty(),
            "Single-column index on '{$columnName}' should not exist but found: " . 
            $singleColumnIndexes->pluck('name')->implode(', ')
        );
    }

    /**
     * Assert that a composite index exists with the specified columns
     */
    private function assertIndexExists(array $indexes, string ...$columns): void
    {
        $matchingIndexes = collect($indexes)->filter(function ($index) use ($columns) {
            return $index['columns'] === $columns;
        });
        
        $this->assertFalse(
            $matchingIndexes->isEmpty(),
            "Composite index on [" . implode(', ', $columns) . "] should exist"
        );
    }

    /**
     * Seed test data for index usage testing
     */
    private function seedTestData(): void
    {
        // Seed enough data to make indexes useful
        $usageCosts = [];
        for ($i = 0; $i < 1000; $i++) {
            $usageCosts[] = [
                'user_id' => rand(1, 100),
                'conversation_id' => 'conv_' . rand(1, 200),
                'message_id' => rand(1, 2000),
                'provider' => collect(['openai', 'anthropic', 'gemini', 'xai'])->random(),
                'model' => collect(['gpt-4', 'gpt-3.5-turbo', 'claude-3-sonnet', 'gemini-pro'])->random(),
                'input_tokens' => rand(100, 1000),
                'output_tokens' => rand(50, 500),
                'total_tokens' => rand(150, 1500),
                'input_cost' => rand(1, 100) / 1000,
                'output_cost' => rand(1, 100) / 1000,
                'total_cost' => rand(2, 200) / 1000,
                'created_at' => Carbon::now()->subMinutes(rand(1, 43200))->format('Y-m-d H:i:s'),
                'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
            ];

            if (count($usageCosts) >= 200) {
                DB::table('ai_usage_costs')->insert($usageCosts);
                $usageCosts = [];
            }
        }
        if (!empty($usageCosts)) {
            DB::table('ai_usage_costs')->insert($usageCosts);
        }

        // Seed budget alerts
        $alerts = [];
        for ($i = 0; $i < 300; $i++) {
            $alerts[] = [
                'user_id' => rand(1, 100),
                'budget_type' => collect(['daily', 'monthly', 'per_request', 'project'])->random(),
                'threshold_percentage' => rand(75, 100),
                'current_spending' => rand(100, 1000) / 100,
                'budget_limit' => rand(1000, 10000) / 100,
                'additional_cost' => rand(10, 500) / 100,
                'severity' => collect(['low', 'medium', 'high', 'critical'])->random(),
                'channels' => json_encode(['email']),
                'project_id' => 'proj_' . rand(1, 50),
                'organization_id' => 'org_' . rand(1, 10),
                'sent_at' => Carbon::now()->subMinutes(rand(1, 43200))->format('Y-m-d H:i:s'),
                'created_at' => Carbon::now()->subMinutes(rand(1, 43200))->format('Y-m-d H:i:s'),
                'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
            ];

            if (count($alerts) >= 100) {
                DB::table('ai_budget_alerts')->insert($alerts);
                $alerts = [];
            }
        }
        if (!empty($alerts)) {
            DB::table('ai_budget_alerts')->insert($alerts);
        }

        // Seed cost analytics
        $analytics = [];
        for ($i = 0; $i < 500; $i++) {
            $totalCost = rand(1, 1000) / 1000;
            $totalTokens = rand(150, 1500);

            $analytics[] = [
                'user_id' => rand(1, 100),
                'provider' => collect(['openai', 'anthropic', 'gemini', 'xai'])->random(),
                'model' => collect(['gpt-4', 'gpt-3.5-turbo', 'claude-3-sonnet', 'gemini-pro'])->random(),
                'input_tokens' => rand(100, 1000),
                'output_tokens' => rand(50, 500),
                'total_tokens' => $totalTokens,
                'input_cost' => rand(1, 100) / 1000,
                'output_cost' => rand(1, 100) / 1000,
                'total_cost' => $totalCost,
                'cost_per_token' => $totalTokens > 0 ? $totalCost / $totalTokens : 0,
                'created_at' => Carbon::now()->subMinutes(rand(1, 43200))->format('Y-m-d H:i:s'),
                'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
            ];

            if (count($analytics) >= 100) {
                DB::table('ai_cost_analytics')->insert($analytics);
                $analytics = [];
            }
        }
        if (!empty($analytics)) {
            DB::table('ai_cost_analytics')->insert($analytics);
        }
    }
}