<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MPC MAF Module: Immutable disbursement records for approved MAF claims.
 *
 * Created when an approved claim is paid out to the member or beneficiary.
 * Intentionally has no soft deletes — once a payment is recorded it is
 * permanent for audit purposes. One claim has at most one payment record.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maf_claim_payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();

            $table->foreignId('claim_id')
                  ->constrained('maf_claims')
                  ->cascadeOnDelete();

            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->comment('Staff who processed the disbursement');

            // Auto-generated: MAFP-YYYY-NNNNNN
            $table->string('payment_number', 20)->unique()->comment('MAFP-YYYY-NNNNNN');

            // Amount disbursed in centavos (equals maf_claims.approved_amount)
            $table->bigInteger('amount')->comment('Disbursed amount in centavos');

            $table->enum('payment_method', [
                'cash',
                'gcash',
                'maya',
                'bank_transfer',
                'check',
                'salary_deduction',
            ]);

            $table->string('reference_number', 100)->nullable();
            $table->date('payment_date');
            $table->text('notes')->nullable();

            $table->timestamps();
            // NO softDeletes — immutable ledger

            $table->index('uuid');
            $table->index(['store_id', 'claim_id']);
            $table->index('payment_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maf_claim_payments');
    }
};
