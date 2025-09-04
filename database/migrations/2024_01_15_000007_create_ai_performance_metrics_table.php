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
        Schema::create('ai_performance_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('operation', 100)->index();
            $table->decimal('duration_ms', 8, 2);
            $table->decimal('target_ms', 8, 2);
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('provider', 50)->nullable()->index();

            $table->timestamps();

            // Indexes for performance analysis
            $table->index(['operation', 'created_at']);
            $table->index(['duration_ms', 'created_at']);
            $table->index(['provider', 'duration_ms', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_performance_metrics');
    }
};
