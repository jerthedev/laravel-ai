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
        Schema::create('ai_budgets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('type'); // 'daily', 'weekly', 'monthly', 'yearly'
            $table->decimal('limit_amount', 10, 4);
            $table->decimal('current_usage', 10, 4)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->decimal('warning_threshold', 5, 2)->default(80.0); // Percentage
            $table->decimal('critical_threshold', 5, 2)->default(90.0); // Percentage
            $table->timestamp('period_start');
            $table->timestamp('period_end');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'type', 'is_active']);
            $table->index(['period_start', 'period_end']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_budgets');
    }
};
