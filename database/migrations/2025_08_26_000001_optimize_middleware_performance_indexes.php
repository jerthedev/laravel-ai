<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: Optimize Middleware Performance Indexes
 *
 * Adds performance-optimized database indexes for middleware operations,
 * specifically targeting budget checking queries to achieve <10ms execution time.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Optimize ai_cost_records table for budget middleware queries
        Schema::table('ai_cost_records', function (Blueprint $table) {
            // Add indexes for daily budget checking (most common query)
            $table->index(['user_id', 'created_at', 'total_cost'], 'idx_user_daily_cost_lookup');

            // Add index for monthly budget checking
            $table->index(['user_id', 'created_at'], 'idx_user_monthly_lookup');

            // Add project-based budget tracking indexes (for metadata queries) - Skip for SQLite
            if (Schema::getConnection()->getDriverName() !== 'sqlite') {
                $table->rawIndex(
                    "user_id, JSON_EXTRACT(metadata, '$.context.project_id'), total_cost, created_at",
                    'idx_user_project_cost'
                );

                // Add organization-based budget tracking indexes
                $table->rawIndex(
                    "user_id, JSON_EXTRACT(metadata, '$.context.organization_id'), total_cost, created_at",
                    'idx_user_org_cost'
                );
            }

            // Optimize for real-time cost aggregation queries
            $table->index(['created_at', 'user_id', 'total_cost'], 'idx_realtime_cost_aggregation');

            // Add composite index for provider-specific budget checking
            $table->index(['user_id', 'provider', 'model', 'created_at'], 'idx_user_provider_model_time');
        });

        // Optimize ai_user_budgets table for faster budget limit lookups (only if table exists)
        if (Schema::hasTable('ai_user_budgets')) {
            Schema::table('ai_user_budgets', function (Blueprint $table) {
                // Add covering index for budget limit queries (includes commonly selected columns)
                $table->index(['user_id', 'type', 'is_active', 'limit_amount'], 'idx_budget_limit_lookup');

                // Add project and organization budget indexes
                $table->unsignedBigInteger('project_id')->nullable()->after('user_id');
                $table->unsignedBigInteger('organization_id')->nullable()->after('project_id');

                $table->index(['project_id', 'type', 'is_active'], 'idx_project_budget_lookup');
                $table->index(['organization_id', 'type', 'is_active'], 'idx_org_budget_lookup');

                // Add index for period-based queries
                $table->index(['period_start', 'period_end', 'is_active'], 'idx_budget_period_active');
            });
        }

        // Create middleware performance metrics table if it doesn't exist
        if (! Schema::hasTable('ai_middleware_performance_metrics')) {
            Schema::create('ai_middleware_performance_metrics', function (Blueprint $table) {
                $table->id();
                $table->string('middleware_class', 200)->index();
                $table->decimal('execution_time_ms', 8, 3);
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('provider', 50)->nullable();
                $table->string('outcome', 20)->default('success'); // success, error, timeout
                $table->unsignedInteger('memory_usage_bytes')->nullable();
                $table->json('context_data')->nullable();
                $table->timestamp('measured_at')->useCurrent()->index();

                // Performance monitoring indexes
                $table->index(['middleware_class', 'measured_at'], 'idx_middleware_time_series');
                $table->index(['execution_time_ms', 'middleware_class'], 'idx_slow_middleware_lookup');
                $table->index(['user_id', 'measured_at', 'execution_time_ms'], 'idx_user_performance_tracking');
                $table->index(['outcome', 'measured_at'], 'idx_outcome_time_lookup');
            });
        }

        // Create budget checking cache table for extreme performance
        Schema::create('ai_budget_cache', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('budget_type', 20); // daily, monthly, project, organization
            $table->string('scope_id', 100)->nullable(); // project_id or organization_id
            $table->decimal('current_spending', 10, 6);
            $table->decimal('budget_limit', 10, 4);
            $table->decimal('percentage_used', 5, 2);
            $table->timestamp('last_updated')->useCurrent();
            $table->timestamp('expires_at')->index();

            // High-performance cache lookup indexes
            $table->unique(['user_id', 'budget_type', 'scope_id'], 'idx_budget_cache_unique');
            $table->index(['expires_at', 'user_id'], 'idx_cache_expiry_lookup');
            $table->index(['percentage_used', 'budget_type'], 'idx_threshold_monitoring');
        });

        // Add specialized indexes for performance analytics
        if (Schema::hasTable('ai_performance_metrics')) {
            Schema::table('ai_performance_metrics', function (Blueprint $table) {
                // Add middleware-specific performance indexes using existing columns
                if (! Schema::hasIndex('ai_performance_metrics', 'idx_operation_time_lookup')) {
                    $table->index(['operation', 'created_at'], 'idx_operation_time_lookup');
                }

                if (! Schema::hasIndex('ai_performance_metrics', 'idx_duration_threshold_analysis')) {
                    $table->index(['duration_ms', 'target_ms', 'created_at'], 'idx_duration_threshold_analysis');
                }

                if (! Schema::hasIndex('ai_performance_metrics', 'idx_performance_analytics')) {
                    $table->index(['created_at', 'duration_ms', 'operation'], 'idx_performance_analytics');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop ai_budget_cache table
        Schema::dropIfExists('ai_budget_cache');

        // Drop ai_middleware_performance_metrics table
        Schema::dropIfExists('ai_middleware_performance_metrics');

        // Remove performance indexes from ai_cost_records
        Schema::table('ai_cost_records', function (Blueprint $table) {
            $table->dropIndex('idx_user_daily_cost_lookup');
            $table->dropIndex('idx_user_monthly_lookup');

            // Only drop JSON-based indexes if not SQLite
            if (Schema::getConnection()->getDriverName() !== 'sqlite') {
                $table->dropIndex('idx_user_project_cost');
                $table->dropIndex('idx_user_org_cost');
            }

            $table->dropIndex('idx_realtime_cost_aggregation');
            $table->dropIndex('idx_user_provider_model_time');
        });

        // Remove performance indexes from ai_user_budgets (only if table exists)
        if (Schema::hasTable('ai_user_budgets')) {
            Schema::table('ai_user_budgets', function (Blueprint $table) {
                $table->dropIndex('idx_budget_limit_lookup');
                $table->dropIndex('idx_project_budget_lookup');
                $table->dropIndex('idx_org_budget_lookup');
                $table->dropIndex('idx_budget_period_active');

                $table->dropColumn(['project_id', 'organization_id']);
            });
        }

        // Remove performance indexes from ai_performance_metrics if they exist
        if (Schema::hasTable('ai_performance_metrics')) {
            Schema::table('ai_performance_metrics', function (Blueprint $table) {
                if (Schema::hasIndex('ai_performance_metrics', 'idx_operation_time_lookup')) {
                    $table->dropIndex('idx_operation_time_lookup');
                }
                if (Schema::hasIndex('ai_performance_metrics', 'idx_duration_threshold_analysis')) {
                    $table->dropIndex('idx_duration_threshold_analysis');
                }
                if (Schema::hasIndex('ai_performance_metrics', 'idx_performance_analytics')) {
                    $table->dropIndex('idx_performance_analytics');
                }
            });
        }
    }
};
