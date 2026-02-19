<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payable_transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->enum('type', ['invoice', 'payment', 'adjustment']);
            $table->string('reference_number')->nullable();
            $table->bigInteger('amount')->default(0); // in centavos
            $table->bigInteger('balance_before')->default(0); // in centavos
            $table->bigInteger('balance_after')->default(0); // in centavos
            $table->text('description')->nullable();
            $table->dateTime('transaction_date');
            $table->date('due_date')->nullable();
            $table->date('paid_date')->nullable();
            $table->enum('payment_method', ['cash', 'bank_transfer', 'check', 'online'])->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_reversed')->default(false);
            $table->dateTime('reversed_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Indexes for performance
            $table->index('uuid');
            $table->index(['store_id', 'supplier_id']);
            $table->index('purchase_order_id');
            $table->index('type');
            $table->index('due_date');
            $table->index('transaction_date');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payable_transactions');
    }
};
