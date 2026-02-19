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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->references('id')->on('units_of_measure')->cascadeOnDelete();
            $table->string('name');
            $table->string('sku')->unique(); // Auto-generated or manual
            $table->string('barcode')->nullable();
            $table->text('description')->nullable();
            $table->string('brand')->nullable();
            $table->string('size')->nullable();
            $table->string('material')->nullable();
            $table->string('color')->nullable();
            $table->bigInteger('cost_price'); // in centavos
            $table->bigInteger('retail_price'); // in centavos
            $table->bigInteger('wholesale_price')->nullable(); // in centavos
            $table->bigInteger('contractor_price')->nullable(); // in centavos
            $table->decimal('current_stock', 12, 4)->default(0);
            $table->decimal('reorder_point', 12, 4)->default(0);
            $table->decimal('minimum_order_qty', 12, 4)->default(1);
            $table->string('image_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_vat_exempt')->default(false);
            $table->boolean('track_inventory')->default(true);
            $table->boolean('allow_negative_stock')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index('uuid');
            $table->index(['store_id', 'sku']);
            $table->index(['store_id', 'barcode']);
            $table->index(['store_id', 'category_id']);
            $table->index(['store_id', 'name']);
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
