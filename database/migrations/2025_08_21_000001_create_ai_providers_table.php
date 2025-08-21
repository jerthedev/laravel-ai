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
        Schema::create('ai_providers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->string('driver');
            $table->json('config')->nullable();
            $table->enum('status', ['active', 'inactive', 'maintenance'])->default('active');
            $table->string('description')->nullable();
            $table->string('website_url')->nullable();
            $table->string('documentation_url')->nullable();
            $table->boolean('supports_streaming')->default(false);
            $table->boolean('supports_function_calling')->default(false);
            $table->boolean('supports_vision')->default(false);
            $table->integer('max_tokens')->nullable();
            $table->integer('max_context_length')->nullable();
            $table->decimal('default_temperature', 3, 2)->nullable();
            $table->json('supported_formats')->nullable(); // ['text', 'json', 'markdown']
            $table->json('rate_limits')->nullable(); // Provider-specific rate limits
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamp('last_health_check_at')->nullable();
            $table->enum('health_status', ['healthy', 'degraded', 'unhealthy'])->default('healthy');
            $table->text('health_message')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['status', 'driver']);
            $table->index('last_synced_at');
            $table->index('health_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_providers');
    }
};
