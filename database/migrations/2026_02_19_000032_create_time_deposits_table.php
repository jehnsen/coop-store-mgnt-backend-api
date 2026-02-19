<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('time_deposits', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();

            $table->string('account_number', 20)->unique()->comment('TD-YYYY-NNNNNN');

            // Core terms (immutable after placement)
            $table->bigInteger('principal_amount')->comment('In centavos — immutable after placement');
            $table->decimal('interest_rate', 8, 6)->comment('Annual rate e.g. 0.060000 = 6%/yr');
            $table->enum('interest_method', ['simple_on_maturity', 'periodic'])->default('simple_on_maturity');
            $table->enum('payment_frequency', ['monthly', 'quarterly', 'semi_annual', 'on_maturity'])->default('on_maturity');
            $table->unsignedSmallInteger('term_months')->comment('e.g. 3, 6, 12, 24, 36');
            $table->decimal('early_withdrawal_penalty_rate', 5, 4)->default(0.2500)
                ->comment('Fraction of earned interest charged as penalty; e.g. 0.2500 = 25%');

            // Dates
            $table->date('placement_date');
            $table->date('maturity_date')->comment('Computed: placement_date + term_months');

            // Running balance
            $table->bigInteger('current_balance')->comment('Principal + accrued interest; in centavos');
            $table->bigInteger('total_interest_earned')->default(0)->comment('In centavos');
            $table->bigInteger('expected_interest')->comment('Total interest if held to maturity; in centavos');

            // Lifecycle
            $table->enum('status', ['active', 'matured', 'pre_terminated', 'rolled_over'])->default('active');
            $table->timestamp('matured_at')->nullable();
            $table->timestamp('pre_terminated_at')->nullable();
            $table->foreignId('pre_terminated_by')->nullable()->references('id')->on('users')->nullOnDelete();
            $table->text('pre_termination_reason')->nullable();

            // Rollover chain
            $table->unsignedSmallInteger('rollover_count')->default(0);
            $table->foreignId('parent_time_deposit_id')->nullable()
                ->references('id')->on('time_deposits')->nullOnDelete();

            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('uuid');
            $table->index(['store_id', 'customer_id']);
            $table->index(['store_id', 'status']);
            $table->index('maturity_date');
            $table->index('account_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('time_deposits');
    }
};
