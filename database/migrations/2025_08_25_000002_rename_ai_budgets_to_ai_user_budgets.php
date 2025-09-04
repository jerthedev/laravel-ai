<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('ai_budgets', 'ai_user_budgets');
    }

    public function down(): void
    {
        Schema::rename('ai_user_budgets', 'ai_budgets');
    }
};
