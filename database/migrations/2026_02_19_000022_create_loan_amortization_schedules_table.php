<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_amortization_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained('loans')->cascadeOnDelete();
            $table->unsignedSmallInteger('payment_number'); // 1-based
            $table->date('due_date');
            $table->bigInteger('beginning_balance')->comment('Outstanding balance at start of period in centavos');
            $table->bigInteger('principal_due')->comment('Principal portion due in centavos');
            $table->bigInteger('interest_due')->comment('Interest portion due in centavos');
            $table->bigInteger('total_due')->comment('principal_due + interest_due in centavos');
            $table->bigInteger('principal_paid')->default(0)->comment('In centavos');
            $table->bigInteger('interest_paid')->default(0)->comment('In centavos');
            $table->bigInteger('penalty_paid')->default(0)->comment('In centavos');
            $table->bigInteger('total_paid')->default(0)->comment('In centavos');
            $table->bigInteger('ending_balance')->comment('Outstanding balance at end of period in centavos');
            $table->date('paid_date')->nullable();
            $table->enum('status', ['pending', 'partial', 'paid', 'overdue', 'waived'])->default('pending');
            $table->timestamps();
            // No softDeletes — child of loan, deleted via cascade

            $table->index('loan_id');
            $table->index('due_date');
            $table->index('status');
            $table->index(['loan_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_amortization_schedules');
    }
};
