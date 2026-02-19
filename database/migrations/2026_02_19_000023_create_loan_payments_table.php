<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('loan_id')->constrained('loans')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // collector
            $table->string('payment_number', 25)->unique(); // LP-2026-000001
            $table->bigInteger('amount')->comment('Total payment amount in centavos');
            $table->bigInteger('principal_portion')->default(0)->comment('In centavos');
            $table->bigInteger('interest_portion')->default(0)->comment('In centavos');
            $table->bigInteger('penalty_portion')->default(0)->comment('In centavos');
            $table->bigInteger('balance_before')->comment('outstanding_balance before payment in centavos');
            $table->bigInteger('balance_after')->comment('outstanding_balance after payment in centavos');
            $table->enum('payment_method', ['cash', 'gcash', 'maya', 'bank_transfer', 'check', 'salary_deduction']);
            $table->string('reference_number', 100)->nullable();
            $table->date('payment_date');
            $table->text('notes')->nullable();
            $table->boolean('is_reversed')->default(false);
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->references('id')->on('users')->nullOnDelete();
            $table->timestamps();
            // No softDeletes — immutable ledger

            $table->index('uuid');
            $table->index(['store_id', 'loan_id']);
            $table->index('payment_date');
            $table->index('is_reversed');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_payments');
    }
};
