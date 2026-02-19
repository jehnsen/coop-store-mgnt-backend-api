<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MPC MAF Module: Benefit claims filed by members or their beneficiaries.
 *
 * Claim lifecycle: pending → under_review → approved/rejected → paid
 *
 * benefit_type is denormalised from the MAF program at filing time so that
 * historical claims retain their type even if the program is later edited.
 *
 * approved_amount may differ from benefit_amount (e.g. partial approval, or
 * the board adjusts the payout). It may also differ from claimed_amount (what
 * the member requested).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maf_claims', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();

            $table->foreignId('maf_program_id')
                  ->constrained('maf_programs')
                  ->comment('Program claimed against');

            // Nullable: beneficiary is linked for death/hospitalization claims
            $table->foreignId('beneficiary_id')
                  ->nullable()
                  ->constrained('maf_beneficiaries')
                  ->nullOnDelete();

            // Auto-generated: CLAM-YYYY-NNNNNN
            $table->string('claim_number', 20)->unique()->comment('CLAM-YYYY-NNNNNN');

            // Snapshot of benefit_type at filing time (for historical accuracy)
            $table->string('benefit_type', 30);

            $table->date('incident_date');
            $table->date('claim_date');
            $table->text('incident_description');

            // JSON array of document file references (uploaded by frontend)
            $table->json('supporting_documents')->nullable();

            // Amounts in centavos
            $table->bigInteger('claimed_amount')->comment('Amount requested by member, in centavos');
            $table->bigInteger('approved_amount')->nullable()->comment('Amount approved by board, in centavos');

            $table->enum('status', [
                'pending',
                'under_review',
                'approved',
                'rejected',
                'paid',
            ])->default('pending');

            // Lifecycle timestamps and actors
            $table->foreignId('reviewed_by')->nullable()->references('id')->on('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();

            $table->foreignId('approved_by')->nullable()->references('id')->on('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();

            $table->foreignId('rejected_by')->nullable()->references('id')->on('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->foreignId('paid_by')->nullable()->references('id')->on('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('uuid');
            $table->index('claim_number');
            $table->index(['store_id', 'status']);
            $table->index(['customer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maf_claims');
    }
};
