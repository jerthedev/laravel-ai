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
        Schema::create('ai_conversation_tag_pivot', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_conversation_id')->constrained('ai_conversations')->onDelete('cascade');
            $table->foreignId('ai_conversation_tag_id')->constrained('ai_conversation_tags')->onDelete('cascade');

            // Who added this tag
            $table->unsignedBigInteger('tagged_by_id')->nullable();
            $table->string('tagged_by_type')->nullable();

            $table->timestamp('tagged_at')->nullable();

            // Indexes
            $table->index(['ai_conversation_id', 'ai_conversation_tag_id'], 'conversation_tag_index');
            $table->index(['tagged_by_id', 'tagged_by_type']);

            // Unique constraint
            $table->unique(['ai_conversation_id', 'ai_conversation_tag_id'], 'unique_conversation_tag');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_conversation_tag_pivot');
    }
};
