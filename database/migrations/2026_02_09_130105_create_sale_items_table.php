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
        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('product_name'); // Snapshot at time of sale
            $table->string('product_sku'); // Snapshot at time of sale
            $table->decimal('quantity', 12, 4);
            $table->bigInteger('unit_price')->default(0); // in centavos (snapshot)
            $table->enum('discount_type', ['percentage', 'fixed'])->nullable();
            $table->decimal('discount_value', 10, 2)->default(0);
            $table->bigInteger('discount_amount')->default(0); // in centavos
            $table->bigInteger('line_total')->default(0); // in centavos (quantity × unit_price - discount_amount)
            $table->bigInteger('cost_price')->default(0); // in centavos (snapshot for profit calculation)
            $table->timestamps();

            $table->index('sale_id');
            $table->index('product_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_items');
    }
};
