<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('ai_usage_costs', 'ai_cost_records');
    }

    public function down(): void
    {
        Schema::rename('ai_cost_records', 'ai_usage_costs');
    }
};
