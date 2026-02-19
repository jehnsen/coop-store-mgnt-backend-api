<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MPC MAF Module: Immutable ledger of member MAF fund contributions.
 *
 * Members make periodic contributions (monthly or annual) to replenish the
 * MAF fund pool. This table is an immutable ledger — no soft deletes. Errors
 * are corrected via reversal fields (is_reversed, reversed_at, etc.) following
 * the same pattern as LoanPayment and SavingsTransaction.
 *
 * Fund balance = SUM(amount WHERE NOT is_reversed) − SUM of MafClaimPayment amounts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maf_contributions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->comment('Cashier who recorded the contribution');

            // Auto-generated: MAFC-YYYY-NNNNNN
            $table->string('contribution_number', 20)->unique()->comment('MAFC-YYYY-NNNNNN');

            // Amount in centavos
            $table->bigInteger('amount')->comment('Contribution amount in centavos');

            $table->enum('payment_method', [
                'cash',
                'gcash',
                'maya',
                'bank_transfer',
                'check',
                'salary_deduction',
            ]);

            $table->string('reference_number', 100)->nullable();
            $table->date('contribution_date');

            // Period this contribution covers. period_month is nullable for annual coops.
            $table->year('period_year');
            $table->unsignedTinyInteger('period_month')->nullable()->comment('1-12; null for annual contributions');

            $table->text('notes')->nullable();

            // Reversal fields (immutable pattern — no softDeletes)
            $table->boolean('is_reversed')->default(false);
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->references('id')->on('users')->nullOnDelete();
            $table->text('reversal_reason')->nullable();

            $table->timestamps();
            // NO softDeletes — immutable ledger

            $table->index('uuid');
            $table->index(['store_id', 'customer_id']);
            $table->index('contribution_date');
            $table->index('is_reversed');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maf_contributions');
    }
};
