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
            
            // Date and time partitioning
            $table->date('date')->index();
            $table->unsignedTinyInteger('hour')->nullable()->index();
            $table->enum('period_type', ['hourly', 'daily', 'weekly', 'monthly'])->default('daily')->index();
            
            // Provider and model references
            $table->foreignId('ai_provider_id')->nullable()->constrained('ai_providers')->onDelete('cascade');
            $table->foreignId('ai_provider_model_id')->nullable()->constrained('ai_provider_models')->onDelete('cascade');
            
            // User information
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('user_type')->nullable();
            
            // Request metrics
            $table->unsignedInteger('total_requests')->default(0);
            $table->unsignedInteger('successful_requests')->default(0);
            $table->unsignedInteger('failed_requests')->default(0);
            
            // Conversation metrics
            $table->unsignedInteger('total_conversations')->default(0);
            $table->unsignedInteger('total_messages')->default(0);
            
            // Token usage metrics
            $table->unsignedBigInteger('total_input_tokens')->default(0);
            $table->unsignedBigInteger('total_output_tokens')->default(0);
            $table->unsignedBigInteger('total_tokens')->default(0);
            $table->decimal('avg_tokens_per_request', 10, 2)->nullable();
            $table->unsignedInteger('max_tokens_in_request')->nullable();
            $table->unsignedInteger('min_tokens_in_request')->nullable();
            
            // Cost metrics
            $table->decimal('total_cost', 10, 4)->default(0);
            $table->decimal('avg_cost_per_request', 10, 4)->nullable();
            $table->decimal('avg_cost_per_token', 10, 6)->nullable();
            $table->string('currency', 3)->default('USD');
            $table->decimal('input_token_cost', 10, 4)->default(0);
            $table->decimal('output_token_cost', 10, 4)->default(0);
            
            // Performance metrics
            $table->unsignedInteger('avg_response_time_ms')->nullable();
            $table->unsignedInteger('min_response_time_ms')->nullable();
            $table->unsignedInteger('max_response_time_ms')->nullable();
            $table->unsignedInteger('p95_response_time_ms')->nullable();
            $table->unsignedInteger('p99_response_time_ms')->nullable();
            
            // Quality metrics
            $table->decimal('avg_quality_rating', 3, 2)->nullable();
            $table->unsignedInteger('total_ratings')->default(0);
            $table->unsignedInteger('positive_ratings')->default(0);
            $table->unsignedInteger('negative_ratings')->default(0);
            $table->decimal('user_satisfaction_score', 3, 2)->nullable();
            
            // Error tracking
            $table->json('error_breakdown')->nullable();
            $table->unsignedInteger('rate_limit_errors')->default(0);
            $table->unsignedInteger('authentication_errors')->default(0);
            $table->unsignedInteger('timeout_errors')->default(0);
            $table->unsignedInteger('content_filter_errors')->default(0);
            $table->unsignedInteger('other_errors')->default(0);
            
            // Feature usage
            $table->unsignedInteger('streaming_requests')->default(0);
            $table->unsignedInteger('function_call_requests')->default(0);
            $table->unsignedInteger('vision_requests')->default(0);
            $table->unsignedInteger('audio_requests')->default(0);
            $table->unsignedInteger('regeneration_requests')->default(0);
            
            // Content analysis
            $table->json('content_types')->nullable();
            $table->json('languages')->nullable();
            $table->json('conversation_types')->nullable();
            $table->decimal('avg_conversation_length', 8, 2)->nullable();
            
            // User behavior metrics
            $table->unsignedInteger('unique_users')->default(0);
            $table->unsignedInteger('new_users')->default(0);
            $table->unsignedInteger('returning_users')->default(0);
            $table->decimal('user_retention_rate', 5, 2)->nullable();
            $table->decimal('daily_active_users', 10, 2)->nullable();
            $table->decimal('monthly_active_users', 10, 2)->nullable();
            
            // System metrics
            $table->decimal('system_uptime_percent', 5, 2)->nullable();
            $table->unsignedInteger('peak_concurrent_requests')->nullable();
            $table->decimal('cache_hit_rate', 5, 2)->nullable();
            $table->decimal('queue_processing_time_ms', 10, 2)->nullable();
            
            // Metadata and versioning
            $table->json('metadata')->nullable();
            $table->timestamp('calculated_at')->nullable();
            $table->string('calculation_version')->nullable();
            
            $table->timestamps();
            
            // Indexes for efficient querying
            $table->index(['date', 'period_type']);
            $table->index(['ai_provider_id', 'date']);
            $table->index(['ai_provider_model_id', 'date']);
            $table->index(['user_id', 'date']);
            $table->index(['date', 'hour']);
            $table->index(['period_type', 'date']);
            
            // Composite indexes for analytics
            $table->index(['ai_provider_id', 'ai_provider_model_id', 'date']);
            $table->index(['user_id', 'ai_provider_id', 'date']);
            $table->index(['date', 'period_type', 'ai_provider_id']);
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
