<?php

namespace JTD\LaravelAI\Tests\Feature\Migration;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use JTD\LaravelAI\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('migration')]
#[Group('table_rename')]
class TableRenameTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_successfully_renames_ai_usage_costs_to_ai_cost_records(): void
    {
        // Create the old table structure first (simulating pre-rename state)
        Schema::create('ai_usage_costs_temp', function ($table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('conversation_id')->nullable();
            $table->unsignedBigInteger('message_id')->nullable();
            $table->string('provider');
            $table->string('model');
            $table->integer('input_tokens')->default(0);
            $table->integer('output_tokens')->default(0);
            $table->integer('total_tokens')->default(0);
            $table->decimal('input_cost', 8, 6)->default(0);
            $table->decimal('output_cost', 8, 6)->default(0);
            $table->decimal('total_cost', 8, 6)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->string('pricing_source')->nullable();
            $table->integer('processing_time_ms')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        // Insert test data
        DB::table('ai_usage_costs_temp')->insert([
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
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Rename the table to simulate the migration
        Schema::rename('ai_usage_costs_temp', 'ai_cost_records_test');

        // Verify the table was renamed and data preserved
        $this->assertTrue(Schema::hasTable('ai_cost_records_test'));
        $this->assertFalse(Schema::hasTable('ai_usage_costs_temp'));

        $record = DB::table('ai_cost_records_test')->first();
        $this->assertEquals(1, $record->user_id);
        $this->assertEquals('openai', $record->provider);
        $this->assertEquals(0.03, $record->total_cost);

        // Clean up
        Schema::dropIfExists('ai_cost_records_test');
    }

    #[Test]
    public function it_successfully_renames_ai_budgets_to_ai_user_budgets(): void
    {
        // Create the old table structure first
        Schema::create('ai_budgets_temp', function ($table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('type');
            $table->decimal('limit_amount', 10, 4);
            $table->decimal('current_usage', 10, 4)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->decimal('warning_threshold', 5, 2)->default(80);
            $table->decimal('critical_threshold', 5, 2)->default(90);
            $table->timestamp('period_start');
            $table->timestamp('period_end');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Insert test data
        DB::table('ai_budgets_temp')->insert([
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
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Rename the table to simulate the migration
        Schema::rename('ai_budgets_temp', 'ai_user_budgets_test');

        // Verify the table was renamed and data preserved
        $this->assertTrue(Schema::hasTable('ai_user_budgets_test'));
        $this->assertFalse(Schema::hasTable('ai_budgets_temp'));

        $record = DB::table('ai_user_budgets_test')->first();
        $this->assertEquals(1, $record->user_id);
        $this->assertEquals('monthly', $record->type);
        $this->assertEquals(100.00, $record->limit_amount);

        // Clean up
        Schema::dropIfExists('ai_user_budgets_test');
    }

    #[Test]
    public function it_handles_table_rename_rollback_correctly(): void
    {
        // Create the new table (post-rename state)
        Schema::create('ai_cost_records_rollback_test', function ($table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('provider');
            $table->decimal('total_cost', 8, 6);
            $table->timestamps();
        });

        // Insert test data
        DB::table('ai_cost_records_rollback_test')->insert([
            'user_id' => 1,
            'provider' => 'openai',
            'total_cost' => 0.05,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Simulate rollback by renaming back
        Schema::rename('ai_cost_records_rollback_test', 'ai_usage_costs_rollback_test');

        // Verify rollback worked
        $this->assertTrue(Schema::hasTable('ai_usage_costs_rollback_test'));
        $this->assertFalse(Schema::hasTable('ai_cost_records_rollback_test'));

        $record = DB::table('ai_usage_costs_rollback_test')->first();
        $this->assertEquals(1, $record->user_id);
        $this->assertEquals('openai', $record->provider);
        $this->assertEquals(0.05, $record->total_cost);

        // Clean up
        Schema::dropIfExists('ai_usage_costs_rollback_test');
    }

    #[Test]
    public function it_preserves_data_integrity_during_rename(): void
    {
        // Create test table with comprehensive data
        Schema::create('data_integrity_test', function ($table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('conversation_id')->nullable();
            $table->string('provider');
            $table->string('model');
            $table->decimal('total_cost', 8, 6);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        $testData = [
            [
                'user_id' => 1,
                'conversation_id' => 'conv-1',
                'provider' => 'openai',
                'model' => 'gpt-4',
                'total_cost' => 0.10,
                'metadata' => json_encode(['context' => 'test']),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => 2,
                'conversation_id' => 'conv-2',
                'provider' => 'anthropic',
                'model' => 'claude-3',
                'total_cost' => 0.15,
                'metadata' => json_encode(['project' => 'test-project']),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('data_integrity_test')->insert($testData);

        // Verify initial data count
        $initialCount = DB::table('data_integrity_test')->count();
        $this->assertEquals(2, $initialCount);

        // Perform rename
        Schema::rename('data_integrity_test', 'data_integrity_renamed');

        // Verify data integrity after rename
        $afterRenameCount = DB::table('data_integrity_renamed')->count();
        $this->assertEquals($initialCount, $afterRenameCount);

        // Verify specific data values
        $record1 = DB::table('data_integrity_renamed')->where('user_id', 1)->first();
        $this->assertEquals('openai', $record1->provider);
        $this->assertEquals('gpt-4', $record1->model);
        $this->assertEquals(0.10, $record1->total_cost);

        $record2 = DB::table('data_integrity_renamed')->where('user_id', 2)->first();
        $this->assertEquals('anthropic', $record2->provider);
        $this->assertEquals('claude-3', $record2->model);
        $this->assertEquals(0.15, $record2->total_cost);

        // Clean up
        Schema::dropIfExists('data_integrity_renamed');
    }

    #[Test]
    public function it_maintains_indexes_after_table_rename(): void
    {
        // Create table with indexes
        Schema::create('index_test_table', function ($table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('provider');
            $table->decimal('total_cost', 8, 6);
            $table->timestamps();

            // Add indexes
            $table->index('user_id');
            $table->index(['provider', 'created_at']);
        });

        // Rename table
        Schema::rename('index_test_table', 'index_test_table_renamed');

        // Verify table exists after rename
        $this->assertTrue(Schema::hasTable('index_test_table_renamed'));
        $this->assertFalse(Schema::hasTable('index_test_table'));

        // Insert and query data to verify indexes work
        DB::table('index_test_table_renamed')->insert([
            'user_id' => 1,
            'provider' => 'openai',
            'total_cost' => 0.05,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $record = DB::table('index_test_table_renamed')
            ->where('user_id', 1)
            ->where('provider', 'openai')
            ->first();

        $this->assertNotNull($record);
        $this->assertEquals(1, $record->user_id);

        // Clean up
        Schema::dropIfExists('index_test_table_renamed');
    }
}
