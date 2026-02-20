<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->timestamp('sale_date')->nullable()->after('sale_number');
            $table->index('sale_date');
        });

        // Backfill existing rows with their created_at value
        DB::statement('UPDATE sales SET sale_date = created_at WHERE sale_date IS NULL');
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex(['sale_date']);
            $table->dropColumn('sale_date');
        });
    }
};
