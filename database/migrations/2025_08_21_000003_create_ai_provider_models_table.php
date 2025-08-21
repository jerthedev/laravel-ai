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
        Schema::create('ai_provider_models', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_provider_id')->constrained('ai_providers')->onDelete('cascade');
            $table->string('model_id'); // Provider's model identifier (e.g., 'gpt-4', 'gemini-pro')
            $table->string('name'); // Human-readable name
            $table->string('version')->nullable(); // Model version if applicable
            $table->text('description')->nullable();
            $table->enum('status', ['active', 'deprecated', 'beta', 'experimental'])->default('active');

            // Model capabilities
            $table->boolean('supports_chat')->default(true);
            $table->boolean('supports_completion')->default(false);
            $table->boolean('supports_streaming')->default(false);
            $table->boolean('supports_function_calling')->default(false);
            $table->boolean('supports_vision')->default(false);
            $table->boolean('supports_audio')->default(false);
            $table->boolean('supports_embeddings')->default(false);
            $table->boolean('supports_fine_tuning')->default(false);

            // Model specifications
            $table->integer('max_tokens')->nullable(); // Maximum output tokens
            $table->integer('context_length')->nullable(); // Maximum context window
            $table->decimal('default_temperature', 3, 2)->nullable();
            $table->decimal('min_temperature', 3, 2)->nullable();
            $table->decimal('max_temperature', 3, 2)->nullable();
            $table->integer('max_top_p')->nullable();
            $table->json('supported_formats')->nullable(); // ['text', 'json', 'markdown']
            $table->json('supported_languages')->nullable(); // Language codes

            // Pricing information (base rates - specific costs in separate table)
            $table->decimal('input_token_cost', 10, 8)->nullable(); // Cost per 1K input tokens
            $table->decimal('output_token_cost', 10, 8)->nullable(); // Cost per 1K output tokens
            $table->decimal('training_cost', 10, 8)->nullable(); // Cost per 1K training tokens
            $table->string('pricing_currency', 3)->default('USD');
            $table->enum('pricing_model', ['per_token', 'per_request', 'subscription', 'free'])->default('per_token');

            // Performance metrics
            $table->integer('avg_response_time_ms')->nullable();
            $table->decimal('avg_quality_score', 3, 2)->nullable(); // 0.00 to 5.00
            $table->decimal('reliability_score', 3, 2)->nullable(); // 0.00 to 1.00
            $table->integer('total_requests')->default(0);
            $table->integer('successful_requests')->default(0);
            $table->integer('failed_requests')->default(0);

            // Sync and metadata
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamp('deprecated_at')->nullable();
            $table->timestamp('sunset_date')->nullable(); // When model will be discontinued
            $table->json('provider_metadata')->nullable(); // Raw data from provider API
            $table->json('custom_metadata')->nullable(); // Custom fields for internal use

            $table->timestamps();

            // Indexes
            $table->unique(['ai_provider_id', 'model_id'], 'unique_provider_model');
            $table->index(['status', 'supports_chat']);
            $table->index('last_synced_at');
            $table->index(['input_token_cost', 'output_token_cost']);
            $table->index('context_length');
            $table->index(['avg_response_time_ms', 'reliability_score']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_provider_models');
    }
};
