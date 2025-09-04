<?php

namespace Tests\Performance\Database;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WritePerformanceTest extends TestCase
{
    use RefreshDatabase;

    private const WRITE_PERFORMANCE_TARGET_MS_PER_RECORD = 0.5;

    private const BATCH_SIZE = 1000;

    private const TOTAL_IMPROVEMENT_TARGET_PERCENT = 15;

    #[Test]
    #[Group('performance')]
    public function it_measures_ai_cost_records_insert_performance_improvement(): void
    {
        // Measure baseline performance with original indexes (simulated)
        $baselineTime = $this->measureBaselineInsertPerformance('ai_cost_records');

        // Measure current performance with consolidated indexes
        $currentTime = $this->measureCurrentInsertPerformance('ai_cost_records');

        // Calculate improvement percentage
        $improvementPercent = (($baselineTime - $currentTime) / $baselineTime) * 100;

        $this->assertGreaterThan(
            self::TOTAL_IMPROVEMENT_TARGET_PERCENT,
            $improvementPercent,
            "Write performance improvement of {$improvementPercent}% is less than target " . self::TOTAL_IMPROVEMENT_TARGET_PERCENT . '%'
        );

        $this->assertLessThan(
            self::WRITE_PERFORMANCE_TARGET_MS_PER_RECORD,
            $currentTime / self::BATCH_SIZE,
            'Current write performance of ' . ($currentTime / self::BATCH_SIZE) . 'ms per record exceeds target'
        );
    }

    #[Test]
    #[Group('performance')]
    public function it_measures_ai_budget_alerts_insert_performance_improvement(): void
    {
        $baselineTime = $this->measureBaselineInsertPerformance('ai_budget_alerts');
        $currentTime = $this->measureCurrentInsertPerformance('ai_budget_alerts');

        $improvementPercent = (($baselineTime - $currentTime) / $baselineTime) * 100;

        $this->assertGreaterThan(
            self::TOTAL_IMPROVEMENT_TARGET_PERCENT,
            $improvementPercent,
            "Budget alerts write performance improvement of {$improvementPercent}% is less than target " . self::TOTAL_IMPROVEMENT_TARGET_PERCENT . '%'
        );

        $this->assertLessThan(
            self::WRITE_PERFORMANCE_TARGET_MS_PER_RECORD,
            $currentTime / self::BATCH_SIZE,
            'Current write performance exceeds target'
        );
    }

    #[Test]
    #[Group('performance')]
    public function it_measures_ai_cost_analytics_insert_performance_improvement(): void
    {
        $baselineTime = $this->measureBaselineInsertPerformance('ai_cost_analytics');
        $currentTime = $this->measureCurrentInsertPerformance('ai_cost_analytics');

        $improvementPercent = (($baselineTime - $currentTime) / $baselineTime) * 100;

        $this->assertGreaterThan(
            self::TOTAL_IMPROVEMENT_TARGET_PERCENT,
            $improvementPercent,
            "Cost analytics write performance improvement of {$improvementPercent}% is less than target " . self::TOTAL_IMPROVEMENT_TARGET_PERCENT . '%'
        );

        $this->assertLessThan(
            self::WRITE_PERFORMANCE_TARGET_MS_PER_RECORD,
            $currentTime / self::BATCH_SIZE,
            'Current write performance exceeds target'
        );
    }

    #[Test]
    #[Group('performance')]
    public function it_validates_bulk_insert_performance(): void
    {
        $startTime = microtime(true);

        // Perform large batch insert to test write scalability
        $usageCosts = $this->generateUsageCostsData(self::BATCH_SIZE * 5);

        foreach (array_chunk($usageCosts, self::BATCH_SIZE) as $chunk) {
            DB::table('ai_cost_records')->insert($chunk);
        }

        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000;

        $avgTimePerRecord = $executionTime / (self::BATCH_SIZE * 5);

        $this->assertLessThan(
            self::WRITE_PERFORMANCE_TARGET_MS_PER_RECORD,
            $avgTimePerRecord,
            "Bulk insert average time per record: {$avgTimePerRecord}ms"
        );

        // Verify all records were inserted
        $this->assertEquals(self::BATCH_SIZE * 5, DB::table('ai_cost_records')->count());
    }

    #[Test]
    #[Group('performance')]
    public function it_validates_concurrent_write_performance(): void
    {
        $concurrentBatches = 5;
        $recordsPerBatch = self::BATCH_SIZE;

        $startTime = microtime(true);

        // Simulate concurrent writes
        $insertPromises = [];

        for ($i = 0; $i < $concurrentBatches; $i++) {
            $batchData = $this->generateUsageCostsData($recordsPerBatch);

            // Insert each batch
            DB::table('ai_cost_records')->insert($batchData);
        }

        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000;

        $totalRecords = $concurrentBatches * $recordsPerBatch;
        $avgTimePerRecord = $executionTime / $totalRecords;

        $this->assertLessThan(
            self::WRITE_PERFORMANCE_TARGET_MS_PER_RECORD * 1.5, // Allow some overhead for concurrency
            $avgTimePerRecord,
            "Concurrent write average time per record: {$avgTimePerRecord}ms"
        );

        // Verify all records were inserted
        $this->assertEquals($totalRecords, DB::table('ai_cost_records')->count());
    }

    #[Test]
    #[Group('performance')]
    public function it_measures_update_performance_improvement(): void
    {
        // Seed initial data
        $initialData = $this->generateUsageCostsData(self::BATCH_SIZE);
        DB::table('ai_cost_records')->insert($initialData);

        $startTime = microtime(true);

        // Perform batch updates
        DB::table('ai_cost_records')
            ->where('provider', 'openai')
            ->update([
                'total_cost' => DB::raw('total_cost * 1.1'),
                'updated_at' => Carbon::now(),
            ]);

        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000;

        $updatedCount = DB::table('ai_cost_records')
            ->where('provider', 'openai')
            ->count();

        $avgTimePerUpdate = $updatedCount > 0 ? $executionTime / $updatedCount : $executionTime;

        $this->assertLessThan(
            self::WRITE_PERFORMANCE_TARGET_MS_PER_RECORD * 2, // Updates can be slower than inserts
            $avgTimePerUpdate,
            "Update performance: {$avgTimePerUpdate}ms per record"
        );

        $this->assertGreaterThan(0, $updatedCount, 'No records were updated');
    }

    #[Test]
    #[Group('performance')]
    public function it_measures_delete_performance_with_optimized_indexes(): void
    {
        // Seed data for deletion test
        $testData = $this->generateUsageCostsData(self::BATCH_SIZE);
        DB::table('ai_cost_records')->insert($testData);

        $startTime = microtime(true);

        // Delete using indexed column (should be fast)
        $deletedCount = DB::table('ai_cost_records')
            ->where('created_at', '<', Carbon::now()->subDays(30))
            ->delete();

        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000;

        // Even if no records match the criteria, the query should be fast due to indexes
        $this->assertLessThan(
            self::WRITE_PERFORMANCE_TARGET_MS_PER_RECORD * self::BATCH_SIZE * 0.1, // Delete should be very fast
            $executionTime,
            "Delete operation took {$executionTime}ms"
        );
    }

    private function measureBaselineInsertPerformance(string $table): float
    {
        // Simulate baseline performance by temporarily adding redundant indexes
        // This represents the performance before index consolidation
        $this->addTemporaryIndexes($table);

        $data = $this->generateDataForTable($table, self::BATCH_SIZE);

        $startTime = microtime(true);
        DB::table($table)->insert($data);
        $endTime = microtime(true);

        $this->dropTemporaryIndexes($table);
        $this->truncateTable($table);

        return ($endTime - $startTime) * 1000;
    }

    private function measureCurrentInsertPerformance(string $table): float
    {
        $data = $this->generateDataForTable($table, self::BATCH_SIZE);

        $startTime = microtime(true);
        DB::table($table)->insert($data);
        $endTime = microtime(true);

        $this->truncateTable($table);

        return ($endTime - $startTime) * 1000;
    }

    private function addTemporaryIndexes(string $table): void
    {
        switch ($table) {
            case 'ai_cost_records':
                DB::statement('CREATE INDEX temp_user_id ON ai_cost_records (user_id)');
                DB::statement('CREATE INDEX temp_provider ON ai_cost_records (provider)');
                DB::statement('CREATE INDEX temp_model ON ai_cost_records (model)');
                DB::statement('CREATE INDEX temp_conversation_id ON ai_cost_records (conversation_id)');
                break;

            case 'ai_budget_alerts':
                DB::statement('CREATE INDEX temp_user_id ON ai_budget_alerts (user_id)');
                DB::statement('CREATE INDEX temp_budget_type ON ai_budget_alerts (budget_type)');
                DB::statement('CREATE INDEX temp_severity ON ai_budget_alerts (severity)');
                DB::statement('CREATE INDEX temp_project_id ON ai_budget_alerts (project_id)');
                DB::statement('CREATE INDEX temp_organization_id ON ai_budget_alerts (organization_id)');
                DB::statement('CREATE INDEX temp_sent_at ON ai_budget_alerts (sent_at)');
                break;

            case 'ai_cost_analytics':
                DB::statement('CREATE INDEX temp_user_id ON ai_cost_analytics (user_id)');
                DB::statement('CREATE INDEX temp_provider ON ai_cost_analytics (provider)');
                DB::statement('CREATE INDEX temp_model ON ai_cost_analytics (model)');
                break;
        }
    }

    private function dropTemporaryIndexes(string $table): void
    {
        switch ($table) {
            case 'ai_cost_records':
                DB::statement('DROP INDEX IF EXISTS temp_user_id');
                DB::statement('DROP INDEX IF EXISTS temp_provider');
                DB::statement('DROP INDEX IF EXISTS temp_model');
                DB::statement('DROP INDEX IF EXISTS temp_conversation_id');
                break;

            case 'ai_budget_alerts':
                DB::statement('DROP INDEX IF EXISTS temp_user_id');
                DB::statement('DROP INDEX IF EXISTS temp_budget_type');
                DB::statement('DROP INDEX IF EXISTS temp_severity');
                DB::statement('DROP INDEX IF EXISTS temp_project_id');
                DB::statement('DROP INDEX IF EXISTS temp_organization_id');
                DB::statement('DROP INDEX IF EXISTS temp_sent_at');
                break;

            case 'ai_cost_analytics':
                DB::statement('DROP INDEX IF EXISTS temp_user_id');
                DB::statement('DROP INDEX IF EXISTS temp_provider');
                DB::statement('DROP INDEX IF EXISTS temp_model');
                break;
        }
    }

    private function generateDataForTable(string $table, int $count): array
    {
        switch ($table) {
            case 'ai_cost_records':
                return $this->generateUsageCostsData($count);

            case 'ai_budget_alerts':
                return $this->generateBudgetAlertsData($count);

            case 'ai_cost_analytics':
                return $this->generateCostAnalyticsData($count);

            default:
                throw new \InvalidArgumentException("Unknown table: {$table}");
        }
    }

    private function generateUsageCostsData(int $count): array
    {
        $data = [];
        for ($i = 0; $i < $count; $i++) {
            $data[] = [
                'user_id' => rand(1, 1000),
                'conversation_id' => 'conv_' . rand(1, 5000),
                'message_id' => rand(1, 50000),
                'provider' => collect(['openai', 'anthropic', 'gemini', 'xai'])->random(),
                'model' => collect(['gpt-4', 'gpt-3.5-turbo', 'claude-3-sonnet', 'gemini-pro'])->random(),
                'input_tokens' => rand(100, 1000),
                'output_tokens' => rand(50, 500),
                'total_tokens' => rand(150, 1500),
                'input_cost' => rand(1, 100) / 1000,
                'output_cost' => rand(1, 100) / 1000,
                'total_cost' => rand(2, 200) / 1000,
                'created_at' => Carbon::now()->subMinutes(rand(1, 10080))->format('Y-m-d H:i:s'),
                'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
            ];
        }

        return $data;
    }

    private function generateBudgetAlertsData(int $count): array
    {
        $data = [];
        for ($i = 0; $i < $count; $i++) {
            $data[] = [
                'user_id' => rand(1, 1000),
                'budget_type' => collect(['daily', 'monthly', 'per_request', 'project'])->random(),
                'threshold_percentage' => rand(75, 100),
                'current_spending' => rand(100, 1000) / 100,
                'budget_limit' => rand(1000, 10000) / 100,
                'additional_cost' => rand(10, 500) / 100,
                'severity' => collect(['low', 'medium', 'high', 'critical'])->random(),
                'channels' => json_encode(['email']),
                'project_id' => 'proj_' . rand(1, 500),
                'organization_id' => 'org_' . rand(1, 100),
                'sent_at' => Carbon::now()->subMinutes(rand(1, 10080))->format('Y-m-d H:i:s'),
                'created_at' => Carbon::now()->subMinutes(rand(1, 10080))->format('Y-m-d H:i:s'),
                'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
            ];
        }

        return $data;
    }

    private function generateCostAnalyticsData(int $count): array
    {
        $data = [];
        for ($i = 0; $i < $count; $i++) {
            $totalCost = rand(1, 1000) / 1000;
            $totalTokens = rand(150, 1500);

            $data[] = [
                'user_id' => rand(1, 1000),
                'provider' => collect(['openai', 'anthropic', 'gemini', 'xai'])->random(),
                'model' => collect(['gpt-4', 'gpt-3.5-turbo', 'claude-3-sonnet', 'gemini-pro'])->random(),
                'input_tokens' => rand(100, 1000),
                'output_tokens' => rand(50, 500),
                'total_tokens' => $totalTokens,
                'input_cost' => rand(1, 100) / 1000,
                'output_cost' => rand(1, 100) / 1000,
                'total_cost' => $totalCost,
                'cost_per_token' => $totalTokens > 0 ? $totalCost / $totalTokens : 0,
                'created_at' => Carbon::now()->subMinutes(rand(1, 10080))->format('Y-m-d H:i:s'),
                'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
            ];
        }

        return $data;
    }

    private function truncateTable(string $table): void
    {
        DB::statement("DELETE FROM {$table}");
    }
}
