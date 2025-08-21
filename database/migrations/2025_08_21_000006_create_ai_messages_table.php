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
        Schema::create('ai_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_conversation_id')->constrained('ai_conversations')->onDelete('cascade');
            $table->string('uuid')->unique(); // Public identifier for messages
            $table->integer('sequence_number'); // Order within conversation

            // Message Content
            $table->enum('role', ['system', 'user', 'assistant', 'function', 'tool'])->default('user');
            $table->longText('content');
            $table->json('content_metadata')->nullable(); // Content type, format, etc.
            $table->string('content_type')->default('text'); // 'text', 'image', 'audio', 'file'
            $table->json('attachments')->nullable(); // File attachments, images, etc.

            // AI Provider Context
            $table->foreignId('ai_provider_id')->nullable()->constrained('ai_providers')->onDelete('set null');
            $table->foreignId('ai_provider_model_id')->nullable()->constrained('ai_provider_models')->onDelete('set null');
            $table->string('provider_message_id')->nullable(); // Provider's message ID if available

            // Request/Response Details
            $table->json('request_parameters')->nullable(); // Temperature, max_tokens, etc.
            $table->json('response_metadata')->nullable(); // Provider response metadata
            $table->string('finish_reason')->nullable(); // 'stop', 'length', 'content_filter', etc.
            $table->boolean('is_streaming')->default(false);
            $table->integer('stream_chunks')->nullable(); // Number of chunks if streaming

            // Token Usage and Cost
            $table->integer('input_tokens')->nullable();
            $table->integer('output_tokens')->nullable();
            $table->integer('total_tokens')->nullable();
            $table->decimal('cost', 10, 6)->nullable();
            $table->string('cost_currency', 3)->default('USD');
            $table->json('cost_breakdown')->nullable(); // Detailed cost calculation

            // Performance and Quality
            $table->integer('response_time_ms')->nullable();
            $table->decimal('quality_rating', 3, 2)->nullable(); // User rating 0.00-5.00
            $table->text('quality_feedback')->nullable(); // User feedback
            $table->boolean('is_regenerated')->default(false); // Was this message regenerated?
            $table->integer('regeneration_count')->default(0);

            // Function/Tool Calling
            $table->json('function_calls')->nullable(); // Function calls made by AI
            $table->json('tool_calls')->nullable(); // Tool calls made by AI
            $table->json('function_results')->nullable(); // Results from function calls

            // Message Status and Processing
            $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'cancelled'])->default('completed');
            $table->text('error_message')->nullable();
            $table->json('error_details')->nullable();
            $table->integer('retry_count')->default(0);
            $table->timestamp('processed_at')->nullable();

            // User Interaction
            $table->boolean('is_edited')->default(false);
            $table->timestamp('edited_at')->nullable();
            $table->boolean('is_hidden')->default(false); // Hidden from conversation display
            $table->boolean('is_pinned')->default(false); // Pinned important messages
            $table->json('user_reactions')->nullable(); // Thumbs up/down, etc.

            // Metadata and Context
            $table->json('context_data')->nullable(); // Additional context for this message
            $table->json('metadata')->nullable(); // Custom metadata
            $table->string('language', 10)->nullable(); // Detected/specified language
            $table->json('content_analysis')->nullable(); // Sentiment, topics, etc.

            $table->timestamps();

            // Indexes
            $table->index(['ai_conversation_id', 'sequence_number']);
            $table->index(['role', 'status']);
            $table->index(['ai_provider_id', 'ai_provider_model_id']);
            $table->index('processed_at');
            $table->index(['cost', 'total_tokens']);
            $table->index('response_time_ms');
            $table->index(['is_edited', 'edited_at']);
            $table->index(['quality_rating', 'created_at']);

            // Unique constraint for sequence within conversation
            $table->unique(['ai_conversation_id', 'sequence_number'], 'unique_conversation_sequence');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_messages');
    }
};
