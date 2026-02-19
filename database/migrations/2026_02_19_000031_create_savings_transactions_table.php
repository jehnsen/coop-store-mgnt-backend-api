<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('savings_transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('savings_account_id')
                ->references('id')->on('member_savings_accounts')
                ->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('transaction_number', 25)->unique()->comment('SVT-YYYY-NNNNNN');
            $table->enum('transaction_type', [
                'deposit',
                'withdrawal',
                'interest_credit',
                'compulsory_deduction',
                'adjustment',
                'closing_payout',
            ]);

            // Amounts (centavos) — always positive; direction determined by transaction_type
            $table->bigInteger('amount')->comment('In centavos');
            $table->bigInteger('balance_before')->comment('In centavos');
            $table->bigInteger('balance_after')->comment('In centavos');

            $table->enum('payment_method', [
                'cash', 'gcash', 'maya', 'bank_transfer', 'check',
                'salary_deduction', 'internal_transfer',
            ])->nullable();
            $table->string('reference_number', 100)->nullable();
            $table->date('transaction_date');
            $table->text('notes')->nullable();

            // Reversal (immutable ledger — no softDeletes)
            $table->boolean('is_reversed')->default(false);
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->references('id')->on('users')->nullOnDelete();

            $table->timestamps();

            $table->index('uuid');
            $table->index(['store_id', 'savings_account_id']);
            $table->index('transaction_date');
            $table->index('transaction_type');
            $table->index('is_reversed');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('savings_transactions');
    }
};
