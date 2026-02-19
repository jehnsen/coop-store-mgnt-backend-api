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
        Schema::create('supplier_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('supplier_sku')->nullable(); // Supplier's product code
            $table->bigInteger('supplier_price')->default(0); // in centavos
            $table->integer('lead_time_days')->default(7);
            $table->decimal('minimum_order_quantity', 12, 4)->default(1);
            $table->boolean('is_preferred')->default(false); // Preferred supplier for this product
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['supplier_id', 'product_id']);
            $table->index('supplier_id');
            $table->index('product_id');
            $table->index('is_preferred');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_products');
    }
};
