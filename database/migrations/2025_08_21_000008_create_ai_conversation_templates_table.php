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
        Schema::create('ai_conversation_templates', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique(); // Public identifier for templates
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('category')->default('general'); // general, business, creative, etc.

            // Template Structure
            $table->json('template_data'); // System prompts, initial messages, etc.
            $table->json('parameters')->nullable(); // Template parameters with types and validation
            $table->json('default_configuration')->nullable(); // Default AI settings (temperature, etc.)

            // AI Provider and Model
            $table->foreignId('ai_provider_id')->nullable()->constrained('ai_providers')->onDelete('set null');
            $table->foreignId('ai_provider_model_id')->nullable()->constrained('ai_provider_models')->onDelete('set null');
            $table->string('provider_name')->nullable(); // Cached for when provider is deleted
            $table->string('model_name')->nullable(); // Cached for when model is deleted

            // Visibility and Status
            $table->boolean('is_public')->default(false); // Public templates can be used by anyone
            $table->boolean('is_active')->default(true);

            // Ownership
            $table->unsignedBigInteger('created_by_id')->nullable(); // Polymorphic user relationship
            $table->string('created_by_type')->nullable(); // For polymorphic relationship

            // Usage and Rating
            $table->integer('usage_count')->default(0); // How many times template has been used
            $table->decimal('avg_rating', 3, 2)->nullable(); // Average user rating (0.00-5.00)

            // Metadata
            $table->json('tags')->nullable(); // User-defined tags for categorization
            $table->json('metadata')->nullable(); // Additional custom data
            $table->string('language', 10)->default('en'); // Primary language

            // Publishing
            $table->timestamp('published_at')->nullable(); // When template was made public

            // Timestamps
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['created_by_id', 'created_by_type']);
            $table->index(['is_public', 'is_active']);
            $table->index(['category', 'is_active']);
            $table->index(['usage_count', 'avg_rating']);
            $table->index('published_at');
            $table->index('language');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_conversation_templates');
    }
};
