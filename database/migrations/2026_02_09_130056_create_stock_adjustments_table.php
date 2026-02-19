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
        Schema::create('stock_adjustments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['initial_stock', 'stock_in', 'stock_out', 'physical_count', 'damage', 'expired', 'return', 'transfer', 'sale', 'purchase']);
            $table->decimal('quantity_before', 12, 4);
            $table->decimal('quantity_change', 12, 4); // Can be positive or negative
            $table->decimal('quantity_after', 12, 4);
            $table->string('reference_type')->nullable(); // e.g., 'App\Models\Sale', 'App\Models\PurchaseOrder'
            $table->unsignedBigInteger('reference_id')->nullable(); // ID of the related record
            $table->text('reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('uuid');
            $table->index(['store_id', 'branch_id']);
            $table->index('product_id');
            $table->index('type');
            $table->index(['reference_type', 'reference_id']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_adjustments');
    }
};
