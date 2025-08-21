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
        Schema::create('ai_provider_model_costs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_provider_model_id')->constrained('ai_provider_models')->onDelete('cascade');
            $table->string('cost_type'); // 'input', 'output', 'training', 'fine_tuning', 'embedding'
            $table->decimal('cost_per_unit', 12, 10); // Cost per unit (usually per 1K tokens)
            $table->string('unit_type')->default('1k_tokens'); // '1k_tokens', 'request', 'minute', 'hour'
            $table->string('currency', 3)->default('USD');
            $table->string('region')->nullable(); // Some providers have regional pricing
            $table->string('tier')->nullable(); // 'standard', 'premium', 'enterprise'

            // Pricing tiers and volume discounts
            $table->integer('min_volume')->nullable(); // Minimum volume for this pricing tier
            $table->integer('max_volume')->nullable(); // Maximum volume for this pricing tier
            $table->decimal('volume_discount_percent', 5, 2)->nullable(); // Percentage discount

            // Time-based pricing
            $table->timestamp('effective_from');
            $table->timestamp('effective_until')->nullable();
            $table->boolean('is_current')->default(true);

            // Billing and usage context
            $table->enum('billing_model', ['pay_per_use', 'subscription', 'credits', 'free_tier'])->default('pay_per_use');
            $table->json('conditions')->nullable(); // Special conditions or requirements
            $table->text('notes')->nullable(); // Additional pricing notes

            // Metadata
            $table->json('provider_pricing_data')->nullable(); // Raw pricing data from provider
            $table->timestamp('last_updated_at')->nullable();
            $table->string('source')->nullable(); // 'api', 'manual', 'scraping'

            $table->timestamps();

            // Indexes
            $table->index(['ai_provider_model_id', 'cost_type', 'is_current']);
            $table->index(['effective_from', 'effective_until']);
            $table->index(['currency', 'region']);
            $table->index(['min_volume', 'max_volume']);
            $table->index('last_updated_at');

            // Unique constraint for current pricing
            $table->unique([
                'ai_provider_model_id',
                'cost_type',
                'currency',
                'region',
                'tier',
                'min_volume',
            ], 'unique_current_pricing');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_provider_model_costs');
    }
};
