<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MPC MAF Module: Member-registered beneficiaries.
 *
 * Members pre-register their beneficiaries so that death or hospitalization
 * claims can be filed on their behalf. A member may have multiple beneficiaries;
 * at most one should be flagged is_primary = true.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maf_beneficiaries', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();

            $table->string('name', 150);

            $table->enum('relationship', [
                'spouse',
                'child',
                'parent',
                'sibling',
                'other',
            ]);

            $table->date('birth_date')->nullable();
            $table->string('contact_number', 30)->nullable();

            // Only one beneficiary per member should be primary
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_active')->default(true);

            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('uuid');
            $table->index(['customer_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maf_beneficiaries');
    }
};
