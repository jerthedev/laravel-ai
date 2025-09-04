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
        Schema::create('ai_usage_costs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('conversation_id')->nullable()->index();
            $table->unsignedBigInteger('message_id')->nullable()->index();
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

            // Pricing metadata
            $table->string('pricing_source', 50)->default('api'); // api, database, fallback
            $table->unsignedInteger('processing_time_ms')->default(0);

            // Additional metadata
            $table->json('metadata')->nullable();

            $table->timestamps();

            // Indexes for performance
            $table->index(['user_id', 'created_at']);
            $table->index(['provider', 'model', 'created_at']);
            $table->index(['created_at', 'total_cost']);

            // Composite indexes for common queries
            $table->index(['user_id', 'provider', 'created_at']);
            $table->index(['conversation_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_usage_costs');
    }
};
