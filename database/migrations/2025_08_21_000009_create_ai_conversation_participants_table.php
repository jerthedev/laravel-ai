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
        Schema::create('ai_conversation_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_conversation_id')->constrained('ai_conversations')->onDelete('cascade');

            // Participant (polymorphic relationship)
            $table->unsignedBigInteger('participant_id');
            $table->string('participant_type'); // User, Team, Organization, etc.

            // Participation Details
            $table->enum('role', ['owner', 'participant', 'observer', 'admin'])->default('participant');
            $table->enum('status', ['active', 'inactive', 'removed'])->default('active');

            // Permissions
            $table->json('permissions')->nullable(); // Custom permissions for this participant
            $table->boolean('can_read')->default(true);
            $table->boolean('can_write')->default(true);
            $table->boolean('can_manage')->default(false); // Can add/remove participants
            $table->boolean('can_delete')->default(false); // Can delete conversation

            // Activity Tracking
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('last_read_at')->nullable(); // For read receipts
            $table->timestamp('last_activity_at')->nullable();
            $table->integer('message_count')->default(0); // Messages sent by this participant

            // Notification Preferences
            $table->json('notification_settings')->nullable();
            $table->boolean('notifications_enabled')->default(true);

            // Metadata
            $table->json('metadata')->nullable(); // Additional custom data

            $table->timestamps();

            // Indexes
            $table->index(['participant_id', 'participant_type']);
            $table->index(['ai_conversation_id', 'status']);
            $table->index(['role', 'status']);
            $table->index('last_activity_at');

            // Unique constraint to prevent duplicate participants
            $table->unique(['ai_conversation_id', 'participant_id', 'participant_type'], 'unique_conversation_participant');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_conversation_participants');
    }
};
