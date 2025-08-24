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
        Schema::create('ai_report_exports', function (Blueprint $table) {
            $table->id();
            $table->string('export_id')->unique()->index();
            $table->unsignedBigInteger('user_id')->index();
            
            // Report configuration
            $table->string('report_type', 50)->index(); // comprehensive, cost_breakdown, usage_trends, budget_analysis
            $table->string('format', 10)->index(); // pdf, csv, json, xlsx
            $table->string('date_range', 20)->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            
            // Export details
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->unsignedBigInteger('file_size')->default(0);
            $table->string('mime_type', 100)->nullable();
            
            // Status tracking
            $table->string('status', 20)->default('pending')->index(); // pending, processing, completed, failed, expired
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            
            // Configuration and metadata
            $table->json('filters')->nullable();
            $table->json('options')->nullable();
            $table->json('metadata')->nullable();
            
            // Download tracking
            $table->unsignedInteger('download_count')->default(0);
            $table->timestamp('last_downloaded_at')->nullable();
            
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['user_id', 'status', 'created_at']);
            $table->index(['report_type', 'format', 'created_at']);
            $table->index(['status', 'expires_at']);
            $table->index(['created_at', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_report_exports');
    }
};
