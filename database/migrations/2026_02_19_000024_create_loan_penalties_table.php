<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_penalties', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('loan_id')->constrained('loans')->cascadeOnDelete();
            $table->foreignId('amortization_schedule_id')->references('id')->on('loan_amortization_schedules')->cascadeOnDelete();
            $table->enum('penalty_type', ['late_payment', 'non_payment'])->default('late_payment');
            $table->decimal('penalty_rate', 5, 4)->comment('Rate used to compute, e.g. 0.0200 = 2%/month');
            $table->unsignedSmallInteger('days_overdue');
            $table->bigInteger('penalty_amount')->comment('Computed penalty in centavos');
            $table->bigInteger('waived_amount')->default(0)->comment('Amount waived in centavos');
            $table->bigInteger('net_penalty')->comment('penalty_amount - waived_amount in centavos');
            $table->foreignId('waived_by')->nullable()->references('id')->on('users')->nullOnDelete();
            $table->timestamp('waived_at')->nullable();
            $table->text('waiver_reason')->nullable();
            $table->date('applied_date');
            $table->boolean('is_paid')->default(false);
            $table->date('paid_date')->nullable();
            $table->timestamps();

            $table->index('uuid');
            $table->index(['store_id', 'loan_id']);
            $table->index('is_paid');
            $table->index('applied_date');
            $table->unique(['amortization_schedule_id', 'applied_date'], 'loan_penalties_schedule_date_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_penalties');
    }
};
