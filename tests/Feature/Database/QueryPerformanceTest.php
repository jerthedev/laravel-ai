<?php

namespace Tests\Feature\Database;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use JTDSoft\LaravelAI\Models\AIUsageCost;
use JTDSoft\LaravelAI\Models\AIBudgetAlert;
use JTDSoft\LaravelAI\Models\AICostAnalytics;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class QueryPerformanceTest extends TestCase
{
    use RefreshDatabase;

    private const PERFORMANCE_THRESHOLD_MS = 50;
    private const SAMPLE_SIZE = 5000;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTestData();
    }

    #[Test]
    #[Group('feature')]
    public function it_efficiently_queries_user_cost_summaries(): void
    {
        $startTime = microtime(true);

        // Query that should use ['user_id', 'created_at'] composite index
        $result = AIUsageCost::where('user_id', 1)
            ->where('created_at', '>=', Carbon::now()->subDays(30))
            ->selectRaw('
                SUM(total_cost) as total_spent,
                AVG(total_cost) as avg_cost_per_request,
                COUNT(*) as total_requests,
                SUM(total_tokens) as total_tokens_used
            ')
            ->first();

        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000;

        $this->assertLessThan(
            self::PERFORMANCE_THRESHOLD_MS,
            $executionTime,
            "User cost summary query took {$executionTime}ms"
        );

        $this->assertNotNull($result);
        $this->assertGreaterThan(0, $result->total_requests);
    }

    #[Test]
    #[Group('feature')]
    public function it_efficiently_queries_provider_model_analytics(): void
    {
        $startTime = microtime(true);

        // Query that should use ['provider', 'model', 'created_at'] composite index
        $results = AIUsageCost::where('provider', 'openai')
            ->where('model', 'gpt-4')
            ->where('created_at', '>=', Carbon::now()->subDays(7))
            ->selectRaw('
                DATE(created_at) as date,
                SUM(total_cost) as daily_cost,
                SUM(total_tokens) as daily_tokens,
                COUNT(*) as daily_requests
            ')
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get();

        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000;

        $this->assertLessThan(
            self::PERFORMANCE_THRESHOLD_MS,
            $executionTime,
            "Provider model analytics query took {$executionTime}ms"
        );

        $this->assertNotEmpty($results);
    }

    #[Test]
    #[Group('feature')]
    public function it_efficiently_queries_conversation_cost_tracking(): void
    {
        $startTime = microtime(true);

        // Query that should use ['conversation_id', 'created_at'] composite index
        $conversationCosts = AIUsageCost::where('conversation_id', 'conv_123')
            ->where('created_at', '>=', Carbon::now()->subHours(24))
            ->selectRaw('
                SUM(total_cost) as conversation_total,
                MAX(created_at) as last_activity,
                COUNT(*) as message_count
            ')
            ->first();

        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000;

        $this->assertLessThan(
            self::PERFORMANCE_THRESHOLD_MS,
            $executionTime,
            "Conversation cost tracking query took {$executionTime}ms"
        );

        $this->assertNotNull($conversationCosts);
    }

    #[Test]
    #[Group('feature')]
    public function it_efficiently_queries_multi_provider_user_analysis(): void
    {
        $startTime = microtime(true);

        // Query that should use ['user_id', 'provider', 'created_at'] composite index
        $providerBreakdown = AIUsageCost::where('user_id', 1)
            ->where('created_at', '>=', Carbon::now()->subDays(30))
            ->selectRaw('
                provider,
                SUM(total_cost) as provider_cost,
                AVG(total_cost) as avg_request_cost,
                COUNT(*) as request_count,
                SUM(total_tokens) as token_usage
            ')
            ->groupBy('provider')
            ->orderBy('provider_cost', 'desc')
            ->get();

        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000;

        $this->assertLessThan(
            self::PERFORMANCE_THRESHOLD_MS,
            $executionTime,
            "Multi-provider user analysis query took {$executionTime}ms"
        );

        $this->assertNotEmpty($providerBreakdown);
    }

    #[Test]
    #[Group('feature')]
    public function it_efficiently_queries_budget_alert_patterns(): void
    {
        $startTime = microtime(true);

        // Query that should use ['severity', 'sent_at'] composite index
        $alertPatterns = AIBudgetAlert::where('severity', 'high')
            ->where('sent_at', '>=', Carbon::now()->subDays(7))
            ->selectRaw('
                budget_type,
                COUNT(*) as alert_count,
                AVG(threshold_percentage) as avg_threshold,
                SUM(additional_cost) as total_overage
            ')
            ->groupBy('budget_type')
            ->orderBy('alert_count', 'desc')
            ->get();

        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000;

        $this->assertLessThan(
            self::PERFORMANCE_THRESHOLD_MS,
            $executionTime,
            "Budget alert patterns query took {$executionTime}ms"
        );

        // Results may be empty, which is fine
        $this->assertIsIterable($alertPatterns);
    }

    #[Test]
    #[Group('feature')]
    public function it_efficiently_queries_project_cost_analysis(): void
    {
        $startTime = microtime(true);

        // Query that should use ['project_id', 'sent_at'] composite index
        $projectAnalysis = AIBudgetAlert::where('project_id', 'proj_123')
            ->where('sent_at', '>=', Carbon::now()->subDays(30))
            ->selectRaw('
                DATE(sent_at) as date,
                COUNT(*) as alert_count,
                AVG(current_spending) as avg_spending,
                AVG(budget_limit) as avg_budget
            ')
            ->groupBy(DB::raw('DATE(sent_at)'))
            ->orderBy('date')
            ->get();

        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000;

        $this->assertLessThan(
            self::PERFORMANCE_THRESHOLD_MS,
            $executionTime,
            "Project cost analysis query took {$executionTime}ms"
        );

        $this->assertIsIterable($projectAnalysis);
    }

    #[Test]
    #[Group('feature')]
    public function it_efficiently_queries_cost_efficiency_analysis(): void
    {
        $startTime = microtime(true);

        // Query that should use ['cost_per_token', 'provider', 'created_at'] composite index
        $efficiencyAnalysis = AICostAnalytics::where('cost_per_token', '>', 0.001)
            ->where('created_at', '>=', Carbon::now()->subDays(7))
            ->selectRaw('
                provider,
                model,
                AVG(cost_per_token) as avg_efficiency,
                COUNT(*) as sample_count,
                MIN(cost_per_token) as best_efficiency,
                MAX(cost_per_token) as worst_efficiency
            ')
            ->groupBy('provider', 'model')
            ->orderBy('avg_efficiency')
            ->get();

        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000;

        $this->assertLessThan(
            self::PERFORMANCE_THRESHOLD_MS,
            $executionTime,
            "Cost efficiency analysis query took {$executionTime}ms"
        );

        $this->assertIsIterable($efficiencyAnalysis);
    }

    #[Test]
    #[Group('feature')]
    public function it_efficiently_queries_model_comparison_analysis(): void
    {
        $startTime = microtime(true);

        // Query that should use ['model', 'total_cost', 'created_at'] composite index
        $modelComparison = AICostAnalytics::where('total_cost', '>', 0.01)
            ->where('created_at', '>=', Carbon::now()->subDays(7))
            ->selectRaw('
                model,
                COUNT(*) as usage_count,
                AVG(total_cost) as avg_cost,
                SUM(total_cost) as total_spend,
                AVG(cost_per_token) as avg_efficiency
            ')
            ->groupBy('model')
            ->orderBy('total_spend', 'desc')
            ->get();

        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000;

        $this->assertLessThan(
            self::PERFORMANCE_THRESHOLD_MS,
            $executionTime,
            "Model comparison analysis query took {$executionTime}ms"
        );

        $this->assertIsIterable($modelComparison);
    }

    #[Test]
    #[Group('feature')]
    public function it_validates_complex_join_query_performance(): void
    {
        $startTime = microtime(true);

        // Complex query joining multiple tables - should benefit from optimized indexes
        $complexAnalysis = DB::table('ai_usage_costs as uc')
            ->leftJoin('ai_cost_analytics as ca', function ($join) {
                $join->on('uc.user_id', '=', 'ca.user_id')
                     ->on('uc.provider', '=', 'ca.provider')
                     ->on('uc.model', '=', 'ca.model');
            })
            ->leftJoin('ai_budget_alerts as ba', 'uc.user_id', '=', 'ba.user_id')
            ->where('uc.created_at', '>=', Carbon::now()->subDays(7))
            ->selectRaw('
                uc.user_id,
                uc.provider,
                SUM(uc.total_cost) as usage_cost,
                AVG(ca.cost_per_token) as avg_efficiency,
                COUNT(DISTINCT ba.id) as alert_count
            ')
            ->groupBy('uc.user_id', 'uc.provider')
            ->orderBy('usage_cost', 'desc')
            ->limit(10)
            ->get();

        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000;

        $this->assertLessThan(
            self::PERFORMANCE_THRESHOLD_MS * 2, // Allow more time for complex joins
            $executionTime,
            "Complex join query took {$executionTime}ms"
        );

        $this->assertIsIterable($complexAnalysis);
    }

    private function seedTestData(): void
    {
        // Seed AI Usage Costs
        $usageCosts = [];
        for ($i = 0; $i < self::SAMPLE_SIZE; $i++) {
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
                'created_at' => Carbon::now()->subMinutes(rand(1, 43200)),
                'updated_at' => Carbon::now(),
            ];

            if (count($usageCosts) >= 500) {
                DB::table('ai_usage_costs')->insert($usageCosts);
                $usageCosts = [];
            }
        }
        if (!empty($usageCosts)) {
            DB::table('ai_usage_costs')->insert($usageCosts);
        }

        // Seed AI Budget Alerts  
        $alerts = [];
        for ($i = 0; $i < intval(self::SAMPLE_SIZE * 0.3); $i++) {
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

            if (count($alerts) >= 200) {
                DB::table('ai_budget_alerts')->insert($alerts);
                $alerts = [];
            }
        }
        if (!empty($alerts)) {
            DB::table('ai_budget_alerts')->insert($alerts);
        }

        // Seed AI Cost Analytics
        $analytics = [];
        for ($i = 0; $i < intval(self::SAMPLE_SIZE * 0.5); $i++) {
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

            if (count($analytics) >= 300) {
                DB::table('ai_cost_analytics')->insert($analytics);
                $analytics = [];
            }
        }
        if (!empty($analytics)) {
            DB::table('ai_cost_analytics')->insert($analytics);
        }
    }
}