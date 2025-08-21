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
        Schema::create('ai_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_provider_id')->constrained('ai_providers')->onDelete('cascade');
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('encrypted_credentials'); // Encrypted JSON with API keys, tokens, etc.
            $table->json('configuration')->nullable(); // Account-specific configuration overrides
            $table->enum('status', ['active', 'inactive', 'suspended', 'expired'])->default('active');
            $table->boolean('is_default')->default(false);
            $table->integer('priority')->default(0); // For account selection priority

            // Usage limits and quotas
            $table->decimal('monthly_budget', 10, 4)->nullable();
            $table->decimal('daily_budget', 10, 4)->nullable();
            $table->integer('monthly_request_limit')->nullable();
            $table->integer('daily_request_limit')->nullable();
            $table->integer('hourly_request_limit')->nullable();

            // Current usage tracking
            $table->decimal('current_month_cost', 10, 4)->default(0);
            $table->decimal('current_day_cost', 10, 4)->default(0);
            $table->integer('current_month_requests')->default(0);
            $table->integer('current_day_requests')->default(0);
            $table->integer('current_hour_requests')->default(0);

            // Account metadata
            $table->string('organization_id')->nullable(); // For providers that support organizations
            $table->string('project_id')->nullable(); // For providers that support projects
            $table->json('metadata')->nullable(); // Additional account-specific data

            // Monitoring and health
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('credentials_expires_at')->nullable();
            $table->timestamp('last_validated_at')->nullable();
            $table->enum('validation_status', ['valid', 'invalid', 'expired', 'pending'])->default('pending');
            $table->text('validation_message')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['ai_provider_id', 'status']);
            $table->index(['is_default', 'priority']);
            $table->index('last_used_at');
            $table->index('validation_status');
            $table->index(['current_month_cost', 'monthly_budget']);

            // Unique constraint for default accounts per provider
            $table->unique(['ai_provider_id', 'is_default'], 'unique_default_per_provider');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_accounts');
    }
};
