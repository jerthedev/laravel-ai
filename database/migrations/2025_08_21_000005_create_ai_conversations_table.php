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
        Schema::create('ai_conversations', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique(); // Public identifier for conversations
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('status', ['active', 'archived', 'deleted'])->default('active');

            // User and ownership
            $table->unsignedBigInteger('user_id')->nullable(); // Polymorphic user relationship
            $table->string('user_type')->nullable(); // For polymorphic relationship
            $table->string('session_id')->nullable(); // For anonymous users
            $table->json('participants')->nullable(); // Multiple participants in group conversations

            // AI Provider and Model
            $table->foreignId('ai_provider_id')->nullable()->constrained('ai_providers')->onDelete('set null');
            $table->foreignId('ai_provider_model_id')->nullable()->constrained('ai_provider_models')->onDelete('set null');
            $table->string('provider_name')->nullable(); // Cached for when provider is deleted
            $table->string('model_name')->nullable(); // Cached for when model is deleted

            // Conversation Configuration
            $table->json('system_prompt')->nullable(); // System/context messages
            $table->json('configuration')->nullable(); // Temperature, max_tokens, etc.
            $table->json('context_data')->nullable(); // Additional context for the conversation
            $table->integer('max_messages')->nullable(); // Limit conversation length
            $table->boolean('auto_title')->default(true); // Auto-generate titles from content

            // Cost and Usage Tracking
            $table->decimal('total_cost', 10, 6)->default(0);
            $table->integer('total_input_tokens')->default(0);
            $table->integer('total_output_tokens')->default(0);
            $table->integer('total_messages')->default(0);
            $table->integer('total_requests')->default(0);

            // Performance Metrics
            $table->integer('avg_response_time_ms')->nullable();
            $table->decimal('avg_quality_rating', 3, 2)->nullable(); // User ratings
            $table->integer('successful_requests')->default(0);
            $table->integer('failed_requests')->default(0);

            // Conversation Metadata
            $table->json('tags')->nullable(); // User-defined tags
            $table->json('metadata')->nullable(); // Additional custom data
            $table->string('language', 10)->default('en'); // Primary language
            $table->enum('conversation_type', ['chat', 'completion', 'analysis', 'creative'])->default('chat');

            // Timestamps and Activity
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['user_id', 'user_type', 'status']);
            $table->index('session_id');
            $table->index(['ai_provider_id', 'ai_provider_model_id']);
            $table->index('last_message_at');
            $table->index('last_activity_at');
            $table->index(['total_cost', 'total_messages']);
            $table->index('conversation_type');
            $table->index(['status', 'archived_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_conversations');
    }
};
