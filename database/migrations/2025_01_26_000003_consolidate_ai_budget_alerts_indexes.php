<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Removes redundant single-column indexes that are covered by existing composite indexes:
     * - user_id (covered by ['user_id', 'budget_type', 'sent_at'])
     * - budget_type (covered by ['user_id', 'budget_type', 'sent_at'])
     * - severity (covered by ['severity', 'sent_at'])
     * - project_id (covered by ['project_id', 'sent_at'])
     * - organization_id (covered by ['organization_id', 'sent_at'])
     * - sent_at (redundant single column index)
     */
    public function up(): void
    {
        Schema::table('ai_budget_alerts', function (Blueprint $table) {
            // Drop redundant single-column indexes
            $table->dropIndex(['user_id']);
            $table->dropIndex(['budget_type']);
            $table->dropIndex(['severity']);
            $table->dropIndex(['project_id']);
            $table->dropIndex(['organization_id']);
            $table->dropIndex(['sent_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_budget_alerts', function (Blueprint $table) {
            // Restore single-column indexes
            $table->index('user_id');
            $table->index('budget_type');
            $table->index('severity');
            $table->index('project_id');
            $table->index('organization_id');
            $table->index('sent_at');
        });
    }
};