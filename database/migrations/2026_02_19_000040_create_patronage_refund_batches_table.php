<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patronage_refund_batches', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();

            // Period descriptor
            $table->string('period_label', 50)->comment('e.g. "FY2026", "Q1 2026"');
            $table->date('period_from');
            $table->date('period_to');

            // Computation settings
            $table->enum('computation_method', ['rate_based', 'pool_based'])->default('rate_based')
                ->comment('rate_based: allocation = purchases × pr_rate; pool_based: allocation = (purchases/total) × pr_fund');
            $table->decimal('pr_rate', 8, 6)->default(0)
                ->comment('For rate_based: e.g. 0.050000 = 5% of purchases');
            $table->bigInteger('pr_fund')->default(0)
                ->comment('For pool_based: total fund to distribute; in centavos');

            // Computed totals (centavos)
            $table->bigInteger('total_member_purchases')->default(0)->comment('Sum of qualifying member purchases; in centavos');
            $table->bigInteger('total_store_sales')->default(0)->comment('Total store completed sales in period; in centavos');
            $table->bigInteger('total_allocated')->default(0)->comment('Sum of all computed allocations; in centavos');
            $table->bigInteger('total_distributed')->default(0)->comment('Sum of paid allocations; in centavos');
            $table->unsignedInteger('member_count')->default(0)->comment('Number of members with qualifying purchases');

            // Lifecycle
            $table->enum('status', ['draft', 'approved', 'distributing', 'completed'])->default('draft');
            $table->foreignId('approved_by')->nullable()->references('id')->on('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('uuid');
            $table->index(['store_id', 'status']);
            $table->index(['store_id', 'period_from', 'period_to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patronage_refund_batches');
    }
};
