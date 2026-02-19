<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('loan_number', 25)->unique(); // LN-2026-000001
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('loan_product_id')->constrained('loan_products')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // loan officer / encoder
            $table->foreignId('approved_by')->nullable()->references('id')->on('users')->nullOnDelete();
            $table->foreignId('disbursed_by')->nullable()->references('id')->on('users')->nullOnDelete();

            // Loan terms (snapshot at application time)
            $table->bigInteger('principal_amount')->comment('Principal in centavos');
            $table->decimal('interest_rate', 8, 4)->comment('Monthly rate snapshot from product');
            $table->enum('interest_method', ['diminishing_balance'])->default('diminishing_balance');
            $table->unsignedSmallInteger('term_months');
            $table->enum('payment_interval', ['weekly', 'semi_monthly', 'monthly'])->default('monthly');
            $table->text('purpose');
            $table->text('collateral_description')->nullable();

            // Computed at approval / amortization time
            $table->bigInteger('processing_fee')->default(0)->comment('In centavos');
            $table->bigInteger('service_fee')->default(0)->comment('In centavos');
            $table->bigInteger('net_proceeds')->default(0)->comment('principal - processing_fee - service_fee in centavos');
            $table->bigInteger('total_interest')->default(0)->comment('Sum of all scheduled interest in centavos');
            $table->bigInteger('total_payable')->default(0)->comment('principal + total_interest in centavos');
            $table->bigInteger('amortization_amount')->default(0)->comment('Per-period payment amount in centavos');

            // Running balances
            $table->bigInteger('outstanding_balance')->default(0)->comment('Current remaining principal in centavos');
            $table->bigInteger('total_principal_paid')->default(0)->comment('In centavos');
            $table->bigInteger('total_interest_paid')->default(0)->comment('In centavos');
            $table->bigInteger('total_penalty_paid')->default(0)->comment('In centavos');
            $table->bigInteger('total_penalties_outstanding')->default(0)->comment('In centavos');

            // Key dates
            $table->date('application_date');
            $table->date('approval_date')->nullable();
            $table->date('disbursement_date')->nullable();
            $table->date('first_payment_date')->nullable();
            $table->date('maturity_date')->nullable();

            // Workflow status
            $table->enum('status', [
                'pending', 'under_review', 'approved', 'rejected',
                'disbursed', 'active', 'closed', 'written_off',
            ])->default('pending');
            $table->text('rejection_reason')->nullable();

            // Restructuring
            $table->foreignId('restructured_from_loan_id')->nullable()->references('id')->on('loans')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index('uuid');
            $table->index('loan_number');
            $table->index(['store_id', 'status']);
            $table->index(['store_id', 'customer_id']);
            $table->index('disbursement_date');
            $table->index('maturity_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};
