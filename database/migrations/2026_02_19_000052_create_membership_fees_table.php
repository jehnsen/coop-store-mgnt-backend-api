<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membership_fees', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // who processed
            $table->foreignId('application_id')
                ->nullable()
                ->references('id')->on('membership_applications')
                ->nullOnDelete();

            // Fee identity
            $table->string('fee_number', 30)->unique(); // MFE-YYYY-NNNNNN
            $table->enum('fee_type', [
                'admission_fee',      // One-time on membership approval
                'annual_dues',        // Yearly membership dues
                'reinstatement_fee',  // On reinstatement from inactive/expelled
                'other',
            ]);
            $table->bigInteger('amount')->comment('Fee amount in centavos');

            // Payment details
            $table->enum('payment_method', [
                'cash', 'check', 'bank_transfer',
                'gcash', 'maya', 'internal_transfer',
            ])->nullable();
            $table->string('reference_number', 100)->nullable();
            $table->date('transaction_date');

            // For annual dues: which year this covers
            $table->unsignedSmallInteger('period_year')->nullable();

            $table->text('notes')->nullable();

            // Immutable ledger — no SoftDeletes; use reversal flag
            $table->boolean('is_reversed')->default(false);
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->references('id')->on('users')->nullOnDelete();

            $table->timestamps();

            $table->index('uuid');
            $table->index(['store_id', 'customer_id']);
            $table->index(['store_id', 'fee_type']);
            $table->index('transaction_date');
            $table->index('fee_number');
            $table->index('is_reversed');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_fees');
    }
};
