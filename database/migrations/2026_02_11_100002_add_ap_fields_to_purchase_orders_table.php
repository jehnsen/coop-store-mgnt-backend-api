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
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->enum('payment_status', ['unpaid', 'partial', 'paid'])->default('unpaid')->after('status');
            $table->bigInteger('amount_paid')->default(0)->after('total_amount'); // in centavos
            $table->date('payment_due_date')->nullable()->after('received_date');
            $table->date('payment_completed_date')->nullable()->after('payment_due_date');

            // Add indexes for performance
            $table->index('payment_status');
            $table->index('payment_due_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropIndex(['payment_status']);
            $table->dropIndex(['payment_due_date']);
            $table->dropColumn(['payment_status', 'amount_paid', 'payment_due_date', 'payment_completed_date']);
        });
    }
};
