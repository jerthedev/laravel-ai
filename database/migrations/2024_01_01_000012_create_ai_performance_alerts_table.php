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
        Schema::create('ai_performance_alerts', function (Blueprint $table) {
            $table->id();

            // Alert identification
            $table->string('component', 50)->index();
            $table->string('component_name', 100)->index();
            $table->enum('severity', ['low', 'medium', 'high', 'critical'])->index();

            // Alert content
            $table->string('message', 500);
            $table->text('detailed_message')->nullable();

            // Performance metrics
            $table->decimal('duration_ms', 10, 2);
            $table->decimal('threshold_ms', 10, 2);
            $table->decimal('threshold_exceeded_percentage', 5, 1);

            // Context and recommendations
            $table->json('context_data')->nullable();
            $table->json('recommended_actions')->nullable();

            // Alert management
            $table->enum('status', ['active', 'acknowledged', 'resolved'])->default('active')->index();
            $table->json('channels_sent')->nullable();
            $table->integer('escalation_level')->default(1);
            $table->integer('occurrence_count')->default(1);

            // Acknowledgment and resolution
            $table->timestamp('acknowledged_at')->nullable();
            $table->unsignedBigInteger('acknowledged_by')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->unsignedBigInteger('resolved_by')->nullable();
            $table->text('resolution_notes')->nullable();

            $table->timestamps();

            // Indexes for performance
            $table->index(['status', 'created_at']);
            $table->index(['severity', 'created_at']);
            $table->index(['component', 'component_name', 'created_at']);
            $table->index(['acknowledged_at']);
            $table->index(['resolved_at']);

            // Foreign key constraints (optional - depends on user table existence)
            // $table->foreign('acknowledged_by')->references('id')->on('users')->onDelete('set null');
            // $table->foreign('resolved_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_performance_alerts');
    }
};
