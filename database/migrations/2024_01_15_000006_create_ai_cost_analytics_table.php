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
        Schema::create('ai_cost_analytics', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();

            // Provider and model information
            $table->string('provider', 50)->index();
            $table->string('model', 100)->index();

            // Token usage
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedInteger('total_tokens')->default(0);

            // Cost breakdown
            $table->decimal('input_cost', 10, 6)->default(0);
            $table->decimal('output_cost', 10, 6)->default(0);
            $table->decimal('total_cost', 10, 6)->default(0);
            $table->string('currency', 3)->default('USD');

            // Cost efficiency metrics
            $table->decimal('cost_per_token', 10, 8)->default(0);

            $table->timestamps();

            // Indexes for cost analytics
            $table->index(['user_id', 'created_at']);
            $table->index(['provider', 'model', 'created_at']);
            $table->index(['total_cost', 'created_at']);
            $table->index(['cost_per_token', 'created_at']);

            // Composite indexes for cost analysis
            $table->index(['user_id', 'provider', 'created_at']);
            $table->index(['provider', 'total_cost', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_cost_analytics');
    }
};
