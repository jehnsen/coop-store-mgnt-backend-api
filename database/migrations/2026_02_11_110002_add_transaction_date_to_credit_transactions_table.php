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
        Schema::table('credit_transactions', function (Blueprint $table) {
            $table->timestamp('transaction_date')->nullable()->after('notes');

            $table->index('transaction_date');
        });

        // Set transaction_date to created_at for existing records
        DB::table('credit_transactions')->update([
            'transaction_date' => DB::raw('created_at')
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('credit_transactions', function (Blueprint $table) {
            $table->dropIndex(['transaction_date']);
            $table->dropColumn('transaction_date');
        });
    }
};
