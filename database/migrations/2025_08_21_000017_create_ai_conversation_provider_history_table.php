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
        Schema::create('ai_conversation_provider_history', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // Conversation reference
            $table->foreignId('ai_conversation_id')
                ->constrained('ai_conversations')
                ->onDelete('cascade');

            // Provider information
            $table->foreignId('ai_provider_id')
                ->nullable()
                ->constrained('ai_providers')
                ->onDelete('set null');
            $table->foreignId('ai_provider_model_id')
                ->nullable()
                ->constrained('ai_provider_models')
                ->onDelete('set null');
            $table->string('provider_name');
            $table->string('model_name');

            // Switch details
            $table->enum('switch_type', ['initial', 'manual', 'fallback', 'automatic']);
            $table->string('switch_reason')->nullable();
            $table->text('switch_context')->nullable(); // JSON context about the switch

            // Previous provider (for switches)
            $table->string('previous_provider_name')->nullable();
            $table->string('previous_model_name')->nullable();

            // Usage tracking
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->integer('message_count')->default(0);
            $table->integer('total_input_tokens')->default(0);
            $table->integer('total_output_tokens')->default(0);
            $table->decimal('total_cost', 10, 6)->default(0);
            $table->integer('successful_requests')->default(0);
            $table->integer('failed_requests')->default(0);
            $table->integer('avg_response_time_ms')->nullable();

            // Performance metrics
            $table->decimal('success_rate', 5, 2)->nullable(); // Percentage
            $table->decimal('cost_per_message', 10, 6)->nullable();
            $table->decimal('tokens_per_message', 8, 2)->nullable();

            // Metadata
            $table->json('metadata')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['ai_conversation_id', 'started_at']);
            $table->index(['provider_name', 'started_at']);
            $table->index(['switch_type', 'started_at']);
            $table->index('started_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_conversation_provider_history');
    }
};
