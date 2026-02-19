<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aga_records', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();

            // Meeting identity
            $table->string('aga_number', 30)->unique(); // AGA-YYYY-NN or SGA-YYYY-NN
            $table->enum('meeting_type', ['annual', 'special'])->default('annual')
                ->comment('Annual General Assembly or Special General Assembly');
            $table->unsignedSmallInteger('meeting_year');
            $table->date('meeting_date');
            $table->string('venue', 255)->nullable();

            // Attendance & quorum
            $table->unsignedInteger('total_members')->default(0)
                ->comment('Total members as of the meeting date');
            $table->unsignedInteger('members_present')->default(0);
            $table->unsignedInteger('members_via_proxy')->default(0);
            $table->decimal('quorum_percentage', 5, 2)->default(0)
                ->comment('(present + proxy) / total * 100');
            $table->boolean('quorum_achieved')->default(false);

            // Meeting details
            $table->string('presiding_officer', 150)->nullable();
            $table->string('secretary', 150)->nullable();
            $table->json('agenda')->nullable()->comment('Array of agenda items');
            $table->json('resolutions_passed')->nullable()->comment('Array of resolutions approved');
            $table->longText('minutes_text')->nullable()->comment('Full verbatim or summary minutes');

            // Workflow
            $table->enum('status', ['draft', 'finalized'])->default('draft');
            $table->foreignId('finalized_by')->nullable()->references('id')->on('users')->nullOnDelete();
            $table->timestamp('finalized_at')->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('uuid');
            $table->index(['store_id', 'meeting_year']);
            $table->index(['store_id', 'status']);
            $table->index('aga_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aga_records');
    }
};
