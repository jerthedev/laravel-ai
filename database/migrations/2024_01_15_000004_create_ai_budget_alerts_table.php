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
        Schema::create('ai_budget_alerts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('budget_type', 50)->index();

            // Budget threshold information
            $table->decimal('threshold_percentage', 5, 2);
            $table->decimal('current_spending', 12, 6);
            $table->decimal('budget_limit', 12, 6);
            $table->decimal('additional_cost', 12, 6);

            // Alert details
            $table->string('severity', 20)->index(); // low, medium, high, critical
            $table->json('channels'); // ['email', 'slack', 'sms', 'database']

            // Context
            $table->string('project_id')->nullable()->index();
            $table->string('organization_id')->nullable()->index();
            $table->json('metadata')->nullable();

            // Timestamps
            $table->timestamp('sent_at');
            $table->timestamps();

            // Indexes for performance and analytics
            $table->index(['user_id', 'budget_type', 'sent_at']);
            $table->index(['severity', 'sent_at']);
            $table->index(['project_id', 'sent_at']);
            $table->index(['organization_id', 'sent_at']);
            $table->index('sent_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_budget_alerts');
    }
};
