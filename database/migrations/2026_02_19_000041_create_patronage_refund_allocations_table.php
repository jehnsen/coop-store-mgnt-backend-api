<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patronage_refund_allocations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('batch_id')
                ->references('id')->on('patronage_refund_batches')
                ->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();

            // Computation results (centavos)
            $table->bigInteger('member_purchases')->comment('Member total purchases in period; in centavos');
            $table->decimal('allocation_percentage', 10, 6)->default(0)
                ->comment('Member share of total purchases; e.g. 2.500000 = 2.5%');
            $table->bigInteger('allocation_amount')->comment('Computed PR refund; in centavos');

            // Distribution tracking
            $table->enum('status', ['pending', 'paid', 'forfeited'])->default('pending');
            $table->enum('payment_method', [
                'cash', 'check', 'bank_transfer', 'savings_credit',
                'gcash', 'maya', 'internal_transfer',
            ])->nullable();
            $table->string('reference_number', 100)->nullable();
            $table->date('paid_date')->nullable();
            $table->foreignId('paid_by')->nullable()->references('id')->on('users')->nullOnDelete();
            $table->text('notes')->nullable();

            // NO softDeletes — immutable audit trail once computed
            $table->timestamps();

            $table->index('uuid');
            $table->index(['store_id', 'batch_id']);
            $table->index(['store_id', 'customer_id']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patronage_refund_allocations');
    }
};
