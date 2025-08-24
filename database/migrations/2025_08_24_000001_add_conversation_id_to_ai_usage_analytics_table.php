<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ai_usage_analytics', function (Blueprint $table) {
            // Add conversation_id column for linking analytics to conversations
            $table->string('conversation_id')->nullable()->after('id')->index();

            // Add additional columns that tests are expecting
            $table->unsignedInteger('input_tokens')->nullable()->after('total_input_tokens');
            $table->unsignedInteger('output_tokens')->nullable()->after('total_output_tokens');
            $table->unsignedInteger('processing_time_ms')->nullable()->after('avg_response_time_ms');
            $table->unsignedInteger('response_time_ms')->nullable()->after('processing_time_ms');
            $table->boolean('success')->default(true)->after('response_time_ms');
            $table->unsignedInteger('content_length')->nullable()->after('success');
            $table->unsignedInteger('response_length')->nullable()->after('content_length');
            $table->string('provider')->nullable()->after('response_length');
            $table->string('model')->nullable()->after('provider');

            // Add indexes for efficient querying
            $table->index(['conversation_id', 'created_at']);
            $table->index(['provider', 'model', 'created_at']);
            $table->index(['success', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // SQLite doesn't support dropping columns with indexes properly
        // For testing purposes, we'll skip the rollback to avoid issues
        if (DB::connection()->getDriverName() === 'sqlite') {
            // In SQLite, we can't easily drop columns with indexes
            // For testing, we'll just leave the columns (they don't hurt anything)
            return;
        }

        Schema::table('ai_usage_analytics', function (Blueprint $table) {
            $table->dropIndex(['conversation_id', 'created_at']);
            $table->dropIndex(['provider', 'model', 'created_at']);
            $table->dropIndex(['success', 'created_at']);

            $table->dropColumn([
                'conversation_id',
                'input_tokens',
                'output_tokens',
                'processing_time_ms',
                'response_time_ms',
                'success',
                'content_length',
                'response_length',
                'provider',
                'model',
            ]);
        });
    }
};
