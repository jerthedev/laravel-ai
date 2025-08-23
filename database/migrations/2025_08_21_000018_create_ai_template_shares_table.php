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
        Schema::create('ai_template_shares', function (Blueprint $table) {
            $table->id();

            // Template being shared
            $table->foreignId('template_id')->constrained('ai_conversation_templates')->onDelete('cascade');

            // Who it's shared with (polymorphic - user, team, etc.)
            $table->unsignedBigInteger('shared_with_id');
            $table->string('shared_with_type'); // 'user', 'team', etc.

            // Who shared it
            $table->unsignedBigInteger('shared_by_id')->nullable();

            // Permissions granted
            $table->json('permissions'); // {view: true, use: true, edit: false, delete: false}

            // Sharing metadata
            $table->timestamp('shared_at');
            $table->timestamp('expires_at')->nullable(); // Optional expiration
            $table->json('sharing_metadata')->nullable(); // Additional sharing context

            $table->timestamps();

            // Indexes
            $table->index(['template_id', 'shared_with_id', 'shared_with_type'], 'template_sharing_index');
            $table->index(['shared_with_id', 'shared_with_type']);
            $table->index('shared_by_id');
            $table->index('shared_at');
            $table->index('expires_at');

            // Unique constraint to prevent duplicate shares
            $table->unique(['template_id', 'shared_with_id', 'shared_with_type'], 'unique_template_share');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_template_shares');
    }
};
