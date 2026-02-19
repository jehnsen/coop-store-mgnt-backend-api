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
        Schema::create('product_unit_conversions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_unit_id')->references('id')->on('units_of_measure')->cascadeOnDelete();
            $table->foreignId('to_unit_id')->references('id')->on('units_of_measure')->cascadeOnDelete();
            $table->decimal('conversion_factor', 12, 4); // e.g., 1 box = 100 pcs, so factor = 100
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index('product_id');
            $table->unique(['product_id', 'from_unit_id', 'to_unit_id'], 'prod_unit_conv_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_unit_conversions');
    }
};
