<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coop_officers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();

            // Link to member (nullable — some officers may not be in customer records)
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();

            // Officer details (stored explicitly so they survive customer name changes)
            $table->string('name', 150);
            $table->string('position', 100)
                ->comment('e.g. Chairperson, Vice-Chairperson, Secretary, Treasurer, Director, Audit Committee Member');
            $table->string('committee', 100)->nullable()
                ->comment('e.g. Board of Directors, Audit Committee, Election Committee, Ethics Committee');

            // Term tracking
            $table->date('term_from');
            $table->date('term_to')->nullable()->comment('Null = still in office');
            $table->boolean('is_active')->default(true)->index();

            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('uuid');
            $table->index(['store_id', 'is_active']);
            $table->index(['store_id', 'committee']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coop_officers');
    }
};
