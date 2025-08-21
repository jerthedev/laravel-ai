<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ai_usage_analytics', function (Blueprint $table) {
            $table->id();

            // Time Period and Aggregation
            $table->date('date'); // Date for daily aggregation
            $table->integer('hour')->nullable(); // Hour for hourly aggregation (0-23)
            $table->enum('period_type', ['hourly', 'daily', 'weekly', 'monthly'])->default('daily');

            // Entity Relationships
            $table->foreignId('ai_provider_id')->nullable()->constrained('ai_providers')->onDelete('cascade');
            $table->foreignId('ai_provider_model_id')->nullable()->constrained('ai_provider_models')->onDelete('cascade');
            $table->unsignedBigInteger('user_id')->nullable(); // Polymorphic user relationship
            $table->string('user_type')->nullable(); // For polymorphic relationship

            // Usage Metrics
            $table->integer('total_requests')->default(0);
            $table->integer('successful_requests')->default(0);
            $table->integer('failed_requests')->default(0);
            $table->integer('total_conversations')->default(0);
            $table->integer('total_messages')->default(0);

            // Token Usage
            $table->bigInteger('total_input_tokens')->default(0);
            $table->bigInteger('total_output_tokens')->default(0);
            $table->bigInteger('total_tokens')->default(0);
            $table->decimal('avg_tokens_per_request', 10, 2)->nullable();
            $table->integer('max_tokens_in_request')->nullable();
            $table->integer('min_tokens_in_request')->nullable();

            // Cost Analytics
            $table->decimal('total_cost', 12, 6)->default(0);
            $table->decimal('avg_cost_per_request', 10, 6)->nullable();
            $table->decimal('avg_cost_per_token', 12, 8)->nullable();
            $table->string('currency', 3)->default('USD');
            $table->decimal('input_token_cost', 10, 6)->default(0);
            $table->decimal('output_token_cost', 10, 6)->default(0);

            // Performance Metrics
            $table->integer('avg_response_time_ms')->nullable();
            $table->integer('min_response_time_ms')->nullable();
            $table->integer('max_response_time_ms')->nullable();
            $table->integer('p95_response_time_ms')->nullable(); // 95th percentile
            $table->integer('p99_response_time_ms')->nullable(); // 99th percentile

            // Quality Metrics
            $table->decimal('avg_quality_rating', 3, 2)->nullable();
            $table->integer('total_ratings')->default(0);
            $table->integer('positive_ratings')->default(0); // 4+ stars
            $table->integer('negative_ratings')->default(0); // 2- stars
            $table->decimal('user_satisfaction_score', 3, 2)->nullable();

            // Error Analytics
            $table->json('error_breakdown')->nullable(); // Count by error type
            $table->integer('rate_limit_errors')->default(0);
            $table->integer('authentication_errors')->default(0);
            $table->integer('timeout_errors')->default(0);
            $table->integer('content_filter_errors')->default(0);
            $table->integer('other_errors')->default(0);

            // Feature Usage
            $table->integer('streaming_requests')->default(0);
            $table->integer('function_call_requests')->default(0);
            $table->integer('vision_requests')->default(0);
            $table->integer('audio_requests')->default(0);
            $table->integer('regeneration_requests')->default(0);

            // Content Analytics
            $table->json('content_types')->nullable(); // Count by content type
            $table->json('languages')->nullable(); // Count by language
            $table->json('conversation_types')->nullable(); // Count by conversation type
            $table->decimal('avg_conversation_length', 8, 2)->nullable(); // Avg messages per conversation

            // Business Metrics
            $table->integer('unique_users')->default(0);
            $table->integer('new_users')->default(0);
            $table->integer('returning_users')->default(0);
            $table->decimal('user_retention_rate', 5, 2)->nullable();
            $table->decimal('daily_active_users', 10, 0)->nullable();
            $table->decimal('monthly_active_users', 10, 0)->nullable();

            // System Performance
            $table->decimal('system_uptime_percent', 5, 2)->nullable();
            $table->integer('peak_concurrent_requests')->nullable();
            $table->decimal('cache_hit_rate', 5, 2)->nullable();
            $table->decimal('queue_processing_time_ms', 10, 2)->nullable();

            // Metadata
            $table->json('metadata')->nullable(); // Additional custom metrics
            $table->timestamp('calculated_at')->nullable(); // When analytics were calculated
            $table->string('calculation_version')->nullable(); // Version of calculation logic

            $table->timestamps();

            // Indexes
            $table->index(['date', 'period_type']);
            $table->index(['ai_provider_id', 'date']);
            $table->index(['ai_provider_model_id', 'date']);
            $table->index(['user_id', 'user_type', 'date']);
            $table->index(['total_cost', 'date']);
            $table->index(['total_requests', 'date']);
            $table->index('calculated_at');

            // Unique constraints for aggregation periods
            $table->unique([
                'date',
                'hour',
                'period_type',
                'ai_provider_id',
                'ai_provider_model_id',
                'user_id',
                'user_type',
            ], 'unique_analytics_period');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_usage_analytics');
    }
};
