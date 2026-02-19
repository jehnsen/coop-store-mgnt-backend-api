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
        Schema::create('held_transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // User who held the transaction
            $table->string('hold_number')->unique(); // HOLD-2026-000001
            $table->string('customer_name')->nullable();
            $table->json('cart_data'); // Full cart state including items, customer, discounts, etc.
            $table->text('notes')->nullable();
            $table->timestamp('expires_at'); // Auto-delete after 24 hours
            $table->timestamps();

            $table->index('uuid');
            $table->index(['store_id', 'branch_id']);
            $table->index('user_id');
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('held_transactions');
    }
};
