<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_products', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->enum('loan_type', ['term', 'emergency', 'salary', 'agricultural', 'livelihood']);
            $table->decimal('interest_rate', 8, 4)->comment('Monthly interest rate, e.g. 0.0150 = 1.5%/month');
            $table->enum('interest_method', ['diminishing_balance'])->default('diminishing_balance');
            $table->unsignedSmallInteger('max_term_months');
            $table->bigInteger('min_amount')->default(0)->comment('Minimum loanable amount in centavos');
            $table->bigInteger('max_amount')->comment('Maximum loanable amount in centavos');
            $table->decimal('processing_fee_rate', 5, 4)->default(0)->comment('Processing fee rate, e.g. 0.0100 = 1%');
            $table->bigInteger('service_fee')->default(0)->comment('Fixed service fee in centavos');
            $table->boolean('requires_collateral')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('uuid');
            $table->index(['store_id', 'is_active']);
            $table->index(['store_id', 'loan_type']);
            $table->unique(['store_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_products');
    }
};
