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
        Schema::create('ai_conversation_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('color', 7)->nullable(); // Hex color code
            $table->text('description')->nullable();

            // Ownership
            $table->unsignedBigInteger('created_by_id')->nullable(); // Polymorphic user relationship
            $table->string('created_by_type')->nullable(); // For polymorphic relationship

            // Visibility
            $table->boolean('is_public')->default(false); // Public tags can be used by anyone
            $table->boolean('is_system')->default(false); // System-defined tags

            // Usage Statistics
            $table->integer('usage_count')->default(0);

            // Metadata
            $table->json('metadata')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['created_by_id', 'created_by_type']);
            $table->index(['is_public', 'is_system']);
            $table->index('usage_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_conversation_tags');
    }
};
