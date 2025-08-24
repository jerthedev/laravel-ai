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
        Schema::create('ai_optimization_tracking', function (Blueprint $table) {
            $table->id();
            
            // Optimization identification
            $table->string('optimization_id', 100)->unique()->index();
            $table->string('optimization_type', 50)->index();
            $table->string('component', 50)->index();
            $table->string('component_name', 100)->index();
            
            // Implementation details
            $table->enum('status', ['planned', 'in_progress', 'completed', 'failed', 'cancelled'])->default('planned')->index();
            $table->text('description')->nullable();
            $table->json('implementation_details')->nullable();
            
            // Performance metrics
            $table->json('baseline_metrics')->nullable();
            $table->json('target_metrics')->nullable();
            $table->json('actual_metrics')->nullable();
            $table->json('implementation_metrics')->nullable();
            
            // Impact tracking
            $table->decimal('expected_improvement_percentage', 5, 2)->nullable();
            $table->decimal('actual_improvement_percentage', 5, 2)->nullable();
            $table->integer('implementation_hours_estimated')->nullable();
            $table->integer('implementation_hours_actual')->nullable();
            
            // Timeline tracking
            $table->timestamp('planned_start_date')->nullable();
            $table->timestamp('actual_start_date')->nullable();
            $table->timestamp('planned_completion_date')->nullable();
            $table->timestamp('actual_completion_date')->nullable();
            
            // Assignment and tracking
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->text('notes')->nullable();
            
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['status', 'created_at']);
            $table->index(['optimization_type', 'status']);
            $table->index(['component', 'component_name', 'status']);
            $table->index(['assigned_to', 'status']);
            $table->index(['actual_completion_date']);
            
            // Foreign key constraints (optional - depends on user table existence)
            // $table->foreign('assigned_to')->references('id')->on('users')->onDelete('set null');
            // $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_optimization_tracking');
    }
};
