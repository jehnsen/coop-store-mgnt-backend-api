<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cda_annual_reports', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();

            // Report identity
            $table->unsignedSmallInteger('report_year');
            $table->date('period_from'); // typically Jan 1 of report_year
            $table->date('period_to');   // typically Dec 31 of report_year

            // CDA cooperative-specific metadata
            $table->string('cda_reg_number', 50)->nullable()
                ->comment('CDA Certificate of Registration number');
            $table->string('cooperative_type', 100)->nullable()
                ->comment('e.g. Multipurpose, Credit, Transport, etc.');
            $table->string('area_of_operation', 100)->nullable()
                ->comment('e.g. Barangay, Municipal, Provincial, National');

            // Compiled report data snapshot (JSON)
            $table->json('report_data')->nullable()
                ->comment('Full compiled report data: membership stats, financial highlights, officers, etc.');

            // Workflow
            $table->enum('status', ['draft', 'finalized', 'submitted'])->default('draft');
            $table->foreignId('compiled_by')->nullable()->references('id')->on('users')->nullOnDelete();
            $table->timestamp('compiled_at')->nullable();
            $table->foreignId('finalized_by')->nullable()->references('id')->on('users')->nullOnDelete();
            $table->timestamp('finalized_at')->nullable();

            // CDA submission tracking
            $table->date('submitted_date')->nullable()->comment('Date physically/digitally submitted to CDA');
            $table->string('submission_reference', 100)->nullable()->comment('CDA tracking/reference number');

            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('uuid');
            $table->index(['store_id', 'report_year']);
            $table->index(['store_id', 'status']);
            $table->unique(['store_id', 'report_year']); // one report per year per store
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cda_annual_reports');
    }
};
