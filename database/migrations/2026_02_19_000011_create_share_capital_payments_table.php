<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('share_capital_payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('share_account_id')->references('id')->on('member_share_accounts')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // who processed payment
            $table->string('payment_number', 25)->unique(); // SCP-2026-000001
            $table->bigInteger('amount')->comment('Payment amount in centavos');
            $table->bigInteger('balance_before')->comment('total_paid_up before this payment in centavos');
            $table->bigInteger('balance_after')->comment('total_paid_up after this payment in centavos');
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
            $table->index(['store_id', 'share_account_id']);
            $table->index('payment_date');
            $table->index('is_reversed');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('share_capital_payments');
    }
};
