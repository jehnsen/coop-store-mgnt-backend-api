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
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // Cashier
            $table->string('sale_number')->unique(); // INV-2026-000001
            $table->timestamp('sale_date')->nullable();
            $table->enum('price_tier', ['retail', 'wholesale', 'contractor'])->default('retail');
            $table->bigInteger('subtotal_amount')->default(0); // in centavos (before order-level discount)
            $table->enum('discount_type', ['percentage', 'fixed'])->nullable();
            $table->decimal('discount_value', 10, 2)->default(0);
            $table->bigInteger('discount_amount')->default(0); // in centavos
            $table->bigInteger('vat_amount')->default(0); // in centavos
            $table->bigInteger('total_amount')->default(0); // in centavos (final amount)
            $table->bigInteger('amount_paid')->default(0); // in centavos
            $table->bigInteger('change_amount')->default(0); // in centavos
            $table->enum('payment_status', ['paid', 'partial', 'unpaid'])->default('paid');
            $table->enum('status', ['completed', 'voided', 'refunded'])->default('completed');
            $table->text('notes')->nullable();
            $table->text('void_reason')->nullable();
            $table->foreignId('voided_by')->nullable()->references('id')->on('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('uuid');
            $table->index('sale_number');
            $table->index(['store_id', 'branch_id']);
            $table->index('customer_id');
            $table->index('user_id');
            $table->index('status');
            $table->index('payment_status');
            $table->index('created_at');
            $table->index('sale_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
