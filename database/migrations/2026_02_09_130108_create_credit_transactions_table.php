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
        Schema::create('credit_transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // User who created the transaction
            $table->enum('type', ['charge', 'payment', 'adjustment']); // charge = credit sale, payment = customer payment
            $table->string('reference_number')->nullable(); // For payments: check/reference number
            $table->bigInteger('amount')->default(0); // in centavos, positive for charges, negative for payments
            $table->bigInteger('balance_before')->default(0); // in centavos
            $table->bigInteger('balance_after')->default(0); // in centavos
            $table->date('due_date')->nullable(); // For charges
            $table->date('paid_date')->nullable(); // For payments
            $table->enum('payment_method', ['cash', 'gcash', 'maya', 'bank_transfer', 'check'])->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('uuid');
            $table->index(['store_id', 'customer_id']);
            $table->index('sale_id');
            $table->index('type');
            $table->index('due_date');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credit_transactions');
    }
};
