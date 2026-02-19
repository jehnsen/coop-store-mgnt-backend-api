<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membership_applications', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();

            // Application identity
            $table->string('application_number', 30)->unique(); // APP-YYYY-NNNNNN
            $table->enum('application_type', ['new', 'reinstatement'])->default('new');
            $table->date('application_date');

            // Membership details captured at application time
            $table->string('civil_status', 30)->nullable();
            $table->string('occupation', 100)->nullable();
            $table->string('employer', 150)->nullable();
            $table->string('monthly_income_range', 50)->nullable();
            $table->text('beneficiary_info')->nullable(); // JSON or free-text

            // Fee expected at admission
            $table->bigInteger('admission_fee_amount')->default(0)
                ->comment('Admission fee in centavos expected at approval');

            // Workflow
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('reviewed_by')->nullable()->references('id')->on('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('uuid');
            $table->index(['store_id', 'customer_id']);
            $table->index(['store_id', 'status']);
            $table->index('application_number');
            $table->index('application_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_applications');
    }
};
