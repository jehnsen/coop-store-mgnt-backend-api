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
        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name', 255);
            $table->string('slug', 100)->unique();
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('province', 100)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('logo_path', 500)->nullable();
            $table->string('tin', 20)->nullable(); // Tax Identification Number
            $table->string('bir_permit_no', 50)->nullable();
            $table->string('bir_min', 50)->nullable(); // Machine Identification Number
            $table->text('receipt_header')->nullable();
            $table->text('receipt_footer')->nullable();
            $table->decimal('vat_rate', 5, 2)->default(12.00);
            $table->boolean('vat_inclusive')->default(true);
            $table->boolean('is_vat_registered')->default(true); // false for BMBE
            $table->integer('default_credit_terms_days')->default(30);
            $table->bigInteger('default_credit_limit')->default(0); // in centavos
            $table->string('timezone', 50)->default('Asia/Manila');
            $table->string('currency', 3)->default('PHP');
            $table->enum('subscription_plan', ['trial', 'basic', 'pro', 'enterprise'])->default('trial');
            $table->timestamp('subscription_expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('uuid');
            $table->index('slug');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stores');
    }
};
