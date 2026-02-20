<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_penalties', function (Blueprint $table) {
            $table->unique(['amortization_schedule_id', 'applied_date'], 'loan_penalties_schedule_date_unique');
        });
    }

    public function down(): void
    {
        Schema::table('loan_penalties', function (Blueprint $table) {
            $table->dropUnique('loan_penalties_schedule_date_unique');
        });
    }
};
