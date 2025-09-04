<?php

namespace JTD\LaravelAI\Tests\Performance\Database;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use JTD\LaravelAI\Models\AIBudgetAlert;
use JTD\LaravelAI\Models\AICostAnalytics;
use JTD\LaravelAI\Models\AIUsageCost;
use JTD\LaravelAI\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

class IndexPerformanceTest extends TestCase
{
    use RefreshDatabase;

    private const SAMPLE_SIZE = 10000;

    private const PERFORMANCE_THRESHOLD_MS = 100;

    #[Test]
    #[Group('performance')]
    public function it_validates_ai_cost_records_query_performance_after_index_consolidation(): void
    {
        // Seed test data
        $this->seedAIUsageCosts();

        $startTime = microtime(true);

        // Common query patterns that should benefit from composite indexes
        $results = collect([
            // User cost analysis query
            AIUsageCost::where('user_id', 1)
                ->where('created_at', '>=', Carbon::now()->subDays(30))
                ->sum('total_cost'),

            // Provider model analysis
            AIUsageCost::where('provider', 'openai')
                ->where('model', 'gpt-4')
                ->where('created_at', '>=', Carbon::now()->subDays(7))
                ->count(),

            // Conversation cost tracking
            AIUsageCost::where('conversation_id', 'conv_123')
                ->where('created_at', '>=', Carbon::now()->subHours(24))
                ->sum('total_cost'),

            // User provider cost breakdown
            AIUsageCost::where('user_id', 1)
                ->where('provider', 'openai')
                ->where('created_at', '>=', Carbon::now()->subDays(7))
                ->avg('total_cost'),
        ]);

        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000;

        $this->assertLessThan(
            self::PERFORMANCE_THRESHOLD_MS,
            $executionTime,
            "AI Usage Costs queries took {$executionTime}ms, exceeding threshold of " . self::PERFORMANCE_THRESHOLD_MS . 'ms'
        );

        // Verify we got meaningful results
        $this->assertGreaterThan(0, $results->filter()->count());
    }

    #[Test]
    #[Group('performance')]
    public function it_validates_ai_budget_alerts_query_performance_after_index_consolidation(): void
    {
        // Seed test data
        $this->seedAIBudgetAlerts();

        $startTime = microtime(true);

        $results = collect([
            // User budget alert history
            AIBudgetAlert::where('user_id', 1)
                ->where('budget_type', 'monthly')
                ->where('sent_at', '>=', Carbon::now()->subDays(30))
                ->count(),

            // Severity-based alert analysis
            AIBudgetAlert::where('severity', 'high')
                ->where('sent_at', '>=', Carbon::now()->subDays(7))
                ->count(),

            // Project alert tracking
            AIBudgetAlert::where('project_id', 'proj_123')
                ->where('sent_at', '>=', Carbon::now()->subDays(30))
                ->avg('threshold_percentage'),

            // Organization alert patterns
            AIBudgetAlert::where('organization_id', 'org_456')
                ->where('sent_at', '>=', Carbon::now()->subDays(30))
                ->sum('additional_cost'),
        ]);

        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000;

        $this->assertLessThan(
            self::PERFORMANCE_THRESHOLD_MS,
            $executionTime,
            "AI Budget Alerts queries took {$executionTime}ms, exceeding threshold of " . self::PERFORMANCE_THRESHOLD_MS . 'ms'
        );

        $this->assertGreaterThan(0, $results->filter()->count());
    }

    #[Test]
    #[Group('performance')]
    public function it_validates_ai_cost_analytics_query_performance_after_index_consolidation(): void
    {
        // Seed test data
        $this->seedAICostAnalytics();

        $startTime = microtime(true);

        $results = collect([
            // User analytics query
            AICostAnalytics::where('user_id', 1)
                ->where('created_at', '>=', Carbon::now()->subDays(30))
                ->avg('cost_per_token'),

            // Provider model efficiency analysis
            AICostAnalytics::where('provider', 'openai')
                ->where('model', 'gpt-4')
                ->where('created_at', '>=', Carbon::now()->subDays(7))
                ->avg('cost_per_token'),

            // Cost efficiency tracking
            AICostAnalytics::where('cost_per_token', '>', 0.001)
                ->where('created_at', '>=', Carbon::now()->subDays(7))
                ->count(),

            // Provider cost comparison
            AICostAnalytics::where('provider', 'openai')
                ->where('total_cost', '>', 1.0)
                ->where('created_at', '>=', Carbon::now()->subDays(7))
                ->sum('total_cost'),
        ]);

        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000;

        $this->assertLessThan(
            self::PERFORMANCE_THRESHOLD_MS,
            $executionTime,
            "AI Cost Analytics queries took {$executionTime}ms, exceeding threshold of " . self::PERFORMANCE_THRESHOLD_MS . 'ms'
        );

        $this->assertGreaterThan(0, $results->filter()->count());
    }

    #[Test]
    #[Group('performance')]
    public function it_measures_index_usage_effectiveness(): void
    {
        $this->seedAIUsageCosts();

        // Enable query logging
        DB::enableQueryLog();

        // Execute complex query that should use multiple indexes
        AIUsageCost::where('user_id', 1)
            ->where('provider', 'openai')
            ->where('created_at', '>=', Carbon::now()->subDays(30))
            ->orderBy('total_cost', 'desc')
            ->limit(100)
            ->get();

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertNotEmpty($queries, 'Queries should be logged');

        foreach ($queries as $query) {
            $this->assertLessThan(
                self::PERFORMANCE_THRESHOLD_MS,
                $query['time'],
                "Query took {$query['time']}ms: {$query['query']}"
            );
        }
    }

    #[Test]
    #[Group('performance')]
    public function it_validates_write_performance_improvements(): void
    {
        $batchSize = 1000;

        $startTime = microtime(true);

        // Perform batch inserts to test write performance
        $usageCosts = [];
        for ($i = 0; $i < $batchSize; $i++) {
            $usageCosts[] = [
                'user_id' => rand(1, 100),
                'conversation_id' => 'conv_' . rand(1, 1000),
                'message_id' => rand(1, 10000),
                'provider' => collect(['openai', 'anthropic', 'gemini'])->random(),
                'model' => 'gpt-4',
                'input_tokens' => rand(100, 1000),
                'output_tokens' => rand(50, 500),
                'total_tokens' => rand(150, 1500),
                'input_cost' => rand(1, 100) / 1000,
                'output_cost' => rand(1, 100) / 1000,
                'total_cost' => rand(2, 200) / 1000,
                'created_at' => Carbon::now()->subMinutes(rand(1, 1440)),
                'updated_at' => Carbon::now(),
            ];
        }

        DB::table('ai_cost_records')->insert($usageCosts);

        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000;

        // With fewer indexes, write performance should improve
        $this->assertLessThan(
            $batchSize * 0.5, // 0.5ms per record maximum
            $executionTime,
            "Batch insert of {$batchSize} records took {$executionTime}ms"
        );

        // Verify data was inserted correctly
        $this->assertEquals($batchSize, DB::table('ai_cost_records')->count());
    }

    private function seedAIUsageCosts(): void
    {
        $batchSize = self::SAMPLE_SIZE;
        $usageCosts = [];

        for ($i = 0; $i < $batchSize; $i++) {
            $usageCosts[] = [
                'user_id' => rand(1, 100),
                'conversation_id' => 'conv_' . rand(1, 1000),
                'message_id' => rand(1, 10000),
                'provider' => collect(['openai', 'anthropic', 'gemini', 'xai'])->random(),
                'model' => collect(['gpt-4', 'gpt-3.5-turbo', 'claude-3-sonnet', 'gemini-pro'])->random(),
                'input_tokens' => rand(100, 1000),
                'output_tokens' => rand(50, 500),
                'total_tokens' => rand(150, 1500),
                'input_cost' => rand(1, 100) / 1000,
                'output_cost' => rand(1, 100) / 1000,
                'total_cost' => rand(2, 200) / 1000,
                'created_at' => Carbon::now()->subMinutes(rand(1, 43200)), // Random within 30 days
                'updated_at' => Carbon::now(),
            ];

            // Insert in batches to avoid memory issues
            if (count($usageCosts) >= 1000) {
                DB::table('ai_cost_records')->insert($usageCosts);
                $usageCosts = [];
            }
        }

        if (! empty($usageCosts)) {
            DB::table('ai_cost_records')->insert($usageCosts);
        }
    }

    private function seedAIBudgetAlerts(): void
    {
        $batchSize = self::SAMPLE_SIZE;
        $alerts = [];

        for ($i = 0; $i < $batchSize; $i++) {
            $alerts[] = [
                'user_id' => rand(1, 100),
                'budget_type' => collect(['daily', 'monthly', 'per_request', 'project'])->random(),
                'threshold_percentage' => rand(75, 100),
                'current_spending' => rand(100, 1000) / 100,
                'budget_limit' => rand(1000, 10000) / 100,
                'additional_cost' => rand(10, 500) / 100,
                'severity' => collect(['low', 'medium', 'high', 'critical'])->random(),
                'channels' => json_encode(['email']),
                'project_id' => 'proj_' . rand(1, 100),
                'organization_id' => 'org_' . rand(1, 20),
                'sent_at' => Carbon::now()->subMinutes(rand(1, 43200)),
                'created_at' => Carbon::now()->subMinutes(rand(1, 43200)),
                'updated_at' => Carbon::now(),
            ];

            if (count($alerts) >= 1000) {
                DB::table('ai_budget_alerts')->insert($alerts);
                $alerts = [];
            }
        }

        if (! empty($alerts)) {
            DB::table('ai_budget_alerts')->insert($alerts);
        }
    }

    private function seedAICostAnalytics(): void
    {
        $batchSize = self::SAMPLE_SIZE;
        $analytics = [];

        for ($i = 0; $i < $batchSize; $i++) {
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
                'created_at' => Carbon::now()->subMinutes(rand(1, 43200)),
                'updated_at' => Carbon::now(),
            ];

            if (count($analytics) >= 1000) {
                DB::table('ai_cost_analytics')->insert($analytics);
                $analytics = [];
            }
        }

        if (! empty($analytics)) {
            DB::table('ai_cost_analytics')->insert($analytics);
        }
    }
}
