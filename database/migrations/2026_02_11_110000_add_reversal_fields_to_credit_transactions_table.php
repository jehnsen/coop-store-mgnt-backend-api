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
            $table->boolean('is_reversed')->default(false)->after('notes');
            $table->timestamp('reversed_at')->nullable()->after('is_reversed');
            $table->foreignId('reversed_by')->nullable()->after('reversed_at')->constrained('users')->nullOnDelete();

            $table->index('is_reversed');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('credit_transactions', function (Blueprint $table) {
            $table->dropForeign(['reversed_by']);
            $table->dropIndex(['is_reversed']);
            $table->dropColumn(['is_reversed', 'reversed_at', 'reversed_by']);
        });
    }
};
