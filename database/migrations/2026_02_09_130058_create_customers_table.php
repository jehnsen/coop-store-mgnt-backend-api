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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('code')->nullable(); // Customer code/number
            $table->string('name');
            $table->enum('type', ['individual', 'business'])->default('individual');
            $table->string('company_name')->nullable();
            $table->string('tin')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('mobile', 20)->nullable();
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('province', 100)->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->bigInteger('credit_limit')->default(0); // in centavos
            $table->integer('credit_terms_days')->default(30);
            $table->bigInteger('total_outstanding')->default(0); // in centavos
            $table->bigInteger('total_purchases')->default(0); // in centavos (lifetime)
            $table->enum('payment_rating', ['excellent', 'good', 'average', 'poor'])->default('good');
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('allow_credit')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index('uuid');
            $table->index(['store_id', 'code']);
            $table->index(['store_id', 'name']);
            $table->index(['store_id', 'email']);
            $table->index(['store_id', 'phone']);
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
