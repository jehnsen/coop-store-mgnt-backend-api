<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_share_accounts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('account_number', 20)->unique(); // SHA-2026-000001
            $table->enum('share_type', ['regular', 'preferred'])->default('regular');
            $table->unsignedInteger('subscribed_shares'); // number of shares subscribed
            $table->bigInteger('par_value_per_share')->comment('Par value per share in centavos'); // e.g. 10000 = ₱100
            $table->bigInteger('total_subscribed_amount')->comment('subscribed_shares × par_value_per_share in centavos');
            $table->bigInteger('total_paid_up_amount')->default(0)->comment('Running total paid in centavos');
            $table->enum('status', ['active', 'suspended', 'withdrawn'])->default('active');
            $table->date('opened_date');
            $table->date('withdrawn_date')->nullable();
            $table->foreignId('withdrawn_by')->nullable()->references('id')->on('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('uuid');
            $table->index(['store_id', 'customer_id']);
            $table->index(['store_id', 'status']);
            $table->index('account_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_share_accounts');
    }
};
