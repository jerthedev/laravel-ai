<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Standardizes indexing patterns across all AI cost and budget tables.
     * Ensures consistent naming conventions and optimizes for common query patterns.
     */
    public function up(): void
    {
        // Optimize ai_usage_costs table further
        Schema::table('ai_cost_records', function (Blueprint $table) {
            // Add index for cost analysis queries
            $table->index(['total_cost', 'provider', 'created_at'], 'idx_usage_costs_cost_analysis');

            // Add index for user cost trends
            $table->index(['user_id', 'total_cost', 'created_at'], 'idx_usage_costs_user_trends');
        });

        // Optimize ai_cost_analytics table further
        Schema::table('ai_cost_analytics', function (Blueprint $table) {
            // Add index for efficiency analysis
            $table->index(['cost_per_token', 'provider', 'created_at'], 'idx_cost_analytics_efficiency');

            // Add index for model comparison
            $table->index(['model', 'total_cost', 'created_at'], 'idx_cost_analytics_model_comparison');
        });

        // Optimize ai_budget_alerts table further
        Schema::table('ai_budget_alerts', function (Blueprint $table) {
            // Add index for alert history analysis
            $table->index(['threshold_percentage', 'severity', 'sent_at'], 'idx_budget_alerts_history');

            // Add index for cost trend analysis
            $table->index(['current_spending', 'budget_limit', 'sent_at'], 'idx_budget_alerts_trends');
        });

        // Optimize ai_budget_alert_configs table
        Schema::table('ai_budget_alert_configs', function (Blueprint $table) {
            // Add index for active configurations
            $table->index(['enabled', 'is_active', 'updated_at'], 'idx_budget_configs_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_cost_records', function (Blueprint $table) {
            $table->dropIndex('idx_usage_costs_cost_analysis');
            $table->dropIndex('idx_usage_costs_user_trends');
        });

        Schema::table('ai_cost_analytics', function (Blueprint $table) {
            $table->dropIndex('idx_cost_analytics_efficiency');
            $table->dropIndex('idx_cost_analytics_model_comparison');
        });

        Schema::table('ai_budget_alerts', function (Blueprint $table) {
            $table->dropIndex('idx_budget_alerts_history');
            $table->dropIndex('idx_budget_alerts_trends');
        });

        Schema::table('ai_budget_alert_configs', function (Blueprint $table) {
            $table->dropIndex('idx_budget_configs_active');
        });
    }
};
