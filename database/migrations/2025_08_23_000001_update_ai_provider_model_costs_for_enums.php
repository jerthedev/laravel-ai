<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ai_provider_model_costs', function (Blueprint $table) {
            // Update unit_type to support all PricingUnit enum values
            $table->string('unit_type')->change();

            // Update billing_model to support all BillingModel enum values
            $table->dropColumn('billing_model');
        });

        Schema::table('ai_provider_model_costs', function (Blueprint $table) {
            // Add the updated billing_model column with all enum values
            $table->enum('billing_model', [
                'pay_per_use',
                'tiered',
                'subscription',
                'credits',
                'free_tier',
                'enterprise',
            ])->default('pay_per_use')->after('is_current');
        });

        // Add comment to document the supported unit_type values (SQLite compatible)
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE ai_provider_model_costs MODIFY COLUMN unit_type VARCHAR(255) COMMENT 'Supported values: per_token, 1k_tokens, 1m_tokens, per_character, 1k_characters, per_second, per_minute, per_hour, per_request, per_image, per_audio_file, per_mb, per_gb'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_provider_model_costs', function (Blueprint $table) {
            // Revert billing_model to original enum values
            $table->dropColumn('billing_model');
        });

        Schema::table('ai_provider_model_costs', function (Blueprint $table) {
            $table->enum('billing_model', ['pay_per_use', 'subscription', 'credits', 'free_tier'])
                ->default('pay_per_use')
                ->after('is_current');
        });

        // Remove comment from unit_type (SQLite compatible)
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE ai_provider_model_costs MODIFY COLUMN unit_type VARCHAR(255) DEFAULT '1k_tokens'");
        }
    }
};
