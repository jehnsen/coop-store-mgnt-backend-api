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
        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_to')->nullable()->references('id')->on('users')->nullOnDelete(); // Delivery person
            $table->string('delivery_number')->unique(); // DEL-2026-000001
            $table->enum('status', ['preparing', 'dispatched', 'in_transit', 'delivered', 'failed', 'cancelled'])->default('preparing');
            $table->text('delivery_address');
            $table->string('delivery_city', 100)->nullable();
            $table->string('delivery_province', 100)->nullable();
            $table->string('contact_person')->nullable();
            $table->string('contact_phone', 20)->nullable();
            $table->date('scheduled_date')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->string('proof_of_delivery_path')->nullable(); // Photo/signature path
            $table->string('received_by')->nullable(); // Name of person who received
            $table->text('delivery_notes')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('uuid');
            $table->index('delivery_number');
            $table->index(['store_id', 'sale_id']);
            $table->index('customer_id');
            $table->index('branch_id');
            $table->index('assigned_to');
            $table->index('status');
            $table->index('scheduled_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deliveries');
    }
};
