<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('time_deposit_transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('time_deposit_id')
                ->references('id')->on('time_deposits')
                ->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('transaction_number', 25)->unique()->comment('TDT-YYYY-NNNNNN');
            $table->enum('transaction_type', [
                'placement',
                'interest_accrual',
                'interest_payout',
                'pre_termination',
                'maturity_payout',
                'rollover',
            ]);

            // Amounts (centavos)
            $table->bigInteger('amount')->comment('Gross amount disbursed/credited; in centavos');
            $table->bigInteger('interest_amount')->default(0)->comment('Interest component; in centavos');
            $table->bigInteger('penalty_amount')->default(0)->comment('Early withdrawal penalty; in centavos');
            $table->bigInteger('balance_before')->comment('In centavos');
            $table->bigInteger('balance_after')->comment('In centavos');

            $table->enum('payment_method', [
                'cash', 'gcash', 'maya', 'bank_transfer', 'check', 'internal_transfer',
            ])->nullable();
            $table->string('reference_number', 100)->nullable();
            $table->date('transaction_date');
            $table->date('period_from')->nullable()->comment('Interest accrual period start');
            $table->date('period_to')->nullable()->comment('Interest accrual period end');
            $table->text('notes')->nullable();

            // Reversal (immutable ledger — no softDeletes)
            $table->boolean('is_reversed')->default(false);
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->references('id')->on('users')->nullOnDelete();

            $table->timestamps();

            $table->index('uuid');
            $table->index(['store_id', 'time_deposit_id']);
            $table->index('transaction_date');
            $table->index('transaction_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('time_deposit_transactions');
    }
};
