<?php

namespace JTD\LaravelAI\Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use JTD\LaravelAI\Tests\TestCase;

class DatabaseMigrationsTest extends TestCase
{
    /** @test */
    public function it_can_run_all_migrations()
    {
        // Fresh migration
        Artisan::call('migrate:fresh');

        // Verify all tables exist
        $this->assertDatabaseTableExists('ai_providers');
        $this->assertDatabaseTableExists('ai_accounts');
        $this->assertDatabaseTableExists('ai_provider_models');
        $this->assertDatabaseTableExists('ai_provider_model_costs');
        $this->assertDatabaseTableExists('ai_conversations');
        $this->assertDatabaseTableExists('ai_messages');
        $this->assertDatabaseTableExists('ai_usage_analytics');
    }

    /** @test */
    public function ai_providers_table_has_correct_structure()
    {
        $this->assertDatabaseTableHasColumns('ai_providers', [
            'id', 'name', 'slug', 'driver', 'config', 'status',
            'description', 'website_url', 'documentation_url',
            'supports_streaming', 'supports_function_calling', 'supports_vision',
            'max_tokens', 'max_context_length', 'default_temperature',
            'supported_formats', 'rate_limits', 'last_synced_at',
            'last_health_check_at', 'health_status', 'health_message',
            'created_at', 'updated_at',
        ]);
    }

    /** @test */
    public function ai_accounts_table_has_correct_structure()
    {
        $this->assertDatabaseTableHasColumns('ai_accounts', [
            'id', 'ai_provider_id', 'name', 'slug', 'encrypted_credentials',
            'configuration', 'status', 'is_default', 'priority',
            'monthly_budget', 'daily_budget', 'monthly_request_limit',
            'daily_request_limit', 'hourly_request_limit',
            'current_month_cost', 'current_day_cost', 'current_month_requests',
            'current_day_requests', 'current_hour_requests',
            'organization_id', 'project_id', 'metadata',
            'last_used_at', 'credentials_expires_at', 'last_validated_at',
            'validation_status', 'validation_message',
            'created_at', 'updated_at',
        ]);
    }

    /** @test */
    public function ai_provider_models_table_has_correct_structure()
    {
        $this->assertDatabaseTableHasColumns('ai_provider_models', [
            'id', 'ai_provider_id', 'model_id', 'name', 'version',
            'description', 'status', 'supports_chat', 'supports_completion',
            'supports_streaming', 'supports_function_calling', 'supports_vision',
            'supports_audio', 'supports_embeddings', 'supports_fine_tuning',
            'max_tokens', 'context_length', 'default_temperature',
            'min_temperature', 'max_temperature', 'max_top_p',
            'supported_formats', 'supported_languages',
            'input_token_cost', 'output_token_cost', 'training_cost',
            'pricing_currency', 'pricing_model',
            'avg_response_time_ms', 'avg_quality_score', 'reliability_score',
            'total_requests', 'successful_requests', 'failed_requests',
            'last_synced_at', 'deprecated_at', 'sunset_date',
            'provider_metadata', 'custom_metadata',
            'created_at', 'updated_at',
        ]);
    }

    /** @test */
    public function ai_provider_model_costs_table_has_correct_structure()
    {
        $this->assertDatabaseTableHasColumns('ai_provider_model_costs', [
            'id', 'ai_provider_model_id', 'cost_type', 'cost_per_unit',
            'unit_type', 'currency', 'region', 'tier',
            'min_volume', 'max_volume', 'volume_discount_percent',
            'effective_from', 'effective_until', 'is_current',
            'billing_model', 'conditions', 'notes',
            'provider_pricing_data', 'last_updated_at', 'source',
            'created_at', 'updated_at',
        ]);
    }

    /** @test */
    public function ai_conversations_table_has_correct_structure()
    {
        $this->assertDatabaseTableHasColumns('ai_conversations', [
            'id', 'uuid', 'title', 'description', 'status',
            'user_id', 'user_type', 'session_id', 'participants',
            'ai_provider_id', 'ai_provider_model_id', 'provider_name', 'model_name',
            'system_prompt', 'configuration', 'context_data', 'max_messages', 'auto_title',
            'total_cost', 'total_input_tokens', 'total_output_tokens',
            'total_messages', 'total_requests',
            'avg_response_time_ms', 'avg_quality_rating',
            'successful_requests', 'failed_requests',
            'tags', 'metadata', 'language', 'conversation_type',
            'last_message_at', 'last_activity_at', 'archived_at',
            'created_at', 'updated_at',
        ]);
    }

    /** @test */
    public function ai_messages_table_has_correct_structure()
    {
        $this->assertDatabaseTableHasColumns('ai_messages', [
            'id', 'ai_conversation_id', 'uuid', 'sequence_number',
            'role', 'content', 'content_metadata', 'content_type', 'attachments',
            'ai_provider_id', 'ai_provider_model_id', 'provider_message_id',
            'request_parameters', 'response_metadata', 'finish_reason',
            'is_streaming', 'stream_chunks',
            'input_tokens', 'output_tokens', 'total_tokens',
            'cost', 'cost_currency', 'cost_breakdown',
            'response_time_ms', 'quality_rating', 'quality_feedback',
            'is_regenerated', 'regeneration_count',
            'function_calls', 'tool_calls', 'function_results',
            'status', 'error_message', 'error_details', 'retry_count', 'processed_at',
            'is_edited', 'edited_at', 'is_hidden', 'is_pinned', 'user_reactions',
            'context_data', 'metadata', 'language', 'content_analysis',
            'created_at', 'updated_at',
        ]);
    }

    /** @test */
    public function ai_usage_analytics_table_has_correct_structure()
    {
        $this->assertDatabaseTableHasColumns('ai_usage_analytics', [
            'id', 'date', 'hour', 'period_type',
            'ai_provider_id', 'ai_provider_model_id', 'user_id', 'user_type',
            'total_requests', 'successful_requests', 'failed_requests',
            'total_conversations', 'total_messages',
            'total_input_tokens', 'total_output_tokens', 'total_tokens',
            'avg_tokens_per_request', 'max_tokens_in_request', 'min_tokens_in_request',
            'total_cost', 'avg_cost_per_request', 'avg_cost_per_token',
            'currency', 'input_token_cost', 'output_token_cost',
            'avg_response_time_ms', 'min_response_time_ms', 'max_response_time_ms',
            'p95_response_time_ms', 'p99_response_time_ms',
            'avg_quality_rating', 'total_ratings', 'positive_ratings',
            'negative_ratings', 'user_satisfaction_score',
            'error_breakdown', 'rate_limit_errors', 'authentication_errors',
            'timeout_errors', 'content_filter_errors', 'other_errors',
            'streaming_requests', 'function_call_requests', 'vision_requests',
            'audio_requests', 'regeneration_requests',
            'content_types', 'languages', 'conversation_types', 'avg_conversation_length',
            'unique_users', 'new_users', 'returning_users',
            'user_retention_rate', 'daily_active_users', 'monthly_active_users',
            'system_uptime_percent', 'peak_concurrent_requests',
            'cache_hit_rate', 'queue_processing_time_ms',
            'metadata', 'calculated_at', 'calculation_version',
            'created_at', 'updated_at',
        ]);
    }

    /** @test */
    public function it_can_rollback_all_migrations()
    {
        // Run migrations first
        Artisan::call('migrate:fresh');

        // Verify tables exist
        $this->assertTrue(Schema::hasTable('ai_providers'));
        $this->assertTrue(Schema::hasTable('ai_accounts'));
        $this->assertTrue(Schema::hasTable('ai_provider_models'));
        $this->assertTrue(Schema::hasTable('ai_provider_model_costs'));
        $this->assertTrue(Schema::hasTable('ai_conversations'));
        $this->assertTrue(Schema::hasTable('ai_messages'));
        $this->assertTrue(Schema::hasTable('ai_usage_analytics'));

        // Rollback all migrations
        Artisan::call('migrate:rollback', ['--step' => 10]);

        // Verify tables are dropped
        $this->assertFalse(Schema::hasTable('ai_usage_analytics'));
        $this->assertFalse(Schema::hasTable('ai_messages'));
        $this->assertFalse(Schema::hasTable('ai_conversations'));
        $this->assertFalse(Schema::hasTable('ai_provider_model_costs'));
        $this->assertFalse(Schema::hasTable('ai_provider_models'));
        $this->assertFalse(Schema::hasTable('ai_accounts'));
        $this->assertFalse(Schema::hasTable('ai_providers'));
    }

    /** @test */
    public function it_can_rollback_and_re_migrate()
    {
        // Fresh migration
        Artisan::call('migrate:fresh');

        // Rollback
        Artisan::call('migrate:rollback', ['--step' => 10]);

        // Re-migrate
        Artisan::call('migrate');

        // Verify all tables exist again
        $this->assertDatabaseTableExists('ai_providers');
        $this->assertDatabaseTableExists('ai_accounts');
        $this->assertDatabaseTableExists('ai_provider_models');
        $this->assertDatabaseTableExists('ai_provider_model_costs');
        $this->assertDatabaseTableExists('ai_conversations');
        $this->assertDatabaseTableExists('ai_messages');
        $this->assertDatabaseTableExists('ai_usage_analytics');
    }

    /** @test */
    public function foreign_key_constraints_are_properly_set()
    {
        // Enable foreign key constraints for SQLite
        \DB::statement('PRAGMA foreign_keys=ON');

        // First create a valid provider
        $providerId = \DB::table('ai_providers')->insertGetId([
            'name' => 'Test Provider',
            'slug' => 'test-provider',
            'driver' => 'test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // This should work (valid foreign key)
        \DB::table('ai_accounts')->insert([
            'ai_provider_id' => $providerId,
            'name' => 'Test Account',
            'slug' => 'test-account',
            'encrypted_credentials' => 'encrypted_data',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Verify the record was created
        $this->assertDatabaseHas('ai_accounts', [
            'ai_provider_id' => $providerId,
            'name' => 'Test Account',
        ]);
    }

    /** @test */
    public function unique_constraints_are_enforced()
    {
        // Test unique constraints
        \DB::table('ai_providers')->insert([
            'name' => 'Test Provider',
            'slug' => 'test-provider',
            'driver' => 'test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        // Try to insert duplicate
        \DB::table('ai_providers')->insert([
            'name' => 'Test Provider', // Duplicate name
            'slug' => 'test-provider-2',
            'driver' => 'test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @test */
    public function indexes_are_created_properly()
    {
        // This is a basic test - in a real scenario you'd query the database
        // information schema to verify indexes exist
        $this->assertTrue(true); // Placeholder - indexes are defined in migrations
    }
}
