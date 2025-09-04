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
        Schema::create('ai_budget_alert_configs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('project_id')->nullable()->index();
            $table->string('organization_id')->nullable()->index();
            $table->string('budget_type', 50)->index(); // daily, monthly, per_request, project, organization

            // Alert configuration
            $table->boolean('enabled')->default(true);
            $table->decimal('min_threshold_percentage', 5, 2)->default(75.00);

            // Email settings
            $table->boolean('email_enabled')->default(true);
            $table->json('email_severities')->nullable(); // ['medium', 'high', 'critical']
            $table->json('additional_emails')->nullable(); // Additional email addresses

            // Slack settings
            $table->boolean('slack_enabled')->default(false);
            $table->json('slack_severities')->nullable(); // ['high', 'critical']
            $table->string('slack_webhook')->nullable();

            // SMS settings
            $table->boolean('sms_enabled')->default(false);
            $table->json('sms_severities')->nullable(); // ['critical']
            $table->string('sms_phone')->nullable();

            // Status
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            // Indexes for performance
            $table->index(['user_id', 'budget_type', 'is_active']);
            $table->index(['project_id', 'budget_type', 'is_active']);
            $table->index(['organization_id', 'budget_type', 'is_active']);

            // Unique constraints
            $table->unique(['user_id', 'budget_type'], 'unique_user_budget_type');
            $table->unique(['project_id', 'budget_type'], 'unique_project_budget_type');
            $table->unique(['organization_id', 'budget_type'], 'unique_org_budget_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_budget_alert_configs');
    }
};
