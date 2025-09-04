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
        Schema::create('ai_cost_validations', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 50)->index();

            // Validation statistics
            $table->unsignedInteger('total_records')->default(0);
            $table->unsignedInteger('validated_records')->default(0);
            $table->unsignedInteger('accurate_records')->default(0);
            $table->unsignedInteger('discrepant_records')->default(0);
            $table->unsignedInteger('validation_errors')->default(0);

            // Accuracy metrics
            $table->decimal('overall_accuracy', 5, 2)->default(0); // Percentage
            $table->decimal('total_calculated_cost', 12, 6)->default(0);
            $table->decimal('total_provider_cost', 12, 6)->default(0);
            $table->decimal('cost_difference', 12, 6)->default(0);
            $table->decimal('cost_difference_percent', 8, 4)->default(0);

            // Detailed results
            $table->json('discrepancies')->nullable();
            $table->json('validation_summary')->nullable();

            $table->timestamps();

            // Indexes for performance
            $table->index(['provider', 'created_at']);
            $table->index(['overall_accuracy', 'created_at']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_cost_validations');
    }
};
