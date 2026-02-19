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
        Schema::create('sms_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // User who triggered the SMS
            $table->string('recipient_phone', 20);
            $table->string('recipient_name')->nullable();
            $table->text('message');
            $table->enum('type', ['receipt', 'credit_reminder', 'delivery_update', 'low_stock', 'general'])->default('general');
            $table->enum('status', ['pending', 'sent', 'failed'])->default('pending');
            $table->string('provider')->default('semaphore'); // SMS provider used
            $table->string('provider_message_id')->nullable(); // Provider's message ID
            $table->text('provider_response')->nullable(); // Raw response from provider
            $table->text('error_message')->nullable();
            $table->integer('credits_used')->default(1); // Number of SMS credits consumed
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'type']);
            $table->index('status');
            $table->index('recipient_phone');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sms_logs');
    }
};
