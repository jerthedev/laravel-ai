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
     * - user_id (covered by ['user_id', 'created_at'] and ['user_id', 'provider', 'created_at'])
     * - conversation_id (covered by ['conversation_id', 'created_at'])
     * - message_id (kept as it's not covered by any composite index)
     * - provider (covered by ['provider', 'model', 'created_at'] and ['user_id', 'provider', 'created_at'])
     * - model (covered by ['provider', 'model', 'created_at'])
     */
    public function up(): void
    {
        Schema::table('ai_cost_records', function (Blueprint $table) {
            // Drop redundant single-column indexes using original index names
            // (indexes retain original names after table rename)
            $table->dropIndex('ai_usage_costs_user_id_index');
            $table->dropIndex('ai_usage_costs_conversation_id_index');
            $table->dropIndex('ai_usage_costs_provider_index');
            $table->dropIndex('ai_usage_costs_model_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_cost_records', function (Blueprint $table) {
            // Restore single-column indexes (these will be created with new table name)
            $table->index('user_id');
            $table->index('conversation_id');
            $table->index('provider');
            $table->index('model');
        });
    }
};
