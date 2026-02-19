<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_savings_accounts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();

            $table->string('account_number', 20)->unique()->comment('SVA-YYYY-NNNNNN');
            $table->enum('savings_type', ['voluntary', 'compulsory'])->default('voluntary');

            // Running balances (centavos)
            $table->bigInteger('current_balance')->default(0)->comment('In centavos');
            $table->bigInteger('minimum_balance')->default(0)->comment('Maintaining balance floor; in centavos');
            $table->decimal('interest_rate', 8, 6)->default(0)->comment('Annual rate e.g. 0.030000 = 3%/yr');

            // Lifetime totals (centavos)
            $table->bigInteger('total_deposited')->default(0)->comment('In centavos');
            $table->bigInteger('total_withdrawn')->default(0)->comment('In centavos');
            $table->bigInteger('total_interest_earned')->default(0)->comment('In centavos');

            // Lifecycle
            $table->enum('status', ['active', 'dormant', 'closed'])->default('active');
            $table->date('opened_date');
            $table->date('closed_date')->nullable();
            $table->foreignId('closed_by')->nullable()->references('id')->on('users')->nullOnDelete();
            $table->date('last_transaction_date')->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('uuid');
            $table->index(['store_id', 'customer_id']);
            $table->index(['store_id', 'status']);
            $table->index(['store_id', 'savings_type']);
            $table->index('account_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_savings_accounts');
    }
};
