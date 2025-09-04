<?php

namespace JTD\LaravelAI\Tests\Unit\Models;

use Illuminate\Foundation\Testing\RefreshDatabase;
use JTD\LaravelAI\Models\AIUsageCost;
use JTD\LaravelAI\Models\User;
use JTD\LaravelAI\Tests\TestCase;

class AIUsageCostTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    public function test_can_create_usage_cost_record()
    {
        $usageCost = AIUsageCost::factory()->create([
            'user_id' => $this->user->id,
            'provider' => 'openai',
            'model' => 'gpt-4',
            'input_tokens' => 1000,
            'output_tokens' => 500,
            'total_tokens' => 1500,
            'total_cost' => 0.045,
        ]);

        $this->assertInstanceOf(AIUsageCost::class, $usageCost);
        $this->assertEquals($this->user->id, $usageCost->user_id);
        $this->assertEquals('openai', $usageCost->provider);
        $this->assertEquals(1500, $usageCost->total_tokens);
    }

    public function test_cost_per_1k_tokens_calculation()
    {
        $usageCost = AIUsageCost::factory()->create([
            'total_tokens' => 2000,
            'total_cost' => 0.060, // $0.06 for 2000 tokens = $0.03 per 1k tokens
        ]);

        $this->assertEquals(30.0, $usageCost->cost_per_1k_tokens);
    }

    public function test_tokens_per_dollar_calculation()
    {
        $usageCost = AIUsageCost::factory()->create([
            'total_tokens' => 5000,
            'total_cost' => 0.25, // 5000 tokens for $0.25 = 20000 tokens per dollar
        ]);

        $this->assertEquals(20000, $usageCost->tokens_per_dollar);
    }

    public function test_processing_time_seconds()
    {
        $usageCost = AIUsageCost::factory()->create([
            'processing_time_ms' => 2500, // 2.5 seconds
        ]);

        $this->assertEquals(2.5, $usageCost->processing_time_seconds);
    }

    public function test_tokens_per_second_calculation()
    {
        $usageCost = AIUsageCost::factory()->create([
            'total_tokens' => 1000,
            'processing_time_ms' => 2000, // 2 seconds = 500 tokens/second
        ]);

        $this->assertEquals(500, $usageCost->tokens_per_second);
    }

    public function test_efficiency_score_calculation()
    {
        $usageCost = AIUsageCost::factory()->create([
            'total_tokens' => 1000,
            'total_cost' => 0.01, // $0.01 per 1000 tokens = $10 per 1k tokens
            'processing_time_ms' => 1000, // 1 second = 1000 tokens/second
        ]);

        $costEfficiency = 1000 / 10; // 100
        $speedEfficiency = 1000; // 1000
        $expectedScore = ($costEfficiency + $speedEfficiency) / 2; // 550

        $this->assertEquals($expectedScore, $usageCost->efficiency_score);
    }

    public function test_is_expensive()
    {
        $expensiveUsage = AIUsageCost::factory()->create(['total_cost' => 1.50]);
        $cheapUsage = AIUsageCost::factory()->create(['total_cost' => 0.50]);

        $this->assertTrue($expensiveUsage->isExpensive(1.0));
        $this->assertFalse($cheapUsage->isExpensive(1.0));
    }

    public function test_is_high_usage()
    {
        $highUsage = AIUsageCost::factory()->create(['total_tokens' => 15000]);
        $lowUsage = AIUsageCost::factory()->create(['total_tokens' => 5000]);

        $this->assertTrue($highUsage->isHighUsage(10000));
        $this->assertFalse($lowUsage->isHighUsage(10000));
    }

    public function test_is_slow()
    {
        $slowUsage = AIUsageCost::factory()->create(['processing_time_ms' => 8000]);
        $fastUsage = AIUsageCost::factory()->create(['processing_time_ms' => 2000]);

        $this->assertTrue($slowUsage->isSlow(5000));
        $this->assertFalse($fastUsage->isSlow(5000));
    }

    public function test_scope_for_user()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        AIUsageCost::factory()->create(['user_id' => $user1->id]);
        AIUsageCost::factory()->create(['user_id' => $user2->id]);

        $costs = AIUsageCost::forUser($user1->id)->get();

        $this->assertCount(1, $costs);
        $this->assertEquals($user1->id, $costs->first()->user_id);
    }

    public function test_scope_for_provider()
    {
        AIUsageCost::factory()->create(['provider' => 'openai']);
        AIUsageCost::factory()->create(['provider' => 'anthropic']);

        $costs = AIUsageCost::forProvider('openai')->get();

        $this->assertCount(1, $costs);
        $this->assertEquals('openai', $costs->first()->provider);
    }

    public function test_scope_for_model()
    {
        AIUsageCost::factory()->create(['model' => 'gpt-4']);
        AIUsageCost::factory()->create(['model' => 'gpt-3.5-turbo']);

        $costs = AIUsageCost::forModel('gpt-4')->get();

        $this->assertCount(1, $costs);
        $this->assertEquals('gpt-4', $costs->first()->model);
    }

    public function test_scope_today()
    {
        AIUsageCost::factory()->create(['created_at' => now()]);
        AIUsageCost::factory()->create(['created_at' => now()->subDay()]);

        $todayCosts = AIUsageCost::today()->get();

        $this->assertCount(1, $todayCosts);
    }

    public function test_scope_this_month()
    {
        AIUsageCost::factory()->create(['created_at' => now()]);
        AIUsageCost::factory()->create(['created_at' => now()->subMonth()]);

        $monthCosts = AIUsageCost::thisMonth()->get();

        $this->assertCount(1, $monthCosts);
    }

    public function test_scope_expensive()
    {
        AIUsageCost::factory()->create(['total_cost' => 2.0]);
        AIUsageCost::factory()->create(['total_cost' => 0.5]);

        $expensiveCosts = AIUsageCost::expensive(1.0)->get();

        $this->assertCount(1, $expensiveCosts);
        $this->assertEquals(2.0, $expensiveCosts->first()->total_cost);
    }

    public function test_scope_high_usage()
    {
        AIUsageCost::factory()->create(['total_tokens' => 15000]);
        AIUsageCost::factory()->create(['total_tokens' => 5000]);

        $highUsageCosts = AIUsageCost::highUsage(10000)->get();

        $this->assertCount(1, $highUsageCosts);
        $this->assertEquals(15000, $highUsageCosts->first()->total_tokens);
    }

    public function test_get_total_cost_for_user()
    {
        AIUsageCost::factory()->create([
            'user_id' => $this->user->id,
            'total_cost' => 1.50,
            'created_at' => now(),
        ]);

        AIUsageCost::factory()->create([
            'user_id' => $this->user->id,
            'total_cost' => 2.25,
            'created_at' => now(),
        ]);

        $totalCost = AIUsageCost::getTotalCostForUser($this->user->id);
        $this->assertEquals(3.75, $totalCost);
    }

    public function test_get_total_tokens_for_user()
    {
        AIUsageCost::factory()->create([
            'user_id' => $this->user->id,
            'total_tokens' => 1000,
            'created_at' => now(),
        ]);

        AIUsageCost::factory()->create([
            'user_id' => $this->user->id,
            'total_tokens' => 2500,
            'created_at' => now(),
        ]);

        $totalTokens = AIUsageCost::getTotalTokensForUser($this->user->id);
        $this->assertEquals(3500, $totalTokens);
    }

    public function test_get_provider_breakdown()
    {
        AIUsageCost::factory()->create([
            'user_id' => $this->user->id,
            'provider' => 'openai',
            'total_cost' => 1.0,
            'total_tokens' => 1000,
        ]);

        AIUsageCost::factory()->create([
            'user_id' => $this->user->id,
            'provider' => 'anthropic',
            'total_cost' => 2.0,
            'total_tokens' => 1500,
        ]);

        $breakdown = AIUsageCost::getProviderBreakdown($this->user->id);

        $this->assertCount(2, $breakdown);

        $anthropicData = collect($breakdown)->firstWhere('provider', 'anthropic');
        $this->assertEquals(2.0, $anthropicData['total_cost']);
        $this->assertEquals(1500, $anthropicData['total_tokens']);
    }

    public function test_get_model_breakdown()
    {
        AIUsageCost::factory()->create([
            'user_id' => $this->user->id,
            'provider' => 'openai',
            'model' => 'gpt-4',
            'total_cost' => 2.5,
            'total_tokens' => 1000,
        ]);

        AIUsageCost::factory()->create([
            'user_id' => $this->user->id,
            'provider' => 'openai',
            'model' => 'gpt-3.5-turbo',
            'total_cost' => 0.5,
            'total_tokens' => 2000,
        ]);

        $breakdown = AIUsageCost::getModelBreakdown($this->user->id, 'openai');

        $this->assertCount(2, $breakdown);

        $gpt4Data = collect($breakdown)->firstWhere('model', 'gpt-4');
        $this->assertEquals(2.5, $gpt4Data['total_cost']);
        $this->assertEquals('openai', $gpt4Data['provider']);
    }

    public function test_user_relationship()
    {
        $usageCost = AIUsageCost::factory()->create(['user_id' => $this->user->id]);

        $this->assertInstanceOf(User::class, $usageCost->user);
        $this->assertEquals($this->user->id, $usageCost->user->id);
    }

    public function test_metadata_cast_to_array()
    {
        $metadata = ['request_id' => 'req_123', 'session_id' => 'sess_456'];
        $usageCost = AIUsageCost::factory()->create(['metadata' => $metadata]);

        $this->assertIsArray($usageCost->metadata);
        $this->assertEquals('req_123', $usageCost->metadata['request_id']);
    }
}
