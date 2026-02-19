<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('share_certificates', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('share_account_id')->references('id')->on('member_share_accounts')->cascadeOnDelete();
            $table->string('certificate_number', 25)->unique(); // SC-2026-000001
            $table->unsignedInteger('shares_covered'); // number of shares this certificate covers
            $table->bigInteger('face_value')->comment('shares_covered × par_value_per_share in centavos');
            $table->date('issue_date');
            $table->foreignId('issued_by')->references('id')->on('users')->cascadeOnDelete();
            $table->enum('status', ['active', 'cancelled'])->default('active');
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->references('id')->on('users')->nullOnDelete();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('uuid');
            $table->index(['store_id', 'customer_id']);
            $table->index('status');
            $table->index('certificate_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('share_certificates');
    }
};
