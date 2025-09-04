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
     * - provider (covered by ['provider', 'model', 'created_at'], ['user_id', 'provider', 'created_at'], and ['provider', 'total_cost', 'created_at'])
     * - model (covered by ['provider', 'model', 'created_at'])
     */
    public function up(): void
    {
        Schema::table('ai_cost_analytics', function (Blueprint $table) {
            // Drop redundant single-column indexes
            $table->dropIndex(['user_id']);
            $table->dropIndex(['provider']);
            $table->dropIndex(['model']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_cost_analytics', function (Blueprint $table) {
            // Restore single-column indexes
            $table->index('user_id');
            $table->index('provider');
            $table->index('model');
        });
    }
};
